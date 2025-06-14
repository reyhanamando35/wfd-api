<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use function PHPSTORM_META\map;

class Purchase extends Model
{
    protected $fillable = [
        'payment_method',
        'file_path',
        'is_verified',
        'customer_id',
        'illustration_id',
    ];

    public function customer():BelongsTo{
        return $this->belongsTo(Customer::class);
    }
    public function illustration():BelongsTo{
        return $this->belongsTo(Illustration::class);
    }
}
