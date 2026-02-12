#!/usr/bin/env bash
# Instala Docker Compose para que Sail funcione en WSL (Ubuntu).
# Ejecutar: sudo bash instalar-docker-compose.sh

set -e

COMPOSE_VERSION="v2.24.5"

echo "▶ Intentando instalar desde repositorios..."
apt-get update -qq
if apt-get install -y docker-compose-plugin 2>/dev/null; then
  echo "▶ Creando wrapper docker-compose para Sail..."
  printf '%s\n' '#!/bin/sh' 'exec docker compose "$@"' > /usr/local/bin/docker-compose
  chmod +x /usr/local/bin/docker-compose
else
  echo "▶ Paquete no disponible; descargando binario oficial..."
  curl -sSL "https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-linux-$(uname -m)" -o /tmp/docker-compose
  chmod +x /tmp/docker-compose
  mv /tmp/docker-compose /usr/local/bin/docker-compose
fi

echo "▶ Comprobando..."
/usr/local/bin/docker-compose version

echo ""
echo "✅ Listo. Ya puedes ejecutar: ./deploy-dev.sh"
