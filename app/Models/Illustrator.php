<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Illustrator extends Model
{
    protected $fillable = [
        'experience_years',
        'portofolio_link',
        'is_open_commision',
        'user_id',
    ];

    public function user():BelongsTo{
        return $this->belongsTo(User::class);
    }
}
