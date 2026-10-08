<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/funciones.php';
requerir_login();

$pdo = db();
$titulo = 'Asistencia';
$seccion = 'asistencia';
$ESTADOS = ['P' => 'Presente', 'F' => 'Falta', 'R' => 'Retardo', 'J' => 'Justificada'];

$clases = clases_permitidas($pdo);
$clase_id = (int)($_POST['clase_id'] ?? $_GET['clase'] ?? 0);
$clase = $clase_id ? buscar_clase($clases, $clase_id) : null;
$fecha = (string)($_POST['fecha'] ?? $_GET['fecha'] ?? date('Y-m-d'));
if (!fecha_valida($fecha) || $fecha > date('Y-m-d')) {
    $fecha = date('Y-m-d');
}

if ($clase_id && !$clase) {
    flash('error', 'No tienes acceso a esa clase.');
    redirigir('asistencia.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $clase) {
    $validos = array_map('intval', array_column(alumnos_de_grupo($pdo, (int)$clase['grupo_id']), 'id'));
    $estados = is_array($_POST['estado'] ?? null) ? $_POST['estado'] : [];
    $st = $pdo->prepare('INSERT INTO asistencias (alumno_id, clase_id, fecha, estado) VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE estado = VALUES(estado)');
    $pdo->beginTransaction();
    foreach ($estados as $alumno_id => $estado) {
        $alumno_id = (int)$alumno_id;
        if (in_array($alumno_id, $validos, true) && is_string($estado) && isset($ESTADOS[$estado])) {
            $st->execute([$alumno_id, $clase_id, $fecha, $estado]);
        }
    }
    $pdo->commit();
    flash('ok', 'Lista del ' . fecha_larga($fecha) . ' guardada.');
    redirigir('asistencia.php?clase=' . $clase_id . '&fecha=' . $fecha);
}

if ($clase) {
    $alumnos = alumnos_de_grupo($pdo, (int)$clase['grupo_id']);

    $st = $pdo->prepare('SELECT alumno_id, estado FROM asistencias WHERE clase_id = ? AND fecha = ?');
    $st->execute([$clase_id, $fecha]);
    $del_dia = array_column($st->fetchAll(), 'estado', 'alumno_id');
    $ya_registrada = (bool)$del_dia;

    $st = $pdo->prepare("SELECT alumno_id, SUM(estado = 'F') AS faltas, SUM(estado = 'R') AS retardos
                         FROM asistencias WHERE clase_id = ? GROUP BY alumno_id");
    $st->execute([$clase_id]);
    $totales = [];
    foreach ($st as $fila) {
        $totales[(int)$fila['alumno_id']] = $fila;
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
  <div>
    <h1>Asistencia</h1>
    <?php if ($clase): ?><p class="tenue"><?= e($clase['materia']) ?>, grupo <?= e($clase['grupo']) ?></p><?php endif; ?>
  </div>
  <form method="get" class="selector-clase">
    <label for="clase" class="solo-lectores">Clase</label>
    <select id="clase" name="clase">
      <option value="">Elige una clase</option>
      <?php foreach ($clases as $c): ?>
        <option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === $clase_id ? ' selected' : '' ?>><?= e($c['grupo'] . ' | ' . $c['materia']) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="fecha" class="solo-lectores">Fecha</label>
    <input id="fecha" type="date" name="fecha" value="<?= e($fecha) ?>" max="<?= date('Y-m-d') ?>">
    <button class="boton boton-secundario" type="submit">Abrir lista</button>
  </form>
</div>

<?php if (!$clase): ?>
  <div class="panel vacio"><?= $clases ? 'Elige una clase y una fecha para pasar lista.' : 'No tienes clases asignadas todavía.' ?></div>
<?php elseif (!$alumnos): ?>
  <div class="panel vacio">Este grupo no tiene alumnos activos.</div>
<?php else: ?>
  <p><strong><?= e(fecha_larga($fecha)) ?></strong>
    <?= $ya_registrada ? '<span class="etiqueta">Lista ya registrada, puedes corregirla</span>' : '<span class="etiqueta">Lista nueva: todos inician como presentes</span>' ?></p>
  <form method="post">
    <?= csrf_campo() ?>
    <input type="hidden" name="clase_id" value="<?= $clase_id ?>">
    <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
    <div class="tabla-envoltura">
      <table>
        <thead><tr><th>Alumno</th><th>Estado</th><th class="num">Faltas</th><th class="num">Retardos</th></tr></thead>
        <tbody>
        <?php foreach ($alumnos as $a):
            $aid = (int)$a['id'];
            $actual = $del_dia[$aid] ?? 'P'; ?>
          <tr>
            <td><?= e($a['apellidos'] . ', ' . $a['nombre']) ?></td>
            <td>
              <fieldset class="asistencia-opciones">
                <legend class="solo-lectores">Asistencia de <?= e($a['nombre']) ?></legend>
                <?php foreach ($ESTADOS as $clave => $texto): ?>
                  <label class="opcion opcion-<?= $clave ?>">
                    <input type="radio" name="estado[<?= $aid ?>]" value="<?= $clave ?>"<?= $actual === $clave ? ' checked' : '' ?>>
                    <span><?= $texto ?></span>
                  </label>
                <?php endforeach; ?>
              </fieldset>
            </td>
            <td class="num"><?= (int)($totales[$aid]['faltas'] ?? 0) ?></td>
            <td class="num"><?= (int)($totales[$aid]['retardos'] ?? 0) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button class="boton" type="submit">Guardar lista</button>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
