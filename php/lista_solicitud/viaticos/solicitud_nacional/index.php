<?php 
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

//Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';

session_start();
// Verificar sesión
if (!isset($_SESSION['tipo_usuario_id']) && !isset($_SESSION['tipo_usuario_id2'])) {
    header('Location: ../../inicio_sesion.php');
    exit;
}

$usuarios_permitidos = [1, 2, 6];
if (!in_array($_SESSION['tipo_usuario_id'], $usuarios_permitidos) && 
    !in_array($_SESSION['tipo_usuario_id2'], $usuarios_permitidos)) {
    header('Location: ../../../menu.php');
    exit;
}

// Obtener usuarios
$usuarios_sql = "SELECT id, nombre FROM usuario WHERE tipo_usuario_id = 5";
$usuarios_stmt = sqlsrv_query($conn, $usuarios_sql);
$usuarios = [];
while ($row = sqlsrv_fetch_array($usuarios_stmt, SQLSRV_FETCH_ASSOC)) {
    $usuarios[] = $row;
}
sqlsrv_free_stmt($usuarios_stmt);

// === Cargar opciones de selects (una sola vez, antes del form) ===
$deps_sql = "SELECT DISTINCT descripcion_dep AS dep FROM municipio WHERE descripcion_dep IS NOT NULL ORDER BY descripcion_dep";
$deps_stmt = sqlsrv_query($conn, $deps_sql);
$departamentos = [];
while ($r = sqlsrv_fetch_array($deps_stmt, SQLSRV_FETCH_ASSOC)) { $departamentos[] = $r['dep']; }
sqlsrv_free_stmt($deps_stmt);

$muni_sql = "SELECT DISTINCT descripcion_mun AS mun FROM municipio WHERE descripcion_mun IS NOT NULL ORDER BY descripcion_mun";
$muni_stmt = sqlsrv_query($conn, $muni_sql);
$municipios = [];
while ($r = sqlsrv_fetch_array($muni_stmt, SQLSRV_FETCH_ASSOC)) { $municipios[] = $r['mun']; }
sqlsrv_free_stmt($muni_stmt);

// ==== ENDPOINTS AJAX DEPENDIENTES (devuelven JSON y terminan) ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=UTF-8');

    if ($_POST['action'] === 'deps_by_region') {
        $region = isset($_POST['region']) ? (int) $_POST['region'] : null;
        $sql = "SELECT DISTINCT descripcion_dep AS dep
                FROM municipio
                WHERE descripcion_dep IS NOT NULL"
             . ($region ? " AND TRY_CAST(region_id AS INT) = ?" : "")
             . " ORDER BY descripcion_dep";
        $params = $region ? [$region] : [];
        $stmt = sqlsrv_query($conn, $sql, $params);
        $data = [];
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $data[] = $r['dep'];
            }
            sqlsrv_free_stmt($stmt);
        }
        echo json_encode(['ok' => true, 'departamentos' => $data]);
        exit;
    }

    if ($_POST['action'] === 'mun_by_dep') {
        $dep    = isset($_POST['departamento']) ? trim($_POST['departamento']) : '';
        $region = isset($_POST['region']) ? (int) $_POST['region'] : null;

        $sql = "SELECT DISTINCT descripcion_mun AS mun
                FROM municipio
                WHERE descripcion_mun IS NOT NULL";
        $params = [];

        if ($dep !== '') {
            $sql .= " AND LTRIM(RTRIM(descripcion_dep)) = LTRIM(RTRIM(?))";
            $params[] = $dep;
        }
        if ($region) {
            $sql .= " AND TRY_CAST(region_id AS INT) = ?";
            $params[] = $region;
        }
        $sql .= " ORDER BY descripcion_mun";

        $stmt = sqlsrv_query($conn, $sql, $params);
        $data = [];
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $data[] = $r['mun'];
            }
            sqlsrv_free_stmt($stmt);
        }
        echo json_encode(['ok' => true, 'municipios' => $data]);
        exit;
    }
}

