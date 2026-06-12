<?php
// ============================================
// capstonemain/pages/student/student_dashboard.php
// PUPBC CARELINK - STUDENT DASHBOARD
// FIXED: Matches actual database schema
// ============================================

// --- 1. SECURE SESSION INITIALIZATION ---
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
}

// --- 2. AUTHENTICATION GUARD ---
if (!isset($_SESSION['student_id']) || empty($_SESSION['student_id'])) {
    error_log("Unauthorized dashboard access attempt from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header('Location: student_login.php');
    exit();
}

// --- 3. SESSION TIMEOUT VALIDATION (30 minutes) ---
$session_timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    session_unset();
    session_destroy();
    header('Location: student_login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// --- 4. DATABASE CONNECTION ---
require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_errno) {
    error_log("CRITICAL: Database connection failed in student_dashboard.php - " . ($conn->connect_error ?? 'Unknown error'));
    die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>System Error</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f8fafc}.card{background:white;padding:40px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.1);text-align:center;max-width:420px;margin:20px}.card h2{color:#800020}.card p{color:#4b5563;margin:12px 0 24px}.card a{color:#800020;font-weight:600;text-decoration:none}</style></head><body><div class="card"><h2>System Unavailable</h2><p>We are experiencing technical difficulties. Please try again in a few minutes.</p><a href="student_login.php">Return to Login</a></div></body></html>');
}

// --- 5. DETECT AVAILABLE DATABASE COLUMNS (One-time check per session) ---
if (!isset($_SESSION['db_cols_checked_v2'])) {
    // Users table
    $_SESSION['db_has_profile_photo'] = false;
    $_SESSION['db_has_account_status'] = false;
    $_SESSION['db_has_last_login'] = false;
    
    $users_cols = mysqli_query($conn, "SHOW COLUMNS FROM users");
    if ($users_cols) {
        while ($col = mysqli_fetch_assoc($users_cols)) {
            $name = $col['Field'];
            if ($name === 'profile_photo') $_SESSION['db_has_profile_photo'] = true;
            if ($name === 'account_status') $_SESSION['db_has_account_status'] = true;
            if ($name === 'last_login') $_SESSION['db_has_last_login'] = true;
        }
        mysqli_free_result($users_cols);
    }
    
    // Students table - check actual column names from your schema
    $_SESSION['db_has_address'] = false;
    $_SESSION['db_has_contact_number'] = false;
    
    $students_cols = mysqli_query($conn, "SHOW COLUMNS FROM students");
    if ($students_cols) {
        while ($col = mysqli_fetch_assoc($students_cols)) {
            $name = $col['Field'];
            if ($name === 'address') $_SESSION['db_has_address'] = true;
            if ($name === 'contact_number') $_SESSION['db_has_contact_number'] = true;
        }
        mysqli_free_result($students_cols);
    }
    
    // Appointments table
    $_SESSION['db_has_clinic_location'] = false;
    $appt_cols = mysqli_query($conn, "SHOW COLUMNS FROM appointments");
    if ($appt_cols) {
        while ($col = mysqli_fetch_assoc($appt_cols)) {
            if ($col['Field'] === 'clinic_location') $_SESSION['db_has_clinic_location'] = true;
        }
        mysqli_free_result($appt_cols);
    }
    
    $_SESSION['db_cols_checked_v2'] = true;
}

// --- 6. INCLUDE HELPER FUNCTIONS ---
$notification_helper_path = __DIR__ . '/../../includes/notification_helper.php';
if (file_exists($notification_helper_path)) {
    require_once $notification_helper_path;
}

// --- 7. VARIABLE INITIALIZATION ---
$student_id = (int)$_SESSION['student_id'];
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize student data with defaults matching YOUR schema
$student = [
    'first_name'     => 'Student',
    'last_name'      => '',
    'email'          => 'N/A',
    'student_number' => 'N/A',
    'course'         => 'N/A',
    'year_level'     => 'N/A',
    'blood_type'     => 'Not specified',
    'allergies'      => 'None',
    'medical_conditions' => 'None',
    'emergency_contact' => 'Not set',      // YOUR actual column name
    'emergency_phone' => 'Not set',         // YOUR actual column name
    'emergency_relation' => 'Not set',      // YOUR actual column name
    'qr_code'        => null,               // YOUR actual column name
    'profile_photo'  => null,
    'account_status' => 'active',
];

// --- 8. BUILD DYNAMIC SELECT QUERY (matches YOUR schema) ---
$select_parts = [
    'u.id AS user_id',
    'u.first_name', 
    'u.last_name', 
    'u.email',
    's.student_number',
    's.course',
    's.year_level',
    's.birthdate',
    's.blood_type',
    's.allergies',
    's.medical_conditions',
    's.emergency_contact',      // YOUR actual column name
    's.emergency_phone',         // YOUR actual column name
    's.emergency_relation',      // YOUR actual column name
    's.qr_code',                 // YOUR actual column name
    's.created_at',
    's.updated_at'
];

