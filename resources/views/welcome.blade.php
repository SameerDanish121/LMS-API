<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Learning Management System (LMS) Overview</title>
  @vite('resources/css/app.css')
</head>
<body class="bg-green-50">

  <!-- Header Section -->
  <header class="bg-green-800 text-white py-6 shadow-lg">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl font-bold">Learning Management System (LMS)</h1>
      <p class="text-lg text-gray-200 mt-2">A Comprehensive Digital Learning & Academic Management Platform</p>
    </div>
  </header>

  <!-- Abstract Section -->
  <section class="container mx-auto px-6 py-12">
    <div class="bg-white p-8 shadow-lg rounded-lg border-l-4 border-green-600">
      <h2 class="text-3xl font-bold text-green-800 mb-4">Abstract</h2>
      <p class="text-gray-700 leading-relaxed text-lg">
        The Learning Management System (LMS) is a powerful digital solution aimed at automating and enhancing academic processes. 
        It is designed to facilitate seamless interactions between students, teachers, and administrators, ensuring a structured learning experience. 
        The system comprises six key modules—Admin, DataCell, Student, Grader, Teacher, and Junior Lecturer—to optimize course management, student assessment, and institutional administration.
      </p>
    </div>
  </section>

  <!-- Introduction Section -->
  <section class="container mx-auto px-6 py-12">
    <div class="bg-white p-8 shadow-lg rounded-lg border-l-4 border-green-600">
      <h2 class="text-3xl font-bold text-green-800 mb-4">Introduction</h2>
      <p class="text-gray-700 leading-relaxed text-lg">
        The LMS is designed to bring innovation to modern education by offering a centralized platform for online learning, grading, and collaboration. 
        It integrates multiple user roles, ensuring efficient management of courses, assignments, exams, and student records. 
        The goal is to provide an **interactive, scalable, and user-friendly** solution for digital learning.
      </p>
    </div>
  </section>

  <!-- Modules Overview -->
  <section class="container mx-auto px-6 py-12">
    <h2 class="text-3xl font-bold text-green-800 mb-6 text-center">System Modules</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
      
      <!-- Admin Module -->
      <div class="bg-white p-6 shadow-lg rounded-lg text-center border-b-4 border-green-600">
        <h3 class="text-2xl font-semibold text-green-700">Admin</h3>
        <p class="text-gray-600 mt-2">Oversees system operations, user management, and security protocols.</p>
      </div>

      <!-- Datacell Module -->
      <div class="bg-white p-6 shadow-lg rounded-lg text-center border-b-4 border-green-600">
        <h3 class="text-2xl font-semibold text-green-700">Datacell</h3>
        <p class="text-gray-600 mt-2">Manages institutional data, reports, and academic insights.</p>
      </div>

      <!-- Student Module -->
      <div class="bg-white p-6 shadow-lg rounded-lg text-center border-b-4 border-green-600">
        <h3 class="text-2xl font-semibold text-green-700">Student</h3>
        <p class="text-gray-600 mt-2">Accesses courses, submits assignments, and tracks progress.</p>
      </div>

      <!-- Grader Module -->
      <div class="bg-white p-6 shadow-lg rounded-lg text-center border-b-4 border-green-600">
        <h3 class="text-2xl font-semibold text-green-700">Grader</h3>
        <p class="text-gray-600 mt-2">Evaluates student performance and assigns grades efficiently.</p>
      </div>

      <!-- Teacher Module -->
      <div class="bg-white p-6 shadow-lg rounded-lg text-center border-b-4 border-green-600">
        <h3 class="text-2xl font-semibold text-green-700">Teacher</h3>
        <p class="text-gray-600 mt-2">Manages courses, conducts lectures, and assesses student work.</p>
      </div>

      <!-- Junior Lecturer Module -->
      <div class="bg-white p-6 shadow-lg rounded-lg text-center border-b-4 border-green-600">
        <h3 class="text-2xl font-semibold text-green-700">Junior Lecturer</h3>
        <p class="text-gray-600 mt-2">Assists teachers, provides academic support, and engages students.</p>
      </div>
      
    </div>
  </section>

  <!-- Credits Section -->
  <section class="container mx-auto px-6 py-12">
    <div class="bg-white p-8 shadow-lg rounded-lg border-l-4 border-green-600 text-center">
      <h2 class="text-3xl font-bold text-green-800 mb-4">Project Credits</h2>
      <p class="text-gray-700 text-lg"><strong>Developed by:</strong> Sameer Danish, Sharjeel Ijaz, Muhammad Ali</p>
      <p class="text-gray-700 text-lg"><strong>Supervisor:</strong> Mr. Muhammad Ahsan</p>
      <p class="text-gray-700 text-lg"><strong>Institute:</strong> Barani Institute of Information Technology (BIIT)</p>
      <p class="text-gray-700 text-lg"><strong>Batch:</strong> Fall-2021</p>
    </div>
  </section>

  <!-- Login Button Section -->
  <section class="container mx-auto px-6 py-12 text-center">
    <a href="/login" class="bg-green-700 text-white px-6 py-3 text-lg font-semibold rounded-lg shadow-md hover:bg-green-800 transition duration-300">
      Go to Login
    </a>
  </section>

  <!-- Footer -->
  <footer class="bg-green-800 text-white py-6 mt-12 text-center">
    <p class="text-gray-300">&copy; 2025 Learning Management System | BIIT - All Rights Reserved</p>
  </footer>

</body>
</html>
