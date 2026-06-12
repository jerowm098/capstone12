<?php
session_start();
require_once '../config/db_connect.php';

$student_number = trim($_POST['student_number'] ?? '');
$error_type = '';

// Validation
if (empty($student_number)) {
    header('Location: kiosk_options.php?error=empty');
    exit();
}

if (!preg_match('/^\d{4}-\d{5}-BN-\d$/', $student_number)) {
    header('Location: kiosk_options.php?error=invalid');
    exit();
}

// Check if student exists in database
$stmt = mysqli_prepare($conn, "SELECT s.id, s.student_number, u.first_name, u.last_name, u.email, s.course, s.year_level, s.blood_type 
                              FROM students s 
                              JOIN users u ON s.user_id = u.id 
                              WHERE s.student_number = ?");
mysqli_stmt_bind_param($stmt, "s", $student_number);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

if (!$student) {
    header('Location: kiosk_options.php?error=notfound');
    exit();
}

// Check if already has active visit today
$check_stmt = mysqli_prepare($conn, "SELECT id FROM visits WHERE student_id = ? AND status IN ('waiting', 'in-progress') AND DATE(visit_date) = CURDATE()");
mysqli_stmt_bind_param($check_stmt, "i", $student['id']);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) > 0) {
    header('Location: kiosk_options.php?error=active');
    exit();
}

// Store student information in session
$_SESSION['patient_info'] = [
    'student_id' => $student['id'],
    'student_number' => $student['student_number'],
    'first_name' => $student['first_name'],
    'last_name' => $student['last_name'],
    'full_name' => $student['first_name'] . ' ' . $student['last_name'],
    'email' => $student['email'] ?? '',
    'course' => $student['course'],
    'year_level' => $student['year_level'],
    'blood_type' => $student['blood_type'] ?? 'N/A'
];

// Redirect to triage form
header('Location: kiosk_triage_form.php');
exit();
?>