<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/funciones.php';
requerir_login();

$pdo = db();
$titulo = 'Calificaciones';
$seccion = 'calificaciones';
$clases = clases_permitidas($pdo);
$clase_id = (int)($_POST['clase_id'] ?? $_GET['clase'] ?? 0);
$clase = $clase_id ? buscar_clase($clases, $clase_id) : null;

if ($clase_id && !$clase) {
    flash('error', 'No tienes acceso a esa clase.');
    redirigir('calificaciones.php');
}

/* ---------- Guardar ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $clase) {
    $alumnos_validos = array_map('intval', array_column(alumnos_de_grupo($pdo, (int)$clase['grupo_id']), 'id'));
    $capturadas = is_array($_POST['cal'] ?? null) ? $_POST['cal'] : [];

    $upsert = $pdo->prepare('INSERT INTO calificaciones (alumno_id, clase_id, parcial, calificacion) VALUES (?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion)');
    $borrar = $pdo->prepare('DELETE FROM calificaciones WHERE alumno_id = ? AND clase_id = ? AND parcial = ?');
    $invalidas = 0;

    $pdo->beginTransaction();
    foreach ($capturadas as $alumno_id => $parciales) {
        $alumno_id = (int)$alumno_id;
        if (!in_array($alumno_id, $alumnos_validos, true) || !is_array($parciales)) {
            continue;
        }
        foreach ([1, 2, 3] as $p) {
            $valor = str_replace(',', '.', trim((string)($parciales[$p] ?? '')));
            if ($valor === '') {
                $borrar->execute([$alumno_id, $clase_id, $p]);
            } elseif (is_numeric($valor) && (float)$valor >= 0 && (float)$valor <= 10) {
                $upsert->execute([$alumno_id, $clase_id, $p, round((float)$valor, 1)]);
            } else {
                $invalidas++;
            }
        }
    }
    $pdo->commit();

    if ($invalidas) {
        flash('error', "Se guardó lo demás, pero $invalidas calificación(es) no eran números entre 0 y 10 y se ignoraron.");
    } else {
        flash('ok', 'Calificaciones guardadas.');
    }
    redirigir('calificaciones.php?clase=' . $clase_id);
}

/* ---------- Cargar datos ---------- */
if ($clase) {
    $alumnos = alumnos_de_grupo($pdo, (int)$clase['grupo_id']);
    $st = $pdo->prepare('SELECT alumno_id, parcial, calificacion FROM calificaciones WHERE clase_id = ?');
    $st->execute([$clase_id]);
    $notas = [];
    foreach ($st as $fila) {
        $notas[(int)$fila['alumno_id']][(int)$fila['parcial']] = (float)$fila['calificacion'];
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
  <div>
    <h1>Calificaciones</h1>
    <?php if ($clase): ?><p class="tenue"><?= e($clase['materia']) ?>, grupo <?= e($clase['grupo']) ?> (<?= e($clase['ciclo']) ?>)</p><?php endif; ?>
  </div>
  <form method="get" class="selector-clase">
    <label for="clase" class="solo-lectores">Clase</label>
    <select id="clase" name="clase" onchange="this.form.submit()">
      <option value="">Elige una clase</option>
      <?php foreach ($clases as $c): ?>
        <option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === $clase_id ? ' selected' : '' ?>><?= e($c['grupo'] . ' | ' . $c['materia']) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="boton boton-secundario">Ver</button></noscript>
  </form>
</div>

<?php if (!$clase): ?>
  <div class="panel vacio"><?= $clases ? 'Elige una clase para capturar sus calificaciones.' : 'No tienes clases asignadas todavía.' ?></div>
<?php elseif (!$alumnos): ?>
  <div class="panel vacio">Este grupo no tiene alumnos activos. <?php if (es_admin()): ?><a href="alumnos.php">Asigna alumnos al grupo</a>.<?php endif; ?></div>
<?php else: ?>
  <form method="post">
    <?= csrf_campo() ?>
    <input type="hidden" name="clase_id" value="<?= $clase_id ?>">
    <div class="tabla-envoltura">
      <table class="tabla-captura">
        <thead><tr><th>Alumno</th><th class="num">Parcial 1</th><th class="num">Parcial 2</th><th class="num">Parcial 3</th><th class="num">Promedio</th></tr></thead>
        <tbody>
        <?php foreach ($alumnos as $a):
            $aid = (int)$a['id'];
            $mis = $notas[$aid] ?? [];
            $prom = $mis ? array_sum($mis) / count($mis) : null; ?>
          <tr>
            <td><?= e($a['apellidos'] . ', ' . $a['nombre']) ?> <span class="tenue"><?= e($a['matricula']) ?></span></td>
            <?php foreach ([1, 2, 3] as $p): $v = $mis[$p] ?? null; ?>
              <td class="num">
                <input class="cal<?= $v !== null && $v < CALIF_APROBATORIA ? ' reprobado' : '' ?>" name="cal[<?= $aid ?>][<?= $p ?>]"
                       inputmode="decimal" maxlength="4" value="<?= e(formato_calif($v)) ?>"
                       aria-label="Parcial <?= $p ?> de <?= e($a['nombre'] . ' ' . $a['apellidos']) ?>">
              </td>
            <?php endforeach; ?>
            <td class="num<?= $prom !== null && $prom < CALIF_APROBATORIA ? ' reprobado' : '' ?>"><strong><?= e(formato_calif($prom)) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="tenue">Escala de 0 a 10. Mínima aprobatoria: <?= number_format(CALIF_APROBATORIA, 1) ?>. Deja una casilla vacía para borrar esa calificación.</p>
    <button class="boton" type="submit">Guardar calificaciones</button>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
