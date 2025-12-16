# Use official PHP with Apache
FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all your project files into the container
COPY . /var/www/html

# Expose port (Railway uses PORT)
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
