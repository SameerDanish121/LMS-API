<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class user_fcm_tokens extends Model
{
  
    protected $table = 'user_fcm_tokens';

    protected $fillable = [
        'user_id',
        'fcm_token',
        'created_at',
    ];

    public $timestamps = false; // Since `created_at` is handled manually in migration

    /**
     * Get the user that owns the FCM token.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
