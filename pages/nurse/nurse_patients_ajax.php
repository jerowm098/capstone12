<?php
// capstonemain/pages/nurse/nurse_patients_ajax.php
session_start();
if (!isset($_SESSION['nurse_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
require_once '../../config/db_connect.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course_filter = isset($_GET['course']) ? $_GET['course'] : '';
$year_filter = isset($_GET['year']) ? $_GET['year'] : '';

$query = "SELECT s.id, s.student_number, u.first_name, u.last_name, s.course, s.year_level, s.blood_type, s.allergies,
          (SELECT COUNT(*) FROM visits WHERE student_id = s.id) as visit_count,
          (SELECT MAX(visit_date) FROM visits WHERE student_id = s.id) as last_visit
          FROM students s 
          JOIN users u ON s.user_id = u.id 
          WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR s.student_number LIKE ?)";
    $sp = "%$search%";
    $params[] = $sp; $params[] = $sp; $params[] = $sp;
    $types .= "sss";
}

if (!empty($course_filter)) {
    $query .= " AND s.course = ?";
    $params[] = $course_filter;
    $types .= "s";
}

if (!empty($year_filter)) {
    $query .= " AND s.year_level = ?";
    $params[] = $year_filter;
    $types .= "s";
}

$query .= " ORDER BY u.last_name ASC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$patients = [];
while ($row = mysqli_fetch_assoc($result)) {
    $patients[] = $row;
}

echo json_encode(['success' => true, 'patients' => $patients]);
?>