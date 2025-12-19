<?php
// export_solicitudes.php
date_default_timezone_set('America/Bogota');
/* ========= Ejecutar sin límite de tiempo ni memoria (bajo tu responsabilidad) ========= */
@ini_set('max_execution_time', '0'); // ilimitado
@set_time_limit(0);                  // ilimitado
@ini_set('memory_limit', '-1');      // sin límite (ajusta si prefieres un tope)
@ini_set('output_buffering', '0');   // desactivar buffering
@ini_set('zlib.output_compression', '0');
ignore_user_abort(true);     
/* ========= Incluir config.php (subiendo hasta 8 niveles) ========= */
$dir = __DIR__;
$configPath = null;
for ($i = 0; $i < 8; $i++) {
  $try = $dir . DIRECTORY_SEPARATOR . 'config.php';
  if (file_exists($try)) { $configPath = $try; break; }
  $dir = dirname($dir);
}
if (!$configPath) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=UTF-8');
  echo "No se encontró config.php desde " . __DIR__;
  exit;
}
require_once $configPath;

// Conexión esperada en $conn desde config.php
if (!isset($conn) || $conn === false) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=UTF-8');
  echo "La conexión \$conn no está disponible después de incluir config.php\n";
  if (function_exists('sqlsrv_errors')) print_r(sqlsrv_errors());
  exit;
}

/* ========= Generar CSV al presionar el botón ========= */
if (isset($_GET['csv'])) {
  $sql = <<<SQL
WITH e_last AS (         -- último evento por radicado
  SELECT es.radicado, MAX(es.id_solicitudes) AS id_ultimo
  FROM dbo.evento_solicitudes es
  GROUP BY es.radicado
),
afiliado_1 AS (          -- desduplicar afiliado
  SELECT a.*,
         ROW_NUMBER() OVER (
           PARTITION BY a.numero_documento
           ORDER BY a.numero_documento
         ) AS rn
  FROM dbo.afiliado a
)
SELECT 
    s.rad_via,
    e.id_solicitudes AS id_evento_ultimo,
    e.fecha_solicitud,
    a.tipo_documento,
    a.numero_documento,
    CONCAT(s.nombre,' ', s.segundo_n, ' ', s.primer_p, ' ', s.segundo_p) AS nombre_completo,
    a.celular_principal,
    a.correo_principal,
    m.descripcion_dep,
    m.descripcion_mun,
    a.direccion_Residencia_cargue,
    e.estado_proceso,
    e.observacion,
    e.fecha_estado,
    s.val_rembolso,
    s.numero_identificacion,
    b.descripcion AS banco,
    t.descripcion AS tipo_cuenta,
    s.numero_cuenta,
    CONCAT(s.nombre,' ', s.segundo_n, ' ', s.primer_p, ' ', s.segundo_p) AS titular_cuenta,
	pv.comprobante,
	pv.fecha
FROM dbo.solicitudes s
JOIN e_last el ON el.radicado = s.radicado
JOIN dbo.evento_solicitudes e 
  ON e.radicado = el.radicado AND e.id_solicitudes = el.id_ultimo
JOIN afiliado_1 a 
  ON a.numero_documento = s.numero_identificacion_titular AND a.rn = 1
LEFT JOIN dbo.municipio m ON a.codigo_dane_municipio_atencion = m.id
LEFT JOIN dbo.banco b     ON s.entidad_bancaria = b.id
LEFT JOIN dbo.tipo_cuenta t ON s.tipo_cuenta = t.id
LEFT JOIN dbo.pagos_viaticos pv ON s.rad_via=pv.radicado
ORDER BY TRY_CAST(s.rad_via AS INT);
SQL;

  $stmt = sqlsrv_query($conn, $sql);
  if ($stmt === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Error al ejecutar la consulta:\n";
    print_r(sqlsrv_errors());
    exit;
  }

  $filename = 'reporte_solicitudes_' . date('Ymd_His') . '.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  header('Pragma: no-cache');
  header('Expires: 0');

  $out = fopen('php://output', 'w');
  // BOM para Excel
  fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));

  // Cabeceras dinámicas
  $meta = sqlsrv_field_metadata($stmt);
  $headers = [];
  $i = 1;
  foreach ($meta as $m) {
    $name = $m['Name'] ?? '';
    if ($name === null || $name === '' || stripos($name, 'No column name') !== false) {
      $name = 'col_'.$i;
    }
    $headers[] = $name;
    $i++;
  }
  fputcsv($out, $headers, ';');

  // Filas
  while (($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) !== null) {
    foreach ($row as $k => $v) {
      if ($v instanceof DateTime)       $row[$k] = $v->format('Y-m-d H:i:s');
      elseif (is_bool($v))              $row[$k] = $v ? '1' : '0';
      elseif ($v === null)              $row[$k] = '';
    }
    fputcsv($out, $row, ';');
  }

  fclose($out);
  sqlsrv_free_stmt($stmt);
  exit;
}

/* ========= URL del menú (ajusta a tu ruta real) ========= */
$menuUrl = 'index.php'; // Ej.: 'menu.php' o url('menu.php') si tienes helper url()
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Descarga de Matrices</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --bg:#f3f5f9;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;
      --primary:#3b82f6;   /* azul */
      --primary-hover:#2563eb;
      --secondary:#6b7280; /* gris */
      --secondary-hover:#4b5563;
      --shadow:0 10px 20px rgba(2,6,23,.06), 0 2px 6px rgba(2,6,23,.08);
    }
    *{box-sizing:border-box}
    body{
      margin:0; font-family:Inter,ui-sans-serif,system-ui,Segoe UI,Roboto,Arial;
      background:var(--bg); color:var(--text);
      min-height:100dvh; display:grid; place-items:center;
    }
    .card{
      width:100%; max-width:540px; background:var(--card);
      border-radius:12px; box-shadow:var(--shadow);
      padding:32px 28px; text-align:center;
    }
    h1{
      margin:0 0 22px; font-size:28px; font-weight:800; color:#2563eb;
    }
    .actions{display:grid; gap:14px; margin-top:6px}
    .btn{
      display:inline-block; width:100%; border:0; border-radius:10px;
      padding:12px 18px; font-size:16px; font-weight:600; cursor:pointer;
      text-decoration:none; color:#fff; box-shadow:0 3px 0 rgba(0,0,0,.15);
      transition:transform .03s ease, background-color .15s ease;
    }
    .btn:active{ transform:translateY(1px); box-shadow:0 2px 0 rgba(0,0,0,.15);}
    .btn-primary{ background:var(--primary); }
    .btn-primary:hover{ background:var(--primary-hover); }
    .btn-secondary{ background:var(--secondary); }
    .btn-secondary:hover{ background:var(--secondary-hover); }
  </style>
</head>
<body>
  <main class="card" role="region" aria-labelledby="titulo">
    <h1 id="titulo">Descarga de Matrices</h1>
    <div class="actions">
      <form action="" method="get">
        <input type="hidden" name="csv" value="1">
        <button type="submit" class="btn btn-primary" aria-label="Descargar Reporte">
          Descargar Reporte
        </button>
      </form>

      <a class="btn btn-secondary" href="<?php echo htmlspecialchars($menuUrl); ?>">
        Regresar al Menú
      </a>
    </div>
  </main>
</body>
</html>
