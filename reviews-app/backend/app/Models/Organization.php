<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'yandex_url',
        'name',
        'average_rating',
        'rating_count',
        'review_count',
        'last_parsed_at',
    ];

    protected $casts = [
        'average_rating' => 'float',
        'rating_count' => 'integer',
        'review_count' => 'integer',
        'last_parsed_at' => 'datetime',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Review::class);
    }
}