$radicado_filtro         = trim($_POST['radicado_filtro'] ?? '');
$identificacion_filtro   = trim($_POST['identificacion_filtro'] ?? '');
$region_filtro           = trim($_POST['region_filtro'] ?? '');
$departamento_filtro     = trim($_POST['departamento_filtro'] ?? '');
$municipio_filtro        = trim($_POST['municipio_filtro'] ?? '');
$usuario_filtro          = $_POST['usuario_filtro'] ?? '';


// === Paginación (500 por página) ===
$perPage = 500;
$page = isset($_POST['page']) ? (int)$_POST['page'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
if ($page < 1) { $page = 1; }
$offset = ($page - 1) * $perPage;

// === BASE FROM + WHERE (reutilizable para COUNT y DATA) ===
$baseFrom = "
FROM solicitudes s
JOIN (
    SELECT *
    FROM (
        SELECT
            e.*,
            ROW_NUMBER() OVER(
                PARTITION BY e.radicado
                ORDER BY e.fecha_estado DESC, e.id_solicitudes DESC
            ) AS rn
        FROM evento_solicitudes e
    ) t
    WHERE t.rn = 1
) e ON s.radicado = e.radicado
WHERE
    s.estado_pago = 'activado'
    AND s.proceso_tercero = 'viaticos'
    AND s.apr_departamental = 'Aprobado'
";


$where = "";
$params = [];

// Filtro por usuario
if (!empty($usuario_filtro)) {
    $where .= " AND e.id_usuario = ?";
    $params[] = $usuario_filtro;
}

// Filtro por radicado (rad_via) - parcial
if ($radicado_filtro !== '') {
    $where .= " AND CAST(s.rad_via AS VARCHAR(50)) LIKE ?";
    $params[] = "%{$radicado_filtro}%";
}

// Filtro por región (numérica)
if ($region_filtro !== '') {
    $where .= " AND TRY_CAST(s.region AS INT) = ?";
    $params[] = (int)$region_filtro;
}

// Filtro por departamento (texto exacto)
if ($departamento_filtro !== '') {
    $where .= " AND s.departamento = ?";
    $params[] = $departamento_filtro;
}

// Filtro por municipio (texto exacto)
if ($municipio_filtro !== '') {
    $where .= " AND s.municipio = ?";
    $params[] = $municipio_filtro;
}
// Filtro por identificación titular - parcial
if ($identificacion_filtro !== '') {
    $where .= " AND CAST(s.numero_identificacion_titular AS VARCHAR(50)) LIKE ?";
    $params[] = "%{$identificacion_filtro}%";
}

// === Total de filas (para saber páginas) ===
$countSql = "SELECT COUNT(*) AS total {$baseFrom} {$where}";
$countStmt = sqlsrv_query($conn, $countSql, $params);
$totalRows = 0;
if ($countStmt && ($cr = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC))) {
    $totalRows = (int)$cr['total'];
}
if ($countStmt) sqlsrv_free_stmt($countStmt);

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// === SELECT paginado ===
$sql = "
SELECT
    s.radicado,
    s.numero_identificacion_titular,
    s.numero_identificacion,
    s.url_drive,
    s.proceso,
    s.rad_via,
    s.region,
    s.municipio,
    s.departamento
{$baseFrom}
{$where}
ORDER BY TRY_CAST(s.rad_via AS INT) ASC
OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";
$paramsData = array_merge($params, [$offset, $perPage]);

$stmt = sqlsrv_query($conn, $sql, $paramsData);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación Departamental</title>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script src="script.js" defer></script>
</head>

<body>

<div class="container">
    <h1>Verificación Nacional</h1>

