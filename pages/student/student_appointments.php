<?php
// capstonemain/pages/student/student_appointments.php
session_start();
if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit();
}
require_once '../../config/db_connect.php';
require_once '../../includes/notification_helper.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$student_id = $_SESSION['student_id'];
$message = '';
$message_type = '';

// Get unread notifications count
$unread_count = getUnreadCount($student_id);
$notifications = getUnreadNotifications($student_id, 5);

// Get student data for sidebar
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, s.student_number, s.course FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

$first_name = $student['first_name'] ?? 'Student';
$last_name = $student['last_name'] ?? '';
$full_name = trim($first_name . ' ' . $last_name);
$student_number = $student['student_number'] ?? 'N/A';
$course = $student['course'] ?? 'N/A';
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Handle Cancel Appointment
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $appointment_id = intval($_GET['cancel']);
    
    $check_stmt = mysqli_prepare($conn, "SELECT id, status, appointment_date, appointment_time, purpose FROM appointments WHERE id = ? AND student_id = ?");
    mysqli_stmt_bind_param($check_stmt, "ii", $appointment_id, $student_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $appointment = mysqli_fetch_assoc($check_result);
    
    if ($appointment && $appointment['status'] != 'cancelled') {
        $update_stmt = mysqli_prepare($conn, "UPDATE appointments SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, "i", $appointment_id);
        if (mysqli_stmt_execute($update_stmt)) {
            $message = "Appointment cancelled successfully.";
            $message_type = "success";
        } else {
            $message = "Failed to cancel appointment.";
            $message_type = "error";
        }
    } else {
        $message = "Appointment not found or already cancelled.";
        $message_type = "error";
    }
    // Redirect to avoid resubmission
    header('Location: student_appointments.php');
    exit();
}

// Handle Reschedule Request
if (isset($_GET['reschedule']) && is_numeric($_GET['reschedule'])) {
    $appointment_id = intval($_GET['reschedule']);
    $_SESSION['reschedule_id'] = $appointment_id;
    header('Location: student_appointments.php');
    exit();
}

// Handle Book Appointment (with CSRF protection)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid request. Please try again.";
        $message_type = "error";
    } else {
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $purpose = trim($_POST['purpose'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        
        $errors = [];
        
        if (empty($appointment_date)) {
            $errors[] = "Please select a date.";
        } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
            $errors[] = "Cannot book appointment for past dates.";
        }
        
        if (empty($appointment_time)) $errors[] = "Please select a time.";
        if (empty($purpose)) $errors[] = "Please select a purpose.";
        if (empty($symptoms)) $errors[] = "Please describe your symptoms.";
        if (empty($contact_number)) $errors[] = "Please provide a contact number.";
        elseif (!preg_match('/^09\d{9}$/', $contact_number)) $errors[] = "Contact number must be 09XXXXXXXXX format.";
        
        if (empty($errors)) {
            $check_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND status NOT IN ('cancelled')");
            mysqli_stmt_bind_param($check_stmt, "ss", $appointment_date, $appointment_time);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $slot_check = mysqli_fetch_assoc($check_result);
            
            if ($slot_check['count'] >= 3) $errors[] = "This time slot is fully booked. Please choose another time.";
        }
        
        $reschedule_id = $_SESSION['reschedule_id'] ?? null;
        
        if (empty($errors)) {
            if ($reschedule_id) {
                $stmt = mysqli_prepare($conn, "UPDATE appointments SET appointment_date = ?, appointment_time = ?, purpose = ?, symptoms = ?, contact_number = ?, status = 'pending', updated_at = NOW() WHERE id = ? AND student_id = ?");
                mysqli_stmt_bind_param($stmt, "sssssii", $appointment_date, $appointment_time, $purpose, $symptoms, $contact_number, $reschedule_id, $student_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Appointment rescheduled successfully!";
                    $message_type = "success";
                    unset($_SESSION['reschedule_id']);
                } else {
                    $message = "Failed to reschedule appointment.";
                    $message_type = "error";
                }
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO appointments (student_id, appointment_date, appointment_time, purpose, symptoms, contact_number, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
                mysqli_stmt_bind_param($stmt, "isssss", $student_id, $appointment_date, $appointment_time, $purpose, $symptoms, $contact_number);
                if (mysqli_stmt_execute($stmt)) {
                    $appointment_id = mysqli_insert_id($conn);
                    
                    $notif_title = "Appointment Booked";
                    $notif_message = "Your appointment on " . date('F d, Y', strtotime($appointment_date)) . " at " . date('g:i A', strtotime($appointment_time)) . " for " . $purpose . " has been booked. Please wait for confirmation.";
                    createNotification($student_id, 'appointment', $notif_title, $notif_message, 'student_appointments.php');
                    
                    $message = "Appointment booked successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to book appointment.";
                    $message_type = "error";
                }
            }
        } else {
            $message = implode("<br>", $errors);
            $message_type = "error";
        }
    }
    // Regenerate CSRF token after form submission
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    // Redirect to avoid resubmission
    header('Location: student_appointments.php');
    exit();
}

// Get appointments
$stmt = mysqli_prepare($conn, "SELECT a.*, u.first_name as nurse_first, u.last_name as nurse_last FROM appointments a LEFT JOIN nurses n ON a.nurse_id = n.id LEFT JOIN users u ON n.user_id = u.id WHERE a.student_id = ? ORDER BY a.appointment_date ASC, a.appointment_time ASC");
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$all_appointments = [];
while ($row = mysqli_fetch_assoc($result)) $all_appointments[] = $row;

$upcoming_appointments = array_filter($all_appointments, function($a) {
    $appt_datetime = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
    return $appt_datetime >= time() && $a['status'] != 'cancelled';
});

$past_appointments = array_filter($all_appointments, function($a) {
    $appt_datetime = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
    return $appt_datetime < time() || $a['status'] == 'cancelled';
});

$reschedule_data = null;
if (isset($_SESSION['reschedule_id'])) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM appointments WHERE id = ? AND student_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $_SESSION['reschedule_id'], $student_id);
    mysqli_stmt_execute($stmt);
    $reschedule_result = mysqli_stmt_get_result($stmt);
    $reschedule_data = mysqli_fetch_assoc($reschedule_result);
}

