#!/usr/bin/env bash
# Instala PHP y las extensiones que necesita el sistema.
# Uso: sudo bash scripts/instalar_php.sh   (después de instalar_apache.sh)
set -euo pipefail

if [ "$EUID" -ne 0 ]; then
  echo "Ejecuta este script con sudo: sudo bash scripts/instalar_php.sh"
  exit 1
fi

echo "==> Actualizando la lista de paquetes"
apt update

echo "==> Instalando PHP, el módulo de Apache y las extensiones"
# php-mysql incluye PDO MySQL, que es lo que usa la aplicación
apt install -y php libapache2-mod-php php-mysql php-mbstring php-xml

echo "==> Ajustando la zona horaria de PHP"
VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
INI="/etc/php/$VERSION/apache2/php.ini"
if [ -f "$INI" ]; then
  sed -i 's#^;\?date.timezone =.*#date.timezone = America/Mexico_City#' "$INI"
  sed -i 's#^expose_php = .*#expose_php = Off#' "$INI"
fi

echo "==> Reiniciando Apache"
systemctl restart apache2

echo
php -v | head -1
echo "Extensión PDO MySQL: $(php -m | grep -qi pdo_mysql && echo instalada || echo FALTA)"
echo "Siguiente paso: sudo bash scripts/instalar_mariadb.sh"
