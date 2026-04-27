# Mebel Backend

Laravel 11 REST API backend for the Mebel furniture platform.

## Requirements

- PHP 8.2+
- Composer
- MySQL 8+

## Setup

```bash
# Install dependencies
composer install

# Copy environment file and configure
cp .env.example .env

# Generate application key
php artisan key:generate

# Create MySQL database
mysql -u root -e "CREATE DATABASE mebel;"

# Run migrations
php artisan migrate

# Seed with demo data
php artisan db:seed

# Start development server (recommended — raises upload limits for /api/upload-image)
composer run serve

# Or, without raised limits (may 422 on larger images if php.ini is still 2M)
php artisan serve
```

The API will be available at `http://localhost:8000/api`. Pass `artisan serve` flags after `--`, e.g. `composer run serve -- --port=8001`.

### Image / file uploads (local PHP)

Homebrew’s default `php` often ships with **2M** / **8M** `upload_max_filesize` / `post_max_size`, so `/api/upload-image` can return **422** before Laravel even validates the file.

**One-time fix (Homebrew, applies to `php` CLI, `php-fpm` if you use the same build):**

```bash
# Adjust the version in the path to match: php -v
cp config/php/99-mebel-uploads.ini "$(brew --prefix)/etc/php/8.4/conf.d/99-mebel-uploads.ini"
```

Then restart the Laravel dev process (`php artisan serve` / your queue / anything using that PHP). Re-run with `php -r "echo ini_get('upload_max_filesize'), PHP_EOL;"` — it should read **10M** (and **12M** for `post_max_size`).

`composer run serve` also passes **-d** flags as a belt-and-braces override; you can use either approach or both. Laravel Sail images already use large limits; Herd/Valet may use a different `php` — use their “Open php.ini” / equivalent and set the same two directives. App file uploads are capped at **10 MB** in validation (`max:10240` KB per route).

## Demo Credentials

- Email: `demo@example.com`
- Password: `demo123`

## API Endpoints

### Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register new admin |
| POST | `/api/auth/login` | Login, returns token |
| POST | `/api/auth/logout` | Logout (auth required) |
| GET | `/api/auth/me` | Get current profile |
| PUT | `/api/auth/me` | Update profile |

### Admin (auth required)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/modes` | List modes + sub-modes |
| GET/POST | `/api/catalog-items` | List / Create |
| GET/PUT/DELETE | `/api/catalog-items/{id}` | Show / Update / Delete |
| GET/POST | `/api/materials` | List / Create |
| GET/PUT/DELETE | `/api/materials/{id}` | Show / Update / Delete |
| GET/POST | `/api/modules` | List / Create |
| GET/PUT/DELETE | `/api/modules/{id}` | Show / Update / Delete |
| GET/POST | `/api/orders` | List / Create |
| GET/PUT | `/api/orders/{id}` | Show / Update status |

### Public (no auth)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/public/{slug}` | Admin info |
| GET | `/api/public/{slug}/catalog` | Active catalog items |
| GET | `/api/public/{slug}/materials` | Active materials |
| GET | `/api/public/{slug}/modules` | Active modules |
| POST | `/api/public/{slug}/orders` | Submit order |

## Authentication

Use Bearer token in the `Authorization` header:

```
Authorization: Bearer <token>
```

Obtain a token via `POST /api/auth/login`.
