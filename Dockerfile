FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite
RUN a2enmod rewrite

# Change Apache to listen on Railway $PORT
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf \
 && sed -i 's/:80/:${PORT}/g' /etc/apache2/sites-enabled/000-default.conf

WORKDIR /var/www/html
COPY . /var/www/html

# Railway sets PORT automatically
EXPOSE ${PORT}

CMD ["apache2-foreground"]
