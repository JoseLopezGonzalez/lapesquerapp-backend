#!/bin/bash
# Genera la documentación API de PesquerApp con Scribe.
# Uso: ./scripts/generate-docs.sh

set -e

echo "🚀 Generando documentación de PesquerApp API..."

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}📚 Generando documentación con Scribe...${NC}"
php artisan scribe:generate

if [ -f "public/docs/index.html" ] && [ -f "public/docs/openapi.yaml" ]; then
    echo -e "${GREEN}✅ Documentación generada correctamente${NC}"
    echo -e "${GREEN}📄 HTML: public/docs/index.html${NC}"
    echo -e "${GREEN}📋 OpenAPI: public/docs/openapi.yaml${NC}"
    echo -e "${GREEN}📮 Postman: public/docs/collection.json${NC}"
    echo ""
    echo -e "${GREEN}🌐 Para ver la documentación: abrir public/docs/index.html o servir la app y visitar la ruta /docs si está configurada.${NC}"
else
    echo -e "${RED}❌ Error al generar documentación${NC}"
    exit 1
fi

echo -e "${GREEN}✨ Proceso completado.${NC}"
