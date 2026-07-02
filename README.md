# World Cup 2026

Real-time tracker for the 2026 FIFA World Cup: fixtures, live scores, group standings, and knockout bracket — built with Laravel + Livewire.

## Credits

The circular knockout bracket design is inspired by the work of **[@emiliosansolini](https://www.instagram.com/emiliosansolini/)**. All credit for the original visual piece goes to him.

## Features

- **Fixtures**: matches grouped by day, with filters by team (up to 3), live matches, and "today". Score and minute auto-update via polling while a match is live.
- **Standings**: group tables calculated from results (points, goal difference, tiebreakers).
- **Bracket**: circular knockout bracket (32 → 16 → 8 → 4 → 2 → champion), with team colors and eliminated-team indicators.
- Dates and times are stored in UTC in the database and converted to São Paulo time (UTC-3) throughout the UI and in "today's matches" queries.
- Data is imported and synced from [football-data.org](https://www.football-data.org/) (primary source) and [API-Football](https://www.api-football.com/) (extra polling during critical windows of live matches, within a configurable daily quota).

## Requirements

- PHP 8.4+
- Composer
- Node.js + npm
- SQLite (default) or another Laravel-supported database
- [Laravel Sail](https://laravel.com/docs/sail) (optional, requires Docker) to run in a container

## Installation and setup

Clone the repository and install dependencies:

```bash
composer setup
```

This does everything in one go: installs PHP and JS dependencies, creates the `.env` file, generates the `APP_KEY`, runs migrations, and builds assets. Equivalent to running manually:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### API keys

To import and sync matches, set these in `.env`:

```env
API_FOOTBALL_URL=https://v3.football.api-sports.io
API_FOOTBALL_API_KEY=
API_FOOTBALL_DAILY_QUOTA=100

FOOTBALL_DATA_ORG_URL=https://api.football-data.org/v4
FOOTBALL_DATA_ORG_API_KEY=
FOOTBALL_DATA_ORG_COMPETITION_CODE=WC
```

### Importing tournament data

```bash
php artisan fixtures:import              # imports teams, groups, and the full fixture list
php artisan fixtures:link-api-football   # cross-references API-Football ids onto the imported fixtures
```

## Running the project

With Sail:

```bash
sail up -d
sail composer dev
```

Without Sail:

```bash
composer dev   # runs server, queue, and Vite together (via `php artisan dev`)
```

The application is available at `http://localhost` (or the port configured in `APP_URL`).

### Tracking live matches

To keep scores updated, run the polling command (every ~10s) while matches are in progress:

```bash
php artisan matches:watch-live
```

## Tests and quality

```bash
composer test          # config:clear + lint:check + types:check + tests
php artisan test --compact
composer lint           # Pint (auto-fixes style)
composer types:check    # PHPStan (Larastan, level 7)
```

## Stack

- Laravel 13 + Livewire 4
- Tailwind CSS v4 (via Vite)
- SQLite
- Pest 4 for testing
- Larastan (PHPStan level 7) for static analysis
