<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

---

## Docker Setup Guide

This project runs with Docker. No local PHP, Composer, or MySQL installation required.

### Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (includes Docker Compose)

### Services

| Service | URL | Notes |
|---------|-----|-------|
| Laravel app | http://localhost:8080 | Main application |
| phpMyAdmin | http://localhost:8081 | Database UI |
| MySQL | `localhost:3306` | Direct DB access |

**Database credentials**

| | Value |
|-|-------|
| Host | `db` (inside Docker) / `localhost` (from your machine) |
| Database | `laravel` |
| Username | `laravel` |
| Password | `secret` |
| Root password | `rootsecret` |

---

### Quick start (first time)

```bash
# 1. Clone and enter the project
git clone <repo-url> webapp
cd webapp

# 2. Copy environment file
cp .env.example .env

# 3. Start everything with one command
./start.sh
```

`start.sh` builds the images, starts all containers, and runs database migrations automatically.

Open http://localhost:8080 — you should see the Laravel welcome page.

---

### Start / stop

```bash
# Start all containers (after first setup)
docker compose up -d

# Stop all containers (data is preserved)
docker compose down

# Stop and delete all data (fresh start)
docker compose down -v
```

---

### Running Artisan commands

All `php artisan` commands run inside the `app` container:

```bash
# General form
docker compose exec app php artisan <command>

# Examples
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan make:model Post -m
docker compose exec app php artisan make:controller PostController --resource
docker compose exec app php artisan route:list
docker compose exec app php artisan tinker
```

---

### Installing Composer packages

```bash
docker compose exec app composer require <package-name>

# Example
docker compose exec app composer require spatie/laravel-permission
```

---

### Viewing logs

```bash
# All containers
docker compose logs -f

# App only (PHP-FPM errors)
docker compose logs -f app

# Nginx access/error log
docker compose logs -f web

# MySQL log
docker compose logs -f db
```

---

### Project structure

```
webapp/
├── docker/
│   ├── php/
│   │   └── Dockerfile        # PHP 8.4-fpm-alpine + extensions
│   └── nginx/
│       └── default.conf      # Nginx → PHP-FPM config
├── docker-compose.yml        # Defines app, web, db, phpmyadmin
├── start.sh                  # One-command startup script
├── .env                      # Environment variables (DB, app key…)
└── (standard Laravel files…)
```

---

### Rebuilding after Dockerfile changes

```bash
docker compose up -d --build
```

---

### Troubleshooting

**Port already in use**

If `8080` or `3306` is taken, edit `docker-compose.yml` and change the left-hand port number:
```yaml
ports:
  - "9090:80"   # change 8080 → 9090
```

**Permission errors on storage/**

```bash
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

**Migration errors / DB not ready**

```bash
# Check DB health
docker compose ps

# Re-run migrations manually
docker compose exec app php artisan migrate --force
```

**Clear all caches**

```bash
docker compose exec app php artisan optimize:clear
```

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
