# Dockerfile for Symfony 7.3
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev \
    mysql-dev \
    nodejs \
    npm \
    supervisor \
    nginx \
    icu-dev \
    oniguruma-dev

# Install PHP extensions required for Symfony 7.3
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    mysqli \
    gd \
    xml \
    intl \
    opcache \
    bcmath \
    mbstring

# Install Redis extension
RUN apk add --no-cache pcre-dev $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del pcre-dev $PHPIZE_DEPS

# Install APCu for improved performance (recommended for Symfony)
# phpize requires autoconf and friends from $PHPIZE_DEPS
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies optimized for Symfony 7.3
RUN composer install --no-scripts --no-autoloader --prefer-dist --optimize-autoloader

# Copy project files
COPY . .

# Set proper permissions for Symfony 7.3
# Ensure required directories exist before applying permissions
RUN mkdir -p /var/www/var /var/www/public \
    && chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www \
    && chmod -R 777 /var/www/var \
    && chmod -R 777 /var/www/public

# Generate optimized autoloader
# Fix git safe.directory for Composer (repo owned by www-data) and use correct APCu flag
RUN git config --global --add safe.directory /var/www \
    && composer dump-autoload --optimize --apcu

# Copy nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy PHP configuration optimized for Symfony 7.3
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
# Link php-fpm pool config to project file for easy dev overrides
RUN ln -sf /var/www/docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Ensure supervisor log directory exists and is writable
RUN mkdir -p /var/log/supervisor \
    && chown -R www-data:www-data /var/log/supervisor \
    && chmod 755 /var/log/supervisor

# Ensure php-fpm log directory exists
RUN mkdir -p /var/log/php-fpm \
    && chown -R www-data:www-data /var/log/php-fpm \
    && chmod 755 /var/log/php-fpm

# Create JWT keys directory for LexikJWTAuthenticationBundle
RUN mkdir -p config/jwt && chown -R www-data:www-data config/jwt

# Install Node.js dependencies and build assets (Symfony 7.3 Asset Mapper)
RUN if [ -f "package.json" ]; then \
        npm install && \
        npm run build; \
    fi

# Symfony 7.3 cache warmup for better performance
RUN php bin/console cache:warmup --env=prod --no-debug || true

# Create necessary Symfony directories
RUN mkdir -p var/cache var/log public/uploads \
    && chown -R www-data:www-data var public/uploads

# Expose port
EXPOSE 80

# Health check for container
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start supervisor in foreground so container stays alive
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
