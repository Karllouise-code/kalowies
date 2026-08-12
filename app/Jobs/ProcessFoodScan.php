<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessFoodScan implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $mealId) {}

    public function handle(): void
    {
        // Implemented in Task 13.
    }
}
