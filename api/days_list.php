<?php
// api/days_list.php
require __DIR__ . '/../config.php';
require_login_api();

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

$st = $pdo->query("
    SELECT id, date, summary_json
    FROM days
    ORDER BY date DESC
");

$days = [];
while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $summary = $row['summary_json']
        ? json_decode($row['summary_json'], true)
        : ['distance_m' => 0, 'moving_s' => 0, 'visits' => 0];

    $days[] = [
        'date'    => $row['date'],
        'summary' => $summary,
    ];
}

echo json_encode([
    'ok'   => true,
    'days' => $days,
]);
