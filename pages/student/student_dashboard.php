<?php
// capstonemain/pages/student/student_dashboard.php
// FIXED VERSION - With proper connection handling and error checking

session_start();

// Check if student is logged in
if (!isset($_SESSION['student_id']) || empty($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../../config/db_connect.php';

// Verify connection exists
if (!isset($conn) || !$conn) {
    error_log("Database connection failed in student_dashboard.php");
    die("System error: Unable to connect to database. Please contact clinic administrator.");
}

require_once __DIR__ . '/../../includes/notification_helper.php';

$student_id = (int)$_SESSION['student_id'];

// Initialize student data with defaults to prevent undefined variable errors
$student = [
    'first_name' => 'Student',
    'last_name' => '',
    'email' => '',
    'student_number' => 'N/A',
    'course' => 'N/A',
    'year_level' => 'N/A',
    'blood_type' => 'Not specified',
    'allergies' => 'None'
];

// Get student data with error checking
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, u.email, s.* 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.id = ?");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $fetched_student = mysqli_fetch_assoc($result);
        if ($fetched_student) {
            $student = array_merge($student, $fetched_student);
        }
    }
    mysqli_stmt_close($stmt);
}

// Extract student data with proper defaults
$first_name = $student['first_name'] ?? 'Student';
$last_name = $student['last_name'] ?? '';
$full_name = trim($first_name . ' ' . $last_name);
$student_number = $student['student_number'] ?? 'N/A';
$course = $student['course'] ?? 'N/A';
$year_level = $student['year_level'] ?? 'N/A';
$blood_type = $student['blood_type'] ?? 'Not specified';
$allergies = $student['allergies'] ?? 'None';
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get unread notifications count
$unread_count = 0;
$notifications = [];

if (function_exists('getUnreadCount')) {
    $unread_count = getUnreadCount($student_id);
    $notifications = getUnreadNotifications($student_id, 5);
}

// Stats with error handling
$total_visits = 0;
$completed_visits = 0;
$pending_appointments = 0;
$last_visit_date = 'No visits yet';
$health_score = 85;

// Total visits
$total_visits_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM visits WHERE student_id = ?");
if ($total_visits_stmt) {
    mysqli_stmt_bind_param($total_visits_stmt, "i", $student_id);
    if (mysqli_stmt_execute($total_visits_stmt)) {
        $total_visits_result = mysqli_stmt_get_result($total_visits_stmt);
        $total_visits = mysqli_fetch_assoc($total_visits_result)['c'] ?? 0;
    }
    mysqli_stmt_close($total_visits_stmt);
}

// Completed visits
$completed_visits_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM visits WHERE student_id = ? AND status = 'completed'");
if ($completed_visits_stmt) {
    mysqli_stmt_bind_param($completed_visits_stmt, "i", $student_id);
    if (mysqli_stmt_execute($completed_visits_stmt)) {
        $completed_visits_result = mysqli_stmt_get_result($completed_visits_stmt);
        $completed_visits = mysqli_fetch_assoc($completed_visits_result)['c'] ?? 0;
    }
    mysqli_stmt_close($completed_visits_stmt);
}

// Pending appointments
$pending_appointments_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM appointments WHERE student_id = ? AND status IN ('pending','confirmed') AND appointment_date >= CURDATE()");
if ($pending_appointments_stmt) {
    mysqli_stmt_bind_param($pending_appointments_stmt, "i", $student_id);
    if (mysqli_stmt_execute($pending_appointments_stmt)) {
        $pending_appointments_result = mysqli_stmt_get_result($pending_appointments_stmt);
        $pending_appointments = mysqli_fetch_assoc($pending_appointments_result)['c'] ?? 0;
    }
    mysqli_stmt_close($pending_appointments_stmt);
}

// Last visit
$last_visit_stmt = mysqli_prepare($conn, "SELECT visit_date FROM visits WHERE student_id = ? AND status = 'completed' ORDER BY visit_date DESC LIMIT 1");
if ($last_visit_stmt) {
    mysqli_stmt_bind_param($last_visit_stmt, "i", $student_id);
    if (mysqli_stmt_execute($last_visit_stmt)) {
        $last_visit_result = mysqli_stmt_get_result($last_visit_stmt);
        $last_visit = mysqli_fetch_assoc($last_visit_result);
        $last_visit_date = $last_visit ? date('M d, Y', strtotime($last_visit['visit_date'])) : 'No visits yet';
    }
    mysqli_stmt_close($last_visit_stmt);
}

// Health score calculation
$health_score = $total_visits > 0 ? min(100, max(60, 100 - ($total_visits * 2))) : 85;

