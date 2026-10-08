<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/funciones.php';
requerir_admin();

$pdo = db();
$titulo = 'Maestros';
$seccion = 'maestros';
$errores = [];
$accion = $_GET['accion'] ?? '';
$registro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['accion'] ?? '') === 'eliminar') {
        $id = (int)$_POST['id'];
        $st = $pdo->prepare('SELECT usuario_id FROM maestros WHERE id = ?');
        $st->execute([$id]);
        $usuario_id = (int)$st->fetchColumn();
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM maestros WHERE id = ?')->execute([$id]);
        if ($usuario_id) {
            $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$usuario_id]);
        }
        $pdo->commit();
        flash('ok', 'Maestro eliminado. Sus clases quedaron sin maestro asignado.');
        redirigir('maestros.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $datos = [
        'nombre'       => post('nombre'),
        'apellidos'    => post('apellidos'),
        'email'        => vacio_a_null(strtolower(post('email'))),
        'telefono'     => vacio_a_null(post('telefono')),
        'especialidad' => vacio_a_null(post('especialidad')),
        'activo'       => isset($_POST['activo']) ? 1 : 0,
    ];
    $password = (string)($_POST['password'] ?? '');

    if ($datos['nombre'] === '' || $datos['apellidos'] === '') {
        $errores[] = 'Escribe el nombre y los apellidos.';
    }
    if ($datos['email'] !== null && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo no tiene un formato válido.';
    }
    if ($password !== '') {
        if ($datos['email'] === null) {
            $errores[] = 'Para dar acceso al sistema, el maestro necesita un correo.';
        }
        if (strlen($password) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
    }

    if (!$errores) {
        try {
            $pdo->beginTransaction();
            $id = guardar($pdo, 'maestros', $datos, $id);

            $st = $pdo->prepare('SELECT usuario_id FROM maestros WHERE id = ?');
            $st->execute([$id]);
            $usuario_id = (int)$st->fetchColumn();
            $nombre_completo = $datos['nombre'] . ' ' . $datos['apellidos'];

            if ($usuario_id) {
                // Mantener sincronizada la cuenta de acceso
                $pdo->prepare('UPDATE usuarios SET nombre = ?, activo = ?, email = COALESCE(?, email) WHERE id = ?')
                    ->execute([$nombre_completo, $datos['activo'], $datos['email'], $usuario_id]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $usuario_id]);
                }
            } elseif ($password !== '') {
                $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, activo) VALUES (?, ?, ?, 'maestro', ?)")
                    ->execute([$nombre_completo, $datos['email'], password_hash($password, PASSWORD_DEFAULT), $datos['activo']]);
                $pdo->prepare('UPDATE maestros SET usuario_id = ? WHERE id = ?')
                    ->execute([(int)$pdo->lastInsertId(), $id]);
            }
            $pdo->commit();
            flash('ok', 'Datos del maestro guardados.');
            redirigir('maestros.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            if (codigo_error($ex) !== 1062) {
                throw $ex;
            }
            $errores[] = 'Ese correo ya lo usa otra cuenta del sistema.';
        }
    }
    $registro = $datos + ['id' => (int)($_POST['id'] ?? 0), 'usuario_id' => (int)($_POST['usuario_id'] ?? 0)];
    $accion = 'formulario';
}

if ($accion === 'editar') {
    $st = $pdo->prepare('SELECT * FROM maestros WHERE id = ?');
    $st->execute([(int)($_GET['id'] ?? 0)]);
    $registro = $st->fetch() ?: null;
    if (!$registro) {
        flash('error', 'Ese maestro no existe.');
        redirigir('maestros.php');
    }
    $accion = 'formulario';
} elseif ($accion === 'nuevo') {
    $registro = ['id' => 0, 'activo' => 1, 'usuario_id' => 0];
    $accion = 'formulario';
}

