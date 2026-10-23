"""Pre-model routing for NORMALIZED Bitrix events; no API calls or sending.

The real connector adapter must populate attachment_state from actual metadata.
It may not assume that missing attachment metadata means no image.
Return values are decisions; the host executes and confirms handoff separately.
"""
from pathlib import PurePosixPath
from urllib.parse import urlsplit, unquote

IMAGE_EXTENSIONS = {'.jpg', '.jpeg', '.png', '.webp', '.gif', '.bmp', '.tif', '.tiff', '.heic', '.heif', '.avif', '.svg'}


def _media_type(attachment):
    if not isinstance(attachment, dict):
        return 'unknown'
    mime = str(attachment.get('mime_type') or '').split(';')[0].strip().lower()
    kind = str(attachment.get('kind') or '').strip().lower()
    names = [attachment.get('filename'), attachment.get('url')]
    extensions = {PurePosixPath(unquote(urlsplit(str(x)).path)).suffix.lower() for x in names if x}
    if mime.startswith('image/') or kind in {'image', 'photo', 'screenshot'} or extensions & IMAGE_EXTENSIONS:
        return 'image'
    if mime == 'application/pdf' or '.pdf' in extensions:
        return 'pdf'
    return 'unknown'


def _handoff(reason, status):
    accepted = status == 'accepted'
    if reason == 'photo_requires_manager':
        reply = 'Фото передал менеджеру.' if accepted else 'Фото нужно проверить менеджеру.'
    elif reason == 'document_requires_manager':
        reply = 'Документ передал менеджеру.' if accepted else 'Документ нужно проверить менеджеру.'
    else:
        reply = 'Вложение передал менеджеру.' if accepted else 'Вложение нужно проверить менеджеру.'
    return {'route': 'handoff', 'reason': reason, 'call_model': False,
            'pause_automation': True, 'request_handoff': status not in {'accepted', 'pending'},
            'reply': reply if status != 'pending' else ''}


def route_event(event):
    """event is a mapping controlled by the adapter, not model-generated fields.

    Required adapter facts: is_customer_message, attachment_state, attachments.
    attachment_state: none | complete | pending | unknown.
    State facts: human_takeover, already_processed, handoff_status, metadata_timed_out.
    This is deliberately a pure decision function, not a persistence engine.
    """
    if event.get('human_takeover') is True or event.get('already_processed') is True:
        return {'route': 'silent', 'call_model': False, 'reply': ''}
    if event.get('is_customer_message') is not True:
        return {'route': 'ignore_noncustomer_or_unverified', 'call_model': False, 'reply': ''}
    attachments = event.get('attachments')
    if attachments is None:
        attachments = []
    if not isinstance(attachments, list):
        return _handoff('unknown_attachment', event.get('handoff_status'))
    types = [_media_type(x) for x in attachments]
    if event.get('image_present') is True or 'image' in types:
        return _handoff('photo_requires_manager', event.get('handoff_status'))
    if attachments:
        return _handoff('document_requires_manager' if 'pdf' in types else 'unknown_attachment',
                        event.get('handoff_status'))
    state = event.get('attachment_state', 'unknown')
    if state not in {'none', 'complete'}:
        if event.get('metadata_timed_out') is True:
            return _handoff('attachment_metadata_unavailable', event.get('handoff_status'))
        return {'route': 'resolve_attachment_metadata', 'call_model': False, 'reply': '',
                'on_timeout': 'handoff', 'pause_automation': True}
    if event.get('handoff_status') in {'accepted', 'pending'}:
        return {'route': 'silent', 'call_model': False, 'reply': ''}
    # The host now applies simple FAQ templates or routes a text question to LLM.
    return {'route': 'text_pipeline', 'call_model': 'only_if_needed', 'reply': None}
