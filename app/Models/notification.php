<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class notification extends Model
{
     // The table name is explicitly set to 'notification'
     protected $table = 'notification';

     // Disable timestamps as the table doesn't seem to have created_at/updated_at columns
     public $timestamps = false;
 
     // Specify the primary key if it's different from 'id' (but here it's 'id' by default)
     protected $primaryKey = 'id';
 
     // Define which fields are mass assignable
     protected $fillable = [
         'title',
         'description',
         'url',
         'notification_date',
         'sender',
         'reciever',
         'Brodcast',
         'TL_sender_id',
         'Student_Section',
         'TL_receiver_id',
     ];
 
     // Define the relationship to the Section model
     public function section()
     {
         return $this->belongsTo(Section::class, 'Student_Section', 'id');
     }
 
     // Define the relationship to the User model for TL sender
     public function senderUser()
     {
         return $this->belongsTo(User::class, 'TL_sender_id', 'id');
     }
 
     // Define the relationship to the User model for TL receiver
     public function receiverUser()
     {
         return $this->belongsTo(User::class, 'TL_receiver_id', 'id');
     }
}
