<?php
require_once __DIR__ . '/common_fs.php';
header('Content-Type: application/json; charset=utf-8');

// Ruta relativa o absoluta dentro de la base
$rawPath = $_POST['path'] ?? '';
$targetDir = safe_join_under_base($rawPath);

if (!$targetDir) {
  echo json_encode(['ok' => false, 'msg' => 'Ruta no permitida o fuera de la base configurada.']);
  exit;
}

if (!is_dir($targetDir)) {
  if (!@mkdir($targetDir, 0775, true)) {
    echo json_encode(['ok' => false, 'msg' => 'No se pudo crear la carpeta destino.']);
    exit;
  }
}

if (empty($_FILES['files']) || !is_array($_FILES['files']['name'])) {
  echo json_encode(['ok' => false, 'msg' => 'No se recibieron archivos.']);
  exit;
}

$saved = [];
$errors = [];
$count = count($_FILES['files']['name']);

for ($i = 0; $i < $count; $i++) {
  $err = $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
  $tmp = $_FILES['files']['tmp_name'][$i] ?? '';
  $name = $_FILES['files']['name'][$i] ?? 'archivo';

  if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
    $errors[] = $name;
    continue;
  }

  $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
  $dest = $targetDir . DIRECTORY_SEPARATOR . $safeName;

  // Evita sobrescribir: añade sufijo incremental
  $k = 1;
  $pi = pathinfo($safeName);
  while (file_exists($dest)) {
    $candidate = ($pi['filename'] ?? 'archivo') . "_$k";
    if (!empty($pi['extension'])) {
      $candidate .= '.' . $pi['extension'];
    }
    $dest = $targetDir . DIRECTORY_SEPARATOR . $candidate;
    $k++;
  }

  if (@move_uploaded_file($tmp, $dest)) {
    $saved[] = basename($dest);
  } else {
    $errors[] = $name;
  }
}

if (!$saved) {
  echo json_encode(['ok' => false, 'msg' => 'No se pudo guardar ninguno de los archivos.']);
  exit;
}

$msg = 'Archivos subidos correctamente.';
if ($errors) {
  $msg .= ' Algunos no se subieron: ' . implode(', ', $errors);
}

echo json_encode([
  'ok' => true,
  'msg' => $msg,
  'saved' => $saved,
  'errors' => $errors
]);

