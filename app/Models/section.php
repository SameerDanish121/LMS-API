<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class section extends Model
{
    protected $table = 'section';

    // Disable timestamps if not present in the table (no created_at/updated_at columns)
    public $timestamps = false;

    // Specify the primary key if it's different from 'id' (but here it's 'id' by default)
    protected $primaryKey = 'id';

    // Define the fillable property that can be mass-assigned
    protected $fillable = ['group', 'semester', 'program'];
    public function getNameByID($id=null)
    {
        if(!$id){
            return null;
        }
        $section = self::where('id', $id)->first();
        if ($section) {
            return $section->program . '-' . $section->semester . $section->group;
        }else{
            return null;
        }
    }
    public function timetables()
    {
        return $this->hasMany(Timetable::class, 'section_id', 'id');
    }
    public function getIDByName($name)
    {
        preg_match('/([A-Za-z]+)-(\d+)([A-Za-z]+)/', $name, $matches);
        if (!empty($matches)) {
            $programType = $matches[1];
            $semester = $matches[2]; 
            $group = $matches[3]; 
            $section = self::where('program', $programType)
                ->where('semester', $semester)
                ->where('group', $group)
                ->first();
            if ($section) {
                return $section->id;
            }
        }
        return null;
    }
}
