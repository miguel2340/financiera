<?php
session_start();
if (!isset($_SESSION['tipo_usuario_id'])) {
    header('Location: ../../inicio_sesion.php');
    exit;
}

if (!isset($_SESSION['tipo_usuario_id']) || ($_SESSION['tipo_usuario_id'] != 10 && $_SESSION['tipo_usuario_id'] != 1)) {
    header('Location: ../menu.php');
    exit;
}

?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadística Vacunacion Fiebre Amarilla</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <script src="estadistica.js" defer></script>
    <style>
               body.screenshot-blocked {
            filter: blur(25px);
            background: rgba(0, 0, 0, 0.85);
            pointer-events: none;
            user-select: none;
        }
        .watermark {
            position: fixed;
            top: 20px;
            left: 20px;
            font-size: 18px;
            color: rgba(255, 0, 0, 0.6);
            font-weight: bold;
            pointer-events: none;
            z-index: 10000;
            opacity: 0.8;
        }
        .transparent-margin {
            position: fixed;
            top: -10px;
            left: -10px;
            width: calc(100% + 20px);
            height: calc(100% + 30px);
            z-index: 9999;
            pointer-events: none;
        }
        .transparent-margin-block {
            position: absolute;
            background:  rgba(255, 0, 0, 0.6);
            pointer-events: auto;
        }

        iframe {
            display: block;
            width: 100%;
            height: 100%;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
        }

        #powerbi-iframe {
            transform: scale(1);
            transform-origin: center;
        }



        
        
        
    </style>
    <script>

    let inactivityTime =30 * 60 * 1000; // 30 minutos en milisegundos
    let inactivityTimer;

    function resetTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(() => {
            window.location.href = "../logout.php"; // Redirige al menú
        }, inactivityTime);
    }




</script>


</head>
<body class="bg-gray-100 p-5">
    <div class="flex items-center justify-center mb-4">
        <h1 class="text-2xl font-bold text-blue-600 mr-4">Estadística Vacunacion Fiebre Amarilla</h1>
        <a href="../menu.php" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition">
            Atrás
        </a>

    </div>

    <div class="bg-white shadow-lg rounded-lg border border-blue-100">
        <div class="flex border-b border-blue-100">
            <button
                class="tab-button px-5 py-3 text-sm font-semibold text-blue-700 bg-white border-b-2 border-blue-600 focus:outline-none"
                data-tab-target="tab-estadisticas"
                aria-selected="true">
                Estadisticas
            </button>
            <button
                class="tab-button px-5 py-3 text-sm font-semibold text-gray-600 bg-gray-50 border-b-2 border-transparent hover:text-blue-700 focus:outline-none"
                data-tab-target="tab-actualizacion"
                aria-selected="false">
                Actualizacion de datos
            </button>
        </div>

        <div class="p-4">
            <div id="tab-estadisticas" data-tab-panel="tab-estadisticas">
                <!-- IFRAME DEL DASHBOARD DE POWER BI -->
                <div class="w-full h-screen border-2 border-blue-500 rounded-lg shadow-lg overflow-hidden flex items-center justify-center p-0 m-0">
                    <iframe 
                        id="powerbi-iframe"
                        frameborder="0" 
                        allowFullScreen="true"
                        class="w-full h-full p-0 m-0 border-none">
                    </iframe>
                </div>
            </div>

            <div id="tab-actualizacion" data-tab-panel="tab-actualizacion" class="hidden">
                <div class="w-full h-full bg-gray-50 border border-dashed border-blue-200 rounded-lg p-6 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold text-blue-700 mb-2">Actualizacion de datos</h2>
                        <p class="text-gray-700">Arrastra un archivo Excel (.xlsx o .xls) para validar. Solo se actualizaran quienes tengan fecha de departamento valida, no tengan fechas previas y coincidan en corte.</p>
                    </div>

                    <div id="upload-dropzone" class="border-2 border-dashed border-blue-400 bg-white rounded-lg p-6 text-center cursor-pointer transition hover:border-blue-600 hover:bg-blue-50">
                        <p class="text-sm text-gray-600 mb-2 font-semibold">Arrastra y suelta el archivo aqui</p>
                        <p class="text-xs text-gray-500">O haz clic para buscar en tu equipo</p>
                        <input id="file-input" type="file" accept=".xlsx,.xls" class="hidden" />
                    </div>

                    <div class="flex items-center justify-between">
                        <div id="file-feedback" class="text-sm text-gray-700">Ningun archivo seleccionado.</div>
                        <button id="upload-button" class="bg-blue-600 text-white px-4 py-2 rounded disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            Validar archivo
                        </button>
                    </div>

                    <div id="validation-summary" class="text-sm text-gray-700 bg-white border border-blue-100 rounded p-3 hidden"></div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="bg-white border border-blue-100 rounded-lg p-3">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-semibold text-blue-700">Candidatos a actualizar</h3>
                                <span class="text-xs text-gray-500" id="count-candidatos">0</span>
                            </div>
                            <div class="overflow-auto max-h-80">
                                <table class="min-w-full text-xs text-left">
                                    <thead class="bg-blue-50 text-blue-700">
                                        <tr>
                                            <th class="px-2 py-1">Tipo</th>
                                            <th class="px-2 py-1">Documento</th>
                                            <th class="px-2 py-1">FechaDepto</th>
                                        </tr>
                                    </thead>
                                    <tbody id="table-candidatos" class="divide-y divide-gray-100">
                                        <tr><td colspan="3" class="px-2 py-2 text-gray-500 text-center">Sin datos</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="bg-white border border-red-100 rounded-lg p-3">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-semibold text-red-700">Rechazados</h3>
                                <span class="text-xs text-gray-500" id="count-rechazados">0</span>
                            </div>
                            <div class="overflow-auto max-h-80">
                                <table class="min-w-full text-xs text-left">
                                    <thead class="bg-red-50 text-red-700">
                                        <tr>
                                            <th class="px-2 py-1">Documento</th>
                                            <th class="px-2 py-1">Motivo</th>
                                        </tr>
                                    </thead>
                                    <tbody id="table-rechazados" class="divide-y divide-gray-100">
                                        <tr><td colspan="2" class="px-2 py-2 text-gray-500 text-center">Sin rechazos</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-2">
                        <button id="confirm-button" class="bg-green-600 text-white px-4 py-2 rounded disabled:opacity-50 disabled:cursor-not-allowed hidden" disabled>
                            Confirmar actualizacion
                        </button>
                    </div>

                    <div class="text-xs text-gray-500">
                        Reglas: formato .xlsx/.xls, peso maximo 10 MB, debe existir match por TipoDocumento + NumeroDocumento en el corte configurado, y el registro no debe tener ya FechaAplicacionMinisterio ni FechaAplicacionDepartamento. Si la fecha de departamento viene vacia o invalida, se ignora.
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
