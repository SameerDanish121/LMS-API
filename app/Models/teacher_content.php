<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class teacher_content extends Model
{
    protected $table = 'teacher_content';

    // Disable timestamps if not using `created_at` and `updated_at`
    public $timestamps = false;

    // Specify the fillable fields
    protected $fillable = [
        'teacher_id',
        'type',
        'title',
        'path',
        'description',
    ];

    /**
     * Define the relationship with the Teacher model.
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'id');
    }
    public function allocations()
    {
        return $this->hasMany(teacher_content_allocation::class, 'content_id', 'id');
    }
}
