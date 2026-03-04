# Athar Back-END

Backend API for the Athar platform, built with **Laravel 12** and **PHP 8.2**. This service exposes REST APIs for authentication, locations/places, accessibility contributions, help requests, notifications, privacy controls, and admin operations.

## Table of Contents

- Overview
- Tech Stack
- Project Structure
- Core Domains & Functionality
- Requirements
- Getting Started (Local Setup)
- Environment Configuration
- Database Setup (SQLite / MySQL)
- Running the App
- Background Jobs / Queue
- API Authentication (Sanctum)
- API Reference
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

## Project Structure

High-level structure (important folders/files):

```text
Athar Back-End/
  app/
    Http/
      Controllers/
        Api/                      # Mobile/client REST API controllers
        Admin/                    # Admin dashboard API controllers
        AdminAuthController.php   # Admin login/logout/me
      Middleware/                 # Request middleware (role enforcement, admin auth, etc.)
      Requests/                   # Form request validation
      Resources/                  # API response transformers
    Models/                       # Eloquent models (domain entities)
  config/                         # Laravel configuration
  database/
    migrations/                   # DB schema migrations
    seeders/                      # Seeders (demo/dev data)
  routes/
    api.php                       # Public + authenticated API routes (Sanctum)
    web.php                       # Admin routes under /admin
    console.php                   # Custom artisan commands
  storage/                        # Logs, cache, uploaded files (local)
  tests/                          # Automated tests
  public/                         # Public web root
  composer.json                   # PHP deps + scripts
```

### Where to look for what

- **Routes**
  - `routes/api.php`: main client/mobile API (JSON)
  - `routes/web.php`: admin endpoints under `/admin`
- **Controllers**
  - `app/Http/Controllers/Api/*`: client features (auth, locations, help requests, etc.)
  - `app/Http/Controllers/Admin/*`: admin dashboard features
- **Validation**
  - `app/Http/Requests/*`: request validation for API endpoints
- **Response shaping**
  - `app/Http/Resources/*`: transforms models to API JSON responses
- **Role enforcement**
  - `app/Http/Middleware/EnsureApiRole.php`: enforces `user` vs `volunteer` API role where applicable
  - `app/Http/Middleware/IsAdmin.php`: protects admin routes
  - `app/Http/Middleware/EnsureJsonAccept.php`: enforces JSON responses (Accept header)

## Core Domains & Functionality

This section describes the main functional areas and where they live in the codebase.

### Authentication & Accounts

- **Client auth**: `AuthController`
  - Register user
  - Register volunteer
  - Login
  - Password change
  - Session listing + session revocation
- **Account deletion**: `AccountController`

### Locations / Places

- Browse places: `LocationController@index`, `LocationController@show`
- Nearby search: `LocationController@nearby`
- Ratings:
  - List ratings: `RatingController@index`
  - Add rating: `RatingController@store`
- Accessibility contributions:
  - Upsert contribution: `AccessibilityContributionController@upsert`
- Place submissions:
  - Submit place: `PlaceSubmissionController@store`
  - View my submissions: `PlaceSubmissionController@mine`
- Place reporting (create a place + rating + contribution in one transaction):
  - `LocationController@storeReport`

### Help Requests (User ↔ Volunteer)

Implemented in `HelpRequestController` and `VolunteerController`.

- Users can:
  - Create help requests
  - View their requests (active/history)
  - Cancel requests
- Volunteers can:
  - Set availability status
  - View incoming requests
  - Accept/decline/complete requests
  - View active and history lists
- Messaging:
  - Read thread messages
  - Send new message

### Notifications

- List notifications: `NotificationController@index`
- Notification preferences:
  - View preferences: `NotificationPreferenceController@show`
  - Update preferences: `NotificationPreferenceController@update`

### Privacy

- Privacy settings:
  - Show settings: `PrivacySettingController@show`
  - Update settings: `PrivacySettingController@update`
- Data export requests:
  - Create export request: `DataExportRequestController@store`
  - Check export status: `DataExportRequestController@show`

### Flags & Moderation

- Create a flag (general): `FlagController@store`
- Create a flag for a location: `FlagController@storeForLocation`
- View my flags: `FlagController@mine`

### Support Tickets

- Create support ticket: `SupportTicketController@store`

### Admin Dashboard

Admin routes are served under `/admin/*` (see `routes/web.php`) and are protected by the `isAdmin` middleware.

Admin capabilities include:

- Accounts management and volunteer approvals
- Places CRUD + accessibility report upsert
- Categories and governments
- Help request moderation and resolution
- Notifications moderation
- Tutorial CRUD
- Place submissions approve/reject
- Flag moderation workflows (request info / dismiss / resolve)

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

