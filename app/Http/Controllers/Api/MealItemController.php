<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MealItem;
use App\Services\NutritionCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealItemController extends Controller
{
    public function update(Request $request, MealItem $item): JsonResponse
    {
        $item = $this->owned($request, $item);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'grams' => ['sometimes', 'numeric', 'min:1', 'max:3000'],
            'calories' => ['sometimes', 'numeric', 'min:1', 'max:2000'],
            'protein' => ['sometimes', 'numeric', 'min:0', 'max:500'],
            'carbs' => ['sometimes', 'numeric', 'min:0', 'max:500'],
            'fat' => ['sometimes', 'numeric', 'min:0', 'max:500'],
        ]);

        $item->update($data);
        NutritionCalculator::recalculate($item->meal);

        return response()->json(['item' => $item->fresh()]);
    }

    public function destroy(Request $request, MealItem $item): JsonResponse
    {
        $this->owned($request, $item)->delete();
        NutritionCalculator::recalculate($item->meal);

        return response()->json(null, 204);
    }

    private function owned(Request $request, MealItem $item): MealItem
    {
        return MealItem::where('id', $item->id)
            ->whereHas('meal', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();
    }
}
