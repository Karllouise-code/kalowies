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
