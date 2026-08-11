# KaloWies — Design Spec

**Date:** 2026-08-11
**Status:** Approved by user (Sections 1–3)

Personal AI-powered calorie tracking PWA. Take a photo of food, the backend runs a Gemini vision analysis, and the app logs the meal with estimated calories and macros against a daily goal.

## Decisions (confirmed)

- **Repo structure:** Single repo; Laravel 11 at root hosts the Vue 3 SPA. One origin (Herd `kalowies.test`), one build (`npm run build` → `public/build`). No CORS.
- **AI provider:** Google Gemini (`gemini-2.0-flash`, fallback `gemini-1.5-flash`) via generic `Http` facade — no SDK.
- **Database:** MySQL (Herd/Dbngin), `InnoDB`, `utf8mb4`.
- **Auth:** Sanctum cookie-based SPA auth (`$middleware->statefulApi()`).
- **Queues:** Redis driver, `database` fallback for dev.
- **Storage:** Local disk in dev, S3-compatible in prod (config-driven).
- **Scan flow:** Approach A — draft meal in DB, queued job, status transitions `draft → processing → ready → confirmed`, editable server-side items before confirm.

## Architecture

Laravel 11 backend serves JSON under `/api`; Vue 3 SPA (built by Vite into `public/build`) is the mobile-first PWA shell. Single origin → Sanctum cookies, no tokens in `localStorage`.

### Backend structure

```
app/
  Http/Controllers/Api/
    AuthController.php          # register, login, logout, me
    MealController.php          # index, store(manual), show, destroy, confirm
    MealScanController.php      # scan (uploads + enqueues job)
    MealItemController.php      # update, destroy
    DailySummaryController.php  # daily-summary
    GoalController.php          # index, update
  Models/
    User.php                    # default
    Meal.php                    # status state machine + scopes
    MealItem.php
    UserGoal.php
  Services/
    FoodVisionService.php       # Gemini call + JSON parsing (Http::fake()-testable)
    NutritionCalculator.php     # recompute meal totals from items
  Jobs/
    ProcessFoodScan.php         # timeout 60, tries 2, backoff [10, 30]
  Rules/
    MealTypeRule.php
database/
  migrations/
    2026_08_11_000001_create_meals_table.php
    2026_08_11_000002_create_meal_items_table.php
    2026_08_11_000003_create_user_goals_table.php
routes/api.php
```

### Frontend structure

```
resources/
  js/
    main.js                 # createApp, Pinia, Router, registerServiceWorker
    app.vue                 # shell: router-view + BottomNav
    router/index.js
    stores/
      auth.js
      meals.js
      scan.js               # poll loop for draft meal status
      goals.js
    services/api.js         # axios wrapper: CSRF, withCredentials, 401 → login
    components/
      BottomNav.vue
      MealCard.vue
      MacroBar.vue
      MealItemRow.vue
      CameraCapture.vue     # input capture + preview + retake
      WeeklyChart.vue       # Chart.js last-7-days
    pages/
      LoginView.vue
      RegisterView.vue
      TodayView.vue
      ScanView.vue
      HistoryView.vue
      ProfileView.vue
  css/app.css               # Tailwind v4
```

## Data model

### `users`
Laravel default (id, name, email, password, timestamps). No goal columns.

### `user_goals`
One active goal per user (`user_id` unique). Separate table keeps `users` clean and allows goal-history/insights later without a migration.
- `id`
- `user_id` — unique FK, cascade
- `calorie_goal` — int, default 2000
- `protein_grams`, `carbs_grams`, `fat_grams` — int, nullable (macro bars hidden when null)
- timestamps

### `meals`
- `id`
- `user_id` — FK cascade, composite index `(user_id, date)`
- `date` — date, indexed
- `type` — string enum: `breakfast | snack | lunch | dinner`
- `status` — string enum: `draft | processing | ready | confirmed | cancelled | failed` (indexed)
- `source` — `scan | manual`
- `image_path` — nullable (uploaded photo for scans)
- `note` — nullable (used for the failed-scan error message)
- `total_calories`, `total_protein`, `total_carbs`, `total_fat` — decimal(8,2), nullable (null while draft/processing)
- timestamps

### `meal_items`
- `id`
- `meal_id` — FK cascade, indexed
- `name` — string
- `grams` — decimal(8,2)
- `calories`, `protein`, `carbs`, `fat` — decimal(8,2)
- `sort_order` — int
- timestamps (not strictly needed but consistent)

Totals on `meals` are always recomputed from items by `NutritionCalculator` — never entered by hand.

Enums are string constants on the models (no spatie/enum dependency).

