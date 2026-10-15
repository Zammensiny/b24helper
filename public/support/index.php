<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/cextrest.php';

$installResult = CRestExt::installApp();
$memberId = null;

if (!empty($installResult['install'])) {
    $memberId = CRestExt::getCurrentBitrix24();
}

$avatarPath = __DIR__ . '/bot.jpg';
$avatarBase64 = '';

if (file_exists($avatarPath)) {
    $avatarBase64 = base64_encode(file_get_contents($avatarPath));
}

/*-- Unregister --*/

//$result = CRestExt::call('imbot.v2.Bot.unregister', [
//    'botId' => 118
//]);

/*-- Register --*/

$result = CRestExt::call(
    'imbot.v2.Bot.register',
    [
        'fields' => [
            'code' => 'technomedia_bot',
            'botToken' => 'my_secret_token_123',
            'properties' => [
                'name' => 'Technomedia Bot',
                'workPosition' => 'Assistant',
                'avatar' => $avatarBase64,
            ],
            'type' => 'bot',
            'eventMode' => 'webhook',
            'webhookUrl' => 'https://trickster.zammensiny.ru/support/handler.php',
        ],
    ]
);

/*-- Subscribe --*/

//$result = CRestExt::call('im.v2.Event.subscribe', []);

/*-- Events --*/

//$result = CRestExt::call(
//    'imbot.v2.Event.get',
//    [
//        'botId' => 110,
//        'offset' => 0,
//        'limit' => 50,
//        'withUserEvents' => true,
//    ]
//);

