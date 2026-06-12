<?php
// capstonemain/ajax/mark_all_read.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

require_once '../config/db_connect.php';
require_once '../includes/notification_helper.php';

$student_id = $_SESSION['student_id'];

$result = markAllNotificationsAsRead($student_id);

echo json_encode(['success' => $result]);
?>