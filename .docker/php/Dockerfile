# 👷‍♀️ 第一阶段：构建阶段，包含所有开发依赖
# T-13: Digest-pinned for reproducible builds. Update via Renovate.
FROM php:8.5-fpm-alpine@sha256:fa01fb1645cd0fc566a5f146b099adace33b906571f972f71f2182a7c12d1cd7 AS builder

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    libxml2-dev \
    libwebp-dev \
    gmp-dev \
    oniguruma-dev \
    openssl-dev \
    linux-headers

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

RUN docker-php-ext-configure ftp --with-ftp-ssl

RUN docker-php-ext-install -j$(nproc) \
    bcmath \
    pdo_mysql \
    gd \
    pcntl \
    sockets \
    gmp \
    zip \
    intl \
    ftp

# 检查 pecl.php.net 可用性
RUN curl -Is https://pecl.php.net | head -n 1 | grep "200" \
    || (echo "❌ pecl.php.net unreachable, aborting build." && exit 1)


# 安装 redis 扩展 (pinned version for reproducibility)
RUN pecl channel-update pecl.php.net \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis

# 👨‍🍳 第二阶段：运行阶段，仅包含必要运行环境
FROM php:8.5-fpm-alpine@sha256:fa01fb1645cd0fc566a5f146b099adace33b906571f972f71f2182a7c12d1cd7

# 复制已构建的扩展
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# 安装运行时所需依赖
RUN apk add --no-cache \
    wget~=1.25 \
    rsync~=3.5 \
    libzip \
    libpng \
    libjpeg-turbo \
    libwebp \
    freetype \
    icu \
    libxml2 \
    gmp \
    oniguruma \
    git~=2.54 \
    mysql-client~=11.8 \
    mariadb-connector-c~=3.4

# Upgrade past the pinned base image: curl/libcurl 8.21.0-r0 has
# CVE-2026-82209/-80229/-80230/-80255/-13608/-18924/-19931,
# nghttp2-libs 1.69.0-r0 has CVE-2026-58055 (fixed in 1.70.0-r0),
# pcre2 10.48-r0 has CVE-2026-103111 (fixed in 10.49-r0),
# zlib 1.3.1.x has CVE-2026-85091 (fixed in 1.3.2-r1+).
RUN apk upgrade --no-cache curl libcurl nghttp2-libs pcre2 zlib

# 配置 www.conf
RUN sed -i \
    -e 's/;catch_workers_output = .*/catch_workers_output = yes/g' \
    -e 's/;php_admin_flag\[log_errors\] = .*/php_admin_flag\[log_errors\] = on/g' \
    -e 's/;php_admin_value\[error_log\] = .*/php_admin_value\[error_log\] = \/var\/www\/html\/storage\/logs\/php_errors.log/g' \
    /usr/local/etc/php-fpm.d/www.conf

# PHP error log -> file readable by the /nexusphp panel (System > PHP Error Log)
RUN echo "error_log = /var/www/html/storage/logs/php_errors.log" >> /usr/local/etc/php/conf.d/error-logging.ini && echo "log_errors = On" >> /usr/local/etc/php/conf.d/error-logging.ini && echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/error-logging.ini
# 上传配置
RUN echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/upload.ini
RUN echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/upload.ini

# Production OPcache defaults (override with --build-arg NEXUSPHP_ENV=dev for development)
ARG NEXUSPHP_ENV=prod
COPY .docker/php/opcache.${NEXUSPHP_ENV}.ini /usr/local/etc/php/conf.d/opcache.ini

# 安装 Composer (with signature verification)
RUN EXPECTED_SIGNATURE="$(curl -sS https://composer.github.io/installer.sig)" \
    && curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")" \
    && if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then \
         echo 'ERROR: Composer installer signature mismatch!' >&2; \
         rm /tmp/composer-setup.php; \
         exit 1; \
       fi \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm /tmp/composer-setup.php

ENV LOG_CHANNEL=stderr
ENV RUNNING_IN_DOCKER=1

COPY .docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
