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
