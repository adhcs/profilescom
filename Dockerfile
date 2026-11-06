# syntax=docker/dockerfile:1
FROM php:8.4-fpm-alpine

# Install nginx + supervisor + curl (php image has extension curl compiled-in in many cases, but keep runtime tools)
RUN apk add --no-cache nginx supervisor curl curl-dev ca-certificates tzdata

# Ensure php extensions
RUN docker-php-ext-install opcache curl

# Configure nginx
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/site.conf /etc/nginx/http.d/default.conf

# Configure supervisor to run both processes
COPY docker/supervisord.conf /etc/supervisord.conf

# Copy app
COPY app/ /var/www/html/

# Writable storage
RUN mkdir -p /var/www/html/storage/logs &&     chown -R www-data:www-data /var/www/html/storage

# PHP runtime limits (uploads/batch)
RUN {   echo 'memory_limit=256M';   echo 'max_execution_time=300';   echo 'max_input_time=300';   echo 'upload_max_filesize=20M';   echo 'post_max_size=25M'; } > /usr/local/etc/php/conf.d/ohip.ini

EXPOSE 8080

CMD ["/usr/bin/supervisord","-c","/etc/supervisord.conf"]
