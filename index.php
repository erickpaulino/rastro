<?php
require __DIR__ . '/config.php';
require_login_html();
$i18nJson = json_encode(
    rastro_client_i18n_data(),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
?>

<!doctype html>
<html lang="<?= htmlspecialchars(rastro_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(rastro_t('app.title'), ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="alternate icon" href="assets/favicon.svg">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="assets/style.css">
  <script>
    window.RASTRO_I18N = <?= $i18nJson ?>;
  </script>
  <script src="assets/i18n.js?v=1"></script>
</head>
<body>
  <div id="map"></div>

  <!-- Painel flutuante -->
  <div id="timeline-panel">
    <div class="panel-drag-handle" id="panel-drag-handle">
      <span class="handle-bar"></span>
    </div>
    <div class="panel-header">
      <div class="panel-header-main">
        <div class="app-title"><?= htmlspecialchars(rastro_t('app.title'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="app-sub"><?= htmlspecialchars(rastro_t('app.subtitle'), ENT_QUOTES, 'UTF-8') ?></div>

        <div class="panel-header-toggles mt-2 text-xs text-slate-600">
          <label class="inline-flex items-center gap-1 cursor-pointer select-none">
            <input type="checkbox" id="toggle-rawsignals" class="h-3 w-3">
            <span><?= htmlspecialchars(rastro_t('toggle.rawsignals'), ENT_QUOTES, 'UTF-8') ?></span>
          </label>
        </div>
      </div>

      <div class="panel-header-actions">
        <button id="toggle-places" class="btn" type="button"><?= htmlspecialchars(rastro_t('action.toggle_places'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="open-import" class="link-btn"><?= htmlspecialchars(rastro_t('action.import_json'), ENT_QUOTES, 'UTF-8') ?></button>
        <a href="logout.php" class="logout-link text-xs text-slate-500"><?= htmlspecialchars(rastro_t('action.logout'), ENT_QUOTES, 'UTF-8') ?></a>
      </div>
    </div>

    <div class="panel-controls">
      <button id="prev-day" class="btn-sm" type="button">◀</button>
      <input type="date" id="day-picker">
      <button id="next-day" class="btn-sm" type="button">▶</button>
    </div>

    <div id="summary" class="summary"></div>
    <div id="places-summary" class="places-summary hidden">
      <div class="places-summary-header">
        <div>
          <div class="places-summary-title"><?= htmlspecialchars(rastro_t('places.summary.title'), ENT_QUOTES, 'UTF-8') ?></div>
          <div class="places-summary-note"><?= htmlspecialchars(rastro_t('places.summary.note'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <button id="refresh-places" class="btn-sm" type="button"><?= htmlspecialchars(rastro_t('places.button.refresh'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
      <div class="places-columns">
        <div>
          <h5><?= htmlspecialchars(rastro_t('places.column.countries'), ENT_QUOTES, 'UTF-8') ?></h5>
          <ul id="places-countries"></ul>
        </div>
        <div>
          <h5><?= htmlspecialchars(rastro_t('places.column.states'), ENT_QUOTES, 'UTF-8') ?></h5>
          <ul id="places-states"></ul>
        </div>
        <div>
          <h5><?= htmlspecialchars(rastro_t('places.column.cities'), ENT_QUOTES, 'UTF-8') ?></h5>
          <ul id="places-cities"></ul>
        </div>
      </div>
    </div>
    <div id="segments-list" class="segments-list"></div>
  </div>

  <!-- Modal leve de importação -->
  <div id="import-modal" class="modal hidden">
    <div class="modal-content">
      <h2><?= htmlspecialchars(rastro_t('import.modal.title'), ENT_QUOTES, 'UTF-8') ?></h2>
      <p><?= htmlspecialchars(rastro_t('import.modal.description'), ENT_QUOTES, 'UTF-8') ?></p>
      <input type="file" id="file-input" accept="application/json">
      <div id="import-status" class="import-status"></div>
      <div class="modal-actions">
        <button id="start-import" class="btn"><?= htmlspecialchars(rastro_t('import.modal.start'), ENT_QUOTES, 'UTF-8') ?></button>
        <button id="close-import" class="btn-secondary"><?= htmlspecialchars(rastro_t('import.modal.close'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="assets/app.js?v=2"></script>
  <script src="assets/import.js?v=2"></script>

  <script>
    // Controla abrir/fechar o modal de import
    document.addEventListener('DOMContentLoaded', function () {
      const openBtn  = document.getElementById('open-import');
      const closeBtn = document.getElementById('close-import');
      const modal    = document.getElementById('import-modal');

      if (openBtn && modal) {
        openBtn.addEventListener('click', function () {
          modal.classList.remove('hidden');
        });
      }
      if (closeBtn && modal) {
        closeBtn.addEventListener('click', function () {
          modal.classList.add('hidden');
        });
      }
    });
  </script>
</body>
</html>
