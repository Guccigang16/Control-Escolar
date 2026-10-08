<?php
declare(strict_types=1);

// Copia este archivo como config/db.php y escribe tus datos.
// En Linux, scripts/instalar_mariadb.sh lo genera automáticamente.
// config/db.php NO se sube a GitHub (está en .gitignore) porque lleva contraseñas.
const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'control_escolar';
const DB_USER = 'root';
const DB_PASS = '';

const NOMBRE_ESCUELA   = 'Escuela Secundaria';
const CALIF_APROBATORIA = 6.0;  // Escala de 0 a 10

date_default_timezone_set('America/Mexico_City');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('No se pudo conectar a la base de datos. Verifica que MySQL/MariaDB esté iniciado '
               . 'y que los datos de config/db.php sean correctos.');
        }
    }
    return $pdo;
}
