# KaloWies Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Laravel 11 API for KaloWies — Sanctum SPA auth, meal/meal-item/goal CRUD, daily summaries, and an async Gemini food-scan pipeline (draft → processing → ready → confirmed).

**Architecture:** Laravel 11 at repo root serves JSON under `/api` with Sanctum cookie-based SPA auth. Food-scan photos are stored on the `public` disk, a draft `Meal` row is created, and a queued `ProcessFoodScan` job calls `FoodVisionService` (Gemini via the `Http` facade) to write `meal_items` and meal totals. Manual meals are created directly as `confirmed`. `NutritionCalculator` always derives meal totals from items.

**Tech Stack:** Laravel 11, PHP 8.4, MySQL, Sanctum, Redis queue (database fallback), Gemini 2.0 Flash, PHPUnit.

## Global Constraints

- Laravel `11.*` scaffolded into the existing repo root (`C:\Users\KARL\Herd\KaloWies`), which already contains `.git` and `docs/`. Scaffold to a temp dir then move contents up (composer refuses non-empty target).
- Enums are string constants on models — no spatie/enum package.
- Meal status flow: `draft → processing → ready → confirmed`, plus `cancelled` and `failed`. Manual meals are created directly as `confirmed`.
- `meals.type` ∈ `breakfast|snack|lunch|dinner`.
- All `meal`/`item`/`goal` routes require `auth:sanctum`; ownership check returns 404 on mismatch (never 403).
- Totals are always recomputed from items via `NutritionCalculator` — never accepted as input.
- Macros/grams are `decimal(8,2)` in MySQL, cast to PHP `float` on the models.
- Image uploads: `image`, `mimes:jpeg,png,webp`, `max:10240` KB, stored on the `public` disk under `meals/`.
- `FoodVisionService` is the only class that talks to Gemini; tests use `Http::fake()`.
- Queue: default `QUEUE_CONNECTION=database` in `.env`; tests run with `QUEUE_CONNECTION=sync` (Laravel default in `phpunit.xml`).
- TDD: every task starts with a failing test, then implementation, then green, then a commit.

---

### Task 1: Scaffold Laravel 11 into the repo root

**Files:**
- Create: whole Laravel skeleton (from `laravel/laravel:11.*`)
- Verify: `artisan`, `composer.json`

**Interfaces:**
- Produces: an installable Laravel app at repo root with default `users` migration, default `phpunit.xml`, default `tests/`.

- [ ] **Step 1: Scaffold into a temp directory**

```powershell
$tmp = "C:\Users\KARL\AppData\Local\Temp\opencode\kalowies-laravel"
if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
composer create-project laravel/laravel:^11.0 $tmp
```

- [ ] **Step 2: Move scaffold contents into the repo root**

```powershell
$tmp = "C:\Users\KARL\AppData\Local\Temp\opencode\kalowies-laravel"
$dest = "C:\Users\KARL\Herd\KaloWies"
Get-ChildItem -Path $tmp -Force | ForEach-Object { Move-Item -Path $_.FullName -Destination $dest -Force }
Remove-Item $tmp -Recurse -Force
```

- [ ] **Step 3: Verify**

Run: `php artisan --version`
Expected: `Laravel Framework 11.x` (no error). Also confirm `artisan` exists at repo root.

- [ ] **Step 4: Sanity-check the test suite runs**

Run: `php artisan test`
Expected: at least the default `ExampleTest` passes (output shows green). If PHPUnit errors on a DB-less environment, ensure `.env` + `.env.example` exist and `php artisan key:generate` was already run by the installer.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel 11"
```

---

### Task 2: Foundation & Sanctum SPA setup

**Files:**
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Create: `resources/views/app.blade.php`
- Modify: `.env`, `.env.example`
- Modify: `config/services.php`
- Modify: `phpunit.xml`
- Test: `tests/Feature/ApiFoundationTest.php`

**Interfaces:**
- Produces: `auth:sanctum` usable on `/api` routes; `GET /` returns the SPA shell view; guest API calls return 401.

- [ ] **Step 1: Install Sanctum and API scaffolding**

Run: `php artisan install:api`
Expected: composer installs `laravel/sanctum`, adds `HasApiTokens` to `app/Models/User.php`, publishes `config/sanctum.php`, creates `routes/api.php`.

- [ ] **Step 2: Configure stateful (cookie-based) Sanctum auth**

Edit `bootstrap/app.php` so the middleware callback becomes:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->statefulApi();
})
```

Keep the existing `api` config line (`->withRouting(api: ..., commands: ..., health: ...)`) unchanged.

- [ ] **Step 3: Add the SPA catch-all route**

Replace the contents of `routes/web.php` with:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
```

- [ ] **Step 4: Create the SPA shell view**

Create `resources/views/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d9488">
    <title>KaloWies</title>
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    @vite(['resources/css/app.css', 'resources/js/main.js'])
</head>
<body class="bg-slate-50 text-slate-800">
    <div id="app"></div>
</body>
</html>
```

> Note: `resources/css/app.css`, `resources/js/main.js`, and `public/icons/` are created in the frontend plan. Until then, browsing `/` in a browser will 500 on the `@vite` directive — that is expected and does not affect API tests.

- [ ] **Step 5: Environment variables**

Edit `.env` and `.env.example` (set values in `.env` only for local secrets; keep keys in `.env.example` with empty values):

```env
APP_NAME=KaloWies
APP_URL=https://kalowies.test

DB_CONNECTION=mysql
DB_DATABASE=kalowies
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database

SESSION_DRIVER=database

SANCTUM_STATEFUL_DOMAINS=kalowies.test,localhost,127.0.0.1

