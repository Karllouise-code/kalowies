# KaloWies

> See your calories clearly.

A calorie-tracking PWA with AI-powered food scanning. Snap a photo of your meal, and KaloWies uses Google Gemini to estimate calories and macros.

## Features

- **Photo scan** — camera capture or gallery upload → AI analysis → editable results → log
- **Manual meals** — add food entries with calories, protein, carbs, fat
- **Today dashboard** — daily calorie/macro totals with goal progress bars
- **History** — 7-day calorie chart, browse past days
- **Goals** — set and update daily calorie/macro targets
- **PWA** — installable on mobile and desktop, works offline (app shell)

## Tech Stack

**Backend:** Laravel 11, PHP 8.2+, MySQL, Laravel Sanctum (SPA auth), Google Gemini API  
**Frontend:** Vue 3, Pinia, Vue Router, Tailwind CSS v4, Chart.js, Vite, Vitest  
**Queue:** Database driver (for async food scan processing)

## Requirements

- PHP 8.2+ with GD extension
- MySQL or SQLite
- Node.js 20+
- Google Gemini API key (for photo scan)

## Setup

### 1. Backend

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Set these in `.env`:
```
GEMINI_API_KEY=your-gemini-api-key
QUEUE_CONNECTION=database
```

### 2. Frontend

```bash
npm install
npm run dev
```

### 3. Queue Worker

Photo scans are processed in the background. Start the worker:

```bash
php artisan queue:work
```

### 4. Run

Open `https://kalowies.test` (Laravel Herd) or `http://localhost:8000` (`php artisan serve`) with `npm run dev` running.

## Testing

```bash
# Backend (54 tests)
php artisan test

# Frontend (14 tests)
npm test

# Production build
npm run build
```

## Environment Variables

| Variable | Required | Description |
|----------|----------|-------------|
| `GEMINI_API_KEY` | Yes | Google Gemini API key for food image analysis |
| `QUEUE_CONNECTION` | Yes | `database` for zero-setup, `redis` for production |
| `DB_CONNECTION` | Yes | `mysql` or `sqlite` |

## PWA

- Install: Chrome/Edge → install icon in address bar; iOS Safari → Share → Add to Home Screen
- Service worker activates after `npm run build` (PWA features are off during dev)

## License

MIT
