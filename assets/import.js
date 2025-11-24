// assets/import.js - versão robusta (DOM carregado + semanticSegments + rawSignals)

'use strict';

// Referências globais aos elementos de UI
let fileInput = null;
let startImportBtn = null;
let importStatus = null;

// ---------------------------------------------------------------------------
// Utilitário de status
// ---------------------------------------------------------------------------
function setStatus(msg) {
  if (importStatus) importStatus.textContent = msg;
  console.log('[IMPORT]', msg);
}

// ---------------------------------------------------------------------------
// Inicialização: só liga os eventos depois do DOM pronto
// ---------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
  fileInput      = document.getElementById('file-input');
  startImportBtn = document.getElementById('start-import');
  importStatus   = document.getElementById('import-status');

  if (!fileInput || !startImportBtn) {
    console.warn('Import: elementos #file-input ou #start-import não encontrados no DOM.');
    return;
  }

  startImportBtn.addEventListener('click', function () {
    const file = fileInput.files && fileInput.files[0];
    if (!file) {
      alert('Escolha um arquivo JSON do Google Maps Timeline.');
      return;
    }
    processFile(file);
  });

  console.log('Import: inicializado, botão de import ligado.');
});

// ---------------------------------------------------------------------------
// Leitura do arquivo
// ---------------------------------------------------------------------------

function processFile(file) {
  setStatus('Lendo arquivo "' + file.name + '"...');
  const reader = new FileReader();

  reader.onload = function (e) {
    try {
      const text = e.target.result;
      handleJsonImport(text, file.name);
    } catch (err) {
      console.error(err);
      setStatus('Erro ao processar arquivo: ' + (err.message || err));
    }
  };

  reader.onerror = function () {
    setStatus('Falha ao ler o arquivo.');
  };

  reader.readAsText(file);
}

function handleJsonImport(text, fileName) {
  let json;
  try {
    json = JSON.parse(text);
  } catch (err) {
    console.error('Erro de JSON:', err);
    setStatus('Arquivo não é um JSON válido.');
    return;
  }

  if (!json || typeof json !== 'object') {
    setStatus('JSON de formato inesperado (raiz não é um objeto).');
    return;
  }

  const topKeys = Object.keys(json);
  console.log('Chaves principais do JSON:', topKeys);

  const byDay = normalizeGoogleTimeline(json, fileName);
  const dayCount = Object.keys(byDay).length;

  if (!dayCount) {
    setStatus('Nenhum dia reconhecido no arquivo (formato não suportado?).');
    return;
  }

  setStatus('Arquivo analisado. Dias detectados: ' + dayCount + '. Enviando para o servidor...');
  sendBatches(byDay)
    .then(function () {
      setStatus('Importação concluída com sucesso. Dias importados: ' + dayCount + '.');
      // Se o app principal expuser window.loadDaysList, recarrega a lista:
      if (typeof window.loadDaysList === 'function') {
        window.loadDaysList();
      }
    })
    .catch(function (err) {
      console.error(err);
      setStatus('Erro durante o envio ao servidor: ' + (err.message || err));
    });
}

// ---------------------------------------------------------------------------
// Normalização de formatos do Google Timeline
// Foco: semanticSegments + rawSignals (export novo do app)
// Saída: byDay = { "YYYY-MM-DD": { summary, segments, rawSignals } }
// ---------------------------------------------------------------------------

