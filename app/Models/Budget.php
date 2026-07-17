<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'account_id',
        'year',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'year'   => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}