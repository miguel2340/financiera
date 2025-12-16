<?php
// ver_comprobante.php
declare(strict_types=1);
mb_internal_encoding('UTF-8');
header('X-Content-Type-Options: nosniff');

$BASE_DIR = 'E:/viaticos/';

/* Extensiones y MIME */
$MIME = [
  'pdf'  => 'application/pdf',
  'jpg'  => 'image/jpeg',
  'jpeg' => 'image/jpeg',
  'png'  => 'image/png',
  'webp' => 'image/webp',
  'tif'  => 'image/tiff',
  'tiff' => 'image/tiff',
  'txt'  => 'text/plain; charset=utf-8',
  'csv'  => 'text/csv; charset=utf-8',
  'xml'  => 'application/xml',
  'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'xls'  => 'application/vnd.ms-excel',
  'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'zip'  => 'application/zip',
  'eml'  => 'message/rfc822',
  'msg'  => 'application/vnd.ms-outlook',
  'mp4'  => 'video/mp4',
];
$PRIORIDAD = ['pdf','jpg','jpeg','png','webp','txt','csv','xml','tif','tiff','xlsx','xls','docx','zip','eml','msg','mp4'];
$PREVIEWABLE = ['pdf','jpg','jpeg','png','webp','txt','csv','xml','tif','tiff'];

/* comp: número/identificador */
$comp = trim($_GET['comp'] ?? '');
$comp = basename($comp); // seguridad básica
if ($comp === '' || !preg_match('/^[A-Za-z0-9._\-]+$/', $comp)) {
  http_response_code(400);
  exit('Parámetro inválido.');
}

/**
 * Busca recursivamente un archivo cuyo nombre contenga $needle (case-insensitive).
 * Prioriza por la lista de extensiones $PRIORIDAD.
 */
function buscarArchivoRecursivo(string $baseDir, string $needle, array $prioridad): ?array {
  $needle = strtolower($needle);
  $mejor = null; // ['path'=>..., 'ext'=>..., 'rank'=>int]

  // Evita seguir links y omite . y ..
  $flags = \FilesystemIterator::SKIP_DOTS;

  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, $flags),
    RecursiveIteratorIterator::SELF_FIRST,
    RecursiveIteratorIterator::CATCH_GET_CHILD
  );

  foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) continue;
    $name = $file->getFilename();
    $lower = strtolower($name);
    if (strpos($lower, $needle) === false) continue; // debe contener el número

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $rank = array_search($ext, $prioridad, true);
    if ($rank === false) $rank = PHP_INT_MAX;

    if ($mejor === null || $rank < $mejor['rank']) {
      $mejor = ['path' => $file->getPathname(), 'ext' => $ext, 'rank' => $rank];
      if ($rank === 0) break; // halló la mejor (pdf) -> salir
    }
  }
  return $mejor;
}

/* ¿Debo encontrar primero (find=1) o ya me dieron ruta exacta? */
$find = isset($_GET['find']);
$ruta = null;
$extFound = null;

if ($find) {
  $hallado = buscarArchivoRecursivo($BASE_DIR, $comp, $PRIORIDAD);
  if ($hallado) {
    $ruta = $hallado['path'];
    $extFound = $hallado['ext'];
  }
} else {
  // Modo legacy: probar comp.ext en baseDir
  foreach ($PRIORIDAD as $ext) {
    $try = $BASE_DIR . $comp . '.' . $ext;
    if (file_exists($try)) { $ruta = $try; $extFound = $ext; break; }
  }
}

if ($ruta === null) {
  http_response_code(404);
  exit('Archivo no encontrado.');
}

$mime = $MIME[$extFound] ?? 'application/octet-stream';
$filename = basename($ruta);
$inline = isset($_GET['inline']);

/* Para inline solo si el tipo es razonablemente previsualizable */
$puedeInline = in_array($extFound, $PREVIEWABLE, true);

/* Cabeceras */
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($ruta));
header('Accept-Ranges: bytes');
if ($inline && $puedeInline) {
  header('Content-Disposition: inline; filename="'.$filename.'"');
} else {
  header('Content-Disposition: attachment; filename="'.$filename.'"');
}

/* Enviar */
$fp = fopen($ruta, 'rb');
if ($fp === false) {
  http_response_code(500);
  exit('No se pudo abrir el archivo.');
}
fpassthru($fp);
fclose($fp);
