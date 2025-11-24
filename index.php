<?php
require __DIR__ . '/config.php';
require_login_html();
?>

<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Rastro Timeline</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="assets/style.css">
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
        <div class="app-title">Rastro Timeline</div>
        <div class="app-sub">Visualização do histórico do Google Maps</div>

        <div class="panel-header-toggles mt-2 text-xs text-slate-600">
          <label class="inline-flex items-center gap-1 cursor-pointer select-none">
            <input type="checkbox" id="toggle-rawsignals" class="h-3 w-3">
            <span>Mostrar sinais brutos (rawSignals)</span>
          </label>
        </div>
      </div>

      <div class="panel-header-actions">
        <button id="open-import" class="btn">Importar JSON</button>
        <a href="logout.php" class="logout-link text-xs text-slate-500">Sair</a>
      </div>
    </div>

    <div class="panel-controls">
      <button id="prev-day" class="btn-sm" type="button">◀</button>
      <input type="date" id="day-picker">
      <button id="next-day" class="btn-sm" type="button">▶</button>
    </div>

    <div id="summary" class="summary"></div>
    <div id="segments-list" class="segments-list"></div>
  </div>

  <!-- Modal leve de importação -->
  <div id="import-modal" class="modal hidden">
    <div class="modal-content">
      <h2>Importar histórico do Google</h2>
      <p>
        Selecione um arquivo JSON exportado pelo Google Takeout
        (Location History / Semantic Location History).
      </p>
      <input type="file" id="file-input" accept="application/json">
      <div id="import-status" class="import-status"></div>
      <div class="modal-actions">
        <button id="start-import" class="btn">Começar importação</button>
        <button id="close-import" class="btn-secondary">Fechar</button>
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
