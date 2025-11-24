// assets/app.js
// Mapa + timeline + rawSignals (com toggle) + navegação por dias com dados + cluster de paradas

let map;
let segmentsLayer;
let rawSignalsLayer;

let currentDate = null;
let availableDates = [];
let currentSegments = [];
let placeClustersForCurrentDay = [];

let rawSignalsData = [];
let rawSignalsVisible = false;

const MODE_PRESETS = {
  'walking':            { label: 'A pé',               icon: '🚶', color: '#16a34a' },
  'on_foot':            { label: 'A pé',               icon: '🚶', color: '#16a34a' },
  'running':            { label: 'Correndo',           icon: '🏃', color: '#ea580c' },
  'on_bicycle':         { label: 'Bicicleta',          icon: '🚴', color: '#22c55e' },
  'in_passenger_vehicle': { label: 'Carro',            icon: '🚗', color: '#0ea5e9' },
  'in_vehicle':         { label: 'Em veículo',         icon: '🚗', color: '#0ea5e9' },
  'in_road_vehicle':    { label: 'Em veículo',         icon: '🚗', color: '#0ea5e9' },
  'in_motor_vehicle':   { label: 'Em veículo',         icon: '🚗', color: '#0ea5e9' },
  'in_bus':             { label: 'Ônibus',             icon: '🚌', color: '#f59e0b' },
  'in_subway':          { label: 'Metrô',              icon: '🚇', color: '#8b5cf6' },
  'in_train':           { label: 'Trem',               icon: '🚆', color: '#6366f1' },
  'in_rail_vehicle':    { label: 'Trem',               icon: '🚆', color: '#6366f1' },
  'in_tram':            { label: 'Bonde/VLT',          icon: '🚊', color: '#14b8a6' },
  'in_ferry':           { label: 'Balsa',              icon: '⛴️', color: '#0ea5e9' },
  'flying':             { label: 'Avião',              icon: '✈️', color: '#c026d3' },
  'in_flight':          { label: 'Avião',              icon: '✈️', color: '#c026d3' },
  'trip_memory':        { label: 'Memória de viagem',  icon: '🧳', color: '#1e1b4b' },
  'in_motorcycle':      { label: 'Moto',               icon: '🏍️', color: '#f97316' },
  'in_taxi':            { label: 'Táxi',               icon: '🚕', color: '#facc15' }
};
// Elementos de UI (IDs do index.php)
const dayPicker       = document.getElementById('day-picker');
const prevDayBtn      = document.getElementById('prev-day');
const nextDayBtn      = document.getElementById('next-day');
const summaryBox      = document.getElementById('summary');
const segmentsListBox = document.getElementById('segments-list');
const toggleRaw       = document.getElementById('toggle-rawsignals');
const timelinePanel   = document.getElementById('timeline-panel');
const panelHandle     = document.getElementById('panel-drag-handle');

const mobileSheet = {
  enabled: false,
  dragging: false,
  state: 'expanded',
  bounds: { max: 0 },
  startY: 0,
  startOffset: 0,
  lastOffset: 0,
  pointerId: null,
  pointerTarget: null,
  mq: null
};

const MOBILE_BREAKPOINT = window.matchMedia
  ? window.matchMedia('(max-width: 768px)')
  : null;

// ---------------------------------------------------------------------------
// Inicialização
// ---------------------------------------------------------------------------

window.addEventListener('DOMContentLoaded', () => {
  initMap();
  bindUI();
  initMobilePanelSheet();
  loadDaysList();
});

function bindUI() {
  if (toggleRaw) {
    toggleRaw.addEventListener('change', () => {
      rawSignalsVisible = toggleRaw.checked;
      updateRawSignalsLayer();
    });
  }

  if (dayPicker) {
    dayPicker.addEventListener('change', () => {
      if (dayPicker.value) {
        loadDay(dayPicker.value);
      }
    });
  }

  if (prevDayBtn) {
    prevDayBtn.addEventListener('click', () => shiftDay(-1));
  }
  if (nextDayBtn) {
    nextDayBtn.addEventListener('click', () => shiftDay(1));
  }

  // exposto para o import.js recarregar a lista depois de importar
  window.loadDaysList = loadDaysList;
}

