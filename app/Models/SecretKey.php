<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class SecretKey extends Model
{
    protected $fillable = [
        'name', 'value', 'environment', 'description', 'is_active'
    ];

    // Automatically encrypt/decrypt the value
    public function setValueAttribute($value)
    {
        $this->attributes['value'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getValueAttribute($value)
    {
        if (is_null($value)) return null;

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            return '[DECRYPTION FAILED]';
        }
    }

    // Helper scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeProduction($query)
    {
        return $query->where('environment', 'production');
    }
}