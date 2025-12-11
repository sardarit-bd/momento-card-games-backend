<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class Order extends Model
{
    use Prunable;

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderHasPaids()
    {
        return $this->hasMany(OrderHasPaid::class);
    }

    /**
     * Define what should be pruned: pending orders older than 24 hours
     */
    public function prunable()
    {
        return static::where('status', 'pending')
            ->where('created_at', '<=', now()->subHours(24));
    }

    /**
     * Boot the model and clean up related data when deleting
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($order) {
            $order->orderItems()->delete();
            $order->orderHasPaids()->delete();
        });
    }
}