<?php

// app/Models/UserOAuthProvider.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOAuthProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'oauth_provider_id',
        'provider_user_id',
        'provider_username',
        'provider_email',
        'provider_avatar',
        'provider_data',
        'last_used_at',
    ];

    protected $casts = [
        'provider_data' => 'array',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function oauthProvider(): BelongsTo
    {
        return $this->belongsTo(OAuthProvider::class);
    }
}
