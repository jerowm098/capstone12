<?php
// capstonemain/ajax/get_notifications.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

require_once '../config/db_connect.php';
require_once '../includes/notification_helper.php';

$student_id = $_SESSION['student_id'];

$unread_count = getUnreadCount($student_id);
$notifications = getUnreadNotifications($student_id, 5);

$notif_list = [];
foreach ($notifications as $notif) {
    $notif_list[] = [
        'id' => $notif['id'],
        'type' => 'announcement',
        'title' => $notif['title'],
        'message' => substr($notif['message'], 0, 80),
        'link' => $notif['link'] ?? 'student_announcement.php',
        'time_ago' => timeAgo($notif['created_at'])
    ];
}

echo json_encode([
    'success' => true,
    'unread_count' => $unread_count,
    'notifications' => $notif_list
]);
?>