// ---------------------------------------------------------------------------
// Painel móvel (bottom sheet) para mobile
// ---------------------------------------------------------------------------

function initMobilePanelSheet() {
  if (!timelinePanel || !panelHandle || !MOBILE_BREAKPOINT) {
    return;
  }

  mobileSheet.mq = MOBILE_BREAKPOINT;
  MOBILE_BREAKPOINT.addEventListener
    ? MOBILE_BREAKPOINT.addEventListener('change', handleSheetBreakpointChange)
    : MOBILE_BREAKPOINT.addListener(handleSheetBreakpointChange);

  handleSheetBreakpointChange(MOBILE_BREAKPOINT);

  const dragOrigins = [
    panelHandle,
    timelinePanel.querySelector('.panel-header')
  ];
  dragOrigins.forEach(origin => {
    if (!origin) return;
    origin.addEventListener('pointerdown', onSheetPointerDown);
  });

  panelHandle.addEventListener('click', () => {
    if (!mobileSheet.enabled || mobileSheet.dragging) return;
    toggleSheetState();
  });

  window.addEventListener('resize', () => {
    if (mobileSheet.enabled) {
      calculateSheetBounds();
      snapSheetToState();
    }
  });
}

function handleSheetBreakpointChange(evt) {
  const isMobile = !!evt.matches;
  mobileSheet.enabled = isMobile;
  if (!timelinePanel) return;

  timelinePanel.classList.toggle('mobile-sheet', isMobile);

  if (!isMobile) {
    timelinePanel.style.setProperty('--panel-offset-y', '0px');
    mobileSheet.state = 'expanded';
    timelinePanel.classList.remove('no-transition', 'dragging');
    return;
  }

  calculateSheetBounds();
  snapSheetToState();
}

function calculateSheetBounds() {
  if (!timelinePanel) return;
  const panelHeight = timelinePanel.offsetHeight || window.innerHeight;
  const collapsedHeight = getCollapsedHeight();
  const maxOffset = Math.max(0, panelHeight - collapsedHeight);
  mobileSheet.bounds.max = maxOffset;
}

function getCollapsedHeight() {
  if (!timelinePanel) return 0;
  const handleHeight = panelHandle ? panelHandle.offsetHeight : 0;
  const header = timelinePanel.querySelector('.panel-header');
  const controls = timelinePanel.querySelector('.panel-controls');

  let total = 16 + handleHeight;
  if (header) total += header.offsetHeight;
  if (controls) total += controls.offsetHeight;
  total += 24; // espaço inferior

  const panelHeight = timelinePanel.offsetHeight || window.innerHeight;
  const maxAvailable = Math.max(120, panelHeight - 80);
  return Math.min(maxAvailable, total);
}

function onSheetPointerDown(evt) {
  if (!mobileSheet.enabled || evt.pointerType === 'mouse' && window.innerWidth > 768) {
    return;
  }
  if (evt.currentTarget !== panelHandle && isPointerOnInteractive(evt)) {
    return;
  }
  evt.preventDefault();
  mobileSheet.dragging = true;
  mobileSheet.startY = evt.clientY;
  mobileSheet.startOffset = getCurrentSheetOffset();
  mobileSheet.pointerId = evt.pointerId;
  mobileSheet.pointerTarget = evt.currentTarget;

  if (mobileSheet.pointerTarget && mobileSheet.pointerId != null &&
      mobileSheet.pointerTarget.setPointerCapture) {
    mobileSheet.pointerTarget.setPointerCapture(mobileSheet.pointerId);
  }

  timelinePanel.classList.add('dragging', 'no-transition');
  window.addEventListener('pointermove', onSheetPointerMove);
  window.addEventListener('pointerup', onSheetPointerUp);
  window.addEventListener('pointercancel', onSheetPointerUp);
}

