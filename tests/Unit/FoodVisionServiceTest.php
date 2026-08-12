<?php

namespace Tests\Unit;

use App\Services\FoodVisionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FoodVisionServiceTest extends TestCase
{
    private function fakeImage(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('meals/meal.jpg', 'fake-image-bytes');
    }

    public function test_analyze_returns_normalized_items(): void
    {
        $this->fakeImage();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['items' => [
                        ['name' => 'Grilled Chicken', 'grams' => 150, 'calories' => 247.4, 'protein' => 37.5, 'carbs' => 0, 'fat' => 5.34],
                        ['name' => 'White Rice', 'grams' => 200, 'calories' => 260, 'protein' => 5.3, 'carbs' => 56, 'fat' => 0.6],
                    ]])]]]],
                ],
            ]),
        ]);

        $items = app(FoodVisionService::class)->analyze('meals/meal.jpg');

        $this->assertCount(2, $items['items']);
        $this->assertSame('Grilled Chicken', $items['items'][0]['name']);
        $this->assertSame(247.4, $items['items'][0]['calories']);
        $this->assertSame(5.3, $items['items'][0]['fat']);
    }

    public function test_analyze_drops_invalid_items(): void
    {
        $this->fakeImage();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['items' => [
                        ['name' => '', 'grams' => 150, 'calories' => 247, 'protein' => 10, 'carbs' => 0, 'fat' => 5],
                        ['name' => 'Valid', 'grams' => 9999, 'calories' => 99999, 'protein' => 10, 'carbs' => 0, 'fat' => 5],
                        ['name' => 'Banana', 'grams' => 120, 'calories' => 105, 'protein' => 1.3, 'carbs' => 27, 'fat' => 0.4],
                    ]])]]]],
                ],
            ]),
        ]);

        $items = app(FoodVisionService::class)->analyze('meals/meal.jpg');

        $this->assertCount(1, $items['items']);
        $this->assertSame('Banana', $items['items'][0]['name']);
    }

    public function test_analyze_returns_empty_for_no_food(): void
    {
        $this->fakeImage();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => '{"items":[]}']]]],
                ],
            ]),
        ]);

        $this->assertSame(['items' => []], app(FoodVisionService::class)->analyze('meals/meal.jpg'));
    }

    public function test_analyze_throws_on_http_failure(): void
    {
        $this->fakeImage();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('boom', 500)]);

        $this->expectException(\RuntimeException::class);
        app(FoodVisionService::class)->analyze('meals/meal.jpg');
    }

    public function test_analyze_throws_on_malformed_json(): void
    {
        $this->fakeImage();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'not json at all']]]],
                ],
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        app(FoodVisionService::class)->analyze('meals/meal.jpg');
    }
}
