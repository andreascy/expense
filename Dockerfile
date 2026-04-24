FROM php:8.2-apache

# Install extensions
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set document root
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Copy application
COPY . /var/www/html/

# Fix permissions for uploads
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod 755 /var/www/html/uploads

# Allow .htaccess overrides
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Pass Docker env vars into PHP via Apache
RUN echo 'PassEnv SAP_BASE_URL SAP_COMPANY_DB SAP_USERNAME SAP_PASSWORD SAP_VERIFY_SSL\n\
PassEnv DB_DRIVER DB_HOST DB_NAME DB_USER DB_PASSWORD\n\
PassEnv APP_CURRENCY APP_VAT_RATE APP_COMPANY' \
    > /etc/apache2/conf-enabled/passenv.conf

EXPOSE 80