## API Reference

Base URLs:

- **Client API**: `/api/*` (defined in `routes/api.php`)
- **Admin API**: `/admin/*` (defined in `routes/web.php`)

All client API routes are wrapped in `json.accept` middleware, so clients should send:

```http
Accept: application/json
```

### Client API (`routes/api.php`)

#### Public (no auth)

- `POST /api/auth/register`
- `POST /api/auth/register-user`
- `POST /api/auth/register-volunteer`
- `POST /api/auth/login`
- `GET /api/categories`
- `GET /api/governments`
- `GET /api/locations`
- `GET /api/locations/nearby`
- `GET /api/locations/{id}`
- `GET /api/locations/{id}/ratings`

#### Authenticated (Sanctum)

- `GET /api/me`
- `POST /api/auth/logout`
- `PUT /api/auth/password`
- `GET /api/auth/sessions`
- `DELETE /api/auth/sessions/others`
- `DELETE /api/auth/sessions/{id}`

- `PUT /api/profile`
- `POST /api/profile/photo`
- `GET /api/profile/stats`

- `GET /api/notifications`
- `GET /api/notification-preferences`
- `PUT /api/notification-preferences`

- `GET /api/privacy/settings`
- `PUT /api/privacy/settings`
- `POST /api/privacy/data-export`
- `GET /api/privacy/data-export/{id}`

- `POST /api/support/tickets`
- `DELETE /api/account`

- `POST /api/locations/{id}/ratings`

- `POST /api/place-submissions`
- `GET /api/place-submissions/mine`

- `POST /api/flags`
- `POST /api/locations/{id}/flags`
- `GET /api/flags/mine`

- `PUT /api/locations/{id}/accessibility-report`

- `GET /api/help-requests/{id}/messages`
- `POST /api/help-requests/{id}/messages`

#### Role-restricted: `user`

These are protected by `api.role:user` middleware.

- `POST /api/locations/report`
- `POST /api/help-requests`
- `GET /api/help-requests/mine`
- `POST /api/help-requests/{id}/cancel`

#### Role-restricted: `volunteer`

These are protected by `api.role:volunteer` middleware.

- `POST /api/help-requests/{id}/accept`
- `POST /api/help-requests/{id}/decline`
- `POST /api/help-requests/{id}/complete`

- `POST /api/volunteer/status`
- `GET /api/volunteer/incoming`
- `GET /api/volunteer/active`
- `GET /api/volunteer/history`
- `GET /api/volunteer/impact`

### Admin API (`routes/web.php`)

Admin endpoints are grouped under `/admin`.

#### Admin auth

- `POST /admin/login`
- `POST /admin/logout`

#### Admin protected (requires `isAdmin`)

- `GET /admin/me`
- `GET /admin/dashboard`

Notifications

- `GET /admin/notifications`
- `POST /admin/notifications/{id}/read`
- `POST /admin/notifications/read-all`

Help requests

- `GET /admin/help-requests`
- `GET /admin/help-requests/{id}`
- `PUT /admin/help-requests/{id}`
- `POST /admin/help-requests/{id}/resolve`

Tutorials

- `GET /admin/tutorials`
- `POST /admin/tutorials`
- `PUT /admin/tutorials/{id}`
- `DELETE /admin/tutorials/{id}`

Governments

- `GET /admin/governments`
- `POST /admin/governments`

Locations (Places)

- `GET /admin/locations`
- `GET /admin/locations/{id}`
- `POST /admin/locations`
- `PUT /admin/locations/{id}`
- `DELETE /admin/locations/{id}`
- `PUT /admin/locations/{id}/accessibility-report`

Categories

- `GET /admin/categories`
- `POST /admin/categories`
- `PUT /admin/categories/{id}`
- `DELETE /admin/categories/{id}`

Place submissions

- `GET /admin/place-submissions`
- `POST /admin/place-submissions/{id}/approve`
- `POST /admin/place-submissions/{id}/reject`

Flags

- `GET /admin/flags`
- `POST /admin/flags/{id}/request-info`
- `POST /admin/flags/{id}/dismiss`
- `POST /admin/flags/{id}/resolve`

Accounts

- `GET /admin/accounts`
- `POST /admin/accounts/{id}/volunteer/approve`
- `POST /admin/accounts/{id}/volunteer/reject`
- `POST /admin/accounts`
- `PUT /admin/accounts/{id}`
- `DELETE /admin/accounts/{id}`

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
