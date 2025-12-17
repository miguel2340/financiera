
function decodeUrl(encodedUrl) {
    try {
        return decodeURIComponent(atob(encodedUrl));
    } catch (error) {
        console.error("Error al decodificar la URL:", error);
        return null;
    }
}

function fetchPowerBIUrl() {
    fetch('get_url.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('No se pudo obtener la URL de Power BI');
            }
            return response.json();
        })
        .then(data => {
            const powerbiUrl = decodeUrl(data.url);
            if (powerbiUrl) {
                console.log("Decoded Power BI URL:", powerbiUrl);
                document.getElementById('powerbi-iframe').src = powerbiUrl;
            } else {
                console.error("No se pudo cargar la URL de Power BI.");
            }
        })
        .catch(error => console.error("Error al obtener la URL:", error));
}

let selectedFile = null;
let lastValidation = null;
let newCutFile = null;
const maxFileSize = 10 * 1024 * 1024; // 10 MB
const newCutMaxSize = 80 * 1024 * 1024; // 80 MB para ZIP/TXT
const allowedExtensions = [".xlsx", ".xls"];
const allowedMimeTypes = [
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "application/vnd.ms-excel"
];

function showUploadFeedback(message, isError = false) {
    const feedback = document.getElementById("file-feedback");
    if (!feedback) return;
    feedback.textContent = message;
    feedback.classList.toggle("text-red-600", isError);
    feedback.classList.toggle("text-gray-700", !isError);
    feedback.classList.toggle("font-semibold", !isError);
}

function validateFile(file) {
    if (!file) {
        return "Selecciona un archivo Excel valido.";
    }

    const ext = file.name ? file.name.substring(file.name.lastIndexOf(".")).toLowerCase() : "";
    const isAllowedExt = allowedExtensions.includes(ext);
    const isAllowedMime = !file.type || allowedMimeTypes.includes(file.type);

    if (!isAllowedExt && !isAllowedMime) {
        return "Formato no valido. Usa .xlsx o .xls.";
    }

    if (file.size > maxFileSize) {
        return "El archivo supera el limite de 10 MB.";
    }

    return "";
}

function handleFileSelection(file) {
    const uploadButton = document.getElementById("upload-button");
    if (!uploadButton) return;

    const validationMessage = validateFile(file);
    if (validationMessage) {
        selectedFile = null;
        uploadButton.disabled = true;
        showUploadFeedback(validationMessage, true);
        return;
    }

    selectedFile = file;
    uploadButton.disabled = false;
    const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
    showUploadFeedback(`Listo: ${file.name} (${sizeMB} MB) listo para validar.`, false);
}

