<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'address', 'city', 'zipcode', 'total', 'status', 'is_paid', 'is_customized', 'customized_file'
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'is_customized' => 'boolean',
    ];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // Optional: relation with user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderHasPaids()
    {
        return $this->hasMany(OrderHasPaid::class);
    }
}
