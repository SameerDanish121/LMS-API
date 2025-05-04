<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class topic extends Model
{
  // The table name is explicitly set to 'topic'
  protected $table = 'topic';

  // Disable timestamps if not present in the table (no created_at/updated_at columns)
  public $timestamps = false;

  // Define the fillable properties for mass assignment
  protected $fillable = ['title'];
}
