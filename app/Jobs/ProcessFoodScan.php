<?php

namespace App\Jobs;

use App\Models\Meal;
use App\Services\FoodVisionService;
use App\Services\NutritionCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessFoodScan implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;
    public array $backoff = [10, 30];

    public function __construct(public int $mealId) {}

    public function handle(FoodVisionService $vision): void
    {
        $meal = Meal::find($this->mealId);

        if (! $meal || ! in_array($meal->status, [Meal::STATUS_DRAFT, Meal::STATUS_PROCESSING])) {
            return;
        }

        if (! $meal->image_path) {
            $this->fail($meal, 'No image attached to this scan.');
            return;
        }

        $meal->forceFill(['status' => Meal::STATUS_PROCESSING])->save();

        $result = $vision->analyze($meal->image_path);

        if (empty($result['items'])) {
            $this->fail($meal, 'No food was detected in the image. Please try again.');
            return;
        }

        $meal->items()->createMany($result['items']);
        NutritionCalculator::recalculate($meal);
        $meal->forceFill(['status' => Meal::STATUS_READY, 'note' => null])->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->fail(Meal::find($this->mealId), 'Could not analyze this image. Please try again.');
    }

    private function fail(?Meal $meal, string $message): void
    {
        if (! $meal) {
            return;
        }

        $meal->forceFill(['status' => Meal::STATUS_FAILED, 'note' => $message])->save();
        Log::warning("Food scan failed for meal {$meal->id}: {$message}");
    }
}
