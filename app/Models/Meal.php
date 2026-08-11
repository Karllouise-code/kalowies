<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Meal extends Model
{
    use HasFactory;

    public const TYPE_BREAKFAST = 'breakfast';

    public const TYPE_SNACK = 'snack';

    public const TYPE_LUNCH = 'lunch';

    public const TYPE_DINNER = 'dinner';

    public const TYPES = [
        self::TYPE_BREAKFAST,
        self::TYPE_SNACK,
        self::TYPE_LUNCH,
        self::TYPE_DINNER,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const SOURCE_SCAN = 'scan';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'user_id',
        'date',
        'type',
        'status',
        'source',
        'image_path',
        'note',
        'total_calories',
        'total_protein',
        'total_carbs',
        'total_fat',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'total_calories' => 'float',
            'total_protein' => 'float',
            'total_carbs' => 'float',
            'total_fat' => 'float',
        ];
    }

    protected $appends = ['image_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
