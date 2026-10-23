<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class ProductCatalogSearch
{
    private ?array $products = null;

    public function search(string $query, int $limit = 6): array
    {
        try {
            $products = $this->loadProducts();
        } catch (Throwable $exception) {
            return ['status' => 'error', 'products' => []];
        }

        $tokens = $this->tokens($query);
        $identifiers = $this->identifiers($tokens);

        $exactMatches = [];
        foreach ($products as $product) {
            $numbers = $product['numbers_from_card'] ?? [];
            if (!is_array($numbers)) {
                $numbers = [];
            }
            if (empty($numbers) && !empty($product['oem_original'])) {
                $numbers = preg_split('/[,;]/u', $product['oem_original']) ?: [];
            }

            foreach ($identifiers as $identifier) {
                $normalizedQuery = $this->normalizeNumber($identifier);
                foreach ($numbers as $number) {
                    if ($normalizedQuery !== '' && $normalizedQuery === $this->normalizeNumber((string) $number)) {
                        $exactMatches[(string) $product['offer_id']] = $product;
                        break 2;
                    }
                }
            }
        }

        if ($exactMatches !== []) {
            return ['status' => 'ok', 'products' => $this->contextProducts(array_slice(array_values($exactMatches), 0, $limit))];
        }

        $titleMatches = [];
        foreach ($products as $product) {
            $titleIdentifiers = array_map($this->normalizeNumber(...), $this->tokens((string) $product['name']));
            foreach ($identifiers as $identifier) {
                if (in_array($this->normalizeNumber($identifier), $titleIdentifiers, true)) {
                    $titleMatches[(string) $product['offer_id']] = $product;
                    break;
                }
            }
        }

        if ($titleMatches !== []) {
            return ['status' => 'ok', 'products' => $this->contextProducts(array_slice(array_values($titleMatches), 0, $limit))];
        }

        if ($identifiers !== []) {
            return ['status' => 'no_match', 'products' => []];
        }

        $searchTerms = array_values(array_unique(array_filter($tokens, function ($token) {
            return mb_strlen($token) >= 3 && !in_array(mb_strtolower($token), [
                'для', 'или', 'как', 'что', 'это', 'есть', 'мне', 'под', 'над', 'при', 'про',
                'акпп', 'кпп', 'коробка', 'коробки', 'передач', 'трансмиссия', 'товар', 'номер',
                'наличии', 'цена', 'цену', 'сколько', 'остаток', 'склад', 'доставка', 'оплата',
                'подойдет', 'подойдёт', 'совместимость', 'состояние', 'новое', 'новый', 'б/у',
                'the', 'and', 'for', 'with', 'from', 'this', 'that',
            ], true);
        })));

        if ($searchTerms === []) {
            return ['status' => 'no_match', 'products' => []];
        }

        $ranked = [];
        foreach ($products as $product) {
            $name = mb_strtolower((string) ($product['name'] ?? ''));
            $vendor = mb_strtolower((string) ($product['vendor'] ?? ''));
            $numbers = mb_strtolower((string) ($product['oem_original'] ?? ''));
            $description = mb_strtolower((string) ($product['description'] ?? ''));
            $score = 0;

            foreach ($searchTerms as $term) {
                $term = mb_strtolower($term);
                if ($this->containsTerm($name, $term)) {
                    $score += 8;
                }
                if ($this->containsTerm($numbers, $term)) {
                    $score += 7;
                }
                if ($this->containsTerm($vendor, $term)) {
                    $score += 3;
                }
                if ($this->containsTerm($description, $term)) {
                    $score += 1;
                }
            }

            if ($score > 0) {
                $ranked[] = ['score' => $score, 'product' => $product];
            }
        }

        usort($ranked, fn ($left, $right) => $right['score'] <=> $left['score']);

        return [
            'status' => $ranked === [] ? 'no_match' : 'ok',
            'products' => $this->contextProducts(array_map(
                fn ($item) => $item['product'],
                array_slice($ranked, 0, $limit)
            )),
        ];
    }

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

    private function loadProducts(): array
    {
        if ($this->products !== null) {
            return $this->products;
        }

        $path = config('services.catalog.path');
        if (!is_string($path) || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Product catalog is not readable.');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || $lines === []) {
            throw new RuntimeException('Product catalog is empty.');
        }

        $products = [];
        foreach ($lines as $line) {
            $product = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($product)
                || !isset($product['offer_id'], $product['name'], $product['vendor'], $product['oem_original'], $product['url'], $product['description'])) {
                throw new RuntimeException('Product catalog contains an invalid record.');
            }
            $products[] = $product;
        }

        $this->products = $products;
        return $this->products;
    }

    private function tokens(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[-.][\p{L}\p{N}]+)*/u', mb_strtolower($text), $matches);
        return $matches[0] ?? [];
    }

    private function contextProducts(array $products): array
    {
        return array_map(function ($product) {
            return [
                'offer_id' => (string) $product['offer_id'],
                'name' => $product['name'],
                'vendor' => $product['vendor'],
                'oem_original' => $product['oem_original'],
                'numbers_from_card' => $product['numbers_from_card'] ?? [],
                'url' => $product['url'],
                'description' => $this->relevantDescription((string) $product['description']),
                'review_flags' => $product['review_flags'] ?? [],
            ];
        }, $products);
    }

    private function relevantDescription(string $description): string
    {
        $lines = preg_split('/\R/u', $description) ?: [];
        $selected = [];
        $includeSection = false;

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if ($index < 5 && preg_match('/^Состояние\s*[—:-]/u', $trimmed)) {
                $selected[] = $trimmed;
                continue;
            }

            if (preg_match('/^(?:⚙|◆|⚠️|✅|☎|★)\s*(.+)$/u', $trimmed, $matches)) {
                $heading = mb_strtolower($matches[1]);
                $includeSection = str_contains($heading, 'назначение')
                    || str_contains($heading, 'применение')
                    || str_contains($heading, 'характеристик')
                    || str_contains($heading, 'артикул')
                    || str_contains($heading, 'oem')
                    || str_contains($heading, 'проверка узла')
                    || str_contains($heading, 'что сверить')
                    || str_contains($heading, 'условия и особенности поставки');

                if ($includeSection) {
                    $selected[] = $trimmed;
                }
                continue;
            }

            if (!$includeSection || preg_match('/^Доступность\s*[—:-]/u', $trimmed)) {
                continue;
            }

            $selected[] = $trimmed;
        }

        return implode("\n", $selected);
    }

    private function identifiers(array $tokens): array
    {
        $identifiers = [];
        $count = count($tokens);

        for ($start = 0; $start < $count; $start++) {
            for ($length = 1; $length <= 4 && $start + $length <= $count; $length++) {
                $identifier = implode('', array_slice($tokens, $start, $length));
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

    private function containsTerm(string $text, string $term): bool
    {
        return preg_match('/(?<![\p{L}\p{N}])' . preg_quote($term, '/') . '(?![\p{L}\p{N}])/u', $text) === 1;
    }
}