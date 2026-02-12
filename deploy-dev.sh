#!/usr/bin/env bash
# Deploy en desarrollo – Ejecutar cuando Docker Desktop esté en marcha.
# Uso: ./deploy-dev.sh   (desde la raíz del proyecto)

set -e
cd "$(dirname "$0")"

echo "▶ Comprobando Docker..."
if ! docker info &>/dev/null; then
  echo "❌ Docker no está en ejecución. Abre Docker Desktop y vuelve a ejecutar: ./deploy-dev.sh"
  exit 1
fi

echo "▶ Levantando contenedores Sail..."
./vendor/bin/sail up -d

echo "▶ Esperando a que MySQL esté listo..."
sleep 10

echo "▶ Migraciones base central..."
./vendor/bin/sail artisan migrate --force

echo "▶ Creando base de datos del tenant (pesquerapp_dev)..."
./vendor/bin/sail mysql -e "CREATE DATABASE IF NOT EXISTS pesquerapp_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
echo "▶ Dando permisos a sail sobre pesquerapp_dev..."
MYSQL_CID=$(docker ps -q -f name=mysql 2>/dev/null | head -1)
if [ -n "$MYSQL_CID" ]; then
  docker exec "$MYSQL_CID" mysql -u root -ppassword -e "GRANT ALL PRIVILEGES ON pesquerapp_dev.* TO 'sail'@'%'; FLUSH PRIVILEGES;" 2>/dev/null || true
fi

echo "▶ Registrando tenant de desarrollo..."
./vendor/bin/sail mysql < insert-tenant-dev.sql 2>/dev/null || true

echo "▶ Migrando y sembrando tenant..."
./vendor/bin/sail artisan tenants:migrate --seed

echo "▶ Comprobando health (con tenant dev)..."
PORT="${APP_PORT:-8000}"
curl -sf -H "X-Tenant: dev" "http://localhost:${PORT}/api/health" && echo "" || echo "⚠ Health no respondió. Prueba: curl -H \"X-Tenant: dev\" http://localhost:${PORT}/api/health"

echo ""
echo "✅ Deploy en desarrollo listo."
echo "   Backend:  http://localhost:${PORT}"
echo "   Mailpit:  http://localhost:8025"
echo "   Health:   curl http://localhost:${PORT}/api/health"
