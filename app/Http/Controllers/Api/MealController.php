<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meal;
use App\Rules\MealTypeRule;
use App\Services\NutritionCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $meals = Meal::with('items')
            ->where('user_id', $request->user()->id)
            ->forDate($data['date'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['meals' => $meals]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', new MealTypeRule],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:120'],
            'items.*.grams' => ['required', 'numeric', 'min:1', 'max:3000'],
            'items.*.calories' => ['required', 'numeric', 'min:1', 'max:2000'],
            'items.*.protein' => ['required', 'numeric', 'min:0', 'max:500'],
            'items.*.carbs' => ['required', 'numeric', 'min:0', 'max:500'],
            'items.*.fat' => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        $meal = Meal::create([
            'user_id' => $request->user()->id,
            'date' => $data['date'],
            'type' => $data['type'],
            'status' => Meal::STATUS_CONFIRMED,
            'source' => Meal::SOURCE_MANUAL,
            'note' => $data['note'] ?? null,
        ]);

        $items = collect($data['items'])->map(fn (array $item, int $index) => $item + ['sort_order' => $index])->all();
        $meal->items()->createMany($items);

        NutritionCalculator::recalculate($meal);

        return response()->json(['meal' => $meal->load('items')], 201);
    }

    public function show(Request $request, Meal $meal): JsonResponse
    {
        return response()->json(['meal' => $this->owned($request, $meal)->load('items')]);
    }

    public function update(Request $request, Meal $meal): JsonResponse
    {
        $meal = $this->owned($request, $meal);

        $data = $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'type' => ['sometimes', new MealTypeRule],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $meal->update($data);

        return response()->json(['meal' => $meal->load('items')]);
    }

    public function destroy(Request $request, Meal $meal): JsonResponse
    {
        $this->owned($request, $meal)->delete();

        return response()->json(null, 204);
    }

    public function confirm(Request $request, Meal $meal): JsonResponse
    {
        $meal = $this->owned($request, $meal);

        if ($meal->status !== Meal::STATUS_READY) {
            return response()->json(['message' => 'Meal is not ready to confirm.'], 422);
        }

        if ($meal->items()->count() === 0) {
            return response()->json(['message' => 'Add at least one item before confirming.'], 422);
        }

        NutritionCalculator::recalculate($meal);
        $meal->forceFill(['status' => Meal::STATUS_CONFIRMED, 'note' => null])->save();

        return response()->json(['meal' => $meal->load('items')]);
    }

    private function owned(Request $request, Meal $meal): Meal
    {
        return Meal::where('id', $meal->id)->where('user_id', $request->user()->id)->firstOrFail();
    }
}
