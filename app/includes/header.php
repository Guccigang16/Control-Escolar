<?php
$u = usuario();
$seccion = $seccion ?? '';
// [archivo, texto, solo administradores, clave de sección]
$menu = [
    ['index.php',          'Inicio',          false, 'inicio'],
    ['alumnos.php',        'Alumnos',         true,  'alumnos'],
    ['maestros.php',       'Maestros',        true,  'maestros'],
    ['grupos.php',         'Grupos',          true,  'grupos'],
    ['materias.php',       'Materias',        true,  'materias'],
    ['clases.php',         'Clases',          true,  'clases'],
    ['calificaciones.php', 'Calificaciones',  false, 'calificaciones'],
    ['asistencia.php',     'Asistencia',      false, 'asistencia'],
    ['boleta.php',         'Boletas',         true,  'boleta'],
];
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo ?? 'Inicio') ?> | Control escolar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap">
<link rel="stylesheet" href="assets/estilos.css">
</head>
<body>
<div class="app">
  <aside class="lateral">
    <div class="marca">
      <span class="escudo" aria-hidden="true">CE</span>
      <div>
        <strong><?= e(NOMBRE_ESCUELA) ?></strong>
        <small>Control escolar</small>
      </div>
    </div>
    <nav aria-label="Principal">
      <?php foreach ($menu as [$archivo, $texto, $soloAdmin, $clave]): ?>
        <?php if (!$soloAdmin || es_admin()): ?>
          <a href="<?= e($archivo) ?>"<?= $clave === $seccion ? ' class="activo" aria-current="page"' : '' ?>><?= e($texto) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <?php if ($u): ?>
    <div class="sesion">
      <span><?= e($u['nombre']) ?></span>
      <small><?= $u['rol'] === 'admin' ? 'Administración' : 'Docente' ?></small>
      <a href="logout.php">Cerrar sesión</a>
    </div>
    <?php endif; ?>
  </aside>
  <main class="contenido">
    <?php foreach (mensajes_flash() as $f): ?>
      <div class="aviso aviso-<?= e($f['tipo']) ?>" role="status"><?= e($f['mensaje']) ?></div>
    <?php endforeach; ?>
