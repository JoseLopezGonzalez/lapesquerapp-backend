# Requisitos del servidor — Thumbnails de documentos

**Fecha:** 2026-06-25  
**Afecta a:** endpoint `GET /api/v2/orders/{id}/attachments/{id}/thumbnail`  
**Contexto de despliegue:** Coolify → build del [Dockerfile](../../Dockerfile) → imagen `php:8.2-apache`

---

## Resumen

| Tipo de adjunto | Necesita | Sin ello devuelve |
|---|---|---|
| Imágenes (JPEG/PNG/WebP) | PHP GD — ya instalado | — (siempre funciona) |
| PDF | Imagick + GhostScript | HTTP 415 |
| Word / Excel | Imagick + GhostScript + LibreOffice headless | HTTP 415 |

---

## Cambios en el Dockerfile

Todo va en el **Stage 2** (`FROM php:8.2-apache`), que es la imagen que Coolify despliega.

### Bloque mínimo (PDFs únicamente)

```dockerfile
# Thumbnails de PDF: Imagick + GhostScript
RUN apt-get update && apt-get install -y \
        libmagickwand-dev \
        ghostscript \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && find /etc/ImageMagick-* -name policy.xml -exec sed -i \
        's/<policy domain="coder" rights="none" pattern="PDF"/<policy domain="coder" rights="read|write" pattern="PDF"/' \
        {} \; \
    && rm -rf /var/lib/apt/lists/*
```

El `sed` es imprescindible: la política por defecto de ImageMagick bloquea la lectura de PDFs y el thumbnail fallaría con "not authorized" aunque Imagick esté instalado. Se usa `find /etc/ImageMagick-*` en lugar de una ruta fija porque Debian Trixie instala ImageMagick 7 en `/etc/ImageMagick-7/`, no en `/etc/ImageMagick-6/`.

### Bloque completo (PDF + Word + Excel)

```dockerfile
# Thumbnails de documentos: Imagick + GhostScript + LibreOffice headless
RUN apt-get update && apt-get install -y \
        libmagickwand-dev \
        ghostscript \
        libreoffice \
        --no-install-recommends \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && sed -i \
        's/<policy domain="coder" rights="none" pattern="PDF"/<policy domain="coder" rights="read|write" pattern="PDF"/' \
        /etc/ImageMagick-6/policy.xml \
    && rm -rf /var/lib/apt/lists/*

# LibreOffice necesita un HOME escribible; www-data no tiene uno por defecto
ENV HOME=/tmp
```

> **Peso aproximado de la imagen**: LibreOffice añade ~300-400 MB a la imagen final. Si solo se necesitan thumbnails de PDF, usar el bloque mínimo.

---

## Dónde insertar el bloque en el Dockerfile actual

El Dockerfile actual termina el Stage 2 así:

```dockerfile
# ✅ Instalar fuentes comunes para mejorar tipografía en PDFs
RUN apt-get update && apt-get install -y fonts-dejavu fonts-liberation fonts-freefont-ttf

# (Opcional) Establecer permisos correctos
# RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
```

Añadir el bloque **después de las fuentes y antes de `EXPOSE 80`**:

```dockerfile
# ✅ Instalar fuentes comunes para mejorar tipografía en PDFs
RUN apt-get update && apt-get install -y fonts-dejavu fonts-liberation fonts-freefont-ttf

# Thumbnails de PDF: Imagick + GhostScript
RUN apt-get update && apt-get install -y \
        libmagickwand-dev \
        ghostscript \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && sed -i \
        's/<policy domain="coder" rights="none" pattern="PDF"/<policy domain="coder" rights="read|write" pattern="PDF"/' \
        /etc/ImageMagick-6/policy.xml \
    && rm -rf /var/lib/apt/lists/*

EXPOSE 80
```

---

## Verificación post-deploy

Ejecutar desde un shell del contenedor en Coolify (pestaña "Terminal" del servicio):

```bash
# Imagick instalado
php -m | grep imagick

# GhostScript disponible
gs --version

# Política de ImageMagick permite PDFs
grep -i "PDF" /etc/ImageMagick-6/policy.xml
# debe mostrar: rights="read|write"

# LibreOffice (si se instaló)
libreoffice --version

# Test completo: generar thumbnail de un PDF de prueba
php -r "
  \$im = new Imagick();
  \$im->setResolution(150, 150);
  \$im->readImage('/ruta/al/archivo.pdf[0]');
  echo 'OK: ' . \$im->getImageWidth() . 'x' . \$im->getImageHeight();
"
```

---

## Errores conocidos y soluciones

| Error | Causa | Solución |
|---|---|---|
| `ImagickException: not authorized` | Política de ImageMagick bloquea PDFs | El `sed` en el Dockerfile no se ejecutó o el path de policy.xml es distinto (ver nota abajo) |
| `ImagickException: no decode delegate` | GhostScript no instalado | Añadir `ghostscript` al `apt-get install` |
| `Error 415` en thumbnail de Word/Excel | LibreOffice no instalado | Usar el bloque completo o asumir que no se generan thumbnails de Office |
| LibreOffice falla silenciosamente | `HOME` no existe o no es escribible para `www-data` | Añadir `ENV HOME=/tmp` al Dockerfile |

### Nota: path de policy.xml

La imagen `php:8.2-apache` usa Debian, donde el archivo es `/etc/ImageMagick-6/policy.xml`. Si en algún momento se cambia la imagen base a una variante distinta (Alpine, etc.) el path puede ser diferente. Verificar con:

```bash
find /etc -name "policy.xml" 2>/dev/null
```
