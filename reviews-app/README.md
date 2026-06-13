# Reviews App — Yandex Maps Integration

A test assignment: Laravel 10 + Vue 3 SPA that connects to a Yandex Maps organization card and displays its reviews and rating.

## Stack

- **Backend**: Laravel 10, Sanctum (token auth), MySQL 8, Guzzle
- **Frontend**: Vue 3 (Composition API), Vue Router, Vite, Axios
- **Infrastructure**: Docker Compose (MySQL, PHP-FPM, nginx, Vite dev server)

## Features

- **Authentication** — email/password login, Sanctum bearer-token auth
- **Settings page** — paste any Yandex Maps organization URL, save to DB, trigger parsing
- **Reviews page** — paginated list (50/page), per-review: author, date, text, rating; org stats: average rating, rating count, review count
- **Error handling** — loading/error states visible in the UI, URL validation on both front and back end

## Running locally with Docker Compose

```bash
cd reviews-app
docker compose up --build
```

- Frontend: http://localhost:5173
- Backend API: http://localhost:8000/api

Login credentials (seeded automatically):

```
email:    user@example.com
password: password
```

## Running without Docker

### Backend

```bash
cd reviews-app/backend
composer install
cp .env.example .env
# Edit .env — set DB_* credentials
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

### Frontend

```bash
cd reviews-app/frontend
npm install
cp .env.example .env
# Set VITE_API_URL=http://localhost:8000/api
npm run dev
```

## Environment variables

### Backend (`.env`)

| Variable | Default | Description |
|---|---|---|
| `APP_KEY` | — | Laravel application key (generated automatically) |
| `APP_ENV` | `local` | Environment (`local` / `production`) |
| `APP_DEBUG` | `true` | Debug mode |
| `DB_HOST` | `db` | MySQL host |
| `DB_DATABASE` | `reviews_db` | Database name |
| `DB_USERNAME` | `reviews_user` | DB username |
| `DB_PASSWORD` | `reviews_pass` | DB password |
| `FRONTEND_URL` | `http://localhost:5173` | CORS allowed origin |

### Frontend (`.env`)

| Variable | Default | Description |
|---|---|---|
| `VITE_API_URL` | `http://localhost:8000/api` | Backend API base URL |

## Parser approach and Yandex bot protection

### Strategy chosen: internal API + CSRF token

Yandex Maps exposes an unofficial internal REST endpoint (`/maps/api/business/fetchReviews`) that the website itself uses via XHR. The endpoint requires:

1. A **CSRF token** embedded in the initial HTML of the organization page — obtained by loading the page first with a browser-like User-Agent.
2. **Session cookies** — carried automatically via Guzzle's `CookieJar`.
3. **Standard browser headers** (`User-Agent`, `Accept`, `Accept-Language`, `X-Requested-With`, `Referer`).

The parser:
1. Loads the organization page to harvest the CSRF token and session cookies.
2. Calls the reviews endpoint in a loop (`skip` / `limit` pagination, 30 reviews per request) until all reviews are fetched or the 600-review cap is reached.
3. Adds a 400 ms delay between requests to avoid rate-limiting.
4. Parses rating, rating count, and review count from the first response's metadata.

### Why not headless browser (Puppeteer/Playwright)?

A headless browser would make bot detection harder to trigger and handle JavaScript-rendered content, but it is expensive (RAM, spin-up time) and operationally heavier. Yandex's internal API is JSON, so we don't need to render HTML — intercepting the XHR is simpler and faster.

### Caching strategy

Reviews are fetched once per "Save & Fetch" action and stored in the `reviews` table. Subsequent page navigations serve data from the database (no re-parsing). This avoids hammering Yandex on every request and makes pagination instant. Users can re-trigger a parse at any time from the Settings page.

## Database schema

```
users            — id, name, email, password
organizations    — id, user_id, yandex_url, name, average_rating, rating_count, review_count, last_parsed_at
reviews          — id, organization_id, external_id, author, rating, text, published_at
personal_access_tokens — Sanctum token table
```

## What I would improve with more time

- **Queue parsing** — run the Yandex fetch in a background job (Laravel Queue) so the HTTP request doesn't block the API response; use Server-Sent Events or polling to notify the frontend when parsing is done.
- **Rotating proxies / browser fingerprinting** — to make the parser more robust against Yandex's anti-bot measures in production.
- **Refresh / incremental sync** — fetch only new reviews since `last_parsed_at` instead of deleting and re-inserting everything.
- **Multi-organization support** — currently one organization per user; allow multiple.
- **Review search & filtering** — filter by rating, date range, keyword.
- **Tests** — PHPUnit feature tests for the API and parser unit tests with mocked HTTP responses.
- **Hosting** — deploy backend to a VPS with Supervisor managing PHP-FPM, frontend to a CDN/Vercel.
