<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class CatalogSnapshotSearch
{
    private ?array $records = null;
    private ?array $metadata = null;

    public function isLikelyProductQuery(string $query): bool
    {
        $tokens = $this->tokens($query);
        if ($this->identifiers($tokens) !== []) {
            return true;
        }

        $hasProductTerm = preg_match(
            '/(?<![\p{L}\p{N}])(запчаст[\p{L}\p{N}_]*|детал[\p{L}\p{N}_]*|товар[\p{L}\p{N}_]*|артикул[\p{L}\p{N}_]*|oem|акпп|кпп|коробк[\p{L}\p{N}_]*|вариатор[\p{L}\p{N}_]*|ремкомплект[\p{L}\p{N}_]*|фильтр[\p{L}\p{N}_]*|насос[\p{L}\p{N}_]*|рем[\p{L}\p{N}_]*|цеп[\p{L}\p{N}_]*|соленоид[\p{L}\p{N}_]*|гидроблок[\p{L}\p{N}_]*|гидротрансформатор[\p{L}\p{N}_]*|мехатрон[\p{L}\p{N}_]*|сцеплен[\p{L}\p{N}_]*|фрикцион[\p{L}\p{N}_]*|барабан[\p{L}\p{N}_]*|планетар[\p{L}\p{N}_]*)(?![\p{L}\p{N}])/iu',
            $query
        ) === 1;
        $hasProductIntent = preg_match(
            '/(?<![\p{L}\p{N}])(найти|найдите|ищу|нужен|нужна|нужно|подберите|подобрать|подойдет|подойдёт|совместим[\p{L}]*|купить|заказать|наличие|в наличии|цена|стоимость|сколько стоит|характеристик[\p{L}]*|размер[\p{L}]*|комплект[\p{L}]*|состав|производител[\p{L}]*|состояни[\p{L}]*)(?![\p{L}\p{N}])/iu',
            $query
        ) === 1;

        return $hasProductTerm && $hasProductIntent;
    }

    public function searchByOfferId(string $offerId): array
    {
        try {
            foreach ($this->loadRecords() as $record) {
                if ((string) $record['offer_id'] === $offerId) {
                    return $this->buildResult([$record], 'ok');
                }
            }
        } catch (\Throwable $exception) {
            return ['status' => 'error', 'products' => [], 'prices' => [], 'inventory' => []];
        }

        return $this->buildResult([], 'no_match');
    }

    public function search(string $query, int $limit = 3): array
    {
        try {
            $records = $this->loadRecords();
        } catch (Throwable $exception) {
            return ['status' => 'error', 'products' => [], 'prices' => [], 'inventory' => []];
        }

        $tokens = $this->tokens($query);
        $identifiers = $this->identifiers($tokens);
        $exact = [];

        foreach ($records as $record) {
            foreach ($record['numbers_from_card'] ?? [] as $number) {
                foreach ($identifiers as $identifier) {
                    if ($this->normalizeNumber((string) $number) === $this->normalizeNumber($identifier)) {
                        $exact[$record['offer_id']] = $record;
                        break 2;
                    }
                }
            }

            $catalogCodes = array_merge(
                $this->tokens((string) $record['name']),
                $this->tokens($this->stringify($record['transmission_raw'] ?? []))
            );
            foreach ($identifiers as $identifier) {
                foreach ($catalogCodes as $catalogCode) {
                    if ($this->normalizeNumber($catalogCode) === $this->normalizeNumber($identifier)) {
                        $exact[$record['offer_id']] = $record;
                        break 2;
                    }
                }
            }
        }

        if ($exact !== []) {
            return $this->buildResult(array_slice(array_values($exact), 0, $limit), 'ok');
        }

        $strictIdentifiers = array_filter($identifiers, function ($identifier) {
            $normalized = $this->normalizeNumber($identifier);
            $hasLetters = preg_match('/\p{L}/u', $normalized) === 1;
            $hasNumbers = preg_match('/\p{N}/u', $normalized) === 1;
            return mb_strlen($normalized) >= 4 && (($hasLetters && $hasNumbers) || !$hasLetters);
        });
        if ($strictIdentifiers !== []) {
            return $this->buildResult([], 'no_match');
        }

        $terms = array_values(array_unique(array_filter($tokens, function ($token) {
            return mb_strlen($token) >= 3 && !in_array(mb_strtolower($token), [
                'для', 'или', 'как', 'что', 'это', 'есть', 'мне', 'под', 'над', 'при', 'про',
                'акпп', 'кпп', 'коробка', 'коробки', 'передач', 'трансмиссия', 'товар', 'номер',
                'наличии', 'цена', 'цену', 'сколько', 'остаток', 'склад', 'доставка', 'оплата',
                'подойдет', 'подойдёт', 'совместимость', 'состояние', 'новое', 'новый',
                'the', 'and', 'for', 'with', 'from', 'this', 'that',
            ], true);
        })));

        if ($terms === []) {
            return $this->buildResult([], 'no_match');
        }

        $ranked = [];
        foreach ($records as $record) {
            $name = mb_strtolower((string) $record['name']);
            $vendor = mb_strtolower((string) $record['vendor']);
            $numbers = mb_strtolower($this->stringify($record['numbers_from_card'] ?? []));
            $transmission = mb_strtolower($this->stringify($record['transmission_raw'] ?? []));
            $fitment = mb_strtolower($this->stringify($record['fitment_raw'] ?? []));
            $extra = mb_strtolower($this->stringify($record['extra_raw'] ?? []));
            $description = mb_strtolower($this->relevantDescription((string) ($record['description_raw'] ?? '')));
            $score = 0;

            foreach ($terms as $term) {
                $term = mb_strtolower($term);
                $score += $this->containsTerm($name, $term) ? 12 : 0;
                $score += $this->containsTerm($numbers, $term) ? 10 : 0;
                $score += $this->containsTerm($transmission, $term) ? 9 : 0;
                $score += $this->containsTerm($fitment, $term) ? 4 : 0;
                $score += $this->containsTerm($vendor, $term) ? 3 : 0;
                $score += $this->containsTerm($extra, $term) ? 2 : 0;
                $score += $this->containsTerm($description, $term) ? 1 : 0;
            }

            if ($score > 0) {
                $ranked[] = ['score' => $score, 'record' => $record];
            }
        }

        usort($ranked, fn ($left, $right) => $right['score'] <=> $left['score']);
        $matches = array_map(fn ($item) => $item['record'], array_slice($ranked, 0, $limit));

        return $this->buildResult($matches, $matches === [] ? 'no_match' : 'ok');
    }

    private function loadRecords(): array
    {
        if ($this->records !== null) {
            return $this->records;
        }

        $path = config('services.catalog.path');
        if (!is_string($path) || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Catalog snapshot is not readable.');
        }

        $manifestPath = config('services.catalog.manifest_path');
        if (is_string($manifestPath) && is_file($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (!is_array($manifest) || ($manifest['complete'] ?? false) !== true) {
                throw new RuntimeException('Catalog snapshot manifest is incomplete.');
            }
        } else {
            throw new RuntimeException('Catalog snapshot manifest is missing.');
        }

        $metadataPath = config('services.catalog.metadata_path');
        if (!is_string($metadataPath) || !is_file($metadataPath)) {
            throw new RuntimeException('Catalog snapshot metadata is missing.');
        }
        $metadata = json_decode(file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($metadata) || ($metadata['is_live'] ?? true) !== false) {
            throw new RuntimeException('Catalog snapshot metadata is invalid.');
        }
        $this->metadata = $metadata;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || $lines === []) {
            throw new RuntimeException('Catalog snapshot is empty.');
        }

        $records = [];
        foreach ($lines as $line) {
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($record)
                || !isset($record['source_id'], $record['snapshot_source_id'], $record['offer_id'], $record['name'], $record['vendor'])
                || !is_array($record['price'] ?? null)
                || !is_array($record['stocks'] ?? null)
            ) {
                throw new RuntimeException('Catalog snapshot contains an invalid record.');
            }
            $records[] = $record;
        }
        if (count($records) !== (int) ($manifest['record_count'] ?? -1)) {
            throw new RuntimeException('Catalog snapshot count does not match manifest.');
        }

        $this->records = $records;
        return $this->records;
    }

    private function buildResult(array $records, string $status): array
    {
        $products = [];
        $prices = [];
        $inventory = [];
        $reviewRequired = [];

        foreach ($records as $record) {
            $sourceId = (string) $record['source_id'];
            $offerId = (string) $record['offer_id'];
            $reviewFlags = is_array($record['review_flags'] ?? null) ? $record['review_flags'] : [];
            $isPossibleTestItem = in_array('possible_test_item', $reviewFlags, true)
                || ($record['automatic_recommendation_allowed'] ?? true) === false;

            if ($isPossibleTestItem) {
                $reviewRequired[] = [
                    'source_id' => $sourceId,
                    'offer_id' => $offerId,
                    'reason' => 'manual_review_required',
                    'review_flags' => $reviewFlags,
                ];
                continue;
            }

            $products[] = [
                'source_id' => $sourceId,
                'snapshot_source_id' => (string) $record['snapshot_source_id'],
                'offer_id' => $offerId,
                'name' => $record['name'],
                'url' => $record['url'] ?? null,
                'vendor' => $record['vendor'],
                'oem_original' => $record['oem_original'] ?? [],
                'numbers_from_card' => $record['numbers_from_card'] ?? [],
                'state_raw' => $record['state_raw'] ?? [],
                'transmission_raw' => $record['transmission_raw'] ?? [],
                'fitment_raw' => $record['fitment_raw'] ?? [],
                'vehicle_raw' => $record['vehicle_raw'] ?? [],
                'extra_raw' => $record['extra_raw'] ?? [],
                'params_raw' => $this->relevantParams($record['params_raw'] ?? []),
                'description_relevant' => $this->relevantDescription((string) ($record['description_raw'] ?? '')),
                'review_flags' => $reviewFlags,
                'automatic_recommendation_allowed' => $record['automatic_recommendation_allowed'] ?? false,
                'technical_review_status' => $record['technical_review_status'] ?? 'not_reviewed',
            ];

            $price = $record['price'];
            $prices[] = [
                'source_id' => $sourceId,
                'snapshot_source_id' => (string) $record['snapshot_source_id'],
                'offer_id' => $offerId,
                'amount' => $price['amount'] ?? null,
                'currency' => ($price['currency_raw'] ?? null) === 'RUR' ? 'RUB' : ($price['currency_raw'] ?? null),
                'unit' => $price['unit'] ?? null,
                'status' => $price['status'] ?? 'unknown',
                'price_type' => $price['price_type'] ?? null,
                'price_type_source_id' => $price['price_type_source_id'] ?? null,
                'quote_allowed' => ($price['status'] ?? null) === 'positive'
                    && ($price['price_type'] ?? null) === 'retail'
                    && ($price['price_type_source_id'] ?? null) === ($this->metadata['price_type_source_id'] ?? null),
            ];

            $inventory[] = [
                'source_id' => $sourceId,
                'snapshot_source_id' => (string) $record['snapshot_source_id'],
                'offer_id' => $offerId,
                'stocks' => array_map(fn ($stock) => [
                    'warehouse_code' => $stock['code'] ?? null,
                    'city' => $stock['city_label'] ?? null,
                    'quantity' => $stock['quantity'] ?? null,
                    'status' => $stock['status'] ?? 'unknown',
                    'reservation_semantics' => $stock['reservation_semantics'] ?? 'unknown',
                ], $record['stocks']),
                'export_generated_at' => $this->metadata['export_generated_at_raw'] ?? null,
                'export_timezone' => $this->metadata['export_timezone'] ?? null,
                'inventory_checked_at' => $this->metadata['inventory_checked_at'] ?? null,
                'is_live' => $this->metadata['is_live'] ?? false,
                'quote_allowed' => false,
            ];
        }

        return [
            'status' => $status,
            'products' => $products,
            'prices' => $prices,
            'inventory' => $inventory,
            'review_required' => $reviewRequired,
            'snapshot_metadata' => [
                'source_id' => $this->metadata['source_id'] ?? null,
                'export_generated_at_raw' => $this->metadata['export_generated_at_raw'] ?? null,
                'export_timezone' => $this->metadata['export_timezone'] ?? null,
                'inventory_checked_at' => $this->metadata['inventory_checked_at'] ?? null,
                'is_live' => $this->metadata['is_live'] ?? false,
                'snapshot_age_limit_hours' => $this->metadata['snapshot_age_limit_hours'] ?? null,
                'reservation_semantics' => $this->metadata['reservation_semantics'] ?? 'unknown',
            ],
        ];
    }

    private function relevantParams($params): array
    {
        if (!is_array($params)) {
            return [];
        }

        $excluded = ['наличие', 'available'];
        return array_values(array_filter($params, function ($param) use ($excluded) {
            $name = mb_strtolower((string) ($param['name'] ?? ''));
            return !in_array($name, $excluded, true);
        }));
    }

    private function relevantDescription(string $description): string
    {
        if ($description === '') {
            return '';
        }

        $parts = preg_split('/(?=⚙|◆|⚠️|✅|☎|★)/u', $description) ?: [];
        $selected = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (!preg_match('/^(?:⚙|◆|⚠️|✅)\s*(.*)$/us', $part, $matches)) {
                continue;
            }

            $section = mb_strtolower($matches[1]);
            if (str_contains($section, 'назначение')
                || str_contains($section, 'применение')
                || str_contains($section, 'характеристик')
                || str_contains($section, 'артикул')
                || str_contains($section, 'oem')
                || str_contains($section, 'проверка узла')
                || str_contains($section, 'что сверить')
                || str_contains($section, 'условия и особенности поставки')) {
                $selected[] = $part;
            }
        }

        return implode("\n", $selected);
    }

    private function tokens(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[-.][\p{L}\p{N}]+)*/u', mb_strtolower($text), $matches);
        return $matches[0] ?? [];
    }

    private function identifiers(array $tokens): array
    {
        $identifiers = [];
        $count = count($tokens);

        for ($start = 0; $start < $count; $start++) {
            for ($length = 1; $length <= 4 && $start + $length <= $count; $length++) {
                $parts = array_slice($tokens, $start, $length);
                if (array_filter($parts, fn ($part) => preg_match('/^(19|20)\d{2}$/', $part)) !== []) {
                    continue;
                }
                $identifier = implode('', $parts);
                $hasLetters = preg_match('/\p{L}/u', $identifier) === 1;
                $hasNumbers = preg_match('/\p{N}/u', $identifier) === 1;
                $isLongNumber = preg_match('/^\p{N}{4,}$/u', $identifier) === 1
                    && !preg_match('/^(19|20)\d{2}$/', $identifier);

                if (($hasLetters && $hasNumbers) || $isLongNumber) {
                    $identifiers[] = $identifier;
                }
            }
        }

        return array_values(array_unique($identifiers));
    }

    private function normalizeNumber(string $number): string
    {
        return mb_strtoupper(preg_replace('/[\s-]+/u', '', trim($number)) ?? '');
    }

    private function stringify($value): string
    {
        if (is_array($value)) {
            return implode(' ', array_map(fn ($item) => $this->stringify($item), $value));
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        return '';
    }

    private function containsTerm(string $text, string $term): bool
    {
        return preg_match('/(?<![\p{L}\p{N}])' . preg_quote($term, '/') . '(?![\p{L}\p{N}])/u', $text) === 1;
    }
}