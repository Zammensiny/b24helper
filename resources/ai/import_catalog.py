#!/usr/bin/env python3
"""Offline importer. Python 3.10+. No network calls and no external dependencies.

Usage: python import_catalog.py 'export.xml' NEW_OUTPUT_DIRECTORY [--price-type retail --price-type-source POLICY_ID]
The output directory must not exist. Only a directory with manifest.json is complete.
The XML timestamp is export metadata, never a live inventory check.
"""
import argparse
from collections import Counter
from datetime import datetime, timezone
from decimal import Decimal, InvalidOperation
import hashlib
import json
from pathlib import Path
import re
import xml.etree.ElementTree as ET


def decimal_text(value):
    try:
        number = Decimal(value.strip())
        return format(number, 'f') if number.is_finite() else None
    except (InvalidOperation, AttributeError, ValueError):
        return None


def parse_catalog(path, price_type='unconfirmed', price_type_source_id=None):
    if price_type not in {'unconfirmed', 'retail'}:
        raise ValueError('Unsupported price type')
    if price_type != 'unconfirmed' and not price_type_source_id:
        raise ValueError('Confirmed price type requires a policy source ID')
    path = Path(path)
    if path.stat().st_size > 32 * 1024 * 1024:
        raise ValueError('XML exceeds the 32 MiB importer limit')
    data = path.read_bytes()
    # Deliberately accept only UTF-8 feeds, preventing UTF-16/32 from bypassing
    # the declaration check below. The supplied feed declares UTF-8.
    xml_text = data.decode('utf-8-sig')
    # This feed has an external DOCTYPE. ElementTree does not fetch that DTD.
    # Reject entity declarations, including internal expansion attacks.
    if re.search(r'<!\s*ENTITY\b', xml_text, re.I):
        raise ValueError('XML entity declarations are not allowed')
    root = ET.fromstring(xml_text)
    if root.tag != 'yml_catalog' or root.find('shop/offers') is None:
        raise ValueError('Expected yml_catalog/shop/offers')
    digest = hashlib.sha256(data).hexdigest()
    source_id = 'xml:' + digest
    rows, warehouses, seen = [], {}, set()
    for offer in root.findall('shop/offers/offer'):
        offer_id = offer.get('id')
        if not offer_id or offer_id in seen:
            raise ValueError('Missing or duplicate offer ID: ' + str(offer_id))
        seen.add(offer_id)
        params = [{'name': x.get('name'), 'value': x.text or '', 'attributes': dict(x.attrib)}
                  for x in offer.findall('param')]

        def values(name):
            return [x['value'] for x in params if x['name'] == name]

        flags = []
        price_raw = offer.findtext('price')
        price = decimal_text(price_raw)
        price_status = 'positive' if price is not None and Decimal(price) > 0 else 'needs_review'
        if price_status == 'needs_review':
            flags.append('invalid_or_nonpositive_price')
        stocks, stock_codes = [], set()
        for stock in offer.findall('stocks/stock'):
            code = stock.get('code')
            if not code or code in stock_codes:
                raise ValueError('Missing or duplicate warehouse in offer ' + offer_id)
            stock_codes.add(code)
            identity = {'code': code, 'city_label': stock.get('name'), 'address_raw': stock.get('address')}
            if code in warehouses and warehouses[code] != identity:
                raise ValueError('Conflicting warehouse identity for ' + code)
            warehouses[code] = identity
            quantity = decimal_text(stock.text)
            if quantity is None:
                status = 'invalid'
            elif Decimal(quantity) < 0:
                status = 'negative'
            elif Decimal(quantity) == 0:
                status = 'zero'
            else:
                status = 'positive'
            if status in ('invalid', 'negative'):
                flags.append(status + '_stock:' + code)
            stocks.append({**identity, 'quantity_raw': stock.text, 'quantity': quantity,
                           'status': status, 'reservation_semantics': 'unknown', 'unit': None})
        if not stocks:
            flags.append('missing_stocks')
        name = offer.findtext('name') or ''
        if re.search(r'\bтест\b', name, re.I):
            flags.append('possible_test_item')
        oem_values = values('ОЕМ номер, маркировка запчасти')
        numbers = [v.strip() for raw in oem_values for v in re.split(r'[,;]', raw) if v.strip()]
        description = offer.findtext('description') or ''
        rows.append({
            'source_id': source_id + ':offer:' + offer_id,
            'snapshot_source_id': source_id,
            'offer_id': offer_id,
            'name': name,
            'url': offer.findtext('url'),
            'category_id_raw': offer.findtext('categoryId'),
            'vendor': offer.findtext('vendor'),
            'oem_original': oem_values,
            'numbers_from_card': numbers,
            'state_raw': values('Состояние запчасти'),
            'transmission_raw': values('Маркировка АКПП, CVT, DSG'),
            'fitment_raw': values('Автомобили, которым данная запчасть подходит'),
            'vehicle_raw': values('Марка и модель авто'),
            'extra_raw': values('Дополнительная информация'),
            'price': {'raw': price_raw, 'amount': price, 'currency_raw': offer.findtext('currencyId'),
                      'status': price_status, 'price_type': price_type,
                      'price_type_source_id': price_type_source_id, 'unit': None},
            'stocks': stocks,
            'available_attribute_raw': offer.get('available'),
            'description_raw': description,
            'pictures': [x.text for x in offer.findall('picture')],
            'params_raw': params,
            'offer_attributes_raw': dict(offer.attrib),
            'review_flags': flags,
            'automatic_recommendation_allowed': 'possible_test_item' not in flags,
            'technical_review_status': 'not_reviewed',
            'is_live': False,
        })
    if not rows:
        raise ValueError('Empty offer list: do not publish this snapshot automatically')
    metadata = {
        'source_id': source_id,
        'source_filename': path.name,
        'source_sha256': digest,
        'export_generated_at_raw': root.get('date'),
        'export_timezone': None,
        'inventory_checked_at': None,
        'imported_at_utc': datetime.now(timezone.utc).isoformat(),
        'is_live': False,
        'shop_name': root.findtext('shop/name'),
        'shop_url': root.findtext('shop/url'),
        'warehouses': list(warehouses.values()),
        'record_count': len(rows),
        'declared_update_frequency': 'twice_per_week_per_owner',
        'snapshot_age_limit_hours': None,
        'reservation_semantics': 'unknown',
        'price_type': price_type,
        'price_type_source_id': price_type_source_id,
        'notes': [
            'Export generation time is not inventory check time.',
            'Do not infer the export timezone from the import machine.',
            'A missing offer is not proof of zero stock; publication completeness is unknown.',
            'Positive quantity does not prove unreserved quantity.',
            'Default read path is a qualified snapshot statement, never a live stock guarantee.',
        ],
    }
    return rows, metadata


