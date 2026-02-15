# Etapa 1: Instalación de dependencias PHP y Composer
FROM php:8.2-cli AS composer

WORKDIR /app

# Copiar archivos necesarios para Composer
COPY composer.json composer.lock ./

# Instalar extensiones necesarias para Composer
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install \
        zip \
        pdo \
        pdo_mysql \
        mbstring \
        gd

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copiar el resto del código del proyecto
COPY . .

# Ejecutar composer install separado para poder depurar
RUN composer install --no-dev --optimize-autoloader --no-interaction || cat /app/composer.json

# Etapa 2: Servidor Apache con PHP
FROM php:8.2-apache

# Instalar extensiones PHP necesarias
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install \
        zip \
        pdo \
        pdo_mysql \
        mbstring \
        gd

# Habilitar mod_rewrite (útil para Laravel)
RUN a2enmod rewrite

# Configurar Apache para que apunte a /public (Laravel)
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Copiar archivos desde la etapa anterior
COPY --from=composer /app /var/www/html

# Instalar Google Chrome para generación de PDFs
RUN apt-get update && apt-get install -y wget gnupg ca-certificates \
    && wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | gpg --dearmor > /usr/share/keyrings/google-chrome.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-chrome.gpg] http://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list \
    && apt-get update && apt-get install -y google-chrome-stable \
    && rm -rf /var/lib/apt/lists/*

# ✅ Instalar fuentes comunes para mejorar tipografía en PDFs
RUN apt-get update && apt-get install -y fonts-dejavu fonts-liberation fonts-freefont-ttf

# (Opcional) Establecer permisos correctos
# RUN chown -R www-data:www-data /var/www/html

EXPOSE 80