function renderTableRows(tbodyId, rows, columns, emptyText) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    tbody.innerHTML = "";

    if (!rows || !rows.length) {
        const tr = document.createElement("tr");
        const td = document.createElement("td");
        td.colSpan = columns.length;
        td.className = "px-2 py-2 text-gray-500 text-center";
        td.textContent = emptyText;
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }

    rows.forEach((row) => {
        const tr = document.createElement("tr");
        columns.forEach((col) => {
            const td = document.createElement("td");
            td.className = "px-2 py-1 text-gray-700";
            td.textContent = row[col] ?? "";
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
}

function updateCounts(candidatos, rechazados) {
    const candCount = document.getElementById("count-candidatos");
    const rejCount = document.getElementById("count-rechazados");
    if (candCount) candCount.textContent = candidatos;
    if (rejCount) rejCount.textContent = rechazados;
}

function showSummary(message, isError = false) {
    const summary = document.getElementById("validation-summary");
    if (!summary) return;
    summary.classList.toggle("hidden", false);
    summary.classList.toggle("text-red-700", isError);
    summary.classList.toggle("text-green-700", !isError);
    summary.textContent = message;
}

function showNewCutFeedback(message, isError = false) {
    const feedback = document.getElementById("newcut-feedback");
    if (!feedback) return;
    feedback.textContent = message;
    feedback.classList.toggle("text-red-600", isError);
    feedback.classList.toggle("text-gray-700", !isError);
    feedback.classList.toggle("font-semibold", !isError);
}

function showNewCutSummary(message, isError = false) {
    const summary = document.getElementById("newcut-summary");
    if (!summary) return;
    summary.classList.toggle("hidden", false);
    summary.classList.toggle("text-red-700", isError);
    summary.classList.toggle("text-green-700", !isError);
    summary.textContent = message;
}

function updateNewCutCounts(nuevos, rechazados, conFecha = 0) {
    const nCount = document.getElementById("newcut-count-nuevos");
    const rCount = document.getElementById("newcut-count-rechazados");
    const cfCount = document.getElementById("newcut-count-confecha");
    if (nCount) nCount.textContent = nuevos;
    if (rCount) rCount.textContent = rechazados;
    if (cfCount) cfCount.textContent = conFecha;
}

async function sendValidation() {
    const uploadButton = document.getElementById("upload-button");
    const confirmButton = document.getElementById("confirm-button");
    if (!selectedFile || !uploadButton) {
        showUploadFeedback("Selecciona un archivo antes de validar.", true);
        return;
    }

    uploadButton.disabled = true;
    if (confirmButton) confirmButton.disabled = true;
    showUploadFeedback("Validando archivo...", false);
    showSummary("Validando, espera un momento...", false);

    const formData = new FormData();
    formData.append("file", selectedFile);

    try {
        const response = await fetch("actualizacion_validar.php", {
            method: "POST",
            body: formData
        });

        if (!response.ok) {
            const text = await response.text();
            throw new Error(text || "Error al validar.");
        }

        const data = await response.json();
        lastValidation = data;

        const candidatos = data.candidatos_preview || data.candidatos || [];
        const rechazados = data.rechazados_preview || data.rechazados || [];

        renderTableRows("table-candidatos", candidatos, ["TipoDocumento", "NumeroDocumento", "FechaAplicacionDepartamento"], "Sin datos");
        renderTableRows("table-rechazados", rechazados, ["doc", "motivo"], "Sin rechazos");
        updateCounts(data.candidatos_total || candidatos.length, data.rechazados_total || rechazados.length);

        const summaryMsg = `Validacion completa. Leidas: ${data.total_leidas ?? "-"}, candidatas: ${data.candidatos_total ?? candidatos.length}, rechazadas: ${data.rechazados_total ?? rechazados.length}. Corte objetivo: ${data.corte ?? "-"}.`;
        showSummary(summaryMsg, false);
        showUploadFeedback("Validacion finalizada.", false);

        if (confirmButton && (data.candidatos_total || candidatos.length) > 0) {
            confirmButton.disabled = false;
            confirmButton.classList.remove("hidden");
        }
    } catch (error) {
        console.error(error);
        showSummary("Error en la validacion: " + error.message, true);
        showUploadFeedback("Ocurrio un error al validar.", true);
    } finally {
        if (uploadButton) uploadButton.disabled = false;
    }
}

async function sendNewCutValidation() {
    const validateBtn = document.getElementById("newcut-validate");
    if (!newCutFile || !validateBtn) {
        showNewCutFeedback("Selecciona un archivo TXT antes de validar.", true);
        return;
    }

    validateBtn.disabled = true;
    showNewCutFeedback("Validando archivo...", false);
    showNewCutSummary("Validando nuevo corte, espera un momento...", false);

    const formData = new FormData();
    formData.append("file", newCutFile);

    try {
        const response = await fetch("nuevo_corte_validar.php", {
            method: "POST",
            body: formData
        });

        const raw = await response.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch (err) {
            throw new Error(raw || "Respuesta no valida del servidor al validar nuevo corte.");
        }

        if (!response.ok || !data || data.success === false) {
            const msg = (data && data.message) ? data.message : raw;
            throw new Error(msg || "Error al validar nuevo corte.");
        }

        const nuevos = data.nuevos_preview || [];
        const rechazados = data.rechazados_preview || [];
        renderTableRows("newcut-table-nuevos", nuevos, ["TipoDocumento", "NumeroDocumento", "FechaUltimaVacuna"], "Sin datos");
        renderTableRows("newcut-table-rechazados", rechazados, ["doc", "motivo"], "Sin datos");
        updateNewCutCounts(
            data.nuevos_total || nuevos.length,
            data.rechazados_total || rechazados.length,
            data.con_fecha_ministerio_total || 0
        );

        const msg = `Validacion nuevo corte completa. Siguiente corte: ${data.next_corte ?? "-"}, leidas: ${data.total_leidas ?? "-"}, nuevos: ${data.nuevos_total ?? nuevos.length}, sin fecha: ${data.vacios_total ?? 0}, con fecha ministerio: ${data.con_fecha_ministerio_total ?? 0}, descartados: ${data.rechazados_total ?? rechazados.length}.`;
        showNewCutSummary(msg, false);
        showNewCutFeedback("Archivo listo, revisa los nuevos vacunados.", false);
    } catch (error) {
        console.error(error);
        showNewCutSummary("Error en la validacion de nuevo corte: " + error.message, true);
        showNewCutFeedback("Ocurrio un error al validar.", true);
    } finally {
        validateBtn.disabled = false;
    }
}

async function sendConfirmation() {
    const confirmButton = document.getElementById("confirm-button");
    if (!lastValidation || !lastValidation.success || (lastValidation.candidatos_total || 0) === 0) {
        showSummary("No hay datos validados para actualizar.", true);
        return;
    }

    if (confirmButton) confirmButton.disabled = true;
    showSummary("Aplicando actualizacion...", false);

    try {
        const response = await fetch("actualizacion_confirmar.php", {
            method: "POST"
        });

        if (!response.ok) {
            const text = await response.text();
            throw new Error(text || "Error al confirmar.");
        }

        const data = await response.json();
        const msg = `Actualizacion realizada. Intentos: ${data.intentos ?? 0}, actualizados: ${data.actualizados ?? 0}, resumen afectados: ${data.resumen_actualizados ?? 0}.`;
        showSummary(msg, false);
        showUploadFeedback("Actualizacion ejecutada.", false);
        updateCounts(0, 0);
        renderTableRows("table-candidatos", [], ["TipoDocumento", "NumeroDocumento", "FechaAplicacionDepartamento"], "Sin datos");
        renderTableRows("table-rechazados", [], ["doc", "motivo"], "Sin rechazos");
        lastValidation = null;
        if (confirmButton) {
            confirmButton.disabled = true;
            confirmButton.classList.add("hidden");
        }
    } catch (error) {
        console.error(error);
        showSummary("Error al confirmar: " + error.message, true);
    } finally {
        if (confirmButton) confirmButton.disabled = true;
    }
}

function setupUploadZone() {
    const dropZone = document.getElementById("upload-dropzone");
    const fileInput = document.getElementById("file-input");
    const uploadButton = document.getElementById("upload-button");
    const confirmButton = document.getElementById("confirm-button");

    if (!dropZone || !fileInput || !uploadButton) {
        return;
    }

    const toggleHighlight = (active) => {
        dropZone.classList.toggle("border-blue-600", active);
        dropZone.classList.toggle("bg-blue-50", active);
    };

    dropZone.addEventListener("click", () => fileInput.click());

    dropZone.addEventListener("dragover", (e) => {
        e.preventDefault();
        toggleHighlight(true);
    });

    dropZone.addEventListener("dragleave", (e) => {
        e.preventDefault();
        toggleHighlight(false);
    });

    dropZone.addEventListener("drop", (e) => {
        e.preventDefault();
        toggleHighlight(false);
        const file = e.dataTransfer.files && e.dataTransfer.files[0];
        if (file) {
            handleFileSelection(file);
        }
    });

    fileInput.addEventListener("change", (e) => {
        const file = e.target.files && e.target.files[0];
        if (file) {
            handleFileSelection(file);
        }
    });

    uploadButton.addEventListener("click", (e) => {
        e.preventDefault();
        sendValidation();
    });

    if (confirmButton) {
        confirmButton.addEventListener("click", (e) => {
            e.preventDefault();
            sendConfirmation();
        });
    }
}

function setupNewCutUpload() {
    const dropZone = document.getElementById("newcut-dropzone");
    const fileInput = document.getElementById("newcut-file");
    const validateBtn = document.getElementById("newcut-validate");

    if (!dropZone || !fileInput || !validateBtn) {
        return;
    }

    const toggleHighlight = (active) => {
        dropZone.classList.toggle("border-blue-600", active);
        dropZone.classList.toggle("bg-blue-50", active);
    };

    dropZone.addEventListener("click", () => fileInput.click());

    dropZone.addEventListener("dragover", (e) => {
        e.preventDefault();
        toggleHighlight(true);
    });

    dropZone.addEventListener("dragleave", (e) => {
        e.preventDefault();
        toggleHighlight(false);
    });

    dropZone.addEventListener("drop", (e) => {
        e.preventDefault();
        toggleHighlight(false);
        const file = e.dataTransfer.files && e.dataTransfer.files[0];
        if (file) {
            if (file.size > newCutMaxSize) {
                showNewCutFeedback("El archivo supera 40 MB. Sube uno mas liviano.", true);
                return;
            }
            newCutFile = file;
            validateBtn.disabled = false;
            showNewCutFeedback(`Listo: ${file.name} listo para validar.`, false);
        }
    });

    fileInput.addEventListener("change", (e) => {
        const file = e.target.files && e.target.files[0];
        if (file) {
            if (file.size > newCutMaxSize) {
                showNewCutFeedback("El archivo supera 40 MB. Sube uno mas liviano.", true);
                return;
            }
            newCutFile = file;
            validateBtn.disabled = false;
            showNewCutFeedback(`Listo: ${file.name} listo para validar.`, false);
        }
    });

    validateBtn.addEventListener("click", (e) => {
        e.preventDefault();
        sendNewCutValidation();
    });
}

function initTabs() {
    const tabButtons = document.querySelectorAll("[data-tab-target]");
    const tabPanels = document.querySelectorAll("[data-tab-panel]");

    if (!tabButtons.length || !tabPanels.length) {
        return;
    }

    const activeClasses = ["text-blue-700", "border-blue-600", "bg-white"];
    const inactiveClasses = ["text-gray-600", "bg-gray-50", "hover:text-blue-700", "border-transparent"];

    const activateTab = (targetId) => {
        tabPanels.forEach((panel) => {
            panel.classList.toggle("hidden", panel.dataset.tabPanel !== targetId);
        });

        tabButtons.forEach((btn) => {
            const isActive = btn.dataset.tabTarget === targetId;
            activeClasses.forEach((cls) => btn.classList.toggle(cls, isActive));
            inactiveClasses.forEach((cls) => btn.classList.toggle(cls, !isActive));
            btn.setAttribute("aria-selected", isActive ? "true" : "false");
        });
    };

    tabButtons.forEach((btn) => {
        btn.addEventListener("click", () => activateTab(btn.dataset.tabTarget));
    });

    activateTab(tabButtons[0].dataset.tabTarget);
}

window.onload = function() {
    fetchPowerBIUrl();
    initTabs();
    setupUploadZone();
    setupNewCutUpload();

    // Crear margenes solo si no existen
    if (!document.querySelector(".transparent-margin")) {
        let marginOverlay = document.createElement("div");
        marginOverlay.className = "transparent-margin";

        let leftMargin = document.createElement("div");
        leftMargin.className = "transparent-margin-block left-margin";

        let rightMargin = document.createElement("div");
        rightMargin.className = "transparent-margin-block right-margin";

        let topMargin = document.createElement("div");
        topMargin.className = "transparent-margin-block top-margin";

        let bottomMargin = document.createElement("div");
        bottomMargin.className = "transparent-margin-block bottom-margin";

        marginOverlay.appendChild(leftMargin);
        marginOverlay.appendChild(rightMargin);
        marginOverlay.appendChild(topMargin);
        marginOverlay.appendChild(bottomMargin);
        document.body.appendChild(marginOverlay);
    }

    //bloquearEventos();
    //detectarDevTools();
};