<form method="POST" action="" class="filters-wrapper">
  <div class="filters-grid">

    <!-- Mantener página actual como hidden -->
    <input type="hidden" name="page" id="page" value="<?= (int)$page ?>">

    <div class="filter-group">
      <label for="radicado_filtro">Radicado</label>
      <input type="text"
             class="filter-input"
             name="radicado_filtro"
             id="radicado_filtro"
             value="<?= htmlspecialchars($radicado_filtro ?? '') ?>"
             placeholder="Ej: 12345">
    </div>
    <div class="filter-group">
      <label for="identificacion_filtro">Identificación titular</label>
      <input type="text"
            class="filter-input"
            name="identificacion_filtro"
            id="identificacion_filtro"
            value="<?= htmlspecialchars($identificacion_filtro ?? '') ?>"
            placeholder="Ej: 1234567890">
    </div>

    <div class="filter-group">
      <label for="region_filtro">Región</label>
      <input type="number"
             class="filter-input"
             name="region_filtro"
             id="region_filtro"
             value="<?= htmlspecialchars($region_filtro ?? '') ?>"
             placeholder="Ej: 1">
    </div>

    <div class="filter-group">
      <label for="departamento_filtro">Departamento</label>
      <select name="departamento_filtro" id="departamento_filtro" style="width:100%;">
        <option value="">Departamento...</option>
        <?php foreach ($departamentos as $dep): ?>
          <option value="<?= htmlspecialchars($dep) ?>"
            <?= ($dep === ($departamento_filtro ?? '')) ? 'selected' : '' ?>>
            <?= htmlspecialchars($dep) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-group">
      <label for="municipio_filtro">Municipio</label>
      <select name="municipio_filtro" id="municipio_filtro" style="width:100%;">
        <option value="">Municipio...</option>
        <?php foreach ($municipios as $mun): ?>
          <option value="<?= htmlspecialchars($mun) ?>"
            <?= ($mun === ($municipio_filtro ?? '')) ? 'selected' : '' ?>>
            <?= htmlspecialchars($mun) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-actions">
      <button type="submit" class="btn btn-blue">🔍 Buscar</button>
      <a href="../../../menu.php" class="btn btn-red">⬅ Regresar</a>
    </div>

    <!-- Badges de filtros activos (visual) -->
    <div class="active-filters" id="activeFilters"></div>
  </div>
</form>

<div class="table-wrapper">
    <table>
        <thead>
        <tr>
            <th>Radicado</th>
            <th>Identificación</th>
            <th>URL Drive</th>
            <th>Proceso</th>
            <th id="th-motivo">Motivo(s)</th>
            <th>Actualizar</th>
            <th>Observación</th>
        </tr>
        </thead>
