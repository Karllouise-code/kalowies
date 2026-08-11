<?php

namespace Tests\Unit;

use App\Rules\MealTypeRule;
use PHPUnit\Framework\TestCase;

class MealTypeRuleTest extends TestCase
{
    public function test_passes_for_valid_types(): void
    {
        $rule = new MealTypeRule;
        $fail = fn ($message) => $this->fail("Should not fail: {$message}");

        foreach (['breakfast', 'snack', 'lunch', 'dinner'] as $type) {
            $rule->validate('type', $type, $fail);
        }
        $this->assertTrue(true);
    }

    public function test_fails_for_invalid_type(): void
    {
        $rule = new MealTypeRule;
        $failed = null;
        $fail = function ($message) use (&$failed) {
            $failed = $message;
        };

        $rule->validate('type', 'brunch', $fail);

        $this->assertNotNull($failed);
    }
}
