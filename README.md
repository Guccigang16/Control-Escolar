# Sistema de Control Escolar

Aplicación web para administrar una escuela: alumnos, maestros, grupos, materias,
calificaciones por parcial, pase de lista y boletas imprimibles.

Hecha con **PHP 8**, **MariaDB/MySQL** y **Apache**. Funciona en Linux (Ubuntu/Debian)
con los scripts incluidos, o en Windows con XAMPP.

## Funciones

- **Administración:** altas, bajas y cambios de alumnos, maestros, grupos y materias.
- **Clases:** asignar qué maestro imparte cada materia a cada grupo.
- **Calificaciones:** captura de 3 parciales con promedio y reprobados resaltados.
- **Asistencia:** pase de lista diario (presente, falta, retardo, justificada).
- **Boletas:** por alumno, con promedio general y porcentaje de asistencia, lista para imprimir.
- **Roles:** administración ve todo; cada maestro solo trabaja con sus clases.

## Estructura del repositorio

```
README.md
app/                      Archivos del sistema (lo único publicado en el servidor web)
├── index.php, login.php, alumnos.php, maestros.php, grupos.php,
│   materias.php, clases.php, calificaciones.php, asistencia.php, boleta.php
├── includes/             Funciones comunes y plantillas
└── assets/estilos.css
scripts/
├── instalar_apache.sh    Instala Apache y publica la app
├── instalar_php.sh       Instala PHP y sus extensiones
└── instalar_mariadb.sh   Instala MariaDB, crea la base y genera config/db.php
database/
└── nexus.sql             Estructura de la base de datos y datos de ejemplo
config/
├── db.example.php        Plantilla de conexión a la base de datos
└── apache-control-escolar.conf   Configuración del sitio en Apache
docs/
└── instalacion.md        Guía de instalación paso a paso
```

## Instalación rápida (Ubuntu / Debian)

```bash
git clone https://github.com/Guccigang16/Control-Escolar.git
cd Control-Escolar
sudo bash scripts/instalar_apache.sh
sudo bash scripts/instalar_php.sh
sudo bash scripts/instalar_mariadb.sh
```

Después abre `http://localhost` o la IP del servidor.

Para Windows con XAMPP y para resolver problemas, consulta [docs/instalacion.md](docs/instalacion.md).

## Cuentas de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| Administración | admin@escuela.mx | admin123 |
| Maestra | maestro@escuela.mx | maestro123 |

Cámbialas antes de usar el sistema con datos reales.

## Versiones recomendadas

| Componente | Linux (scripts) | Windows (XAMPP 8.2) |
|---|---|---|
| PHP | 8.2 o superior | 8.2 |
| Base de datos | MariaDB 10.6 o superior | MariaDB 10.4 |
| Servidor web | Apache 2.4 | Apache 2.4 |

## Seguridad

Consultas preparadas con PDO, contraseñas cifradas con `password_hash`, tokens CSRF en todos
los formularios, escape de HTML, bloqueo temporal tras 5 intentos fallidos de inicio de sesión,
y la configuración con contraseñas fuera de la carpeta pública y fuera de GitHub.