function onSheetPointerMove(evt) {
  if (!mobileSheet.dragging) return;
  evt.preventDefault();
  const delta = evt.clientY - mobileSheet.startY;
  const next = mobileSheet.startOffset + delta;
  setSheetOffset(next, false);
}

function onSheetPointerUp(evt) {
  if (!mobileSheet.dragging) return;
  if (mobileSheet.pointerTarget && mobileSheet.pointerId != null &&
      mobileSheet.pointerTarget.releasePointerCapture) {
    mobileSheet.pointerTarget.releasePointerCapture(mobileSheet.pointerId);
  }

  window.removeEventListener('pointermove', onSheetPointerMove);
  window.removeEventListener('pointerup', onSheetPointerUp);
  window.removeEventListener('pointercancel', onSheetPointerUp);

  timelinePanel.classList.remove('dragging', 'no-transition');

  mobileSheet.dragging = false;
  mobileSheet.pointerTarget = null;
  mobileSheet.pointerId = null;

  const halfway = mobileSheet.bounds.max * 0.5;
  mobileSheet.state = mobileSheet.lastOffset > halfway ? 'collapsed' : 'expanded';
  snapSheetToState();
}

function setSheetOffset(value, animate = true) {
  if (!timelinePanel) return;
  const clamped = Math.min(
    Math.max(0, value),
    mobileSheet.bounds.max
  );
  mobileSheet.lastOffset = clamped;
  timelinePanel.style.setProperty('--panel-offset-y', clamped + 'px');

  if (animate) {
    timelinePanel.classList.remove('no-transition');
  } else {
    timelinePanel.classList.add('no-transition');
  }
}

function snapSheetToState() {
  const target = mobileSheet.state === 'collapsed' ? mobileSheet.bounds.max : 0;
  setSheetOffset(target, true);
}

function toggleSheetState() {
  if (!mobileSheet.enabled) return;
  mobileSheet.state = mobileSheet.state === 'collapsed' ? 'expanded' : 'collapsed';
  snapSheetToState();
}

function getCurrentSheetOffset() {
  if (!timelinePanel) return 0;
  const raw = getComputedStyle(timelinePanel).getPropertyValue('--panel-offset-y');
  const numeric = parseFloat(raw);
  return Number.isFinite(numeric) ? numeric : 0;
}

function isPointerOnInteractive(evt) {
  const target = evt.target;
  if (!target) return false;
  return !!target.closest('button, input, select, textarea, label, a');
}

// ---------------------------------------------------------------------------
// Mapa
// ---------------------------------------------------------------------------

function initMap() {
  if (map) return;

  map = L.map('map');
  const defaultCenter = [-23.55, -46.63];
  map.setView(defaultCenter, 12);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 20,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  segmentsLayer   = L.layerGroup().addTo(map);
  rawSignalsLayer = L.layerGroup();
}

// ---------------------------------------------------------------------------
// Carregamento de dias (lista lateral + navegação)
// ---------------------------------------------------------------------------

