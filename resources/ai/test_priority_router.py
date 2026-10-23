import unittest
from priority_router import route_event


class PriorityRouterTests(unittest.TestCase):
    def event(self, **kwargs):
        return {'is_customer_message': True, 'attachment_state': 'none', 'attachments': [], **kwargs}

    def test_photo_mime_outranks_hours_caption(self):
        out = route_event(self.event(text='До скольки?', attachments=[{'mime_type':'image/jpeg'}]))
        self.assertEqual(out['reason'], 'photo_requires_manager')
        self.assertFalse(out['call_model'])
        self.assertTrue(out['pause_automation'])

    def test_extension_recognizes_photo_sent_as_document(self):
        for ext in ['JPG', 'heic', 'png', 'AVIF']:
            with self.subTest(ext=ext):
                out=route_event(self.event(attachments=[{'mime_type':'application/octet-stream','filename':'part.'+ext}]))
                self.assertEqual(out['reason'], 'photo_requires_manager')

    def test_image_url_query_is_handled_without_fetching(self):
        out=route_event(self.event(attachments=[{'url':'https://example.invalid/a%20b.jpg?download=1'}]))
        self.assertEqual(out['reason'], 'photo_requires_manager')

    def test_mixed_files_still_route_photo(self):
        out=route_event(self.event(attachments=[{'filename':'receipt.pdf'},{'kind':'photo'}]))
        self.assertEqual(out['reason'],'photo_requires_manager')

    def test_pdf_receipt_never_confirms_payment(self):
        out=route_event(self.event(text='Оплатил', attachments=[{'filename':'receipt.pdf'}]))
        self.assertEqual(out['reason'], 'document_requires_manager')
        self.assertNotIn('получена',out['reply'])
        self.assertFalse(out['call_model'])

    def test_unknown_file_is_not_sent_to_model(self):
        out=route_event(self.event(attachments=[{'id':'f1'}]))
        self.assertEqual(out['route'],'handoff')
        self.assertFalse(out['call_model'])

    def test_missing_metadata_is_resolved_then_handed_off_on_timeout(self):
        out=route_event(self.event(attachment_state='unknown'))
        self.assertEqual(out['route'],'resolve_attachment_metadata')
        out=route_event(self.event(attachment_state='pending',metadata_timed_out=True))
        self.assertEqual(out['route'],'handoff')
        self.assertFalse(out['call_model'])

    def test_human_takeover_always_silent(self):
        out=route_event(self.event(human_takeover=True,attachments=[{'kind':'photo'}]))
        self.assertEqual(out['route'],'silent')
        self.assertEqual(out['reply'],'')

    def test_noncustomer_and_duplicate_are_not_answered(self):
        self.assertEqual(route_event(self.event(is_customer_message=False))['route'],'ignore_noncustomer_or_unverified')
        self.assertEqual(route_event(self.event(already_processed=True))['route'],'silent')

    def test_handoff_language_depends_on_result(self):
        failed=route_event(self.event(handoff_status='failed',attachments=[{'kind':'photo'}]))
        accepted=route_event(self.event(handoff_status='accepted',attachments=[{'kind':'photo'}]))
        self.assertNotIn('передал',failed['reply'].lower())
        self.assertIn('передал',accepted['reply'].lower())
        self.assertFalse(accepted['request_handoff'])

    def test_pending_handoff_does_not_repeat_notification(self):
        out=route_event(self.event(handoff_status='pending',attachments=[{'kind':'photo'}]))
        self.assertFalse(out['request_handoff'])
        self.assertEqual(out['reply'],'')

    def test_text_word_photo_does_not_fake_attachment(self):
        out=route_event(self.event(text='Фото у вас есть?'))
        self.assertEqual(out['route'],'text_pipeline')

    def test_normal_text_can_use_fast_pipeline(self):
        self.assertEqual(route_event(self.event(text='Торг есть?'))['route'],'text_pipeline')


if __name__=='__main__':
    unittest.main()
