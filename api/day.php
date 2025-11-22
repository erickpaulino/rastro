<?php
// api/day.php
require __DIR__ . '/../config.php';
require_login_api();

header('Content-Type: application/json; charset=utf-8');

$date = $_GET['date'] ?? null;
if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'data_invalida']);
    exit;
}

$pdo = db();

// Dia
$stDay = $pdo->prepare("SELECT id, date, summary_json FROM days WHERE date = ?");
$stDay->execute([$date]);
$dayRow = $stDay->fetch(PDO::FETCH_ASSOC);

if (!$dayRow) {
    echo json_encode([
        'error' => 'sem_dados',
        'date'  => $date
    ]);
    exit;
}

$dayId = (int)$dayRow['id'];
$summary = $dayRow['summary_json']
    ? json_decode($dayRow['summary_json'], true)
    : ['distance_m' => 0, 'moving_s' => 0, 'visits' => 0];

// Segmentos
$stSeg = $pdo->prepare("
    SELECT
      uid, kind, mode, place_name, address,
      start_ts, end_ts, duration_s, distance_m,
      lat, lng, path_json, raw_source, source_file
    FROM segments
    WHERE day_id = ?
    ORDER BY start_ts ASC, seq ASC, id ASC
");
$stSeg->execute([$dayId]);

$segments = [];
while ($row = $stSeg->fetch(PDO::FETCH_ASSOC)) {
    $segments[] = [
        'uid'         => $row['uid'],
        'kind'        => $row['kind'],
        'mode'        => $row['mode'],
        'place_name'  => $row['place_name'],
        'address'     => $row['address'],
        'start_ts'    => (int)$row['start_ts'],
        'end_ts'      => (int)$row['end_ts'],
        'duration_s'  => (int)$row['duration_s'],
        'distance_m'  => (int)$row['distance_m'],
        'lat'         => $row['lat'] !== null ? (float)$row['lat'] : null,
        'lng'         => $row['lng'] !== null ? (float)$row['lng'] : null,
        'path'        => $row['path_json'] ? json_decode($row['path_json'], true) : null,
        'raw_source'  => $row['raw_source'],
        'source_file' => $row['source_file'],
    ];
}

// Raw signals
$rawSignals = [];
try {
    $stRaw = $pdo->prepare("
        SELECT
          ts, kind, uid, lat, lng, accuracy_m,
          altitude_m, speed_mps, source, wifi_devices, source_file
        FROM raw_signals
        WHERE day_id = ?
        ORDER BY ts ASC, id ASC
    ");
    $stRaw->execute([$dayId]);

    while ($row = $stRaw->fetch(PDO::FETCH_ASSOC)) {
        $rawSignals[] = [
            'ts'           => (int)$row['ts'],
            'kind'         => $row['kind'],
            'uid'          => $row['uid'],
            'lat'          => $row['lat'] !== null ? (float)$row['lat'] : null,
            'lng'          => $row['lng'] !== null ? (float)$row['lng'] : null,
            'accuracy_m'   => $row['accuracy_m'] !== null ? (float)$row['accuracy_m'] : null,
            'altitude_m'   => $row['altitude_m'] !== null ? (float)$row['altitude_m'] : null,
            'speed_mps'    => $row['speed_mps'] !== null ? (float)$row['speed_mps'] : null,
            'source'       => $row['source'],
            'wifi_devices' => $row['wifi_devices'] !== null ? (int)$row['wifi_devices'] : null,
            'source_file'  => $row['source_file'],
        ];
    }
} catch (Throwable $e) {
    $rawSignals = [];
}

echo json_encode([
    'date'       => $date,
    'summary'    => $summary,
    'segments'   => $segments,
    'rawSignals' => $rawSignals,
], JSON_UNESCAPED_UNICODE);
