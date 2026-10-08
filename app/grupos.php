<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/funciones.php';
requerir_admin();

$pdo = db();
$titulo = 'Grupos';
$seccion = 'grupos';
$errores = [];
$registro = ['id' => 0, 'nombre' => '', 'grado' => 1, 'turno' => 'Matutino', 'ciclo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['accion'] ?? '') === 'eliminar') {
        eliminar_registro($pdo, 'grupos', (int)$_POST['id'], 'Grupo eliminado. Sus alumnos quedaron sin grupo.',
            'No se puede eliminar el grupo porque tiene clases asignadas. Elimina primero sus clases.');
        redirigir('grupos.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $datos = [
        'nombre' => post('nombre'),
        'grado'  => (int)post('grado'),
        'turno'  => post('turno') === 'Vespertino' ? 'Vespertino' : 'Matutino',
        'ciclo'  => post('ciclo'),
    ];
    if ($datos['nombre'] === '' || $datos['ciclo'] === '') {
        $errores[] = 'Escribe el nombre del grupo y el ciclo escolar.';
    }
    if ($datos['grado'] < 1 || $datos['grado'] > 12) {
        $errores[] = 'El grado debe estar entre 1 y 12.';
    }
    if (!$errores) {
        try {
            guardar($pdo, 'grupos', $datos, $id);
            flash('ok', $id ? 'Grupo actualizado.' : 'Grupo creado.');
            redirigir('grupos.php');
        } catch (PDOException $ex) {
            if (codigo_error($ex) !== 1062) {
                throw $ex;
            }
            $errores[] = 'Ya existe un grupo con ese nombre en ese ciclo escolar.';
        }
    }
    $registro = $datos + ['id' => $id];
} elseif (($_GET['accion'] ?? '') === 'editar') {
    $st = $pdo->prepare('SELECT * FROM grupos WHERE id = ?');
    $st->execute([(int)($_GET['id'] ?? 0)]);
    $registro = $st->fetch() ?: $registro;
}

if ($registro['ciclo'] === '') {
    $anio = (int)date('Y');
    $registro['ciclo'] = (int)date('n') >= 8 ? "$anio-" . ($anio + 1) : ($anio - 1) . "-$anio";
}

$grupos = $pdo->query('SELECT g.*,
        (SELECT COUNT(*) FROM alumnos a WHERE a.grupo_id = g.id AND a.activo = 1) AS num_alumnos,
        (SELECT COUNT(*) FROM clases c WHERE c.grupo_id = g.id) AS num_clases
    FROM grupos g ORDER BY g.ciclo DESC, g.grado, g.nombre')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="encabezado"><h1>Grupos</h1></div>

<div class="dos-columnas">
  <form method="post" class="panel formulario formulario-angosto">
    <h2><?= $registro['id'] ? 'Editar grupo' : 'Nuevo grupo' ?></h2>
    <?php foreach ($errores as $err): ?><div class="aviso aviso-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int)$registro['id'] ?>">
    <div class="campo"><label for="nombre">Nombre *</label>
      <input id="nombre" name="nombre" maxlength="30" placeholder="Ej. 2° B" required value="<?= e($registro['nombre']) ?>"></div>
    <div class="campo"><label for="grado">Grado *</label>
      <input id="grado" name="grado" type="number" min="1" max="12" required value="<?= (int)$registro['grado'] ?>"></div>
    <div class="campo"><label for="turno">Turno</label>
      <select id="turno" name="turno">
        <?php foreach (['Matutino', 'Vespertino'] as $t): ?>
          <option<?= $registro['turno'] === $t ? ' selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="campo"><label for="ciclo">Ciclo escolar *</label>
      <input id="ciclo" name="ciclo" maxlength="20" required value="<?= e($registro['ciclo']) ?>"></div>
    <div class="acciones-form">
      <button class="boton" type="submit"><?= $registro['id'] ? 'Guardar cambios' : 'Crear grupo' ?></button>
      <?php if ($registro['id']): ?><a class="boton boton-secundario" href="grupos.php">Cancelar</a><?php endif; ?>
    </div>
  </form>

  <div>
    <?php if (!$grupos): ?>
      <div class="panel vacio">Crea tu primer grupo con el formulario.</div>
    <?php else: ?>
    <div class="tabla-envoltura">
      <table>
        <thead><tr><th>Grupo</th><th>Turno</th><th>Ciclo</th><th class="num">Alumnos</th><th class="num">Clases</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($grupos as $g): ?>
          <tr>
            <td><a href="alumnos.php?grupo=<?= (int)$g['id'] ?>"><?= e($g['nombre']) ?></a></td>
            <td><?= e($g['turno']) ?></td>
            <td><?= e($g['ciclo']) ?></td>
            <td class="num"><?= (int)$g['num_alumnos'] ?></td>
            <td class="num"><?= (int)$g['num_clases'] ?></td>
            <td class="acciones">
              <a href="grupos.php?accion=editar&amp;id=<?= (int)$g['id'] ?>">Editar</a>
              <form method="post" onsubmit="return confirm('¿Eliminar este grupo?');">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                <button class="enlace-peligro" type="submit">Eliminar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
