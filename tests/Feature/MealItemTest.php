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

        $this->assertSame(200.0, $meal->fresh()->total_calories);
        $this->assertSame(4.0, $meal->fresh()->total_protein);
        $this->assertSame(44.0, $meal->fresh()->total_carbs);
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
