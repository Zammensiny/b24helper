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

        $messages = array_slice($data['messages'], -8);
        if (end($messages)['role'] !== 'user') {
            return response()->json(['message' => 'Последнее сообщение должно быть сообщением пользователя.'], 422);
        }

        while (count($messages) > 1 && $this->messageLength($messages) > 8000) {
            array_shift($messages);
        }

        $promptPath = config('services.ollama.system_prompt_path');
        $systemPrompt = is_file($promptPath) ? file_get_contents($promptPath) : false;
        if (!is_string($systemPrompt) || trim($systemPrompt) === '') {
            return response()->json(['message' => 'Системная инструкция консультанта недоступна.'], 503);
        }

        array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);

        try {
            $response = Http::timeout(45)->post(rtrim(config('services.ollama.url'), '/') . '/api/chat', [
                'model' => config('services.ollama.model'),
                'messages' => $messages,
                'stream' => false,
                'options' => ['temperature' => 0.3, 'num_predict' => 256],
            ]);
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'Не удалось подключиться к Ollama. Проверьте, что сервис запущен и доступен по указанному адресу.',
            ], 502);
        }
        if (!$response->successful()) {
            return response()->json(['message' => 'Ollama вернула ошибку: ' . $response->body()], 502);
        }

        $reply = $response->json('message.content');
        if (!is_string($reply) || trim($reply) === '') {
            return response()->json(['message' => 'Ollama вернула пустой ответ.'], 502);
        }

        return response()->json(['reply' => trim($reply)]);
    }

    private function messageLength(array $messages): int
    {
        return array_sum(array_map(fn ($message) => mb_strlen($message['content']), $messages));
    }

}