VISION_PROVIDER=gemini
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.0-flash
```

Add to `config/services.php` (append to the array):

```php
'gemini' => [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
],
```

Add to `phpunit.xml` (inside `<php>`):

```xml
<server name="APP_URL" value="http://localhost"/>
<server name="SESSION_DRIVER" value="array"/>
<server name="QUEUE_CONNECTION" value="sync"/>
<server name="SANCTUM_STATEFUL_DOMAINS" value="localhost,127.0.0.1"/>
```

- [ ] **Step 6: Add a temporary protected probe route**

`install:api` leaves `routes/api.php` effectively empty. Add a probe route so the auth stack can be tested now (Task 6 replaces this file entirely):

```php
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
```

- [ ] **Step 7: Write the failing tests**

Create `tests/Feature/ApiFoundationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_protected_api(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_protected_api(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_sanctum_csrf_cookie_endpoint_responds(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertOk();
    }
}
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=ApiFoundationTest`
Expected: all 3 tests PASS — `auth:sanctum` rejects guests with 401, accepts the `actingAs` user (proving `statefulApi()` + `phpunit.xml` Sanctum domains work), and `/sanctum/csrf-cookie` responds.

- [ ] **Step 9: Commit**

```bash
git add bootstrap/app.php routes/web.php routes/api.php resources/views/app.blade.php .env .env.example config/services.php phpunit.xml tests/Feature/ApiFoundationTest.php composer.json composer.lock
git commit -m "feat: configure Sanctum SPA auth and SPA shell"
```

---

### Task 3: Migrations and Eloquent models

**Files:**
- Create: `database/migrations/2026_08_11_000001_create_meals_table.php`
- Create: `database/migrations/2026_08_11_000002_create_meal_items_table.php`
- Create: `database/migrations/2026_08_11_000003_create_user_goals_table.php`
- Create: `app/Models/Meal.php`
- Create: `app/Models/MealItem.php`
- Create: `app/Models/UserGoal.php`
- Test: `tests/Feature/ModelTest.php`

**Interfaces:**
- Produces: `App\Models\Meal` with constants `TYPE_BREAKFAST|TYPE_SNACK|TYPE_LUNCH|TYPE_DINNER`, `TYPES` array, `STATUS_*` constants, `SOURCE_SCAN`, `SOURCE_MANUAL`; relations `items()` (HasMany), `user()` (BelongsTo); scopes `forDate($date)`, `status($status)`; accessor `image_url`; `$fillable`, float casts. `MealItem` with `meal()` relation. `UserGoal` with `defaultValues()`, `user()` relation.

- [ ] **Step 1: Write the failing model test**

Create `tests/Feature/ModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\MealItem;
use App\Models\User;
use App\Models\UserGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_meal_constants_are_defined(): void
    {
        $this->assertContains('breakfast', Meal::TYPES);
        $this->assertContains('lunch', Meal::TYPES);
        $this->assertSame('draft', Meal::STATUS_DRAFT);
        $this->assertSame('confirmed', Meal::STATUS_CONFIRMED);
        $this->assertSame('scan', Meal::SOURCE_SCAN);
    }

    public function test_meal_items_relationship_and_totals_persist(): void
    {
        $user = User::factory()->create();
        $meal = Meal::create([
            'user_id' => $user->id,
            'date' => '2026-08-11',
            'type' => Meal::TYPE_LUNCH,
            'status' => Meal::STATUS_CONFIRMED,
            'source' => Meal::SOURCE_MANUAL,
            'total_calories' => 500,
        ]);

        $meal->items()->create([
            'name' => 'Rice', 'grams' => 150, 'calories' => 200, 'protein' => 4, 'carbs' => 44, 'fat' => 0.4,
        ]);

        $this->assertSame(1, $meal->items()->count());
        $this->assertSame(200.0, $meal->items()->first()->calories);
        $this->assertSame('2026-08-11', $meal->fresh()->date->format('Y-m-d'));
    }

    public function test_meal_for_date_and_status_scopes(): void
    {
        $user = User::factory()->create();
        $meal = Meal::create([
            'user_id' => $user->id,
            'date' => '2026-08-11',
            'type' => Meal::TYPE_DINNER,
            'status' => Meal::STATUS_READY,
            'source' => Meal::SOURCE_SCAN,
        ]);

        $this->assertTrue(Meal::forDate('2026-08-11')->status(Meal::STATUS_READY)->where('user_id', $user->id)->exists());
        $this->assertFalse(Meal::forDate('2026-08-12')->where('user_id', $user->id)->exists());
    }

    public function test_user_goal_defaults(): void
    {
        $this->assertSame(2000, UserGoal::defaultValues()['calorie_goal']);
    }

    public function test_meal_item_belongs_to_meal(): void
    {
        $user = User::factory()->create();
        $meal = Meal::create([
            'user_id' => $user->id,
            'date' => '2026-08-11',
            'type' => Meal::TYPE_SNACK,
            'status' => Meal::STATUS_CONFIRMED,
            'source' => Meal::SOURCE_MANUAL,
        ]);
        $item = $meal->items()->create([
            'name' => 'Apple', 'grams' => 180, 'calories' => 95, 'protein' => 0.5, 'carbs' => 25, 'fat' => 0.3,
        ]);

        $this->assertSame($meal->id, $item->meal->id);
        $this->assertSame(MealItem::class, get_class($meal->items()->first()));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ModelTest`
Expected: FAIL — classes `App\Models\Meal` etc. do not exist.

- [ ] **Step 3: Create the migrations**

Create `database/migrations/2026_08_11_000001_create_meals_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 20);
            $table->string('status', 20)->default('draft');
            $table->string('source', 20);
            $table->string('image_path')->nullable();
            $table->string('note', 500)->nullable();
            $table->decimal('total_calories', 8, 2)->nullable();
            $table->decimal('total_protein', 8, 2)->nullable();
            $table->decimal('total_carbs', 8, 2)->nullable();
            $table->decimal('total_fat', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
```

Create `database/migrations/2026_08_11_000002_create_meal_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('grams', 8, 2);
            $table->decimal('calories', 8, 2);
            $table->decimal('protein', 8, 2);
            $table->decimal('carbs', 8, 2);
            $table->decimal('fat', 8, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('meal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_items');
    }
};
```

Create `database/migrations/2026_08_11_000003_create_user_goals_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('calorie_goal')->default(2000);
            $table->unsignedInteger('protein_grams')->nullable();
            $table->unsignedInteger('carbs_grams')->nullable();
            $table->unsignedInteger('fat_grams')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_goals');
    }
};
```

- [ ] **Step 4: Create the models**

Create `app/Models/Meal.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Meal extends Model
{
    use HasFactory;

    public const TYPE_BREAKFAST = 'breakfast';
    public const TYPE_SNACK = 'snack';
    public const TYPE_LUNCH = 'lunch';
    public const TYPE_DINNER = 'dinner';
    public const TYPES = [self::TYPE_BREAKFAST, self::TYPE_SNACK, self::TYPE_LUNCH, self::TYPE_DINNER];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    public const SOURCE_SCAN = 'scan';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'user_id', 'date', 'type', 'status', 'source', 'image_path', 'note',
        'total_calories', 'total_protein', 'total_carbs', 'total_fat',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'total_calories' => 'float',
        'total_protein' => 'float',
        'total_carbs' => 'float',
        'total_fat' => 'float',
    ];

    protected $appends = ['image_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
```

Create `app/Models/MealItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealItem extends Model
{
    use HasFactory;

    protected $fillable = ['meal_id', 'name', 'grams', 'calories', 'protein', 'carbs', 'fat', 'sort_order'];

    protected $casts = [
        'grams' => 'float',
        'calories' => 'float',
        'protein' => 'float',
        'carbs' => 'float',
        'fat' => 'float',
    ];

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }
}
```

Create `app/Models/UserGoal.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGoal extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'calorie_goal', 'protein_grams', 'carbs_grams', 'fat_grams'];

    protected $casts = [
        'calorie_goal' => 'integer',
        'protein_grams' => 'integer',
        'carbs_grams' => 'integer',
        'fat_grams' => 'integer',
    ];

    public static function defaultValues(): array
    {
        return [
            'calorie_goal' => 2000,
            'protein_grams' => null,
            'carbs_grams' => null,
            'fat_grams' => null,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Run migrations and tests**

Run: `php artisan migrate --force && php artisan test --filter=ModelTest`
Expected: migrations run clean; all 5 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models tests/Feature/ModelTest.php
git commit -m "feat: add meals, meal_items, user_goals migrations and models"
```

---

### Task 4: NutritionCalculator service

**Files:**
- Create: `app/Services/NutritionCalculator.php`
- Test: `tests/Feature/NutritionCalculatorTest.php`

**Interfaces:**
- Produces:
  - `NutritionCalculator::totalsFor(iterable $items): array` → `['calories'=>float,'protein'=>float,'carbs'=>float,'fat'=>float]`, each rounded to 2 decimals.
  - `NutritionCalculator::recalculate(Meal $meal): Meal` — sums `$meal->items`, writes `total_*` columns, saves, returns fresh meal.
- Consumes: `App\Models\Meal`, `App\Models\MealItem` from Task 3.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/NutritionCalculatorTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\User;
use App\Services\NutritionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NutritionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_for_sums_and_rounds(): void
    {
        $item1 = (object) ['calories' => 100.1, 'protein' => 10, 'carbs' => 20, 'fat' => 5];
        $item2 = (object) ['calories' => 50.2, 'protein' => 4, 'carbs' => 7, 'fat' => 1.25];

        $totals = NutritionCalculator::totalsFor([$item1, $item2]);

        $this->assertSame(150.3, $totals['calories']);
        $this->assertSame(14.0, $totals['protein']);
        $this->assertSame(27.0, $totals['carbs']);
        $this->assertSame(6.25, $totals['fat']);
    }

    public function test_totals_for_empty_items_returns_zeros(): void
    {
        $this->assertSame(
            ['calories' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0],
            NutritionCalculator::totalsFor([]),
        );
    }

    public function test_recalculate_writes_totals_on_meal(): void
    {
        $user = User::factory()->create();
        $meal = Meal::create([
            'user_id' => $user->id,
            'date' => '2026-08-11',
            'type' => Meal::TYPE_LUNCH,
            'status' => Meal::STATUS_CONFIRMED,
            'source' => Meal::SOURCE_MANUAL,
        ]);
        $meal->items()->create(['name' => 'A', 'grams' => 100, 'calories' => 200, 'protein' => 10, 'carbs' => 30, 'fat' => 5]);
        $meal->items()->create(['name' => 'B', 'grams' => 50, 'calories' => 100, 'protein' => 5, 'carbs' => 15, 'fat' => 2.5]);

        $recalculated = NutritionCalculator::recalculate($meal);

        $this->assertSame(300.0, $recalculated->total_calories);
        $this->assertSame(15.0, $recalculated->total_protein);
        $this->assertSame(45.0, $recalculated->total_carbs);
        $this->assertSame(7.5, $recalculated->total_fat);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=NutritionCalculatorTest`
Expected: FAIL — class `NutritionCalculator` not found.

- [ ] **Step 3: Implement the service**

Create `app/Services/NutritionCalculator.php`:

```php
<?php

namespace App\Services;

use App\Models\Meal;

class NutritionCalculator
{
    public static function totalsFor(iterable $items): array
    {
        $totals = ['calories' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];

        foreach ($items as $item) {
            $totals['calories'] += (float) $item->calories;
            $totals['protein'] += (float) $item->protein;
            $totals['carbs'] += (float) $item->carbs;
            $totals['fat'] += (float) $item->fat;
        }

        return array_map(fn (float $v): float => round($v, 2), $totals);
    }

    public static function recalculate(Meal $meal): Meal
    {
        $totals = static::totalsFor($meal->items()->get());

        $meal->forceFill($totals)->save();

        return $meal->refresh();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=NutritionCalculatorTest`
Expected: 3 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/NutritionCalculator.php tests/Feature/NutritionCalculatorTest.php
git commit -m "feat: add NutritionCalculator service"
```

---

### Task 5: MealTypeRule validation rule

**Files:**
- Create: `app/Rules/MealTypeRule.php`
- Test: `tests/Unit/MealTypeRuleTest.php`

**Interfaces:**
- Produces: `App\Rules\MealTypeRule implements ValidationRule` with `validate(string $attribute, mixed $value, Closure $fail): void`.
- Consumes: `App\Models\Meal::TYPES`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/MealTypeRuleTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Rules\MealTypeRule;
use PHPUnit\Framework\TestCase;

class MealTypeRuleTest extends TestCase
{
    public function test_passes_for_valid_types(): void
    {
        $rule = new MealTypeRule;
        $fail = fn ($message) => $this->fail("Should not fail: {$message}");

        foreach (['breakfast', 'snack', 'lunch', 'dinner'] as $type) {
            $rule->validate('type', $type, $fail);
        }
        $this->assertTrue(true);
    }

    public function test_fails_for_invalid_type(): void
    {
        $rule = new MealTypeRule;
        $failed = null;
        $fail = function ($message) use (&$failed) {
            $failed = $message;
        };

        $rule->validate('type', 'brunch', $fail);

        $this->assertNotNull($failed);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MealTypeRuleTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the rule**

Create `app/Rules/MealTypeRule.php`:

```php
<?php

namespace App\Rules;

use App\Models\Meal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MealTypeRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array($value, Meal::TYPES, true)) {
            $fail('The selected meal type is invalid.');
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=MealTypeRuleTest`
Expected: 2 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Rules/MealTypeRule.php tests/Unit/MealTypeRuleTest.php
git commit -m "feat: add meal type validation rule"
```

---

### Task 6: Auth endpoints

**Files:**
- Create: `app/Http/Controllers/Api/AuthController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/AuthTest.php`

**Interfaces:**
- Produces:
  - `POST /api/register` → 201 `{user}` (name, email, password, password_confirmation)
  - `POST /api/login` → 200 `{user}`; 422 on bad credentials
  - `POST /api/logout` → 200
  - `GET /api/me` → 200 `{user}` (auth)
- Consumes: `App\Models\User` (default factory, password `password`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AuthTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Karlo',
            'email' => 'karlo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201)->assertJsonPath('user.email', 'karlo@example.com');
        $this->assertDatabaseHas('users', ['email' => 'karlo@example.com']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_login_with_bad_credentials_fails(): void
    {
        $this->postJson('/api/login', ['email' => 'nope@example.com', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_authenticated_user_can_fetch_me(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/logout')->assertOk();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AuthTest`
Expected: FAIL — `POST /api/register` returns 404 (no routes yet).

- [ ] **Step 3: Implement the controller**

Create `app/Http/Controllers/Api/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $request->boolean('remember'))) {
            return response()->json(['message' => 'These credentials do not match our records.'], 422);
        }

        $request->session()->regenerate();

        return response()->json(['user' => Auth::user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }
}
```

- [ ] **Step 4: Register the routes**

Replace the contents of `routes/api.php` with:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=AuthTest`
Expected: 5 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php routes/api.php tests/Feature/AuthTest.php
git commit -m "feat: add register, login, logout, me API endpoints"
```

---

### Task 7: Manual meal CRUD (index, store, show, update, destroy)

**Files:**
- Create: `app/Http/Controllers/Api/MealController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/MealCrudTest.php`

**Interfaces:**
- Produces:
  - `GET /api/meals?date=YYYY-MM-DD` → 200 `{meals:[...]}` (with `items`), only the caller's meals for that date.
  - `POST /api/meals` → 201 `{meal}` with `items`; body `{date, type, note?, items:[{name, grams, calories, protein, carbs, fat}]}`. Meal created `status=confirmed`, `source=manual`, totals computed.
  - `GET /api/meals/{meal}` → 200 `{meal}` (+items).
  - `PUT /api/meals/{meal}` → 200 `{meal}` (date/type/note only).
  - `DELETE /api/meals/{meal}` → 204.
  - Ownership: other users' meals → 404.
- Consumes: `Meal`, `MealItem`, `MealTypeRule`, `NutritionCalculator`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/MealCrudTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_meal(): void
    {
        $this->postJson('/api/meals', [])->assertUnauthorized();
    }

    public function test_user_can_create_manual_meal_with_items_and_totals(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/meals', [
            'date' => '2026-08-11',
            'type' => 'lunch',
            'note' => 'Quick lunch',
            'items' => [
                ['name' => 'Rice', 'grams' => 150, 'calories' => 200, 'protein' => 4, 'carbs' => 44, 'fat' => 0.4],
                ['name' => 'Chicken', 'grams' => 120, 'calories' => 200, 'protein' => 30, 'carbs' => 0, 'fat' => 8],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('meal.status', 'confirmed')
            ->assertJsonPath('meal.source', 'manual')
            ->assertJsonPath('meal.total_calories', 400)
            ->assertJsonCount(2, 'meal.items');
    }

    public function test_manual_meal_requires_items(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/meals', ['date' => '2026-08-11', 'type' => 'lunch', 'items' => []])
            ->assertStatus(422);
    }

    public function test_manual_meal_rejects_invalid_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/meals', [
            'date' => '2026-08-11', 'type' => 'brunch',
            'items' => [['name' => 'X', 'grams' => 1, 'calories' => 1, 'protein' => 0, 'carbs' => 0, 'fat' => 0]],
        ])->assertStatus(422);
    }

    public function test_user_can_list_meals_for_a_date(): void
    {
        $user = User::factory()->create();
        Meal::create(['user_id' => $user->id, 'date' => '2026-08-11', 'type' => 'breakfast', 'status' => 'confirmed', 'source' => 'manual']);
        Meal::create(['user_id' => $user->id, 'date' => '2026-08-12', 'type' => 'lunch', 'status' => 'confirmed', 'source' => 'manual']);

        $this->actingAs($user)->getJson('/api/meals?date=2026-08-11')
            ->assertOk()
            ->assertJsonCount(1, 'meals')
            ->assertJsonPath('meals.0.type', 'breakfast');
    }

    public function test_user_cannot_see_other_users_meals(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $meal = Meal::create(['user_id' => $owner->id, 'date' => '2026-08-11', 'type' => 'lunch', 'status' => 'confirmed', 'source' => 'manual']);

        $this->actingAs($other)->getJson("/api/meals/{$meal->id}")->assertNotFound();
    }

    public function test_user_can_show_update_and_delete_own_meal(): void
    {
        $user = User::factory()->create();
        $meal = Meal::create(['user_id' => $user->id, 'date' => '2026-08-11', 'type' => 'lunch', 'status' => 'confirmed', 'source' => 'manual']);

        $this->actingAs($user)->getJson("/api/meals/{$meal->id}")->assertOk();
        $this->actingAs($user)->putJson("/api/meals/{$meal->id}", ['type' => 'dinner'])
            ->assertOk()->assertJsonPath('meal.type', 'dinner');
        $this->actingAs($user)->deleteJson("/api/meals/{$meal->id}")->assertNoContent();
        $this->assertDatabaseMissing('meals', ['id' => $meal->id]);
    }

    public function test_user_cannot_delete_other_users_meal(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $meal = Meal::create(['user_id' => $owner->id, 'date' => '2026-08-11', 'type' => 'lunch', 'status' => 'confirmed', 'source' => 'manual']);

        $this->actingAs($other)->deleteJson("/api/meals/{$meal->id}")->assertNotFound();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MealCrudTest`
Expected: FAIL — routes 404.

- [ ] **Step 3: Implement the controller**

Create `app/Http/Controllers/Api/MealController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meal;
use App\Rules\MealTypeRule;
use App\Services\NutritionCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $meals = Meal::with('items')
            ->where('user_id', $request->user()->id)
            ->forDate($data['date'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['meals' => $meals]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', new MealTypeRule],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:120'],
            'items.*.grams' => ['required', 'numeric', 'min:0.1', 'max:3000'],
            'items.*.calories' => ['required', 'numeric', 'min:0', 'max:2000'],
            'items.*.protein' => ['required', 'numeric', 'min:0', 'max:500'],
            'items.*.carbs' => ['required', 'numeric', 'min:0', 'max:500'],
            'items.*.fat' => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        $meal = Meal::create([
            'user_id' => $request->user()->id,
            'date' => $data['date'],
            'type' => $data['type'],
            'status' => Meal::STATUS_CONFIRMED,
            'source' => Meal::SOURCE_MANUAL,
            'note' => $data['note'] ?? null,
        ]);

        $items = collect($data['items'])->map(fn (array $item, int $index) => $item + ['sort_order' => $index])->all();
        $meal->items()->createMany($items);

        NutritionCalculator::recalculate($meal);

        return response()->json(['meal' => $meal->load('items')], 201);
    }

    public function show(Request $request, Meal $meal): JsonResponse
    {
        return response()->json(['meal' => $this->owned($request, $meal)->load('items')]);
    }

    public function update(Request $request, Meal $meal): JsonResponse
    {
        $meal = $this->owned($request, $meal);

        $data = $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'type' => ['sometimes', new MealTypeRule],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $meal->update($data);

        return response()->json(['meal' => $meal->load('items')]);
    }

    public function destroy(Request $request, Meal $meal): JsonResponse
    {
        $this->owned($request, $meal)->delete();

        return response()->json(null, 204);
    }

    public function confirm(Request $request, Meal $meal): JsonResponse
    {
        $meal = $this->owned($request, $meal);

        if ($meal->status !== Meal::STATUS_READY) {
            return response()->json(['message' => 'Meal is not ready to confirm.'], 422);
        }

        if ($meal->items()->count() === 0) {
            return response()->json(['message' => 'Add at least one item before confirming.'], 422);
        }

        NutritionCalculator::recalculate($meal);
        $meal->forceFill(['status' => Meal::STATUS_CONFIRMED, 'note' => null])->save();

        return response()->json(['meal' => $meal->load('items')]);
    }

    private function owned(Request $request, Meal $meal): Meal
    {
        return Meal::where('id', $meal->id)->where('user_id', $request->user()->id)->firstOrFail();
    }
}
```

> `confirm()` is included here (it lives on MealController); it is fully tested in Task 13.

- [ ] **Step 4: Register the routes**

Append inside the existing `auth:sanctum` group in `routes/api.php`:

```php
Route::get('/meals', [MealController::class, 'index']);
Route::post('/meals', [MealController::class, 'store']);
Route::get('/meals/{meal}', [MealController::class, 'show']);
Route::put('/meals/{meal}', [MealController::class, 'update']);
Route::delete('/meals/{meal}', [MealController::class, 'destroy']);
Route::post('/meals/{meal}/confirm', [MealController::class, 'confirm']);
```

Add the import: `use App\Http\Controllers\Api\MealController;`

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=MealCrudTest`
Expected: 9 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/MealController.php routes/api.php tests/Feature/MealCrudTest.php
git commit -m "feat: add manual meal CRUD API"
```

---

### Task 8: Meal item editing (update, destroy)

**Files:**
- Create: `app/Http/Controllers/Api/MealItemController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/MealItemTest.php`

**Interfaces:**
- Produces:
  - `PUT /api/meal-items/{item}` → 200 `{item}`; updates name/grams/calories/protein/carbs/fat, then recomputes the parent meal totals.
  - `DELETE /api/meal-items/{item}` → 204; recomputes parent meal totals.
  - Ownership: items on other users' meals → 404.
- Consumes: `MealItem`, `NutritionCalculator`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/MealItemTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\MealItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealItemTest extends TestCase
{
    use RefreshDatabase;

    private function makeMeal(User $user): Meal
    {
        return Meal::create([
            'user_id' => $user->id,
            'date' => '2026-08-11',
            'type' => 'lunch',
            'status' => 'confirmed',
            'source' => 'manual',
        ]);
    }

    public function test_user_can_update_item_and_totals_recompute(): void
    {
        $user = User::factory()->create();
        $meal = $this->makeMeal($user);
        $item = $meal->items()->create(['name' => 'Rice', 'grams' => 150, 'calories' => 200, 'protein' => 4, 'carbs' => 44, 'fat' => 0.4]);

        $this->actingAs($user)->putJson("/api/meal-items/{$item->id}", ['grams' => 300])
            ->assertOk()
            ->assertJsonPath('item.grams', 300);

        $this->assertSame(400.0, $meal->fresh()->total_calories);
    }

    public function test_user_can_delete_item_and_totals_recompute(): void
    {
        $user = User::factory()->create();
        $meal = $this->makeMeal($user);
        $meal->items()->create(['name' => 'Rice', 'grams' => 150, 'calories' => 200, 'protein' => 4, 'carbs' => 44, 'fat' => 0.4]);
        $item = $meal->items()->create(['name' => 'Chicken', 'grams' => 100, 'calories' => 200, 'protein' => 30, 'carbs' => 0, 'fat' => 8]);

        $this->actingAs($user)->deleteJson("/api/meal-items/{$item->id}")->assertNoContent();

        $this->assertSame(200.0, $meal->fresh()->total_calories);
        $this->assertSame(1, $meal->items()->count());
    }

    public function test_user_cannot_update_other_users_item(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $meal = $this->makeMeal($owner);
        $item = $meal->items()->create(['name' => 'Rice', 'grams' => 150, 'calories' => 200, 'protein' => 4, 'carbs' => 44, 'fat' => 0.4]);

        $this->actingAs($other)->putJson("/api/meal-items/{$item->id}", ['grams' => 500])->assertNotFound();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MealItemTest`
Expected: FAIL — routes 404.

- [ ] **Step 3: Implement the controller**

Create `app/Http/Controllers/Api/MealItemController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MealItem;
use App\Services\NutritionCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealItemController extends Controller
{
    public function update(Request $request, MealItem $item): JsonResponse
    {
        $item = $this->owned($request, $item);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'grams' => ['sometimes', 'numeric', 'min:0.1', 'max:3000'],
            'calories' => ['sometimes', 'numeric', 'min:0', 'max:2000'],
            'protein' => ['sometimes', 'numeric', 'min:0', 'max:500'],
            'carbs' => ['sometimes', 'numeric', 'min:0', 'max:500'],
            'fat' => ['sometimes', 'numeric', 'min:0', 'max:500'],
        ]);

        $item->update($data);
        NutritionCalculator::recalculate($item->meal);

        return response()->json(['item' => $item->fresh()]);
    }

    public function destroy(Request $request, MealItem $item): JsonResponse
    {
        $this->owned($request, $item)->delete();
        NutritionCalculator::recalculate($item->meal);

        return response()->json(null, 204);
    }

    private function owned(Request $request, MealItem $item): MealItem
    {
        return MealItem::where('id', $item->id)
            ->whereHas('meal', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();
    }
}
```

- [ ] **Step 4: Register the routes**

Append inside the `auth:sanctum` group in `routes/api.php`:

```php
Route::put('/meal-items/{item}', [MealItemController::class, 'update']);
Route::delete('/meal-items/{item}', [MealItemController::class, 'destroy']);
```

Add the import: `use App\Http\Controllers\Api\MealItemController;`

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=MealItemTest`
Expected: 3 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/MealItemController.php routes/api.php tests/Feature/MealItemTest.php
git commit -m "feat: add meal item update and delete API"
```

---

### Task 9: Daily summary endpoint

**Files:**
- Create: `app/Http/Controllers/Api/DailySummaryController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/DailySummaryTest.php`

**Interfaces:**
- Produces: `GET /api/daily-summary?date=YYYY-MM-DD` → 200
  ```json
  {
    "date": "2026-08-11",
    "meals": [...confirmed meals with items...],
    "totals": {"calories": 400.0, "protein": 34.0, "carbs": 44.0, "fat": 8.4},
    "goal": {...active UserGoal or defaults...},
    "remaining": {"calories": 1600},
    "per_meal_type": {"breakfast": {"meals": 0, "calories": 0.0}, "snack": {...}, "lunch": {...}, "dinner": {...}}
  }
  ```
  Only `status=confirmed` meals count toward totals.
- Consumes: `Meal`, `UserGoal`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/DailySummaryTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\User;
use App\Models\UserGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_aggregates_confirmed_meals_only(): void
    {
        $user = User::factory()->create();
        $user->goals()->create(['calorie_goal' => 2000]);

        $lunch = Meal::create(['user_id' => $user->id, 'date' => '2026-08-11', 'type' => 'lunch', 'status' => 'confirmed', 'source' => 'manual']);
        $lunch->items()->create(['name' => 'Rice', 'grams' => 150, 'calories' => 200, 'protein' => 4, 'carbs' => 44, 'fat' => 0.4]);
        $lunch->items()->create(['name' => 'Chicken', 'grams' => 120, 'calories' => 200, 'protein' => 30, 'carbs' => 0, 'fat' => 8]);

        Meal::create(['user_id' => $user->id, 'date' => '2026-08-11', 'type' => 'dinner', 'status' => 'draft', 'source' => 'scan']);
        Meal::create(['user_id' => $user->id, 'date' => '2026-08-12', 'type' => 'breakfast', 'status' => 'confirmed', 'source' => 'manual']);

        $this->actingAs($user)->getJson('/api/daily-summary?date=2026-08-11')
            ->assertOk()
            ->assertJsonPath('totals.calories', 400)
            ->assertJsonPath('totals.protein', 34)
            ->assertJsonPath('totals.carbs', 44)
            ->assertJsonPath('totals.fat', 8.4)
            ->assertJsonPath('remaining.calories', 1600)
            ->assertJsonPath('per_meal_type.lunch.meals', 1)
            ->assertJsonCount(1, 'meals');
    }

    public function test_summary_creates_default_goal_when_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/daily-summary?date=2026-08-11')
            ->assertOk()
            ->assertJsonPath('goal.calorie_goal', 2000);

        $this->assertDatabaseHas('user_goals', ['user_id' => $user->id]);
    }
}
```

> Note: `$user->goals()` requires a `goals()` relationship on `User`. Add it to `app/Models/User.php` in Step 3.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=DailySummaryTest`
Expected: FAIL — route 404 (and/or missing `goals()` relation).

- [ ] **Step 3: Add the `goals()` relationship to User**

Edit `app/Models/User.php` — add:

```php
use Illuminate\Database\Eloquent\Relations\HasOne;

public function goals(): HasOne
{
    return $this->hasOne(UserGoal::class);
}
```

(Keep the existing `HasApiTokens`, `HasFactory`, `Notifiable` traits.)

- [ ] **Step 4: Implement the controller**

Create `app/Http/Controllers/Api/DailySummaryController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meal;
use App\Models\UserGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailySummaryController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $user = $request->user();

        $meals = Meal::with('items')
            ->where('user_id', $user->id)
            ->forDate($data['date'])
            ->where('status', Meal::STATUS_CONFIRMED)
            ->orderBy('created_at')
            ->get();

        $totals = ['calories' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];
        $perMealType = array_fill_keys(Meal::TYPES, ['meals' => 0, 'calories' => 0.0]);

        foreach ($meals as $meal) {
            foreach (['calories', 'protein', 'carbs', 'fat'] as $key) {
                $totals[$key] += (float) $meal->{'total_' . $key};
            }
            $perMealType[$meal->type]['meals'] += 1;
            $perMealType[$meal->type]['calories'] += (float) $meal->total_calories;
        }

        $goal = UserGoal::firstOrCreate(['user_id' => $user->id], UserGoal::defaultValues());

        return response()->json([
            'date' => $data['date'],
            'meals' => $meals,
            'totals' => array_map(fn (float $v): float => round($v, 2), $totals),
            'goal' => $goal,
            'remaining' => ['calories' => max(0, (int) $goal->calorie_goal - (int) $totals['calories'])],
            'per_meal_type' => $perMealType,
        ]);
    }
}
```

- [ ] **Step 5: Register the route**

Append inside the `auth:sanctum` group in `routes/api.php`:

```php
Route::get('/daily-summary', [DailySummaryController::class, 'show']);
```

Add the import: `use App\Http\Controllers\Api\DailySummaryController;`

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=DailySummaryTest`
Expected: 2 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/DailySummaryController.php app/Models/User.php routes/api.php tests/Feature/DailySummaryTest.php
git commit -m "feat: add daily summary endpoint"
```

---

### Task 10: Goals endpoints

**Files:**
- Create: `app/Http/Controllers/Api/GoalController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/GoalTest.php`

**Interfaces:**
- Produces:
  - `GET /api/goals` → 200 `{goal}` (creates default if missing).
  - `PUT /api/goals` → 200 `{goal}`; body `{calorie_goal, protein_grams?, carbs_grams?, fat_grams?}`.
- Consumes: `UserGoal`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/GoalTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_goals_creates_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/goals')
            ->assertOk()
            ->assertJsonPath('goal.calorie_goal', 2000);
    }

    public function test_user_can_update_goals(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/goals', [
            'calorie_goal' => 2200,
            'protein_grams' => 120,
            'carbs_grams' => 250,
            'fat_grams' => 60,
        ])->assertOk()
            ->assertJsonPath('goal.calorie_goal', 2200)
            ->assertJsonPath('goal.protein_grams', 120);

        $this->assertDatabaseHas('user_goals', ['user_id' => $user->id, 'calorie_goal' => 2200]);
    }

    public function test_goal_validation_rejects_negative_calories(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/goals', ['calorie_goal' => -10])->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=GoalTest`
Expected: FAIL — route 404.

- [ ] **Step 3: Implement the controller**

Create `app/Http/Controllers/Api/GoalController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $goal = UserGoal::firstOrCreate(['user_id' => $request->user()->id], UserGoal::defaultValues());

        return response()->json(['goal' => $goal]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'calorie_goal' => ['required', 'integer', 'min:500', 'max:10000'],
            'protein_grams' => ['nullable', 'integer', 'min:0', 'max:500'],
            'carbs_grams' => ['nullable', 'integer', 'min:0', 'max:500'],
            'fat_grams' => ['nullable', 'integer', 'min:0', 'max:500'],
        ]);

        $goal = UserGoal::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'calorie_goal' => $data['calorie_goal'],
                'protein_grams' => $data['protein_grams'] ?? null,
                'carbs_grams' => $data['carbs_grams'] ?? null,
                'fat_grams' => $data['fat_grams'] ?? null,
            ]
        );

        return response()->json(['goal' => $goal]);
    }
}
```

- [ ] **Step 4: Register the routes**

Append inside the `auth:sanctum` group in `routes/api.php`:

```php
Route::get('/goals', [GoalController::class, 'index']);
Route::put('/goals', [GoalController::class, 'update']);
```

Add the import: `use App\Http\Controllers\Api\GoalController;`

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=GoalTest`
Expected: 3 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/GoalController.php routes/api.php tests/Feature/GoalTest.php
git commit -m "feat: add goals get and update endpoints"
```

---

### Task 11: FoodVisionService (Gemini integration)

**Files:**
- Create: `app/Services/FoodVisionService.php`
- Test: `tests/Unit/FoodVisionServiceTest.php`

**Interfaces:**
- Produces: `App\Services\FoodVisionService::analyze(string $imagePath): array`
  - Reads `$imagePath` from the `public` disk.
  - Calls Gemini `:generateContent` with `responseMimeType=application/json` + `responseSchema`, base64 inline image.
  - On model 404, retries with fallback model `gemini-1.5-flash`.
  - Returns `['items' => [ ['name'=>string,'grams'=>float,'calories'=>float,'protein'=>float,'carbs'=>float,'fat'=>float], ... ]]`.
  - Throws `RuntimeException` on HTTP failure, empty response, or malformed JSON. Invalid items are dropped.
- Consumes: `config('services.gemini.api_key')`, `config('services.gemini.model')`.
- Depends: `.env` `GEMINI_API_KEY` set; image stored on `public` disk.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/FoodVisionServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\FoodVisionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FoodVisionServiceTest extends TestCase
{
    private function fakeImage(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('meals/meal.jpg', 'fake-image-bytes');
    }

    public function test_analyze_returns_normalized_items(): void
    {
        $this->fakeImage();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['items' => [
                        ['name' => 'Grilled Chicken', 'grams' => 150, 'calories' => 247.4, 'protein' => 37.5, 'carbs' => 0, 'fat' => 5.34],
                        ['name' => 'White Rice', 'grams' => 200, 'calories' => 260, 'protein' => 5.3, 'carbs' => 56, 'fat' => 0.6],
                    ]])]]]],
                ],
            ]),
        ]);

        $items = app(FoodVisionService::class)->analyze('meals/meal.jpg');

        $this->assertCount(2, $items['items']);
        $this->assertSame('Grilled Chicken', $items['items'][0]['name']);
        $this->assertSame(247.4, $items['items'][0]['calories']);
        $this->assertSame(5.3, $items['items'][0]['fat']);
    }

    public function test_analyze_drops_invalid_items(): void
    {
        $this->fakeImage();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['items' => [
                        ['name' => '', 'grams' => 150, 'calories' => 247, 'protein' => 10, 'carbs' => 0, 'fat' => 5],
                        ['name' => 'Valid', 'grams' => 9999, 'calories' => 99999, 'protein' => 10, 'carbs' => 0, 'fat' => 5],
                        ['name' => 'Banana', 'grams' => 120, 'calories' => 105, 'protein' => 1.3, 'carbs' => 27, 'fat' => 0.4],
                    ]])]]]],
                ],
            ]),
        ]);

        $items = app(FoodVisionService::class)->analyze('meals/meal.jpg');

        $this->assertCount(1, $items['items']);
        $this->assertSame('Banana', $items['items'][0]['name']);
    }

    public function test_analyze_returns_empty_for_no_food(): void
    {
        $this->fakeImage();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => '{"items":[]}']]]],
                ],
            ]),
        ]);

        $this->assertSame(['items' => []], app(FoodVisionService::class)->analyze('meals/meal.jpg'));
    }

    public function test_analyze_throws_on_http_failure(): void
    {
        $this->fakeImage();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('boom', 500)]);

        $this->expectException(\RuntimeException::class);
        app(FoodVisionService::class)->analyze('meals/meal.jpg');
    }

    public function test_analyze_throws_on_malformed_json(): void
    {
        $this->fakeImage();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'not json at all']]]],
                ],
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        app(FoodVisionService::class)->analyze('meals/meal.jpg');
    }
}
```

> These tests run with a real `TestCase` (not the bare `PHPUnit\Framework\TestCase`) so `Http::fake()` and `Storage::fake()` work.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=FoodVisionServiceTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the service**

Create `app/Services/FoodVisionService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FoodVisionService
{
    private const FALLBACK_MODEL = 'gemini-1.5-flash';

    private const PROMPT = <<<'EOT'
Identify every food item in this image. For each item, estimate the portion size in grams (or a common serving). Scale all macronutrients to the estimated portion. Return ONLY JSON matching this structure, with no commentary:
{
  "items": [
    { "name": "grilled chicken breast", "grams": 150, "calories": 247, "protein": 37.5, "carbs": 0, "fat": 5.3 }
  ]
}
If no food is recognizable, return { "items": [] }.
EOT;

    public function analyze(string $imagePath): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($imagePath)) {
            throw new RuntimeException('Stored image not found.');
        }

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => self::PROMPT],
                    ['inline_data' => [
                        'mime_type' => $disk->mimeType($imagePath) ?: 'image/jpeg',
                        'data' => base64_encode($disk->get($imagePath)),
                    ]],
                ],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'items' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'name' => ['type' => 'STRING'],
                                    'grams' => ['type' => 'NUMBER'],
                                    'calories' => ['type' => 'NUMBER'],
                                    'protein' => ['type' => 'NUMBER'],
                                    'carbs' => ['type' => 'NUMBER'],
                                    'fat' => ['type' => 'NUMBER'],
                                ],
                                'required' => ['name', 'grams', 'calories', 'protein', 'carbs', 'fat'],
                            ],
                        ],
                    ],
                    'required' => ['items'],
                ],
            ],
        ];

        $response = Http::withOptions(['timeout' => 45])->post($this->endpoint(config('services.gemini.model', 'gemini-2.0-flash')), $payload);

        if ($response->status() === 404) {
            $response = Http::withOptions(['timeout' => 45])->post($this->endpoint(self::FALLBACK_MODEL), $payload);
        }

        if ($response->failed()) {
            Log::error('Gemini vision request failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('Vision API request failed.');
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Vision API returned an empty response.');
        }

        return $this->parseItems($text);
    }

    private function endpoint(string $model): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.config('services.gemini.api_key');
    }

    private function parseItems(string $jsonText): array
    {
        $decoded = json_decode($jsonText, true);

        if (! is_array($decoded) || ! isset($decoded['items']) || ! is_array($decoded['items'])) {
            throw new RuntimeException('Vision API returned malformed JSON.');
        }

        $items = [];

        foreach ($decoded['items'] as $entry) {
            $name = is_string($entry['name'] ?? null) ? trim($entry['name']) : '';
            $grams = (float) ($entry['grams'] ?? 0);
            $calories = (float) ($entry['calories'] ?? 0);
            $protein = (float) ($entry['protein'] ?? 0);
            $carbs = (float) ($entry['carbs'] ?? 0);
            $fat = (float) ($entry['fat'] ?? 0);

            if ($name === '' || $grams < 1 || $grams > 3000 || $calories < 1 || $calories > 2000) {
                continue;
            }

            $items[] = [
                'name' => mb_substr($name, 0, 120),
                'grams' => round($grams, 2),
                'calories' => round($calories, 1),
                'protein' => round($protein, 1),
                'carbs' => round($carbs, 1),
                'fat' => round($fat, 1),
            ];
        }

        return ['items' => $items];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=FoodVisionServiceTest`
Expected: 5 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/FoodVisionService.php tests/Unit/FoodVisionServiceTest.php
git commit -m "feat: add FoodVisionService with Gemini vision integration"
```

---

### Task 12: Scan endpoint (upload + draft meal + dispatch)

**Files:**
- Create: `app/Http/Controllers/Api/MealScanController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/MealScanTest.php`

**Interfaces:**
- Produces: `POST /api/meals/scan` (multipart: `image`, `date`, `type`) → 201 `{meal}` with `status=draft`, `source=scan`, `image_path` set. Dispatches `ProcessFoodScan` on the meal id.
- Consumes: `Meal`, `ProcessFoodScan` job (defined in Task 13 — this task dispatches it; the test fakes the queue and asserts a draft is returned).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MealScanTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\ProcessFoodScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MealScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_creates_draft_meal_and_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/api/meals/scan', [
            'image' => UploadedFile::fake()->image('meal.jpg'),
            'date' => '2026-08-11',
            'type' => 'dinner',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('meal.status', 'draft')
            ->assertJsonPath('meal.source', 'scan');

        $mealId = $response->json('meal.id');
        $this->assertDatabaseHas('meals', ['id' => $mealId, 'status' => 'draft']);

        Queue::assertPushed(ProcessFoodScan::class, fn ($job) => $job->mealId === $mealId);
    }

    public function test_scan_requires_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/meals/scan', ['date' => '2026-08-11', 'type' => 'dinner'])
            ->assertStatus(422);
    }

    public function test_scan_rejects_invalid_image_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/meals/scan', [
            'image' => UploadedFile::fake()->create('meal.txt', 100),
            'date' => '2026-08-11',
            'type' => 'dinner',
        ])->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MealScanTest`
Expected: FAIL — route 404 and `ProcessFoodScan` class missing.

- [ ] **Step 3: Create a placeholder job (full impl in Task 13)**

Create `app/Jobs/ProcessFoodScan.php`:

```php
<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessFoodScan implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $mealId) {}

    public function handle(): void
    {
        // Implemented in Task 13.
    }
}
```

- [ ] **Step 4: Implement the controller**

Create `app/Http/Controllers/Api/MealScanController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFoodScan;
use App\Models\Meal;
use App\Rules\MealTypeRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealScanController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', new MealTypeRule],
        ]);

        $path = $request->file('image')->store('meals', 'public');

        $meal = Meal::create([
            'user_id' => $request->user()->id,
            'date' => $data['date'],
            'type' => $data['type'],
            'status' => Meal::STATUS_DRAFT,
            'source' => Meal::SOURCE_SCAN,
            'image_path' => $path,
        ]);

        ProcessFoodScan::dispatch($meal->id);

        return response()->json(['meal' => $meal], 201);
    }
}
```

- [ ] **Step 5: Register the route**

Append inside the `auth:sanctum` group in `routes/api.php` (before the `/meals/{meal}` routes so `scan` is not captured as an id):

```php
Route::post('/meals/scan', [MealScanController::class, 'scan']);
```

Add the import: `use App\Http\Controllers\Api\MealScanController;`

- [ ] **Step 6: Link storage so images are publicly served**

Run: `php artisan storage:link`
Expected: symlink `public/storage` → `storage/app/public`.

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=MealScanTest`
Expected: 3 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/MealScanController.php app/Jobs/ProcessFoodScan.php routes/api.php tests/Feature/MealScanTest.php
git commit -m "feat: add meal scan endpoint with draft meal and queued job"
```

---

### Task 13: ProcessFoodScan job (draft → processing → ready/failed)

**Files:**
- Modify: `app/Jobs/ProcessFoodScan.php`
- Test: `tests/Feature/ProcessFoodScanTest.php`

**Interfaces:**
- Produces: full `ProcessFoodScan` job:
  - `handle(FoodVisionService $vision): void` — no-op unless meal exists and is `draft`; sets `processing`; calls `analyze`; on non-empty items writes them, recomputes totals, sets `ready` (clears note); on empty items sets `failed` with note `No food was detected in the image. Please try again.`
  - `failed(Throwable $e): void` — sets `failed` with note `Could not analyze this image. Please try again.`
  - `public int $tries = 2; public int $timeout = 60; public array $backoff = [10, 30];`
- Consumes: `FoodVisionService`, `NutritionCalculator`, `Meal`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/ProcessFoodScanTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\ProcessFoodScan;
use App\Models\Meal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessFoodScanTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGeminiResponse(array $items): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['items' => $items])]]]],
                ],
            ]),
        ]);
    }

    private function makeDraft(User $user): Meal
    {
        Storage::fake('public');
        Storage::disk('public')->put('meals/meal.jpg', 'bytes');
        $meal = Meal::create([
            'user_id' => $user->id,
            'date' => '2026-08-11',
            'type' => 'dinner',
            'status' => 'draft',
            'source' => 'scan',
            'image_path' => 'meals/meal.jpg',
        ]);
        return $meal;
    }

    public function test_job_writes_items_and_marks_meal_ready(): void
    {
        $user = User::factory()->create();
        $meal = $this->makeDraft($user);
        $this->fakeGeminiResponse([
            ['name' => 'Pasta', 'grams' => 250, 'calories' => 350, 'protein' => 12, 'carbs' => 70, 'fat' => 2],
            ['name' => 'Meatballs', 'grams' => 100, 'calories' => 250, 'protein' => 18, 'carbs' => 5, 'fat' => 17],
        ]);

        (new ProcessFoodScan($meal->id))->handle(app(\App\Services\FoodVisionService::class));

        $meal->refresh();
        $this->assertSame('ready', $meal->status);
        $this->assertSame(2, $meal->items()->count());
        $this->assertSame(600.0, $meal->total_calories);
        $this->assertSame(30.0, $meal->total_protein);
    }

    public function test_job_marks_failed_when_no_food_detected(): void
    {
        $user = User::factory()->create();
        $meal = $this->makeDraft($user);
        $this->fakeGeminiResponse([]);

        (new ProcessFoodScan($meal->id))->handle(app(\App\Services\FoodVisionService::class));

        $meal->refresh();
        $this->assertSame('failed', $meal->status);
        $this->assertSame('No food was detected in the image. Please try again.', $meal->note);
        $this->assertSame(0, $meal->items()->count());
    }

    public function test_failed_callback_marks_meal_failed(): void
    {
        $user = User::factory()->create();
        $meal = $this->makeDraft($user);

        (new ProcessFoodScan($meal->id))->failed(new \RuntimeException('boom'));

        $meal->refresh();
        $this->assertSame('failed', $meal->status);
        $this->assertSame('Could not analyze this image. Please try again.', $meal->note);
    }

    public function test_job_does_nothing_when_meal_already_confirmed(): void
    {
        $user = User::factory()->create();
        $meal = Meal::create([
            'user_id' => $user->id,
            'date' => '2026-08-11',
            'type' => 'dinner',
            'status' => 'confirmed',
            'source' => 'scan',
        ]);

        (new ProcessFoodScan($meal->id))->handle(app(\App\Services\FoodVisionService::class));

        $this->assertSame('confirmed', $meal->fresh()->status);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ProcessFoodScanTest`