// Optional users columns
if ($_SESSION['db_has_profile_photo']) {
    $select_parts[] = 'u.profile_photo';
}
if ($_SESSION['db_has_account_status']) {
    $select_parts[] = 'u.account_status';
}
if ($_SESSION['db_has_last_login']) {
    $select_parts[] = 'u.last_login';
}

// Optional students columns
if ($_SESSION['db_has_address']) {
    $select_parts[] = 's.address';
}
if ($_SESSION['db_has_contact_number']) {
    $select_parts[] = 's.contact_number';
}

$select_query = "SELECT " . implode(', ', $select_parts) . " 
                 FROM students s 
                 INNER JOIN users u ON s.user_id = u.id 
                 WHERE s.id = ? 
                 LIMIT 1";

// --- 9. FETCH STUDENT PROFILE ---
$stmt = mysqli_prepare($conn, $select_query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $fetched = mysqli_fetch_assoc($result);
        if ($fetched) {
            $student = array_merge($student, $fetched);
        } else {
            error_log("WARNING: Student ID $student_id exists in session but not in database");
            session_destroy();
            header('Location: student_login.php?error=profile_not_found');
            exit();
        }
    } else {
        error_log("Database query execution failed: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
} else {
    error_log("Database prepare statement failed: " . mysqli_error($conn));
    die('A system error occurred. Please try again later.');
}

// --- 10. EXTRACT STUDENT DATA ---
$first_name    = htmlspecialchars($student['first_name'] ?? 'Student', ENT_QUOTES, 'UTF-8');
$last_name     = htmlspecialchars($student['last_name'] ?? '', ENT_QUOTES, 'UTF-8');
$full_name     = trim($first_name . ' ' . $last_name) ?: 'Student';
$student_number = htmlspecialchars($student['student_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$course        = htmlspecialchars($student['course'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$year_level    = htmlspecialchars($student['year_level'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$blood_type    = htmlspecialchars($student['blood_type'] ?? 'Not specified', ENT_QUOTES, 'UTF-8');
$allergies     = htmlspecialchars($student['allergies'] ?? 'None', ENT_QUOTES, 'UTF-8');
$medical_conditions = htmlspecialchars($student['medical_conditions'] ?? 'None', ENT_QUOTES, 'UTF-8');

// Use YOUR actual column names
$emergency_contact = htmlspecialchars($student['emergency_contact'] ?? 'Not set', ENT_QUOTES, 'UTF-8');
$emergency_phone = htmlspecialchars($student['emergency_phone'] ?? 'Not set', ENT_QUOTES, 'UTF-8');
$emergency_relation = htmlspecialchars($student['emergency_relation'] ?? '', ENT_QUOTES, 'UTF-8');
$qr_code = $student['qr_code'] ?? null;

// Optional fields
$profile_photo = $_SESSION['db_has_profile_photo'] ? ($student['profile_photo'] ?? null) : null;
$account_status = $_SESSION['db_has_account_status'] ? ($student['account_status'] ?? 'active') : 'active';
$last_login = $_SESSION['db_has_last_login'] ? ($student['last_login'] ?? null) : null;

// Initials for avatar
$initials = strtoupper(substr($student['first_name'] ?? 'S', 0, 1) . substr($student['last_name'] ?? '', 0, 1));

// --- 11. FETCH STATISTICS ---
$total_visits = 0;
$completed_visits = 0;
$pending_appointments = 0;
$health_records_count = 0;
$last_visit_date = 'No visits yet';
$health_score = 85;

// Total Visits
$stmt1 = mysqli_prepare($conn, "SELECT COUNT(*) AS count FROM visits WHERE student_id = ?");
if ($stmt1) {
    mysqli_stmt_bind_param($stmt1, "i", $student_id);
    mysqli_stmt_execute($stmt1);
    $res1 = mysqli_stmt_get_result($stmt1);
    $total_visits = mysqli_fetch_assoc($res1)['count'] ?? 0;
    mysqli_stmt_close($stmt1);
}

// Completed Visits
$stmt2 = mysqli_prepare($conn, "SELECT COUNT(*) AS count FROM visits WHERE student_id = ? AND status = 'completed'");
if ($stmt2) {
    mysqli_stmt_bind_param($stmt2, "i", $student_id);
    mysqli_stmt_execute($stmt2);
    $res2 = mysqli_stmt_get_result($stmt2);
    $completed_visits = mysqli_fetch_assoc($res2)['count'] ?? 0;
    mysqli_stmt_close($stmt2);
}

// Pending Appointments
$stmt3 = mysqli_prepare($conn, "SELECT COUNT(*) AS count FROM appointments WHERE student_id = ? AND status IN ('pending','confirmed') AND appointment_date >= CURDATE()");
if ($stmt3) {
    mysqli_stmt_bind_param($stmt3, "i", $student_id);
    mysqli_stmt_execute($stmt3);
    $res3 = mysqli_stmt_get_result($stmt3);
    $pending_appointments = mysqli_fetch_assoc($res3)['count'] ?? 0;
    mysqli_stmt_close($stmt3);
}

// Health Records Count
$hr_table = mysqli_query($conn, "SHOW TABLES LIKE 'health_records'");
if ($hr_table && mysqli_num_rows($hr_table) > 0) {
    $stmt4 = mysqli_prepare($conn, "SELECT COUNT(*) AS count FROM health_records WHERE student_id = ?");
    if ($stmt4) {
        mysqli_stmt_bind_param($stmt4, "i", $student_id);
        mysqli_stmt_execute($stmt4);
        $res4 = mysqli_stmt_get_result($stmt4);
        $health_records_count = mysqli_fetch_assoc($res4)['count'] ?? 0;
        mysqli_stmt_close($stmt4);
    }
}

// Last Visit
$stmt5 = mysqli_prepare($conn, "SELECT visit_date FROM visits WHERE student_id = ? AND status = 'completed' ORDER BY visit_date DESC LIMIT 1");
if ($stmt5) {
    mysqli_stmt_bind_param($stmt5, "i", $student_id);
    mysqli_stmt_execute($stmt5);
    $res5 = mysqli_stmt_get_result($stmt5);
    $last_visit = mysqli_fetch_assoc($res5);
    $last_visit_date = $last_visit ? date('M d, Y', strtotime($last_visit['visit_date'])) : 'No visits yet';
    mysqli_stmt_close($stmt5);
}

// Health Score
if ($total_visits > 0) {
    $completion_rate = $completed_visits / max($total_visits, 1);
    $health_score = min(100, max(50, round(60 + ($completion_rate * 40) - (min($pending_appointments, 5) * 2))));
}

// --- 12. UPCOMING APPOINTMENTS ---
$upcoming_appointments = [];
$appt_fields = ['id', 'appointment_date', 'appointment_time', 'purpose', 'status'];
if ($_SESSION['db_has_clinic_location']) {
    $appt_fields[] = 'clinic_location';
}

$appt_query = "SELECT " . implode(', ', $appt_fields) . " 
               FROM appointments 
               WHERE student_id = ? AND status IN ('pending','confirmed') AND appointment_date >= CURDATE() 
               ORDER BY appointment_date ASC, appointment_time ASC 
               LIMIT 3";

$stmt6 = mysqli_prepare($conn, $appt_query);
if ($stmt6) {
    mysqli_stmt_bind_param($stmt6, "i", $student_id);
    mysqli_stmt_execute($stmt6);
    $res6 = mysqli_stmt_get_result($stmt6);
    while ($row = mysqli_fetch_assoc($res6)) {
        $upcoming_appointments[] = $row;
    }
    mysqli_stmt_close($stmt6);
}

// --- 13. RECENT HEALTH RECORDS ---
$recent_records = [];
$stmt7 = mysqli_prepare($conn, 
    "SELECT v.id, v.visit_date, v.diagnosis, v.status,
            u.first_name AS nurse_first, u.last_name AS nurse_last
     FROM visits v 
     LEFT JOIN nurses n ON v.nurse_id = n.id 
     LEFT JOIN users u ON n.user_id = u.id 
     WHERE v.student_id = ? AND v.status = 'completed' 
     ORDER BY v.visit_date DESC 
     LIMIT 3"
);
if ($stmt7) {
    mysqli_stmt_bind_param($stmt7, "i", $student_id);
    mysqli_stmt_execute($stmt7);
    $res7 = mysqli_stmt_get_result($stmt7);
    while ($row = mysqli_fetch_assoc($res7)) {
        $recent_records[] = $row;
    }
    mysqli_stmt_close($stmt7);
}

// --- 14. NOTIFICATIONS ---
$unread_count = 0;
$notifications = [];

if (function_exists('getUnreadCount')) {
    $unread_count = getUnreadCount($student_id);
    $notifications = getUnreadNotifications($student_id, 5);
} else {
    $ntable = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
    if ($ntable && mysqli_num_rows($ntable) > 0) {
        $stmt8 = mysqli_prepare($conn, "SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND user_type = 'student' AND is_read = 0");
        if ($stmt8) {
            mysqli_stmt_bind_param($stmt8, "i", $student_id);
            mysqli_stmt_execute($stmt8);
            $res8 = mysqli_stmt_get_result($stmt8);
            $unread_count = mysqli_fetch_assoc($res8)['count'] ?? 0;
            mysqli_stmt_close($stmt8);
        }
    }
}

// --- 15. ANNOUNCEMENTS ---
$announcements = [];
$atable = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
if ($atable && mysqli_num_rows($atable) > 0) {
    $stmt9 = mysqli_query($conn, "SELECT id, title, content, created_at FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 2");
    if ($stmt9) {
        while ($row = mysqli_fetch_assoc($stmt9)) {
            $announcements[] = $row;
        }
    }
}

// --- 16. TIME AGO HELPER ---
function timeAgo($datetime) {
    if (empty($datetime)) return 'Just now';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'Unknown';
    $diff = time() - $timestamp;
    if ($diff < 0) return 'Just now';
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d', $timestamp);
}

// --- 17. ALERT MESSAGES ---
$alert_type = $_GET['alert'] ?? null;
$alert_messages = [
    'appointment_booked' => ['type' => 'success', 'message' => 'Appointment booked successfully!'],
    'profile_updated'   => ['type' => 'success', 'message' => 'Profile updated successfully!'],
    'record_updated'    => ['type' => 'success', 'message' => 'Health record updated.'],
    'qr_regenerated'    => ['type' => 'success', 'message' => 'QR code regenerated successfully!'],
    'error'             => ['type' => 'error', 'message' => 'An error occurred. Please try again.'],
];
$current_alert = $alert_messages[$alert_type] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta name="description" content="PUPBC Carelink Student Dashboard - Manage your health records, appointments, and QR code">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard | PUPBC Carelink</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'] },
                    colors: {
                        primary: { DEFAULT: '#800020', light: '#a0002a', dark: '#5c0017', foreground: '#ffffff' },
                        accent: { DEFAULT: '#c9a84c', light: '#d4b96a', dark: '#b89945' },
                    },
                    boxShadow: {
                        'card': '0 1px 3px 0 rgba(0,0,0,0.05), 0 1px 2px -1px rgba(0,0,0,0.05)',
                    },
                },
            },
        }
    </script>
    
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; -webkit-font-smoothing: antialiased; }
        .stat-card { transition: all 0.2s ease; cursor: default; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        @keyframes pulse-badge { 0%,100%{transform:scale(1)} 50%{transform:scale(1.2)} }
        .pulse-badge { animation: pulse-badge 0.5s ease-in-out; }
        @keyframes fadeInUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
        .animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
        .safe-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
        a:focus-visible, button:focus-visible { outline: 2px solid #800020; outline-offset: 2px; border-radius: 4px; }
        .nav-active { background-color: rgba(201,168,76,0.15); color: #c9a84c; font-weight: 600; }
        .nav-active-mobile { color: #800020; border-top: 2px solid #800020; }
    </style>
</head>
<body class="min-h-screen bg-gray-50">

<!-- DESKTOP SIDEBAR -->
<aside class="hidden lg:flex lg:flex-col fixed top-0 left-0 h-full w-64 bg-primary shadow-xl z-40 overflow-y-auto" role="navigation" aria-label="Main navigation">
    
    <nav class="flex-1 py-4 space-y-1">
        <div class="px-5 mb-2"><p class="text-[10px] font-semibold uppercase tracking-wider text-accent/80">Main Menu</p></div>
        
        <a href="student_dashboard.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white nav-active" aria-current="page">
            <i class="fas fa-th-large w-5 text-center"></i><span class="text-sm font-medium">Dashboard</span>
        </a>
        <a href="student_profile.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all">
            <i class="fas fa-user w-5 text-center"></i><span class="text-sm font-medium">My Profile</span>
        </a>
        <a href="student_qr.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all">
            <i class="fas fa-qrcode w-5 text-center"></i><span class="text-sm font-medium">My QR Code</span>
        </a>
        <a href="student_record.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all">
            <i class="fas fa-notes-medical w-5 text-center"></i><span class="text-sm font-medium">Health Records</span>
        </a>
        <a href="student_appointments.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all">
            <i class="fas fa-calendar-alt w-5 text-center"></i><span class="text-sm font-medium">Appointments</span>
        </a>
        <a href="student_announcement.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all">
            <i class="fas fa-bullhorn w-5 text-center"></i><span class="text-sm font-medium">Announcements</span>
        </a>
        <a href="student_notifications.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all">
            <i class="fas fa-bell w-5 text-center"></i><span class="text-sm font-medium">Notifications</span>
            <?php if ($unread_count > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="student_settings.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all">
            <i class="fas fa-cog w-5 text-center"></i><span class="text-sm font-medium">Settings</span>
        </a>
        
        <div class="border-t border-primary-dark my-4 mx-5"></div>
        
        <a href="logout.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/60 hover:text-white hover:bg-red-600/20 transition-all">
            <i class="fas fa-sign-out-alt w-5 text-center"></i><span class="text-sm font-medium">Sign Out</span>
        </a>
    </nav>
    
    <div class="p-4 border-t border-primary-dark mt-auto">
        <p class="text-[10px] text-white/30 text-center">&copy; <?php echo date('Y'); ?> PUPBC Carelink</p>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="lg:ml-64 min-h-screen pb-24 lg:pb-8">
    
    <!-- Top Bar -->
    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-sm border-b border-gray-200 shadow-sm">
        <div class="px-4 lg:px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="text-sm text-gray-500 hidden sm:block">
                    Welcome back, <?php echo $first_name; ?>!
                    <?php if ($last_login): ?>
                        <span class="text-xs text-gray-400">• Last login: <?php echo date('M d, Y h:i A', strtotime($last_login)); ?></span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                <?php if ($account_status !== 'active' && $_SESSION['db_has_account_status']): ?>
                    <span class="hidden sm:inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 border border-yellow-200">
                        <i class="fas fa-exclamation-circle"></i> <?php echo ucfirst($account_status); ?>
                    </span>
                <?php endif; ?>
                
                <div class="relative">
                    <button id="notificationBtn" class="relative p-2 hover:bg-gray-100 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bell text-gray-600 text-xl"></i>
                        <?php if ($unread_count > 0): ?>
                            <span id="notificationBadge" class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 lg:w-96 bg-white rounded-xl shadow-xl border border-gray-200 z-50 overflow-hidden" role="menu">
                        <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                            <h4 class="font-semibold text-gray-900">Notifications</h4>
                            <div class="flex gap-3">
                                <?php if ($unread_count > 0): ?>
                                    <button onclick="markAllAsRead()" class="text-xs text-primary hover:underline font-medium">Mark all read</button>
                                <?php endif; ?>
                                <a href="student_notifications.php" class="text-xs text-primary hover:underline font-medium">View all</a>
                            </div>
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                            <?php if (empty($notifications)): ?>
                                <div class="p-6 text-center">
                                    <i class="fas fa-bell-slash text-3xl text-gray-300 mb-3 block"></i>
                                    <p class="text-sm text-gray-500">No new notifications</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <a href="<?php echo htmlspecialchars($notif['link'] ?? 'student_announcement.php', ENT_QUOTES, 'UTF-8'); ?>?notif_id=<?php echo (int)($notif['id'] ?? 0); ?>" class="block p-4 border-b border-gray-100 hover:bg-gray-50 transition-colors" role="menuitem">
                                        <div class="flex items-start gap-3">
                                            <div class="mt-0.5 flex-shrink-0">
                                                <?php if (($notif['type'] ?? '') === 'announcement'): ?>
                                                    <i class="fas fa-bullhorn text-primary"></i>
                                                <?php elseif (($notif['type'] ?? '') === 'appointment'): ?>
                                                    <i class="fas fa-calendar-alt text-blue-500"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-info-circle text-gray-400"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($notif['title'] ?? 'Notification', ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p class="text-xs text-gray-500 mt-0.5 line-clamp-2"><?php echo htmlspecialchars(substr($notif['message'] ?? '', 0, 100), ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p class="text-[10px] text-gray-400 mt-1"><?php echo timeAgo($notif['created_at'] ?? ''); ?></p>
                                            </div>
                                            <?php if (empty($notif['is_read'])): ?>
                                                <div class="w-2 h-2 bg-primary rounded-full flex-shrink-0 mt-1.5" aria-label="Unread"></div>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- DASHBOARD CONTENT -->
    <div class="p-4 lg:p-6 space-y-6">
        
        <?php if ($current_alert): ?>
            <div class="animate-fade-in-up flex items-start gap-3 p-4 rounded-xl border <?php echo $current_alert['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>" role="alert">
                <i class="fas <?php echo $current_alert['type'] === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'; ?> mt-0.5"></i>
                <p class="font-medium text-sm"><?php echo htmlspecialchars($current_alert['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                <button onclick="this.parentElement.remove()" class="ml-auto p-1 hover:bg-black/5 rounded" aria-label="Dismiss"><i class="fas fa-times text-sm"></i></button>
            </div>
        <?php endif; ?>
        
        <!-- STATS CARDS -->
        <section aria-label="Dashboard Statistics">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="stat-card bg-white rounded-xl p-4 lg:p-5 shadow-card border border-gray-100">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs lg:text-sm text-gray-500 font-medium">Total Visits</p>
                        <div class="w-9 h-9 lg:w-10 lg:h-10 rounded-full bg-red-50 flex items-center justify-center"><i class="fas fa-stethoscope text-red-500"></i></div>
                    </div>
                    <p class="text-2xl lg:text-3xl font-bold text-gray-900"><?php echo $total_visits; ?></p>
                    <p class="text-xs text-gray-400 mt-1">Lifetime clinic visits</p>
                </div>
                <div class="stat-card bg-white rounded-xl p-4 lg:p-5 shadow-card border border-gray-100">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs lg:text-sm text-gray-500 font-medium">Consultations</p>
                        <div class="w-9 h-9 lg:w-10 lg:h-10 rounded-full bg-green-50 flex items-center justify-center"><i class="fas fa-check-circle text-green-500"></i></div>
                    </div>
                    <p class="text-2xl lg:text-3xl font-bold text-gray-900"><?php echo $completed_visits; ?></p>
                    <p class="text-xs text-gray-400 mt-1">Completed consultations</p>
                </div>
                <div class="stat-card bg-white rounded-xl p-4 lg:p-5 shadow-card border border-gray-100">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs lg:text-sm text-gray-500 font-medium">Appointments</p>
                        <div class="w-9 h-9 lg:w-10 lg:h-10 rounded-full bg-blue-50 flex items-center justify-center"><i class="fas fa-calendar-alt text-blue-500"></i></div>
                    </div>
                    <p class="text-2xl lg:text-3xl font-bold text-gray-900"><?php echo $pending_appointments; ?></p>
                    <p class="text-xs text-gray-400 mt-1">Upcoming appointments</p>
                </div>
                <div class="stat-card bg-white rounded-xl p-4 lg:p-5 shadow-card border border-gray-100">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs lg:text-sm text-gray-500 font-medium">Health Score</p>
                        <div class="w-9 h-9 lg:w-10 lg:h-10 rounded-full bg-yellow-50 flex items-center justify-center"><i class="fas fa-heartbeat text-yellow-500"></i></div>
                    </div>
                    <p class="text-2xl lg:text-3xl font-bold text-gray-900"><?php echo $health_score; ?>%</p>
                    <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                        <div class="h-1.5 rounded-full <?php echo $health_score >= 80 ? 'bg-green-500' : ($health_score >= 60 ? 'bg-yellow-500' : 'bg-red-500'); ?>" style="width: <?php echo $health_score; ?>%" role="progressbar" aria-valuenow="<?php echo $health_score; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- TWO COLUMN LAYOUT -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- LEFT -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Appointments -->
                <section class="bg-white rounded-xl shadow-card border border-gray-100 overflow-hidden" aria-labelledby="appt-heading">
                    <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h3 id="appt-heading" class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-calendar-week text-primary"></i> Upcoming Appointments</h3>
                        <a href="student_appointments.php" class="text-xs text-primary hover:underline font-medium">View all →</a>
                    </div>
                    <div class="p-5">
                        <?php if (empty($upcoming_appointments)): ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4"><i class="fas fa-calendar-times text-2xl text-gray-400"></i></div>
                                <p class="text-sm text-gray-500 font-medium">No upcoming appointments</p>
                                <a href="student_appointments.php?action=book" class="inline-block mt-3 text-sm text-primary hover:underline font-medium"><i class="fas fa-plus-circle mr-1"></i> Book Appointment</a>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($upcoming_appointments as $appt): ?>
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 lg:w-12 lg:h-12 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0"><i class="fas fa-calendar-day text-primary"></i></div>
                                            <div>
                                                <p class="font-semibold text-gray-900"><?php echo date('F d, Y', strtotime($appt['appointment_date'])); ?></p>
                                                <p class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($appt['appointment_time'])); ?> • <?php echo htmlspecialchars($appt['purpose'] ?? 'General Checkup', ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                        </div>
                                        <span class="self-start sm:self-center px-3 py-1 rounded-full text-xs font-medium <?php echo ($appt['status'] ?? '') === 'confirmed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>"><?php echo ucfirst($appt['status'] ?? 'Pending'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                
                <!-- Records -->
                <section class="bg-white rounded-xl shadow-card border border-gray-100 overflow-hidden" aria-labelledby="rec-heading">
                    <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h3 id="rec-heading" class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-notes-medical text-primary"></i> Recent Consultations</h3>
                        <a href="student_record.php" class="text-xs text-primary hover:underline font-medium">View all →</a>
                    </div>
                    <div class="overflow-x-auto">
                        <?php if (empty($recent_records)): ?>
                            <div class="text-center py-8 px-5">
                                <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4"><i class="fas fa-file-medical text-2xl text-gray-400"></i></div>
                                <p class="text-sm text-gray-500 font-medium">No consultation records yet</p>
                            </div>
                        <?php else: ?>
                            <table class="w-full text-sm">
                                <thead><tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider"><th class="px-5 py-3 text-left font-semibold">Date</th><th class="px-5 py-3 text-left font-semibold">Diagnosis</th><th class="px-5 py-3 text-left font-semibold hidden sm:table-cell">Nurse</th></tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($recent_records as $record): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-5 py-3.5 text-gray-900 font-medium whitespace-nowrap"><?php echo date('M d, Y', strtotime($record['visit_date'])); ?></td>
                                            <td class="px-5 py-3.5 text-gray-600 max-w-[200px] truncate"><?php echo htmlspecialchars($record['diagnosis'] ?: 'Under evaluation', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-5 py-3.5 text-gray-500 hidden sm:table-cell whitespace-nowrap"><?php echo $record['nurse_first'] ? 'Nurse ' . htmlspecialchars($record['nurse_first'] . ' ' . ($record['nurse_last'] ?? ''), ENT_QUOTES, 'UTF-8') : '<span class="text-gray-400">—</span>'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            
            <!-- RIGHT -->
            <div class="space-y-6">
                
                <!-- Profile -->
                <section class="bg-white rounded-xl shadow-card border border-gray-100 p-5" aria-labelledby="prof-heading">
                    <h3 id="prof-heading" class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fas fa-id-card text-primary"></i> Health Profile</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between py-1.5 border-b border-gray-50"><span class="text-gray-500">Student Number</span><span class="font-medium text-gray-900"><?php echo $student_number; ?></span></div>
                        <div class="flex justify-between py-1.5 border-b border-gray-50"><span class="text-gray-500">Course</span><span class="font-medium text-gray-900"><?php echo $course; ?></span></div>
                        <div class="flex justify-between py-1.5 border-b border-gray-50"><span class="text-gray-500">Year Level</span><span class="font-medium text-gray-900"><?php echo $year_level; ?></span></div>
                        <div class="flex justify-between py-1.5 border-b border-gray-50"><span class="text-gray-500">Blood Type</span><span class="font-medium text-gray-900"><?php echo $blood_type; ?></span></div>
                        <div class="flex justify-between py-1.5 border-b border-gray-50"><span class="text-gray-500">Last Visit</span><span class="font-medium text-gray-900"><?php echo $last_visit_date; ?></span></div>
                        <div class="flex justify-between py-1.5"><span class="text-gray-500">Health Records</span><span class="font-medium text-gray-900"><?php echo $health_records_count; ?> files</span></div>
                    </div>
                    
                    <?php if ($allergies !== 'None' && !empty($allergies)): ?>
                        <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2">
                            <i class="fas fa-exclamation-triangle text-red-500 mt-0.5 flex-shrink-0"></i>
                            <div><p class="text-xs font-semibold text-red-700">Allergies</p><p class="text-xs text-red-600"><?php echo $allergies; ?></p></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($medical_conditions !== 'None' && !empty($medical_conditions)): ?>
                        <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg flex items-start gap-2">
                            <i class="fas fa-notes-medical text-yellow-600 mt-0.5 flex-shrink-0"></i>
                            <div><p class="text-xs font-semibold text-yellow-700">Medical Conditions</p><p class="text-xs text-yellow-600"><?php echo $medical_conditions; ?></p></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($emergency_contact !== 'Not set' && !empty($emergency_contact)): ?>
                        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs font-semibold text-gray-700 mb-1">Emergency Contact</p>
                            <p class="text-sm text-gray-900"><?php echo $emergency_contact; ?><?php echo !empty($emergency_relation) ? ' (' . $emergency_relation . ')' : ''; ?></p>
                            <?php if ($emergency_phone !== 'Not set'): ?>
                                <p class="text-xs text-gray-500 mt-0.5"><i class="fas fa-phone-alt mr-1"></i><?php echo $emergency_phone; ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <a href="student_profile.php" class="mt-4 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                </section>
                
                <!-- Announcements -->
                <section class="bg-white rounded-xl shadow-card border border-gray-100 p-5" aria-labelledby="ann-heading">
                    <h3 id="ann-heading" class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fas fa-bullhorn text-primary"></i> Latest Announcements</h3>
                    <?php if (empty($announcements)): ?>
                        <p class="text-sm text-gray-500 text-center py-4">No announcements at this time.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($announcements as $ann): ?>
                                <div class="pb-3 border-b border-gray-50 last:border-0 last:pb-0">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($ann['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?php echo htmlspecialchars(substr($ann['content'], 0, 120), ENT_QUOTES, 'UTF-8'); ?>...</p>
                                    <p class="text-[10px] text-gray-400 mt-1"><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <a href="student_announcement.php" class="mt-4 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">View All Announcements</a>
                </section>
                
                <!-- Health Tip -->
                <div class="bg-gradient-to-br from-primary to-primary-dark rounded-xl shadow-lg p-5 text-white">
                    <div class="flex items-center gap-2 mb-3"><i class="fas fa-lightbulb text-accent text-xl"></i><h3 class="font-semibold">Health Tip</h3></div>
                    <p class="text-sm text-white/90 leading-relaxed">Regular hand washing with soap and water for at least 20 seconds helps prevent the spread of infections.</p>
                    <p class="text-xs text-white/50 mt-3 italic">— PUPBC Clinic Advisory</p>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- MOBILE BOTTOM NAV -->
<nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 lg:hidden z-40 safe-bottom shadow-[0_-4px_12px_rgba(0,0,0,0.05)]" aria-label="Mobile navigation">
    <div class="flex justify-around items-center py-1.5 px-2 max-w-lg mx-auto">
        <a href="student_dashboard.php" class="flex flex-col items-center py-1 px-3 rounded-lg nav-active-mobile" aria-current="page"><i class="fas fa-th-large text-lg"></i><span class="text-[10px] mt-0.5 font-medium">Home</span></a>
        <a href="student_qr.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-qrcode text-lg"></i><span class="text-[10px] mt-0.5 font-medium">QR Code</span></a>
        <a href="student_record.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-notes-medical text-lg"></i><span class="text-[10px] mt-0.5 font-medium">Records</span></a>
        <a href="student_appointments.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-calendar-alt text-lg"></i><span class="text-[10px] mt-0.5 font-medium">Appts</span></a>
        <a href="student_notifications.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-gray-400 hover:text-gray-600 transition-colors relative">
            <i class="fas fa-bell text-lg"></i>
            <?php if ($unread_count > 0): ?>
                <span class="absolute -top-0.5 right-0 bg-red-500 text-white text-[9px] font-bold rounded-full min-w-[16px] h-[16px] flex items-center justify-center px-0.5"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
            <?php endif; ?>
            <span class="text-[10px] mt-0.5 font-medium">Alerts</span>
        </a>
        <a href="student_profile.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-gray-400 hover:text-gray-600 transition-colors"><i class="fas fa-user-circle text-lg"></i><span class="text-[10px] mt-0.5 font-medium">Profile</span></a>
    </div>
</nav>

<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        if (notificationBtn && notificationDropdown) {
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = !notificationDropdown.classList.contains('hidden');
                notificationDropdown.classList.toggle('hidden');
                notificationBtn.setAttribute('aria-expanded', !isOpen);
            });
            
            document.addEventListener('click', function(e) {
                if (!notificationDropdown.classList.contains('hidden') && !notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.add('hidden');
                    notificationBtn.setAttribute('aria-expanded', 'false');
                }
            });
            
            notificationDropdown.addEventListener('click', function(e) { e.stopPropagation(); });
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !notificationDropdown.classList.contains('hidden')) {
                    notificationDropdown.classList.add('hidden');
                    notificationBtn.setAttribute('aria-expanded', 'false');
                    notificationBtn.focus();
                }
            });
        }
        
        window.markAllAsRead = function() {
            const btn = event.target;
            const orig = btn.textContent;
            btn.textContent = 'Marking...';
            btn.disabled = true;
            $.ajax({
                url: '../../ajax/mark_all_read.php',
                type: 'POST',
                dataType: 'json',
                success: function(r) {
                    if (r && r.success) { btn.textContent = '✓ All read'; btn.classList.add('text-green-600'); setTimeout(() => location.reload(), 800); }
                    else { btn.textContent = orig; btn.disabled = false; }
                },
                error: function() { btn.textContent = orig; btn.disabled = false; }
            });
        };
        
        setInterval(function() {
            $.ajax({
                url: '../../ajax/get_notification_count.php',
                type: 'GET',
                dataType: 'json',
                success: function(d) {
                    if (d && typeof d.unread_count !== 'undefined') {
                        const badge = document.getElementById('notificationBadge');
                        if (badge) {
                            if (d.unread_count > 0) { badge.textContent = d.unread_count > 99 ? '99+' : d.unread_count; badge.classList.remove('hidden'); badge.classList.add('pulse-badge'); setTimeout(() => badge.classList.remove('pulse-badge'), 500); }
                            else { badge.classList.add('hidden'); }
                        }
                    }
                }
            });
        }, 30000);
        
        const alertBox = document.querySelector('[role="alert"]');
        if (alertBox) { setTimeout(() => { alertBox.style.transition = 'opacity 0.5s ease'; alertBox.style.opacity = '0'; setTimeout(() => alertBox.remove(), 500); }, 5000); }
        
        function adjustPadding() {
            const nav = document.querySelector('nav[aria-label="Mobile navigation"]');
            document.body.style.paddingBottom = (nav && window.innerWidth < 1024) ? nav.offsetHeight + 'px' : '0px';
        }
        window.addEventListener('resize', adjustPadding);
        adjustPadding();
    });
</script>

</body>
</html>