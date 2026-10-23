<?php

namespace App\Services;

class PriorityMessageRouter
{
    public function route(array $event): array
    {
        if (($event['human_takeover'] ?? false) || ($event['already_processed'] ?? false)) {
            return ['route' => 'silent', 'reason' => 'human_or_duplicate', 'reply' => ''];
        }

        if (($event['is_customer_message'] ?? false) !== true) {
            return ['route' => 'ignore', 'reason' => 'not_customer_message', 'reply' => ''];
        }

        $attachments = $event['attachments'] ?? null;
        if (!is_array($attachments)) {
            return $this->handoff('unknown_attachment', $event['handoff_status'] ?? null);
        }

        $types = array_map(fn ($attachment) => $this->attachmentType($attachment), $attachments);
        if (($event['image_present'] ?? false) === true || in_array('image', $types, true)) {
            return $this->handoff('photo_requires_manager', $event['handoff_status'] ?? null);
        }
        if ($attachments !== []) {
            return $this->handoff(
                in_array('pdf', $types, true) ? 'document_requires_manager' : 'unknown_attachment',
                $event['handoff_status'] ?? null
            );
        }

        $attachmentState = $event['attachment_state'] ?? 'unknown';
        if (!in_array($attachmentState, ['none', 'complete'], true)) {
            if (($event['metadata_timed_out'] ?? false) === true) {
                return $this->handoff('attachment_metadata_unavailable', $event['handoff_status'] ?? null);
            }

            return [
                'route' => 'resolve_attachment_metadata',
                'reason' => 'attachment_metadata_required',
                'reply' => '',
                'request_handoff' => false,
                'pause_automation' => true,
            ];
        }

        if (in_array($event['handoff_status'] ?? null, ['accepted', 'pending'], true)) {
            return ['route' => 'silent', 'reason' => 'handoff_active', 'reply' => ''];
        }

        return ['route' => 'text_pipeline', 'reason' => null, 'reply' => null];
    }

    private function attachmentType($attachment): string
    {
        if (!is_array($attachment)) {
            return 'unknown';
        }

        $mime = strtolower(trim(explode(';', (string) ($attachment['mime_type'] ?? ''))[0]));
        $kind = strtolower(trim((string) ($attachment['kind'] ?? '')));
        $imageKinds = ['image', 'photo', 'screenshot'];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'heif', 'avif', 'svg'];

        if (str_starts_with($mime, 'image/') || in_array($kind, $imageKinds, true)) {
            return 'image';
        }
        if ($mime === 'application/pdf') {
            return 'pdf';
        }

        foreach (['filename', 'url'] as $field) {
            $path = parse_url((string) ($attachment[$field] ?? ''), PHP_URL_PATH);
            $extension = strtolower(pathinfo(rawurldecode((string) $path), PATHINFO_EXTENSION));
            if (in_array($extension, $imageExtensions, true)) {
                return 'image';
            }
            if ($extension === 'pdf') {
                return 'pdf';
            }
        }

        return 'unknown';
    }

    private function handoff(string $reason, ?string $status): array
    {
        $accepted = $status === 'accepted';
        $pending = $status === 'pending';
        $reply = match ($reason) {
            'photo_requires_manager' => $accepted ? 'Фото передал менеджеру.' : 'Фото нужно проверить менеджеру.',
            'document_requires_manager' => $accepted ? 'Документ передал менеджеру.' : 'Документ нужно проверить менеджеру.',
            default => 'Вложение нужно проверить менеджеру.',
        };

        return [
            'route' => 'handoff',
            'reason' => $reason,
            'reply' => $pending ? '' : $reply,
            'request_handoff' => !$accepted && !$pending,
            'pause_automation' => true,
        ];
    }
}