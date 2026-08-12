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
