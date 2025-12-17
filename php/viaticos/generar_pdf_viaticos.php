<?php

// Ruta al archivo de configuración
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
require_once str_repeat('../', $levelsUp) . 'config.php';

require_once __DIR__ . '/../../vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// Forzar la codificación UTF-8
session_start();
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding("UTF-8");
mb_http_output("UTF-8");

function limpiar_array($arr) {
    foreach ($arr as $key => $val) {
        if (is_array($val)) {
            $arr[$key] = limpiar_array($val);
        } else {
            $arr[$key] = htmlspecialchars(trim((string)$val));
        }
    }
    return $arr;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['datos_json'])) {
    $datos_json = $_POST['datos_json'];
    $datos = json_decode($datos_json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        die('Error al decodificar JSON: ' . json_last_error_msg());
    }

    $datos = limpiar_array($datos);

    // Asegurar que todas las variables necesarias existen
    $variables_necesarias = [
        'regional', 'departamento', 'ciudad', 'fecha_solicitud', 'nombre', 't_identificacion', 'c', 'fe_na', 'edad',
        'fisica', 'visual', 'auditiva', 'intelectual', 'psicosocial', 'sordoceguera', 'multiple', 'no',
        'Ciudad_r', 'Dirección', 'Barrio', 'correo', 'telefono_Celular', 'Telefono_Fijo',
        'fecha_m', 'tiempo_m', 'Departamento_a', 'Municipio_a', 'IPS_a',
        'fecha_m1', 'tiempo_m1', 'Departamento_a1', 'Municipio_a1', 'IPS_a1',
        'fecha_m2', 'tiempo_m2', 'Departamento_a2', 'Municipio_a2', 'IPS_a2',
        'fecha_m3', 'tiempo_m3', 'Departamento_a3', 'Municipio_a3', 'IPS_a3',
        'fecha_m4', 'tiempo_m4', 'Departamento_a4', 'Municipio_a4', 'IPS_a4',
        'Servicio1', 'Departamento_at1', 'Municipio_at1', 'cantidad1', 'valor_unitario1',
        'Servicio2', 'Departamento_at2', 'Municipio_at2', 'cantidad2', 'valor_unitario2',
        'Servicio3', 'Departamento_at3', 'Municipio_at3', 'cantidad3', 'valor_unitario3',
        'Servicio4', 'Departamento_at4', 'Municipio_at4', 'cantidad4', 'valor_unitario4',
        'Servicio5', 'Departamento_at5', 'Municipio_at5', 'cantidad5', 'valor_unitario5',
        'Servicio6', 'Departamento_at6', 'Municipio_at6', 'cantidad6', 'valor_unitario6',
        'val_rembolso','N°', 'motivo_des', 'tutela', 'Nº', 'nombre_acom', 'Parentesco', 'tipo_doc',
        'numero_idn', 'fec_na', 'tel_acom', 'nombre_repre', 'segundo_n', 'primer_p', 'segundo_p',
        'Parentesco_pago', 'id_titular', 't_cuenta', 'bancos', 'n_cuenta','nom_entidad' ,'cargo', 'fecha_na',
        'departamento_res', 'regional_res', 'Activo', 'Protección_Laboral', 'Suspendido', 'Retirado',
        'Oportunidad', 'Completitud', 'Observaciones', 'cop_identificacion', 'cert_bancaria', 'orden_medica',
        'doc_id_titular', 'soporte_pro', 'fallo_tutela', 'cop_documental', 'aut_tuto', 'facturas_apro',
        'FOMAG', 'Intermunicipal', 'Fluvial', 'Aéreo', 'Otros', 'can', 'Hospedaje', 'Criterio_medico',
        'Criterio_administrativo', 'can1', 'Acompañante', 'men_18', 'may_65', 'Discapacidad', 'can2',
        'Otro_opc', 'otro_text', 'can3', 'pertinencia'
    ];

    foreach ($variables_necesarias as $var) {
        if (!isset($datos[$var])) {
            $$var = '';
        } else {
            $$var = $datos[$var];
            // Si es array tipo cantidad, toma solo el primero si se espera string/valor simple
            if (is_array($$var) && count($$var) > 0) {
                $$var = $$var[0];
            }
        }
    }

    // Obtener la descripción del banco si existe t_banco
    $t_banco_id = isset($datos['t_banco']) ? (int)$datos['t_banco'] : 0;
    $t_banco_descripcion = '';

    if ($t_banco_id > 0) {
        $sql = "SELECT descripcion FROM banco WHERE id = ?";
        $params = [$t_banco_id];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $t_banco_descripcion = $row['descripcion'];
        } else {
            $t_banco_descripcion = 'Banco no encontrado.';
        }

        sqlsrv_free_stmt($stmt);
    }



    // Crear una nueva instancia de FPDI
    $pdf = new FPDI();

    // Ruta del archivo PDF plantilla
    $plantilla = 'viaticos.pdf';

    // Cargar la plantilla PDF
    $pageCount = $pdf->setSourceFile($plantilla); // 🔥 CORREGIDO: Se obtiene el número total de páginas

    // Recorrer todas las páginas del PDF plantilla
    for ($i = 1; $i <= $pageCount; $i++) {
        $pdf->AddPage(); // Agregar una nueva página en el PDF generado
        $template = $pdf->importPage($i); // Importar la página correctamente
        $pdf->useTemplate($template, 0, 0, 210, 297); // Ajustar al tamaño A4
        $pdf->SetAutoPageBreak(false); // Desactiva el salto automático

        // Establecer la fuente (usa una fuente con soporte UTF-8 si es necesario)
        $pdf->SetFont('Arial', '', 7);
        
        // Posicionar y escribir el texto en la página
        if ($i == 1) { // Solo en la primera página
            $pdf->SetXY(38, 34); 
            $pdf->Write(0, $regional);
        }

        if ($i == 1) { // Solo en la primera página
            $pdf->SetXY(81.5, 34); 
            $pdf->Write(0, $departamento);
        }

        if ($i == 1) { // Solo en la primera página
            $pdf->SetXY(123, 32); 
            $pdf->MultiCell(160, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $ciudad), 0, 'L', false);
        }

        if ($i == 1) { 
            $pdf->SetFont('Arial', '', 9);// Solo en la primera página
            $pdf->SetXY(155.5, 35.9); 
            $pdf->Write(0, $fecha_solicitud);
        }
        
        $pdf->SetFont('Arial', '', 7);
        
        if ($i == 1) { // Solo en la primera página
            $pdf->SetXY(61, 56); 
            $pdf->Write(0, $nombre);
        }

        if ($i == 1) { // Solo en la primera página
            $pdf->SetXY(61, 62); 
            $pdf->Write(0, $t_identificacion.'-'.$c);
        }

        if ($i == 1) { // Solo en la primera página
            $pdf->SetXY(121.5, 62); 
            $pdf->Write(0, $fe_na);
        }

        if ($i == 1) { // Solo en la primera página
            $pdf->SetXY(174, 62); 
            $pdf->Write(0, $edad);
        }

        if ($i == 1) { //
            $pdf->SetXY(40, 67.3); 
            $pdf->Write(0, $fisica);
        }

        if ($i == 1) { //
            $pdf->SetXY(50.2, 67.3); 
            $pdf->Write(0, $visual);
        }

        if ($i == 1) { //
            $pdf->SetXY(61.8,67.3); 
            $pdf->Write(0, $auditiva);
        }

        if ($i == 1) { //
            $pdf->SetXY(75.5, 67.2); 
            $pdf->Write(0, $intelectual);
        }

        if ($i == 1) { //
            $pdf->SetXY(91.4, 67.3); 
            $pdf->Write(0, $psicosocial);
        }

        if ($i == 1) { //
            $pdf->SetXY(109.5,67.5); 
            $pdf->Write(0, $sordoceguera);
        }

        if ($i == 1) { //
            $pdf->SetXY(120.7, 67.5); 
            $pdf->Write(0, $multiple);
        }

        if ($i == 1) { //
            $pdf->SetXY(130.4, 67.3); 
            $pdf->Write(0, $no);
        }

        if ($i == 1) { //
            $pdf->SetXY(60, 71.8); 
            $pdf->MultiCell(160, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $ciudad), 0, 'L', false);
        }

        if ($i == 1) { //
            $pdf->SetXY(99, 73.2); 
            $pdf->Write(0, $Dirección);
        }

        if ($i == 1) { //
            $pdf->SetXY(173, 73.2); 
            $pdf->Write(0, $Barrio);
        }

        if ($i == 1) { //
            $pdf->SetXY(59.1, 80); 
            $pdf->Write(0, $correo);
        }

        if ($i == 1) { //
            $pdf->SetXY(125, 80); 
            $pdf->Write(0, $telefono_Celular);
        }

        if ($i == 1) { //
            $pdf->SetXY(173, 80); 
            $pdf->Write(0, $Telefono_Fijo);
        }

        if ($i == 1) { //
            $pdf->SetXY(15, 110.2); 
            $pdf->Write(0, $fecha_m);
        }

        if ($i == 1) { //
            $pdf->SetXY(40, 110.2); 
            $pdf->Write(0, $tiempo_m);
        }

        if ($i == 1) { //
            $pdf->SetXY(69, 110.2); 
            $pdf->Write(0, $Departamento_a);
        }

        if ($i == 1) { //
            $pdf->SetXY(111, 110.2); 
            $pdf->Write(0, $Municipio_a);
        }

        if ($i == 1) { //
            $pdf->SetXY(144, 110.2); 
            $pdf->Write(0, $IPS_a);
        }

        if ($i == 1) { //
            $pdf->SetXY(15, 116.5); 
            $pdf->Write(0, $fecha_m1);
        }

        if ($i == 1) { //
            $pdf->SetXY(40, 116.5); 
            $pdf->Write(0, $tiempo_m1);
        }

        if ($i == 1) { //
            $pdf->SetXY(69, 116.5); 
            $pdf->Write(0, $Departamento_a1);
        }

        if ($i == 1) { //
            $pdf->SetXY(111, 116.5); 
            $pdf->Write(0, $Municipio_a1);
        }

        if ($i == 1) { //
            $pdf->SetXY(144, 116.5); 
            $pdf->Write(0, $IPS_a1);
        }

        
        if ($i == 1) { //
            $pdf->SetXY(15, 123); 
            $pdf->Write(0, $fecha_m2);
        }

        if ($i == 1) { //
            $pdf->SetXY(40, 123); 
            $pdf->Write(0, $tiempo_m2);
        }

        if ($i == 1) { //
            $pdf->SetXY(69, 123); 
            $pdf->Write(0, $Departamento_a2);
        }

        if ($i == 1) { //
            $pdf->SetXY(111, 123); 
            $pdf->Write(0, $Municipio_a2);
        }

        if ($i == 1) { //
            $pdf->SetXY(144,123); 
            $pdf->Write(0, $IPS_a2);
        }

        
        if ($i == 1) { //
            $pdf->SetXY(15, 129.7); 
            $pdf->Write(0, $fecha_m3);
        }

        if ($i == 1) { //
            $pdf->SetXY(40, 129.7); 
            $pdf->Write(0, $tiempo_m3);
        }

        if ($i == 1) { //
            $pdf->SetXY(69, 129.7); 
            $pdf->Write(0, $Departamento_a3);
        }

        if ($i == 1) { //
            $pdf->SetXY(111, 129.7); 
            $pdf->Write(0, $Municipio_a3);
        }

        if ($i == 1) { //
            $pdf->SetXY(144, 129.7); 
            $pdf->Write(0, $IPS_a3);
        }

        
        if ($i == 1) { //
            $pdf->SetXY(15, 136); 
            $pdf->Write(0, $fecha_m4);
        }

        if ($i == 1) { //
            $pdf->SetXY(40, 136); 
            $pdf->Write(0, $tiempo_m4);
        }

        if ($i == 1) { //
            $pdf->SetXY(69, 136); 
            $pdf->Write(0, $Departamento_a4);
        }

        if ($i == 1) { //
            $pdf->SetXY(111, 136); 
            $pdf->Write(0, $Municipio_a4);
        }

        if ($i == 1) { //
            $pdf->SetXY(144, 136); 
            $pdf->Write(0, $IPS_a4);
        }

        if ($i == 1) { //
            $pdf->SetXY(30, 158); 
            $pdf->Write(0, $Servicio1);
        }

        if ($i == 1) { //
            $pdf->SetXY(69, 158); 
            $pdf->Write(0, $Departamento_at1);
        }
        
        if ($i == 1) { //
            $pdf->SetXY(111, 158); 
            $pdf->Write(0, $Municipio_at1);
        }

        if ($i == 1) { //
            $pdf->SetXY(151, 158); 
            $pdf->Write(0, $cantidad1);
        }

        if ($i == 1) { //
            $pdf->SetXY(174, 158); 
            $pdf->Write(0, $valor_unitario1);
        }

        if ($i == 1) { //
            $pdf->SetXY(30, 164.2); 
            $pdf->Write(0, $Servicio2);
        }

        if ($i == 1) { //
            $pdf->SetXY(69, 164.2); 
            $pdf->Write(0, $Departamento_at2);
        }
        
        if ($i == 1) { //
            $pdf->SetXY(111, 164.2); 
            $pdf->Write(0, $Municipio_at2);
        }

        if ($i == 1) { //
            $pdf->SetXY(151, 164.2); 
            $pdf->Write(0, $cantidad2);
        }

        if ($i == 1) { //
            $pdf->SetXY(174, 164.2); 
            $pdf->Write(0, $valor_unitario2);
        }

        if ($i == 1) { //
            $pdf->SetXY(30, 170.5); 
            $pdf->Write(0, $Servicio3);
        }

        if ($i == 1) { //
            $pdf->SetXY(69, 170.5); 
            $pdf->Write(0, $Departamento_at3);
        }
        
        if ($i == 1) { //
            $pdf->SetXY(111, 170.5); 
            $pdf->Write(0, $Municipio_at3);
        }

        if ($i == 1) { //
            $pdf->SetXY(151, 170.5); 
            $pdf->Write(0, $cantidad3);
        }

        if ($i == 1) { //
            $pdf->SetXY(174, 170.5); 
            $pdf->Write(0, $valor_unitario3);
        }
        
        if ($i == 1) { //
            $pdf->SetXY(30, 177); 
            $pdf->Write(0, $Servicio4);
        }

        if ($i == 1) { //
            $pdf->SetXY(69, 177); 
            $pdf->Write(0, $Departamento_at4);
        }
        
        if ($i == 1) { //
            $pdf->SetXY(111, 177); 
            $pdf->Write(0, $Municipio_at4);
        }

        if ($i == 1) { //
            $pdf->SetXY(151, 177); 
            $pdf->Write(0, $cantidad4);
        }

        if ($i == 1) { //
            $pdf->SetXY(174, 177); 
            $pdf->Write(0, $valor_unitario4);
        }

                if ($i == 1) { //
            $pdf->SetXY(30, 183.3); 
            $pdf->Write(0, $Servicio5);
        }

        if ($i == 1) { //
            $pdf->SetXY(69, 183.3); 
            $pdf->Write(0, $Departamento_at5);
        }
        
        if ($i == 1) { //
            $pdf->SetXY(111, 183.3); 
            $pdf->Write(0, $Municipio_at5);
        }

        if ($i == 1) { //
            $pdf->SetXY(151, 183.3); 
            $pdf->Write(0, $cantidad5);
        }

        if ($i == 1) { //
            $pdf->SetXY(174, 183.3); 
            $pdf->Write(0, $valor_unitario5);
        }

        if ($i == 1) { //
            $pdf->SetXY(30, 190); 
            $pdf->Write(0, $Servicio6);
        }

        if ($i == 1) { //
            $pdf->SetXY(69, 190); 
            $pdf->Write(0, $Departamento_at6);
        }
        
        if ($i == 1) { //
            $pdf->SetXY(111, 190); 
            $pdf->Write(0, $Municipio_at6);
        }

        if ($i == 1) { //
            $pdf->SetXY(151, 190); 
            $pdf->Write(0, $cantidad6);
        }

        if ($i == 1) { //
            $pdf->SetXY(174, 190); 
            $pdf->Write(0, $valor_unitario6);
        }

        if ($i == 1) { //
            $pdf->SetXY(173, 196); 
            $pdf->Write(0, $val_rembolso);
        }

        if ($i == 1) { //
           
            $pdf->SetXY(15, 205);
            $pdf->SetX(15); 
            $pdf->MultiCell(180, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $motivo_des), 0, 'L', false);
        }

        if ($i == 1) {

            if ($tutela == 'si') { 
                $x_pos = 80.3;   
                $y_pos = 236.4;  
            } elseif ($tutela == 'no') {  
                $x_pos = 89;  
                $y_pos = 236;  
            }
        
            if ($tutela == 'si' || $tutela == 'no') {
                $pdf->SetXY($x_pos, $y_pos);  
                $pdf->Write(0, 'X');  
            }
        }
        if ($i == 1) { //
            $pdf->SetXY(102, 236.4); 
            $pdf->Write(0, $N°);
        }

        if ($i == 1) { //
            $pdf->SetXY(68, 250); 
            $pdf->Write(0, $nombre_acom);
        }

        if ($i == 1) { //
            $pdf->SetXY(172, 250); 
            $pdf->Write(0, $Parentesco);
        }

        if ($i == 1) { //
            $pdf->SetXY(63, 255.5); 
            $pdf->Write(0, $tipo_doc.'-'.$numero_idn);
        }

        if ($i == 1) { //
            $pdf->SetXY(120, 255.5); 
            $pdf->Write(0, $fec_na);
        }

        if ($i == 1) { //
            $pdf->SetXY(173, 255.5); 
            $pdf->Write(0, $tel_acom);
        }

        if ($i == 1) { //
            $pdf->SetXY(71, 269); 
            $pdf->Write(0, $nombre_repre.'  '.$segundo_n.'  '.$primer_p.'  '.$segundo_p);
        }

        if ($i == 1) { //
            $pdf->SetXY(162, 269); 
            $pdf->Write(0, $Parentesco_pago);
        }
                                                                                                
        if ($i == 1) {

            if ($t_cuenta == 'SV') { 
                $x_pos = 153;   
                $y_pos = 275.4;  
            } elseif ($t_cuenta == '03') {  
                $x_pos = 178.5;  
                $y_pos = 275.4; 
            }
        
            if ($t_cuenta == 'SV' || $t_cuenta == '03') {
                $pdf->SetXY($x_pos, $y_pos);  
                $pdf->Write(0, 'X');  
            }
        }

        if ($i == 1) { //
            $pdf->SetXY(90, 275.4); 
            $pdf->Write(0, $id_titular);
        }

        if ($i == 1) { //
            $pdf->SetXY(125, 277);
            $pdf->Cell(0, 10, $n_cuenta, 0, 1);
        }

        if ($i == 1) { //
            $pdf->SetXY(46, 282); 
            $pdf->Write(0, $t_banco_descripcion);
        }

        if ($i == 2) { //
            $pdf->SetXY(34, 69.5); 
            $pdf->Write(0, $nom_entidad);
        }

        if ($i == 2) { //
            $pdf->SetXY(125, 69.5); 
            $pdf->Write(0, $cargo);
        }

        if ($i == 2) { //
            $pdf->SetXY(160.2, 74); 
            $pdf->Write(0, $fecha_na);
        }

        if ($i == 2) { //
            $pdf->SetXY(67, 74); 
            $pdf->Write(0, $departamento_res);
        }

        if ($i == 2) { //
            $pdf->SetXY(120, 74); 
            $pdf->Write(0, $regional_res);
        }

        if ($i == 2) { //
            $pdf->SetXY(65.3, 95); 
            $pdf->Write(0, $Activo);
        }

        if ($i == 2) { //
            $pdf->SetXY(103.5, 95); 
            $pdf->Write(0, $Protección_Laboral);
        }

        if ($i == 2) { //
            $pdf->SetXY(137.5, 95); 
            $pdf->Write(0, $Suspendido);
        }

        if ($i == 2) { //
            $pdf->SetXY(168, 95); 
            $pdf->Write(0, $Retirado);
        }
        
        if ($i == 2) {
            if ($Oportunidad == 'si') { 
                $x_pos = 106.5;   
                $y_pos = 106.8;  
            } elseif ($Oportunidad == 'no') {  
                $x_pos = 129.5;  
                $y_pos = 106.8;  
            }
        
            if ($Oportunidad == 'si' || $Oportunidad == 'no') {
                $pdf->SetXY($x_pos, $y_pos);  
                $pdf->Write(0, 'X');  
            }
        }

        if ($i == 2) {
            if ($Completitud == 'si') { 
                $x_pos = 106.5;   
                $y_pos = 112;  
            } elseif ($Completitud == 'no') {  
                $x_pos = 129.5;  
                $y_pos = 112;  
            }
        
            if ($Completitud == 'si' || $Completitud == 'no') {
                $pdf->SetXY($x_pos, $y_pos);  
                $pdf->Write(0, 'X');  
            }
        }

        if ($i == 2) { //
            $pdf->SetFont('Arial', '', 4);
            $pdf->SetXY(133, 105);
            $pdf->SetX(133); 
            $pdf->MultiCell(62, 2, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $Observaciones), 0, 'L', false);
        }

        $pdf->SetFont('Arial', '', 7);

        if ($i == 2) { //
            $pdf->SetXY(93.7, 122.3); 
            $pdf->Write(0, $cop_identificacion);
        }

        if ($i == 2) { //
            $pdf->SetXY(189.4, 122.3); 
            $pdf->Write(0, $cert_bancaria);
        }

        if ($i == 2) { //
            $pdf->SetXY(93.7, 127); 
            $pdf->Write(0, $orden_medica);
        }

        if ($i == 2) { //
            $pdf->SetXY(189.4, 127); 
            $pdf->Write(0, $orden_medica);
        }

        if ($i == 2) { //
            $pdf->SetXY(93.7, 132); 
            $pdf->Write(0, $soporte_pro);
        }

        if ($i == 2) { //
            $pdf->SetXY(93.7, 143.5); 
            $pdf->Write(0, $fallo_tutela);
        }

        if ($i == 2) { //
            $pdf->SetXY(189.4, 143.5); 
            $pdf->Write(0, $cop_documental);
        }

        if ($i == 2) { //
            $pdf->SetXY(93.7, 148.3); 
            $pdf->Write(0, $aut_tuto);
        }

        if ($i == 2) { //
            $pdf->SetXY(189.4, 148.3); 
            $pdf->Write(0, $facturas_apro);
        }

        if ($i == 2) {
            if ($FOMAG == 'si') { 
                $x_pos = 63.5;   
                $y_pos = 160.5;  
            } elseif ($FOMAG == 'no') {  
                $x_pos = 76;  
                $y_pos = 160.5;  
            }
        
            if ($FOMAG == 'si' || $FOMAG == 'no') {
                $pdf->SetXY($x_pos, $y_pos);  
                $pdf->Write(0, 'X');  
            }
        }
        
        if ($i == 2) { //
            $pdf->SetXY(93.5, 160.2); 
            $pdf->Write(0, $Intermunicipal);
        }

        if ($i == 2) { //
            $pdf->SetXY(103.4, 160); 
            $pdf->Write(0, $Fluvial);
        }

        if ($i == 2) { //
            $pdf->SetXY(112, 160); 
            $pdf->Write(0, $Aéreo);
        }

        if ($i == 2) { //
            $pdf->SetXY(121, 159.9); 
            $pdf->Write(0, $Otros);
        }

        if ($i == 2) {
            if ($Hospedaje == 'si') { 
                $x_pos = 63.5;   
                $y_pos = 165.3;  
            } elseif ($Hospedaje == 'no') {  
                $x_pos = 76;  
                $y_pos = 165.3;  
            }
        
            if ($Hospedaje == 'si' || $Hospedaje == 'no') {
                $pdf->SetXY($x_pos, $y_pos);  
                $pdf->Write(0, 'X');  
            }
        }

        if ($i == 2) { //
            $pdf->SetXY(99, 164.5); 
            $pdf->Write(0, $Criterio_medico);
        }

        if ($i == 2) { //
            $pdf->SetXY(123, 164.3); 
            $pdf->Write(0, $Criterio_administrativo);
        }


        if ($i == 2) {
            if ($Acompañante == 'si') { 
                $x_pos = 63.5;   
                $y_pos = 170;  
            } elseif ($Acompañante == 'no') {  
                $x_pos = 76;  
                $y_pos = 170;  
            }
        
            if ($Acompañante == 'si' || $Acompañante == 'no') {
                $pdf->SetXY($x_pos, $y_pos);  
                $pdf->Write(0, 'X');  
            }
        }

        if ($i == 2) { //
            $pdf->SetXY(94.3, 169.6); 
            $pdf->Write(0, $men_18);
        }

        if ($i == 2) { //
            $pdf->SetXY(112.7, 169.6); 
            $pdf->Write(0, $may_65);
        }

        if ($i == 2) { //
            $pdf->SetXY(128.5, 169.6); 
            $pdf->Write(0, $Discapacidad);
        }


        if ($i == 2) {
            if ($Otro_opc == 'si') { 
                $x_pos = 63.5;   
                $y_pos = 175;  
            } elseif ($Otro_opc == 'no') {  
                $x_pos = 76;  
                $y_pos =175;  
            }
        
            if ($Otro_opc == 'si' || $Otro_opc == 'no') {
                $pdf->SetXY($x_pos, $y_pos);  
                $pdf->Write(0, 'X');  
            }
        }

        if ($i == 2) { //
            $pdf->SetXY(81,175); 
            $pdf->Write(0, $otro_text);
        }

         if ($i == 2) { //
            $pdf->SetXY(12, 185);
            $pdf->SetX(12); 
            $pdf->MultiCell(170, 3, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $pertinencia), 0, 'L', false);
        }

        if ($i == 2) { //
            $pdf->SetXY(175,160.5); 
            $pdf->Write(0, $can);
        }

         if ($i == 2) { //
            $pdf->SetXY(175,165.3); 
            $pdf->Write(0, $can1);
        }

        if ($i == 2) { //
            $pdf->SetXY(175,175); 
            $pdf->Write(0, $can3);
        }

        
    }



    // Output del PDF al navegador
    $pdf->Output('I', 'viaticos.pdf'); // 'D' descarga el archivo, 'I' lo muestra en el navegador
}
