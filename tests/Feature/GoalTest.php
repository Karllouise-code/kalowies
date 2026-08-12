<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_goals_creates_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/goals')
            ->assertOk()
            ->assertJsonPath('goal.calorie_goal', 2000);
    }

    public function test_user_can_update_goals(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/goals', [
            'calorie_goal' => 2200,
            'protein_grams' => 120,
            'carbs_grams' => 250,
            'fat_grams' => 60,
        ])->assertOk()
            ->assertJsonPath('goal.calorie_goal', 2200)
            ->assertJsonPath('goal.protein_grams', 120);

        $this->assertDatabaseHas('user_goals', ['user_id' => $user->id, 'calorie_goal' => 2200]);
    }

    public function test_goal_validation_rejects_negative_calories(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/goals', ['calorie_goal' => -10])->assertStatus(422);
    }
}
