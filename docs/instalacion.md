# Guía de instalación

Hay dos formas de instalar el sistema:

- **Opción A:** servidor Linux (Ubuntu 22.04/24.04 o Debian 12) usando los scripts de `scripts/`.
- **Opción B:** computadora con Windows usando XAMPP.

---

## Opción A: Linux con los scripts

### Requisitos
- Ubuntu 22.04/24.04 o Debian 12, recién instalado o sin otro sitio web en el puerto 80.
- Un usuario con permisos de `sudo` y conexión a internet.

### 1. Instalar Git y descargar el proyecto

```bash
sudo apt update
sudo apt install -y git
git clone https://github.com/Guccigang16/Control-Escolar.git
cd Control-Escolar
```

### 2. Ejecutar los scripts en este orden

```bash
sudo bash scripts/instalar_apache.sh
sudo bash scripts/instalar_php.sh
sudo bash scripts/instalar_mariadb.sh
```

El orden importa: PHP necesita que Apache ya exista, y la base de datos se configura al final.

Qué hace cada uno:

| Script | Qué hace |
|---|---|
| `instalar_apache.sh` | Instala Apache, copia el proyecto a `/var/www/control-escolar` y publica solo la carpeta `app/`. |
| `instalar_php.sh` | Instala PHP con PDO MySQL y ajusta la zona horaria a Ciudad de México. |
| `instalar_mariadb.sh` | Instala MariaDB, importa `database/nexus.sql`, crea el usuario `escuela` con una contraseña aleatoria y genera `config/db.php`. |

### 3. Abrir el sistema

En el navegador entra a `http://localhost` (o a `http://IP-DEL-SERVIDOR` desde otra computadora).
Para ver la IP del servidor: `hostname -I`.

### 4. Actualizar después de cambios en GitHub

```bash
cd Control-Escolar
git pull
sudo bash scripts/instalar_apache.sh
```

Esto vuelve a copiar los archivos sin tocar la base de datos ni `config/db.php`.

### Reinstalar la base de datos desde cero

```bash
sudo bash scripts/instalar_mariadb.sh --reinstalar
```

**Cuidado:** borra todos los alumnos, calificaciones y asistencias.

---

## Opción B: Windows con XAMPP

### 1. Instalar XAMPP
Descarga XAMPP 8.2 desde https://www.apachefriends.org e instálalo.
Abre el panel de XAMPP e inicia **Apache** y **MySQL**.

### 2. Copiar el proyecto
Descarga el repositorio (botón verde **Code > Download ZIP** en GitHub), descomprímelo
y copia la carpeta como `control-escolar` dentro de `htdocs` de tu XAMPP
(por ejemplo `C:\xampp\htdocs\control-escolar`).

### 3. Crear la base de datos
Abre el **Shell** desde el panel de XAMPP y ejecuta (ajusta la ruta):

```
mysql -u root -p
```

Escribe la contraseña de root (si no tiene, solo presiona Enter) y luego:

```sql
SOURCE C:/xampp/htdocs/control-escolar/database/nexus.sql;
EXIT;
```

También puedes hacerlo desde phpMyAdmin: pestaña **Importar** > `database/nexus.sql`.

### 4. Configurar la conexión
Copia `config/db.example.php` como `config/db.php` y escribe tu usuario y contraseña:

```php
const DB_USER = 'root';
const DB_PASS = 'tu contraseña, o vacío si no tiene';
```

### 5. Abrir el sistema
Entra a `http://localhost/control-escolar`.

---

## Solución de problemas

| Mensaje | Causa | Solución |
|---|---|---|
| "No se pudo conectar a la base de datos" | Usuario o contraseña incorrectos en `config/db.php`, o MySQL apagado. | Revisa `DB_USER` y `DB_PASS`, y que MySQL esté iniciado. |
| `#1045 Access denied for user 'root'@'localhost' (using password: NO)` | MySQL tiene contraseña y phpMyAdmin intenta entrar sin ella. | En `phpMyAdmin/config.inc.php` usa `auth_type = 'cookie'` para que pida la contraseña. |
| `#1044 Acceso negado para usuario ... a la base de datos` | El usuario no tiene permisos sobre `control_escolar`. | Entra como root y ejecuta `GRANT ALL PRIVILEGES ON control_escolar.* TO 'usuario'@'localhost';` |
| `bash: ...\r: command not found` al correr un script | El archivo `.sh` se guardó con saltos de línea de Windows. | `sudo apt install dos2unix && dos2unix scripts/*.sh` |
| Página en blanco o error 500 en Linux | Error de PHP. | Revisa `sudo tail /var/log/apache2/control_escolar_error.log` |

## Cambiar las contraseñas de prueba

- **Maestros:** inicia sesión como admin, ve a **Maestros > Editar** y escribe una nueva contraseña.
- **Administrador:** registra tu cuenta como maestro con tu correo, cambia su `rol` a `admin` en la
  tabla `usuarios` y desactiva `admin@escuela.mx` poniendo `activo = 0`.
