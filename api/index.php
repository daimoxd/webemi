<?php
    error_reporting(0);
    date_default_timezone_set("America/Mexico_City");
    header('Content-Type: application/json; charset=utf-8');

    // Sesión PHP: se usa para login/logout y para saber quién guarda tarjetas.
    session_start();

    // Origen de datos:
    // Se movió FUERA de la ruta pública directa (antes se leía con fetch
    // directo desde app.js); ahora solo este endpoint lo toca.
    define('RUTA_DATOS', __DIR__ . '/data/tarjetas.json');
    /**
    * --------------------------------------------------
    * Utilidades de respuesta
    * --------------------------------------------------
    */
    function responder($statusCode, $payload) {
        http_response_code($statusCode);
        echo json_encode($payload);
        exit;
    }

    function responderExito($mensaje, $data = null) {
        responder(200, [
            'success' => true,
            'message' => $mensaje,
            'data' => $data,
        ]);
    }

    function responderError($mensaje, $statusCode = 400) {
        responder($statusCode, [
            'success' => false,
            'error' => $mensaje,
        ]);
    }

    // Conexión PDO a generadorPerfilesDB (usuarios).
    // Se incluye después de definir responderError() para que getDB()
    // pueda devolver un error JSON si la conexión falla.
    require_once __DIR__ . '/conectdb.php';
    /*--------------------------------------------------------------------------
    * Persistencia de tarjetas: el archivo JSON es la única fuente de datos.
    * (Los usuarios ahora viven en generadorPerfilesDB).
    *--------------------------------------------------------------------------*/
    function leerTarjetas() {
        if (!file_exists(RUTA_DATOS)) {
            return [];
        }

        $contenido = file_get_contents(RUTA_DATOS);
        if ($contenido === false || trim($contenido) === '') {
            return [];
        }

        $tarjetas = json_decode($contenido, true);
        // Si el JSON está corrupto se trata como lista vacía en lugar de
        // romper toda la galería.
        return is_array($tarjetas) ? $tarjetas : [];
    }
    function guardarTarjetas($tarjetas) {
        $directorio = dirname(RUTA_DATOS);
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $json = json_encode(
            $tarjetas,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            return false;
        }

        // LOCK_EX evita que dos escrituras simultáneas se pisen entre sí.
        return file_put_contents(RUTA_DATOS, $json, LOCK_EX) !== false;
    }
    /*--------------------------------------------------------------------------
    * Usuarios: se guardan en la tabla usuarios de generadorPerfilesDB.
    * Las contraseñas NUNCA se guardan en texto plano, solo su hash.
    *--------------------------------------------------------------------------*/
    // Quita el hash de la contraseña antes de devolver un usuario al cliente.
    function usuarioPublico($usuario) {
        unset($usuario['password'], $usuario['password_hash']);
        return $usuario;
    }
    $action = $_GET['action'] ?? $_POST['action'] ?? null;
    switch($action){
        case 'crear_tarjeta':
            accionCrearTarjeta();
            break;
        case 'listar_tarjeta':
        case 'listar_tarjetas':
            accionListarTarjetas();
            break;
        case 'registrar_usuario':
            accionRegistrarUsuario();
            break;
        case 'login':
            accionLogin();
            break;
        case 'logout':
            accionLogout();
            break;
        case 'check_session':
            accionCheckSession();
            break;
        default:
            responderError('Accion no reconocida o no especificada', 404);
    }
    /*--------------------------------------------------------------------------
    * Acciones
    *--------------------------------------------------------------------------*/
    function accionCrearTarjeta() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderError('Método no permitido. Usa POST.', 405);
        }

        $inputJSON = $_POST['datos_tarjeta'] ?? file_get_contents('php://input');
        $nuevaTarjeta = json_decode($inputJSON, true);

        if (!$nuevaTarjeta || empty($nuevaTarjeta['name'])) {
            responderError('Datos inválidos o incompletos.', 400);
        }

        $nuevaTarjeta['id'] = uniqid('user_');
        $nuevaTarjeta['createdAt'] = date('Y-m-d H:i:s');

        // Si hay una sesión activa, la tarjeta queda ligada a ese usuario.
        $nuevaTarjeta['usuario_id'] = $_SESSION['usuario']['id'] ?? null;

        $tarjetas = leerTarjetas();
        $tarjetas[] = $nuevaTarjeta;

        if (guardarTarjetas($tarjetas) === false) {
            responderError('Error de permisos. El servidor no permite escribir en api/data/.', 500);
        }

        responderExito('Tarjeta guardada correctamente en el servidor.', $nuevaTarjeta);
    }
    function accionListarTarjetas() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            responderError('Método no permitido. Usa GET.', 405);
        }

        $tarjetas = leerTarjetas();
        responderExito('Tarjetas obtenidas correctamente.', $tarjetas);
    }
    function accionRegistrarUsuario() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderError('Método no permitido. Usa POST.', 405);
        }

        // Mismos campos que el formulario de registro del frontend.
        $camposObligatorios = [
            'nombre', 'nacimiento', 'educacion', 'telefono',
            'horas', 'email', 'nickname', 'password',
        ];
        foreach ($camposObligatorios as $campo) {
            if (!isset($_POST[$campo]) || trim((string) $_POST[$campo]) === '') {
                responderError("El campo \"$campo\" es obligatorio.", 422);
            }
        }

        $nombre = trim($_POST['nombre']);
        $fechaNacimiento = trim($_POST['nacimiento']);
        $nivelEducativo = trim($_POST['educacion']);
        $telefono = trim($_POST['telefono']);
        $horas = trim($_POST['horas']);
        $email = trim($_POST['email']);
        $nickname = trim($_POST['nickname']);
        $password = (string) $_POST['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            responderError('El correo electrónico no es válido.', 422);
        }

        $fecha = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
        if (!$fecha || $fecha->format('Y-m-d') !== $fechaNacimiento) {
            responderError('La fecha de nacimiento no es válida.', 422);
        }

        if (!is_numeric($horas) || $horas < 0 || $horas > 24) {
            responderError('Las horas programando deben estar entre 0 y 24.', 422);
        }

        if (strlen($password) < 6) {
            responderError('La contraseña debe tener al menos 6 caracteres.', 422);
        }

        if (
            mb_strlen($nombre) > 150
            || mb_strlen($telefono) > 15
            || mb_strlen($nickname) > 50
            || mb_strlen($email) > 150
        ) {
            responderError('Uno de los campos excede la longitud permitida.', 422);
        }

        $nivelesPermitidos = ['preparatoria', 'ingenieria', 'maestria', 'doctorado'];
        if (!in_array($nivelEducativo, $nivelesPermitidos, true)) {
            responderError('El nivel educativo no es válido.', 422);
        }

        try {
            $pdo = getDB();

            $stmt = $pdo->prepare(
                'SELECT email, nickname FROM usuarios
                 WHERE email = :email OR nickname = :nickname
                 LIMIT 1'
            );
            $stmt->execute([
                ':email' => $email,
                ':nickname' => $nickname,
            ]);
            $usuarioExistente = $stmt->fetch();

            if ($usuarioExistente) {
                if (strcasecmp($usuarioExistente['email'], $email) === 0) {
                    responderError('Ya existe una cuenta registrada con ese correo.', 409);
                }
                responderError('Ese nickname ya está en uso.', 409);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO usuarios
                    (nombre_completo, fecha_nacimiento, nivel_educativo, telefono,
                     horas_programando, email, nickname, password_hash)
                 VALUES
                    (:nombre_completo, :fecha_nacimiento, :nivel_educativo, :telefono,
                     :horas_programando, :email, :nickname, :password_hash)'
            );
            $stmt->execute([
                ':nombre_completo' => $nombre,
                ':fecha_nacimiento' => $fechaNacimiento,
                ':nivel_educativo' => $nivelEducativo,
                ':telefono' => $telefono,
                ':horas_programando' => $horas,
                ':email' => $email,
                ':nickname' => $nickname,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            $id = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'SELECT id, nombre_completo, fecha_nacimiento, nivel_educativo, telefono,
                        horas_programando, email, nickname, fecha_registro
                 FROM usuarios
                 WHERE id = :id'
            );
            $stmt->execute([':id' => $id]);
            $usuario = $stmt->fetch();
        } catch (PDOException $e) {
            // 23000 = violación de una restricción UNIQUE (correo o nickname).
            if ((string) $e->getCode() === '23000') {
                responderError('El correo o el nickname ya están registrados.', 409);
            }
            responderError('No se pudo completar el registro en la base de datos.', 500);
        }

        responderExito('Registro exitoso. Ahora puedes iniciar sesión.', usuarioPublico($usuario));
    }
    function accionLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderError('Método no permitido. Usa POST.', 405);
        }

        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            responderError('Correo y contraseña son obligatorios.', 422);
        }

        try {
            $pdo = getDB();
            $stmt = $pdo->prepare(
                'SELECT id, nombre_completo, fecha_nacimiento, nivel_educativo, telefono,
                        horas_programando, email, nickname, password_hash, fecha_registro
                 FROM usuarios
                 WHERE email = :email
                 LIMIT 1'
            );
            $stmt->execute([':email' => $email]);
            $usuarioEncontrado = $stmt->fetch();
        } catch (PDOException $e) {
            responderError('No se pudo consultar la base de datos.', 500);
        }

        // Mismo mensaje para correo inexistente y contraseña incorrecta:
        // así no se filtra qué correos están registrados.
        if (
            !$usuarioEncontrado
            || !password_verify($password, $usuarioEncontrado['password_hash'])
        ) {
            responderError('Correo o contraseña incorrectos.', 401);
        }

        // Evita fijación de sesión al iniciar sesión.
        session_regenerate_id(true);
        $_SESSION['usuario'] = usuarioPublico($usuarioEncontrado);

        responderExito('Bienvenido de nuevo.', usuarioPublico($usuarioEncontrado));
    }
    function accionLogout() {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        responderExito('Sesión cerrada correctamente.');
    }
    function accionCheckSession() {
        if (empty($_SESSION['usuario'])) {
            responderError('No hay una sesión activa.', 401);
        }

        responderExito('Sesión activa.', $_SESSION['usuario']);
    }
