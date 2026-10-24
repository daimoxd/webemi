<?php
/**
 * ------------------------------------------------------------------
 * Conexión a Base de Datos
 * ------------------------------------------------------------------
 * Devuelve una instancia PDO conectada a generadorPerfilesDB.
 * Se usa charset utf8mb4 para aceptar tildes, caracteres especiales
 * y emojis.
 */
function getDB() {
    $db_host = 'localhost';
    $db_name = 'generadorPerfilesDB';
    $db_user = 'root'; // Cambia esto si tu usuario de MySQL es distinto
    $db_pass = '';     

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        // Si index.php ya definió responderError(), la usamos para
        // mantener el formato JSON del endpoint.
        if (function_exists('responderError')) {
            responderError('Error de conexión a la base de datos.', 500);
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Error de conexión a la base de datos.',
        ]);
        exit;
    }
}
