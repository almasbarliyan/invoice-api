# Laravel RESTful API - Invoice Management

A simple RESTful API for managing invoices

---

## Tech Stack

- PHP 8.2
- Laravel 12
- Laravel Sanctum (API token auth)
- Laravel Swagger (OpenAPI docs)
- MySQL

---

## Installation

```bash
# Clone this repo
git clone https://github.com/almasbarliyan/invoice-api.git
cd invoice-api

# Install dependencies
composer install

# Copy environment config
cp .env.example .env

# Generate app key
php artisan key:generate

# Configure database in `.env`
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_pass

# Run migrations
php artisan migrate

# (Optional) Seed database for create example Customer 
php artisan db:seed --class=CustomerSeeder

# Serve the app
php artisan serve

# Endpoint 
Please read the swagger documentation at http://localhost:8000/api/documentation
