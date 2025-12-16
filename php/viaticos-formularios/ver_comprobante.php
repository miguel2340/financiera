<?php
// ver_comprobante.php
declare(strict_types=1);
mb_internal_encoding('UTF-8');
header('X-Content-Type-Options: nosniff');

if (!function_exists('buscarEnProyecto')) {
  function buscarEnProyecto(string $archivo, int $niveles = 7): string {
    $dir = __DIR__;
    for ($i=0; $i<$niveles; $i++){
      $ruta = $dir . DIRECTORY_SEPARATOR . $archivo;
      if (file_exists($ruta)) return $ruta;
      $dir = dirname($dir);
    }
    throw new RuntimeException("No se encontró {$archivo} buscando en el proyecto.");
  }
}
require_once buscarEnProyecto('config.php'); // si defines VIATICOS_DIR aquí, lo usará

$comp   = isset($_GET['comp']) ? trim((string)$_GET['comp']) : '';
$find   = isset($_GET['find']) ? (int)$_GET['find'] : 0;
$inline = isset($_GET['inline']) ? (int)$_GET['inline'] : 0;

if ($comp === '') {
  http_response_code(400);
  echo "Falta parametro comp";
  exit;
}

// Directorio base (por defecto E:\viaticos)
$baseDir = defined('VIATICOS_DIR') ? VIATICOS_DIR : (getenv('VIATICOS_DIR') ?: 'E:\\viaticos');
$baseDir = rtrim($baseDir, "\\/");

if (!is_dir($baseDir)) {
  http_response_code(500);
  echo "Directorio base no válido: {$baseDir}";
  exit;
}

$permitidas = ['pdf','jpg','jpeg','png'];

// Normaliza variantes (con y sin ceros a la izquierda)
$compDigits = preg_replace('/\D+/', '', $comp);
$noZeros    = ltrim($compDigits, '0');
$variantes  = array_values(array_unique(array_filter([$comp, $compDigits, $noZeros])));

// Función de búsqueda recursiva (primer match)
function buscarArchivo(string $root, array $variantes, array $exts): ?string {
  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
  );
  foreach ($it as $fileInfo) {
    if (!$fileInfo->isFile()) continue;
    $ext = strtolower($fileInfo->getExtension());
    if (!in_array($ext, $exts, true)) continue;

    $name = $fileInfo->getBasename('.'.$ext);

    // match si el nombre contiene cualquiera de las variantes
    foreach ($variantes as $v) {
      if ($v !== '' && stripos($name, (string)$v) !== false) {
        return $fileInfo->getPathname();
      }
    }
  }
  return null;
}

$path = $find ? buscarArchivo($baseDir, $variantes, $permitidas) : null;

// Si no se pidió find, asume ruta absoluta directa (no recomendado pero útil)
if (!$find && file_exists($comp)) {
  $path = $comp;
}

if (!$path || !is_file($path)) {
  http_response_code(404);
  echo "No se encontró un comprobante para: ".htmlspecialchars($comp, ENT_QUOTES);
  exit;
}

// Enviar el archivo
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = [
  'pdf'  => 'application/pdf',
  'jpg'  => 'image/jpeg',
  'jpeg' => 'image/jpeg',
  'png'  => 'image/png',
][$ext] ?? 'application/octet-stream';

$disposition = $inline ? 'inline' : 'attachment';
header('Content-Type: '.$mime);
header('Content-Disposition: '.$disposition.'; filename="'.basename($path).'"');
header('Content-Length: '.filesize($path));
readfile($path);
