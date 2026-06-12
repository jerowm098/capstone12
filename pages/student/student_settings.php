<?php
// capstonemain/pages/student/student_settings.php
session_start();
if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit();
}
require_once '../../config/db_connect.php';
require_once '../../includes/notification_helper.php';

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
} elseif (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$student_id = $_SESSION['student_id'];

// Get student data
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, u.email, s.* 
                      FROM students s JOIN users u ON s.user_id = u.id 
                      WHERE s.id = ?");
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

if (!$student) {
    $student = [
        'first_name' => $_SESSION['first_name'] ?? 'Student',
        'last_name' => $_SESSION['last_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'student_number' => $_SESSION['student_number'] ?? 'N/A',
        'course' => $_SESSION['course'] ?? 'N/A',
        'year_level' => $_SESSION['year_level'] ?? 'N/A',
        'blood_type' => 'N/A',
        'allergies' => '',
        'medical_conditions' => '',
        'emergency_contact' => '',
        'emergency_phone' => '',
        'emergency_relation' => '',
        'email_notifications' => 1,
        'appointment_reminders' => 1,
        'announcement_alerts' => 1
    ];
}

$first_name = $student['first_name'] ?? 'Student';
$last_name = $student['last_name'] ?? '';
$full_name = trim($first_name . ' ' . $last_name);
$student_number = $student['student_number'] ?? 'N/A';
$course = $student['course'] ?? 'N/A';
$year_level = $student['year_level'] ?? 'N/A';
$email = $student['email'] ?? 'N/A';
$blood_type = $student['blood_type'] ?? 'N/A';
$allergies = $student['allergies'] ?? '';
$medical_conditions = $student['medical_conditions'] ?? '';
$emergency_contact = $student['emergency_contact'] ?? '';
$emergency_phone = $student['emergency_phone'] ?? '';
$emergency_relation = $student['emergency_relation'] ?? '';
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get unread notifications count for badge
$unread_count = getUnreadCount($student_id);
$notifications = getUnreadNotifications($student_id, 5);

// Get current notification preferences
$email_notifications = $student['email_notifications'] ?? 1;
$appointment_reminders = $student['appointment_reminders'] ?? 1;
$announcement_alerts = $student['announcement_alerts'] ?? 1;

$success_message = '';
$error_message = '';

// Handle Edit Profile Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    $new_email = trim($_POST['email'] ?? '');
    $new_blood_type = $_POST['blood_type'] ?? '';
    $new_allergies = trim($_POST['allergies'] ?? '');
    $new_conditions = trim($_POST['medical_conditions'] ?? '');
    $new_emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $new_emergency_phone = trim($_POST['emergency_phone'] ?? '');
    $new_emergency_relation = $_POST['emergency_relation'] ?? '';
    
    $errors = [];
    
    if (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (!empty($new_emergency_phone) && !preg_match('/^09\d{9}$/', $new_emergency_phone)) {
        $errors[] = 'Phone must be 09XXXXXXXXX format (11 digits).';
    }
    
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "UPDATE students s JOIN users u ON s.user_id = u.id 
                              SET u.email = ?, s.blood_type = ?, s.allergies = ?, s.medical_conditions = ?,
                                  s.emergency_contact = ?, s.emergency_phone = ?, s.emergency_relation = ?
                              WHERE s.id = ?");
        mysqli_stmt_bind_param($stmt, "sssssssi", $new_email, $new_blood_type, $new_allergies, $new_conditions, $new_emergency_contact, $new_emergency_phone, $new_emergency_relation, $student_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_message = 'Profile updated successfully!';
            $email = $new_email;
            $blood_type = $new_blood_type;
            $allergies = $new_allergies;
            $medical_conditions = $new_conditions;
            $emergency_contact = $new_emergency_contact;
            $emergency_phone = $new_emergency_phone;
            $emergency_relation = $new_emergency_relation;
            $_SESSION['user_email'] = $new_email;
        } else {
            $error_message = 'Failed to update profile.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (empty($current_password)) $errors[] = 'Current password is required.';
    if (empty($new_password)) $errors[] = 'New password is required.';
    elseif (strlen($new_password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($new_password !== $confirm_password) $errors[] = 'Passwords do not match.';
    
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT u.password FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        if ($user && password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt2 = mysqli_prepare($conn, "UPDATE students s JOIN users u ON s.user_id = u.id SET u.password = ? WHERE s.id = ?");
            mysqli_stmt_bind_param($stmt2, "si", $hashed_password, $student_id);
            if (mysqli_stmt_execute($stmt2)) {
                $success_message = 'Password changed successfully!';
            } else {
                $error_message = 'Failed to change password.';
            }
        } else {
            $error_message = 'Current password is incorrect.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Handle Notification Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notifications'])) {
    $email_notif = isset($_POST['email_notifications']) ? 1 : 0;
    $appointment_reminder = isset($_POST['appointment_reminders']) ? 1 : 0;
    $announcement_alert = isset($_POST['announcement_alerts']) ? 1 : 0;
    
    $stmt = mysqli_prepare($conn, "UPDATE students SET email_notifications = ?, appointment_reminders = ?, announcement_alerts = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "iiii", $email_notif, $appointment_reminder, $announcement_alert, $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $success_message = 'Notification preferences saved successfully!';
        $email_notifications = $email_notif;
        $appointment_reminders = $appointment_reminder;
        $announcement_alerts = $announcement_alert;
    } else {
        $error_message = 'Failed to save notification preferences.';
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
$relations = ['Parent', 'Guardian', 'Sibling', 'Spouse', 'Relative', 'Friend'];

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
    <title>Profile - PUPBC Carelink</title>
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
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .notification-item:hover { background-color: #f9fafb; }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .pulse-animation { animation: pulse 0.5s ease-in-out; }
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: #800020; }
        input:checked + .slider:before { transform: translateX(20px); }
    </style>
</head>
<body>

<!-- Desktop Sidebar -->
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
        <a href="student_dashboard.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]"><i class="fas fa-home w-5"></i><span class="text-sm font-medium">Dashboard</span></a>
        <a href="student_qr.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]"><i class="fas fa-qrcode w-5"></i><span class="text-sm font-medium">QR Code</span></a>
        <a href="student_record.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]"><i class="fas fa-notes-medical w-5"></i><span class="text-sm font-medium">Health Records</span></a>
        <a href="student_appointments.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]"><i class="fas fa-calendar-alt w-5"></i><span class="text-sm font-medium">Appointments</span></a>
        <a href="student_announcement.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]"><i class="fas fa-newspaper w-5"></i><span class="text-sm font-medium">Announcements</span></a>
        <a href="student_settings.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'student_settings.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>"><i class="fas fa-user-circle w-5"></i><span class="text-sm font-medium">Profile</span></a>
        <div class="border-t border-[#600018] my-4 mx-3"></div>
        <a href="student_logout.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/70 hover:text-white hover:bg-[#600018] transition-all"><i class="fas fa-sign-out-alt w-5"></i><span class="text-sm font-medium">Sign Out</span></a>
    </nav>
    <div class="p-4 border-t border-[#600018] mt-auto"><p class="text-[10px] text-white/40 text-center">© <?php echo date('Y'); ?> PUPBC Carelink</p></div>
</aside>

<!-- Main Content -->
<main class="md:ml-64 min-h-screen pb-20 md:pb-6">
    
    <!-- Header -->
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200">
        <div class="px-4 md:px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Profile Settings</h1>
                <p class="text-sm text-gray-500">Manage your account preferences</p>
            </div>
            
            <!-- Notification Bell -->
            <div class="relative">
                <button id="notificationBtn" class="relative focus:outline-none p-2 hover:bg-gray-100 rounded-full transition">
                    <i class="fas fa-bell text-gray-600 text-xl"></i>
                    <?php if ($unread_count > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                    <?php endif; ?>
                </button>
                <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border z-50">
                    <div class="p-3 border-b bg-gray-50 rounded-t-lg flex justify-between items-center">
                        <h4 class="font-semibold text-gray-900">Notifications</h4>
                        <div class="flex gap-2">
                            <?php if ($unread_count > 0): ?><button onclick="markAllAsRead()" class="text-xs text-[#800020] hover:underline">Mark all read</button><?php endif; ?>
                            <a href="student_notifications.php" class="text-xs text-[#800020] hover:underline">View all</a>
                        </div>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <?php if (empty($notifications)): ?>
                            <div class="p-4 text-center text-gray-500"><i class="fas fa-bell-slash text-3xl mb-2 block"></i><p class="text-sm">No new notifications</p></div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                            <a href="<?php echo $notif['link']; ?>?notif_id=<?php echo $notif['id']; ?>" class="notification-item block p-3 border-b hover:bg-gray-50 transition">
                                <div class="flex items-start gap-2">
                                    <div class="mt-0.5"><?php if ($notif['type'] == 'announcement'): ?><i class="fas fa-bullhorn text-[#800020]"></i><?php elseif ($notif['type'] == 'appointment'): ?><i class="fas fa-calendar-alt text-blue-500"></i><?php else: ?><i class="fas fa-info-circle text-gray-500"></i><?php endif; ?></div>
                                    <div class="flex-1"><p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notif['title']); ?></p><p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars(substr($notif['message'], 0, 80)); ?></p><p class="text-xs text-gray-400 mt-1"><?php echo timeAgo($notif['created_at']); ?></p></div>
                                    <?php if (!$notif['is_read']): ?><div class="w-2 h-2 bg-[#800020] rounded-full"></div><?php endif; ?>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Page Content -->
    <div class="p-4 md:p-6">
        <div class="max-w-4xl mx-auto">
            
            <!-- Edit Profile Button -->
            <div class="flex justify-end mb-4">
                <button onclick="openEditModal()" class="inline-flex items-center gap-2 rounded-lg bg-[#800020] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#600018] transition"><i class="fas fa-edit"></i> Edit Profile</button>
            </div>
            
            <?php if ($success_message): ?>
                <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700"><i class="fas fa-check-circle mr-2"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700"><i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Profile Information Card -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-6 card-hover">
                <div class="px-5 py-4 border-b bg-gray-50/50">
                    <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-user-circle text-[#800020] text-lg"></i> Profile Information</h3>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div><label class="text-xs text-gray-400 uppercase tracking-wider">Full Name</label><p class="text-gray-800 font-medium mt-1"><?php echo htmlspecialchars($full_name); ?></p></div>
                        <div><label class="text-xs text-gray-400 uppercase tracking-wider">Student Number</label><p class="text-gray-800 font-medium mt-1"><?php echo htmlspecialchars($student_number); ?></p></div>
                        <div><label class="text-xs text-gray-400 uppercase tracking-wider">Course & Year</label><p class="text-gray-800 font-medium mt-1"><?php echo htmlspecialchars($course . ' - ' . $year_level); ?></p></div>
                        <div><label class="text-xs text-gray-400 uppercase tracking-wider">Email</label><p class="text-gray-800 font-medium mt-1 break-all"><?php echo htmlspecialchars($email); ?></p></div>
                        <div><label class="text-xs text-gray-400 uppercase tracking-wider">Blood Type</label><p class="text-gray-800 font-medium mt-1"><?php echo htmlspecialchars($blood_type); ?></p></div>
                        <div><label class="text-xs text-gray-400 uppercase tracking-wider">Allergies</label><p class="text-gray-800 font-medium mt-1"><?php echo !empty($allergies) ? htmlspecialchars($allergies) : '—'; ?></p></div>
                        <div><label class="text-xs text-gray-400 uppercase tracking-wider">Medical Conditions</label><p class="text-gray-800 font-medium mt-1"><?php echo !empty($medical_conditions) ? htmlspecialchars($medical_conditions) : '—'; ?></p></div>
                        <div><label class="text-xs text-gray-400 uppercase tracking-wider">Emergency Contact</label><p class="text-gray-800 font-medium mt-1"><?php echo !empty($emergency_contact) ? htmlspecialchars($emergency_contact) : '—'; ?></p></div>
                        <div><label class="text-xs text-gray-400 uppercase tracking-wider">Emergency Phone</label><p class="text-gray-800 font-medium mt-1"><?php echo !empty($emergency_phone) ? htmlspecialchars($emergency_phone) : '—'; ?></p></div>
                        <div><label class="text-xs text-gray-400 uppercase tracking-wider">Emergency Relation</label><p class="text-gray-800 font-medium mt-1"><?php echo !empty($emergency_relation) ? htmlspecialchars($emergency_relation) : '—'; ?></p></div>
                    </div>
                </div>
            </div>
            
            <!-- Change Password Card -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-6 card-hover">
                <div class="px-5 py-4 border-b bg-gray-50/50">
                    <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-lock text-[#800020] text-lg"></i> Change Password</h3>
                </div>
                <div class="p-5">
                    <form method="POST" class="space-y-4 max-w-md">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label><input type="password" name="current_password" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">New Password</label><input type="password" name="new_password" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"><p class="mt-1 text-xs text-gray-400">Minimum 8 characters</p></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label><input type="password" name="confirm_password" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                        <div><button type="submit" name="change_password" class="inline-flex items-center gap-2 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018] transition"><i class="fas fa-key"></i> Update Password</button></div>
                    </form>
                </div>
            </div>
            
            <!-- Notification Settings Card -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden card-hover">
                <div class="px-5 py-4 border-b bg-gray-50/50">
                    <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-bell text-[#800020] text-lg"></i> Notification Preferences</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Manage how you receive notifications</p>
                </div>
                <div class="p-5">
                    <form method="POST" id="notificationForm" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="flex items-center justify-between py-2">
                            <div class="pr-3"><div class="text-sm font-medium text-gray-900">📧 Email Notifications</div><div class="text-xs text-gray-500">Receive email updates about your queue numbers and health records</div></div>
                            <label class="switch"><input type="checkbox" name="email_notifications" value="1" <?php echo $email_notifications ? 'checked' : ''; ?> onchange="document.getElementById('notificationForm').submit();"><span class="slider"></span></label>
                        </div>
                        <div class="flex items-center justify-between py-2 border-t border-gray-100">
                            <div class="pr-3"><div class="text-sm font-medium text-gray-900">📅 Appointment Reminders</div><div class="text-xs text-gray-500">Get notified about upcoming appointments</div></div>
                            <label class="switch"><input type="checkbox" name="appointment_reminders" value="1" <?php echo $appointment_reminders ? 'checked' : ''; ?> onchange="document.getElementById('notificationForm').submit();"><span class="slider"></span></label>
                        </div>
                        <div class="flex items-center justify-between py-2 border-t border-gray-100">
                            <div class="pr-3"><div class="text-sm font-medium text-gray-900">📢 Announcements</div><div class="text-xs text-gray-500">Receive clinic announcements and health advisories</div></div>
                            <label class="switch"><input type="checkbox" name="announcement_alerts" value="1" <?php echo $announcement_alerts ? 'checked' : ''; ?> onchange="document.getElementById('notificationForm').submit();"><span class="slider"></span></label>
                        </div>
                        <input type="hidden" name="save_notifications" value="1">
                    </form>
                    
                    <div class="mt-5 p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <div class="flex items-start gap-2">
                            <i class="fas fa-envelope text-[#800020] mt-0.5"></i>
                            <div class="text-xs text-gray-600"><span class="font-medium text-gray-700">Email notifications will be sent to:</span><span class="block break-all text-[#800020] font-mono mt-0.5"><?php echo htmlspecialchars($email); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SIGN OUT BUTTON - at the very bottom of profile page -->
            <div class="mt-8 pt-4 border-t border-gray-200">
                <a href="student_logout.php" 
                   onclick="return confirm('Are you sure you want to sign out?')" 
                   class="flex items-center justify-center gap-3 w-full rounded-lg bg-red-50 px-4 py-3 text-red-600 hover:bg-red-100 transition-all duration-200">
                    <i class="fas fa-sign-out-alt text-lg"></i>
                    <span class="font-medium">Sign Out</span>
                </a>
                <p class="text-center text-xs text-gray-400 mt-4">PUPBC Carelink v1.0 © <?php echo date('Y'); ?></p>
            </div>
            
        </div>
    </div>
</main>

<!-- Bottom Navigation Bar (Mobile only) - NO SIGN OUT HERE, nasa loob na ng profile -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden z-40 shadow-lg safe-bottom">
    <div class="flex justify-around py-2">
        <a href="student_dashboard.php" class="flex flex-col items-center py-1 px-2 text-gray-400"><i class="fas fa-home text-xl"></i><span class="text-[10px] mt-1">Home</span></a>
        <a href="student_qr.php" class="flex flex-col items-center py-1 px-2 text-gray-400"><i class="fas fa-qrcode text-xl"></i><span class="text-[10px] mt-1">QR</span></a>
        <a href="student_record.php" class="flex flex-col items-center py-1 px-2 text-gray-400"><i class="fas fa-notes-medical text-xl"></i><span class="text-[10px] mt-1">Records</span></a>
        <a href="student_appointments.php" class="flex flex-col items-center py-1 px-2 text-gray-400"><i class="fas fa-calendar-alt text-xl"></i><span class="text-[10px] mt-1">Appts</span></a>
        <a href="student_announcement.php" class="flex flex-col items-center py-1 px-2 text-gray-400"><i class="fas fa-newspaper text-xl"></i><span class="text-[10px] mt-1">News</span></a>
        <a href="student_settings.php" class="flex flex-col items-center py-1 px-2 text-[#800020]"><i class="fas fa-user-circle text-xl"></i><span class="text-[10px] mt-1">Profile</span></a>
    </div>
</div>

<!-- EDIT PROFILE MODAL -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-edit mr-2 text-[#800020]"></i> Edit Profile</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">First Name</label><input type="text" value="<?php echo htmlspecialchars($first_name); ?>" disabled class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label><input type="text" value="<?php echo htmlspecialchars($last_name); ?>" disabled class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Student Number</label><input type="text" value="<?php echo htmlspecialchars($student_number); ?>" disabled class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Course & Year</label><input type="text" value="<?php echo htmlspecialchars($course . ' - ' . $year_level); ?>" disabled class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Email *</label><input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Blood Type</label><select name="blood_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"><option value="">Not specified</option><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type): ?><option value="<?php echo $type; ?>" <?php echo $blood_type === $type ? 'selected' : ''; ?>><?php echo $type; ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Emergency Relation</label><select name="emergency_relation" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"><option value="">Select</option><?php foreach ($relations as $rel): ?><option value="<?php echo $rel; ?>" <?php echo $emergency_relation === $rel ? 'selected' : ''; ?>><?php echo $rel; ?></option><?php endforeach; ?></select></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Allergies</label><input type="text" name="allergies" value="<?php echo htmlspecialchars($allergies); ?>" placeholder="e.g., Penicillin, Shellfish" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Medical Conditions</label><input type="text" name="medical_conditions" value="<?php echo htmlspecialchars($medical_conditions); ?>" placeholder="e.g., Asthma, Diabetes" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Person</label><input type="text" name="emergency_contact" value="<?php echo htmlspecialchars($emergency_contact); ?>" placeholder="Full name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Emergency Phone</label><input type="tel" name="emergency_phone" value="<?php echo htmlspecialchars($emergency_phone); ?>" placeholder="09123456789" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
            </div>
            <div class="flex gap-3 pt-4 border-t">
                <button type="button" onclick="closeEditModal()" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" name="edit_profile" class="flex-1 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018]">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (notificationBtn && notificationDropdown) {
        notificationBtn.addEventListener('click', function(e) { e.stopPropagation(); notificationDropdown.classList.toggle('hidden'); });
        document.addEventListener('click', function() { notificationDropdown.classList.add('hidden'); });
        notificationDropdown.addEventListener('click', function(e) { e.stopPropagation(); });
    }
    
    function markAllAsRead() { $.ajax({ url: '../../ajax/mark_all_read.php', type: 'POST', success: function(response) { location.reload(); } }); }
    function openEditModal() { document.getElementById('editModal').classList.remove('hidden'); document.getElementById('editModal').classList.add('flex'); document.body.style.overflow = 'hidden'; }
    function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); document.getElementById('editModal').classList.remove('flex'); document.body.style.overflow = ''; }
    document.getElementById('editModal')?.addEventListener('click', function(e) { if (e.target === this) closeEditModal(); });
    
    setInterval(function() { $.ajax({ url: '../../ajax/get_notifications.php', type: 'GET', dataType: 'json', success: function(data) { if (data.unread_count > 0) { const badge = document.querySelector('#notificationBtn span'); if (badge) { badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count; badge.classList.add('pulse-animation'); setTimeout(() => badge.classList.remove('pulse-animation'), 500); } } } }); }, 30000);
</script>

</body>
</html>