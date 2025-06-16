<?php

// app/Models/OAuthProvider.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OAuthProvider extends Model
{

    protected $table = 'oauth_providers';
    
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'is_active',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    public function userOAuthProviders(): HasMany
    {
        return $this->hasMany(UserOAuthProvider::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}