## API surface

All JSON. Every route `auth:sanctum` except register/login.

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/register` | name, email, password → 201 |
| POST | `/api/login` | credentials → user |
| POST | `/api/logout` | revoke session |
| GET | `/api/me` | current user |
| GET | `/api/meals?date=YYYY-MM-DD` | meals for date (+items; scan source includes image url) |
| POST | `/api/meals` | manual: `{date, type, items:[{name, grams, calories, protein, carbs, fat}]}` → `confirmed` |
| GET | `/api/meals/{id}` | meal with items (scan poll target) |
| DELETE | `/api/meals/{id}` | delete (also cancels pending drafts) |
| POST | `/api/meals/scan` | multipart: image + date + type → returns draft `{meal}`, enqueues job |
| PUT | `/api/meals/{id}` | update type/date/note (drafts + confirmed) |
| PUT | `/api/meal-items/{id}` | edit grams/name/macros → recompute totals |
| DELETE | `/api/meal-items/{id}` | remove → recompute totals |
| POST | `/api/meals/{id}/confirm` | `ready` → `confirmed` (validates ≥1 item, totals set) |
| GET | `/api/daily-summary?date=` | `{totals, goal, remaining, perMealType}` |
| GET/PUT | `/api/goals` | read / update active goal |

**Scan poll protocol:** `POST /scan` returns the draft; frontend polls `GET /api/meals/{id}` every ~2s while status ∈ {draft, processing}; editable list on `ready`; error UI on `failed`.

**Ownership:** all meal/item routes check `$meal->user_id === auth()->id()` (404 on mismatch). Item routes load the parent meal to check ownership.

## AI vision integration

`FoodVisionService` is the only place that touches Gemini — provider-agnostic, `Http::fake()`-testable.

**Config (.env):**
```
VISION_PROVIDER=gemini
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.0-flash
```

**Call:** `POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={key}` with `responseMimeType: "application/json"`, `responseSchema` (forces valid JSON), inline base64 image data.

**Prompt:** "Identify every food item. Estimate portion size in grams or common serving. Scale all macros to the estimated portion. Return JSON only: `{items:[{name, grams, calories, protein, carbs, fat}]}`. If nothing recognizable, return `{items:[]}`." Macros rounded to nearest 0.1.

**Parsing:** decode JSON; validate each item (grams 1–3000, calories 1–2000 per item); drop invalid items; recompute meal totals.

**`ProcessFoodScan` job:** `$timeout = 60`, `tries = 2`, `backoff = [10, 30]`. `processing` → call service → write items → set totals → `ready`. Final failure → `failed` + short note ("Could not analyze this image. Please try again.").

## Frontend behavior

- **TodayView** — big calorie progress vs goal, macro bars, "Take Photo" FAB, today's meals grouped by type, remaining calories.
- **ScanView** — full-screen camera: `<input type="file" accept="image/*" capture="environment">` with retake + preview; upload → spinner → poll; `ready` → editable items (edit portion grams with live macro rescale, remove item, change name); "Log meal" → confirm → back to Today.
- **HistoryView** — date nav, Chart.js line/bar of calories for last 7 days, per-day list.
- **ProfileView** — goal form (calories + macro grams), logout.
- Axios wrapper: fetch `/sanctum/csrf-cookie`, `withCredentials`, 401 → login redirect.

## PWA

- `vite-plugin-pwa` (workbox), `registerType: 'autoUpdate'`.
- Manifest: name "KaloWies", short_name "KaloWies", `display: standalone`, theme `#0d9488` (teal-600), background `#f8fafc`, icons 192/512 (generated placeholders).
- Service worker: precache built shell (offline app shell); API network-only.

## Testing

- **Backend (PHPUnit feature tests):** auth (register/login/logout/me); manual meal create + totals recompute; scan flow end-to-end with `Http::fake()` Gemini (draft → ready → confirm); ownership 404s; daily-summary math; goal validation.
- **Frontend (Vitest + Vue Test Utils):** auth store, scan store poll logic (fake timers), MacroBar rendering.
- **Manual verification** documented in README (`php artisan queue:work`, take a photo).

## Branding

- Palette: primary teal-600 `#0d9488`, accent emerald-400, background slate-50 `#f8fafc`, text slate-800.
- Clean white cards, `rounded-2xl`, soft shadows, generous spacing. Tagline "See your calories clearly." on login.
- Bottom nav with simple SVG icons.

## Deferred (out of MVP)

USDA food DB lookup, photo crop/rotate, goal history/insights beyond 7-day chart, sharing, multi-user households.
