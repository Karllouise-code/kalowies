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
