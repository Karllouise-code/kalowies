<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'calorie_goal',
        'protein_grams',
        'carbs_grams',
        'fat_grams',
    ];

    protected function casts(): array
    {
        return [
            'calorie_goal' => 'integer',
            'protein_grams' => 'integer',
            'carbs_grams' => 'integer',
            'fat_grams' => 'integer',
        ];
    }

    public static function defaultValues(): array
    {
        return [
            'calorie_goal' => 2000,
            'protein_grams' => null,
            'carbs_grams' => null,
            'fat_grams' => null,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
