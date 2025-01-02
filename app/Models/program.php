<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class program extends Model
{
    protected $table = 'program';
    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'description', 'status'];
}
