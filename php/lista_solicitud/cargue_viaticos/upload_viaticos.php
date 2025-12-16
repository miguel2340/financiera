<?php
/**
 * upload_viaticos.php
 * 1) Cargar Excel -> SQL Server (TRUNCATE + INSERT)
 * 2) Subir comprobantes (PDF/JPG/PNG) a E:\viaticos
 */

declare(strict_types=1);
mb_internal_encoding('UTF-8');
header('X-Content-Type-Options: nosniff');

$RESULT = null;        // Resultado de carga Excel
$ERRORS = [];          // Errores de carga Excel
$UP_MSG = null;        // Mensaje de subida de comprobantes
$UP_ERR = [];          // Errores de subida de comprobantes

/* ---------- Localizador genérico hacia arriba ---------- */
function buscarEnProyecto(string $archivo, int $niveles = 7): string {
    $dir = __DIR__;
    for ($i = 0; $i < $niveles; $i++) {
        $ruta = $dir . DIRECTORY_SEPARATOR . $archivo;
        if (file_exists($ruta)) return $ruta;
        $dir = dirname($dir);
    }
    throw new RuntimeException("No se encontró {$archivo} buscando en el proyecto.");
}

require_once buscarEnProyecto('vendor/autoload.php');
require_once buscarEnProyecto('config.php'); // debe definir $conn (sqlsrv)

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

/* ------------------- Config ------------------- */
$tableName = 'pagos_viaticos';   // Tabla con columnas: id, radicado, cedula, valor, fecha, comprobante

// Mapeo de encabezados normalizados del Excel -> columnas en BD
$headerMap = [
  'radicado'     => 'radicado',
  'cedula'       => 'cedula',       // CÉDULA
  'valor'        => 'valor',
  'fecha'        => 'fecha',
  'comprobante'  => 'comprobante',
];

/* ------------------- Helpers ------------------- */
function quitar_tildes(string $s): string {
  if (class_exists('\Normalizer')) {
    $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
    $s = preg_replace('/\p{Mn}+/u', '', $s);
  } else {
    $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
  }
  return $s;
}
function norm_header(string $h): string {
  $h = trim($h);
  $h = quitar_tildes($h);
  $h = mb_strtolower($h);
  $h = preg_replace('/[^a-z0-9_ ]+/u', ' ', $h);
  $h = preg_replace('/\s+/', '_', $h);
  return $h;
}
function limpiar_doc($v): string {
  $s = trim((string)$v);
  $s = preg_replace('/\D+/', '', $s);
  return $s;
}
function to_float_default($v, float $default=0.0): float {
  if ($v === null) return $default;
  if (is_numeric($v)) return (float)$v;

  $s = trim((string)$v);
  if ($s === '') return $default;
  $s = str_replace(['$', ' ', "\xC2\xA0"], '', $s); // moneda/espacios
  $s = str_replace(',', '', $s);                    // miles
  return is_numeric($s) ? (float)$s : $default;
}
function to_date_or_null($v) {
  if ($v === null || $v === '') return null;
  if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
  if (is_numeric($v)) {
    try { return XlsDate::excelToDateTimeObject((float)$v)->format('Y-m-d'); }
    catch (\Throwable $e) {}
  }
  $ts = strtotime((string)$v);
  return $ts ? date('Y-m-d', $ts) : null;
}

/* ------------------- Controladores ------------------- */

// Carpeta temp para Excel
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

