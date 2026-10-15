<?php

declare(strict_types=1);

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'laravel';
$dbUser = 'laravel';
$dbPassword = 'CsOelhlIVYCz';

$tableName = 'portal_messages';

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
} catch (Throwable $exception) {
    http_response_code(500);
    exit('Ошибка подключения к базе данных.');
}

$allowedSortFields = [
    'id' => 'id',
    'source_domain' => 'source_domain',
    'sender_name' => 'sender_name',
    'message_date' => 'message_date',
    'created_at' => 'created_at',
];

$sort = (string)($_GET['sort'] ?? 'created_at');
$direction = strtoupper((string)($_GET['direction'] ?? 'DESC'));
$domain = trim((string)($_GET['domain'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$action = (string)($_GET['action'] ?? '');

if (!isset($allowedSortFields[$sort])) {
    $sort = 'created_at';
}

if (!in_array($direction, ['ASC', 'DESC'], true)) {
    $direction = 'DESC';
}

$tablesStatement = $pdo->query('SHOW TABLES');
$tables = $tablesStatement->fetchAll(PDO::FETCH_COLUMN);

/*
 * ВАЖНО:
 * Для каждого LIKE используется отдельный placeholder.
 * Один и тот же named placeholder нельзя повторно использовать
 * в подготовленном запросе при отключенной эмуляции prepares.
 */
$where = [];
$params = [];

if ($domain !== '') {
    $where[] = 'source_domain = :domain';
    $params[':domain'] = $domain;
}

if ($search !== '') {
    $where[] = '(message_text LIKE :search_message OR sender_name LIKE :search_sender)';
    $searchValue = '%' . $search . '%';
    $params[':search_message'] = $searchValue;
    $params[':search_sender'] = $searchValue;
}

$whereSql = $where !== []
    ? ' WHERE ' . implode(' AND ', $where)
    : '';

$orderBy = $allowedSortFields[$sort];

// Экспорт текущей выборки в CSV.
if ($action === 'export') {
    $sql = "SELECT
                id,
                source_domain,
                message_id,
                chat_id,
                user_id,
                sender_name,
                message_text,
                event_type,
                message_date,
                created_at
            FROM `{$tableName}`
            {$whereSql}
            ORDER BY {$orderBy} {$direction}";

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    $filename = 'portal_messages_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'ID',
        'Портал',
        'ID сообщения',
        'ID чата',
        'ID пользователя',
        'Отправитель',
        'Сообщение',
        'Тип события',
        'Дата сообщения',
        'Дата получения',
    ], ';', '"', '', "\r\n");

    while ($row = $statement->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['source_domain'],
            $row['message_id'],
            $row['chat_id'],
            $row['user_id'],
            $row['sender_name'],
            $row['message_text'],
            $row['event_type'],
            $row['message_date'],
            $row['created_at'],
        ], ';', '"', '', "\r\n");
    }

    fclose($output);
    exit;
}

