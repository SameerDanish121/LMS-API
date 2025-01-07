<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\course;
use App\Models\FileHandler;
use App\Models\section;
use App\Models\student_offered_courses;
use App\Models\teacher;
use Illuminate\Http\Request;
use App\Models\session;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
class TestController extends Controller
{
    public function upload(Request $request)
    {

        return FileHandler::storeFile('CC-LAB','Course Content/Fall-2024',$request->file('file'));
    }
    public function uploadFile(Request $request)
    {
       
        try {
        
            $file = $request->file('file');
            //$filedata=FileHandler::getFileData($file);
            if (!$file->isValid()) {
                throw new Exception('The uploaded file is invalid.');
            }
            $filePath = $file->store('uploads', 'public'); // This stores the file in the 'storage/app/public/uploads' directory
            $fileData = Storage::get($filePath);
            return response()->json([
                'success' => true,
                'message' => 'File uploaded and stored successfully!',
                'file_data' => $file->getPathname(),
                'data'=>$fileData??'Hello'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function Empty(Request $request)
    {
        try {
            $task_id = (new course())->getIDByName('Technical And Business Writing');
            return response()->json(
                [
                    'message' => 'Fetched Successfully',
                    'Course' => $task_id
                ],
                200
            );
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
