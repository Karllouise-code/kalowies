# KaloWies Backend — Architecture

## Data model

Three application tables (plus the standard framework tables and Sanctum's
`personal_access_tokens`). All foreign keys are `cascadeOnDelete`.

### `meals`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned PK | |
| `user_id` | FK `users.id` | cascade delete |
| `date` | date | |
| `type` | string(20) | `breakfast` \| `snack` \| `lunch` \| `dinner` |
| `status` | string(20) | default `draft` |
| `source` | string(20) | `scan` \| `manual` |
| `image_path` | string | nullable |
| `note` | string(500) | nullable |
| `total_calories` | decimal(8,2) | nullable |
| `total_protein` | decimal(8,2) | nullable |
| `total_carbs` | decimal(8,2) | nullable |
| `total_fat` | decimal(8,2) | nullable |
| `created_at` / `updated_at` | timestamps | |

Indexes: `(user_id, date)` and `status`.

### `meal_items`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned PK | |
| `meal_id` | FK `meals.id` | cascade delete |
| `name` | string(120) | |
| `grams` | decimal(8,2) | |
| `calories` | decimal(8,2) | |
| `protein` | decimal(8,2) | |
| `carbs` | decimal(8,2) | |
| `fat` | decimal(8,2) | |
| `sort_order` | unsignedInteger | default 0 |
| `created_at` / `updated_at` | timestamps | |

Index: `meal_id`.

### `user_goals`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned PK | |
| `user_id` | FK `users.id` | cascade delete, **unique** (one goal row per user) |
| `calorie_goal` | unsignedInteger | default 2000 |
| `protein_grams` | unsignedInteger | nullable |
| `carbs_grams` | unsignedInteger | nullable |
| `fat_grams` | unsignedInteger | nullable |
| `created_at` / `updated_at` | timestamps | |

Unique index: `user_id`.

### Eloquent relations

- `Meal::user()` — `belongsTo(User)`.
- `Meal::items()` — `hasMany(MealItem)->orderBy('sort_order')->orderBy('id')`.
- `MealItem::meal()` — `belongsTo(Meal)`.
- `UserGoal::user()` — `belongsTo(User)`.
- `User::goals()` — `hasOne(UserGoal)`.

Casts: `Meal` casts `date` to `date:Y-m-d` and the four `total_*` columns to float;
`MealItem` casts the five numeric columns to float; `UserGoal` casts the four integers.

`Meal` appends `image_url`; `getImageUrlAttribute()` returns
`Storage::disk('public')->url($image_path)` or `null`. The `public` disk is configured with
`url = env('APP_URL').'/storage'`, so `image_url` resolves to `{APP_URL}/storage/meals/<file>`
(an absolute URL when `APP_URL` is set; root-relative `/storage/...` only when it is empty).

## Meal lifecycle

- **Statuses** (`Meal` constants): `draft` → `processing` → `ready` → `confirmed`, plus
  `cancelled` and `failed`.
- **Sources:** `scan` | `manual`.
- Manual creation (`POST /api/meals`) writes `status=confirmed` directly.
- Scanning (`POST /api/meals/scan`) creates `status=draft`, `source=scan`.
- `POST /api/meals/{meal}/confirm` — `ready` → `confirmed` (recalculates totals and clears
  `note`); only allowed for `ready` meals with ≥ 1 item.
- `ProcessFoodScan` performs the scan transitions `draft` → `processing` → `ready` (or
  `failed`) — see "Queue / scan flow" below.

## Services

### `NutritionCalculator`

- `totalsFor(iterable $items)` — sums `calories`, `protein`, `carbs` and `fat` across items,
  rounds each to 2 decimals, and returns an array keyed by those API-facing names.
- `recalculate(Meal $meal)` — re-reads the meal's persisted items, computes the totals,
  **force-fills the `total_*` columns** on the meal, saves, refreshes and returns the meal.

Totals are **always derived from items and never accepted from request input** — there is no
`total_*` field in any validation rule.

### `FoodVisionService`

