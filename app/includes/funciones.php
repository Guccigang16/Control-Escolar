<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('control_escolar');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ---------- Utilidades generales ---------- */

function e($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function post(string $campo): string
{
    $v = $_POST[$campo] ?? '';
    return is_string($v) ? trim($v) : '';
}

function vacio_a_null(string $v): ?string
{
    return $v === '' ? null : $v;
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function mensajes_flash(): array
{
    $m = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $m;
}

function fecha_valida(string $f): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $f);
    return $d !== false && $d->format('Y-m-d') === $f;
}

/** "lunes 5 de octubre de 2026" sin depender de la extensión intl. */
function fecha_larga(string $f): string
{
    $dias  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
              'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = strtotime($f);
    return ucfirst($dias[(int)date('w', $t)]) . ' ' . date('j', $t) . ' de '
         . $meses[(int)date('n', $t)] . ' de ' . date('Y', $t);
}

/* ---------- Protección CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verificar_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && !hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('La sesión del formulario expiró. Regresa, recarga la página e inténtalo de nuevo.');
    }
}

/* ---------- Autenticación y permisos ---------- */

function usuario(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function es_admin(): bool
{
    $u = usuario();
    return $u !== null && $u['rol'] === 'admin';
}

function requerir_login(): void
{
    if (usuario() === null) {
        redirigir('login.php');
    }
    verificar_csrf();
}

function requerir_admin(): void
{
    requerir_login();
    if (!es_admin()) {
        http_response_code(403);
        $titulo = 'Sin permiso';
        require __DIR__ . '/header.php';
        echo '<div class="panel vacio"><h1>Sin permiso</h1><p>Esta sección es solo para administración.</p>'
           . '<a class="boton" href="index.php">Ir al inicio</a></div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

/** Clases que el usuario actual puede ver: todas (admin) o solo las suyas (maestro). */
function clases_permitidas(PDO $pdo): array
{
    $sql = "SELECT c.id, c.grupo_id, g.nombre AS grupo, g.ciclo, m.nombre AS materia,
                   CONCAT(ma.nombre, ' ', ma.apellidos) AS maestro
            FROM clases c
            JOIN grupos g   ON g.id = c.grupo_id
            JOIN materias m ON m.id = c.materia_id
            LEFT JOIN maestros ma ON ma.id = c.maestro_id";
    if (es_admin()) {
        return $pdo->query($sql . ' ORDER BY g.nombre, m.nombre')->fetchAll();
    }
    $st = $pdo->prepare($sql . ' WHERE c.maestro_id = ? ORDER BY g.nombre, m.nombre');
    $st->execute([(int)(usuario()['maestro_id'] ?? 0)]);
    return $st->fetchAll();
}

function buscar_clase(array $clases, int $id): ?array
{
    foreach ($clases as $c) {
        if ((int)$c['id'] === $id) {
            return $c;
        }
    }
    return null;
}

function alumnos_de_grupo(PDO $pdo, int $grupo_id): array
{
    $st = $pdo->prepare('SELECT id, matricula, nombre, apellidos FROM alumnos
                         WHERE grupo_id = ? AND activo = 1 ORDER BY apellidos, nombre');
    $st->execute([$grupo_id]);
    return $st->fetchAll();
}

/* ---------- Ayudantes de base de datos ---------- */

/** Inserta o actualiza. $tabla y las claves de $datos vienen del código, nunca del usuario. */
function guardar(PDO $pdo, string $tabla, array $datos, int $id = 0): int
{
    $cols = array_keys($datos);
    if ($id > 0) {
        $set = implode(', ', array_map(fn($c) => "`$c` = :$c", $cols));
        $pdo->prepare("UPDATE `$tabla` SET $set WHERE id = :id_registro")
            ->execute($datos + ['id_registro' => $id]);
        return $id;
    }
    $sql = "INSERT INTO `$tabla` (`" . implode('`, `', $cols) . '`) VALUES (:' . implode(', :', $cols) . ')';
    $pdo->prepare($sql)->execute($datos);
    return (int)$pdo->lastInsertId();
}

function codigo_error(PDOException $ex): int
{
    return (int)($ex->errorInfo[1] ?? 0);
}

function eliminar_registro(PDO $pdo, string $tabla, int $id, string $ok, string $bloqueado): void
{
    try {
        $pdo->prepare("DELETE FROM `$tabla` WHERE id = ?")->execute([$id]);
        flash('ok', $ok);
    } catch (PDOException $ex) {
        if (codigo_error($ex) !== 1451) {
            throw $ex;
        }
        flash('error', $bloqueado);
    }
}

/** Genera <option> para un <select>. */
function opciones(array $filas, $seleccionado, string $texto = 'nombre', string $valor = 'id'): string
{
    $html = '';
    foreach ($filas as $f) {
        $sel = (string)$f[$valor] === (string)$seleccionado ? ' selected' : '';
        $html .= '<option value="' . e($f[$valor]) . '"' . $sel . '>' . e($f[$texto]) . '</option>';
    }
    return $html;
}

function formato_calif($v): string
{
    return $v === null ? '' : number_format((float)$v, 1);
}
