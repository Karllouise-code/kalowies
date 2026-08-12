<?php

namespace Tests\Feature;

use App\Jobs\ProcessFoodScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MealScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_creates_draft_meal_and_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/meals/scan', [
            'image' => UploadedFile::fake()->image('meal.jpg'),
            'date' => '2026-08-11',
            'type' => 'dinner',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('meal.status', 'draft')
            ->assertJsonPath('meal.source', 'scan');

        $mealId = $response->json('meal.id');
        $this->assertDatabaseHas('meals', ['id' => $mealId, 'status' => 'draft']);

        Queue::assertPushed(ProcessFoodScan::class, fn ($job) => $job->mealId === $mealId);
    }

    public function test_scan_requires_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/meals/scan', ['date' => '2026-08-11', 'type' => 'dinner'])
            ->assertStatus(422);
    }

    public function test_scan_rejects_invalid_image_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/meals/scan', [
            'image' => UploadedFile::fake()->create('meal.txt', 100),
            'date' => '2026-08-11',
            'type' => 'dinner',
        ])->assertStatus(422);
    }
}