def make_audit(rows, metadata):
    return {
        'records': len(rows),
        'warehouses': metadata['warehouses'],
        'available_attributes': dict(Counter(x['available_attribute_raw'] for x in rows)),
        'stock_status_counts': {
            w['code']: dict(Counter(s['status'] for x in rows for s in x['stocks'] if s['code'] == w['code']))
            for w in metadata['warehouses']
        },
        'flagged_offers': [{'offer_id': x['offer_id'], 'name': x['name'], 'flags': x['review_flags']}
                           for x in rows if x['review_flags']],
        'descriptions_mentioning_discounts': sum('скидк' in x['description_raw'].lower() for x in rows),
        'descriptions_mentioning_any_carrier': sum('любой транспортной' in x['description_raw'].lower() for x in rows),
        'technical_review_performed': False,
    }


def write_bundle(input_path, output_directory, price_type='unconfirmed', price_type_source_id=None):
    rows, metadata = parse_catalog(input_path, price_type, price_type_source_id)
    output = Path(output_directory)
    output.mkdir(parents=True, exist_ok=False)
    (output / 'catalog_snapshot.jsonl').write_text(
        ''.join(json.dumps(x, ensure_ascii=False) + '\n' for x in rows), encoding='utf-8')
    for name, value in [('snapshot_metadata.json', metadata), ('data_audit.json', make_audit(rows, metadata))]:
        (output / name).write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    files = {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in output.iterdir() if p.is_file()}
    (output / 'manifest.json').write_text(json.dumps({'complete': True, 'record_count': len(rows),
        'source_id': metadata['source_id'], 'files_sha256': files}, indent=2) + '\n', encoding='utf-8')
    return metadata


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('input_xml')
    parser.add_argument('new_output_directory')
    parser.add_argument('--price-type', choices=['unconfirmed', 'retail'], default='unconfirmed')
    parser.add_argument('--price-type-source', default=None)
    args = parser.parse_args()
    print(json.dumps(write_bundle(args.input_xml, args.new_output_directory,
                                 args.price_type, args.price_type_source), ensure_ascii=False, indent=2))
