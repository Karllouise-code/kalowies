<?php

namespace App\Rules;

use App\Models\Meal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MealTypeRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array($value, Meal::TYPES, true)) {
            $fail('The selected meal type is invalid.');
        }
    }
}