- `analyze(string $imagePath)` — reads the image from the **public** disk (throws
  `RuntimeException` if the stored file is missing) and encodes it base64 inline with its
  mime type.
- Calls `POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={api_key}`
  with a 45-second timeout.
- Sends `generationConfig` with `responseMimeType: application/json` plus a
  `responseSchema` declaring `items` as an array of objects, each requiring
  `name`, `grams`, `calories`, `protein`, `carbs` and `fat`.
- On HTTP **404** it retries the same payload against the fallback model `gemini-1.5-flash`.
  The primary model is `config('services.gemini.model')` (env `GEMINI_MODEL`), default
  `gemini-2.0-flash`.
- A failed response is logged and raises `RuntimeException`; an empty response body raises
  `RuntimeException`.
- `parseItems()`: decodes the model text and **drops items** with an empty/blank `name`,
  `grams < 1` or `> 3000`, or `calories < 1` or `> 2000`. Surviving items are normalized —
  `name` truncated to 120 chars (`mb_substr`), `grams` rounded to 2 decimals, the four
  macros to 1 decimal. Malformed JSON (no `items` key) raises `RuntimeException`.

## Queue / scan flow

1. `POST /api/meals/scan` validates the multipart upload (`image`, `date`, `type`).
2. The file is stored on the **public** disk as `meals/<ulid>.<ext>`.
3. A meal is created with `status=draft`, `source=scan` and the stored `image_path`.
4. `ProcessFoodScan::dispatch($meal->id)` is pushed onto the queue and the endpoint returns
   `201` immediately.

**`app/Jobs/ProcessFoodScan.php`:**

- Implements `ShouldQueue`, uses `Queueable`, and takes a `public int $mealId`.
- **Retry policy:** `public int $tries = 2`, `public int $timeout = 60`,
  `public array $backoff = [10, 30]`.
- `handle(FoodVisionService $vision)`: no-ops unless the meal exists and its status is
  `draft` or `processing` (admitting `processing` lets a scheduled retry re-enter after a
  first-attempt failure). If the image is missing it fails immediately. Otherwise it sets
  `status=processing`, runs `FoodVisionService::analyze($image_path)`, and — when non-empty —
  writes the returned items (with `sort_order` = array index), calls
  `NutritionCalculator::recalculate()`, sets `status=ready` and clears `note`. When `analyze`
  returns empty items it sets `status=failed` with note
  `No food was detected in the image. Please try again.`
- `failed(Throwable $e)`: sets `status=failed` with note
  `Could not analyze this image. Please try again.`
- Every failure path logs a warning via a shared `fail()` helper.
- The frontend observes results by polling `GET /api/meals?date=YYYY-MM-DD` (which returns
  meals of every status) until a scanned meal reaches `ready`.

## Testing

- `phpunit.xml` uses an **in-memory SQLite** DB (`DB_CONNECTION=sqlite`,
  `DB_DATABASE=:memory:`) with `RefreshDatabase` — no local MySQL server is needed.
- `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`,
  `SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1`, `APP_URL=http://localhost`.
- Sanctum stateful-domain tests (register / login / logout) send `Origin: http://localhost`
  headers.
- `MealScanTest` fakes the queue (`Queue::fake()`) and the public disk
  (`Storage::fake('public')`) and asserts `ProcessFoodScan` is pushed with the right `mealId`.
- `FoodVisionServiceTest` stubs the Gemini HTTP call with `Http::fake()` (normalized items,
  invalid-item dropping, empty result, HTTP failure, malformed JSON); no real network calls.
- `ProcessFoodScanTest` runs the real job against in-memory sqlite with `Http::fake()` for
  Gemini: writes items and marks the meal `ready`, marks `failed` when no food is detected,
  exercises the `failed()` callback, no-ops for a non-draft meal, and re-enters on a
  `processing` meal (retry semantics).
- `MealConfirmTest` locks the confirm lifecycle: `ready` + items → `confirmed`; `draft` → 422;
  `ready` without items → 422; another user's meal → 404.
- Full suite: **53 passed, 121 assertions, 0 failures** (verified with `php artisan test`).
