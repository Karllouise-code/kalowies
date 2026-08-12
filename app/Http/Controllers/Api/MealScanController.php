<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFoodScan;
use App\Models\Meal;
use App\Rules\MealTypeRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealScanController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', new MealTypeRule],
        ]);

        $path = $request->file('image')->store('meals', 'public');

        $meal = Meal::create([
            'user_id' => $request->user()->id,
            'date' => $data['date'],
            'type' => $data['type'],
            'status' => Meal::STATUS_DRAFT,
            'source' => Meal::SOURCE_SCAN,
            'image_path' => $path,
        ]);

        ProcessFoodScan::dispatch($meal->id);

        return response()->json(['meal' => $meal], 201);
    }
}