$current_page = basename($_SERVER['PHP_SELF']);

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return date('M d', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Appointments - PUPBC Carelink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: { primary: { DEFAULT: '#800020' }, accent: { DEFAULT: '#c9a84c' } },
                backgroundImage: { 'gradient-primary': 'linear-gradient(135deg, #800020, #600018)' },
            }}
        }
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f8fafc; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .notification-item:hover { background-color: #f9fafb; }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .pulse-animation { animation: pulse 0.5s ease-in-out; }
    </style>
</head>
<body>

<!-- Desktop Sidebar (hidden on mobile) -->
<aside class="hidden md:block fixed top-0 left-0 h-full w-64 bg-[#800020] shadow-xl overflow-y-auto z-30">
    <div class="flex items-center gap-2 p-4 border-b border-[#600018]">
        <div class="rounded-lg bg-white/20 p-2"><i class="fas fa-heartbeat text-white text-lg"></i></div>
        <div><span class="font-bold text-white">PUPBC Carelink</span><p class="text-[10px] text-white/60">Health Information System</p></div>
    </div>
    
    <div class="flex items-center gap-3 p-4 border-b border-[#600018] bg-[#600018]">
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#c9a84c] text-[#800020] font-bold text-sm"><?php echo $initials; ?></div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($full_name); ?></div>
            <div class="text-xs text-[#c9a84c] truncate"><?php echo htmlspecialchars($student_number); ?></div>
        </div>
    </div>
    
    <nav class="py-4">
        <div class="px-3 mb-2"><p class="text-[10px] font-semibold uppercase tracking-wider text-[#c9a84c] px-3">Main Menu</p></div>
        
        <a href="student_dashboard.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-home w-5"></i><span class="text-sm font-medium">Dashboard</span>
        </a>
        <a href="student_qr.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-qrcode w-5"></i><span class="text-sm font-medium">QR Code</span>
        </a>
        <a href="student_record.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-notes-medical w-5"></i><span class="text-sm font-medium">Health Records</span>
        </a>
        <a href="student_appointments.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'student_appointments.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
            <i class="fas fa-calendar-alt w-5"></i><span class="text-sm font-medium">Appointments</span>
        </a>
        <a href="student_announcement.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-newspaper w-5"></i><span class="text-sm font-medium">Announcements</span>
        </a>
        <a href="student_settings.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-user-circle w-5"></i><span class="text-sm font-medium">Profile</span>
        </a>
        
        <div class="border-t border-[#600018] my-4 mx-3"></div>
        <a href="student_logout.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/70 hover:text-white hover:bg-[#600018] transition-all">
            <i class="fas fa-sign-out-alt w-5"></i><span class="text-sm font-medium">Sign Out</span>
        </a>
    </nav>
    
    <div class="p-4 border-t border-[#600018] mt-auto"><p class="text-[10px] text-white/40 text-center">© <?php echo date('Y'); ?> PUPBC Carelink</p></div>
</aside>

<!-- Main Content -->
<main class="md:ml-64 min-h-screen pb-20 md:pb-6">
    
    <!-- Header with Notification Bell (no menu toggle button!) -->
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200">
        <div class="px-4 md:px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Appointments</h1>
                <p class="text-sm text-gray-500">Manage your clinic appointments</p>
            </div>
            
            <!-- Notification Bell - Top Right Corner -->
            <div class="relative">
                <button id="notificationBtn" class="relative focus:outline-none p-2 hover:bg-gray-100 rounded-full transition">
                    <i class="fas fa-bell text-gray-600 text-xl"></i>
                    <?php if ($unread_count > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        <?php echo $unread_count > 9 ? '9+' : $unread_count; ?>
                    </span>
                    <?php endif; ?>
                </button>
                
                <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border z-50">
                    <div class="p-3 border-b bg-gray-50 rounded-t-lg flex justify-between items-center">
                        <h4 class="font-semibold text-gray-900">Notifications</h4>
                        <div class="flex gap-2">
                            <?php if ($unread_count > 0): ?>
                            <button onclick="markAllAsRead()" class="text-xs text-[#800020] hover:underline">Mark all read</button>
                            <?php endif; ?>
                            <a href="student_notifications.php" class="text-xs text-[#800020] hover:underline">View all</a>
                        </div>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <?php if (empty($notifications)): ?>
                            <div class="p-4 text-center text-gray-500">
                                <i class="fas fa-bell-slash text-3xl mb-2 block"></i>
                                <p class="text-sm">No new notifications</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                            <button onclick="viewNotification(<?php echo htmlspecialchars(json_encode([
                                'id' => $notif['id'],
                                'title' => $notif['title'],
                                'message' => $notif['message'],
                                'date' => timeAgo($notif['created_at']),
                                'full_date' => date('F d, Y g:i A', strtotime($notif['created_at'])),
                                'type' => $notif['type']
                            ])); ?>)" class="notification-item w-full text-left block p-3 border-b hover:bg-gray-50 transition">
                                <div class="flex items-start gap-2">
                                    <div class="mt-0.5">
                                        <?php if ($notif['type'] == 'announcement'): ?>
                                            <i class="fas fa-bullhorn text-[#800020]"></i>
                                        <?php elseif ($notif['type'] == 'appointment'): ?>
                                            <i class="fas fa-calendar-alt text-blue-500"></i>
                                        <?php else: ?>
                                            <i class="fas fa-info-circle text-gray-500"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notif['title']); ?></p>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars(substr($notif['message'], 0, 80)) . (strlen($notif['message']) > 80 ? '...' : ''); ?></p>
                                        <p class="text-xs text-gray-400 mt-1"><?php echo timeAgo($notif['created_at']); ?></p>
                                    </div>
                                    <?php if (!$notif['is_read']): ?>
                                    <div class="w-2 h-2 bg-[#800020] rounded-full notif-dot-<?php echo $notif['id']; ?>"></div>
                                    <?php endif; ?>
                                </div>
                            </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Page Content -->
    <div class="p-4 md:p-6">
        <div class="max-w-5xl mx-auto">
            
            <?php if ($message): ?>
                <div class="mb-4 rounded-lg p-4 <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
                    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Book Appointment Button -->
            <div class="flex justify-end mb-4">
                <button onclick="openBookingModal()" class="inline-flex items-center gap-2 rounded-lg bg-[#800020] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#600018] transition">
                    <i class="fas fa-plus"></i> <?php echo $reschedule_data ? 'Reschedule Appointment' : 'Book Appointment'; ?>
                </button>
            </div>
            
            <!-- Tabs -->
            <div class="flex gap-2 border-b border-gray-200 pb-2 mb-6">
                <button onclick="showTab('upcoming')" id="tabUpcomingBtn" class="px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 border-[#800020] text-[#800020] transition"><i class="fas fa-calendar-week mr-1"></i> Upcoming</button>
                <button onclick="showTab('past')" id="tabPastBtn" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 transition"><i class="fas fa-history mr-1"></i> Past Appointments</button>
            </div>
            
            <!-- Upcoming Appointments -->
            <div id="upcomingTab">
                <?php if (empty($upcoming_appointments)): ?>
                    <div class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <i class="fas fa-calendar-plus text-5xl text-gray-300 mb-3 block"></i>
                        <p class="text-gray-500">No upcoming appointments.</p>
                        <button onclick="openBookingModal()" class="text-sm text-[#800020] mt-2 inline-block">Book an appointment →</button>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($upcoming_appointments as $a): ?>
                            <div class="bg-white rounded-xl shadow-sm border p-4 card-hover">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div class="flex items-start gap-3">
                                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-[#800020] to-[#600018] flex flex-col items-center justify-center text-white shadow-sm">
                                            <span class="text-[10px] font-bold uppercase"><?php echo date('M', strtotime($a['appointment_date'])); ?></span>
                                            <span class="text-base font-bold leading-none"><?php echo date('d', strtotime($a['appointment_date'])); ?></span>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($a['purpose'] ?? 'Consultation'); ?></p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <i class="far fa-clock mr-1"></i><?php echo date('g:i A', strtotime($a['appointment_time'])); ?>
                                                <span class="mx-1">•</span>
                                                <i class="far fa-user mr-1"></i>Nurse: <?php echo htmlspecialchars(($a['nurse_first'] ?? 'TBA') . ' ' . ($a['nurse_last'] ?? '')); ?>
                                            </p>
                                            <?php if (!empty($a['symptoms'])): ?>
                                                <p class="text-xs text-gray-400 mt-1"><i class="fas fa-notes-medical mr-1"></i><?php echo htmlspecialchars(substr($a['symptoms'], 0, 60)); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <span class="text-xs px-2 py-1 rounded-full <?php echo $a['status'] == 'confirmed' ? 'bg-green-100 text-green-700' : ($a['status'] == 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'); ?>">
                                            <?php echo ucfirst($a['status'] ?? 'Pending'); ?>
                                        </span>
                                        <div class="flex gap-2">
                                            <?php if ($a['status'] == 'pending'): ?>
                                            <button onclick="viewReceipt(<?php echo htmlspecialchars(json_encode([
                                                'id' => $a['id'],
                                                'purpose' => $a['purpose'],
                                                'date' => date('F d, Y', strtotime($a['appointment_date'])),
                                                'time' => date('g:i A', strtotime($a['appointment_time'])),
                                                'status' => $a['status'],
                                                'symptoms' => $a['symptoms'],
                                                'student_name' => $full_name,
                                                'student_number' => $student_number
                                            ])); ?>)" class="text-xs text-blue-600 hover:underline"><i class="fas fa-receipt"></i> Receipt</button>
                                            <?php endif; ?>
                                            <a href="?reschedule=<?php echo $a['id']; ?>" class="text-xs text-[#800020] hover:underline"><i class="fas fa-edit"></i> Reschedule</a>
                                            <a href="?cancel=<?php echo $a['id']; ?>" onclick="return confirm('Are you sure you want to cancel this appointment?')" class="text-xs text-red-600 hover:underline"><i class="fas fa-times"></i> Cancel</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Past Appointments -->
            <div id="pastTab" class="hidden">
                <?php if (empty($past_appointments)): ?>
                    <div class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <i class="fas fa-calendar-check text-5xl text-gray-300 mb-3 block"></i>
                        <p class="text-gray-500">No past appointments found.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($past_appointments as $a): ?>
                            <div class="bg-white rounded-xl shadow-sm border p-4 opacity-75">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div class="flex items-start gap-3">
                                        <div class="w-12 h-12 rounded-xl bg-gray-400 flex flex-col items-center justify-center text-white shadow-sm">
                                            <span class="text-[10px] font-bold uppercase"><?php echo date('M', strtotime($a['appointment_date'])); ?></span>
                                            <span class="text-base font-bold leading-none"><?php echo date('d', strtotime($a['appointment_date'])); ?></span>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($a['purpose'] ?? 'Consultation'); ?></p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <i class="far fa-calendar-alt mr-1"></i><?php echo date('F d, Y', strtotime($a['appointment_date'])); ?>
                                                <span class="mx-1">•</span>
                                                <i class="far fa-clock mr-1"></i><?php echo date('g:i A', strtotime($a['appointment_time'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full <?php echo $a['status'] == 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700'; ?>">
                                        <?php echo ucfirst($a['status'] ?? 'Completed'); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Clinic Hours Card -->
            <div class="mt-6 bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b">
                    <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-clock text-[#800020]"></i> Clinic Hours</h3>
                </div>
                <div class="p-4 space-y-2 text-sm">
                    <div class="flex justify-between py-1"><span class="text-gray-500">Monday - Friday</span><span class="font-medium">7:30 AM - 5:00 PM</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500">Saturday</span><span class="font-medium">8:00 AM - 12:00 PM</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500">Sunday</span><span class="font-medium">Closed</span></div>
                    <div class="mt-2 p-2 bg-gray-50 rounded-lg text-xs text-gray-500"><i class="fas fa-info-circle mr-1"></i> Holidays follow the official PUP academic calendar.</div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Booking Modal -->
<div id="bookingModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-calendar-plus mr-2 text-[#800020]"></i><?php echo $reschedule_data ? 'Reschedule Appointment' : 'Book an Appointment'; ?></h3>
            <button onclick="closeBookingModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Appointment Date *</label>
                <input type="date" name="appointment_date" id="appointment_date" required min="<?php echo date('Y-m-d'); ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Time *</label>
                <select name="appointment_time" id="appointment_time" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                    <option value="">Select time</option>
                    <option value="08:00:00">8:00 AM</option>
                    <option value="08:30:00">8:30 AM</option>
                    <option value="09:00:00">9:00 AM</option>
                    <option value="09:30:00">9:30 AM</option>
                    <option value="10:00:00">10:00 AM</option>
                    <option value="10:30:00">10:30 AM</option>
                    <option value="11:00:00">11:00 AM</option>
                    <option value="11:30:00">11:30 AM</option>
                    <option value="13:00:00">1:00 PM</option>
                    <option value="13:30:00">1:30 PM</option>
                    <option value="14:00:00">2:00 PM</option>
                    <option value="14:30:00">2:30 PM</option>
                    <option value="15:00:00">3:00 PM</option>
                    <option value="15:30:00">3:30 PM</option>
                    <option value="16:00:00">4:00 PM</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Purpose *</label>
                <select name="purpose" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                    <option value="">Select purpose</option>
                    <option value="Consultation">Medical Consultation</option>
                    <option value="Follow-up">Follow-up Checkup</option>
                    <option value="Vaccination">Vaccination</option>
                    <option value="Medical Certificate">Medical Certificate</option>
                    <option value="Physical Exam">Physical Exam</option>
                    <option value="Dental Check-up">Dental Check-up</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Symptoms / Reason *</label>
                <textarea name="symptoms" rows="3" required placeholder="Please describe your symptoms or reason for visit..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number *</label>
                <input type="tel" name="contact_number" required placeholder="09XXXXXXXXX" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                <p class="mt-1 text-xs text-gray-400">We'll use this to confirm your appointment</p>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeBookingModal()" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" name="book_appointment" class="flex-1 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018]"><?php echo $reschedule_data ? 'Confirm Reschedule' : 'Book Appointment'; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Receipt Modal -->
<div id="receiptModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden mx-4">
        <div class="bg-[#800020] px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-white"><i class="fas fa-receipt mr-2"></i>Appointment Receipt</h3>
            <button onclick="closeReceiptModal()" class="text-white/80 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-6 space-y-4" id="receiptContent">
            <!-- Content will be injected here via JS -->
        </div>
        <div class="bg-gray-50 px-6 py-4 flex justify-end">
            <button onclick="window.print()" class="px-4 py-2 bg-[#c9a84c] text-[#800020] text-sm font-semibold rounded-lg hover:bg-[#b09342] transition mr-2"><i class="fas fa-print mr-1"></i> Print</button>
            <button onclick="closeReceiptModal()" class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-100 transition">Close</button>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<div id="notificationDetailsModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl mx-4 overflow-hidden">
        <div class="border-b px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2" id="notifModalIcon">
                <!-- Icon injected -->
                Notification Details
            </h3>
            <button onclick="closeNotificationModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-6">
            <h4 class="font-bold text-lg text-gray-900 mb-1" id="notifModalTitle">Title</h4>
            <p class="text-xs text-gray-500 mb-4" id="notifModalDate">Date</p>
            <div class="text-gray-700 text-sm whitespace-pre-line" id="notifModalMessage">
                Message content
            </div>
        </div>
        <div class="bg-gray-50 px-6 py-4 flex justify-end">
            <button onclick="closeNotificationModal()" class="px-4 py-2 bg-[#800020] text-white text-sm font-medium rounded-lg hover:bg-[#600018] transition">Done</button>
        </div>
    </div>
</div>

<!-- Bottom Navigation Bar (Mobile only) -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden z-40 shadow-lg safe-bottom">
    <div class="flex justify-around py-2">
        <a href="student_dashboard.php" class="flex flex-col items-center py-1 px-2 text-gray-400">
            <i class="fas fa-home text-xl"></i><span class="text-[10px] mt-1">Home</span>
        </a>
        <a href="student_qr.php" class="flex flex-col items-center py-1 px-2 text-gray-400">
            <i class="fas fa-qrcode text-xl"></i><span class="text-[10px] mt-1">QR</span>
        </a>
        <a href="student_record.php" class="flex flex-col items-center py-1 px-2 text-gray-400">
            <i class="fas fa-notes-medical text-xl"></i><span class="text-[10px] mt-1">Records</span>
        </a>
        <a href="student_appointments.php" class="flex flex-col items-center py-1 px-2 text-[#800020]">
            <i class="fas fa-calendar-alt text-xl"></i><span class="text-[10px] mt-1">Appts</span>
        </a>
        <a href="student_announcement.php" class="flex flex-col items-center py-1 px-2 text-gray-400">
            <i class="fas fa-newspaper text-xl"></i><span class="text-[10px] mt-1">News</span>
        </a>
        <a href="student_settings.php" class="flex flex-col items-center py-1 px-2 text-gray-400">
            <i class="fas fa-user-circle text-xl"></i><span class="text-[10px] mt-1">Profile</span>
        </a>
    </div>
</div>

<script>
    // Functions for Modals
    function viewReceipt(data) {
        let content = `
            <div class="text-center mb-4">
                <h4 class="font-bold text-gray-900 text-lg">PUPBC Clinic</h4>
                <p class="text-xs text-gray-500">Appointment Confirmation</p>
            </div>
            <div class="border-t border-b border-dashed border-gray-300 py-4 mb-4 space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Student Name:</span>
                    <span class="text-sm font-semibold text-gray-900">${data.student_name}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Student No:</span>
                    <span class="text-sm font-semibold text-gray-900">${data.student_number}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Date:</span>
                    <span class="text-sm font-semibold text-gray-900">${data.date}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Time:</span>
                    <span class="text-sm font-semibold text-gray-900">${data.time}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Purpose:</span>
                    <span class="text-sm font-semibold text-gray-900">${data.purpose}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Status:</span>
                    <span class="text-sm font-bold text-yellow-600 uppercase">${data.status}</span>
                </div>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">Symptoms/Remarks:</p>
                <p class="text-sm text-gray-800 bg-gray-50 p-2 rounded">${data.symptoms || 'None provided'}</p>
            </div>
            <div class="text-center mt-4 pt-4 border-t border-gray-200">
                <p class="text-[10px] text-gray-400">Please present this receipt at the clinic window on your appointment date.</p>
            </div>
        `;
        document.getElementById('receiptContent').innerHTML = content;
        document.getElementById('receiptModal').classList.remove('hidden');
        document.getElementById('receiptModal').classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeReceiptModal() {
        document.getElementById('receiptModal').classList.add('hidden');
        document.getElementById('receiptModal').classList.remove('flex');
        document.body.style.overflow = '';
    }

    function viewNotification(data) {
        document.getElementById('notifModalTitle').textContent = data.title;
        document.getElementById('notifModalDate').textContent = data.full_date;
        document.getElementById('notifModalMessage').textContent = data.message;
        
        let iconHtml = '';
        if (data.type == 'announcement') {
            iconHtml = '<i class="fas fa-bullhorn text-[#800020]"></i>';
        } else if (data.type == 'appointment') {
            iconHtml = '<i class="fas fa-calendar-alt text-blue-500"></i>';
        } else {
            iconHtml = '<i class="fas fa-info-circle text-gray-500"></i>';
        }
        document.getElementById('notifModalIcon').innerHTML = iconHtml + ' Notification Details';
        
        document.getElementById('notificationDetailsModal').classList.remove('hidden');
        document.getElementById('notificationDetailsModal').classList.add('flex');
        document.body.style.overflow = 'hidden';
        
        // Hide dropdown
        document.getElementById('notificationDropdown').classList.add('hidden');
        
        // Mark as read via AJAX
        $.ajax({
            url: '../../ajax/mark_read.php',
            type: 'POST',
            data: { notif_id: data.id },
            success: function(response) {
                if (response.success) {
                    // Update badge
                    const badge = document.querySelector('#notificationBtn span');
                    if (badge) {
                        if (response.unread_count > 0) {
                            badge.textContent = response.unread_count > 9 ? '9+' : response.unread_count;
                        } else {
                            badge.remove();
                        }
                    }
                    // Remove unread dots
                    const dots = document.querySelectorAll('.notif-dot-' + data.id);
                    dots.forEach(dot => dot.remove());
                }
            }
        });
    }

    function closeNotificationModal() {
        document.getElementById('notificationDetailsModal').classList.add('hidden');
        document.getElementById('notificationDetailsModal').classList.remove('flex');
        document.body.style.overflow = '';
    }

    document.getElementById('receiptModal').addEventListener('click', function(e) {
        if (e.target === this) closeReceiptModal();
    });
    
    document.getElementById('notificationDetailsModal').addEventListener('click', function(e) {
        if (e.target === this) closeNotificationModal();
    });

    // Notification Dropdown Toggle
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (notificationBtn && notificationDropdown) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('hidden');
        });
        
        document.addEventListener('click', function() {
            notificationDropdown.classList.add('hidden');
        });
        
        notificationDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    function markAllAsRead() {
        $.ajax({
            url: '../../ajax/mark_all_read.php',
            type: 'POST',
            success: function(response) {
                location.reload();
            }
        });
    }
    
    function showTab(tab) {
        const upcoming = document.getElementById('upcomingTab');
        const past = document.getElementById('pastTab');
        const upcomingBtn = document.getElementById('tabUpcomingBtn');
        const pastBtn = document.getElementById('tabPastBtn');
        
        if (tab === 'upcoming') {
            upcoming.classList.remove('hidden'); past.classList.add('hidden');
            upcomingBtn.classList.add('border-[#800020]', 'text-[#800020]'); upcomingBtn.classList.remove('text-gray-500');
            pastBtn.classList.remove('border-[#800020]', 'text-[#800020]'); pastBtn.classList.add('text-gray-500');
        } else {
            upcoming.classList.add('hidden'); past.classList.remove('hidden');
            pastBtn.classList.add('border-[#800020]', 'text-[#800020]'); pastBtn.classList.remove('text-gray-500');
            upcomingBtn.classList.remove('border-[#800020]', 'text-[#800020]'); upcomingBtn.classList.add('text-gray-500');
        }
    }
    
    function openBookingModal() { 
        document.getElementById('bookingModal').classList.remove('hidden'); 
        document.getElementById('bookingModal').classList.add('flex'); 
        document.body.style.overflow = 'hidden'; 
    }
    
    function closeBookingModal() { 
        document.getElementById('bookingModal').classList.add('hidden'); 
        document.getElementById('bookingModal').classList.remove('flex'); 
        document.body.style.overflow = ''; 
    }
    
    const dateInput = document.getElementById('appointment_date');
    if (dateInput) { 
        const today = new Date(); 
        dateInput.min = today.toISOString().split('T')[0]; 
    }
    
    document.getElementById('bookingModal')?.addEventListener('click', function(e) { 
        if (e.target === this) closeBookingModal(); 
    });
    
    // Auto-refresh notifications every 30 seconds
    setInterval(function() {
        $.ajax({
            url: '../../ajax/get_notifications.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.unread_count > 0) {
                    const badge = document.querySelector('#notificationBtn span');
                    if (badge) {
                        badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                        badge.classList.add('pulse-animation');
                        setTimeout(() => badge.classList.remove('pulse-animation'), 500);
                    }
                }
            }
        });
    }, 30000);
</script>

</body>
</html>