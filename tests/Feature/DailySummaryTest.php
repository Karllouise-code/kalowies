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
        \App\Services\NutritionCalculator::recalculate($lunch);

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
