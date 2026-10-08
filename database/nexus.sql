-- =============================================================
--  Sistema de Control Escolar  |  Base de datos
--  Compatible con MariaDB 10.4+ (la que trae XAMPP) y MySQL 8.0+
--  Importar desde phpMyAdmin: pestaña "Importar" > este archivo.
--  ATENCIÓN: borra las tablas si ya existen (úsalo solo para instalar).
-- =============================================================

CREATE DATABASE IF NOT EXISTS control_escolar
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE control_escolar;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS asistencias, calificaciones, clases, alumnos, materias, grupos, maestros, usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- Usuarios que pueden iniciar sesión (administradores y maestros)
CREATE TABLE usuarios (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(120) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  rol        ENUM('admin','maestro') NOT NULL DEFAULT 'maestro',
  activo     TINYINT(1) NOT NULL DEFAULT 1,
  creado_en  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE maestros (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id   INT UNSIGNED NULL UNIQUE,
  nombre       VARCHAR(80)  NOT NULL,
  apellidos    VARCHAR(100) NOT NULL,
  email        VARCHAR(150) NULL,
  telefono     VARCHAR(20)  NULL,
  especialidad VARCHAR(100) NULL,
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_maestro_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE grupos (
  id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(30) NOT NULL,
  grado  TINYINT UNSIGNED NOT NULL,
  turno  ENUM('Matutino','Vespertino') NOT NULL DEFAULT 'Matutino',
  ciclo  VARCHAR(20) NOT NULL,
  UNIQUE KEY uq_grupo_ciclo (nombre, ciclo)
) ENGINE=InnoDB;

CREATE TABLE alumnos (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  matricula        VARCHAR(20)  NOT NULL UNIQUE,
  nombre           VARCHAR(80)  NOT NULL,
  apellidos        VARCHAR(100) NOT NULL,
  curp             CHAR(18)     NULL UNIQUE,
  fecha_nacimiento DATE         NULL,
  sexo             ENUM('H','M') NULL,
  email            VARCHAR(150) NULL,
  telefono         VARCHAR(20)  NULL,
  tutor            VARCHAR(150) NULL,
  grupo_id         INT UNSIGNED NULL,
  activo           TINYINT(1) NOT NULL DEFAULT 1,
  creado_en        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_alumno_apellidos (apellidos),
  CONSTRAINT fk_alumno_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE materias (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave        VARCHAR(15)  NOT NULL UNIQUE,
  nombre       VARCHAR(100) NOT NULL,
  horas_semana TINYINT UNSIGNED NOT NULL DEFAULT 5
) ENGINE=InnoDB;

-- Una "clase" es una materia impartida a un grupo por un maestro
CREATE TABLE clases (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  grupo_id   INT UNSIGNED NOT NULL,
  materia_id INT UNSIGNED NOT NULL,
  maestro_id INT UNSIGNED NULL,
  UNIQUE KEY uq_clase (grupo_id, materia_id),
  CONSTRAINT fk_clase_grupo   FOREIGN KEY (grupo_id)   REFERENCES grupos(id)   ON DELETE RESTRICT,
  CONSTRAINT fk_clase_materia FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clase_maestro FOREIGN KEY (maestro_id) REFERENCES maestros(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE calificaciones (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alumno_id     INT UNSIGNED NOT NULL,
  clase_id      INT UNSIGNED NOT NULL,
  parcial       TINYINT UNSIGNED NOT NULL,
  calificacion  DECIMAL(4,1) NOT NULL,
  actualizado   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_calificacion (alumno_id, clase_id, parcial),
  CONSTRAINT fk_cal_alumno FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE,
  CONSTRAINT fk_cal_clase  FOREIGN KEY (clase_id)  REFERENCES clases(id)  ON DELETE CASCADE,
  CONSTRAINT chk_cal_rango CHECK (calificacion BETWEEN 0 AND 10),
  CONSTRAINT chk_parcial   CHECK (parcial BETWEEN 1 AND 3)
) ENGINE=InnoDB;

-- P = presente, F = falta, R = retardo, J = falta justificada
CREATE TABLE asistencias (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alumno_id INT UNSIGNED NOT NULL,
  clase_id  INT UNSIGNED NOT NULL,
  fecha     DATE NOT NULL,
  estado    ENUM('P','F','R','J') NOT NULL DEFAULT 'P',
  UNIQUE KEY uq_asistencia (alumno_id, clase_id, fecha),
  CONSTRAINT fk_asis_alumno FOREIGN KEY (alumno_id) REFERENCES alumnos(id) ON DELETE CASCADE,
  CONSTRAINT fk_asis_clase  FOREIGN KEY (clase_id)  REFERENCES clases(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================================
--  Datos de ejemplo
--  admin@escuela.mx   / admin123     (administrador)
--  maestro@escuela.mx / maestro123   (maestra Laura Méndez)
--  ¡Cambia estas contraseñas antes de usar el sistema en serio!
-- =============================================================
INSERT INTO usuarios (id, nombre, email, password, rol) VALUES
 (1, 'Administrador', 'admin@escuela.mx',   '$2y$10$cpbWxTMgfvj9hH69FVnf1OGlaBJK5rP5mr2oGI5Rs6y/zuJwn19.W', 'admin'),
 (2, 'Laura Méndez Ruiz', 'maestro@escuela.mx', '$2y$10$12RCQ/4Q4TAlKHZxj/DsbeD9.ZjWxuyFP6Ky8rXq3J5QeR9jxqZlS', 'maestro');

INSERT INTO maestros (id, usuario_id, nombre, apellidos, email, telefono, especialidad) VALUES
 (1, 2,    'Laura', 'Méndez Ruiz',     'maestro@escuela.mx',  '2221234567', 'Matemáticas'),
 (2, NULL, 'Jorge', 'Hernández Pérez', 'jorge.hp@escuela.mx', '2227654321', 'Español e Historia');

INSERT INTO grupos (id, nombre, grado, turno, ciclo) VALUES
 (1, '1° A', 1, 'Matutino',   '2026-2027'),
 (2, '1° B', 1, 'Vespertino', '2026-2027');

INSERT INTO materias (id, clave, nombre, horas_semana) VALUES
 (1, 'MAT1', 'Matemáticas I', 5),
 (2, 'ESP1', 'Español I',     5),
 (3, 'CIE1', 'Ciencias I',    4),
 (4, 'HIS1', 'Historia I',    3);

INSERT INTO clases (id, grupo_id, materia_id, maestro_id) VALUES
 (1, 1, 1, 1), (2, 1, 2, 2), (3, 1, 3, 1), (4, 1, 4, 2),
 (5, 2, 1, 1), (6, 2, 2, 2);

INSERT INTO alumnos (matricula, nombre, apellidos, fecha_nacimiento, sexo, tutor, grupo_id) VALUES
 ('2026A001', 'Sofía',     'García López',     '2014-03-12', 'M', 'María López',      1),
 ('2026A002', 'Diego',     'Martínez Sánchez', '2014-07-25', 'H', 'Roberto Martínez', 1),
 ('2026A003', 'Valentina', 'Ramírez Torres',   '2014-01-30', 'M', 'Ana Torres',       1),
 ('2026A004', 'Mateo',     'Flores Cruz',      '2013-11-08', 'H', 'Luis Flores',      1),
 ('2026A005', 'Regina',    'Morales Vega',     '2014-05-17', 'M', 'Carmen Vega',      1),
 ('2026B001', 'Santiago',  'Jiménez Rojas',    '2014-02-14', 'H', 'Pedro Jiménez',    2),
 ('2026B002', 'Camila',    'Ortiz Navarro',    '2014-09-03', 'M', 'Laura Navarro',    2);

INSERT INTO calificaciones (alumno_id, clase_id, parcial, calificacion) VALUES
 (1, 1, 1, 9.5), (2, 1, 1, 7.0), (3, 1, 1, 8.5), (4, 1, 1, 5.5), (5, 1, 1, 10.0);
