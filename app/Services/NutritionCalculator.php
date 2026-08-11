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

        $meal->forceFill([
            'total_calories' => $totals['calories'],
            'total_protein' => $totals['protein'],
            'total_carbs' => $totals['carbs'],
            'total_fat' => $totals['fat'],
        ])->save();

        return $meal->refresh();
    }
}
