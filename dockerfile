# Usamos la imagen oficial de FrankenPHP basada en PHP 8.3
FROM dunglas/frankenphp:1-php8.3 AS runner

# 1. Variables de entorno vitales para Octane y límites de subida
ENV SERVER_NAME=":80"
ENV FRANKENPHP_MAX_REQUEST_BODY_SIZE=104857600
ENV MAX_UPLOAD_FILESIZE=100M

# 2. Instalamos las extensiones de PHP necesarias para Laravel, Redis y Octane
# (La imagen de FrankenPHP trae un script mágico para instalar extensiones fácilmente)
RUN install-php-extensions \
    pdo_mysql \
    gd \
    intl \
    zip \
    bcmath \
    pcntl \
    redis \
    opcache \
    exif \
    sockets

# 3. Definimos el directorio de trabajo dentro del contenedor
WORKDIR /app

# 4. Traemos Composer desde su imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Forzamos nuestra configuración de PHP para aceptar archivos grandes (modelos 3D)
RUN echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "variables_order = EGPCS" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini

# 6. Copiamos solo los archivos de dependencias primero para aprovechar la caché de Docker
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-autoloader

# 7. Copiamos el resto del código fuente de tu API
COPY . .

# 8. Generamos el autoloader optimizado de Composer y creamos carpetas necesarias
RUN composer dump-autoload --optimize && \
    mkdir -p storage/logs bootstrap/cache storage/framework/views storage/framework/cache storage/framework/sessions

# 9. Ajustamos los permisos para que el servidor web pueda escribir en storage y cache
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# 10. Exponemos el puerto 80
EXPOSE 80

# 11. El comando por defecto encenderá Octane con FrankenPHP
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=80"]