<tbody>
<?php if ($stmt !== false): ?>
  <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
    <?php
      $radicado_via = $row['rad_via'] ?? '';
      $proceso_bd   = trim((string)($row['proceso'] ?? ''));

      // si viene vacío en BD, lo tratamos como "Revision"
      $proceso_view = $proceso_bd !== '' ? $proceso_bd : 'Revision';

      // SOLO si NO hay proceso real (o está en Revision), y tiene punto -> mostrar Legalización
      if (($proceso_bd === '' || strcasecmp($proceso_bd, 'Revision') === 0) && strpos((string)$radicado_via, '.') !== false) {
          $proceso_view = 'Legalización';
      }


      $apr_dep_val = strtoupper(trim((string)($row['apr_departamental'] ?? '')));
      $es_objetado = ($apr_dep_val === 'OBJETADO');
    ?>
    <tr>
      <!-- Radicado -->
      <td><?= htmlspecialchars($radicado_via) ?></td>

      <!-- Identificación -->
      <td><?= htmlspecialchars($row['numero_identificacion_titular']) ?></td>

      <!-- Drive -->
      <td>
        <button type="button"
                class="btn btn-green ver-archivos-btn"
                data-url="<?= htmlspecialchars($row['url_drive']) ?>">
          📂 Ver Archivos
        </button>
      </td>

      <!-- Proceso (vista) -->
      <td class="celda-proceso"><?= htmlspecialchars($proceso_view) ?></td>

      <!-- Motivo(s) (tu bloque oculto) -->
      <td class="motivo-td" style="display:none;">
        <div class="motivo-field">
          <label><input type="checkbox" name="motivo[]" value="soportes"> Soportes</label>
          <label><input type="checkbox" name="motivo[]" value="tarifas"> Tarifas</label>
          <label><input type="checkbox" name="motivo[]" value="pertinencia"> Pertinencia</label>
          <label><input type="checkbox" name="motivo[]" value="requisitos administrativos"> Requisitos administrativos</label>
          <label><input type="checkbox" name="motivo[]" value="oportunidad"> Oportunidad</label>
          <label><input type="checkbox" name="motivo[]" value="solicitudes duplicadas"> Solicitudes duplicadas</label>
        </div>
      </td>

            <!-- Motivo(s) (tu bloque oculto) -->
      <td class="motivo-td2" style="display:none;">
        <div class="motivo-field">
          <label><input type="checkbox" name="motivo2[]" value="soportes">Soportes</label>
          <label><input type="checkbox" name="motivo2[]" value="requisitos administrativos">Requisitos administrativos</label>
      </td>

      <!-- ACTUALIZAR -->
      <td>
        <form class="proceso-form" data-identificacion="<?= htmlspecialchars($radicado_via) ?>">
          <select class="proceso-select">
            <option value="Revision">Revisión</option>
            <option value="Aprobado">Aprobado</option>
            <option value="Rechazado">Rechazado</option>
            <option value="Subsanacion">Subsanación</option>
          </select>

          <textarea class="observacion-field"
                    style="display:none; width:240px; height:60px; margin-top:6px;"
                    maxlength="500"
                    placeholder="Escriba la observación..."></textarea>

          <button type="button" class="update-individual btn btn-green">🔄 Actualizar</button>
        </form>
      </td>
<!-- 🔵 Observación (historial) — SIEMPRE visible -->
<td>
  <button type="button"
          class="btn btn-blue ver-observaciones-btn"
          data-radicado="<?= htmlspecialchars((string)$row['radicado']) ?>">
    📝 Observación
  </button>
</td>

    </tr>
  <?php endwhile; ?>
<?php endif; ?>
</tbody>
    </table>

    <!-- PAGINACIÓN -->
    <?php if ($totalRows > 0): ?>
      <div class="pagination">
        <button type="button" class="btn" onclick="goPage(1)" <?= $page<=1?'disabled':'' ?>>« Primera</button>
        <button type="button" class="btn" onclick="goPage(<?= $page-1 ?>)" <?= $page<=1?'disabled':'' ?>>‹ Anterior</button>

        <span class="pager-info">
          Página <?= (int)$page ?> de <?= (int)$totalPages ?> — <?= number_format($totalRows) ?> registros
        </span>

        <button type="button" class="btn" onclick="goPage(<?= $page+1 ?>)" <?= $page>=$totalPages?'disabled':'' ?>>Siguiente ›</button>
        <button type="button" class="btn" onclick="goPage(<?= $totalPages ?>)" <?= $page>=$totalPages?'disabled':'' ?>>Última »</button>
      </div>
    <?php endif; ?>

    <button id="update-massive" class="btn btn-yellow">🔄 Actualizar Todo</button>
</div>

<!-- Mensaje flotante -->
<div id="mensajeActualizacion" class="mensaje-flotante"></div>

<!-- Modal archivos -->
<div id="modalArchivos" class="modal">
    <div class="modal-contenido">
        <span class="cerrar-modal" onclick="cerrarModalArchivos()">&times;</span>
        <h3>📁 Archivos en la carpeta</h3>
        <div id="contenido-archivos">Cargando...</div>
    </div>
</div>

<!-- Modal Observaciones -->
<div id="modalObservaciones" class="modal">
  <div class="modal-contenido" style="max-width:780px;">
    <span class="cerrar-modal" onclick="cerrarModalObs()">&times;</span>
    <h3>📝 Historial de observaciones — Radicado: <span id="obsRad"></span></h3>
    <div id="contenido-observaciones">Cargando…</div>
  </div>
