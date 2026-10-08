<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/funciones.php';

if (usuario()) {
    redirigir('index.php');
}

$error = '';
$email = '';
$bloqueado_hasta = $_SESSION['bloqueo'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $email = post('email');
    $password = (string)($_POST['password'] ?? '');

    if ($bloqueado_hasta > time()) {
        $error = 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.';
    } else {
        $st = db()->prepare('SELECT id, nombre, password, rol FROM usuarios WHERE email = ? AND activo = 1');
        $st->execute([$email]);
        $fila = $st->fetch();

        if ($fila && password_verify($password, $fila['password'])) {
            session_regenerate_id(true);
            unset($_SESSION['intentos'], $_SESSION['bloqueo']);

            $maestro_id = null;
            if ($fila['rol'] === 'maestro') {
                $m = db()->prepare('SELECT id FROM maestros WHERE usuario_id = ?');
                $m->execute([$fila['id']]);
                $maestro_id = $m->fetchColumn() ?: null;
            }
            $_SESSION['usuario'] = [
                'id'         => (int)$fila['id'],
                'nombre'     => $fila['nombre'],
                'rol'        => $fila['rol'],
                'maestro_id' => $maestro_id !== null ? (int)$maestro_id : null,
            ];
            redirigir('index.php');
        }

        $_SESSION['intentos'] = ($_SESSION['intentos'] ?? 0) + 1;
        if ($_SESSION['intentos'] >= 5) {
            $_SESSION['bloqueo'] = time() + 300;
            $_SESSION['intentos'] = 0;
        }
        $error = 'El correo o la contraseña no son correctos.';
    }
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión | Control escolar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap">
<link rel="stylesheet" href="assets/estilos.css">
</head>
<body class="login-pagina">
  <form class="login-caja" method="post" novalidate>
    <span class="escudo" aria-hidden="true">CE</span>
    <h1><?= e(NOMBRE_ESCUELA) ?></h1>
    <p class="tenue">Entra con tu cuenta para continuar.</p>
    <?php if ($error): ?><div class="aviso aviso-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_campo() ?>
    <div class="campo">
      <label for="email">Correo</label>
      <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
    </div>
    <div class="campo">
      <label for="password">Contraseña</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button class="boton boton-bloque" type="submit">Iniciar sesión</button>
  </form>
</body>
</html>
