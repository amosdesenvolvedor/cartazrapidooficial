<?php
session_start();
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$role = $_SESSION['user']['role'] ?? 'cliente';
$isSuperadmin = $role === 'superadmin';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT id, user_id, user_slug, name, image_path, created_at FROM template5_backgrounds ORDER BY created_at DESC");
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['url'] = '/' . ltrim($row['image_path'], '/');
        $row['canDelete'] = $isSuperadmin || ((int)$row['user_id'] === $userId);
    }
    echo json_encode($rows);
    exit;
}

if ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID inválido']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, user_id, image_path FROM template5_backgrounds WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $background = $stmt->fetch();
    if (!$background) {
        http_response_code(404);
        echo json_encode(['error' => 'Fundo não encontrado']);
        exit;
    }
    $canDelete = $isSuperadmin || ((int)$background['user_id'] === $userId);
    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado']);
        exit;
    }
    $fullPath = __DIR__ . '/' . ltrim($background['image_path'], '/');
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
    $pdo->prepare("DELETE FROM template5_backgrounds WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Informe o nome do fundo']);
    exit;
}

if (empty($_FILES['background']) || $_FILES['background']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['error' => 'Selecione um arquivo PNG ou JPEG válido']);
    exit;
}

$file = $_FILES['background'];
$maxSize = 4 * 1024 * 1024; // 4MB
if ($file['size'] > $maxSize) {
    http_response_code(422);
    echo json_encode(['error' => 'O arquivo deve ter até 4MB']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : $file['type'];
if ($finfo) {
    finfo_close($finfo);
}
$allowedTypes = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
if (!isset($allowedTypes[$mime])) {
    http_response_code(422);
    echo json_encode(['error' => 'Formato inválido. Use PNG ou JPEG']);
    exit;
}

 $storageDir = __DIR__ . '/public/storage/template5/fundos';
if (!is_dir($storageDir)) {
    if (!mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
        http_response_code(500);
        echo json_encode(['error' => 'Falha ao criar pasta de fundos']);
        exit;
    }
}

$ext = $allowedTypes[$mime];
$base = pathinfo($file['name'], PATHINFO_FILENAME);
$safeBase = preg_replace('/[^A-Za-z0-9_-]/', '_', $base);
if ($safeBase === '') {
    $safeBase = 'fundo';
}
try {
    $rand = bin2hex(random_bytes(5));
} catch (Exception $e) {
    $rand = substr(md5(uniqid('', true)), 0, 10);
}
$filename = time() . '_' . $rand . '_' . $safeBase . '.' . $ext;
$targetPath = $storageDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao salvar o arquivo']);
    exit;
}

$relativePath = 'public/storage/template5/fundos/' . $filename;
$slugStmt = $pdo->prepare("SELECT slug FROM users WHERE id = ? LIMIT 1");
$slugStmt->execute([$userId]);
$userSlug = $slugStmt->fetchColumn() ?: '';
$insert = $pdo->prepare("INSERT INTO template5_backgrounds (user_id, user_slug, name, image_path) VALUES (?, ?, ?, ?)");
$insert->execute([$userId, $userSlug, $name, $relativePath]);

$lastId = $pdo->lastInsertId();

http_response_code(201);
echo json_encode([
    'id' => (int)$lastId,
    'name' => $name,
    'url' => '/' . $relativePath,
    'created_at' => date('c'),
]);
