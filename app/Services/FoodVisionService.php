<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FoodVisionService
{
    private const FALLBACK_MODEL = 'gemini-1.5-flash';

    private const PROMPT = <<<'EOT'
Identify every food item in this image. For each item, estimate the portion size in grams (or a common serving). Scale all macronutrients to the estimated portion. Return ONLY JSON matching this structure, with no commentary:
{
  "items": [
    { "name": "grilled chicken breast", "grams": 150, "calories": 247, "protein": 37.5, "carbs": 0, "fat": 5.3 }
  ]
}
If no food is recognizable, return { "items": [] }.
EOT;

    public function analyze(string $imagePath): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($imagePath)) {
            throw new RuntimeException('Stored image not found.');
        }

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => self::PROMPT],
                    ['inline_data' => [
                        'mime_type' => $disk->mimeType($imagePath) ?: 'image/jpeg',
                        'data' => base64_encode($disk->get($imagePath)),
                    ]],
                ],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'items' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'name' => ['type' => 'STRING'],
                                    'grams' => ['type' => 'NUMBER'],
                                    'calories' => ['type' => 'NUMBER'],
                                    'protein' => ['type' => 'NUMBER'],
                                    'carbs' => ['type' => 'NUMBER'],
                                    'fat' => ['type' => 'NUMBER'],
                                ],
                                'required' => ['name', 'grams', 'calories', 'protein', 'carbs', 'fat'],
                            ],
                        ],
                    ],
                    'required' => ['items'],
                ],
            ],
        ];

        $response = Http::withOptions(['timeout' => 45])->post($this->endpoint(config('services.gemini.model', 'gemini-2.0-flash')), $payload);

        if ($response->status() === 404) {
            $response = Http::withOptions(['timeout' => 45])->post($this->endpoint(self::FALLBACK_MODEL), $payload);
        }

        if ($response->failed()) {
            Log::error('Gemini vision request failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('Vision API request failed.');
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Vision API returned an empty response.');
        }

        return $this->parseItems($text);
    }

    private function endpoint(string $model): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.config('services.gemini.api_key');
    }

    private function parseItems(string $jsonText): array
    {
        $decoded = json_decode($jsonText, true);

        if (! is_array($decoded) || ! isset($decoded['items']) || ! is_array($decoded['items'])) {
            throw new RuntimeException('Vision API returned malformed JSON.');
        }

        $items = [];

        foreach ($decoded['items'] as $entry) {
            $name = is_string($entry['name'] ?? null) ? trim($entry['name']) : '';
            $grams = (float) ($entry['grams'] ?? 0);
            $calories = (float) ($entry['calories'] ?? 0);
            $protein = (float) ($entry['protein'] ?? 0);
            $carbs = (float) ($entry['carbs'] ?? 0);
            $fat = (float) ($entry['fat'] ?? 0);

            if ($name === '' || $grams < 1 || $grams > 3000 || $calories < 1 || $calories > 2000) {
                continue;
            }

            $items[] = [
                'name' => mb_substr($name, 0, 120),
                'grams' => round($grams, 2),
                'calories' => round($calories, 1),
                'protein' => round($protein, 1),
                'carbs' => round($carbs, 1),
                'fat' => round($fat, 1),
            ];
        }

        return ['items' => $items];
    }
}