// Upcoming appointments
$upcoming_appointments = [];
$ua_stmt = mysqli_prepare($conn, "SELECT * FROM appointments 
    WHERE student_id = ? AND status IN ('pending','confirmed') 
    AND appointment_date >= CURDATE() ORDER BY appointment_date ASC, appointment_time ASC LIMIT 3");
if ($ua_stmt) {
    mysqli_stmt_bind_param($ua_stmt, "i", $student_id);
    if (mysqli_stmt_execute($ua_stmt)) {
        $ua_result = mysqli_stmt_get_result($ua_stmt);
        while ($row = mysqli_fetch_assoc($ua_result)) {
            $upcoming_appointments[] = $row;
        }
    }
    mysqli_stmt_close($ua_stmt);
}

// Recent health records
$recent_records = [];
$rr_stmt = mysqli_prepare($conn, "SELECT v.*, u.first_name as nurse_first 
    FROM visits v 
    LEFT JOIN nurses n ON v.nurse_id = n.id 
    LEFT JOIN users u ON n.user_id = u.id 
    WHERE v.student_id = ? AND v.status = 'completed' 
    ORDER BY v.visit_date DESC LIMIT 3");
if ($rr_stmt) {
    mysqli_stmt_bind_param($rr_stmt, "i", $student_id);
    if (mysqli_stmt_execute($rr_stmt)) {
        $rr_result = mysqli_stmt_get_result($rr_stmt);
        while ($row = mysqli_fetch_assoc($rr_result)) {
            $recent_records[] = $row;
        }
    }
    mysqli_stmt_close($rr_stmt);
}

// Announcements (safe query - no user input)
$announcements = [];
$ann_query = mysqli_query($conn, "SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 2");
if ($ann_query) {
    while ($row = mysqli_fetch_assoc($ann_query)) {
        $announcements[] = $row;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);

function timeAgo($datetime) {
    if (empty($datetime)) return 'Just now';
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M d', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Dashboard - PUPBC Carelink</title>
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
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .notification-dropdown { transition: all 0.2s ease; }
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
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#c9a84c] text-[#800020] font-bold text-sm"><?php echo htmlspecialchars($initials); ?></div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($full_name); ?></div>
            <div class="text-xs text-[#c9a84c] truncate"><?php echo htmlspecialchars($student_number); ?></div>
        </div>
    </div>
    
    <nav class="py-4">
        <div class="px-3 mb-2"><p class="text-[10px] font-semibold uppercase tracking-wider text-[#c9a84c] px-3">Main Menu</p></div>
        
        <a href="student_dashboard.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'student_dashboard.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
            <i class="fas fa-home w-5"></i><span class="text-sm font-medium">Dashboard</span>
        </a>
        <a href="student_qr.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-qrcode w-5"></i><span class="text-sm font-medium">QR Code</span>
        </a>
        <a href="student_record.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-notes-medical w-5"></i><span class="text-sm font-medium">Health Records</span>
        </a>
        <a href="student_appointments.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
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
    
    <!-- Header with Notification Bell -->
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200">
        <div class="px-4 md:px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="text-sm text-gray-500">Welcome back, <?php echo htmlspecialchars($first_name); ?>!</p>
            </div>
            
            <!-- Notification Bell -->
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
                            <a href="<?php echo htmlspecialchars($notif['link'] ?? 'student_announcement.php'); ?>?notif_id=<?php echo $notif['id']; ?>" class="notification-item block p-3 border-b hover:bg-gray-50 transition">
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
                                        <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars(substr($notif['message'], 0, 80)); ?></p>
                                        <p class="text-xs text-gray-400 mt-1"><?php echo timeAgo($notif['created_at']); ?></p>
                                    </div>
                                    <?php if (!$notif['is_read']): ?>
                                    <div class="w-2 h-2 bg-[#800020] rounded-full"></div>
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
    
    <!-- Dashboard Content -->
    <div class="p-4 md:p-6">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm border stat-card transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Total Visits</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $total_visits; ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center"><i class="fas fa-stethoscope text-red-500 text-lg"></i></div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl p-4 shadow-sm border stat-card transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Consultations</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $completed_visits; ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center"><i class="fas fa-check-circle text-green-500 text-lg"></i></div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl p-4 shadow-sm border stat-card transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Appointments</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $pending_appointments; ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center"><i class="fas fa-calendar-alt text-blue-500 text-lg"></i></div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl p-4 shadow-sm border stat-card transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Health Score</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $health_score; ?>%</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-yellow-50 flex items-center justify-center"><i class="fas fa-heartbeat text-yellow-500 text-lg"></i></div>
                </div>
            </div>
        </div>
        
        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Upcoming Appointments -->
                <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                    <div class="px-5 py-4 border-b flex justify-between items-center">
                        <h3 class="font-semibold text-gray-900"><i class="fas fa-calendar-week text-[#800020] mr-2"></i>Upcoming Appointments</h3>
                        <a href="student_appointments.php" class="text-xs text-[#800020] hover:underline">View all →</a>
                    </div>
                    <div class="p-5">
                        <?php if (empty($upcoming_appointments)): ?>
                            <div class="text-center text-gray-400 py-6">
                                <i class="fas fa-calendar-times text-3xl mb-2 block"></i>
                                <p class="text-sm">No upcoming appointments</p>
                                <a href="student_appointments.php" class="text-xs text-[#800020] mt-2 inline-block">Book an appointment →</a>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($upcoming_appointments as $appt): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-[#800020]/10 flex items-center justify-center">
                                            <i class="fas fa-calendar-day text-[#800020]"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900"><?php echo date('F d, Y', strtotime($appt['appointment_date'])); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($appt['appointment_time'])); ?> • <?php echo htmlspecialchars($appt['purpose'] ?? 'General Checkup'); ?></p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 rounded-full text-xs <?php echo $appt['status'] == 'confirmed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                        <?php echo ucfirst($appt['status']); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Health Records -->
                <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                    <div class="px-5 py-4 border-b flex justify-between items-center">
                        <h3 class="font-semibold text-gray-900"><i class="fas fa-notes-medical text-[#800020] mr-2"></i>Recent Consultations</h3>
                        <a href="student_record.php" class="text-xs text-[#800020] hover:underline">View all →</a>
                    </div>
                    <div class="overflow-x-auto">
                        <?php if (empty($recent_records)): ?>
                            <div class="p-8 text-center text-gray-400">
                                <i class="fas fa-file-alt text-3xl mb-2 block"></i>
                                <p class="text-sm">No health records yet</p>
                                <a href="student_qr.php" class="text-xs text-[#800020] mt-2 inline-block">Check in at the kiosk →</a>
                            </div>
                        <?php else: ?>
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-500 text-xs">
                                    <tr><th class="px-5 py-3 text-left">Date</th><th class="px-5 py-3 text-left">Diagnosis</th><th class="px-5 py-3 text-left">Nurse</th></tr>
                                </thead>
                                <tbody class="divide-y">
                                    <?php foreach ($recent_records as $record): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-3"><?php echo date('M d, Y', strtotime($record['visit_date'])); ?></td>
                                        <td class="px-5 py-3"><?php echo htmlspecialchars($record['diagnosis'] ?: 'Under evaluation'); ?></td>
                                        <td class="px-5 py-3">Nurse <?php echo htmlspecialchars($record['nurse_first'] ?? 'Staff'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="space-y-6">
                
                <!-- Health Profile Summary -->
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-id-card text-[#800020] mr-2"></i>Your Profile</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Student ID:</span><span class="font-medium"><?php echo htmlspecialchars($student_number); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Course:</span><span class="font-medium"><?php echo htmlspecialchars($course); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Year Level:</span><span class="font-medium"><?php echo htmlspecialchars($year_level); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Blood Type:</span><span class="font-medium"><?php echo htmlspecialchars($blood_type); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Last Visit:</span><span class="font-medium"><?php echo $last_visit_date; ?></span></div>
                    </div>
                    <?php if ($allergies != 'None' && !empty($allergies) && $allergies !== ''): ?>
                        <div class="mt-3 p-2 bg-red-50 rounded-lg">
                            <p class="text-xs text-red-600"><i class="fas fa-exclamation-triangle mr-1"></i> Allergies: <?php echo htmlspecialchars($allergies); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Announcements -->
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-bullhorn text-[#800020] mr-2"></i>Latest Announcements</h3>
                    <div class="space-y-3">
                        <?php foreach ($announcements as $ann): ?>
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($ann['title']); ?></p>
                            <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars(substr($ann['content'], 0, 80)); ?>...</p>
                            <p class="text-xs text-gray-400 mt-1"><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></p>
                        </div>
                        <?php endforeach; ?>
                        <a href="student_announcement.php" class="text-xs text-[#800020] block text-center mt-2">View all →</a>
                    </div>
                </div>
                
                <!-- Health Tip -->
                <div class="bg-gradient-primary rounded-xl shadow-sm p-5 text-white">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-lightbulb text-yellow-300 text-xl"></i>
                        <h3 class="font-semibold">Health Tip</h3>
                    </div>
                    <p class="text-sm text-white/90">Stay hydrated! Drink at least 8 glasses of water daily to maintain good health.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Bottom Navigation Bar (Mobile only) -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden z-40 shadow-lg safe-bottom">
    <div class="flex justify-around py-2">
        <a href="student_dashboard.php" class="flex flex-col items-center py-1 px-2 text-[#800020]">
            <i class="fas fa-home text-xl"></i><span class="text-[10px] mt-1">Home</span>
        </a>
        <a href="student_qr.php" class="flex flex-col items-center py-1 px-2 text-gray-400">
            <i class="fas fa-qrcode text-xl"></i><span class="text-[10px] mt-1">QR</span>
        </a>
        <a href="student_record.php" class="flex flex-col items-center py-1 px-2 text-gray-400">
            <i class="fas fa-notes-medical text-xl"></i><span class="text-[10px] mt-1">Records</span>
        </a>
        <a href="student_appointments.php" class="flex flex-col items-center py-1 px-2 text-gray-400">
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
            },
            error: function() {
                console.log('Failed to mark notifications as read');
            }
        });
    }
    
    // Auto-refresh notifications every 30 seconds
    setInterval(function() {
        $.ajax({
            url: '../../ajax/get_notifications.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data && data.unread_count > 0) {
                    const badge = document.querySelector('#notificationBtn span');
                    if (badge) {
                        badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                        badge.classList.add('pulse-animation');
                        setTimeout(() => badge.classList.remove('pulse-animation'), 500);
                    }
                }
            },
            error: function() {
                // Silent fail - don't show error to user
            }
        });
    }, 30000);
</script>

</body>
</html>