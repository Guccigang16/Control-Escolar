<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/funciones.php';
requerir_admin();

$pdo = db();
$titulo = 'Alumnos';
$seccion = 'alumnos';
$errores = [];
$accion = $_GET['accion'] ?? '';
$registro = null;

/* ---------- Acciones POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['accion'] ?? '') === 'eliminar') {
        eliminar_registro($pdo, 'alumnos', (int)$_POST['id'], 'Alumno eliminado.', 'No se pudo eliminar el alumno.');
        redirigir('alumnos.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $datos = [
        'matricula'        => strtoupper(post('matricula')),
        'nombre'           => post('nombre'),
        'apellidos'        => post('apellidos'),
        'curp'             => vacio_a_null(strtoupper(post('curp'))),
        'fecha_nacimiento' => vacio_a_null(post('fecha_nacimiento')),
        'sexo'             => in_array(post('sexo'), ['H', 'M'], true) ? post('sexo') : null,
        'email'            => vacio_a_null(post('email')),
        'telefono'         => vacio_a_null(post('telefono')),
        'tutor'            => vacio_a_null(post('tutor')),
        'grupo_id'         => (int)post('grupo_id') ?: null,
        'activo'           => isset($_POST['activo']) ? 1 : 0,
    ];

    if ($datos['matricula'] === '' || $datos['nombre'] === '' || $datos['apellidos'] === '') {
        $errores[] = 'Escribe la matrícula, el nombre y los apellidos.';
    }
    if ($datos['curp'] !== null && !preg_match('/^[A-Z0-9]{18}$/', $datos['curp'])) {
        $errores[] = 'La CURP debe tener 18 letras o números.';
    }
    if ($datos['email'] !== null && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo no tiene un formato válido.';
    }
    if ($datos['fecha_nacimiento'] !== null && !fecha_valida($datos['fecha_nacimiento'])) {
        $errores[] = 'La fecha de nacimiento no es válida.';
    }

    if (!$errores) {
        try {
            guardar($pdo, 'alumnos', $datos, $id);
            flash('ok', $id ? 'Cambios guardados.' : 'Alumno registrado.');
            redirigir('alumnos.php');
        } catch (PDOException $ex) {
            if (codigo_error($ex) !== 1062) {
                throw $ex;
            }
            $errores[] = 'Ya existe un alumno con esa matrícula o esa CURP.';
        }
    }
    $registro = $datos + ['id' => $id];
    $accion = 'formulario';
}

$grupos = $pdo->query("SELECT id, CONCAT(nombre, ' (', ciclo, ')') AS nombre FROM grupos ORDER BY ciclo DESC, nombre")->fetchAll();

if ($accion === 'editar') {
    $st = $pdo->prepare('SELECT * FROM alumnos WHERE id = ?');
    $st->execute([(int)($_GET['id'] ?? 0)]);
    $registro = $st->fetch() ?: null;
    if (!$registro) {
        flash('error', 'Ese alumno no existe.');
        redirigir('alumnos.php');
    }
    $accion = 'formulario';
} elseif ($accion === 'nuevo') {
    $registro = ['id' => 0, 'activo' => 1];
    $accion = 'formulario';
}

/* ---------- Listado con filtros ---------- */
if ($accion !== 'formulario') {
    $q = trim((string)($_GET['q'] ?? ''));
    $filtro_grupo = (string)($_GET['grupo'] ?? '');
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = "(a.matricula LIKE ? OR CONCAT(a.nombre, ' ', a.apellidos) LIKE ? OR a.curp LIKE ?)";
        array_push($params, "%$q%", "%$q%", "%$q%");
    }
    if ($filtro_grupo === 'sin') {
        $where[] = 'a.grupo_id IS NULL';
    } elseif ((int)$filtro_grupo > 0) {
        $where[] = 'a.grupo_id = ?';
        $params[] = (int)$filtro_grupo;
    }
    $sql = 'SELECT a.*, g.nombre AS grupo FROM alumnos a LEFT JOIN grupos g ON g.id = a.grupo_id'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY a.apellidos, a.nombre LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $alumnos = $st->fetchAll();
}

require __DIR__ . '/includes/header.php';
?>