function normalizeGoogleTimeline(json, sourceFileName) {
  const byDay = {};
  const placeCache = Object.create(null);

  seedPlaceCacheFromProfile(json.userLocationProfile, placeCache);

  // 1) Novo formato de export do app
  if (Array.isArray(json.semanticSegments)) {
    console.log('Detectado formato: semanticSegments (export Timeline do app).');
    handleSemanticSegments(json.semanticSegments, byDay, sourceFileName, placeCache);
    cleanupSemanticSegments(byDay);
  }

  if (Array.isArray(json.rawSignals)) {
    console.log('Detectado formato: rawSignals (wifi/position).');
    handleRawSignals(json.rawSignals, byDay, sourceFileName);
  }
  cleanupRawSignals(byDay);

  // (Outros formatos, como timelineObjects/locations, podem ser acrescentados depois)

  // 2) Construir segmentos a partir dos rawSignals.position, se houver
  buildSegmentsFromRawSignals(byDay);
  ensurePlaceholderSegments(byDay);

  // 3) Recalcular resumo para todos os dias
  const dates = Object.keys(byDay);
  for (let i = 0; i < dates.length; i++) {
    const dateKey = dates[i];
    recomputeSummary(byDay[dateKey]);
  }

  return byDay;
}

function seedPlaceCacheFromProfile(profile, placeCache) {
  if (!profile || typeof profile !== 'object') return;

  if (Array.isArray(profile.frequentPlaces)) {
    for (let i = 0; i < profile.frequentPlaces.length; i++) {
      const place = profile.frequentPlaces[i];
      if (!place || !place.placeId) continue;
      const coords = parseLatLng(place.placeLocation || null);
      if (coords) {
        rememberPlaceLocation(placeCache, place.placeId, coords, place.label || null);
      }
    }
  }
}

function preloadPlaceCacheFromSegments(semanticSegments, placeCache) {
  if (!Array.isArray(semanticSegments)) return;
  for (let i = 0; i < semanticSegments.length; i++) {
    const seg = semanticSegments[i];
    if (!seg || !seg.visit) continue;
    const visit = seg.visit;
    const top = visit.topCandidate || {};
    if (!top.placeId) continue;
    const coords = parseLatLng(top.placeLocation || null);
    if (coords) {
      rememberPlaceLocation(placeCache, top.placeId, coords, labelForSemanticType(top.semanticType || visit.label));
    }
  }
}

function cleanupSemanticSegments(byDay) {
  const dates = Object.keys(byDay);
  for (let i = 0; i < dates.length; i++) {
    const dateKey = dates[i];
    const day = byDay[dateKey];
    if (!day || !Array.isArray(day.segments) || !day.segments.length) {
      continue;
    }

    const timelineMoveSegs = [];
    const primaryMoveSegs = [];
    for (let j = 0; j < day.segments.length; j++) {
      const seg = day.segments[j];
      if (!seg) continue;
      if (seg.raw_source === 'semantic.timelinePath' && seg.kind === 'move') {
        timelineMoveSegs.push(seg);
      } else if (seg.kind === 'move' && seg.raw_source !== 'semantic.timelinePath') {
        primaryMoveSegs.push(seg);
      }
    }

    if (!timelineMoveSegs.length || !primaryMoveSegs.length) {
      continue;
    }

    day.segments = day.segments.filter(function (seg) {
      if (!seg || seg.raw_source !== 'semantic.timelinePath' || seg.kind !== 'move') {
        return true;
      }
      const overlapsMove = primaryMoveSegs.some(function (primary) {
        return segmentsOverlap(seg, primary);
      });
      return !overlapsMove;
    });
  }
}

function segmentsOverlap(a, b) {
  if (!a || !b) return false;
  const startA = a.start_ts || 0;
  const endA = a.end_ts != null ? a.end_ts : startA;
  const startB = b.start_ts || 0;
  const endB = b.end_ts != null ? b.end_ts : startB;
  return startA < endB && endA > startB;
}

// ---------------------------------------------------------------------------
// Helpers gerais
// ---------------------------------------------------------------------------

function ensureDay(byDay, dateKey) {
  if (!byDay[dateKey]) {
    byDay[dateKey] = {
      summary: { distance_m: 0, moving_s: 0, visits: 0 },
      segments: [],
      rawSignals: []
    };
  }
  return byDay[dateKey];
}

// Converte timestamp (segundos) para data local YYYY-MM-DD
function tsToLocalDateKey(ts) {
  const d = new Date(ts * 1000);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return y + '-' + m + '-' + day;
}