$domainsStatement = $pdo->query("SELECT DISTINCT source_domain
                                  FROM `{$tableName}`
                                  WHERE source_domain IS NOT NULL
                                    AND source_domain <> ''
                                  ORDER BY source_domain ASC");
$domains = $domainsStatement->fetchAll(PDO::FETCH_COLUMN);

$sql = "SELECT
            id,
            source_domain,
            message_id,
            chat_id,
            user_id,
            sender_name,
            message_text,
            event_type,
            message_date,
            created_at
        FROM `{$tableName}`
        {$whereSql}
        ORDER BY {$orderBy} {$direction}
        LIMIT 500";

try {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $messages = $statement->fetchAll();
} catch (Throwable $exception) {
    file_put_contents(
        __DIR__ . '/admin_errors.log',
        date('Y-m-d H:i:s') . ' - ' . $exception->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    http_response_code(500);
    exit('Ошибка выполнения запроса. Подробности записаны в admin_errors.log.');
}

$sortLink = static function (string $field) use ($sort, $direction, $domain, $search): string {
    $nextDirection = ($sort === $field && $direction === 'ASC') ? 'DESC' : 'ASC';

    return '?' . http_build_query([
            'sort' => $field,
            'direction' => $nextDirection,
            'domain' => $domain,
            'search' => $search,
        ]);
};

$exportUrl = '?' . http_build_query([
        'action' => 'export',
        'sort' => $sort,
        'direction' => $direction,
        'domain' => $domain,
        'search' => $search,
    ]);

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Админка обращений</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: #f3f4f6;
            color: #1f2937;
            font: 14px/1.45 Arial, sans-serif;
        }
        .container { max-width: 1600px; margin: 0 auto; }
        h1 { margin: 0 0 20px; font-size: 26px; }
        h2 { margin: 0 0 12px; font-size: 18px; }
        .card {
            margin-bottom: 20px;
            padding: 18px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
        }
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
        }
        label {
            display: flex;
            flex-direction: column;
            gap: 5px;
            color: #4b5563;
            font-size: 12px;
        }
        input, select, button, .button {
            min-height: 36px;
            padding: 8px 11px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            color: #111827;
            font: inherit;
        }
        input { min-width: 260px; }
        button, .button {
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            text-decoration: none;
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
        button:hover, .button:hover { background: #1d4ed8; }
        .button.secondary { background: #4b5563; border-color: #4b5563; }
        .button.secondary:hover { background: #374151; }
        .table-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .table-name {
            padding: 6px 9px;
            border-radius: 5px;
            background: #eef2ff;
            color: #3730a3;
            font-family: monospace;
        }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; min-width: 1200px; border-collapse: collapse; }
        th, td {
            padding: 10px 9px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }
        th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
            white-space: nowrap;
        }
        th a { color: #111827; text-decoration: none; }
        th a:hover { color: #2563eb; }
        td.message {
            max-width: 480px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }
        .muted { color: #6b7280; }
        .count { margin-bottom: 12px; color: #4b5563; }
        .empty { padding: 25px 0; color: #6b7280; }
    </style>
</head>
<body>
<div class="container">
    <h1>Админка обращений</h1>

    <section class="card">
        <h2>Таблицы базы данных</h2>
        <div class="table-list">
            <?php if ($tables === []): ?>
                <span class="muted">Таблиц нет</span>
            <?php else: ?>
                <?php foreach ($tables as $databaseTable): ?>
                    <span class="table-name"><?= h((string)$databaseTable) ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <form method="get" class="filters">
            <label>
                Портал
                <select name="domain">
                    <option value="">Все порталы</option>
                    <?php foreach ($domains as $availableDomain): ?>
                        <option value="<?= h((string)$availableDomain) ?>" <?= $domain === $availableDomain ? 'selected' : '' ?>>
                            <?= h((string)$availableDomain) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Поиск по сообщению или имени
                <input type="text" name="search" value="<?= h($search) ?>" placeholder="Введите текст">
            </label>

            <input type="hidden" name="sort" value="<?= h($sort) ?>">
            <input type="hidden" name="direction" value="<?= h($direction) ?>">

            <button type="submit">Применить</button>
            <a class="button secondary" href="admin.php">Сбросить</a>
            <a class="button" href="<?= h($exportUrl) ?>">Экспорт CSV</a>
        </form>
    </section>

    <section class="card">
        <h2>Обращения</h2>
        <div class="count">Показано записей: <?= count($messages) ?>. Максимум на странице: 500.</div>

        <?php if ($messages === []): ?>
            <div class="empty">По заданным условиям обращения не найдены.</div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th><a href="<?= h($sortLink('id')) ?>">ID</a></th>
                        <th><a href="<?= h($sortLink('source_domain')) ?>">Портал</a></th>
                        <th>Отправитель</th>
                        <th>Сообщение</th>
                        <th>Чат</th>
                        <th><a href="<?= h($sortLink('message_date')) ?>">Дата сообщения</a></th>
                        <th><a href="<?= h($sortLink('created_at')) ?>">Получено</a></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($messages as $message): ?>
                        <tr>
                            <td><?= h((string)$message['id']) ?></td>
                            <td><?= h((string)$message['source_domain']) ?></td>
                            <td>
                                <?= h((string)($message['sender_name'] ?? '')) ?><br>
                                <span class="muted">ID: <?= h((string)($message['user_id'] ?? '')) ?></span>
                            </td>
                            <td class="message"><?= h((string)$message['message_text']) ?></td>
                            <td><?= h((string)($message['chat_id'] ?? '')) ?></td>
                            <td><?= h((string)($message['message_date'] ?? '')) ?></td>
                            <td><?= h((string)$message['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
