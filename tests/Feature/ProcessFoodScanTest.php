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
        return $this->makeMeal($user, 'draft');
    }

    private function makeMeal(User $user, string $status): Meal
    {
        Storage::fake('public');
        Storage::disk('public')->put('meals/meal.jpg', 'bytes');
        $meal = Meal::create([
            'user_id' => $user->id,
            'date' => '2026-08-11',
            'type' => 'dinner',
            'status' => $status,
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

    public function test_job_reruns_analysis_when_meal_is_processing(): void
    {
        $user = User::factory()->create();
        $meal = $this->makeMeal($user, 'processing');
        $this->fakeGeminiResponse([
            ['name' => 'Salad', 'grams' => 200, 'calories' => 120, 'protein' => 3, 'carbs' => 10, 'fat' => 8],
        ]);

        (new ProcessFoodScan($meal->id))->handle(app(\App\Services\FoodVisionService::class));

        $meal->refresh();
        $this->assertSame('ready', $meal->status);
        $this->assertSame(1, $meal->items()->count());
        $this->assertSame(120.0, $meal->total_calories);
    }

    public function test_does_not_duplicate_items_on_retry(): void
    {
        $user = User::factory()->create();
        $meal = $this->makeDraft($user);

        $meal->items()->createMany([
            ['name' => 'Old Pasta', 'grams' => 200, 'calories' => 300, 'protein' => 10, 'carbs' => 60, 'fat' => 1],
            ['name' => 'Old Bread', 'grams' => 50, 'calories' => 130, 'protein' => 4, 'carbs' => 24, 'fat' => 2],
        ]);

        $this->fakeGeminiResponse([
            ['name' => 'New Salad', 'grams' => 150, 'calories' => 90, 'protein' => 2, 'carbs' => 8, 'fat' => 6],
            ['name' => 'New Soup', 'grams' => 250, 'calories' => 110, 'protein' => 5, 'carbs' => 12, 'fat' => 4],
            ['name' => 'New Cake', 'grams' => 100, 'calories' => 350, 'protein' => 4, 'carbs' => 50, 'fat' => 16],
        ]);

        (new ProcessFoodScan($meal->id))->handle(app(\App\Services\FoodVisionService::class));

        $this->assertSame(3, $meal->fresh()->items()->count());
    }
}
