<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class excluded_days extends Model
{
    protected $table = 'excluded_days';

    // Set the primary key for the table
    protected $primaryKey = 'id';

    // Specify that the primary key is auto-incrementing
    public $incrementing = true;

    // Set the data type of the primary key
    protected $keyType = 'integer';

    // Disable timestamps (if the table does not have `created_at` and `updated_at` columns)
    public $timestamps = false;

    // Mass assignable attributes
    protected $fillable = [
        'date',
        'type',
        'reason',
    ];
}
