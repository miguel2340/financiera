// 🔧 Endpoint dinámico: arma la URL según dónde esté cargada la página
const AJAX_ENDPOINT = new URL('ajax_actualizar_proceso.php', window.location.href).toString();

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.proceso-form').forEach(form => {
    const row         = form.closest('tr');
    const select      = form.querySelector('.proceso-select');
    const observacion = form.querySelector('.observacion-field');
    const motivoTd    = row.querySelector('.motivo-td');
    const btnUpdate   = form.querySelector('.update-individual');

    const toggleCampos = () => {
      const v = (select?.value || '').trim();
      const showObs    = (v === 'Aprobado' || v === 'Rechazado');
      const showMotivo = (v === 'Rechazado');

      if (observacion) {
        observacion.style.display = showObs ? 'block' : 'none';
        if (!showObs) observacion.value = '';
      }
      if (motivoTd) {
        motivoTd.style.display = showMotivo ? '' : 'none';
        if (!showMotivo) {
          motivoTd.querySelectorAll('input[name="motivo[]"]').forEach(chk => chk.checked = false);
        }
      }

      // No permitir guardar en Revisión
      btnUpdate.disabled = (v === 'Revision');

      refreshMotivoHeader();
    };

    select?.addEventListener('change', toggleCampos);
    toggleCampos();

    // Guardar individual
    btnUpdate?.addEventListener('click', (e) => {
      e.preventDefault();

      // Evitar doble clic / doble envío
      if (btnUpdate.dataset.loading === '1') return;

      const v = (select?.value || '').trim();

      if (v === 'Revision') {
        mostrarMensaje('ℹ Selecciona Aprobado o Rechazado para actualizar.', false);
        return;
      }

      const payload = buildPayloadFromRow(form, row);

      // Si requiere observación y no hay -> mostrar y enfocar (evita el “2do clic”)
      if ((v === 'Aprobado' || v === 'Rechazado') && !payload.observacion) {
        if (observacion) {
          observacion.style.display = 'block';
          observacion.focus();
        }
        mostrarMensaje('✖ La observación es obligatoria para Aprobado o Rechazado.', false);
        return;
      }

      if (v === 'Rechazado' && !payload.motivo) {
        // mostrar motivos
        if (motivoTd) motivoTd.style.display = '';
        mostrarMensaje('✖ Debes seleccionar al menos un motivo para rechazar.', false);
        return;
      }

      enviarActualizacion(payload, row);
    });
  });

  // Guardado masivo (NO se detiene en la primera fila inválida)
  document.getElementById('update-massive')?.addEventListener('click', () => {
    let errores = 0;
    let enviados = 0;

    document.querySelectorAll('.proceso-form').forEach(form => {
      const row    = form.closest('tr');
      const select = form.querySelector('.proceso-select');
      const v      = (select?.value || '').trim();

      if (!row) return;

      if (v === 'Revision') {
        errores++;
        // NO return global; solo esta fila
        return;
      }

      const payload = buildPayloadFromRow(form, row);

      if ((v === 'Aprobado' || v === 'Rechazado') && !payload.observacion) {
        errores++;
        return;
      }

      if (v === 'Rechazado' && !payload.motivo) {
        errores++;
        return;
      }

      enviados++;
      enviarActualizacion(payload, row);
    });

    if (errores > 0) {
      mostrarMensaje(`⚠ Masivo: ${enviados} enviados, ${errores} con errores (revisa observación/motivos).`, false);
    } else {
      mostrarMensaje(`✅ Masivo: ${enviados} enviados.`, true);
    }
  });
});

function buildPayloadFromRow(form, row) {
  const radicado_via = form.getAttribute('data-identificacion') || '';
  const estado       = (form.querySelector('.proceso-select')?.value || '').trim();
  const observacion  = (form.querySelector('.observacion-field')?.value || '').trim();
  const motivoTd     = row.querySelector('.motivo-td');

  const motivos = motivoTd
    ? Array.from(motivoTd.querySelectorAll('input[name="motivo[]"]:checked')).map(i => i.value)
    : [];

  // Si no hay motivos, mandar vacío (no " , ")
  const motivo = motivos.length ? motivos.join(', ') : '';

  return { radicado_via, estado, observacion, motivo };
}

