<?php
require __DIR__ . '/config.php';

unset($_SESSION['rastro_user']);
session_destroy();

header('Location: login.php');
exit;
