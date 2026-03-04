# Athar Back-END

Backend API for the Athar platform, built with **Laravel 12** and **PHP 8.2**. This service exposes REST APIs for authentication, locations/places, accessibility contributions, help requests, notifications, privacy controls, and admin operations.

## Table of Contents

- Overview
- Tech Stack
- Requirements
- Getting Started (Local Setup)
- Environment Configuration
- Database Setup (SQLite / MySQL)
- Running the App
- Background Jobs / Queue
- API Authentication (Sanctum)
- Postman Collections
- Useful Artisan Commands
- Testing
- Deployment Notes
- License

## Overview

Athar is focused on improving accessibility and support through:

- Locations/Places discovery and management
- Accessibility contributions and moderation
- Help requests (user ↔ volunteer flows)
- Notifications and user preferences
- Privacy settings and data export requests
- Place submissions and admin review

## Tech Stack

- **Laravel**: 12
- **PHP**: 8.2+
- **Auth**: Laravel Sanctum (token/session authentication)
- **Queue**: Database queue driver
- **Frontend tooling (for assets/admin/dev convenience)**: Vite

## Requirements

- **PHP**: 8.2 or newer
- **Composer**: latest stable
- **Node.js**: recommended (for Vite assets) + npm
- One of:
  - **SQLite** (default in `.env.example`)
  - **MySQL/MariaDB** (optional)

## Getting Started (Local Setup)

From the backend root directory:

```bash
composer install
```

Create your environment file:

```bash
copy .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

Run migrations:

```bash
php artisan migrate
```

Install Node dependencies (needed for Vite build/dev scripts referenced by `composer run dev`):

```bash
npm install
```

### One-command setup

This project includes a Composer script that performs the common setup steps:

```bash
composer run setup
```

This script:

- Installs PHP dependencies
- Ensures `.env` exists
- Generates app key
- Runs migrations
- Installs npm dependencies
- Builds assets

## Environment Configuration

The backend uses standard Laravel environment variables.

Common variables you’ll likely edit in `.env`:

- **APP_NAME**, **APP_ENV**, **APP_DEBUG**, **APP_URL**
- **DB_CONNECTION** and DB credentials
- **QUEUE_CONNECTION** (defaults to `database`)
- **SESSION_DRIVER** (defaults to `database`)

### Notes

- If you change `APP_URL`, ensure your client apps point to the same base URL.
- If you use MySQL, update DB variables accordingly.

## Database Setup

### Option A: SQLite (default)

In `.env`:

```env
DB_CONNECTION=sqlite
```

Then run:

```bash
php artisan migrate
```

### Option B: MySQL / MariaDB

In `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=athar
DB_USERNAME=root
DB_PASSWORD=
```

Then:

```bash
php artisan migrate
```

## Running the App

### Development (recommended)

This repo defines a `composer run dev` script which starts multiple processes using `concurrently`:

- Laravel dev server
- Queue listener
- Log viewer (Pail)
- Vite dev server

Run:

```bash
composer run dev
```

### Backend only

```bash
php artisan serve
```

## Background Jobs / Queue

Queue is configured to use the **database** driver by default (`QUEUE_CONNECTION=database`).

Make sure required tables exist (migrations handle this), then run a worker:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

## API Authentication (Sanctum)

Protected endpoints require authentication using **Laravel Sanctum**.

General approach:

- Authenticate via `POST /api/auth/login`
- Use the returned token/cookie/session as required by your client
- Send requests with `Accept: application/json`

The API routes are defined in `routes/api.php`.

## Postman Collections

Postman collections are included in the repository root:

- `Athar.postman_collection.json`
- `Athar_MERGED_Mobile_Admin.postman_collection.json`

Import these into Postman to explore and test endpoints quickly.

## Useful Artisan Commands

### Migrations

```bash
php artisan migrate
php artisan migrate:fresh --seed
```

### Cache/config cleanup

```bash
php artisan optimize:clear
```

### OSM Places Import

This project includes a custom import command that can load places from a delimited file (CSV/TSV), optionally wipe existing locations (soft/hard), and bulk insert results.

```bash
php artisan osm:import-places {path} --wipe=soft|hard --delimiter= --dry-run
```

Examples:

```bash
php artisan osm:import-places "D:\\path\\to\\places.csv" --wipe=soft
php artisan osm:import-places "D:\\path\\to\\places.tsv" --delimiter="\t" --dry-run
```

## Testing

Run the test suite:

```bash
composer run test
```

Or directly:

```bash
php artisan test
```

## Deployment Notes

Recommended production checklist:

- Set `APP_ENV=production` and `APP_DEBUG=false`
- Configure your production database
- Run migrations:

```bash
php artisan migrate --force
```

- Cache configuration/routes/views (optional but recommended):

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- Ensure the queue worker is running under a process manager (e.g., Supervisor / systemd)
- Configure your web server (Nginx/Apache) to serve `public/`

## License

This project is private to the Athar codebase unless stated otherwise by the repository owner.
