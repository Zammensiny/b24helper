<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProxyTest extends TestCase
{
    public function test_api_requires_normalized_event_metadata(): void
    {
        config(['services.openai.proxy_token' => 'test-proxy-token']);

        $this->withToken('test-proxy-token')->postJson('/api/ai', ['prompt' => 'Привет'])
            ->assertUnprocessable();
    }

    public function test_unambiguous_policy_faq_skips_openai(): void
    {
        config([
            'services.openai.proxy_token' => 'test-proxy-token',
            'services.catalog.path' => base_path('missing-catalog-snapshot.jsonl'),
        ]);
        Http::fake();

        $this->withToken('test-proxy-token')->postJson('/api/ai', [
            'prompt' => 'Торг есть?',
            'event' => $this->textEvent(),
            'current_offer_id' => '38383',
        ])->assertOk()
            ->assertJsonPath('response', 'Цена фиксированная, без торга.')
            ->assertJsonPath('action', 'answer')
            ->assertJsonPath('evidence.0.source', 'store_policy')
            ->assertJsonPath('extracted_data', null);

        Http::assertNothingSent();
    }

    public function test_plain_greeting_skips_openai(): void
    {
        config(['services.openai.proxy_token' => 'test-proxy-token']);
        Http::fake();

        $this->withToken('test-proxy-token')->postJson('/api/ai', [
            'prompt' => 'Здравствуйте!',
            'event' => $this->textEvent(),
        ])->assertOk()
            ->assertJsonPath('response', 'Здравствуйте! Чем могу помочь?')
            ->assertJsonPath('action', 'answer');

        Http::assertNothingSent();
    }

    public function test_general_question_skips_catalog_and_uses_v2_prompt_and_schema(): void
    {
        $this->configureOpenAi();
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => $this->assistantJson(
                        'answer',
                        'Гидротрансформатор передаёт крутящий момент от двигателя к коробке передач.',
                        [],
                        []
                    )],
                ]],
            ]),
        ]);

        $this->withToken('test-proxy-token')->postJson('/api/ai', [
            'prompt' => 'Что делает гидротрансформатор?',
            'event' => $this->textEvent(),
        ])->assertOk()
            ->assertJsonPath('response', 'Гидротрансформатор передаёт крутящий момент от двигателя к коробке передач.')
            ->assertJsonPath('action', 'answer');

        Http::assertSent(function ($request) {
            $context = json_decode(explode("\n", $request['messages'][1]['content'], 2)[1] ?? '', true);
            return $request['response_format']['type'] === 'json_schema'
                && $request['model'] === 'gpt-4o-mini'
                && str_contains($request['messages'][0]['content'], 'ФОТО И ВЛОЖЕНИЯ')
                && ($context['products'] ?? null) === []
                && ($context['runtime']['catalog_searched'] ?? null) === false
                && ($context['store_policy']['hours']['opens'] ?? null) === '09:00';
        });
    }

    public function test_exact_oem_search_uses_snapshot_and_marks_stock_as_non_live(): void
    {
        $this->configureOpenAi();
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => $this->assistantJson(
                        'answer',
                        'В последней выгрузке указан ремкомплект CD4E Transnation по розничному прайсу 7 849 ₽. Остаток — снимок учёта, перед выездом нужно подтвердить.',
                        ['38383'],
                        [
                            ['source' => 'products', 'source_id' => 'xml:e4335ef6ebfe970eea2fd53958c376e7dc5dfb321853c1dfff0e05e8a6191830:offer:38383', 'field' => 'name, vendor, transmission_raw'],
                            ['source' => 'price_snapshot', 'source_id' => 'xml:e4335ef6ebfe970eea2fd53958c376e7dc5dfb321853c1dfff0e05e8a6191830:offer:38383', 'field' => 'price.amount'],
                            ['source' => 'inventory_snapshot', 'source_id' => 'xml:e4335ef6ebfe970eea2fd53958c376e7dc5dfb321853c1dfff0e05e8a6191830:offer:38383', 'field' => 'stocks'],
                        ]
                    )],
                ]],
            ]),
        ]);

        $this->withToken('test-proxy-token')->postJson('/api/ai', [
            'prompt' => 'Ищу T11202A для Ford CD4E. Есть и сколько стоит?',
            'event' => $this->textEvent(),
        ])->assertOk()
            ->assertJsonPath('action', 'answer')
            ->assertJsonPath('offer_ids.0', '38383');

        Http::assertSent(function ($request) {
            $context = json_decode(explode("\n", $request['messages'][1]['content'], 2)[1] ?? '', true);
            $product = $context['products'][0] ?? [];
            return ($context['runtime']['catalog_searched'] ?? false) === true
                && ($context['runtime']['search_status'] ?? null) === 'ok'
                && ($product['offer_id'] ?? null) === '38383'
                && ($product['source_id'] ?? null) === 'xml:e4335ef6ebfe970eea2fd53958c376e7dc5dfb321853c1dfff0e05e8a6191830:offer:38383'
                && ($context['price_snapshot'][0]['amount'] ?? null) === '7849'
                && ($context['price_snapshot'][0]['quote_allowed'] ?? false) === true
                && ($context['inventory_snapshot'][0]['is_live'] ?? true) === false
                && ($context['inventory_snapshot'][0]['quote_allowed'] ?? true) === false
                && ($context['snapshot_metadata']['is_live'] ?? true) === false
                && ($context['runtime']['snapshot_quote_allowed'] ?? true) === false;
        });
    }

    public function test_photo_is_routed_before_openai(): void
    {
        config(['services.openai.proxy_token' => 'test-proxy-token']);
        Http::fake();

        $this->withToken('test-proxy-token')->postJson('/api/ai', [
            'prompt' => 'Подойдёт?',
            'event' => array_merge($this->textEvent(), [
                'attachment_state' => 'complete',
                'attachments' => [['mime_type' => 'image/jpeg']],
            ]),
        ])->assertOk()
            ->assertJsonPath('action', 'handoff')
            ->assertJsonPath('request_handoff', true)
            ->assertJsonPath('handoff_reason', 'photo_requires_manager');

        Http::assertNothingSent();
    }

    public function test_unknown_attachment_metadata_is_resolved_before_handoff(): void
    {
        config(['services.openai.proxy_token' => 'test-proxy-token']);
        Http::fake();

        $this->withToken('test-proxy-token')->postJson('/api/ai', [
            'prompt' => 'Добрый день',
            'event' => array_merge($this->textEvent(), ['attachment_state' => 'unknown']),
        ])->assertAccepted()
            ->assertJsonPath('route', 'resolve_attachment_metadata')
            ->assertJsonPath('request_handoff', false)
            ->assertJsonPath('response', '');

        Http::assertNothingSent();
    }

    public function test_active_manager_takeover_silences_the_bot(): void
    {
        config(['services.openai.proxy_token' => 'test-proxy-token']);
        Http::fake();

        $this->withToken('test-proxy-token')->postJson('/api/ai', [
            'prompt' => 'Здравствуйте',
            'event' => array_merge($this->textEvent(), ['human_takeover' => true]),
        ])->assertOk()
            ->assertJsonPath('action', 'silent')
            ->assertJsonPath('response', '');

        Http::assertNothingSent();
    }

    public function test_gpt6_astra_receives_reasoning_compatible_parameters(): void
    {
        $this->configureOpenAi('gpt-6-astra');
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => $this->assistantJson('answer', 'Здравствуйте!', [], [])],
                ]],
            ]),
        ]);

        $this->withToken('test-proxy-token')->postJson('/api/ai', [
            'prompt' => 'Здравствуйте',
            'event' => $this->textEvent(),
        ])->assertOk()->assertJsonPath('response', 'Здравствуйте!');

        Http::assertSent(function ($request) {
            return $request['model'] === 'gpt-6-astra'
                && $request['reasoning_effort'] === 'low'
                && $request['max_completion_tokens'] === 1200
                && !isset($request['temperature'])
                && !isset($request['max_tokens']);
        });
    }

    public function test_endpoint_rejects_requests_without_proxy_token(): void
    {
        config(['services.openai.proxy_token' => 'test-proxy-token']);

        $this->postJson('/api/ai', ['prompt' => 'Привет', 'event' => $this->textEvent()])
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'Unauthorized.']);
    }

    private function configureOpenAi(string $model = 'gpt-4o-mini'): void
    {
        config([
            'services.openai.api_key' => 'test-openai-key',
            'services.openai.model' => $model,
            'services.openai.proxy_token' => 'test-proxy-token',
            'services.catalog.path' => resource_path('ai/snapshot/catalog_snapshot.jsonl'),
            'services.catalog.manifest_path' => resource_path('ai/snapshot/manifest.json'),
            'services.catalog.metadata_path' => resource_path('ai/snapshot/snapshot_metadata.json'),
        ]);
    }

    private function textEvent(): array
    {
        return [
            'is_customer_message' => true,
            'message_id' => 'test-message-1',
            'attachment_state' => 'none',
            'attachments' => [],
            'human_takeover' => false,
            'already_processed' => false,
            'handoff_status' => 'unavailable',
        ];
    }

    private function assistantJson(string $action, string $reply, array $offerIds, array $evidence): string
    {
        return json_encode([
            'action' => $action,
            'reply' => $reply,
            'offer_ids' => $offerIds,
            'evidence' => $evidence,
            'handoff_reason' => null,
            'manager_summary' => null,
            'extracted_data' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}