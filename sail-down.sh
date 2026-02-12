#!/usr/bin/env bash
# Para los contenedores Sail.
# Uso: ./sail-down.sh

set -e
cd "$(dirname "$0")"

./vendor/bin/sail down
echo "✅ Contenedores parados."
