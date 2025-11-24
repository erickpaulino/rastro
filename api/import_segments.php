<?php
// api/import_segments.php

require __DIR__ . '/../config.php';
require_login_api();

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['days']) || !is_array($data['days'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'payload_invalido']);
    exit;
}

$pdo = null;

try {
    $pdo = db();
    $pdo->beginTransaction();

    // DAYS
    $stDaySel = $pdo->prepare("SELECT id FROM days WHERE date = ?");
    $stDayIns = $pdo->prepare("INSERT INTO days (date, summary_json) VALUES (?, ?)");
    $stDayUpd = $pdo->prepare("UPDATE days SET summary_json = ? WHERE id = ?");

    // Limpeza por dia
    $stDelSeg = $pdo->prepare("DELETE FROM segments WHERE day_id = ?");

    $hasRawSignalsTable = false;
    $stDelRaw = null;
    try {
        $pdo->query("SELECT 1 FROM raw_signals LIMIT 1");
        $hasRawSignalsTable = true;
        $stDelRaw = $pdo->prepare("DELETE FROM raw_signals WHERE day_id = ?");
    } catch (Throwable $e) {
        $hasRawSignalsTable = false;
    }

    // SEGMENTS
    $stSegIns = $pdo->prepare("
        INSERT INTO segments
        (day_id, uid, seq, kind, mode, place_name, address,
         start_ts, end_ts, duration_s, distance_m,
         lat, lng, path_json, raw_source, source_file)
        VALUES
        (:day_id, :uid, :seq, :kind, :mode, :place_name, :address,
         :start_ts, :end_ts, :duration_s, :distance_m,
         :lat, :lng, :path_json, :raw_source, :source_file)
    ");

    // RAW_SIGNALS
    $stRawIns = null;
    if ($hasRawSignalsTable) {
        $stRawIns = $pdo->prepare("
            INSERT INTO raw_signals
            (day_id, ts, kind, uid, lat, lng, accuracy_m,
             altitude_m, speed_mps, source, wifi_devices, source_file, raw_source)
            VALUES
            (:day_id, :ts, :kind, :uid, :lat, :lng, :accuracy_m,
             :altitude_m, :speed_mps, :source, :wifi_devices, :source_file, :raw_source)
        ");
    }

    foreach ($data['days'] as $dateStr => $payload) {
        // data do tipo YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            continue;
        }

        $summary    = $payload['summary']    ?? ['distance_m' => 0, 'moving_s' => 0, 'visits' => 0];
        $segments   = $payload['segments']   ?? [];
        $rawSignals = $payload['rawSignals'] ?? [];

        // Upsert em days
        $stDaySel->execute([$dateStr]);
        $dayId = $stDaySel->fetchColumn();

        $summaryJson = json_encode($summary, JSON_UNESCAPED_UNICODE);

        if ($dayId) {
            $stDayUpd->execute([$summaryJson, $dayId]);
        } else {
            $stDayIns->execute([$dateStr, $summaryJson]);
            $dayId = (int)$pdo->lastInsertId();
        }

        // REGRAVAÇÃO: apaga segmentos e raw_signals desse dia
        $stDelSeg->execute([$dayId]);
        if ($hasRawSignalsTable && $stDelRaw) {
            $stDelRaw->execute([$dayId]);
        }

        // ---------- SEGMENTS ----------
        $seq = 0;
        foreach ($segments as $seg) {
            $seq++;

            $start_ts = (int)($seg['start_ts'] ?? 0);
            $end_ts   = (int)($seg['end_ts'] ?? 0);
            $kind     = normalize_segment_kind($seg['kind'] ?? 'move');

            // Garante que o uid de segmento nunca seja vazio
            $uid = $seg['uid'] ?? '';
            if ($uid === '' || $uid === null) {
                $latStr = isset($seg['lat']) ? (string)$seg['lat'] : '';
                $lngStr = isset($seg['lng']) ? (string)$seg['lng'] : '';
                $uid = 'sg2-' . substr(
                    hash(
                        'sha256',
                        $dateStr . '-' . $start_ts . '-' . $end_ts . '-' . $kind . '-' . $latStr . '-' . $lngStr . '-' . $seq
                    ),
                    0,
                    16
                );
            }

            $rawSource = isset($seg['raw_source']) ? (string)$seg['raw_source'] : null;
            if ($rawSource !== null && strlen($rawSource) > 20) {
                $rawSource = substr($rawSource, 0, 20);
            }

            $sourceFile = isset($seg['source_file']) ? (string)$seg['source_file'] : null;
            if ($sourceFile !== null && strlen($sourceFile) > 255) {
                $sourceFile = substr($sourceFile, 0, 255);
            }

            $params = [
                ':day_id'      => $dayId,
                ':uid'         => $uid,
                ':seq'         => $seq,
                ':kind'        => $kind,
                ':mode'        => $seg['mode'] ?? null,
                ':place_name'  => $seg['place_name'] ?? null,
                ':address'     => $seg['address'] ?? null,
                ':start_ts'    => $start_ts,
                ':end_ts'      => $end_ts,
                ':duration_s'  => (int)($seg['duration_s'] ?? 0),
                ':distance_m'  => (int)($seg['distance_m'] ?? 0),
                ':lat'         => isset($seg['lat']) ? (double)$seg['lat'] : null,
                ':lng'         => isset($seg['lng']) ? (double)$seg['lng'] : null,
                ':path_json'   => isset($seg['path']) ? json_encode($seg['path']) : null,
                ':raw_source'  => $rawSource,
                ':source_file' => $sourceFile,
            ];
            $stSegIns->execute($params);
        }

        // ---------- RAW_SIGNALS ----------
        if ($hasRawSignalsTable && $stRawIns && !empty($rawSignals)) {
            $rsSeq = 0;
            foreach ($rawSignals as $rs) {
                $rsSeq++;

                $ts   = isset($rs['ts'])   ? (int)$rs['ts']   : 0;
                $kind = normalize_raw_signal_kind($rs['kind'] ?? 'position');

                // Ignora qualquer uid vindo do JSON e gera um novo UID globalmente único
                $latStr = isset($rs['lat']) ? (string)$rs['lat'] : '';
                $lngStr = isset($rs['lng']) ? (string)$rs['lng'] : '';

                // rs2- prefixo garante que não bate com UIDs antigos do tipo "95f0f39e..."
                $uid = 'rs2-' . substr(
                    hash(
                        'sha256',
                        $dateStr . '-' . $ts . '-' . $kind . '-' . $latStr . '-' . $lngStr . '-' . $rsSeq
                    ),
                    0,
                    16
                );

                $rawSource = isset($rs['raw_source']) ? (string)$rs['raw_source'] : null;
                if ($rawSource !== null && strlen($rawSource) > 30) {
                    $rawSource = substr($rawSource, 0, 30);
                }

                $params = [
                    ':day_id'       => $dayId,
                    ':ts'           => $ts,
                    ':kind'         => $kind,
                    ':uid'          => $uid,
                    ':lat'          => isset($rs['lat']) ? (double)$rs['lat'] : null,
                    ':lng'          => isset($rs['lng']) ? (double)$rs['lng'] : null,
                    ':accuracy_m'   => isset($rs['accuracy_m']) ? (double)$rs['accuracy_m'] : null,
                    ':altitude_m'   => isset($rs['altitude_m']) ? (double)$rs['altitude_m'] : null,
                    ':speed_mps'    => isset($rs['speed_mps']) ? (double)$rs['speed_mps'] : null,
                    ':source'       => $rs['source'] ?? null,
                    ':wifi_devices' => (int)($rs['wifi_devices'] ?? 0),
                    ':source_file'  => $rs['source_file'] ?? null,
                    ':raw_source'   => $rawSource
                ];
                $stRawIns->execute($params);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

function normalize_segment_kind($kind) {
    $value = $kind;
    if (!is_string($value)) {
        $value = 'move';
    }

    $normalized = strtolower(trim($value));
    if ($normalized === 'place' || $normalized === 'move') {
        return $normalized;
    }

    if (strpos($normalized, 'place') !== false || strpos($normalized, 'stop') !== false) {
        return 'place';
    }
    if (
        strpos($normalized, 'move') !== false ||
        strpos($normalized, 'travel') !== false ||
        strpos($normalized, 'activity') !== false
    ) {
        return 'move';
    }

    return 'move';
}

function normalize_raw_signal_kind($kind) {
    if (!is_string($kind)) {
        return 'position';
    }

    $normalized = strtolower(trim($kind));
    if ($normalized === 'wifi' || $normalized === 'position') {
        return $normalized;
    }

    if (strpos($normalized, 'wifi') !== false) {
        return 'wifi';
    }

    return 'position';
}
