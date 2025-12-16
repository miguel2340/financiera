<?php

// Obtiene el directorio del archivo actual y Dividir la ruta en partes
$pathParts = explode('\\', dirname(__FILE__));

// Contar cuántos niveles hay hasta '\php\'
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));

// Conectar con la configuración de la conexión
require_once str_repeat('../', $levelsUp) . 'config.php';
session_start();

// Depuración: Registrar información de la sesión al cargar la página de menú
error_log("Sesión al cargar la página de menú: " . print_r($_SESSION, true));

// Asegura que la sesión esté iniciada

// Obtiene el tipo de usuario desde la sesión, si no está definido, será null
$tipo_usuario_id = $_SESSION['tipo_usuario_id'] ?? null;

// Verifica si el usuario está en la lista de permitidos (1, 2, 7)
$disabled = (!in_array($tipo_usuario_id, [1, 2, 7])) ? 'style="pointer-events: none; opacity: 0.5; cursor: not-allowed;"' : '';
$disabled2 = (!in_array($tipo_usuario_id, [1, 2, 10])) ? 'style="pointer-events: none; opacity: 0.5; cursor: not-allowed;"' : '';
?>



<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fomag Pagos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>

        .logo-container {
            width: 100vw; /* Ocupa todo el ancho de la pantalla */
            height: 100vh; /* Ocupa toda la altura de la pantalla */
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(to right, #E3F2FD, #CFEBF9);
            overflow: hidden;
            position: relative;
            z-index: 0;
        }

        /* Ajustes específicos para la imagen */
        .logo-container img {
            max-width: 80%;
            max-height: 80%;
            object-fit: contain;
            margin-top: -290px; /* Ajusta este valor según necesites */
        }



        /* Estilos del header */
        header {
            background: linear-gradient(to bottom, #364B9B, #50A5D8);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative; /* Para crear un nuevo contexto de apilamiento */
            z-index: 10; /* Coloca el header y submenús por encima */
        }

        /* Estilos del nav */
        nav {
            background: linear-gradient(to bottom, #364B9B, #50A5D8);
            text-align: center;
            padding: 12px 0;
            position: relative; /* Asegura que el nav esté sobre el logo */
            z-index: 10; /* Mayor que el logo para estar por encima */
        }

        /* Estilos del submenu */
        .submenu {
            opacity: 0; /* Oculta el submenú por defecto */
            transform: translateY(10px); /* Desplaza el submenú ligeramente */
            transition: opacity 0.3s ease, transform 0.3s ease; /* Animación suave */
            visibility: hidden; /* Oculta el submenú visualmente */
            position: absolute; /* Coloca el submenú en relación al contenedor */
            left: 0;
            z-index: 20;
        }

        /* Mostrar el submenú al hacer hover */
        .group:hover > .submenu,
        .group:focus-within > .submenu {
            opacity: 1;
            transform: translateY(0); /* Restablece la posición */
            visibility: visible; /* Muestra el submenú */
        }

        /* Efectos al pasar el mouse sobre los enlaces del submenú */
        .submenu a {
            transition: background 0.3s, transform 0.2s;
        }

        .submenu a:hover {
            background: linear-gradient(45deg, #1A3E70, #364B9B);
            color: white;
            transform: scale(1.05); /* Aumenta ligeramente el tamaño */
        }

        /* Oculta la barra de desplazamiento en todo el sitio */
body {
    overflow: hidden; /* Oculta el desplazamiento */
}

/* Para navegadores basados en WebKit (Chrome, Edge, Safari) */
::-webkit-scrollbar {
    display: none; /* Oculta la barra de desplazamiento */
}



    </style>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll(".group").forEach((menu) => {
                const submenu = menu.querySelector(".submenu");
                let hideTimeout;

                menu.addEventListener("mouseenter", () => {
                    clearTimeout(hideTimeout);
                    submenu.style.opacity = "1";
                    submenu.style.transform = "translateY(0)";
                    submenu.style.visibility = "visible";
                });

                menu.addEventListener("mouseleave", () => {
                    hideTimeout = setTimeout(() => {
                        submenu.style.opacity = "0";
                        submenu.style.transform = "translateY(10px)";
                        submenu.style.visibility = "hidden";
                    }, 400);
                });
            });
        });
    </script>
</head>
<body>
<header class="shadow-md">
    <div class="container mx-auto px-6">
        <div class="flex justify-end items-center py-2 text-sm">
            <div class="flex space-x-4">
                <!-- <a href="#" class="text-white">Ingresa a FOMAG</a>
                <a href="#" class="text-white">Ingresa</a> -->
                <a href="logout.php" class="text-white" style="float: left;">Cerrar sesión</a>



            </div>
        </div>
        <div class="flex justify-center items-center py-4">
            <img src="../logo5.png" alt="Logo" class="h-20 w-auto">
            
        </div>
        <nav class="bg-blue-700 text-white py-2">
            <ul class="flex flex-wrap justify-center items-center text-center gap-6">
                <li class="relative group">
                    <a href="#" class="px-4 py-2 block whitespace-nowrap">PROCESO DE PAGOS ▼</a>
                    <ul class="submenu bg-white text-black mt-2 py-4 w-auto min-w-[600px] rounded-lg shadow-lg flex px-6 space-x-6">
                        <li class="relative group">
                            <a href="#" class="block px-4 py-2 hover:bg-gray-200 whitespace-nowrap">Terceros ▼</a>
                            <ul class="submenu absolute bg-white text-black mt-0 py-2 w-48 rounded-lg shadow-lg">
                                <li><a href="terceros/validar_terceros/validar_terceros.php" class="block px-4 py-2 hover:bg-gray-200">Validar Terceros</a></li>
                                <li><a href="terceros/validar_terceros/modificar/modificar_ter.php" class="block px-4 py-2 hover:bg-gray-200">Modificar(id tercero)</a></li>
                                <li><a href="terceros/exportes/exportar_registro_tercero.php" class="block px-4 py-2 hover:bg-gray-200">Exportar Validaciones (Excel)</a></li>
                            </ul>
                        </li>
                        <li class="relative group">
                            <a href="#" class="block px-4 py-2 hover:bg-gray-200 whitespace-nowrap">Anexo Pago ▼</a>
                            <ul class="submenu absolute bg-white text-black mt-0 py-2 w-48 rounded-lg shadow-lg">
                                <li><a href="anexo_pago/listado_trabajo/anexo_pago.php" class="block px-4 py-2 hover:bg-gray-200">Lista De Trabajo</a></li>
                                <li><a href="anexo_pago/modificacion_anexo/anexo_pago.php" class="block px-4 py-2 hover:bg-gray-200">Modificar Anexo</a></li>
                                <li><a href="anexo_pago/exportes/exportar_anexo_pago.php" class="block px-4 py-2 hover:bg-gray-200">Exportar Anexo de Pago (Excel)</a></li>
                            </ul>
                        </li>
                        <li class="relative group">
                            <a href="#" class="block px-4 py-2 hover:bg-gray-200 whitespace-nowrap">Masivo ▼</a>
                            <ul class="submenu absolute bg-white text-black mt-0 py-2 w-48 rounded-lg shadow-lg">
                                <li><a href="inf_masivo_tapa/inf_masivo/info.php" class="block px-4 py-2 hover:bg-gray-200">Lista De Masivo</a></li>
                                <li><a href="masivo/index.php" class="block px-4 py-2 hover:bg-gray-200">Descarga Masivo</a></li>
                            </ul>
                        </li>
                        <li class="relative group">
                            <a href="#" class="block px-4 py-2 hover:bg-gray-200 whitespace-nowrap">Tapa ▼</a>
                            <ul class="submenu absolute bg-white text-black mt-0 py-2 w-48 rounded-lg shadow-lg">
                                <li><a href="inf_masivo_tapa/inf_tapa/tapa.php" class="block px-4 py-2 hover:bg-gray-200">Lista tapa</a></li>
                                <li><a href="masivo/index1.php" class="block px-4 py-2 hover:bg-gray-200">Descarga Tapa</a></li>
                            </ul>
                        </li>
                        <li class="relative group">
                            <a href="#" class="block px-4 py-2 hover:bg-gray-200 whitespace-nowrap">Reporte facturas postuladas ▼</a>
                            <ul class="submenu absolute bg-white text-black mt-0 py-2 w-48 rounded-lg shadow-lg">
                                <li><a href="pagos_exel/exel.php" class="block px-4 py-2 hover:bg-gray-200">carga de tarea pagos</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li class="relative group">
                    <a href="#" class="px-4 py-2 block whitespace-nowrap">PROCESO DE REEMBOLSO Y ANTICIPOS ▼</a>
                    <ul class="submenu bg-white text-black mt-2 py-4 w-auto min-w-[600px] rounded-lg shadow-lg flex px-6 space-x-6">
                        <li class="relative group">
                            <a href="#" class="block px-4 py-2 hover:bg-gray-200 whitespace-nowrap">VIATICOS ▼</a>
                            <ul class="submenu absolute bg-white text-black mt-0 py-2 w-48 rounded-lg shadow-lg">
                                <li><a href="viaticos/viaticos.php" class="block px-4 py-2 hover:bg-gray-200">viaticos</a></li>
								<li><a href="legalizacion/legalizacion.php" class="block px-4 py-2 hover:bg-gray-200">Legalización</a></li>
                                        <li><a href="lista_solicitud/viaticos/solicitud_nacional/index.php" class="block px-4 py-2 hover:bg-gray-200">revision Nacional</a></li>
                                        <li><a href="lista_solicitud/viaticos/solicitud_departamental/index.php" class="block px-4 py-2 hover:bg-gray-200">revision Departamental</a></li>
                                        <li><a href="viaticos-formularios/index.php" class="block px-4 py-2 hover:bg-gray-200">historico de viaticos</a></li>                                        
                            </ul>
                        </li>
                        <li class="relative group">
                            <a href="#" class="block px-4 py-2 hover:bg-gray-200 whitespace-nowrap">Reembolso de tecnologia ▼</a>
                            <!-- <ul class="submenu absolute bg-white text-black mt-0 py-2 w-48 rounded-lg shadow-lg">
                                <li><a href="listado_rembolso/reembolso/index.php" class="block px-4 py-2 hover:bg-gray-200">Lista De Reembolso</a></li>
                                <li><a href="listado_rembolso/anticipo/index.php" class="block px-4 py-2 hover:bg-gray-200">Listado De Anticipo</a></li>
                                 <li class="relative group">
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-200">Listado De Viaticos</a>
                                    <ul class="submenu absolute left-full top-0 bg-white text-black mt-0 py-2 w-56 rounded-lg shadow-lg">
                                        <li><a href="listado_rembolso/viaticos/index.php" class="block px-4 py-2 hover:bg-gray-200">Viaticos con novedad</a></li>
										<li><a href="estadistica/viaticos/index.php" class="block px-4 py-2 hover:bg-gray-200">Control de Viáticos</a></li>
                                </li>
                            </ul> -->
                        </li>
                        <li class="relative group">
                            <a href="#" class="block px-4 py-2 hover:bg-gray-300 whitespace-nowrap">Anticipos ▼</a>
                            <!-- <ul class="submenu absolute bg-white text-black mt-0 py-2 w-52 rounded-lg shadow-lg">
                                <li class="relative group">
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-200">Revisión Reembolsos</a>
                                    <ul class="submenu absolute left-full top-0 bg-white text-black mt-0 py-2 w-56 rounded-lg shadow-lg">
                                        <li><a href="lista_solicitud/Reembolso/solicitud_nacional/index.php" class="block px-4 py-2 hover:bg-gray-200">Reembolsos Nacional</a></li>
                                        <li><a href="lista_solicitud/Reembolso/solicitud_departamental/index.php" class="block px-4 py-2 hover:bg-gray-200">Reembolsos Departamental</a></li>
                                </li>
                                <li class="relative group">
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-200">Revisión Anticipos</a>
                                    <ul class="submenu absolute left-full top-0 bg-white text-black mt-0 py-2 w-56 rounded-lg shadow-lg">
                                        <li><a href="lista_solicitud/Anticipos/solicitud_nacional/index.php" class="block px-4 py-2 hover:bg-gray-200">Anticipos Nacional</a></li>
                                        <li><a href="lista_solicitud/Anticipos/solicitud_departamental/index.php" class="block px-4 py-2 hover:bg-gray-200">Anticipos Departamental</a></li>
                                    </ul>
                                </li>
                                <li class="relative group">
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-200">Revisión viaticos</a>
                                    <ul class="submenu absolute left-full top-0 bg-white text-black mt-0 py-2 w-56 rounded-lg shadow-lg">
                                        <li><a href="lista_solicitud/viaticos/solicitud_nacional/index.php" class="block px-4 py-2 hover:bg-gray-200">Viaticos Nacional</a></li>
                                        <li><a href="lista_solicitud/viaticos/solicitud_departamental/index.php" class="block px-4 py-2 hover:bg-gray-200">Viaticos Departamental</a></li>
									   <li><a href="lista_solicitud/viaticos/pagos_viaticos/ver_archivos.php" class="block px-4 py-2 hover:bg-gray-200">Pagos Viaticos</a></li>
                                    </ul>
                                </li>
                            </ul>
                        </li> -->
                        <li class="relative group">
                            <a href="#" class="block px-4 py-2 hover:bg-gray-200 whitespace-nowrap">Matrices ▼</a>
                            <ul class="submenu absolute bg-white text-black mt-0 py-2 w-48 rounded-lg shadow-lg">
                                <li><a href="matrices/menu_matrices.php" class="block px-4 py-2 hover:bg-gray-200">Reembolso</a></li>
                                <li><a href="matrices/menu_matrices_anticipo.php" class="block px-4 py-2 hover:bg-gray-200">Anticipo</a></li>
                                <li><a href="matrices/menu_matrices_viaticos.php" class="block px-4 py-2 hover:bg-gray-200">viaticos</a></li>
								<li><a href="matrices/viaticos/index.php" class="block px-4 py-2 hover:bg-gray-200">viaticos registro uncio</a></li>
								<li><a href="lista_solicitud/cargue_viaticos/upload_viaticos.php" class="block px-4 py-2 hover:bg-gray-200">cargue de pagos para viaticos</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li class="relative group">
                    <a href="#" class="px-4 py-2 block">ESTADÍSTICA ▼</a>
                    <ul class="submenu bg-white text-black mt-2 py-2 w-48 rounded-lg shadow-lg">
                        <li><a href="estadistica/estadistica.php" class="block px-4 py-2 hover:bg-gray-200">Power Bi Pagos</a></li>
                    </ul>
                </li>
                <a href="../../proyecto_mapa/public/index.html" class="px-4 py-2 block" <?= $disabled ?>>MAPA REPS</a>
				 <a href="Vacunacion_BI/estadistica.php" class="px-4 py-2 block" <?= $disabled2 ?>>VACUNACION</a>
                <li class="relative group">
                    <a href="#" class="px-4 py-2 block">MENU DE USUARIOS ▼</a>
                    <ul class="submenu bg-white text-black mt-2 py-2 w-48 rounded-lg shadow-lg">
                        <li><a href="administrar_usuarios/crear_usuarios/formulario_usuarios.php" class="block px-4 py-2 hover:bg-gray-200">Crear Usuarios</a></li>
                        <li><a href="administrar_usuarios/editar_usuarios/modificar.php" class="block px-4 py-2 hover:bg-gray-200">Editar Usuarios</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
   
</header>
</body>
</html>

<div class="logo-container">
    <img src="logo7.png" alt="Logo">
</div>

