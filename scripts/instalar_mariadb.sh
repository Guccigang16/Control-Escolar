#!/usr/bin/env bash
# Instala MariaDB, crea la base de datos, un usuario propio para la app
# y genera config/db.php con sus datos.
# Uso: sudo bash scripts/instalar_mariadb.sh
#      sudo bash scripts/instalar_mariadb.sh --reinstalar   (borra y vuelve a crear las tablas)
set -euo pipefail

if [ "$EUID" -ne 0 ]; then
  echo "Ejecuta este script con sudo: sudo bash scripts/instalar_mariadb.sh"
  exit 1
fi

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DESTINO="/var/www/control-escolar"
DB_NAME="control_escolar"
DB_USER="escuela"
DB_PASS="$(openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | head -c 20)"

echo "==> Actualizando la lista de paquetes"
apt update

echo "==> Instalando MariaDB"
apt install -y mariadb-server mariadb-client
systemctl enable mariadb
systemctl start mariadb

# En Linux, root de MariaDB entra por socket desde sudo, sin contraseña
if mysql -e "USE $DB_NAME" 2>/dev/null && [ "${1:-}" != "--reinstalar" ]; then
  echo "==> La base $DB_NAME ya existe: no se toca (usa --reinstalar para recrearla)"
else
  echo "==> Creando la base de datos con database/nexus.sql"
  mysql < "$REPO_DIR/database/nexus.sql"
fi

echo "==> Creando el usuario '$DB_USER' solo con acceso a $DB_NAME"
mysql <<SQL
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "==> Generando config/db.php"
mkdir -p "$DESTINO/config"
sed -e "s/^const DB_USER = .*/const DB_USER = '$DB_USER';/" \
    -e "s/^const DB_PASS = .*/const DB_PASS = '$DB_PASS';/" \
    "$REPO_DIR/config/db.example.php" > "$DESTINO/config/db.php"
chown www-data:www-data "$DESTINO/config/db.php"
chmod 640 "$DESTINO/config/db.php"

echo
mysql -V
echo "Tablas creadas: $(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'")"
echo
echo "Listo. Abre http://localhost (o la IP del servidor) en el navegador."
echo "Cuentas de prueba: admin@escuela.mx / admin123  y  maestro@escuela.mx / maestro123"
echo "Cámbialas antes de usar el sistema con datos reales."
