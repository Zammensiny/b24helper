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

    public function test_message_is_sent_to_ollama_and_reply_is_returned(): void
    {
        config([
            'services.ollama.url' => 'http://127.0.0.1:11434',
            'services.ollama.model' => 'test-model',
        ]);

        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'message' => ['content' => 'Привет!'],
            ]),
        ]);

        $this->postJson('/test-chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Привет'],
            ],
        ])->assertOk()->assertExactJson(['reply' => 'Привет!']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:11434/api/chat'
                && $request['model'] === 'test-model'
                && $request['stream'] === false
                && $request['messages'][0]['role'] === 'system'
                && $request['messages'][0]['content'] === 'Отвечай пользователю только на русском языке.'
                && $request['messages'][1]['content'] === 'Привет';
        });
    }
}