<?php

namespace App\Http\Controllers;

use App\Models\notification;
use App\Models\program;
use App\Models\role;
use App\Models\section;
use App\Models\session;
use App\Models\student;
use DateTime;
use Exception;
use App\Models\user;
use App\Models\admin;
use App\Models\Action;
use App\Models\teacher;
use App\Models\datacell;
use App\Models\FileHandler;
use Illuminate\Http\Request;
use App\Models\juniorlecturer;
use App\Models\StudentManagement;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SingleInsertionController extends Controller
{
    public function AddSingleTeacher(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'date_of_birth' => 'required|date',
                'gender' => 'required|string',
                'cnic' => 'required',
                'email' => 'nullable',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            $name = trim($request->input('name'));
            $dateOfBirth = $request->input('date_of_birth');
            $gender = $request->input('gender');
            $cnic = $request->input('cnic');
            $email = $request->input('email') ?? null;
            $username = strtolower(str_replace(' ', '', $name)) . '@biit.edu';
            if (teacher::where('cnic', $cnic)->exists()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "The teacher with cnic: {$cnic} already exists."
                ], 409);
            }

            $existingUser = User::where('username', $username)->first();
            if ($existingUser) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "The teacher with username: {$username} already exists."
                ], 409);
            }

            $formattedDOB = (new DateTime($dateOfBirth))->format('Y-m-d');
            $password = Action::generateUniquePassword($name);
            $userId = Action::addOrUpdateUser($username, $password, $email, 'Teacher');

            if (!$userId) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "Failed to create or update user for {$name}."
                ], 500);
            }

            $teacher = Teacher::create([
                'user_id' => $userId,
                'name' => $name,
                'date_of_birth' => $formattedDOB,
                'gender' => $gender,
                'email' => $email,
                'cnic' => $cnic
            ]);
            // Handle image upload only if it's provided
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $directory = 'Images/Teacher';
                $storedFilePath = FileHandler::storeFile($teacher->user_id, $directory, $image);
                $teacher->update(['image' => $storedFilePath]);
            }

            return response()->json([
                'status' => 'success',
                'message' => "The teacher with Name: {$name} was added.",
                'username' => $username,
                'password' => $password
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data not found'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function AddSingleJuniorLecturer(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'date_of_birth' => 'required|date',
                'gender' => 'required|string',
                'cnic' => 'required',
                'email' => 'nullable', // Email is optional
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048' // Image is optional
            ]);

            $name = trim($request->input('name'));
            $dateOfBirth = $request->input('date_of_birth');
            $gender = $request->input('gender');
            $cnic = $request->input('cnic');
            $email = $request->input('email') ?? null; // Default to null if not provided
            $username = strtolower(str_replace(' ', '', $name)) . '@biit.edu';
            if (juniorlecturer::where('cnic', $cnic)->exists()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "The JuniorLecturer with cnic: {$cnic} already exists."
                ], 409);
            }
            // Check if the user already exists
            $existingUser = User::where('username', $username)->first();
            if ($existingUser) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "The Junior Lecturer with username: {$username} already exists."
                ], 409);
            }

            $formattedDOB = (new DateTime($dateOfBirth))->format('Y-m-d');
            $password = Action::generateUniquePassword($name);
            $userId = Action::addOrUpdateUser($username, $password, $email, 'JuniorLecturer');

            if (!$userId) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "Failed to create or update user for {$name}."
                ], 500);
            }
            $juniorLecturer = juniorlecturer::create([
                'user_id' => $userId,
                'name' => $name,
                'date_of_birth' => $formattedDOB,
                'gender' => $gender,
                'email' => $email,
                'cnic' => $cnic
            ]);
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $directory = 'Images/JuniorLecturer';
                $storedFilePath = FileHandler::storeFile($juniorLecturer->user_id, $directory, $image);
                $juniorLecturer->update(['image' => $storedFilePath]);
            }
            return response()->json([
                'status' => 'success',
                'message' => "The Junior Lecturer with Name: {$name} was added.",
                'username' => $username,
                'password' => $password
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function AddSingleUser(Request $request, $role)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'phone_number' => 'required|string',
                'Designation' => 'required|string',
                'email' => 'nullable|email',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            $name = trim($request->input('name'));
            $phone = $request->input('phone_number');
            $designation = $request->input('Designation');
            $email = $request->input('email') ?? null;
            if ($role == 'Admin') {
                $postfix = '.admin@biit.edu';
            } else {
                $postfix = '.datacell@biit.edu';
            }
            $username = strtolower(str_replace(' ', '', $name)) . $postfix;

            // Determine model and image path based on role
            $model = $role === 'Admin' ? admin::class : datacell::class;
            $directory = $role === 'Admin' ? 'Images/Admin' : 'Images/DataCell';

            // Check if user already exists
            $existingUser = User::where('username', $username)->first();
            if ($existingUser) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "The user with username: {$username} already exists."
                ], 409);
            }

            $password = Action::generateUniquePassword($name);
            $userId = Action::addOrUpdateUser($username, $password, $email, $role);

            if (!$userId) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "Failed to create or update user for {$name}."
                ], 500);
            }

            $user = $model::create([
                'user_id' => $userId,
                'name' => $name,
                'phone_number' => $phone,
                'Designation' => $designation,
                'email' => $email
            ]);

            // Handle image upload if provided
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $storedFilePath = FileHandler::storeFile($user->user_id, $directory, $image);
                $user->update(['image' => $storedFilePath]);
            }

            return response()->json([
                'status' => 'success',
                'message' => "The {$role} with Name: {$name} was added.",
                'username' => $username,
                'password' => $password
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data not found'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function AddSingleAdmin(Request $request)
    {
        return $this->AddSingleUser($request, 'Admin');
    }
    public function AddSingleDatacell(Request $request)
    {
        return $this->AddSingleUser($request, 'Datacell');
    }
    public function InsertStudent(Request $request)
    {
        try {
            $request->validate([
                'RegNo' => 'required|string|unique:students,RegNo',
                'Name' => 'required|string',
                'gender' => 'required|in:Male,Female',
                'dob' => 'required|date',
                'guardian' => 'required|string',
                'cgpa' => 'nullable|numeric|min:0|max:4.00',
                'email' => 'nullable|email',
                'currentSection' => 'required|string',
                'status' => 'required|string',
                'InTake' => 'required|string',
                'Discipline' => 'required|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            $regNo = $request->RegNo;

            // Check if student already exists
            if (student::where('RegNo', $regNo)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Student with RegNo {$regNo} already exists!"
                ], 409);
            }

            $name = $request->Name;
            $gender = $request->gender;
            $dob = (new DateTime($request->dob))->format('Y-m-d');
            $guardian = $request->guardian;
            $cgpa = $request->cgpa;
            $email = $request->email;
            $currentSection = $request->currentSection;
            $status = $request->status;
            $inTake = $request->InTake;
            $discipline = $request->Discipline;

            // Generate a password for the student
            $password = Action::generateUniquePassword($name);

            // Create a user account for the student
            $user_id = Action::addOrUpdateUser($regNo, $password, $email, 'Student');

            // Get section ID
            $section_id = section::addNewSection($currentSection);
            if (!$section_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Invalid format for Current Section: {$currentSection}"
                ], 400);
            }

            // Get session ID
            $session_id = (new session())->getSessionIdByName($inTake);
            if (!$session_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Invalid format for Session: {$inTake}"
                ], 400);
            }

            // Get program ID
            $program_id = program::where('name', $discipline)->value('id');
            if (!$program_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Invalid format for Discipline: {$discipline}"
                ], 400);
            }

            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $imagePath = $image->storeAs('student_images', $imageName, 'public');
            }

            // Insert the student record
            $student = StudentManagement::addOrUpdateStudent($regNo, $name, $cgpa, $gender, $dob, $guardian, $imagePath, $user_id, $section_id, $program_id, $session_id, $status);

            if ($student) {
                return response()->json([
                    'status' => 'success',
                    'message' => "Student with RegNo: {$regNo} added successfully!",
                    'Username' => $regNo,
                    'Password' => $password
                ], 201);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Unknown error occurred while inserting student'
            ], 500);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function pushNotification(Request $request)
    {
        try {
            // Validate request fields
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'sender' => 'required|string|max:50',
                'sender_id' => 'required|integer|exists:user,id', // Sender ID is required
                'reciever' => 'nullable|string|max:50',
                'Brodcast' => 'nullable|boolean',
                'url' => 'nullable|string|max:255',
                'image'=>'nullable|file',
                'Student_Section_Name' => 'nullable|string', // Section name to find Section ID
                'TL_receiver_name' => 'nullable|string', // Receiver name for ID lookup
            ]);
            $imageUrl = null;
            if ($request->hasFile('image')) {
                // If a file was uploaded, store it and use its path
                $imagePath = FileHandler::storeFile(now()->timestamp, 'Notification', $request->file('image'));
                $imageUrl = $imagePath;
            } elseif (!is_null($request->url)) {
                // If no image file but URL is provided, use the URL
                $imageUrl = $request->url;
            } elseif ($request->has('image') && is_string($request->image)) {
                // If image is passed as a string path instead of uploaded file (fallback)
                $imageUrl = $request->image;
            }

            $TL_receiver_id = null;
            $Student_Section = null;
            if ($request->has('Brodcast') && $request->Brodcast == true) {
                if (!$request->has('reciever')) {
                    // Insert only broadcast data without receiver
                    $notification = notification::create([
                        'title' => $request->title,
                        'description' => $request->description,
                        'url' => $imageUrl ?? null,
                        'notification_date' => now(),
                        'sender' => $request->sender,
                        'reciever' => null,
                        'Brodcast' => true,
                        'TL_sender_id' => $request->sender_id,
                        'Student_Section' => null,
                        'TL_receiver_id' => null,
                    ]);
                } else {
                    // Insert broadcast with receiver
                    if ($request->reciever == 'student') {
                        $reciver = 'Student';
                    } else if ($request->reciever == 'teacher') {
                        $reciver = 'Teacher';

                    } else if ($request->reciever == 'lecturer') {
                        $reciver = 'JuniorLecturer';

                    } else {
                        $reciver = $request->reciever;
                    }
                    $notification = Notification::create([
                        'title' => $request->title,
                        'description' => $request->description,
                        'url' => $imageUrl ?? null,
                        'notification_date' => now(),
                        'sender' => $request->sender,
                        'reciever' => $reciver,
                        'Brodcast' => true,
                        'TL_sender_id' => $request->sender_id,
                        'Student_Section' => null,
                        'TL_receiver_id' => null,
                    ]);
                }

                return response()->json([
                    'message' => 'Notification pushed successfully!',
                    'notification' => $notification
                ], 201);
            }

            // If sender is Student and Section Name is provided, find section ID
            if ($request->reciever == 'student') {
                $reciver = 'Student';
            } else if ($request->reciever == 'teacher') {
                $reciver = 'Teacher';

            } else if ($request->reciever == 'lecturer') {
                $reciver = 'JuniorLecturer';

            } else {
                $reciver = $request->reciever;
            }
            if (
                $request->reciever === 'student' && 
                $request->has('Student_Section_Name') && 
                !is_null($request->Student_Section_Name)
            ){
                $section_id = (new section())->getIDByName($request->Student_Section_Name);
                if (!$section_id) {
                    return response()->json(['message' => 'Invalid Section Name'], 400);
                }
                $Student_Section = $section_id;
            } else if ($request->reciever === 'student' && !$Student_Section && $request->has('TL_receiver_name')) {
                $student = Student::where('name', $request->TL_receiver_name)->first();
                if (!$student) {
                    return response()->json(['message' => 'Student not found ' . $request->TL_receiver_name], 400);
                }
                $TL_receiver_id = $student->user_id; // Use student's linked user_id
            } else
                // If receiver is Teacher, find user_id from Teacher model
                if ($request->reciever === 'teacher' && $request->has('TL_receiver_name')) {
                    $teacher = Teacher::where('name', $request->TL_receiver_name)->first();
                    if (!$teacher) {
                        return response()->json(['message' => 'Teacher not found'], 400);
                    }
                    $TL_receiver_id = $teacher->user_id;
                } else

                    // If receiver is JuniorLecturer, find user_id from JuniorLecturer model
                    if ($request->reciever === 'lecturer' && $request->has('TL_receiver_name')) {
                        $junior = JuniorLecturer::where('name', $request->TL_receiver_name)->first();
                        if (!$junior) {
                            return response()->json(['message' => 'Junior Lecturer not found'], 400);
                        }
                        $TL_receiver_id = $junior->user_id;
                    }

            // Create and save the notification
            $notification = Notification::create([
                'title' => $request->title,
                'description' => $request->description,
                'url' => $imageUrl  ?? null,
                'notification_date' => now(),
                'sender' => $request->sender,
                'reciever' => $reciver,
                'Brodcast' => false,
                'TL_sender_id' => $request->sender_id,
                'Student_Section' => $Student_Section,
                'TL_receiver_id' => $TL_receiver_id,
            ]);

            return response()->json([
                'message' => 'Notification pushed successfully!',
                'notification' => $notification
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'messages' => 'Failed to push notification',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function UpdateSingleUser(Request $request)
    {
        try {
            $request->validate([
                'role' => 'required|string|in:Admin,Datacell',
                'name' => 'required|string',
                'phone_number' => 'required|string',
                'Designation' => 'required|string',
                'email' => 'nullable|email',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'password' => 'nullable|string|min:6',
                'id' => 'required|integer'
            ]);
            $id = $request->input('id');
            $role = ucfirst(strtolower($request->input('role')));
            $name = trim($request->input('name'));
            $phone = $request->input('phone_number');
            $designation = $request->input('Designation');
            $email = $request->input('email') ?? null;
            $password = $request->input('password');
            $model = $role === 'Admin' ? Admin::class : Datacell::class;
            $directory = $role === 'Admin' ? 'Images/Admin' : 'Images/DataCell';
            $user = $model::find($id);
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => "{$role} user with ID: {$id} was not found."
                ], 404);
            }
            $user->update([
                'name' => $name,
                'phone_number' => $phone,
                'Designation' => $designation
            ]);
            if ($role == 'Admin') {
                $postfix = '.admin@biit.edu';
            } else {
                $postfix = '.datacell@biit.edu';
            }
            $username = strtolower(str_replace(' ', '', $name)) . $postfix;
            $userAccount = User::find($user->user_id);
            if ($userAccount) {
                $userAccount->update([
                    'username' => $username // Hash password before saving
                ]);
            }
            if (!empty($password)) {
                $userAccount = User::find($user->user_id);
                if ($userAccount) {
                    $userAccount->update([
                        'password' => $password // Hash password before saving
                    ]);
                }
            }
            if (!empty($email)) {
                $userAccount = User::find($user->user_id);
                if ($userAccount) {
                    $userAccount->update([
                        'email' => $email // Hash password before saving
                    ]);
                }
            }
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $storedFilePath = FileHandler::storeFile($user->user_id, $directory, $image);
                $user->update(['image' => $storedFilePath]);
            }
            return response()->json([
                'status' => 'success',
                'message' => "{$role} user with ID: {$id} was updated successfully.",
                'name' => $name,
                'designation' => $designation,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'image' => $user->image ? asset($user->image) : null,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data not found'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addStudent(Request $request)
    {
        try {
            $request->validate([
                'RegNo' => 'required|unique:student,RegNo',
                'name' => 'required',
                'email' => 'required|email|unique:user,email',
                'program_id' => 'required|exists:program,id',
                'section_id' => 'nullable|exists:section,id',
                'session_id' => 'nullable|exists:session,id',
                'cgpa' => 'nullable|numeric|min:0|max:4',
                'gender' => 'nullable|in:Male,Female',
                'date_of_birth' => 'nullable|date',
                'guardian' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'status' => 'nullable|in:Graduate,UnderGraduate,Freeze'
            ]);

            $regNo = $request->RegNo;
            $name = $request->name;
            $email = $request->email;
            $program_id = $request->program_id;
            $section_id = $request->section_id;
            $session_id = $request->session_id;
            $cgpa = $request->cgpa;
            $gender = $request->gender;
            $date_of_birth = $request->date_of_birth;
            $guardian = $request->guardian;
            $status = $request->status;
            $password = Action::generateUniquePassword($name);
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = Action::storeFile($request->file('image'), 'Images/Student', $regNo);
            }
            $user = User::create([
                'username' => $regNo,
                'password' => $password,
                'email' => $email,
                'role_id' => role::where('type', 'Student')->value('id')
            ]);

            $student = Student::create([
                'RegNo' => $regNo,
                'name' => $name,
                'cgpa' => $cgpa,
                'gender' => $gender,
                'date_of_birth' => $date_of_birth,
                'guardian' => $guardian,
                'image' => $imagePath,
                'user_id' => $user->id,
                'section_id' => $section_id,
                'program_id' => $program_id,
                'session_id' => $session_id,
                'status' => $status
            ]);

            return response()->json([
                'message' => 'Student added successfully!',
                'username' => $regNo,
                'password' => $password
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
    public function addSession(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'year' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // // Split "name-year" (e.g., "Spring-2025" → name="Spring", year="2025")
        // $nameParts = explode('-', $request->name);
        // if (count($nameParts) != 2 || !is_numeric($nameParts[1])) {
        //     return response()->json(['message' => 'Invalid session name format. Use "name-year" (e.g., Spring-2025).'], 400);
        // }
        $name = $request->name;
        $year = $request->year;
        $existingSession = Session::where('name', $name)
            ->where('year', $year)
            ->exists();

        if ($existingSession) {
            return response()->json(['message' => 'A session with the same name and year already exists.'], 400);
        }
        // Check for date range overlap
        $overlap = Session::where(function ($query) use ($request) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                ->orWhere(function ($query) use ($request) {
                    $query->where('start_date', '<=', $request->start_date)
                        ->where('end_date', '>=', $request->end_date);
                });
        })->exists();

        if ($overlap) {
            return response()->json(['message' => 'Session date range overlaps with an existing session.'], 400);
        }

        // Create session
        $session = Session::create([
            'name' => $name,
            'year' => $year,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        return response()->json(['message' => 'Session added successfully.', 'session' => $session], 201);
    }

    // Update an existing session
    public function updateSession(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $session = Session::find($id);
        if (!$session) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        // Split "name-year" format
        $nameParts = explode('-', $request->name);
        if (count($nameParts) != 2 || !is_numeric($nameParts[1])) {
            return response()->json(['message' => 'Invalid session name format. Use "name-year" (e.g., Spring-2025).'], 400);
        }
        [$name, $year] = $nameParts;

        // Check for date range overlap (excluding current session)
        $overlap = Session::where('id', '!=', $id)
            ->where(function ($query) use ($request) {
                $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                    ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                    ->orWhere(function ($query) use ($request) {
                        $query->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                    });
            })->exists();

        if ($overlap) {
            return response()->json(['message' => 'Session date range overlaps with an existing session.'], 400);
        }

        // Update session
        $session->update([
            'name' => $name,
            'year' => $year,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        return response()->json(['message' => 'Session updated successfully.', 'session' => $session], 200);
    }
}