<?php if ($accion === 'formulario'): ?>
  <div class="encabezado">
    <h1><?= $registro['id'] ? 'Editar alumno' : 'Nuevo alumno' ?></h1>
    <a class="boton boton-secundario" href="alumnos.php">Volver a la lista</a>
  </div>
  <?php foreach ($errores as $err): ?><div class="aviso aviso-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" class="panel formulario">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int)$registro['id'] ?>">
    <div class="campo"><label for="matricula">Matrícula *</label>
      <input id="matricula" name="matricula" maxlength="20" required value="<?= e($registro['matricula'] ?? '') ?>"></div>
    <div class="campo"><label for="nombre">Nombre(s) *</label>
      <input id="nombre" name="nombre" maxlength="80" required value="<?= e($registro['nombre'] ?? '') ?>"></div>
    <div class="campo"><label for="apellidos">Apellidos *</label>
      <input id="apellidos" name="apellidos" maxlength="100" required value="<?= e($registro['apellidos'] ?? '') ?>"></div>
    <div class="campo"><label for="curp">CURP</label>
      <input id="curp" name="curp" maxlength="18" value="<?= e($registro['curp'] ?? '') ?>"></div>
    <div class="campo"><label for="fecha_nacimiento">Fecha de nacimiento</label>
      <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" value="<?= e($registro['fecha_nacimiento'] ?? '') ?>"></div>
    <div class="campo"><label for="sexo">Sexo</label>
      <select id="sexo" name="sexo">
        <option value="">Sin especificar</option>
        <option value="H"<?= ($registro['sexo'] ?? '') === 'H' ? ' selected' : '' ?>>Hombre</option>
        <option value="M"<?= ($registro['sexo'] ?? '') === 'M' ? ' selected' : '' ?>>Mujer</option>
      </select></div>
    <div class="campo"><label for="grupo_id">Grupo</label>
      <select id="grupo_id" name="grupo_id">
        <option value="">Sin grupo</option>
        <?= opciones($grupos, $registro['grupo_id'] ?? '') ?>
      </select></div>
    <div class="campo"><label for="tutor">Madre, padre o tutor</label>
      <input id="tutor" name="tutor" maxlength="150" value="<?= e($registro['tutor'] ?? '') ?>"></div>
    <div class="campo"><label for="telefono">Teléfono</label>
      <input id="telefono" name="telefono" type="tel" maxlength="20" value="<?= e($registro['telefono'] ?? '') ?>"></div>
    <div class="campo"><label for="email">Correo</label>
      <input id="email" name="email" type="email" maxlength="150" value="<?= e($registro['email'] ?? '') ?>"></div>
    <div class="campo campo-check">
      <label><input type="checkbox" name="activo" value="1"<?= !empty($registro['activo']) ? ' checked' : '' ?>> Alumno activo</label>
    </div>
    <div class="acciones-form">
      <button class="boton" type="submit"><?= $registro['id'] ? 'Guardar cambios' : 'Registrar alumno' ?></button>
      <a class="boton boton-secundario" href="alumnos.php">Cancelar</a>
    </div>
  </form>

<?php else: ?>
  <div class="encabezado">
    <h1>Alumnos</h1>
    <a class="boton" href="alumnos.php?accion=nuevo">Registrar alumno</a>
  </div>

  <form class="filtros" method="get" role="search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, matrícula o CURP" aria-label="Buscar alumno">
    <select name="grupo" aria-label="Filtrar por grupo">
      <option value="">Todos los grupos</option>
      <option value="sin"<?= $filtro_grupo === 'sin' ? ' selected' : '' ?>>Sin grupo</option>
      <?= opciones($grupos, $filtro_grupo) ?>
    </select>
    <button class="boton boton-secundario" type="submit">Buscar</button>
  </form>

  <?php if (!$alumnos): ?>
    <div class="panel vacio">No se encontraron alumnos. Prueba con otra búsqueda o registra uno nuevo.</div>
  <?php else: ?>
  <div class="tabla-envoltura">
    <table>
      <thead><tr><th>Matrícula</th><th>Nombre</th><th>Grupo</th><th>Tutor</th><th>Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($alumnos as $a): ?>
        <tr>
          <td><?= e($a['matricula']) ?></td>
          <td><?= e($a['apellidos'] . ', ' . $a['nombre']) ?></td>
          <td><?= $a['grupo'] ? e($a['grupo']) : '<span class="tenue">Sin grupo</span>' ?></td>
          <td><?= e($a['tutor']) ?></td>
          <td><?= $a['activo'] ? 'Activo' : '<span class="tenue">Baja</span>' ?></td>
          <td class="acciones">
            <a href="boleta.php?alumno=<?= (int)$a['id'] ?>">Boleta</a>
            <a href="alumnos.php?accion=editar&amp;id=<?= (int)$a['id'] ?>">Editar</a>
            <form method="post" onsubmit="return confirm('¿Eliminar a este alumno? También se borrarán sus calificaciones y asistencias. Si solo dejó la escuela, mejor márcalo como baja.');">
              <?= csrf_campo() ?>
              <input type="hidden" name="accion" value="eliminar">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <button class="enlace-peligro" type="submit">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="tenue"><?= count($alumnos) ?> resultado(s)</p>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
