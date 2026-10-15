<?php
// Конфигурация
$webhookUrl = 'https://b24-dxog22.bitrix24.ru/rest/1/evyvhuz268o3m0us/';
$totalLeads = 150;
$stages = ['NEW', 'IN_PROCESS', 'PROCESSED'];

// Получаем текущий номер лида из query-параметра
$current = isset($_GET['n']) ? (int)$_GET['n'] : 1;
$created = isset($_GET['created']) ? (int)$_GET['created'] : 0;

// Генерация случайных данных
function generateRandomLead($index) {
    $firstNames = ['Иван', 'Петр', 'Алексей', 'Дмитрий', 'Сергей', 'Андрей', 'Николай', 'Владимир', 'Александр', 'Михаил'];
    $lastNames = ['Иванов', 'Петров', 'Сидоров', 'Смирнов', 'Кузнецов', 'Попов', 'Васильев', 'Михайлов', 'Новиков', 'Федоров'];
    $companies = ['ООО "Вектор"', 'ЗАО "Прогресс"', 'ИП "Старт"', 'ООО "Техно"', 'АО "Инновация"', 'ООО "Финанс"', 'ИП "Сервис"', 'ООО "Торг"', 'ЗАО "Логистик"', 'ООО "Консалт"'];

    $stages = ['NEW', 'IN_PROCESS', 'PROCESSED'];

    return [
        'TITLE' => 'Лид #' . $index,
        'NAME' => $firstNames[array_rand($firstNames)],
        'LAST_NAME' => $lastNames[array_rand($lastNames)],
        'PHONE' => [['VALUE' => '+7999' . rand(1000000, 9999999), 'VALUE_TYPE' => 'WORK']],
        'EMAIL' => [['VALUE' => 'lead' . $index . '@example.com', 'VALUE_TYPE' => 'WORK']],
        'COMPANY_TITLE' => $companies[array_rand($companies)],
        'STAGE_ID' => $stages[array_rand($stages)],
        'COMMENTS' => 'Сгенерированный лид #' . $index,
    ];
}

// Создаём один лид
$result = null;
$error = null;
$autoRedirect = false;

if ($current <= $totalLeads) {
    $lead = generateRandomLead($current);

    $ch = curl_init($webhookUrl . 'crm.lead.add.json');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($lead));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode !== 200 || (isset($result['error']) && $result['error'])) {
        $error = [
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'api_error' => $result
        ];
    } else {
        $created++;
        $autoRedirect = true; // Автопереход только при успехе
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Генератор лидов Bitrix24</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .btn { padding: 12px 24px; font-size: 16px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn-success { background: #4CAF50; color: white; }
        .btn-primary { background: #2196F3; color: white; }
        .btn-warning { background: #FF9800; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .status { margin: 20px 0; padding: 15px; border-radius: 5px; }
        .status-success { background: #d4edda; border: 1px solid #c3e6cb; }
        .status-error { background: #f8d7da; border: 1px solid #f5c6cb; }
        .status-info { background: #d1ecf1; border: 1px solid #bee5eb; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
        .progress { margin: 20px 0; }
        .timer { color: #666; font-size: 14px; }
    </style>
</head>
<body>
<h1>🚀 Генератор лидов для Bitrix24</h1>

<div class="progress">
    <p>Создано лидов: <strong><?= $created ?> / <?= $totalLeads ?></strong></p>
    <p>Текущий: <strong><?= $current ?> / <?= $totalLeads ?></strong></p>
</div>

<?php if ($result && !$error): ?>
    <div class="status status-success">
        <strong>✅ Лид #<?= $current ?> создан!</strong><br>
        ID лида: <strong><?= $result['result'] ?></strong><br>
        <pre><?= json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?></pre>
    </div>

    <?php if ($current < $totalLeads): ?>
        <p class="timer">⏱ Автоперезагрузка через <span id="countdown">2</span> сек...</p>
        <a href="?n=<?= $current + 1 ?>&created=<?= $created ?>" class="btn btn-success">Создать следующий лид</a>
        <a href="?n=<?= $current ?>&created=<?= $created ?>" class="btn btn-primary">Повторить этот</a>
    <?php else: ?>
        <div class="status status-info">
            <strong>🎉 Все лиды созданы!</strong>
        </div>
        <a href="?n=1&created=0" class="btn btn-warning">Начать заново</a>
    <?php endif; ?>

<?php elseif ($error): ?>
    <div class="status status-error">
        <strong>❌ Ошибка при создании лида #<?= $current ?></strong><br>
        HTTP код: <?= $error['http_code'] ?><br>
        <?php if ($error['curl_error']): ?>
            cURL ошибка: <?= $error['curl_error'] ?><br>
        <?php endif; ?>
        <pre><?= json_encode($error['api_error'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?></pre>
    </div>

    <p class="timer">⏱ Автоперезагрузка через <span id="countdown">3</span> сек...</p>
    <a href="?n=<?= $current ?>&created=<?= $created ?>" class="btn btn-success">Повторить этот</a>
    <a href="?n=<?= $current + 1 ?>&created=<?= $created ?>" class="btn btn-primary">Пропустить и дальше</a>

<?php elseif ($current > $totalLeads): ?>
    <div class="status status-info">
        <strong>🎉 Все лиды созданы!</strong>
    </div>
    <a href="?n=1&created=0" class="btn btn-warning">Начать заново</a>

<?php else: ?>
    <a href="?n=1&created=0" class="btn btn-success">Начать создание лидов</a>
<?php endif; ?>

<a href="?n=<?= $current ?>&created=<?= $created ?>" class="btn btn-primary">Обновить</a>

<?php if ($autoRedirect && $current < $totalLeads): ?>
    <script>
        let seconds = 2;
        const countdown = document.getElementById('countdown');

        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;

            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = '?n=<?= $current + 1 ?>&created=<?= $created ?>';
            }
        }, 1000);
    </script>
<?php elseif ($error && $current <= $totalLeads): ?>
    <script>
        // При ошибке тоже автоперезагрузка, но с повтором текущего лида
        let seconds = 3;
        const countdown = document.getElementById('countdown');

        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;

            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = '?n=<?= $current ?>&created=<?= $created ?>';
            }
        }, 1000);
    </script>
<?php endif; ?>

</body>
</html>
