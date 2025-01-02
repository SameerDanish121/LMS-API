<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class course extends Model
{
    protected $table = 'course';
    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $fillable = [
        'code',
        'name',
        'credit_hours',
        'pre_req_main',
        'program_id',
        'type',
        'description',
        'lab',
    ];

    // Define the relationship with the Program model
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id', 'id');
    }

    // Define the self-referential relationship for the 'pre_req_main' field (prerequisite course)
    public function prerequisite()
    {
        return $this->belongsTo(Course::class, 'pre_req_main', 'id');
    }
}
