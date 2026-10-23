<?php

namespace App\Services;

use RuntimeException;

class StorePolicyContext
{
    private ?array $policy = null;

    public function quickAnswer(string $query, bool $productQuery): ?array
    {
        $normalized = mb_strtolower(trim($query, " \t\n\r\0\x0B.!?"));
        if (in_array($normalized, ['привет', 'здравствуйте', 'добрый день', 'доброе утро', 'добрый вечер'], true)) {
            return $this->answer('Здравствуйте! Чем могу помочь?', 'style.greeting');
        }
        if (in_array($normalized, ['спасибо', 'благодарю', 'спасибо большое'], true)) {
            return $this->answer('Пожалуйста!', 'style.thanks');
        }

        if ($productQuery || $this->mentionsWarehouse($query) || $this->policyIntentCount($query) !== 1) {
            return null;
        }

        if (preg_match('/(торг|скидк|крайн\w+ цен|подешевле)/iu', $query)) {
            return $this->answer('Цена фиксированная, без торга.', 'bargaining.reply');
        }

        if (preg_match('/(доставк\w* авито|авито[- ]доставк\w*)/iu', $query)) {
            return $this->answer('Пока отправляем СДЭКом. Доставку Авито ещё не подключили.', 'delivery.avito');
        }

        if (preg_match('/(под заказ|на заказ привез|заказать отсутствующ)/iu', $query)) {
            return $this->answer('Под заказ не работаем.', 'special_orders');
        }

        if (preg_match('/(реквизит|куда переводить|как оплатить|способ оплаты)/iu', $query)) {
            return $this->answer('Реквизиты и способ оплаты нужно уточнить у менеджера.', 'payment_methods');
        }

        if (preg_match('/(гарант|возврат|претензи)/iu', $query)) {
            return $this->answer('Условия по гарантии или возврату нужно уточнить у менеджера по конкретному товару.', 'warranty_terms');
        }

        if (preg_match('/(сдэк|сдек)/iu', $query)
            && preg_match('/(отправ|достав|посылк|высыла)/iu', $query)) {
            return $this->answer('Да, отправляем СДЭКом.', 'delivery.cdek');
        }

        if (preg_match('/(самовывоз|заберу сам|забрать самому)/iu', $query)) {
            return $this->answer('Да, самовывоз есть. С какого склада удобнее забрать?', 'pickup');
        }

        if (preg_match('/(до скольки|график работ|режим работ|во сколько откры|во сколько закры|работаете каждый день)/iu', $query)) {
            return $this->answer('Работаем каждый день с 9:00 до 18:00 по местному времени склада.', 'hours');
        }

        return null;
    }

    private function policyIntentCount(string $query): int
    {
        $patterns = [
            '/торг|скидк|крайн\w+ цен|подешевле/iu',
            '/доставк\w* авито|авито[- ]доставк\w*/iu',
            '/под заказ|на заказ привез|заказать отсутствующ/iu',
            '/сдэк|сдек/iu',
            '/самовывоз|заберу сам|забрать самому/iu',
            '/до скольки|график работ|режим работ|во сколько откры|во сколько закры|работаете каждый день/iu',
            '/налич|остаток|есть ли|забрать сегодня/iu',
            '/сколько стоит|цена|стоимость/iu',
            '/реквизит|куда переводить|как оплатить|способ оплаты/iu',
            '/гарант|возврат|претензи/iu',
        ];
        $count = 0;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query)) {
                $count++;
            }
        }
        return $count;
    }

    private function mentionsWarehouse(string $query): bool
    {
        return preg_match('/новосибирск|новосиб|\bнск\b|краснодар|\bкрд\b|яблоновский|екатеринбург|\bекб\b/iu', $query) === 1;
    }

    public function contextFor(string $query): array
    {
        $policy = $this->load();
        $context = [
            'source_id' => $policy['source_id'] ?? null,
            'version' => $policy['version'] ?? null,
        ];

        $selectors = [
            'bargaining' => '/торг|скидк|цен/iu',
            'hours' => '/до скольки|график|режим работ|работаете|открыты|закрыты/iu',
            'pickup' => '/самовывоз|забрать|заберу/iu',
            'delivery' => '/достав|сдэк|сдек|авито/iu',
            'special_orders' => '/под заказ|на заказ привез|заказать отсутствующ/iu',
            'pricing' => '/цен|стоимост|сколько стоит/iu',
            'inventory' => '/налич|склад|есть ли|остаток|забрать сегодня/iu',
            'checkout' => '/заказ|оформ|получател|фио|пункт|пвз/iu',
            'payment_methods' => '/оплат|реквизит|перевод/iu',
            'warranty_terms' => '/гарант|возврат|претензи/iu',
            'warehouses' => '/склад|самовывоз|адрес|краснодар|новосибирск|екатеринбург|екб|нск/iu',
        ];

        foreach ($selectors as $key => $pattern) {
            if (preg_match($pattern, $query) && array_key_exists($key, $policy)) {
                $context[$key] = $policy[$key];
            }
        }

        return $context;
    }

    public function sourceId(): string
    {
        return (string) ($this->load()['source_id'] ?? 'owner-policy-2026-10-23-v3');
    }

    private function answer(string $reply, string $field): array
    {
        return [
            'action' => 'answer',
            'reply' => $reply,
            'offer_ids' => [],
            'evidence' => str_starts_with($field, 'style.') ? [] : [[
                    'source' => 'store_policy',
                    'source_id' => $this->sourceId(),
                    'field' => $field,
                ]],
            'handoff_reason' => null,
            'manager_summary' => null,
            'extracted_data' => null,
        ];
    }

    private function load(): array
    {
        if ($this->policy !== null) {
            return $this->policy;
        }

        $path = config('services.store_policy.path');
        if (!is_string($path) || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Store policy is not readable.');
        }

        $policy = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($policy) || !isset($policy['source_id'], $policy['version'])) {
            throw new RuntimeException('Store policy has an invalid format.');
        }

        $this->policy = $policy;
        return $this->policy;
    }
}