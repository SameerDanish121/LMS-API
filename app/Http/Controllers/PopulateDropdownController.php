<?php

namespace App\Http\Controllers;

use App\Models\student;
use Illuminate\Http\Request;

class PopulateDropdownController extends Controller
{
    public function AllStudent()
    {
        return student::all('name');
    }
}
