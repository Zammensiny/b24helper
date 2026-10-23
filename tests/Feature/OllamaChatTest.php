<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaChatTest extends TestCase
{
    public function test_chat_page_is_available(): void
    {
        $this->get('/test-chat')->assertOk()->assertSee('Чат с Ollama');
    }

    public function test_plain_chat_uses_short_rules_and_returns_text(): void
    {
        config([
            'services.ollama.url' => 'http://127.0.0.1:11434',
            'services.ollama.model' => 'test-model',
        ]);

        Http::fakeSequence()->push([
            'message' => ['content' => 'Здравствуйте! Чем могу помочь?'],
        ]);

        $this->postJson('/test-chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Добрый день'],
            ],
        ])->assertOk()->assertExactJson(['reply' => 'Здравствуйте! Чем могу помочь?']);

        Http::assertSent(function ($request) {
            $messages = $request['messages'];

            return $request->url() === 'http://127.0.0.1:11434/api/chat'
                && $request['model'] === 'test-model'
                && $request['stream'] === false
                && $request['format'] === null
                && $request['options']['num_predict'] === 256
                && $messages[0]['role'] === 'system'
                && str_contains($messages[0]['content'], 'Не выдумывай товары')
                && $messages[1]['role'] === 'user'
                && $messages[1]['content'] === 'Добрый день';
        });
    }

    public function test_recent_conversation_is_passed_without_catalog_context(): void
    {
        Http::fakeSequence()->push([
            'message' => ['content' => 'Понял, уточню.'],
        ]);

        $this->postJson('/test-chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'У меня Ford Escape'],
                ['role' => 'assistant', 'content' => 'Какой код коробки?'],
                ['role' => 'user', 'content' => 'Пока не знаю'],
            ],
        ])->assertOk()->assertExactJson(['reply' => 'Понял, уточню.']);

        Http::assertSent(function ($request) {
            $messages = $request['messages'];
            return count($messages) === 4
                && $messages[1] === ['role' => 'user', 'content' => 'У меня Ford Escape']
                && $messages[2] === ['role' => 'assistant', 'content' => 'Какой код коробки?']
                && $messages[3] === ['role' => 'user', 'content' => 'Пока не знаю'];
        });
    }
}