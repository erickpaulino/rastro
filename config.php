<?php
session_start();

// config.php
$DB_HOST = 'localhost';
$DB_NAME = 'u726209715_rastrotimeline';
$DB_USER = 'u726209715_rastrotimeline';
$DB_PASS = '|W>oEP+|uwZ4';

function db() {
    static $pdo;
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    if (!$pdo) {
        $pdo = new PDO(
            "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
            $DB_USER,
            $DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

// --- Autenticação simples do Rastro ---

// Troque o usuário/senha aqui
$RASTRO_USERS = [
    'erick' => '123mudar',   // coloque uma senha forte
];

function rastro_is_logged_in() {
    return isset($_SESSION['rastro_user']);
}

function require_login_html() {
    if (!rastro_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_login_api() {
    if (!rastro_is_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'nao_autenticado']);
        exit;
    }
}

