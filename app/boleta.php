<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/funciones.php';
requerir_admin();

$pdo = db();
$titulo = 'Boletas';
$seccion = 'boleta';
$alumno_id = (int)($_GET['alumno'] ?? 0);

$lista = $pdo->query("SELECT a.id, CONCAT(a.apellidos, ', ', a.nombre, ' (', a.matricula, ')') AS nombre,
                             COALESCE(g.nombre, 'Sin grupo') AS grupo
                      FROM alumnos a LEFT JOIN grupos g ON g.id = a.grupo_id
                      WHERE a.activo = 1 ORDER BY g.nombre, a.apellidos, a.nombre")->fetchAll();
$por_grupo = [];
foreach ($lista as $fila) {
    $por_grupo[$fila['grupo']][] = $fila;
}

$alumno = null;
if ($alumno_id) {
    $st = $pdo->prepare('SELECT a.*, g.nombre AS grupo, g.ciclo, g.turno FROM alumnos a
                         LEFT JOIN grupos g ON g.id = a.grupo_id WHERE a.id = ?');
    $st->execute([$alumno_id]);
    $alumno = $st->fetch() ?: null;
}

if ($alumno) {
    $materias = [];
    if ($alumno['grupo_id']) {
        $st = $pdo->prepare("SELECT c.id, m.nombre AS materia, CONCAT(ma.nombre, ' ', ma.apellidos) AS maestro
                             FROM clases c JOIN materias m ON m.id = c.materia_id
                             LEFT JOIN maestros ma ON ma.id = c.maestro_id
                             WHERE c.grupo_id = ? ORDER BY m.nombre");
        $st->execute([(int)$alumno['grupo_id']]);
        $materias = $st->fetchAll();
    }

    $st = $pdo->prepare('SELECT clase_id, parcial, calificacion FROM calificaciones WHERE alumno_id = ?');
    $st->execute([$alumno_id]);
    $notas = [];
    foreach ($st as $f) {
        $notas[(int)$f['clase_id']][(int)$f['parcial']] = (float)$f['calificacion'];
    }

    $st = $pdo->prepare("SELECT clase_id, COUNT(*) AS total, SUM(estado = 'F') AS faltas
                         FROM asistencias WHERE alumno_id = ? GROUP BY clase_id");
    $st->execute([$alumno_id]);
    $asis = [];
    foreach ($st as $f) {
        $asis[(int)$f['clase_id']] = $f;
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="encabezado no-imprimir">
  <h1>Boletas</h1>
  <form method="get" class="selector-clase">
    <label for="alumno" class="solo-lectores">Alumno</label>
    <select id="alumno" name="alumno" onchange="this.form.submit()">
      <option value="">Elige un alumno</option>
      <?php foreach ($por_grupo as $grupo => $filas): ?>
        <optgroup label="<?= e($grupo) ?>"><?= opciones($filas, $alumno_id) ?></optgroup>
      <?php endforeach; ?>
    </select>
    <?php if ($alumno): ?><button type="button" class="boton" onclick="window.print()">Imprimir boleta</button><?php endif; ?>
  </form>
</div>

<?php if (!$alumno): ?>
  <div class="panel vacio">Elige un alumno para ver su boleta.</div>
<?php else: ?>
  <article class="panel boleta">
    <header class="boleta-encabezado">
      <div>
        <p class="tenue"><?= e(NOMBRE_ESCUELA) ?> | Boleta de calificaciones</p>
        <h2><?= e($alumno['nombre'] . ' ' . $alumno['apellidos']) ?></h2>
      </div>
      <dl>
        <div><dt>Matrícula</dt><dd><?= e($alumno['matricula']) ?></dd></div>
        <div><dt>Grupo</dt><dd><?= e($alumno['grupo'] ?? 'Sin grupo') ?></dd></div>
        <div><dt>Ciclo</dt><dd><?= e($alumno['ciclo'] ?? '') ?></dd></div>
        <?php if ($alumno['curp']): ?><div><dt>CURP</dt><dd><?= e($alumno['curp']) ?></dd></div><?php endif; ?>
      </dl>
    </header>

    <?php if (!$materias): ?>
      <p class="vacio">El alumno no tiene materias porque no está en un grupo con clases asignadas.</p>
    <?php else: ?>
    <div class="tabla-envoltura">
      <table>
        <thead><tr><th>Materia</th><th class="num">P1</th><th class="num">P2</th><th class="num">P3</th><th class="num">Promedio</th><th class="num">Faltas</th><th class="num">Asistencia</th></tr></thead>
        <tbody>
        <?php
        $promedios = [];
        foreach ($materias as $m):
            $cid = (int)$m['id'];
            $mis = $notas[$cid] ?? [];
            $prom = $mis ? array_sum($mis) / count($mis) : null;
            if ($prom !== null) { $promedios[] = $prom; }
            $total = (int)($asis[$cid]['total'] ?? 0);
            $faltas = (int)($asis[$cid]['faltas'] ?? 0);
        ?>
          <tr>
            <td><?= e($m['materia']) ?><br><small class="tenue"><?= e($m['maestro'] ?? 'Sin maestro') ?></small></td>
            <?php foreach ([1, 2, 3] as $p): $v = $mis[$p] ?? null; ?>
              <td class="num<?= $v !== null && $v < CALIF_APROBATORIA ? ' reprobado' : '' ?>"><?= $v === null ? '-' : e(formato_calif($v)) ?></td>
            <?php endforeach; ?>
            <td class="num<?= $prom !== null && $prom < CALIF_APROBATORIA ? ' reprobado' : '' ?>"><strong><?= $prom === null ? '-' : e(formato_calif($prom)) ?></strong></td>
            <td class="num"><?= $faltas ?></td>
            <td class="num"><?= $total ? round(($total - $faltas) / $total * 100) . '%' : '-' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($promedios): $general = array_sum($promedios) / count($promedios); ?>
        <tfoot>
          <tr><th colspan="4">Promedio general</th>
              <th class="num<?= $general < CALIF_APROBATORIA ? ' reprobado' : '' ?>"><?= e(formato_calif($general)) ?></th>
              <th colspan="2"></th></tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
    <?php endif; ?>

    <footer class="firmas">
      <div><span></span>Dirección</div>
      <div><span></span>Madre, padre o tutor</div>
    </footer>
  </article>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