Expected: FAIL — placeholder `handle()` does nothing.

- [ ] **Step 3: Implement the full job**

Replace the contents of `app/Jobs/ProcessFoodScan.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Meal;
use App\Services\FoodVisionService;
use App\Services\NutritionCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessFoodScan implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;
    public array $backoff = [10, 30];

    public function __construct(public int $mealId) {}

    public function handle(FoodVisionService $vision): void
    {
        $meal = Meal::find($this->mealId);

        if (! $meal || $meal->status !== Meal::STATUS_DRAFT) {
            return;
        }

        if (! $meal->image_path) {
            $this->fail($meal, 'No image attached to this scan.');
            return;
        }

        $meal->forceFill(['status' => Meal::STATUS_PROCESSING])->save();

        $result = $vision->analyze($meal->image_path);

        if (empty($result['items'])) {
            $this->fail($meal, 'No food was detected in the image. Please try again.');
            return;
        }

        $meal->items()->createMany($result['items']);
        NutritionCalculator::recalculate($meal);
        $meal->forceFill(['status' => Meal::STATUS_READY, 'note' => null])->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->fail(Meal::find($this->mealId), 'Could not analyze this image. Please try again.');
    }

    private function fail(?Meal $meal, string $message): void
    {
        if (! $meal) {
            return;
        }

        $meal->forceFill(['status' => Meal::STATUS_FAILED, 'note' => $message])->save();
        Log::warning("Food scan failed for meal {$meal->id}: {$message}");
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=ProcessFoodScanTest`
Expected: 4 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/ProcessFoodScan.php tests/Feature/ProcessFoodScanTest.php
git commit -m "feat: process food scans end to end in queued job"
```

---

### Task 14: Confirm endpoint tests (full scan lifecycle)

**Files:**
- Test: `tests/Feature/MealConfirmTest.php`

**Interfaces:**
- Verifies: `POST /api/meals/{meal}/confirm` (already defined on `MealController` in Task 7):
  - `ready` meal with items → `confirmed`, totals recomputed.
  - `draft` meal → 422.
  - `ready` meal with no items → 422.
  - Other users' meals → 404.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/MealConfirmTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealConfirmTest extends TestCase
{
    use RefreshDatabase;

    public function test_ready_meal_can_be_confirmed(): void
    {
        $user = User::factory()->create();
        $meal = Meal::create(['user_id' => $user->id, 'date' => '2026-08-11', 'type' => 'dinner', 'status' => 'ready', 'source' => 'scan']);
        $meal->items()->create(['name' => 'Pasta', 'grams' => 250, 'calories' => 350, 'protein' => 12, 'carbs' => 70, 'fat' => 2]);

        $this->actingAs($user)->postJson("/api/meals/{$meal->id}/confirm")
            ->assertOk()
            ->assertJsonPath('meal.status', 'confirmed');
    }

    public function test_draft_meal_cannot_be_confirmed(): void
    {
        $user = User::factory()->create();
        $meal = Meal::create(['user_id' => $user->id, 'date' => '2026-08-11', 'type' => 'dinner', 'status' => 'draft', 'source' => 'scan']);

        $this->actingAs($user)->postJson("/api/meals/{$meal->id}/confirm")->assertStatus(422);
    }

    public function test_ready_meal_without_items_cannot_be_confirmed(): void
    {
        $user = User::factory()->create();
        $meal = Meal::create(['user_id' => $user->id, 'date' => '2026-08-11', 'type' => 'dinner', 'status' => 'ready', 'source' => 'scan']);

        $this->actingAs($user)->postJson("/api/meals/{$meal->id}/confirm")->assertStatus(422);
    }

    public function test_cannot_confirm_other_users_meal(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $meal = Meal::create(['user_id' => $owner->id, 'date' => '2026-08-11', 'type' => 'dinner', 'status' => 'ready', 'source' => 'scan']);
        $meal->items()->create(['name' => 'Pasta', 'grams' => 250, 'calories' => 350, 'protein' => 12, 'carbs' => 70, 'fat' => 2]);

        $this->actingAs($other)->postJson("/api/meals/{$meal->id}/confirm")->assertNotFound();
    }
}
```

