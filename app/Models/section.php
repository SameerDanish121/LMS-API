<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Exception;
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
       if($section)
       {
        if (in_array($section->program, ['BCS', 'BAI', 'BSE'])) {
            return $section->program . '-' . $section->semester . $section->group;
        }else{
            return $section->program . $section->semester . $section->group;
        }    
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
        preg_match('/^([A-Za-z]+)ExtraSection$/', $name, $matches);
        if(!empty($matches)){
            $programType = $matches[1];
            // $semester = $matches[2]; 
            // $group = $matches[3]; 
            $section = self::where('program', $programType)
                ->where('semester', 'Extra')
                ->where('group', 'Section')
                ->first();
            if ($section) {
                return $section->id;
            }
        }
        return null;
    }
    public static function addNewSection($name)
    {

        if(!$name){
            return null;
        }
        if(preg_match('/([A-Za-z]+)-(\d+)([A-Za-z]+)/', $name, $matches))
        {
            if (!empty($matches)) {
                $programType = $matches[1];
                $semester = $matches[2];
                $group = $matches[3];
                $section = self::firstOrCreate(
                    [
                        'program' => $programType,
                        'semester' => $semester,
                        'group' => $group,
                    ]
                );
                return $section->id;
            }
        }else if(preg_match('/^([A-Za-z]+)ExtraSection$/', $name, $matches)){
            if (!empty($matches)) {
                $programType = $matches[1];
                $semester ='Extra';
                $group = 'Section';
                $section = self::firstOrCreate(
                    [
                        'program' => $programType,
                        'semester' => $semester,
                        'group' => $group,
                    ]
                );
                return $section->id;
            }
        }
        return null;
    }
}
