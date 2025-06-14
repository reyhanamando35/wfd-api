<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Illustration extends Model
{
    protected $fillable = [
        'title',
        'description',
        'price',
        'image_path',
        'date_issued',
        'is_sold',
        'illustrator_id',
        'category_id',
    ];

    public function illustrator(): BelongsTo{
        return $this->belongsTo(Illustrator::class);
    }

    public function category(): BelongsTo{
        return $this->belongsTo(Category::class);
    }
}
