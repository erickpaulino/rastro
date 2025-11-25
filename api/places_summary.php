<?php
require __DIR__ . '/../config.php';
if (!rastro_is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'nao_autenticado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$cacheDir = __DIR__ . '/../data/cache';
$cacheFile = $cacheDir . '/places_summary.json';
$forceRefresh = isset($_GET['force']) && $_GET['force'] !== '0';

if (!$forceRefresh) {
    $cached = load_cached_places_summary($cacheFile);
    if ($cached !== null) {
        echo $cached;
        return;
    }
    http_response_code(404);
    echo json_encode(['error' => 'cache_missing']);
    return;
}

try {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT lat, lng, start_ts, place_name
        FROM segments
        WHERE kind = 'place'
          AND lat IS NOT NULL
          AND lng IS NOT NULL
    ");
    $places = $stmt->fetchAll();

    $resolver = new GeoResolver();
    $countries = [];
    $states = [];
    $cities = [];

    $unique = [];
    foreach ($places as $p) {
        $lat = (float)$p['lat'];
        $lng = (float)$p['lng'];
        $key = round($lat, 4) . '|' . round($lng, 4);
        if (!isset($unique[$key])) {
            $unique[$key] = [
                'lat'        => $lat,
                'lng'        => $lng,
                'dates'      => [],
                'is_home'    => false,
                'home_label' => null,
            ];
        }

        $label = isset($p['place_name']) ? trim((string)$p['place_name']) : null;
        if ($label !== '' && !$unique[$key]['home_label']) {
            $unique[$key]['home_label'] = $label;
        }

        if (is_home_label($label)) {
            $unique[$key]['is_home'] = true;
        }

        if (!empty($p['start_ts'])) {
            $date = date('Y-m-d', (int)$p['start_ts']);
            $unique[$key]['dates'][$date] = true;
        }
    }

    foreach ($unique as $entry) {
        if (empty($entry['dates'])) continue;
        $lat = $entry['lat'];
        $lng = $entry['lng'];
        $visits = collapseVisitDates(array_keys($entry['dates']));
        if (!$visits) continue;
        $isHome = !empty($entry['is_home']);

        $country = $resolver->resolveCountry($lat, $lng);
        if ($country) {
            if (!isset($countries[$country])) {
                $countries[$country] = [
                    'name'       => $country,
                    'visits'     => [],
                    'is_home'    => false,
                    'home_label' => $entry['home_label'] ?? null,
                ];
            }
            $countries[$country]['visits'] = array_merge($countries[$country]['visits'], $visits);
            if ($isHome) {
                $countries[$country]['is_home'] = true;
                if (!$countries[$country]['home_label'] && !empty($entry['home_label'])) {
                    $countries[$country]['home_label'] = $entry['home_label'];
                }
            }
        }

        if ($country === 'Brazil') {
            $state = $resolver->resolveState($lat, $lng);
            if ($state) {
                $stateKey = $state['code'] . '|' . $state['name'];
                if (!isset($states[$stateKey])) {
                    $states[$stateKey] = [
                        'name'       => $state['name'],
                        'code'       => $state['code'],
                        'ibge'       => $state['ibge'],
                        'visits'     => [],
                        'is_home'    => false,
                        'home_label' => $entry['home_label'] ?? null,
                    ];
                }
                $states[$stateKey]['visits'] = array_merge($states[$stateKey]['visits'], $visits);
                if ($isHome) {
                    $states[$stateKey]['is_home'] = true;
                    if (!$states[$stateKey]['home_label'] && !empty($entry['home_label'])) {
                        $states[$stateKey]['home_label'] = $entry['home_label'];
                    }
                }
            }

            $city = $resolver->resolveCity($lat, $lng, $state['ibge'] ?? null);
            if ($city) {
                $cityKey = $city['state'] . '|' . $city['name'];
                if (!isset($cities[$cityKey])) {
                    $cities[$cityKey] = [
                        'name'       => $city['name'],
                        'state'      => $city['state'],
                        'visits'     => [],
                        'is_home'    => false,
                        'home_label' => $entry['home_label'] ?? null,
                    ];
                }
                $cities[$cityKey]['visits'] = array_merge($cities[$cityKey]['visits'], $visits);
                if ($isHome) {
                    $cities[$cityKey]['is_home'] = true;
                    if (!$cities[$cityKey]['home_label'] && !empty($entry['home_label'])) {
                        $cities[$cityKey]['home_label'] = $entry['home_label'];
                    }
                }
            }
        }
    }

    $response = [
        'countries' => finalizeVisitList($countries, function ($info, $visits) {
            return [
                'name'       => $info['name'],
                'count'      => count($visits),
                'visits'     => formatVisits($visits),
                'is_home'    => !empty($info['is_home']),
                'home_label' => $info['home_label'] ?? null,
            ];
        }),
        'states'    => finalizeVisitList($states, function ($info, $visits) {
            return [
                'name'       => $info['name'],
                'code'       => $info['code'],
                'count'      => count($visits),
                'visits'     => formatVisits($visits),
                'is_home'    => !empty($info['is_home']),
                'home_label' => $info['home_label'] ?? null,
            ];
        }),
        'cities'    => finalizeVisitList($cities, function ($info, $visits) {
            return [
                'name'       => $info['name'],
                'state'      => $info['state'],
                'count'      => count($visits),
                'visits'     => formatVisits($visits),
                'is_home'    => !empty($info['is_home']),
                'home_label' => $info['home_label'] ?? null,
            ];
        }),
        'sources' => [
            'countries' => 'Natural Earth v5',
            'states'    => 'IBGE / Click that Hood',
            'cities'    => 'IBGE (kelvins/municipios-brasileiros)'
        ]
    ];

    $payload = json_encode($response, JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('json_encode_failed');
    }
    store_places_summary_cache($cacheDir, $cacheFile, $payload);
    echo $payload;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'internal_error', 'message' => $e->getMessage()]);
}

