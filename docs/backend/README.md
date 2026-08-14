# KaloWies Backend — API Reference

## Overview

The KaloWies backend is a Laravel 11.55 application that exposes a JSON API for a
calorie-tracking PWA. The Vue 3 SPA (served separately) talks to this API over HTTP.

- **Framework:** Laravel 11.55 (PHP ^8.2)
- **Auth:** Laravel Sanctum 4.x, cookie-based SPA mode (no bearer tokens)
- **Production DB:** MySQL — `DB_CONNECTION=mysql`, `DB_DATABASE=kalowies`
- **Tests:** in-memory SQLite — `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`

All routes live in `routes/api.php` under the `/api` prefix and are registered through
Laravel's `statefulApi()` middleware group (`bootstrap/app.php`).

## Authentication

Sanctum's **SPA (cookie) mode** is used. There are no personal access tokens.

1. `GET /sanctum/csrf-cookie` → **204 No Content**. Laravel sets the `XSRF-TOKEN` cookie
   (an encrypted copy of the CSRF token) plus the session cookie.
2. On every subsequent request the SPA must read the `XSRF-TOKEN` cookie value and send it
   in the **`X-XSRF-TOKEN` header**.
3. Requests must carry the session cookie and come from a **stateful domain**
   (see `SANCTUM_STATEFUL_DOMAINS`) so Sanctum treats them as first-party.

Protected routes are wrapped in `auth:sanctum`, which authenticates against the `web`
guard (session). **Guest requests to any protected route receive HTTP 401.**

Only `POST /api/register` and `POST /api/login` are public. Logging in/registering starts a
server-side session; there is nothing to store on the client besides the cookies.

## Endpoints

16 endpoints (15 requiring auth) plus the Sanctum CSRF cookie route.

### `POST /api/register`
- **Auth:** no
- **Body (JSON):**
  - `name` — required, string, max 255
  - `email` — required, valid email, max 255, **unique** in `users`
  - `password` — required, string, min 8, must equal `password_confirmation`
  - `password_confirmation` — required
- **Success:** `201` — `{ "user": {...} }`; the user is logged in and the session is regenerated.
- **Errors:** `422` validation errors.

### `POST /api/login`
- **Auth:** no
- **Body (JSON):**
  - `email` — required, valid email
  - `password` — required, string
  - `remember` — optional boolean
- **Success:** `200` — `{ "user": {...} }`; the session is regenerated.
- **Errors:** `422` — `{ "message": "These credentials do not match our records." }` when
  credentials are invalid.

### `POST /api/logout`
- **Auth:** yes
- **Success:** `200` — `{ "message": "Logged out." }`; the web guard is logged out and the
  session is invalidated and its token regenerated.

### `GET /api/me`
- **Auth:** yes
- **Success:** `200` — `{ "user": {...} }`.

### `GET /api/meals?date=YYYY-MM-DD`
- **Auth:** yes
- **Query:** `date` — required, `Y-m-d` format.
- **Success:** `200` — `{ "meals": [...] }`, the caller's meals for that date ordered by
  `created_at`, each with its `items` (ordered by `sort_order`, then `id`). All statuses are
  returned (draft / processing / ready / confirmed / …), so the frontend polls this endpoint
  to observe scan results.
- **Errors:** `422` when `date` is missing/invalid.

### `POST /api/meals`
- **Auth:** yes
- **Body (JSON):**
  - `date` — required, `Y-m-d`
  - `type` — required, one of `breakfast | snack | lunch | dinner`
  - `note` — nullable, string, max 500
  - `items` — required array, min 1; each item:
    - `name` — required, string, max 120
    - `grams` — required, numeric, **1–3000**
    - `calories` — required, numeric, **1–2000**
    - `protein` — required, numeric, 0–500
    - `carbs` — required, numeric, 0–500
    - `fat` — required, numeric, 0–500
- **Behavior:** creates the meal with `status=confirmed`, `source=manual`, stores items with
  `sort_order` = array index, then recomputes totals from the items via `NutritionCalculator`.
- **Success:** `201` — `{ "meal": {...} }` including `items` and the `total_calories /
  total_protein / total_carbs / total_fat` fields.
- **Errors:** `422` validation errors.

### `GET /api/meals/{meal}`
- **Auth:** yes
- **Success:** `200` — `{ "meal": {...} }` with `items`.
- **Errors:** `404` when not found or owned by another user.

### `PUT /api/meals/{meal}`
- **Auth:** yes
- **Body (JSON, partial):**
  - `date` — sometimes required, `Y-m-d`
  - `type` — sometimes required, valid meal type
  - `note` — nullable, string, max 500
- **Success:** `200` — `{ "meal": {...} }` with `items`.
- **Errors:** `404` (other user) or `422` (validation).

### `DELETE /api/meals/{meal}`
- **Auth:** yes
- **Success:** `204` No Content (items cascade-delete with the meal).
- **Errors:** `404` when owned by another user.

### `POST /api/meals/{meal}/confirm`
- **Auth:** yes
- **Behavior:** allowed only when the meal `status` is `ready` **and** it has ≥ 1 item.
  Recomputes totals and sets `status=confirmed`, clearing `note` to `null`.