</div>

</body>
</html>

<script>
$(function () {
  // Inicializar Select2
  $('#departamento_filtro, #municipio_filtro').select2({
    placeholder: 'Seleccione...',
    allowClear: true,
    width: 'resolve'
  });

  // Al cambiar REGIÓN: cargar departamentos de esa región y limpiar municipio
  $('#region_filtro').on('change', function () {
    const region = $('#region_filtro').val();

    // Limpiar selects
    $('#departamento_filtro').empty()
      .append('<option value="">Departamento...</option>').trigger('change');
    $('#municipio_filtro').empty()
      .append('<option value="">Municipio...</option>').trigger('change');

    if (!region) return;

    $.post('', { action: 'deps_by_region', region: region }, function (resp) {
      if (!resp || !resp.ok) return;
      const $dep = $('#departamento_filtro');
      resp.departamentos.forEach(dep => {
        $dep.append(new Option(dep, dep));
      });
      $dep.trigger('change');
    }, 'json');
  });

  // Al cambiar DEPARTAMENTO: cargar municipios del departamento (y opcionalmente región si está)
  $('#departamento_filtro').on('change', function () {
    const dep    = $('#departamento_filtro').val();
    const region = $('#region_filtro').val();

    $('#municipio_filtro').empty()
      .append('<option value="">Municipio...</option>').trigger('change');

    if (!dep && !region) return;

    $.post('', { action: 'mun_by_dep', departamento: dep ?? '', region: region ?? '' }, function (resp) {
      if (!resp || !resp.ok) return;
      const $mun = $('#municipio_filtro');
      resp.municipios.forEach(m => {
        $mun.append(new Option(m, m));
      });
      $mun.trigger('change');
    }, 'json');
  });
});

// Paginación: mantiene filtros reenviando el mismo form
function goPage(n){
  const p = Math.max(1, parseInt(n,10)||1);
  document.getElementById('page').value = p;
  document.querySelector('form.filters-wrapper').submit();
}

// --- Modal Observaciones ---
function abrirModalObs(){ document.getElementById('modalObservaciones').style.display='block'; }
function cerrarModalObs(){ document.getElementById('modalObservaciones').style.display='none'; }

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.ver-observaciones-btn');
  if (!btn) return;

  const radicado = btn.dataset.radicado || '';
  document.getElementById('obsRad').textContent = radicado;

  const box = document.getElementById('contenido-observaciones');
  box.innerHTML = 'Cargando…';

  try {
    const OBS_ENDPOINT = '/aplicacion/php/viaticos-formularios/observaciones.php';
    const url = new URL(OBS_ENDPOINT, window.location.origin);
    url.searchParams.set('radicado', radicado);

    const resp = await fetch(url.toString(), { method: 'GET' });
    if (!resp.ok) {
      box.innerHTML = `<div class="alerta-roja">Error ${resp.status} al cargar observaciones.<br><small>${url}</small></div>`;
      abrirModalObs(); return;
    }

    const data = await resp.json();
    if (!data.ok || !Array.isArray(data.items) || data.items.length === 0) {
      box.innerHTML = '<div class="alerta">No hay observaciones para este radicado.</div>';
      abrirModalObs(); return;
    }

    const html = data.items.map(it => {
      const badge = ({
        'NACIONAL':      '<span class="badge badge-azul">Nacional </span>',
        'DEPARTAMENTAL': '<span class="badge badge-verde">Departamental</span>',
        'OBJECION':      '<span class="badge badge-roja">Objeción</span>'
      })[it.tipo] || '<span class="badge">Otro</span>';

      const fecha = it.fecha ? `<small class="muted">${it.fecha}</small>` : '';
      const texto = (it.observacion || '').replace(/\n/g,'<br>');

      return `
        <div class="card-obs">
          <div class="card-obs-head">${badge}${fecha}</div>
          <div class="card-obs-body">${texto || '<em class="muted">—</em>'}</div>
        </div>
      `;
    }).join('');

    box.innerHTML = html;
    abrirModalObs();
  } catch (err) {
    console.error(err);
    box.innerHTML = '<div class="alerta-roja">Error cargando observaciones.</div>';
    abrirModalObs();
  }
});
</script>
<script>
// --- Modal Archivos ---
function abrirModalArchivos(){ document.getElementById('modalArchivos').style.display='block'; }
function cerrarModalArchivos(){ document.getElementById('modalArchivos').style.display='none'; }