function finalizeVisitList(array $items, callable $formatter): array {
    if (!$items) return [];
    $result = [];
    foreach ($items as $info) {
        $visits = collapseVisitDates($info['visits'] ?? []);
        $isHome = !empty($info['is_home']);
        if (!$visits && !$isHome) continue;
        $info['is_home'] = $isHome;
        $result[] = $formatter($info, $visits);
    }
    usort($result, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    return $result;
}

function collapseVisitDates(array $dates): array {
    if (!$dates) return [];
    $normalized = [];
    foreach ($dates as $d) {
        if (!$d) continue;
        $normalized[] = date('Y-m-d', strtotime($d));
    }
    $normalized = array_unique($normalized);
    sort($normalized);

    $visits = [];
    $prevTs = null;
    $maxGap = 6 * 86400; // até 6 dias entre idas/voltas conta como mesma viagem
    foreach ($normalized as $date) {
        $ts = strtotime($date);
        if ($prevTs === null || ($ts - $prevTs) > $maxGap) {
            $visits[] = $date;
        }
        $prevTs = $ts;
    }
    return $visits;
}

function formatVisits(array $dates): array {
    $result = [];
    $labelCounts = [];
    foreach ($dates as $date) {
        $label = formatVisitLabel($date);
        $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;
        $display = $label;
        if ($labelCounts[$label] > 1) {
            $display = sprintf('%s - %s', $label, date('d/m', strtotime($date)));
        }
        $result[] = [
            'date'  => $date,
            'label' => $display
        ];
    }
    return $result;
}

function formatVisitLabel(string $date): string {
    static $months = [
        '01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr',
        '05' => 'mai', '06' => 'jun', '07' => 'jul', '08' => 'ago',
        '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'
    ];

    $parts = explode('-', $date);
    if (count($parts) === 3) {
        $month = $months[$parts[1]] ?? $parts[1];
        return sprintf('%s/%s', $month, $parts[0]);
    }
    return $date;
}

function is_home_label(?string $label): bool {
    if (!$label) {
        return false;
    }
    $normalized = strtolower($label);
    return strpos($normalized, 'casa') !== false
        || strpos($normalized, 'home') !== false
        || strpos($normalized, 'resid') !== false;
}

function load_cached_places_summary(string $cacheFile): ?string {
    if (!is_file($cacheFile)) {
        return null;
    }
    $contents = @file_get_contents($cacheFile);
    if ($contents === false || trim($contents) === '') {
        return null;
    }
    json_decode($contents);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    return $contents;
}

function store_places_summary_cache(string $cacheDir, string $cacheFile, string $payload): void {
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    @file_put_contents($cacheFile, $payload, LOCK_EX);
}

class GeoResolver {
    private $countries = [];
    private $states = [];
    private $citiesByState = [];
    private $allCities = [];

    public function __construct() {
        $this->countries = $this->loadPolygons(
            __DIR__ . '/../data/geo/countries.geojson',
            'name',
            null,
            null
        );
        $this->states = $this->loadPolygons(
            __DIR__ . '/../data/geo/brazil-states.geojson',
            'name',
            function ($props) {
                return [
                    'code' => $props['sigla'] ?? null,
                    'ibge' => isset($props['codigo_ibg']) ? (int)$props['codigo_ibg'] : null
                ];
            }
        );
        $this->loadCities(__DIR__ . '/../data/geo/brazil-cities.json');
    }

    public function resolveCountry(float $lat, float $lng): ?string {
        $candidates = [];
        foreach ($this->countries as $feature) {
            if (!$this->inBounds($lat, $lng, $feature['bbox'])) continue;
            $candidates[] = $feature;
        }
        if (!count($candidates)) return null;
        if (count($candidates) === 1) return $candidates[0]['name'];

        foreach ($candidates as $feature) {
            if ($this->containsPoint($lat, $lng, $feature['polygons'])) {
                return $feature['name'];
            }
        }

        return $this->nearestByCentroid($lat, $lng, $candidates)['name'] ?? null;
    }

    public function resolveState(float $lat, float $lng): ?array {
        $candidates = [];
        foreach ($this->states as $feature) {
            if (!$this->inBounds($lat, $lng, $feature['bbox'])) continue;
            $candidates[] = $feature;
        }
        if (!count($candidates)) return null;
        if (count($candidates) === 1) {
            return [
                'name' => $candidates[0]['name'],
                'code' => $candidates[0]['code'],
                'ibge' => $candidates[0]['ibge'],
            ];
        }

        foreach ($candidates as $feature) {
            if ($this->containsPoint($lat, $lng, $feature['polygons'])) {
                return [
                    'name' => $feature['name'],
                    'code' => $feature['code'],
                    'ibge' => $feature['ibge'],
                ];
            }
        }

        $nearest = $this->nearestByCentroid($lat, $lng, $candidates);
        if ($nearest) {
            return [
                'name' => $nearest['name'],
                'code' => $nearest['code'],
                'ibge' => $nearest['ibge'],
            ];
        }
        return null;
    }

    public function resolveCity(float $lat, float $lng, ?int $stateIbge): ?array {
        $candidates = [];
        if ($stateIbge !== null && isset($this->citiesByState[$stateIbge])) {
            $candidates = $this->citiesByState[$stateIbge];
        } else {
            $candidates = $this->allCities;
        }

        $best = null;
        $bestDist = PHP_FLOAT_MAX;
        foreach ($candidates as $city) {
            $dist = $this->haversine($lat, $lng, $city['lat'], $city['lng']);
            if ($dist < $bestDist) {
                $best = $city;
                $bestDist = $dist;
            }
        }

        if ($best && $bestDist <= 50) {
            return $best;
        }
        return null;
    }

    private function loadPolygons(string $path, string $nameKey, ?callable $metaFn = null, ?string $codeKey = null): array {
        if (!is_file($path)) {
            return [];
        }

        $json = json_decode(file_get_contents($path), true);
        if (!$json || !isset($json['features'])) {
            return [];
        }

        $features = [];
        foreach ($json['features'] as $feature) {
            if (empty($feature['geometry'])) continue;
            $props = $feature['properties'] ?? [];
            $name = $props[$nameKey] ?? null;
            if (!$name) continue;

            $meta = ['code' => $codeKey ? ($props[$codeKey] ?? null) : null];
            if ($metaFn) {
                $extra = $metaFn($props);
                if ($extra && is_array($extra)) {
                    $meta = array_merge($meta, $extra);
                }
            }

            $polygons = $this->extractPolygons($feature['geometry']);
            if (!$polygons) continue;
            $bbox = $this->computeBounds($polygons);

            $features[] = array_merge([
                'name' => $name,
                'polygons' => $polygons,
                'bbox' => $bbox,
                'centroid' => $this->computeCentroid($polygons),
            ], $meta);
        }

        return $features;
    }

    private function extractPolygons(array $geometry): array {
        $type = $geometry['type'] ?? null;
        $coords = $geometry['coordinates'] ?? [];
        $polygons = [];

        if ($type === 'Polygon') {
            $polygons[] = $this->convertRing($coords[0] ?? []);
        } elseif ($type === 'MultiPolygon') {
            foreach ($coords as $poly) {
                $polygons[] = $this->convertRing($poly[0] ?? []);
            }
        }

        return array_filter($polygons);
    }

    private function convertRing(array $ring): array {
        $converted = [];
        foreach ($ring as $pair) {
            if (!is_array($pair) || count($pair) < 2) continue;
            $converted[] = [$pair[1], $pair[0]]; // [lat, lng]
        }
        return $converted;
    }

    private function containsPoint(float $lat, float $lng, array $polygons): bool {
        foreach ($polygons as $polygon) {
            if ($this->pointInPolygon($lat, $lng, $polygon)) {
                return true;
            }
        }
        return false;
    }

    private function inBounds(float $lat, float $lng, ?array $bbox): bool {
        if (!$bbox) return true;
        return $lat >= $bbox[0] && $lat <= $bbox[1] && $lng >= $bbox[2] && $lng <= $bbox[3];
    }

    private function pointInPolygon(float $lat, float $lng, array $polygon): bool {
        $inside = false;
        $count = count($polygon);
        if ($count < 3) return false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $polygon[$i][1];
            $yi = $polygon[$i][0];
            $xj = $polygon[$j][1];
            $yj = $polygon[$j][0];

            $intersect = (($yi > $lat) !== ($yj > $lat)) &&
                ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    private function computeBounds(array $polygons): ?array {
        $minLat = null;
        $maxLat = null;
        $minLng = null;
        $maxLng = null;

        foreach ($polygons as $poly) {
            foreach ($poly as $point) {
                if ($minLat === null || $point[0] < $minLat) $minLat = $point[0];
                if ($maxLat === null || $point[0] > $maxLat) $maxLat = $point[0];
                if ($minLng === null || $point[1] < $minLng) $minLng = $point[1];
                if ($maxLng === null || $point[1] > $maxLng) $maxLng = $point[1];
            }
        }

        if ($minLat === null) return null;
        return [$minLat, $maxLat, $minLng, $maxLng];
    }

    private function loadCities(string $path): void {
        if (!is_file($path)) {
            return;
        }
        $json = json_decode(file_get_contents($path), true);
        if (!is_array($json)) return;

        foreach ($json as $entry) {
            if (!isset($entry['nome'], $entry['latitude'], $entry['longitude'], $entry['codigo_uf'])) {
                continue;
            }
            $state = (int)$entry['codigo_uf'];
            $city = [
                'name'  => $entry['nome'],
                'lat'   => (float)$entry['latitude'],
                'lng'   => (float)$entry['longitude'],
                'state' => $this->mapUfCodeToSigla($state),
                'ibge'  => (int)$entry['codigo_ibge']
            ];
            $this->citiesByState[$state][] = $city;
            $this->allCities[] = $city;
        }
    }

    private function mapUfCodeToSigla(int $code): ?string {
        static $map = [
            11 => 'RO', 12 => 'AC', 13 => 'AM', 14 => 'RR', 15 => 'PA', 16 => 'AP', 17 => 'TO',
            21 => 'MA', 22 => 'PI', 23 => 'CE', 24 => 'RN', 25 => 'PB', 26 => 'PE', 27 => 'AL',
            28 => 'SE', 29 => 'BA', 31 => 'MG', 32 => 'ES', 33 => 'RJ', 35 => 'SP',
            41 => 'PR', 42 => 'SC', 43 => 'RS', 50 => 'MS', 51 => 'MT', 52 => 'GO', 53 => 'DF'
        ];
        return $map[$code] ?? null;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth * $c;
    }

    private function computeCentroid(array $polygons): array {
        $latSum = 0;
        $lngSum = 0;
        $count = 0;
        foreach ($polygons as $poly) {
            foreach ($poly as $point) {
                $latSum += $point[0];
                $lngSum += $point[1];
                $count++;
            }
        }
        if (!$count) return [0, 0];
        return [$latSum / $count, $lngSum / $count];
    }

    private function nearestByCentroid(float $lat, float $lng, array $features): ?array {
        $best = null;
        $bestDist = PHP_FLOAT_MAX;
        foreach ($features as $feature) {
            $centroid = $feature['centroid'] ?? null;
            if (!$centroid) continue;
            $dist = $this->haversine($lat, $lng, $centroid[0], $centroid[1]);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $feature;
            }
        }
        return $best;
    }
}
