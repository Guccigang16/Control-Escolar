<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/funciones.php';
requerir_login();

$pdo = db();
$titulo = 'Inicio';
$seccion = 'inicio';
$clases = clases_permitidas($pdo);

if (es_admin()) {
    $cifras = $pdo->query("SELECT
        (SELECT COUNT(*) FROM alumnos  WHERE activo = 1) AS alumnos,
        (SELECT COUNT(*) FROM maestros WHERE activo = 1) AS maestros,
        (SELECT COUNT(*) FROM grupos)   AS grupos,
        (SELECT COUNT(*) FROM materias) AS materias")->fetch();

    $reprobados = $pdo->query("SELECT COUNT(DISTINCT alumno_id) FROM calificaciones
                               WHERE calificacion < " . CALIF_APROBATORIA)->fetchColumn();

    $sin_grupo = $pdo->query('SELECT COUNT(*) FROM alumnos WHERE activo = 1 AND grupo_id IS NULL')->fetchColumn();
    $sin_maestro = $pdo->query('SELECT COUNT(*) FROM clases WHERE maestro_id IS NULL')->fetchColumn();
}

require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
  <div>
    <h1>Hola, <?= e(explode(' ', usuario()['nombre'])[0]) ?></h1>
    <p class="tenue"><?= e(fecha_larga(date('Y-m-d'))) ?></p>
  </div>
</div>

<?php if (es_admin()): ?>
  <section class="cifras" aria-label="Resumen">
    <a class="cifra" href="alumnos.php"><b><?= (int)$cifras['alumnos'] ?></b> alumnos activos</a>
    <a class="cifra" href="maestros.php"><b><?= (int)$cifras['maestros'] ?></b> maestros</a>
    <a class="cifra" href="grupos.php"><b><?= (int)$cifras['grupos'] ?></b> grupos</a>
    <a class="cifra" href="materias.php"><b><?= (int)$cifras['materias'] ?></b> materias</a>
  </section>

  <?php if ($sin_grupo || $sin_maestro || $reprobados): ?>
  <section class="panel pendientes">
    <h2>Para revisar</h2>
    <ul>
      <?php if ($sin_grupo): ?><li><a href="alumnos.php?grupo=sin"><?= (int)$sin_grupo ?> alumno(s) sin grupo asignado</a></li><?php endif; ?>
      <?php if ($sin_maestro): ?><li><a href="clases.php"><?= (int)$sin_maestro ?> clase(s) sin maestro</a></li><?php endif; ?>
      <?php if ($reprobados): ?><li><?= (int)$reprobados ?> alumno(s) con al menos un parcial reprobado</li><?php endif; ?>
    </ul>
  </section>
  <?php endif; ?>
<?php endif; ?>

<section class="panel">
  <h2><?= es_admin() ? 'Clases registradas' : 'Mis clases' ?></h2>
  <?php if (!$clases): ?>
    <p class="vacio"><?= es_admin()
        ? 'Aún no hay clases. Crea grupos y materias, y después asígnalas en Clases.'
        : 'Todavía no tienes clases asignadas. Pide a administración que te asigne una.' ?></p>
  <?php else: ?>
    <div class="tabla-envoltura">
      <table>
        <thead><tr><th>Grupo</th><th>Materia</th><?php if (es_admin()): ?><th>Maestro</th><?php endif; ?><th></th></tr></thead>
        <tbody>
        <?php foreach ($clases as $c): ?>
          <tr>
            <td><?= e($c['grupo']) ?> <span class="etiqueta"><?= e($c['ciclo']) ?></span></td>
            <td><?= e($c['materia']) ?></td>
            <?php if (es_admin()): ?><td><?= $c['maestro'] ? e($c['maestro']) : '<span class="tenue">Sin asignar</span>' ?></td><?php endif; ?>
            <td class="acciones">
              <a href="calificaciones.php?clase=<?= (int)$c['id'] ?>">Calificaciones</a>
              <a href="asistencia.php?clase=<?= (int)$c['id'] ?>">Asistencia</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
