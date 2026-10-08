#!/usr/bin/env bash
# Instala Apache y publica el Sistema de Control Escolar.
# Uso: sudo bash scripts/instalar_apache.sh
# Probado para Ubuntu 22.04 / 24.04 y Debian 12.
set -euo pipefail

if [ "$EUID" -ne 0 ]; then
  echo "Ejecuta este script con sudo: sudo bash scripts/instalar_apache.sh"
  exit 1
fi

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DESTINO="/var/www/control-escolar"

echo "==> Actualizando la lista de paquetes"
apt update

echo "==> Instalando Apache"
apt install -y apache2

echo "==> Copiando el proyecto a $DESTINO"
mkdir -p "$DESTINO"
cp -r "$REPO_DIR/app" "$REPO_DIR/config" "$DESTINO/"
chown -R www-data:www-data "$DESTINO"
find "$DESTINO" -type d -exec chmod 755 {} \;
find "$DESTINO" -type f -exec chmod 644 {} \;

echo "==> Configurando el sitio"
cp "$REPO_DIR/config/apache-control-escolar.conf" /etc/apache2/sites-available/control-escolar.conf
a2enmod rewrite
a2dissite 000-default.conf || true
a2ensite control-escolar.conf

echo "==> Iniciando Apache"
systemctl enable apache2
systemctl restart apache2

if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
  echo "==> Abriendo el puerto 80 en el firewall"
  ufw allow 'Apache'
fi

echo
echo "Listo. Apache $(apache2 -v | head -1 | cut -d/ -f2 | cut -d' ' -f1) está activo."
echo "Siguiente paso: sudo bash scripts/instalar_php.sh"
