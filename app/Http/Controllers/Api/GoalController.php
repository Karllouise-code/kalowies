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
