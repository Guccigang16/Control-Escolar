<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/funciones.php';
requerir_admin();

$pdo = db();
$titulo = 'Clases';
$seccion = 'clases';
$errores = [];
$registro = ['id' => 0, 'grupo_id' => '', 'materia_id' => '', 'maestro_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['accion'] ?? '') === 'eliminar') {
        eliminar_registro($pdo, 'clases', (int)$_POST['id'], 'Clase eliminada junto con sus calificaciones y asistencias.',
            'No se pudo eliminar la clase.');
        redirigir('clases.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $datos = [
        'grupo_id'   => (int)post('grupo_id'),
        'materia_id' => (int)post('materia_id'),
        'maestro_id' => (int)post('maestro_id') ?: null,
    ];
    if (!$datos['grupo_id'] || !$datos['materia_id']) {
        $errores[] = 'Elige el grupo y la materia.';
    } else {
        try {
            guardar($pdo, 'clases', $datos, $id);
            flash('ok', $id ? 'Clase actualizada.' : 'Clase asignada.');
            redirigir('clases.php');
        } catch (PDOException $ex) {
            if (codigo_error($ex) !== 1062) {
                throw $ex;
            }
            $errores[] = 'Ese grupo ya tiene esa materia. Edita la clase existente para cambiar al maestro.';
        }
    }
    $registro = $datos + ['id' => $id];
} elseif (($_GET['accion'] ?? '') === 'editar') {
    $st = $pdo->prepare('SELECT * FROM clases WHERE id = ?');
    $st->execute([(int)($_GET['id'] ?? 0)]);
    $registro = $st->fetch() ?: $registro;
}

$grupos   = $pdo->query("SELECT id, CONCAT(nombre, ' (', ciclo, ')') AS nombre FROM grupos ORDER BY ciclo DESC, nombre")->fetchAll();
$materias = $pdo->query("SELECT id, CONCAT(clave, ' - ', nombre) AS nombre FROM materias ORDER BY nombre")->fetchAll();
$maestros = $pdo->query("SELECT id, CONCAT(apellidos, ', ', nombre) AS nombre FROM maestros WHERE activo = 1 ORDER BY apellidos")->fetchAll();
$clases   = clases_permitidas($pdo);

require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
  <div>
    <h1>Clases</h1>
    <p class="tenue">Una clase es una materia que se imparte a un grupo. Aquí decides quién la da.</p>
  </div>
</div>

<div class="dos-columnas">
  <form method="post" class="panel formulario formulario-angosto">
    <h2><?= $registro['id'] ? 'Editar clase' : 'Asignar materia a grupo' ?></h2>
    <?php foreach ($errores as $err): ?><div class="aviso aviso-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
    <?php if (!$grupos || !$materias): ?>
      <p class="tenue">Primero necesitas al menos un <a href="grupos.php">grupo</a> y una <a href="materias.php">materia</a>.</p>
    <?php endif; ?>
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int)$registro['id'] ?>">
    <div class="campo"><label for="grupo_id">Grupo *</label>
      <select id="grupo_id" name="grupo_id" required><option value="">Elige un grupo</option><?= opciones($grupos, $registro['grupo_id']) ?></select></div>
    <div class="campo"><label for="materia_id">Materia *</label>
      <select id="materia_id" name="materia_id" required><option value="">Elige una materia</option><?= opciones($materias, $registro['materia_id']) ?></select></div>
    <div class="campo"><label for="maestro_id">Maestro</label>
      <select id="maestro_id" name="maestro_id"><option value="">Sin asignar</option><?= opciones($maestros, $registro['maestro_id'] ?? '') ?></select></div>
    <div class="acciones-form">
      <button class="boton" type="submit"><?= $registro['id'] ? 'Guardar cambios' : 'Asignar' ?></button>
      <?php if ($registro['id']): ?><a class="boton boton-secundario" href="clases.php">Cancelar</a><?php endif; ?>
    </div>
  </form>

  <div>
    <?php if (!$clases): ?>
      <div class="panel vacio">Todavía no hay clases asignadas.</div>
    <?php else: ?>
    <div class="tabla-envoltura">
      <table>
        <thead><tr><th>Grupo</th><th>Materia</th><th>Maestro</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($clases as $c): ?>
          <tr>
            <td><?= e($c['grupo']) ?></td>
            <td><?= e($c['materia']) ?></td>
            <td><?= $c['maestro'] ? e($c['maestro']) : '<span class="aviso-texto">Sin asignar</span>' ?></td>
            <td class="acciones">
              <a href="clases.php?accion=editar&amp;id=<?= (int)$c['id'] ?>">Editar</a>
              <form method="post" onsubmit="return confirm('¿Eliminar esta clase? Se borrarán también sus calificaciones y asistencias.');">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