// Converte string ISO com offset (2025-11-14T04:41:00.000-03:00) -> ts em segundos
function parseTimeToTs(isoString) {
  if (!isoString) return null;
  const ms = Date.parse(isoString);
  if (!Number.isFinite(ms)) return null;
  return Math.floor(ms / 1000);
}

// Parse de "-23.588637°, -46.700664°" ou objeto latitudeE7/longitudeE7
function parseLatLng(latlng) {
  if (!latlng) return null;

  if (typeof latlng === 'string') {
    let s = latlng.replace(/°/g, '').trim();
    s = s.replace(/\s+/g, '');
    const parts = s.split(',');
    if (parts.length !== 2) return null;
    const lat = parseFloat(parts[0]);
    const lng = parseFloat(parts[1]);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
    return { lat: lat, lng: lng };
  }

  if (typeof latlng === 'object' && latlng) {
    if (typeof latlng.latLng === 'string') {
      return parseLatLng(latlng.latLng);
    }
    if (latlng.latitudeE7 != null && latlng.longitudeE7 != null) {
      const lat = latlng.latitudeE7 / 1e7;
      const lng = latlng.longitudeE7 / 1e7;
      return { lat: lat, lng: lng };
    }
  }

  return null;
}

function hashUid(str) {
  let h = 0;
  for (let i = 0; i < str.length; i++) {
    const chr = str.charCodeAt(i);
    h = ((h << 5) - h) + chr;
    h |= 0;
  }
  return 'h' + (h >>> 0).toString(16);
}

