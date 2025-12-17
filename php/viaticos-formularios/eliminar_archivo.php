<?php
require_once __DIR__ . '/common_fs.php';
header('Content-Type: application/json; charset=utf-8');

$b64 = $_POST['b64'] ?? '';
if ($b64 === '') {
  echo json_encode(['ok' => false, 'msg' => 'Archivo no especificado.']);
  exit;
}

$rel = base64_decode(strtr($b64, '-_', '+/'));
$full = safe_join_under_base($rel);

if (!$full || !is_file($full)) {
  echo json_encode(['ok' => false, 'msg' => 'No se encontró el archivo.']);
  exit;
}

if (!@unlink($full)) {
  echo json_encode(['ok' => false, 'msg' => 'No se pudo eliminar el archivo.']);
  exit;
}

echo json_encode(['ok' => true, 'msg' => 'Archivo eliminado.']);
