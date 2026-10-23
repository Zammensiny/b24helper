import tempfile
from pathlib import Path
import unittest
from import_catalog import parse_catalog, write_bundle


class ImportTests(unittest.TestCase):
    def run_xml(self, offer, prefix='', **kwargs):
        xml = prefix + '<yml_catalog date="2026-10-23 16:51"><shop><offers>' + offer + '</offers></shop></yml_catalog>'
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'feed.xml'
            path.write_text(xml, encoding='utf-8')
            return parse_catalog(path, **kwargs)

    def offer(self, price='100', qty='2', ident='001', name='Деталь', stock=True):
        stock_xml = f'<stocks><stock code="kras" name="Краснодар" address="Адрес">{qty}</stock></stocks>' if stock else ''
        return f'<offer id="{ident}" available="true"><name>{name}</name><price>{price}</price><currencyId>RUR</currencyId><param name="ОЕМ номер, маркировка запчасти">00123-A, 0AM325025D</param>{stock_xml}</offer>'

    def test_ids_and_suffixes_are_preserved(self):
        rows, meta = self.run_xml(self.offer())
        self.assertEqual(rows[0]['offer_id'], '001')
        self.assertEqual(rows[0]['numbers_from_card'], ['00123-A', '0AM325025D'])
        self.assertIsNone(meta['export_timezone'])
        self.assertIsNone(meta['inventory_checked_at'])
        self.assertFalse(rows[0]['is_live'])

    def test_zero_price_is_not_free(self):
        row = self.run_xml(self.offer(price='0'))[0][0]
        self.assertEqual(row['price']['status'], 'needs_review')
        self.assertIn('invalid_or_nonpositive_price', row['review_flags'])

    def test_retail_confirmation_keeps_provenance(self):
        rows, meta = self.run_xml(self.offer(), price_type='retail', price_type_source_id='owner-policy-test')
        self.assertEqual(rows[0]['price']['price_type'], 'retail')
        self.assertEqual(meta['price_type_source_id'], 'owner-policy-test')
        self.assertFalse(rows[0]['is_live'])

    def test_retail_without_source_is_rejected(self):
        with self.assertRaises(ValueError):
            self.run_xml(self.offer(), price_type='retail')

    def test_nonfinite_price_is_rejected_as_a_value(self):
        for price in ['NaN', 'Infinity', '-1', 'bad']:
            with self.subTest(price=price):
                self.assertEqual(self.run_xml(self.offer(price=price))[0][0]['price']['status'], 'needs_review')

    def test_negative_and_missing_stock_are_not_zero(self):
        row = self.run_xml(self.offer(qty='-1'))[0][0]
        self.assertEqual(row['stocks'][0]['status'], 'negative')
        self.assertEqual(row['stocks'][0]['quantity'], '-1')
        missing = self.run_xml(self.offer(stock=False))[0][0]
        self.assertEqual(missing['stocks'], [])
        self.assertIn('missing_stocks', missing['review_flags'])

    def test_available_does_not_override_city_zero(self):
        row = self.run_xml(self.offer(qty='0'))[0][0]
        self.assertEqual(row['available_attribute_raw'], 'true')
        self.assertEqual(row['stocks'][0]['status'], 'zero')

    def test_test_item_is_retained_but_not_recommended(self):
        row = self.run_xml(self.offer(name='Герметик тест'))[0][0]
        self.assertFalse(row['automatic_recommendation_allowed'])

    def test_duplicate_offer_fails(self):
        with self.assertRaises(ValueError):
            self.run_xml(self.offer() + self.offer())

    def test_entities_fail_and_external_doctype_is_inert(self):
        with self.assertRaises(ValueError):
            self.run_xml(self.offer(), '<!DOCTYPE x [<!ENTITY a "payload">]>')
        rows, _ = self.run_xml(self.offer(), '<!DOCTYPE yml_catalog SYSTEM "https://example.invalid/shops.dtd">')
        self.assertEqual(len(rows), 1)

    def test_publication_requires_new_directory_and_manifest(self):
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / 'in.xml'
            source.write_text('<yml_catalog><shop><offers>' + self.offer() + '</offers></shop></yml_catalog>', encoding='utf-8')
            target = Path(directory) / 'out'
            write_bundle(source, target)
            self.assertTrue((target / 'manifest.json').exists())
            with self.assertRaises(FileExistsError):
                write_bundle(source, target)

    def test_empty_catalog_fails(self):
        with self.assertRaises(ValueError):
            self.run_xml('')


if __name__ == '__main__':
    unittest.main()
