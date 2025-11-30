<?php
define('RASTRO_BYPASS_INSTALL_CHECK', true);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$lang = trim($_POST['lang'] ?? '');
$languages = rastro_available_languages();
if (!isset($languages[$lang])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'language_not_supported']);
    exit;
}

rastro_set_lang($lang);

echo json_encode(['ok' => true, 'lang' => $lang]);