// Click en 📂 Ver Archivos
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.ver-archivos-btn');
  if (!btn) return;

  const path = (btn.dataset.url || '').trim();
  const cont = document.getElementById('contenido-archivos');

  if (!path) {
    cont.innerHTML = '<div class="alerta-roja">No hay ruta (url_drive) configurada.</div>';
    abrirModalArchivos();
    return;
  }

  // Muestra modal y estado "cargando"
  cont.innerHTML = 'Cargando...';
  abrirModalArchivos();

  try {
    // Ajusta el endpoint si tu archivo se llama distinto
    const url = new URL('listar_archivos.php', window.location.href);
    url.searchParams.set('path', path);

    const resp = await fetch(url.toString(), { method: 'GET' });
    if (!resp.ok) {
      cont.innerHTML = `<div class="alerta-roja">Error ${resp.status} al listar archivos.<br><small>${url}</small></div>`;
      return;
    }

    const data = await resp.json();  // { files: [{name, previewUrl, downloadUrl, ext}, ...] }
    if (!data.files || data.files.length === 0) {
      cont.innerHTML = '<div class="alerta">Sin archivos en la carpeta.</div>';
      return;
    }

    // Render simple con previsualización básica (img/pdf/txt)
    const html = `
      <ul class="lista-archivos">
        ${data.files.map(f => `
          <li>
            <span class="archivo"
                  style="cursor:pointer;text-decoration:underline"
                  title="Abrir vista previa en el navegador"
                  data-preview="${f.previewUrl || ''}">
              ${f.name}
            </span>
            <a href="${f.downloadUrl}" target="_blank" rel="noopener">Descargar</a>
          </li>
        `).join('')}
      </ul>
    `;
    cont.innerHTML = html;

    // Delegación para click en cada archivo -> preview
    // Delegación para click en cada archivo -> abrir en navegador
    cont.querySelectorAll('.archivo').forEach(el => {
      el.addEventListener('click', () => {
        const prev = (el.dataset.preview || '').trim();
        if (!prev) return alert('Sin vista previa.');

        // ✅ Opción A: abrir en nueva pestaña
        window.open(prev, '_blank', 'noopener');

        // ✅ Opción B (si prefieres MISMA pestaña): descomenta esta
        // window.location.href = prev;
      });
    });


  } catch (err) {
    console.error(err);
    cont.innerHTML = '<div class="alerta-roja">Error cargando la lista de archivos.</div>';
  }
});
</script>

<style>
/* badges simples */
.badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:12px; }
.badge-azul { background:#e6f0ff; color:#0b61d8; }
.badge-verde { background:#eaffea; color:#1d7a1d; }
.badge-roja { background:#ffeaea; color:#b20d0d; }
.muted { color:#777; }

/* tarjetas */
.card-obs { border:1px solid #e4e4e4; border-radius:8px; padding:8px 10px; margin-bottom:10px; background:#fff; }
.card-obs-head { display:flex; justify-content:space-between; align-items:center; }
.card-obs-body { margin-top:6px; }

/* paginación */
.pagination { display:flex; gap:8px; align-items:center; margin:12px 0; }
.pagination .btn { padding:6px 10px; border:1px solid #ddd; background:#fff; cursor:pointer; }
.pagination .btn[disabled]{ opacity:.5; cursor:not-allowed; }
.pager-info { margin:0 6px; color:#555; font-size:14px; }
</style>

<?php
if ($stmt !== false) {
    sqlsrv_free_stmt($stmt);
}
sqlsrv_close($conn);
?>
