<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/funciones.php';
requerir_admin();

$pdo = db();
$titulo = 'Materias';
$seccion = 'materias';
$errores = [];
$registro = ['id' => 0, 'clave' => '', 'nombre' => '', 'horas_semana' => 5];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['accion'] ?? '') === 'eliminar') {
        eliminar_registro($pdo, 'materias', (int)$_POST['id'], 'Materia eliminada.',
            'No se puede eliminar la materia porque se imparte en al menos un grupo. Elimina primero esas clases.');
        redirigir('materias.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $datos = [
        'clave'        => strtoupper(post('clave')),
        'nombre'       => post('nombre'),
        'horas_semana' => (int)post('horas_semana'),
    ];
    if ($datos['clave'] === '' || $datos['nombre'] === '') {
        $errores[] = 'Escribe la clave y el nombre de la materia.';
    }
    if ($datos['horas_semana'] < 1 || $datos['horas_semana'] > 40) {
        $errores[] = 'Las horas por semana deben estar entre 1 y 40.';
    }
    if (!$errores) {
        try {
            guardar($pdo, 'materias', $datos, $id);
            flash('ok', $id ? 'Materia actualizada.' : 'Materia creada.');
            redirigir('materias.php');
        } catch (PDOException $ex) {
            if (codigo_error($ex) !== 1062) {
                throw $ex;
            }
            $errores[] = 'Ya existe una materia con esa clave.';
        }
    }
    $registro = $datos + ['id' => $id];
} elseif (($_GET['accion'] ?? '') === 'editar') {
    $st = $pdo->prepare('SELECT * FROM materias WHERE id = ?');
    $st->execute([(int)($_GET['id'] ?? 0)]);
    $registro = $st->fetch() ?: $registro;
}

$materias = $pdo->query('SELECT m.*, (SELECT COUNT(*) FROM clases c WHERE c.materia_id = m.id) AS num_grupos
                         FROM materias m ORDER BY m.nombre')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="encabezado"><h1>Materias</h1></div>

<div class="dos-columnas">
  <form method="post" class="panel formulario formulario-angosto">
    <h2><?= $registro['id'] ? 'Editar materia' : 'Nueva materia' ?></h2>
    <?php foreach ($errores as $err): ?><div class="aviso aviso-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int)$registro['id'] ?>">
    <div class="campo"><label for="clave">Clave *</label>
      <input id="clave" name="clave" maxlength="15" placeholder="Ej. MAT2" required value="<?= e($registro['clave']) ?>"></div>
    <div class="campo"><label for="nombre">Nombre *</label>
      <input id="nombre" name="nombre" maxlength="100" required value="<?= e($registro['nombre']) ?>"></div>
    <div class="campo"><label for="horas_semana">Horas por semana</label>
      <input id="horas_semana" name="horas_semana" type="number" min="1" max="40" value="<?= (int)$registro['horas_semana'] ?>"></div>
    <div class="acciones-form">
      <button class="boton" type="submit"><?= $registro['id'] ? 'Guardar cambios' : 'Crear materia' ?></button>
      <?php if ($registro['id']): ?><a class="boton boton-secundario" href="materias.php">Cancelar</a><?php endif; ?>
    </div>
  </form>

  <div>
    <?php if (!$materias): ?>
      <div class="panel vacio">Crea tu primera materia con el formulario.</div>
    <?php else: ?>
    <div class="tabla-envoltura">
      <table>
        <thead><tr><th>Clave</th><th>Materia</th><th class="num">Horas</th><th class="num">Grupos</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($materias as $m): ?>
          <tr>
            <td><?= e($m['clave']) ?></td>
            <td><?= e($m['nombre']) ?></td>
            <td class="num"><?= (int)$m['horas_semana'] ?></td>
            <td class="num"><?= (int)$m['num_grupos'] ?></td>
            <td class="acciones">
              <a href="materias.php?accion=editar&amp;id=<?= (int)$m['id'] ?>">Editar</a>
              <form method="post" onsubmit="return confirm('¿Eliminar esta materia?');">
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
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
