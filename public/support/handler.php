<?php

declare(strict_types=1);

$webhookUrl = 'https://technomedia.bitrix24.ru/rest/116/fl4hzil14be7py53/im.message.add.json';
$dialogId = 'chat860';

$eventData = $_POST;

file_put_contents(
    __DIR__ . '/webhook_events.log',
    date('Y-m-d H:i:s') . ' - ' .
    json_encode($eventData, JSON_UNESCAPED_UNICODE) .
    PHP_EOL,
    FILE_APPEND
);

$eventType = (string)($eventData['event'] ?? '');

if ($eventType === 'ONIMBOTV2MESSAGEADD') {
    $messageData = $eventData['data']['message'] ?? [];
    $userData = $eventData['data']['user'] ?? [];
    $chatData = $eventData['data']['chat'] ?? [];

    $messageText = (string)($messageData['text'] ?? '');
    $senderName = (string)($userData['name'] ?? '');

    $sourceDomain = (string)(
        $eventData['auth']['domain']
        ?? $eventData['data']['bot']['auth']['domain']
        ?? 'unknown'
    );

    $messageId = isset($messageData['id'])
        ? (int)$messageData['id']
        : null;

    $userId = isset($userData['id'])
        ? (int)$userData['id']
        : null;

    $chatId = (string)(
        $chatData['dialogId']
        ?? $chatData['id']
        ?? ''
    );

    $messageDate = $messageData['date'] ?? null;

    if ($messageDate !== null && $messageDate !== '') {
        try {
            $messageDate = (new DateTimeImmutable((string)$messageDate))
                ->format('Y-m-d H:i:s');
        } catch (Throwable $exception) {
            $messageDate = null;
        }
    } else {
        $messageDate = null;
    }

    /*
     * Подключение к MySQL.
     * При необходимости замените значения на свои.
     */
    $dbHost = '127.0.0.1';
    $dbPort = '3306';
    $dbName = 'laravel';
    $dbUser = 'laravel';
    $dbPassword = 'CsOelhlIVYCz';

    try {
        $pdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        /*
         * Создание таблицы, если ее еще нет.
         */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS portal_messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                source_domain VARCHAR(255) NOT NULL,
                message_id BIGINT UNSIGNED NULL,
                chat_id VARCHAR(100) NULL,
                user_id BIGINT UNSIGNED NULL,
                sender_name VARCHAR(255) NULL,
                message_text TEXT NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                message_date DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                INDEX idx_source_domain (source_domain),
                INDEX idx_message_id (message_id),
                INDEX idx_created_at (created_at)
            )
            ENGINE=InnoDB
            DEFAULT CHARACTER SET utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");

        /*
         * Сохранение сообщения.
         */
        $insertQuery = $pdo->prepare("
            INSERT INTO portal_messages (
                source_domain,
                message_id,
                chat_id,
                user_id,
                sender_name,
                message_text,
                event_type,
                message_date
            ) VALUES (
                :source_domain,
                :message_id,
                :chat_id,
                :user_id,
                :sender_name,
                :message_text,
                :event_type,
                :message_date
            )
        ");

        $insertQuery->execute([
            ':source_domain' => $sourceDomain,
            ':message_id' => $messageId,
            ':chat_id' => $chatId !== '' ? $chatId : null,
            ':user_id' => $userId,
            ':sender_name' => $senderName !== '' ? $senderName : null,
            ':message_text' => $messageText,
            ':event_type' => $eventType,
            ':message_date' => $messageDate,
        ]);

        file_put_contents(
            __DIR__ . '/database.log',
            date('Y-m-d H:i:s') .
            ' - Saved message from ' .
            $sourceDomain .
            PHP_EOL,
            FILE_APPEND
        );
    } catch (Throwable $exception) {
        file_put_contents(
            __DIR__ . '/database_errors.log',
            date('Y-m-d H:i:s') .
            ' - ' .
            $exception->getMessage() .
            PHP_EOL,
            FILE_APPEND
        );
    }

    /*
     * Отправка сообщения в другой чат Bitrix24.
     */
    $textToSend = "[{$sourceDomain}] {$senderName}: {$messageText}";

    $postFields = [
        'DIALOG_ID' => $dialogId,
        'MESSAGE' => $textToSend,
    ];

    $ch = curl_init($webhookUrl);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);

    curl_close($ch);

    file_put_contents(
        __DIR__ . '/webhook_send.log',
        date('Y-m-d H:i:s') .
        ' - Response: ' . (string)$response .
        ' Error: ' . $curlError .
        PHP_EOL,
        FILE_APPEND
    );
}

http_response_code(200);
