<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class user extends Model
{
    protected $table = 'user';
    public $timestamps = false;
    protected $fillable = ['username', 'password', 'email', 'role_id'];
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
    public function fcmTokens()
    {
        return $this->hasMany(user_fcm_tokens::class, 'user_id', 'id');
    }
    public function getRoleByID($id)
    {
        $user = self::where('id', $id)->with('role')->first();
        if ($user && $user->role) {
            return $user->role->type;
        }
        return null;
    }
}