function refreshMotivoHeader() {
  const th = document.getElementById('th-motivo');
  if (!th) return;

  const anyRejected = Array.from(document.querySelectorAll('.proceso-select'))
    .some(sel => (sel.value || '').trim() === 'Rechazado');

  th.style.display = anyRejected ? '' : 'none';
  if (!anyRejected) {
    document.querySelectorAll('td.motivo-td').forEach(td => td.style.display = 'none');
  }
}

function enviarActualizacion(payload, row) {
  const btn = row.querySelector('.update-individual');
  const sel = row.querySelector('.proceso-select');

  if (!btn) return;

  // Evitar doble envío
  if (btn.dataset.loading === '1') return;
  btn.dataset.loading = '1';

  btn.disabled = true;
  const oldText = btn.textContent;
  btn.textContent = 'Guardando...';

  console.log('POST =>', AJAX_ENDPOINT, payload);

  fetch(AJAX_ENDPOINT, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin', // ✅ IMPORTANTÍSIMO para que viaje la sesión
    body: JSON.stringify(payload)
  })
  .then(async r => {
    const text = await r.text().catch(() => '');
    let resp;
    try { resp = JSON.parse(text || '{}'); }
    catch { resp = { status: 'error', message: `Respuesta no válida (${r.status})`, raw: text }; }
    resp._httpStatus = r.status;
    return resp;
  })
  .then(resp => {
    const ok  = (typeof resp.ok === 'boolean') ? resp.ok : (resp.status === 'success');
    const msg = resp.message || resp.msg || (ok ? 'Actualizado' : `Error HTTP ${resp._httpStatus || ''}`);

    mostrarMensaje(ok ? `✔ ${msg}` : `✖ ${msg}`, ok);

    if (ok) {
      const celdaProceso = row.querySelector('.celda-proceso');
      const visible = resp.proceso_visible || payload.estado || '';
      if (celdaProceso) celdaProceso.textContent = visible;

      marcarFilaGuardada(row);

      btn.textContent = '✓ Guardado';
      setTimeout(() => { btn.textContent = oldText; }, 1200);
    } else {
      btn.textContent = oldText;
    }

    refreshMotivoHeader();
  })
  .catch(err => {
    console.error('Error de red/fetch:', err);
    mostrarMensaje('✖ Error de red', false);
    btn.textContent = oldText;
  })
  .finally(() => {
    btn.dataset.loading = '0';
    btn.disabled = ((sel?.value || '').trim() === 'Revision');
  });
}

function marcarFilaGuardada(row) {
  row.classList.add('row-saved');
  setTimeout(() => row.classList.remove('row-saved'), 900);
}

function mostrarMensaje(texto, ok) {
  const div = document.getElementById('mensajeActualizacion');
  if (!div) { alert(texto); return; }
  div.textContent = texto;
  div.style.background = ok ? '#18a558' : '#d64545';
  div.style.display = 'block';
  setTimeout(() => { div.style.display = 'none'; }, 3000);
}

  
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

    // Si región vacía, no llamamos; quedará lista vacía (o podrías recargar todos los deptos)
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

    // Limpiar municipios
    $('#municipio_filtro').empty()
      .append('<option value="">Municipio...</option>').trigger('change');

    if (!dep && !region) return; // nada que cargar

    $.post('', { action: 'mun_by_dep', departamento: dep ?? '', region: region ?? '' }, function (resp) {
      if (!resp || !resp.ok) return;
      const $mun = $('#municipio_filtro');
      resp.municipios.forEach(m => {
        $mun.append(new Option(m, m));
      });
      $mun.trigger('change');
    }, 'json');
  });

  // (Opcional) autosubmit al seleccionar municipio o presionar Enter en inputs:
  // $('#municipio_filtro').on('change', function(){ $(this).closest('form')[0].submit(); });
  // $('#radicado_filtro, #region_filtro').on('keypress', e => { if (e.which===13) $(e.target).closest('form')[0].submit(); });
});

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
    // ✅ usa ruta ABSOLUTA al endpoint (no depende de la carpeta actual)
    const OBS_ENDPOINT = '/aplicacion/php/viaticos-formularios/observaciones.php';
    const url = new URL(OBS_ENDPOINT, window.location.origin);
    url.searchParams.set('radicado', radicado);

    console.log('GET:', url.toString()); // para depurar

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
        'NACIONAL':      '<span class="badge badge-azul">Nacional (Rechazo)</span>',
        'DEPARTAMENTAL': '<span class="badge badge-verde">Departamental (Rechazo)</span>',
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
