<?php
require_once __DIR__ . '/common_fs.php';
header('Content-Type: application/json; charset=utf-8');

$raw = $_GET['path'] ?? '';
$dir = safe_join_under_base($raw);
if (!$dir || !is_dir($dir)) {
  echo json_encode(['files'=>[]]);
  exit;
}

$all = array_values(array_filter(scandir($dir), function($f){
  return $f !== '.' && $f !== '..' && is_file($GLOBALS['dir'] . DIRECTORY_SEPARATOR . $f);
}));

$files = [];
foreach ($all as $name) {
  $full = $dir . DIRECTORY_SEPARATOR . $name;
  $rel  = substr($full, strlen(rtrim(realpath(SOPORTES_BASE), DIRECTORY_SEPARATOR)) + 1);
  $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $size = @filesize($full);

  // codifica rel en base64 url-safe
  $b64 = rtrim(strtr(base64_encode($rel), '+/', '-_'), '=');

  $files[] = [
    'name' => $name,
    'ext'  => $ext,
    'size' => $size ?: 0,
    'previewUrl'  => "stream.php?b64={$b64}",
    'downloadUrl' => "download.php?b64={$b64}",
    'b64'   => $b64, // identificador seguro para borrar
  ];
}

echo json_encode(['files'=>$files]);
