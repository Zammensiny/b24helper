<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OllamaChatController extends Controller
{
    public function reply(Request $request)
    {
        $data = $request->validate([
            'messages' => 'required|array|min:1|max:30',
            'messages.*.role' => 'required|in:user,assistant',
            'messages.*.content' => 'required|string|max:12000',
        ]);

        $messages = array_map(function ($message) {
            return [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }, $data['messages']);

        array_unshift($messages, [
            'role' => 'system',
            'content' => 'Отвечай пользователю только на русском языке.',
        ]);

        try {
            $response = Http::timeout(120)
                ->post(rtrim(config('services.ollama.url'), '/') . '/api/chat', [
                    'model' => config('services.ollama.model'),
                    'messages' => $messages,
                    'stream' => false,
                ]);
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'Не удалось подключиться к Ollama. Проверьте, что сервис запущен и доступен по указанному адресу.',
            ], 502);
        }

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Ollama вернула ошибку: ' . $response->body(),
            ], 502);
        }

        $answer = $response->json('message.content');
        if (!is_string($answer) || $answer === '') {
            return response()->json(['message' => 'Ollama вернула пустой ответ.'], 502);
        }

        return response()->json(['reply' => $answer]);
    }
}