$accion = $_POST['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'cargar_excel') {
  // ============ 1) Carga de Excel a SQL Server ============
  if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $ERRORS[] = 'No se recibió el archivo o hubo un error en la subida.';
  } else {
    $f = $_FILES['archivo'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $permitidas = ['xlsx','xls'];
    if (!in_array($ext, $permitidas, true)) {
      $ERRORS[] = "Extensión no permitida: .$ext (solo .xlsx/.xls).";
    } else {
      $dest = $uploadDir . '/' . uniqid('viaticos_', true) . '.' . $ext;
      if (!move_uploaded_file($f['tmp_name'], $dest)) {
        $ERRORS[] = 'No se pudo mover el archivo subido.';
      } else {
        try {
          $spreadsheet = IOFactory::load($dest);
          $sheet = $spreadsheet->getActiveSheet();
          $rows = $sheet->toArray(null, true, true, true);

          if (!$rows || count($rows) < 2) {
            $ERRORS[] = 'El archivo parece estar vacío o sin datos.';
          } else {
            // map headers
            $firstRow = array_shift($rows);
            $indexByKey = [];
            foreach ($firstRow as $colLetter => $header) {
              $norm = norm_header((string)$header);
              if ($norm !== '' && isset($headerMap[$norm])) {
                $indexByKey[$headerMap[$norm]] = $colLetter;
              }
            }

            // Diagnóstico de valores forzados
            $diag = ['valor_forzado_cero'=>0, 'fecha_null'=>0, 'cedula_vacia'=>0];

            if (!sqlsrv_begin_transaction($conn)) {
              $ERRORS[] = 'No se pudo iniciar la transacción.';
            } else {
              $ok = true;
              $total=0; $insertados=0;

              $stmt = sqlsrv_query($conn, "TRUNCATE TABLE {$tableName}");
              if ($stmt === false) {
                $ok = false;
                $ERRORS[] = 'Error al TRUNCATE: ' . print_r(sqlsrv_errors(), true);
              } else {
                $sqlInsert = "INSERT INTO {$tableName} (radicado, cedula, valor, fecha, comprobante)
                              VALUES (?, ?, ?, ?, ?)";
                $stmtIns = sqlsrv_prepare($conn, $sqlInsert, [
                  &$p_radicado, &$p_cedula, &$p_valor, &$p_fecha, &$p_comprobante
                ]);
                if ($stmtIns === false) {
                  $ok = false;
                  $ERRORS[] = 'Error preparando INSERT: ' . print_r(sqlsrv_errors(), true);
                } else {
                  foreach ($rows as $r) {
                    $total++;

                    $p_radicado   = trim((string)($indexByKey['radicado']    ? ($r[$indexByKey['radicado']]    ?? '') : ''));
                    $p_cedula     = limpiar_doc($indexByKey['cedula']        ? ($r[$indexByKey['cedula']]      ?? '') : '');
                    if ($p_cedula === '') $diag['cedula_vacia']++;

                    $p_valor      = to_float_default($indexByKey['valor']    ? ($r[$indexByKey['valor']]       ?? null) : null, 0.0);
                    if ($p_valor === 0.0) $diag['valor_forzado_cero']++;

                    $p_fecha      = to_date_or_null($indexByKey['fecha']     ? ($r[$indexByKey['fecha']]       ?? null) : null);
                    if ($p_fecha === null) $diag['fecha_null']++;

                    $p_comprobante= trim((string)($indexByKey['comprobante'] ? ($r[$indexByKey['comprobante']]  ?? '') : ''));

                    // Insertar SIEMPRE (no descartamos filas)
                    $okRow = sqlsrv_execute($stmtIns);
                    if ($okRow !== false) $insertados++;
                  }
                }
              }

              if ($ok && sqlsrv_commit($conn)) {
                $RESULT = [
                  'total'      => $total,
                  'insertados' => $insertados,
                  'tabla'      => $tableName,
                  'diag'       => $diag
                ];
              } else {
                sqlsrv_rollback($conn);
                if ($ok) $ERRORS[] = 'No se pudo confirmar la transacción.';
              }
            }
          }
        } catch (Throwable $e) {
          $ERRORS[] = 'Error leyendo el Excel: ' . $e->getMessage();
        }
        @unlink($dest);
      }
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'subir_comprobantes') {
  // ============ 2) Subida de comprobantes (sin radicado) ============
  try {
    // Ruta ABSOLUTA (puedes definir VIATICOS_DIR en config.php)
    $destDir = defined('VIATICOS_DIR') ? VIATICOS_DIR : (getenv('VIATICOS_DIR') ?: 'E:\\viaticos');

    if (!is_dir($destDir)) {
      if (!@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
        throw new RuntimeException("No se pudo crear la carpeta destino: $destDir");
      }
    }

    $permitidas = ['pdf','jpg','jpeg','png'];

    if (!isset($_FILES['comprobantes'])) {
      throw new RuntimeException('No se recibió ningún archivo.');
    }

    $files = $_FILES['comprobantes'];
    $total = is_array($files['name']) ? count($files['name']) : 0;
    $ok    = 0;

    for ($i=0; $i<$total; $i++) {
      if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $UP_ERR[] = "Archivo #".($i+1).": error de subida (código {$files['error'][$i]}).";
        continue;
      }

      $name = $files['name'][$i];
      $tmp  = $files['tmp_name'][$i];
      $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext, $permitidas, true)) {
        $UP_ERR[] = "Archivo '".htmlspecialchars($name, ENT_QUOTES)."' ignorado (extensión no permitida).";
        continue;
      }

      // Nombre saneado (sin prefijos de radicado)
      $base = pathinfo($name, PATHINFO_FILENAME);
      $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);
      $base = trim($base, '_');

      $final = $base . '.' . $ext;

      // Evita sobrescribir: agrega timestamp si existe
      $dest = rtrim($destDir, "\\/") . DIRECTORY_SEPARATOR . $final;
      if (file_exists($dest)) {
        $final = $base . '_' . date('Ymd_His') . '.' . $ext;
        $dest  = rtrim($destDir, "\\/") . DIRECTORY_SEPARATOR . $final;
      }

      if (!move_uploaded_file($tmp, $dest)) {
        $UP_ERR[] = "No se pudo mover el archivo '".htmlspecialchars($name, ENT_QUOTES)."' a destino.";
        continue;
      }
      $ok++;
    }

    $UP_MSG = "Se cargaron {$ok} de {$total} archivo(s) en: {$destDir}";
  } catch (Throwable $e) {
    $UP_ERR[] = $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Cargar viáticos / Subir comprobantes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 24px; background:#f7fafc; }
    .card { max-width: 900px; margin: 0 auto 18px; border: 1px solid #e5e7eb; border-radius: 14px; padding: 20px; background:#fff; box-shadow: 0 3px 14px rgba(0,0,0,.05); }
    h1 { font-size: 22px; margin: 0 0 12px; color:#0f62fe; }
    h2 { font-size: 18px; margin: 0 0 10px; color:#0f62fe; }
    .muted { color: #666; font-size: 14px; }
    .row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .row > * { flex: 1; min-width: 220px; }
    input[type="file"], input[type="text"] { padding: 8px 10px; border:1px solid #d1d5db; border-radius:10px; height:38px; }
    button { padding: 8px 12px; border: 0; border-radius: 10px; cursor: pointer; background: #0f62fe; color: #fff; font-weight: 600; }
    button:disabled { opacity: .6; cursor: not-allowed; }
    .ok { background: #e8f5e9; border: 1px solid #c8e6c9; padding: 12px; border-radius: 10px; margin-top: 16px; }
    .err { background: #ffebee; border: 1px solid #ffcdd2; padding: 12px; border-radius: 10px; margin-top: 16px; white-space: pre-wrap; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { text-align: left; padding: 8px; border-bottom: 1px solid #eee; }
    .hint { font-size: 12px; color: #555; margin-top: 6px; }
    code { background: #f6f8fa; padding: 2px 6px; border-radius: 6px; }
  </style>
</head>
<body>

  <!-- 1) Cargar Excel -->
  <div class="card">
    <h1>Cargar archivo de Viáticos</h1>
    <p class="muted">
      Sube un Excel (.xlsx / .xls). Esta acción <strong>reemplaza</strong> por completo los datos actuales de
      <code><?=htmlspecialchars($tableName)?></code>.
    </p>

    <form method="post" enctype="multipart/form-data" onsubmit="btnCargar.disabled=true">
      <input type="hidden" name="accion" value="cargar_excel">
      <div class="row">
        <input type="file" name="archivo" accept=".xlsx,.xls" required />
        <button name="btnCargar" type="submit">Cargar y reemplazar</button>
      </div>
      <div class="hint">
        Cabeceras aceptadas: <code>RADICADO</code>, <code>CÉDULA</code>, <code>VALOR</code>, <code>FECHA</code>, <code>COMPROBANTE</code>.
      </div>
    </form>

    <?php if ($RESULT): ?>
      <div class="ok">
        <strong>¡Carga completada!</strong>
        <table>
          <tr><th>Tabla</th><td><?=htmlspecialchars($RESULT['tabla'])?></td></tr>
          <tr><th>Filas leídas</th><td><?=number_format($RESULT['total'])?></td></tr>
          <tr><th>Insertadas</th><td><?=number_format($RESULT['insertados'])?></td></tr>
          <tr><th>Forzadas</th>
            <td>
              Valor=0: <?= (int)$RESULT['diag']['valor_forzado_cero'] ?> |
              Fecha NULL: <?= (int)$RESULT['diag']['fecha_null'] ?> |
              Cédula vacía: <?= (int)$RESULT['diag']['cedula_vacia'] ?>
            </td>
          </tr>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($ERRORS): ?>
      <div class="err">
        <strong>Errores:</strong>
        <ul>
          <?php foreach ($ERRORS as $e): ?>
            <li><?=htmlspecialchars(is_string($e) ? $e : json_encode($e, JSON_UNESCAPED_UNICODE))?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>

  <!-- 2) Subir comprobantes -->
  <div class="card">
    <h2>Subir comprobantes</h2>
    <p class="muted">Adjunta comprobantes en formato <strong>PDF/JPG/PNG</strong>. Se guardarán en <code>E:\viaticos</code>.</p>

    <?php if (!empty($UP_MSG)): ?>
      <div class="ok"><?= htmlspecialchars($UP_MSG) ?></div>
    <?php endif; ?>
    <?php if (!empty($UP_ERR)): ?>
      <div class="err">
        <strong>Errores:</strong>
        <ul>
          <?php foreach ($UP_ERR as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="row">
      <input type="hidden" name="accion" value="subir_comprobantes">
      <input type="file" name="comprobantes[]" multiple accept=".pdf,.jpg,.jpeg,.png" required>
      <button type="submit">Subir</button>
    </form>
  </div>

</body>
</html>
