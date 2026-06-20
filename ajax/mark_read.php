<?php
// capstonemain/ajax/mark_read.php
session_start();
require_once '../config/db_connect.php';
require_once '../includes/notification_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_SESSION['student_id'];
    $notif_id = isset($_POST['notif_id']) ? intval($_POST['notif_id']) : 0;
    
    if ($notif_id > 0) {
        if (markNotificationAsRead($notif_id, $student_id)) {
            $unread_count = getUnreadCount($student_id);
            echo json_encode(['success' => true, 'unread_count' => $unread_count]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark as read']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>