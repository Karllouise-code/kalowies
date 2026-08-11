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
