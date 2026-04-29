# ─────────────────────────────────────────────
# Base: PHP 8.4 con Apache sobre Debian Bookworm
# ─────────────────────────────────────────────
FROM php:8.4-apache

ENV DEBIAN_FRONTEND=noninteractive

# ─────────────────────────────────────────────
# 1. Dependencias del sistema
# ─────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    # Java (para Audiveris)
    default-jre \
    # Tesseract OCR (requerido por Audiveris)
    tesseract-ocr \
    tesseract-ocr-eng \
    # Utilidades PHP
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    unzip \
    curl \
    git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ─────────────────────────────────────────────
# 2. Extensiones PHP
# ─────────────────────────────────────────────
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    mbstring \
    zip \
    xml \
    simplexml \
    gd

# ─────────────────────────────────────────────
# 3. Instalar Composer
# ─────────────────────────────────────────────
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ─────────────────────────────────────────────
# 4. Copiar código Laravel
# ─────────────────────────────────────────────
WORKDIR /var/www/html
COPY . .

# ─────────────────────────────────────────────
# 5. Instalar dependencias PHP
# ─────────────────────────────────────────────
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ─────────────────────────────────────────────
# 6. Variables de entorno para Audiveris
#    El .jar y sus librerías están en ./audiveris/app/
# ─────────────────────────────────────────────
ENV AUDIVERIS_JAR=/var/www/html/audiveris/app/audiveris.jar
ENV AUDIVERIS_DIR=/var/www/html/audiveris/app

# ─────────────────────────────────────────────
# 7. Permisos Laravel
# ─────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# ─────────────────────────────────────────────
# 8. Configurar Apache para Laravel
#    El DocumentRoot apunta a /public (estándar Laravel)
# ─────────────────────────────────────────────
RUN a2enmod rewrite

RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]