- **Success:** `200` — `{ "meal": {...} }` with `items`.
- **Errors:** `422` — `{ "message": "Meal is not ready to confirm." }` when status ≠ `ready`,
  or `{ "message": "Add at least one item before confirming." }` when it has no items.
  `404` when owned by another user.

### `PUT /api/meal-items/{item}`
- **Auth:** yes
- **Body (JSON, partial):**
  - `name` — sometimes, string, max 120
  - `grams` — sometimes, numeric, 1–3000
  - `calories` — sometimes, numeric, 1–2000
  - `protein` / `carbs` / `fat` — sometimes, numeric, 0–500
- **Behavior:** updates the item, then **recomputes the parent meal totals**.
- **Success:** `200` — `{ "item": {...} }`.
- **Errors:** `404` when the item's meal belongs to another user, or `422` (validation).

### `DELETE /api/meal-items/{item}`
- **Auth:** yes
- **Behavior:** deletes the item, then **recomputes the parent meal totals**.
- **Success:** `204` No Content.
- **Errors:** `404` when the item's meal belongs to another user.

### `POST /api/meals/scan`
- **Auth:** yes
- **Body (`multipart/form-data`):**
  - `image` — required file; must be a valid image of mime `jpeg`, `png` or `webp`, max
    **10240 KB (10 MB)**
  - `date` — required, `Y-m-d`
  - `type` — required, valid meal type
- **Behavior:** stores the image on the **public** disk under `meals/<ulid>.<ext>`, creates a
  meal with `status=draft`, `source=scan` and the stored `image_path`, then dispatches
  `ProcessFoodScan($meal->id)`.
- **Success:** `201` — `{ "meal": {...} }` (includes the `image_url` accessor; see
  `docs/backend/architecture.md`).
- **Errors:** `422` validation errors (e.g. missing image, non-image file, invalid type).

### `GET /api/daily-summary?date=YYYY-MM-DD`
- **Auth:** yes
- **Query:** `date` — required, `Y-m-d`.
- **Behavior:** only `status=confirmed` meals count toward totals; creates a default
  goal (`calorie_goal=2000`, grams `null`) if none exists.
- **Success:** `200`:
  ```json
  {
    "date": "YYYY-MM-DD",
    "meals": [ /* confirmed meals with items */ ],
    "totals": { "calories": 400.0, "protein": 34.0, "carbs": 44.0, "fat": 8.4 },
    "goal": { "user_goal": "..." },
    "remaining": { "calories": 1600 },
    "per_meal_type": {
      "breakfast": { "meals": 0, "calories": 0.0 },
      "snack":     { "meals": 0, "calories": 0.0 },
      "lunch":     { "meals": 1, "calories": 400.0 },
      "dinner":    { "meals": 0, "calories": 0.0 }
    }
  }
  ```
  `totals` are rounded to 2 decimals; `remaining.calories` is `max(0, goal − consumed)` so it
  never goes negative.
- **Errors:** `422` when `date` is missing/invalid.

### `GET /api/goals`
- **Auth:** yes
- **Success:** `200` — `{ "goal": {...} }`; creates the default
  (`calorie_goal=2000`, protein/carbs/fat grams `null`) if the user has none.

### `PUT /api/goals`
- **Auth:** yes
- **Body (JSON):**
  - `calorie_goal` — required, integer, **500–10000**
  - `protein_grams` — nullable, integer, 0–500
  - `carbs_grams` — nullable, integer, 0–500
  - `fat_grams` — nullable, integer, 0–500
- **Success:** `200` — `{ "goal": {...} }`; the row is created if it did not exist.
- **Errors:** `422` validation errors.

## Ownership rule

Every meal and meal-item route resolves the resource scoped to the authenticated user via a
private `owned()` helper (`MealController` / `MealItemController`) that uses `firstOrFail()`.
A resource belonging to another user is therefore indistinguishable from a missing one and
returns **404 — never 403**.

## Environment

Relevant environment keys:

| Key | Purpose |
| --- | --- |
| `APP_URL` | Base URL; used for the public-disk URL (`image_url`) and Sanctum's `currentApplicationUrlWithPort()`. |
| `SANCTUM_STATEFUL_DOMAINS` | Comma-separated SPA origins allowed to use stateful cookie auth (e.g. `kalowies.test,localhost,127.0.0.1`). |
| `QUEUE_CONNECTION` | Queue driver — `database` in production, `sync` in tests. |
| `SESSION_DRIVER` | Session store — `database` in production, `array` in tests. |
| `DB_CONNECTION` / `DB_DATABASE` (+ `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`) | MySQL in production; `sqlite` / `:memory:` in tests. |
| `GEMINI_API_KEY` | Google Gemini API key (read as `services.gemini.api_key`). |
| `GEMINI_MODEL` | Default vision model, default `gemini-2.0-flash` (read as `services.gemini.model`). |
| `VISION_PROVIDER` | Declared in `.env.example` (`=gemini`) but **not currently read by any PHP code**. |