async function loadDaysList() {
  try {
    const res = await fetch('api/days_list.php', { credentials: 'include' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const raw = await res.json();

    let dates = [];

    // Formatos possíveis:
    // 1) ["2025-11-14", ...]
    // 2) { ok:true, days:["2025-11-14", ...] }
    // 3) { ok:true, days:[ {date:"2025-11-14", summary:{...}}, ... ] }
    if (Array.isArray(raw)) {
      dates = raw;
    } else if (raw && Array.isArray(raw.days)) {
      dates = raw.days
        .map(d => {
          if (typeof d === 'string') return d;
          if (d && typeof d.date === 'string') return d.date;
          return null;
        })
        .filter(Boolean);
    } else {
      console.warn('Formato inesperado de days_list.php:', raw);
    }

    availableDates = dates.slice().sort();

    // escolhe último dia (mais recente) como padrão
    const last = availableDates[availableDates.length - 1];
    if (!currentDate) {
      loadDay(last);
    } else if (!availableDates.includes(currentDate)) {
      loadDay(last);
    } else {
      // mantém o dia atual, mas atualiza select/date se precisar
      setCurrentDateUI(currentDate);
    }

  } catch (err) {
    console.error(err);
    if (summaryBox) {
      summaryBox.textContent = 'Erro ao carregar lista de dias: ' + (err.message || err);
    }
  }
}

function setCurrentDateUI(date) {
  currentDate = date;
  if (dayPicker) {
    dayPicker.value = date || '';
  }
}

// ---------------------------------------------------------------------------
// Navegação prev/next usando apenas dias com dados
// ---------------------------------------------------------------------------

function shiftDay(delta) {
  if (!availableDates.length) return;

  let idx = availableDates.indexOf(currentDate);
  if (idx === -1) {
    // Se por algum motivo o currentDate não estiver na lista,
    // posiciona no começo/fim conforme o delta
    idx = delta > 0 ? -1 : availableDates.length;
  }

  const newIdx = idx + delta;
  if (newIdx < 0 || newIdx >= availableDates.length) {
    // chegou no começo/fim da lista de dias com dados
    return;
  }

  const newDate = availableDates[newIdx];
  loadDay(newDate);
}

function parseISODate(value) {
  if (!value) return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  if (!match) return null;
  const year = Number(match[1]);
  const month = Number(match[2]) - 1;
  const day = Number(match[3]);
  return new Date(year, month, day);
}

// ---------------------------------------------------------------------------
// Carregamento de um dia específico
// ---------------------------------------------------------------------------

async function loadDay(date) {
  try {
    const res = await fetch('api/day.php?date=' + encodeURIComponent(date), {
      credentials: 'include'
    });
    const data = await res.json();

    if (data.error) {
      if (summaryBox)      summaryBox.textContent = 'Nenhum dado para este dia.';
      if (segmentsListBox) segmentsListBox.innerHTML = '';
      rawSignalsData = [];
      updateRawSignalsLayer();
      placeClustersForCurrentDay = [];
      currentSegments = [];
      return;
    }

    setCurrentDateUI(data.date);

    renderSummary(data.summary || {});
    currentSegments = (data.segments || [])
      .slice()
      .sort((a, b) => (a.start_ts || 0) - (b.start_ts || 0));

    rawSignalsData = data.rawSignals || [];
    updateRawSignalsLayer();

    // clusters de paradas para o mapa (agrupa paradas picadas)
    placeClustersForCurrentDay = buildPlaceClusters(currentSegments);

    renderSegments(currentSegments);

  } catch (err) {
    console.error(err);
    if (summaryBox) {
      summaryBox.textContent = 'Erro ao carregar dia: ' + (err.message || err);
    }
  }
}

// ---------------------------------------------------------------------------
// Resumo
// ---------------------------------------------------------------------------

function renderSummary(summary) {
  const distKm  = (summary.distance_m || 0) / 1000;
  const movingH = (summary.moving_s || 0) / 3600;
  const visits  = summary.visits || 0;

  if (!summaryBox) return;

  summaryBox.innerHTML = `
    <span>Distância: ${distKm.toFixed(1)} km</span>
    <span>Tempo em movimento: ${movingH.toFixed(1)} h</span>
    <span>Visitas: ${visits}</span>
  `;
}

// ---------------------------------------------------------------------------
// Segmentos: lista + mapa (usando clusters de paradas)
// ---------------------------------------------------------------------------

function renderSegments(segments) {
  if (!segmentsLayer || !segmentsListBox) return;

  segmentsLayer.clearLayers();
  segmentsListBox.innerHTML = '';

  if (!segments.length) {
    segmentsListBox.innerHTML = '<div class="segment-item">Sem segmentos para este dia.</div>';
    return;
  }

  const bounds = [];
  const frag   = document.createDocumentFragment();

  // ----- Lista (mantém todas as paradas/deslocamentos) -----
  segments.forEach((seg, idx) => {
    const kind = seg.kind || 'move';

    const item = document.createElement('div');
    item.className    = 'segment-item';
    item.dataset.index = String(idx);

    const titleRow = document.createElement('div');
    titleRow.className = 'segment-title-row';

    const iconSpan = document.createElement('span');
    iconSpan.className = 'segment-icon';
    const modeInfo = kind === 'move' ? getModeInfo(seg.mode) : null;
    iconSpan.textContent = (kind === 'place')
      ? '📍'
      : (modeInfo && modeInfo.icon ? modeInfo.icon : '➡️');

    const titleSpan = document.createElement('span');
    titleSpan.className = 'segment-title';

    if (kind === 'place') {
      let label = seg.place_name;
      if (!label || label === 'Lugar') label = 'Parada';
      titleSpan.textContent = label;
    } else {
      if (modeInfo) {
        titleSpan.textContent = modeInfo.label;
      } else {
        const km = (seg.distance_m || 0) / 1000;
        titleSpan.textContent = `${km.toFixed(1)} km`;
      }
    }

    titleRow.appendChild(iconSpan);
    titleRow.appendChild(titleSpan);

    const timeRow = document.createElement('div');
    timeRow.className = 'segment-sub';

    const start = seg.start_ts ? new Date(seg.start_ts * 1000) : null;
    const end   = seg.end_ts   ? new Date(seg.end_ts   * 1000) : null;
    if (start && end) {
      timeRow.textContent = fmtTime(start) + ' – ' + fmtTime(end);
    }

    const metaRow = document.createElement('div');
    metaRow.className = 'segment-meta';
    const metaPieces = [];
    if (seg.duration_s) metaPieces.push(formatDuration(seg.duration_s));
    if (seg.distance_m) metaPieces.push((seg.distance_m / 1000).toFixed(1) + ' km');
    metaRow.textContent = metaPieces.join(' • ');

    item.appendChild(titleRow);
    item.appendChild(timeRow);
    item.appendChild(metaRow);

    item.addEventListener('click', () => {
      highlightSegment(idx);
    });

    frag.appendChild(item);
  });

  segmentsListBox.appendChild(frag);

  // ----- Mapa: linhas de deslocamento -----
  segments.forEach(seg => {
    const kind = seg.kind || 'move';
    if (kind !== 'move') return;

    const pathLatLngs = getSegmentPathLatLngs(seg);
    if (!pathLatLngs || pathLatLngs.length < 2) return;

    const modeInfo = getModeInfo(seg.mode);
    const color = modeInfo ? modeInfo.color : '#1a73e8';
    const line = L.polyline(pathLatLngs, { weight: 4, opacity: 0.85, color });
    line.addTo(segmentsLayer);
    if (modeInfo) {
      const popupLines = [
        `<strong>${modeInfo.icon ? modeInfo.icon + ' ' : ''}${modeInfo.label}</strong>`
      ];
      if (seg.distance_m) {
        popupLines.push(`${(seg.distance_m / 1000).toFixed(1)} km`);
      }
      line.bindPopup(popupLines.join('<br>'));
    }
    bounds.push(...pathLatLngs);
  });

  // ----- Mapa: um pin por cluster de parada -----
  placeClustersForCurrentDay.forEach(cluster => {
    if (cluster.lat == null || cluster.lng == null) return;

    const m = L.marker([cluster.lat, cluster.lng]).addTo(segmentsLayer);

    const start = cluster.start_ts ? new Date(cluster.start_ts * 1000) : null;
    const end   = cluster.end_ts   ? new Date(cluster.end_ts   * 1000) : null;

    let label = cluster.place_name || 'Parada';
    if (!label || label === 'Lugar') label = 'Parada';

    let popup = '<strong>' + label + '</strong>';
    if (start && end) {
      popup += '<br>' + fmtTime(start) + ' – ' + fmtTime(end);
    }

    m.bindPopup(popup);
    bounds.push([cluster.lat, cluster.lng]);
  });

  if (bounds.length) {
    map.fitBounds(bounds, { padding: [40, 40] });
  }
}

function highlightSegment(index) {
  if (!currentSegments.length || !map) return;
  const seg = currentSegments[index];
  if (!seg) return;

  // Destaque na lista
  const items = segmentsListBox.querySelectorAll('.segment-item');
  items.forEach(i => i.classList.remove('active'));
  const el = segmentsListBox.querySelector(`.segment-item[data-index="${index}"]`);
  if (el) el.classList.add('active');

  const kind = seg.kind || 'move';

  // Tenta achar o cluster onde essa parada está
  const cluster = placeClustersForCurrentDay.find(c => c.segmentIndices.includes(index));

  if (cluster && cluster.lat != null && cluster.lng != null) {
    map.setView([cluster.lat, cluster.lng], 17);
    return;
  }

  // Fallback: vai direto pro segmento
  if (kind === 'place' && seg.lat != null && seg.lng != null) {
    map.setView([seg.lat, seg.lng], 17);
  } else {
    const latlngs = getSegmentPathLatLngs(seg);
    if (!latlngs || !latlngs.length) {
      return;
    }
    const b = L.latLngBounds(latlngs);
    map.fitBounds(b, { padding: [40, 40] });
  }
}

// ---------------------------------------------------------------------------
// Clusters de paradas: agrupa paradas coladas no tempo e espaço
// ---------------------------------------------------------------------------

function buildPlaceClusters(segments) {
  const clusters = [];
  let current = null;

  const MAX_TIME_GAP = 5 * 60;  // 5 minutos entre blocos
  const MAX_DIST_M   = 120;     // ~120 m de distância máxima

  for (let i = 0; i < segments.length; i++) {
    const seg = segments[i];
    if (!seg || seg.kind !== 'place' || seg.lat == null || seg.lng == null) {
      if (current) {
        clusters.push(current);
        current = null;
      }
      continue;
    }

    const start_ts = seg.start_ts || 0;
    const end_ts   = seg.end_ts   || start_ts;
    const lat      = seg.lat;
    const lng      = seg.lng;

    if (!current) {
      current = {
        start_ts: start_ts,
        end_ts: end_ts,
        latSum: lat,
        lngSum: lng,
        count: 1,
        lat: lat,
        lng: lng,
        place_name: seg.place_name || null,
        segmentIndices: [i]
      };
      continue;
    }

    const timeGap = start_ts - current.end_ts;
    const dist    = haversineDistance(current.lat, current.lng, lat, lng);

    if (timeGap <= MAX_TIME_GAP && dist <= MAX_DIST_M) {
      // Mesma parada "agregada"
      current.end_ts = Math.max(current.end_ts, end_ts);
      current.latSum += lat;
      current.lngSum += lng;
      current.count += 1;
      current.lat = current.latSum / current.count;
      current.lng = current.lngSum / current.count;
      if (!current.place_name && seg.place_name) {
        current.place_name = seg.place_name;
      }
      current.segmentIndices.push(i);
    } else {
      // Fecha cluster atual e abre outro
      clusters.push(current);
      current = {
        start_ts: start_ts,
        end_ts: end_ts,
        latSum: lat,
        lngSum: lng,
        count: 1,
        lat: lat,
        lng: lng,
        place_name: seg.place_name || null,
        segmentIndices: [i]
      };
    }
  }

  if (current) {
    clusters.push(current);
  }

  return clusters;
}

// ---------------------------------------------------------------------------
// RawSignals: camada extra com toggle
// ---------------------------------------------------------------------------

function updateRawSignalsLayer() {
  if (!map || !rawSignalsLayer) return;

  rawSignalsLayer.clearLayers();

  if (!rawSignalsVisible) {
    if (map.hasLayer(rawSignalsLayer)) {
      map.removeLayer(rawSignalsLayer);
    }
    return;
  }

  for (const r of rawSignalsData) {
    if (r.lat == null || r.lng == null) continue;

    const marker = L.circleMarker([r.lat, r.lng], {
      radius: 6,
      weight: 2,
      opacity: 0.9,
      fillOpacity: 0.6
    });

    const t = new Date(r.ts * 1000).toLocaleString();
    const kindLabel =
      r.kind === 'wifi'
        ? 'Wi-Fi'
        : (r.kind === 'sem_path'
            ? 'Ponto de trajeto (semantic)'
            : (r.source || 'posição'));

    let html = `<div style="font-size:11px">
      <div><strong>${kindLabel}</strong></div>
      <div>${t}</div>`;

    if (r.accuracy_m) {
      html += `<div>Precisão: ~${Math.round(r.accuracy_m)} m</div>`;
    }
    if (r.wifi_devices) {
      html += `<div>APs Wi-Fi: ${r.wifi_devices}</div>`;
    }
    html += '</div>';

    marker.bindPopup(html);
    rawSignalsLayer.addLayer(marker);
  }

  if (!map.hasLayer(rawSignalsLayer)) {
    map.addLayer(rawSignalsLayer);
  }
}

// ---------------------------------------------------------------------------
// Utils
// ---------------------------------------------------------------------------

function fmtTime(d) {
  const h = String(d.getHours()).padStart(2, '0');
  const m = String(d.getMinutes()).padStart(2, '0');
  return `${h}:${m}`;
}

function formatDuration(sec) {
  const h = Math.floor(sec / 3600);
  const m = Math.floor((sec % 3600) / 60);
  if (h && m) return `${h} h ${m} min`;
  if (h) return `${h} h`;
  return `${m} min`;
}

// Haversine em metros
function haversineDistance(lat1, lng1, lat2, lng2) {
  const R = 6371000; // metros
  const toRad = v => v * Math.PI / 180;
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

function getModeInfo(mode) {
  if (!mode) return null;
  const key = String(mode).toLowerCase();
  if (MODE_PRESETS[key]) {
    return MODE_PRESETS[key];
  }
  return {
    label: humanizeMode(key),
    icon: '',
    color: '#2563eb'
  };
}

function humanizeMode(key) {
  return key
    .split(/[_\s]/g)
    .filter(Boolean)
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function getSegmentPathLatLngs(seg) {
  if (!seg) return null;

  if (Array.isArray(seg.path) && seg.path.length >= 3) {
    return seg.path.map(p => [p[0], p[1]]);
  }

  if (Array.isArray(seg._derivedPath) && seg._derivedPath.length >= 2) {
    return seg._derivedPath;
  }

  const derived = buildPathFromRawSignalsForSegment(seg);
  if (derived && derived.length >= 2) {
    seg._derivedPath = derived;
    return derived;
  }

  return null;
}

function buildPathFromRawSignalsForSegment(seg) {
  if (!rawSignalsData || !rawSignalsData.length) return null;
  if (!seg.start_ts || !seg.end_ts) return null;

  const startWindow = seg.start_ts - 120;
  const endWindow = seg.end_ts + 120;
  const candidates = [];

  for (let i = 0; i < rawSignalsData.length; i++) {
    const r = rawSignalsData[i];
    if (!r || r.kind !== 'position') continue;
    if (r.ts == null || r.lat == null || r.lng == null) continue;
    if (r.ts < startWindow || r.ts > endWindow) continue;
    candidates.push([r.lat, r.lng, r.ts]);
  }

  if (candidates.length < 2) {
    return null;
  }

  candidates.sort((a, b) => a[2] - b[2]);

  const latlngs = candidates.map(p => [p[0], p[1]]);
  return simplifyPathLatLngs(latlngs);
}

function simplifyPathLatLngs(points) {
  if (!points || points.length < 2) return points || null;
  const simplified = [];
  let lastLat = null;
  let lastLng = null;

  for (let i = 0; i < points.length; i++) {
    const lat = points[i][0];
    const lng = points[i][1];
    if (lastLat !== null) {
      const diffLat = Math.abs(lat - lastLat);
      const diffLng = Math.abs(lng - lastLng);
      if (diffLat < 1e-5 && diffLng < 1e-5) {
        continue;
      }
    }
    simplified.push([lat, lng]);
    lastLat = lat;
    lastLng = lng;

    if (simplified.length > 500) {
      break;
    }
  }

  return simplified.length >= 2 ? simplified : null;
}
