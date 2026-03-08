<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$logDir = sys_get_temp_dir() . '/cartaz_mercadopago';
$logFile = $logDir . '/mercadopago.log';
if (!is_readable($logFile)) {
    echo json_encode(['success' => false, 'error' => 'Log ainda não disponível']);
    exit;
}
$lines = [];
$handle = fopen($logFile, 'r');
if ($handle !== false) {
    while (($line = fgets($handle)) !== false) {
        $lines[] = $line;
    }
    fclose($handle);
}
echo json_encode(['success' => true, 'log' => array_slice($lines, -20)]);