function haversineDistance(lat1, lng1, lat2, lng2) {
  const R = 6371000; // metros
  const toRad = function (v) { return v * Math.PI / 180; };
  const φ1 = toRad(lat1);
  const φ2 = toRad(lat2);
  const Δφ = toRad(lat2 - lat1);
  const Δλ = toRad(lng2 - lng1);

  const a =
    Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
    Math.cos(φ1) * Math.cos(φ2) *
    Math.sin(Δλ / 2) * Math.sin(Δλ / 2);

  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

function computePathDistance(path) {
  if (!Array.isArray(path) || path.length < 2) return 0;
  let dist = 0;
  for (let i = 1; i < path.length; i++) {
    const p1 = path[i - 1];
    const p2 = path[i];
    dist += haversineDistance(p1[0], p1[1], p2[0], p2[1]);
  }
  return dist;
}

function recomputeSummary(day) {
  const summary = { distance_m: 0, moving_s: 0, visits: 0 };
  if (!day.segments) {
    day.summary = summary;
    return;
  }
  for (let i = 0; i < day.segments.length; i++) {
    const seg = day.segments[i];
    if (!seg) continue;
    if (seg.kind === 'move') {
      summary.distance_m += seg.distance_m || 0;
      summary.moving_s   += seg.duration_s || 0;
    } else if (seg.kind === 'place') {
      summary.visits += 1;
    }
  }
  day.summary = summary;
}

// ---------------------------------------------------------------------------
// 1) semanticSegments -> segmentos (fallback, se não houver rawSignals suficientes)
// ---------------------------------------------------------------------------

function handleSemanticSegments(semanticSegments, byDay, sourceFileName, placeCache) {
  preloadPlaceCacheFromSegments(semanticSegments, placeCache);

  for (let i = 0; i < semanticSegments.length; i++) {
    const s = semanticSegments[i];
    if (!s) continue;

    const start_ts = parseTimeToTs(s.startTime);
    const end_ts   = parseTimeToTs(s.endTime);
    if (!start_ts || !end_ts) continue;

    const duration_s = Math.max(0, end_ts - start_ts);
    const baseDateKey = tsToLocalDateKey(start_ts);
    const dayForSeg   = ensureDay(byDay, baseDateKey);

    let seg = null;

    if (Array.isArray(s.timelinePath) && s.timelinePath.length) {
      seg = buildSegmentFromTimelinePath(s.timelinePath, start_ts, end_ts, duration_s, byDay, sourceFileName);
    } else if (s.visit) {
      seg = buildSegmentFromVisit(s.visit, start_ts, end_ts, duration_s, sourceFileName, placeCache);
    } else if (s.activity) {
      seg = buildSegmentFromActivity(s.activity, start_ts, end_ts, duration_s, sourceFileName);
    } else if (s.timelineMemory) {
      seg = buildSegmentFromTimelineMemory(s.timelineMemory, start_ts, end_ts, duration_s, sourceFileName, placeCache);
    } else if (duration_s >= 5 * 60) {
      seg = {
        uid: hashUid('sem-fallback-' + start_ts + '-' + end_ts),
        kind: 'place',
        mode: null,
        place_name: null,
        address: null,
        start_ts: start_ts,
        end_ts: end_ts,
        duration_s: duration_s,
        distance_m: 0,
        lat: null,
        lng: null,
        path: null,
        raw_source: 'semanticSegments',
        source_file: sourceFileName || null
      };
    }

    if (seg) {
      dayForSeg.segments.push(seg);
    }
  }
}

function buildSegmentFromTimelinePath(timelinePath, start_ts, end_ts, duration_s, byDay, sourceFileName) {
  if (!Array.isArray(timelinePath) || !timelinePath.length) return null;

  const path = [];

  for (let j = 0; j < timelinePath.length; j++) {
    const p = timelinePath[j];
    const coords = parseLatLng(p.point || p.LatLng || p.latLng || p || null);
    if (!coords) continue;

    path.push([coords.lat, coords.lng]);

    const tsPoint = parseTimeToTs(p.time);
    if (tsPoint) {
      const dateKeyPoint = tsToLocalDateKey(tsPoint);
      const dayForRaw = ensureDay(byDay, dateKeyPoint);
      dayForRaw.rawSignals.push({
        kind: 'sem_path',
        ts: tsPoint,
        lat: coords.lat,
        lng: coords.lng,
        accuracy_m: null,
        altitude_m: null,
        speed_mps: null,
        source: 'semantic.timelinePath',
        wifi_devices: null,
        source_file: sourceFileName || null,
        raw_source: 'semantic.timelinePath'
      });
    }
  }

  if (!path.length) {
    return null;
  }

  let distance_m = path.length > 1 ? computePathDistance(path) : 0;
  let kind = 'move';
  let lat = path[0][0];
  let lng = path[0][1];

  if (path.length === 1 || distance_m < 150) {
    kind = 'place';
    distance_m = 0;
  }

  return {
    uid: hashUid('sem-path-' + start_ts + '-' + end_ts + '-' + kind),
    kind: kind,
    mode: null,
    place_name: null,
    address: null,
    start_ts: start_ts,
    end_ts: end_ts,
    duration_s: duration_s,
    distance_m: Math.round(distance_m),
    lat: lat,
    lng: lng,
    path: kind === 'move' ? path : null,
    raw_source: 'semantic.timelinePath',
    source_file: sourceFileName || null
  };
}

function buildSegmentFromVisit(visit, start_ts, end_ts, duration_s, sourceFileName, placeCache) {
  if (!visit) return null;
  if (typeof visit.hierarchyLevel === 'number' && visit.hierarchyLevel > 0) {
    return null;
  }
  const top = visit.topCandidate || {};
  let coords = null;
  if (top.placeLocation) {
    coords = parseLatLng(top.placeLocation);
  }
  if (!coords && Array.isArray(visit.otherCandidates)) {
    for (let i = 0; i < visit.otherCandidates.length && !coords; i++) {
      const candidate = visit.otherCandidates[i];
      if (candidate && candidate.placeLocation) {
        coords = parseLatLng(candidate.placeLocation);
      }
    }
  }
  if (!coords && top.placeId && placeCache[top.placeId]) {
    coords = {
      lat: placeCache[top.placeId].lat,
      lng: placeCache[top.placeId].lng
    };
  }

  const semanticType = top.semanticType || visit.label || null;
  const placeLabel = labelForSemanticType(semanticType);

  if (top.placeId && coords) {
    rememberPlaceLocation(placeCache, top.placeId, coords, placeLabel);
  }

  return {
    uid: hashUid('sem-visit-' + start_ts + '-' + end_ts),
    kind: 'place',
    mode: null,
    place_name: placeLabel,
    address: null,
    start_ts: start_ts,
    end_ts: end_ts,
    duration_s: duration_s,
    distance_m: 0,
    lat: coords ? coords.lat : null,
    lng: coords ? coords.lng : null,
    path: null,
    raw_source: 'semantic.visit',
    source_file: sourceFileName || null
  };
}

function buildSegmentFromActivity(activity, start_ts, end_ts, duration_s, sourceFileName) {
  if (!activity) return null;
  const startCoords = parseLatLng((activity.start && (activity.start.latLng || activity.start)) || null);
  const endCoords   = parseLatLng((activity.end && (activity.end.latLng || activity.end)) || null);
  if (!startCoords || !endCoords) return null;

  const path = [
    [startCoords.lat, startCoords.lng],
    [endCoords.lat, endCoords.lng]
  ];

  let distance_m = 0;
  if (activity.distanceMeters != null) {
    distance_m = activity.distanceMeters;
  } else {
    distance_m = computePathDistance(path);
  }

  const mode = normalizeActivityMode(activity.topCandidate && activity.topCandidate.type);

  return {
    uid: hashUid('sem-activity-' + start_ts + '-' + end_ts),
    kind: 'move',
    mode: mode,
    place_name: null,
    address: null,
    start_ts: start_ts,
    end_ts: end_ts,
    duration_s: duration_s,
    distance_m: Math.round(distance_m || 0),
    lat: startCoords.lat,
    lng: startCoords.lng,
    path: path,
    raw_source: 'semantic.activity',
    source_file: sourceFileName || null
  };
}

function buildSegmentFromTimelineMemory(memory, start_ts, end_ts, duration_s, sourceFileName, placeCache) {
  if (!memory || !memory.trip || !Array.isArray(memory.trip.destinations)) {
    return null;
  }

  const coordsInfo = [];
  for (let i = 0; i < memory.trip.destinations.length; i++) {
    const dest = memory.trip.destinations[i];
    const placeId = dest && dest.identifier ? dest.identifier.placeId : null;
    if (placeId && placeCache[placeId]) {
      coordsInfo.push({
        lat: placeCache[placeId].lat,
        lng: placeCache[placeId].lng,
        label: placeCache[placeId].label || null
      });
    }
  }

  if (!coordsInfo.length) {
    return null;
  }

  const coords = coordsInfo.map(info => [info.lat, info.lng]);

  const distance_km = memory.trip.distanceFromOriginKms != null
    ? memory.trip.distanceFromOriginKms
    : (computePathDistance(coords) / 1000);

  const labels = coordsInfo.map(info => info.label).filter(Boolean);
  const labelSummary = labels.length ? labels.join(' → ') : null;

  return {
    uid: hashUid('sem-memory-' + start_ts + '-' + end_ts),
    kind: coords.length > 1 ? 'move' : 'place',
    mode: 'trip_memory',
    place_name: 'Memória de viagem',
    address: labelSummary,
    start_ts: start_ts,
    end_ts: end_ts,
    duration_s: duration_s,
    distance_m: Math.round(distance_km * 1000),
    lat: coords[0][0],
    lng: coords[0][1],
    path: coords.length > 1 ? coords : null,
    raw_source: 'semantic.timelineMemory',
    source_file: sourceFileName || null
  };
}

function labelForSemanticType(type) {
  if (!type) return 'Parada';
  const normalized = type.toUpperCase();
  switch (normalized) {
    case 'HOME':
      return 'Casa';
    case 'WORK':
      return 'Trabalho';
    case 'INFERRED_HOME':
      return 'Casa (inferido)';
    case 'INFERRED_WORK':
      return 'Trabalho (inferido)';
    case 'SEARCHED_ADDRESS':
      return 'Endereço pesquisado';
    default:
      return 'Parada';
  }
}

function normalizeActivityMode(type) {
  if (!type) return null;
  const normalized = type.toLowerCase();
  if (normalized === 'unknown_activity_type' || normalized === 'unknown') {
    return null;
  }
  return normalized;
}

function rememberPlaceLocation(cache, placeId, coords, label) {
  if (!placeId || !coords) return;
  cache[placeId] = {
    lat: coords.lat,
    lng: coords.lng,
    label: label || null
  };
}


// ---------------------------------------------------------------------------
// 2) rawSignals -> day.rawSignals (position + wifi)
// ---------------------------------------------------------------------------

function handleRawSignals(rawSignalsArr, byDay, sourceFileName) {
  for (let i = 0; i < rawSignalsArr.length; i++) {
    const item = rawSignalsArr[i];
    if (!item || typeof item !== 'object') continue;

    // position
    if (item.position) {
      const pos = item.position;
      const ts  = parseTimeToTs(pos.timestamp);
      const coords = parseLatLng(pos.LatLng || pos.latLng || pos.point || null);
      if (!ts || !coords) continue;

      const dateKey = tsToLocalDateKey(ts);
      const day = ensureDay(byDay, dateKey);

      day.rawSignals.push({
        kind: 'position',
        ts: ts,
        lat: coords.lat,
        lng: coords.lng,
        accuracy_m: pos.accuracyMeters != null ? pos.accuracyMeters : null,
        altitude_m: pos.altitudeMeters != null ? pos.altitudeMeters : null,
        speed_mps: pos.speedMetersPerSecond != null ? pos.speedMetersPerSecond : null,
        source: pos.source || null,
        wifi_devices: null,
        source_file: sourceFileName || null,
        raw_source: 'raw.position'
      });
    }

    // wifiScan
    if (item.wifiScan) {
      const ws = item.wifiScan;
      const ts  = parseTimeToTs(ws.deliveryTime);
      if (!ts) continue;

      const dateKey = tsToLocalDateKey(ts);
      const day = ensureDay(byDay, dateKey);
      const devCount =
        Array.isArray(ws.devicesRecords) ? ws.devicesRecords.length : 0;

      day.rawSignals.push({
        kind: 'wifi',
        ts: ts,
        lat: null,
        lng: null,
        accuracy_m: null,
        altitude_m: null,
        speed_mps: null,
        source: 'wifiScan',
        wifi_devices: devCount,
        source_file: sourceFileName || null,
        raw_source: 'raw.wifi'
      });
    }

    // activityRecord ainda não está sendo usado, mas pode servir depois
  }
}

function cleanupRawSignals(byDay) {
  const dates = Object.keys(byDay);
  for (let i = 0; i < dates.length; i++) {
    const day = byDay[dates[i]];
    if (!day || !Array.isArray(day.rawSignals) || !day.rawSignals.length) {
      continue;
    }

    const hasRealPos = day.rawSignals.some(function (r) {
      if (r.kind !== 'position') return false;
      if (r.raw_source && r.raw_source === 'semantic.timelinePath') return false;
      if (!r.raw_source && r.source === 'semantic.timelinePath') return false;
      return true;
    });

    if (!hasRealPos) continue;

    day.rawSignals = day.rawSignals.filter(function (r) {
      if (r.kind === 'sem_path') return false;
      if (r.raw_source && r.raw_source === 'semantic.timelinePath') return false;
      if (!r.raw_source && r.source === 'semantic.timelinePath') return false;
      return true;
    });
  }
}

function ensurePlaceholderSegments(byDay) {
  const dates = Object.keys(byDay);
  for (let i = 0; i < dates.length; i++) {
    const dateKey = dates[i];
    const day = byDay[dateKey];
    if (!day) continue;

    if (Array.isArray(day.segments) && day.segments.length) {
      continue;
    }

    const candidate = pickRepresentativePoint(day);
    if (!candidate) continue;

    const ts = candidate.ts || parseTimeToTs(dateKey + 'T12:00:00');
    const placeholder = {
      uid: hashUid('placeholder-' + dateKey + '-' + ts),
      kind: 'place',
      place_name: candidate.place_name || 'Registro',
      address: null,
      start_ts: ts,
      end_ts: ts,
      duration_s: 0,
      distance_m: 0,
      lat: candidate.lat,
      lng: candidate.lng,
      path: null,
      raw_source: candidate.raw_source || 'placeholder',
      source_file: candidate.source_file || null
    };

    day.segments = [placeholder];
  }
}

function pickRepresentativePoint(day) {
  if (!day) return null;
  const signals = (day.rawSignals || [])
    .filter(r => r && r.lat != null && r.lng != null);

  if (!signals.length) return null;

  const positions = signals.filter(r => r.kind === 'position');
  const pool = positions.length ? positions : signals;

  pool.sort((a, b) => (a.ts || 0) - (b.ts || 0));
  return pool[Math.floor(pool.length / 2)];
}

// ---------------------------------------------------------------------------
// 3) Criar segmentos (paradas + deslocamentos) a partir de rawSignals.position
// ---------------------------------------------------------------------------

function buildSegmentsFromRawSignals(byDay) {
  const dates = Object.keys(byDay);
  for (let i = 0; i < dates.length; i++) {
    const dateKey = dates[i];
    const day = byDay[dateKey];

    const positions = (day.rawSignals || [])
      .filter(function (r) {
        return r.kind === 'position' &&
               r.lat != null && r.lng != null && r.ts != null;
      })
      .sort(function (a, b) {
        return a.ts - b.ts;
      });

    if (positions.length < 2) {
      // poucos pontos -> mantém o que semanticSegments criou (se existir)
      continue;
    }

    const segs = deriveSegmentsFromPositions(positions);
    if (segs.length && (!Array.isArray(day.segments) || !day.segments.length)) {
      day.segments = segs;
    }
  }
}

function deriveSegmentsFromPositions(positions) {
  const result = [];
  if (!positions || positions.length < 2) return result;

  // Parâmetros (mais permissivos):
  const MIN_STOP_DURATION = 5 * 60;   // 5 minutos parado
  const MAX_STOP_SPEED    = 1.2;      // m/s (~4,3 km/h)
  const MAX_STOP_RADIUS   = 150;      // metros

  const n = positions.length;
  let i = 0;
  const stops = [];

  while (i < n - 1) {
    const anchor = positions[i];
    let j = i;
    let maxDistFromAnchor = 0;

    while (j + 1 < n) {
      const prev = positions[j];
      const p    = positions[j + 1];

      const dtPrev   = p.ts - prev.ts;
      const distPrev = haversineDistance(prev.lat, prev.lng, p.lat, p.lng);
      const speedPrev = dtPrev > 0 ? distPrev / dtPrev : 0;

      const distAnchor = haversineDistance(anchor.lat, anchor.lng, p.lat, p.lng);
      if (distAnchor > maxDistFromAnchor) {
        maxDistFromAnchor = distAnchor;
      }

      const speedSource =
        (p.speed_mps != null ? p.speed_mps : speedPrev);
      const speedOK  = speedSource <= MAX_STOP_SPEED;
      const radiusOK = maxDistFromAnchor <= MAX_STOP_RADIUS;

      if (speedOK && radiusOK) {
        j++;
      } else {
        break;
      }
    }

    const duration = positions[j].ts - anchor.ts;
    if (j > i && duration >= MIN_STOP_DURATION) {
      stops.push({ startIndex: i, endIndex: j });
      i = j + 1;
    } else {
      i++;
    }
  }

  if (!stops.length) {
    const moveSeg = makeMoveSegmentFromPositions(positions, 0, n - 1);
    if (moveSeg) result.push(moveSeg);
    return result;
  }

  let cursor = 0;
  for (let s = 0; s < stops.length; s++) {
    const st = stops[s];
    if (st.startIndex > cursor) {
      const moveSeg = makeMoveSegmentFromPositions(positions, cursor, st.startIndex);
      if (moveSeg) result.push(moveSeg);
    }
    const placeSeg = makePlaceSegmentFromPositions(positions, st.startIndex, st.endIndex);
    if (placeSeg) result.push(placeSeg);
    cursor = st.endIndex + 1;
  }

  if (cursor < n - 1) {
    const moveSeg = makeMoveSegmentFromPositions(positions, cursor, n - 1);
    if (moveSeg) result.push(moveSeg);
  }

  return result;
}

function makeMoveSegmentFromPositions(positions, startIndex, endIndex) {
  if (endIndex <= startIndex) return null;

  const slice = positions.slice(startIndex, endIndex + 1);
  const path = [];
  for (let i = 0; i < slice.length; i++) {
    path.push([slice[i].lat, slice[i].lng]);
  }

  const start_ts = slice[0].ts;
  const end_ts   = slice[slice.length - 1].ts;
  const duration_s = Math.max(0, end_ts - start_ts);
  const distance_m = computePathDistance(path);

  if (!duration_s && !distance_m) {
    return null;
  }

  const uid = hashUid('rs-move-' + start_ts + '-' + end_ts);

  return {
    uid: uid,
    kind: 'move',
    mode: null,
    place_name: null,
    address: null,
    start_ts: start_ts,
    end_ts: end_ts,
    duration_s: duration_s,
    distance_m: Math.round(distance_m),
    lat: slice[0].lat,
    lng: slice[0].lng,
    path: path,
    raw_source: 'rawSignals',
    source_file: null
  };
}

function makePlaceSegmentFromPositions(positions, startIndex, endIndex) {
  if (endIndex < startIndex) return null;

  const slice = positions.slice(startIndex, endIndex + 1);
  const start_ts = slice[0].ts;
  const end_ts   = slice[slice.length - 1].ts;
  const duration_s = Math.max(0, end_ts - start_ts);

  let latSum = 0;
  let lngSum = 0;
  for (let i = 0; i < slice.length; i++) {
    latSum += slice[i].lat;
    lngSum += slice[i].lng;
  }
  const latAvg = latSum / slice.length;
  const lngAvg = lngSum / slice.length;

  const uid = hashUid('rs-place-' + start_ts + '-' + end_ts);

  return {
    uid: uid,
    kind: 'place',
    mode: null,
    place_name: 'Parada',
    address: null,
    start_ts: start_ts,
    end_ts: end_ts,
    duration_s: duration_s,
    distance_m: 0,
    lat: latAvg,
    lng: lngAvg,
    path: null,
    raw_source: 'rawSignals',
    source_file: null
  };
}

// ---------------------------------------------------------------------------
// Envio em lotes para o servidor (api/import_segments.php)
// ---------------------------------------------------------------------------

async function sendBatches(byDay) {
  const dates = Object.keys(byDay).sort();
  const totalDays = dates.length;
  const BATCH_SIZE = 20;

  let sent = 0;

  for (let i = 0; i < dates.length; i += BATCH_SIZE) {
    const chunkDates = dates.slice(i, i + BATCH_SIZE);
    const payloadDays = {};
    for (let j = 0; j < chunkDates.length; j++) {
      const d = chunkDates[j];
      payloadDays[d] = byDay[d];
    }

    setStatus('Enviando dias ' + (i + 1) + '–' + (i + chunkDates.length) + ' de ' + totalDays + '...');

    const res = await fetch('api/import_segments.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ days: payloadDays })
    });

    let json = null;
    try {
      json = await res.json();
    } catch (err) {
      console.error('Falha ao ler resposta JSON do servidor', err);
    }

    if (!res.ok || !json || json.ok !== true) {
      const msg = json && json.error ? json.error : ('HTTP ' + res.status);
      console.error('Erro ao enviar lote:', msg);
      throw new Error('Erro ao enviar lote: ' + msg);
    }

    sent += chunkDates.length;
  }

  setStatus('Todos os ' + totalDays + ' dias foram enviados.');
}
