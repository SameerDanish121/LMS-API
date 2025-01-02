<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class question extends Model
{
    protected $table = 'question';

    // Disable timestamps if not present in the table (no created_at/updated_at columns)
    public $timestamps = false;

    // Specify the primary key if it's different from 'id' (but here it's 'id' by default)
    protected $primaryKey = 'id';

    // Define the fillable properties that can be mass-assigned
    protected $fillable = ['marks', 'q_no', 'exam_id'];

    /**
     * Define the relationship with the Exam model
     */
    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

}
