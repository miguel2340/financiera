<?php
session_start();
if (!isset($_SESSION['tipo_usuario_id'])) {
    header('Location: ../../inicio_sesion.php');
    exit;
}

$usuarios_permitidos = [1, 2, 7,9];
if (!in_array($_SESSION['tipo_usuario_id'], $usuarios_permitidos) && 
    !in_array($_SESSION['tipo_usuario_id2'], $usuarios_permitidos)) {
    header('Location: ../../../menu.php');
    exit;
}

?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadística Power BI</title>
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

    // Detectar eventos de actividad del usuario
    document.addEventListener("mousemove", resetTimer);
    document.addEventListener("keypress", resetTimer);
    document.addEventListener("click", resetTimer);
    document.addEventListener("scroll", resetTimer);
    document.addEventListener("keydown", resetTimer);

    // Iniciar el temporizador
    resetTimer();

    function desenfocarPantalla() {
        document.body.classList.add("screenshot-blocked");
    }

    document.addEventListener("keyup", function (e) {
        if (e.key === "PrintScreen") {
            navigator.clipboard.writeText("Captura bloqueada");
            desenfocarPantalla();
        }
    });

    document.addEventListener("keydown", function (e) {
        const key = e.key.toLowerCase();

        if (e.ctrlKey && key === "p") {
            e.preventDefault();
            desenfocarPantalla();
        }
        if ((e.metaKey || e.ctrlKey) && e.shiftKey && key === "s") {
            e.preventDefault();
            desenfocarPantalla();
        }
        if (e.metaKey && key === "s") {
            e.preventDefault();
            desenfocarPantalla();
        }
        if (e.metaKey && e.shiftKey) {
            e.preventDefault();
            desenfocarPantalla();
        }
        if (["f12", "f11", "f10"].includes(key)) {
            e.preventDefault();
            desenfocarPantalla();
        }
        if (e.metaKey && e.altKey && key === "r") {
            e.preventDefault();
            desenfocarPantalla();
        }
    });

    window.onbeforeprint = function () {
        desenfocarPantalla();
        setTimeout(() => {
            alert("La impresión está bloqueada en este sistema.");
        }, 100);
    };

    document.addEventListener("contextmenu", function (e) {
        e.preventDefault();
    });


    (function detectDevTools() {
    let devtoolsOpen = false;
    const threshold = 1.3; // Aumenta el umbral para evitar falsos positivos
    setInterval(function () {
        let widthRatio = window.outerWidth / window.innerWidth;
        let heightRatio = window.outerHeight / window.innerHeight;

        if (widthRatio > threshold || heightRatio > threshold) {
            if (!devtoolsOpen) {
                devtoolsOpen = true;
                window.location.href = "../menu.php";
            }
        } else {
            devtoolsOpen = false;
        }
    }, 500);
})();



    // Bloquear intentos de abrir la consola
    console.log("%c¡No intentes inspeccionar el código!", "color: red; font-size: 20px; font-weight: bold;");
    setInterval(function () {
        let before = console.log;
        console.log = function () {
            desenfocarPantalla();
            alert("Se ha detectado acceso a la consola.");
            setTimeout(() => {
                window.close();
                window.location.href = "../menu.php";
            }, 1000);
            before.apply(console, arguments);
        };
    }, 2000);
</script>


</head>
<body class="bg-gray-100 p-5">
    <div class="flex items-center justify-center mb-4">
        <h1 class="text-2xl font-bold text-blue-600 mr-4">Reporte de Power BI</h1>
        <a href="../menu.php" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition">
            Atrás
        </a>
    </div>

    <!-- IFRAME DEL DASHBOARD DE POWER BI -->
    <div class="w-full h-screen border-2 border-blue-500 rounded-lg shadow-lg overflow-hidden flex items-center justify-center p-0 m-0">
    <iframe 
        id="powerbi-iframe"
        frameborder="0" 
        allowFullScreen="true"
        class="w-full h-full p-0 m-0 border-none">
    </iframe>
</div>

    
</body>
</html>
