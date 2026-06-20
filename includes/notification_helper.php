<?php
// capstonemain/includes/notification_helper.php

require_once __DIR__ . '/../config/db_connect.php';

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff/60) . 'm ago';
        if ($diff < 86400) return floor($diff/3600) . 'h ago';
        if ($diff < 604800) return floor($diff/86400) . 'd ago';
        return date('M d, Y', $time);
    }
}

function createNotification($student_id, $type, $title, $message, $link = '') {
    global $conn;
    // Map type to your enum values
    $notif_type = 'push';
    if ($type == 'announcement') $notif_type = 'push';
    elseif ($type == 'appointment') $notif_type = 'push';
    elseif ($type == 'system') $notif_type = 'push';
    
    // Use 'subject' for title and 'message' as is
    $stmt = mysqli_prepare($conn, "INSERT INTO notifications (student_id, type, subject, message, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
    mysqli_stmt_bind_param($stmt, "isss", $student_id, $notif_type, $title, $message);
    return mysqli_stmt_execute($stmt);
}

function createNotificationForAllStudents($type, $title, $message, $link = '') {
    global $conn;
    $notif_type = 'push';
    $status = 'pending';
    $stmt = mysqli_prepare($conn, "INSERT INTO notifications (student_id, type, subject, message, status, created_at) 
                                   SELECT s.id, ?, ?, ?, ?, NOW() 
                                   FROM students s");
    mysqli_stmt_bind_param($stmt, "ssss", $notif_type, $title, $message, $status);
    return mysqli_stmt_execute($stmt);
}

function getUnreadNotifications($student_id, $limit = 10) {
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT id, student_id, type, subject as title, message, created_at, is_read 
                                   FROM notifications 
                                   WHERE student_id = ? AND is_read = 0 
                                   ORDER BY created_at DESC LIMIT ?");
    mysqli_stmt_bind_param($stmt, "ii", $student_id, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['title'] = $row['title'];
        $row['link'] = '';
        $notifications[] = $row;
    }
    return $notifications;
}

function getAllNotifications($student_id, $limit = 50) {
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT id, student_id, type, subject as title, message, created_at, is_read 
                                   FROM notifications 
                                   WHERE student_id = ? 
                                   ORDER BY created_at DESC LIMIT ?");
    mysqli_stmt_bind_param($stmt, "ii", $student_id, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['title'] = $row['title'];
        $row['link'] = '';
        $notifications[] = $row;
    }
    return $notifications;
}

function markNotificationAsRead($notification_id, $student_id) {
    global $conn;
    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE id = ? AND student_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $notification_id, $student_id);
    return mysqli_stmt_execute($stmt);
}

function markAllNotificationsAsRead($student_id) {
    global $conn;
    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    return mysqli_stmt_execute($stmt);
}

function getUnreadCount($student_id) {
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM notifications WHERE student_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['c'] ?? 0;
}

function deleteOldNotifications($days = 30) {
    global $conn;
    $stmt = mysqli_prepare($conn, "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND is_read = 1");
    mysqli_stmt_bind_param($stmt, "i", $days);
    return mysqli_stmt_execute($stmt);
}
?>