- [ ] **Step 2: Run tests to verify they pass**

Run: `php artisan test --filter=MealConfirmTest`
Expected: 4 tests PASS (the `confirm` method from Task 7 already implements this).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/MealConfirmTest.php
git commit -m "test: cover meal confirm lifecycle"
```

---

### Task 15: Full backend verification & queue worker docs

**Files:**
- Modify: `README.md`

**Interfaces:**
- Produces: a green full suite, a documented worker command, and a runnable dev setup.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: ALL tests PASS (foundation, models, calculator, rule, auth, meals, items, summary, goals, vision, scan, job, confirm). No failures or errors.

- [ ] **Step 2: Verify route list**

Run: `php artisan route:list --except-vendor`
Expected: all `/api` routes above present; `auth:sanctum` middleware on the protected group; `POST /api/meals/scan` is not shadowed by `/api/meals/{meal}`.

- [ ] **Step 3: Write dev setup docs**

Append to `README.md`:

```markdown
## KaloWies

See your calories clearly. Photo-based calorie tracking (Laravel 11 API + Vue 3 PWA).

### Backend setup

1. `cp .env.example .env` and configure MySQL credentials, `APP_URL=https://kalowies.test`,
   `SANCTUM_STATEFUL_DOMAINS=kalowies.test`, `GEMINI_API_KEY=...` (Google AI Studio).
2. `php artisan key:generate`
3. `php artisan migrate`
4. `php artisan storage:link`

### Running the food-scan worker

```bash
php artisan queue:work
```

Dev default is `QUEUE_CONNECTION=database`. Swap `.env` to `QUEUE_CONNECTION=redis`
when Redis is running; the `database` driver is the zero-setup fallback.

### API tests

```bash
php artisan test
```
```

- [ ] **Step 4: Commit**

```bash
git add README.md
git commit -m "docs: add backend setup and queue worker docs"
```
