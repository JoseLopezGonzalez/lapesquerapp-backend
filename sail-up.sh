#!/usr/bin/env bash
# Solo levanta los contenedores Sail (cuando ya desplegaste antes).
# Uso: ./sail-up.sh

set -e
cd "$(dirname "$0")"

if ! docker info &>/dev/null; then
  echo "❌ Docker no está en ejecución. Inicia Docker y vuelve a ejecutar."
  exit 1
fi

./vendor/bin/sail up -d
echo ""
echo "✅ Contenedores en marcha."
echo "   Backend:  http://localhost:${APP_PORT:-8000}"
echo "   Mailpit:  http://localhost:8025"
echo "   Health:   curl -H \"X-Tenant: dev\" http://localhost:${APP_PORT:-8000}/api/health"