if ($accion !== 'formulario') {
    $maestros = $pdo->query('SELECT m.*, (SELECT COUNT(*) FROM clases c WHERE c.maestro_id = m.id) AS num_clases
                             FROM maestros m ORDER BY m.apellidos, m.nombre')->fetchAll();
}

require __DIR__ . '/includes/header.php';
?>

<?php if ($accion === 'formulario'): ?>
  <div class="encabezado">
    <h1><?= $registro['id'] ? 'Editar maestro' : 'Nuevo maestro' ?></h1>
    <a class="boton boton-secundario" href="maestros.php">Volver a la lista</a>
  </div>
  <?php foreach ($errores as $err): ?><div class="aviso aviso-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" class="panel formulario">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int)$registro['id'] ?>">
    <input type="hidden" name="usuario_id" value="<?= (int)($registro['usuario_id'] ?? 0) ?>">
    <div class="campo"><label for="nombre">Nombre(s) *</label>
      <input id="nombre" name="nombre" maxlength="80" required value="<?= e($registro['nombre'] ?? '') ?>"></div>
    <div class="campo"><label for="apellidos">Apellidos *</label>
      <input id="apellidos" name="apellidos" maxlength="100" required value="<?= e($registro['apellidos'] ?? '') ?>"></div>
    <div class="campo"><label for="especialidad">Especialidad</label>
      <input id="especialidad" name="especialidad" maxlength="100" value="<?= e($registro['especialidad'] ?? '') ?>"></div>
    <div class="campo"><label for="telefono">Teléfono</label>
      <input id="telefono" name="telefono" type="tel" maxlength="20" value="<?= e($registro['telefono'] ?? '') ?>"></div>
    <div class="campo"><label for="email">Correo</label>
      <input id="email" name="email" type="email" maxlength="150" value="<?= e($registro['email'] ?? '') ?>"></div>
    <div class="campo"><label for="password"><?= !empty($registro['usuario_id']) ? 'Nueva contraseña' : 'Contraseña para entrar al sistema' ?></label>
      <input id="password" name="password" type="password" minlength="8" autocomplete="new-password">
      <small class="tenue"><?= !empty($registro['usuario_id'])
          ? 'Ya tiene acceso. Déjalo vacío para no cambiar la contraseña.'
          : 'Opcional. Si la escribes, el maestro podrá entrar con su correo.' ?></small></div>
    <div class="campo campo-check">
      <label><input type="checkbox" name="activo" value="1"<?= !empty($registro['activo']) ? ' checked' : '' ?>> Maestro activo</label>
    </div>
    <div class="acciones-form">
      <button class="boton" type="submit">Guardar maestro</button>
      <a class="boton boton-secundario" href="maestros.php">Cancelar</a>
    </div>
  </form>

<?php else: ?>
  <div class="encabezado">
    <h1>Maestros</h1>
    <a class="boton" href="maestros.php?accion=nuevo">Registrar maestro</a>
  </div>
  <?php if (!$maestros): ?>
    <div class="panel vacio">Aún no hay maestros registrados.</div>
  <?php else: ?>
  <div class="tabla-envoltura">
    <table>
      <thead><tr><th>Nombre</th><th>Especialidad</th><th>Correo</th><th class="num">Clases</th><th>Acceso</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($maestros as $m): ?>
        <tr>
          <td><?= e($m['apellidos'] . ', ' . $m['nombre']) ?><?= $m['activo'] ? '' : ' <span class="etiqueta">Inactivo</span>' ?></td>
          <td><?= e($m['especialidad']) ?></td>
          <td><?= e($m['email']) ?></td>
          <td class="num"><?= (int)$m['num_clases'] ?></td>
          <td><?= $m['usuario_id'] ? 'Puede entrar' : '<span class="tenue">Sin cuenta</span>' ?></td>
          <td class="acciones">
            <a href="maestros.php?accion=editar&amp;id=<?= (int)$m['id'] ?>">Editar</a>
            <form method="post" onsubmit="return confirm('¿Eliminar a este maestro y su cuenta de acceso? Sus clases quedarán sin maestro.');">
              <?= csrf_campo() ?>
              <input type="hidden" name="accion" value="eliminar">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button class="enlace-peligro" type="submit">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
