<?php
// capstonemain/pages/student/student_record.php
session_start();
if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit();
}
require_once '../../config/db_connect.php';
require_once '../../includes/notification_helper.php';

$student_id = $_SESSION['student_id'];

// Get student data
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, s.student_number, s.course, s.year_level 
                      FROM students s JOIN users u ON s.user_id = u.id 
                      WHERE s.id = ?");
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

$first_name = $student['first_name'] ?? 'Student';
$last_name = $student['last_name'] ?? '';
$full_name = trim($first_name . ' ' . $last_name);
$student_number = $student['student_number'] ?? 'N/A';
$course = $student['course'] ?? 'N/A';
$year_level = $student['year_level'] ?? 'N/A';
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get unread notifications count
$unread_count = getUnreadCount($student_id);
$notifications = getUnreadNotifications($student_id, 5);

// Get visits
$visits = [];
$stmt = mysqli_prepare($conn, "SELECT v.*, u.first_name as nurse_first, u.last_name as nurse_last 
                      FROM visits v 
                      LEFT JOIN nurses n ON v.nurse_id = n.id 
                      LEFT JOIN users u ON n.user_id = u.id 
                      WHERE v.student_id = ? 
                      ORDER BY v.visit_date DESC");
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) $visits[] = $row;

// Get prescriptions (optional: only if tables exist)
$prescriptions = [];
$hasPrescriptionsResult = mysqli_query($conn, "SHOW TABLES LIKE 'prescriptions'");
$hasPrescriptionsTable = $hasPrescriptionsResult && mysqli_num_rows($hasPrescriptionsResult) > 0;
$hasMedicinesResult = mysqli_query($conn, "SHOW TABLES LIKE 'medicines'");
$hasMedicinesTable = $hasMedicinesResult && mysqli_num_rows($hasMedicinesResult) > 0;

if ($hasPrescriptionsTable && $hasMedicinesTable) {
    $pr = mysqli_query($conn, "SELECT p.*, m.name as medicine_name, v.visit_date 
                              FROM prescriptions p 
                              JOIN medicines m ON p.medicine_id = m.id 
                              JOIN visits v ON p.visit_id = v.id 
                              WHERE v.student_id = $student_id 
                              ORDER BY p.created_at DESC");
    if ($pr) while ($row = mysqli_fetch_assoc($pr)) $prescriptions[] = $row;
}


$current_page = basename($_SERVER['PHP_SELF']);
$tab = $_GET['tab'] ?? 'visits';

// Function for time ago
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
    <title>Health Records - PUPBC Carelink</title>
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
        .safe-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
        .nav-active { background-color: rgba(201,168,76,0.15); color: #c9a84c; font-weight: 600; }
        .nav-active-mobile { color: #800020; border-top: 2px solid #800020; }
    </style>
</head>
<body>

<!-- Desktop Sidebar (hidden on mobile) -->
<aside class="hidden md:flex md:flex-col fixed top-0 left-0 h-full w-64 bg-[#800020] shadow-xl overflow-y-auto z-30">
    
    <nav class="flex-1 py-4 space-y-1">
        <div class="px-5 mb-2"><p class="text-[10px] font-semibold uppercase tracking-wider text-[#c9a84c]/80">Main Menu</p></div>
        
        <a href="student_dashboard.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'student_dashboard.php' ? 'text-white nav-active' : 'text-white/80 hover:text-white hover:bg-white/10'; ?>" <?php echo $current_page == 'student_dashboard.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-th-large w-5 text-center"></i><span class="text-sm font-medium">Dashboard</span>
        </a>
        <a href="student_profile.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'student_profile.php' ? 'text-white nav-active' : 'text-white/80 hover:text-white hover:bg-white/10'; ?>" <?php echo $current_page == 'student_profile.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-user w-5 text-center"></i><span class="text-sm font-medium">My Profile</span>
        </a>
        <a href="student_qr.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'student_qr.php' ? 'text-white nav-active' : 'text-white/80 hover:text-white hover:bg-white/10'; ?>" <?php echo $current_page == 'student_qr.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-qrcode w-5 text-center"></i><span class="text-sm font-medium">My QR Code</span>
        </a>
        <a href="student_record.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'student_record.php' ? 'text-white nav-active' : 'text-white/80 hover:text-white hover:bg-white/10'; ?>" <?php echo $current_page == 'student_record.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-notes-medical w-5 text-center"></i><span class="text-sm font-medium">Health Records</span>
        </a>
        <a href="student_appointments.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'student_appointments.php' ? 'text-white nav-active' : 'text-white/80 hover:text-white hover:bg-white/10'; ?>" <?php echo $current_page == 'student_appointments.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-calendar-alt w-5 text-center"></i><span class="text-sm font-medium">Appointments</span>
        </a>
        <a href="student_announcement.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'student_announcement.php' ? 'text-white nav-active' : 'text-white/80 hover:text-white hover:bg-white/10'; ?>" <?php echo $current_page == 'student_announcement.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-bullhorn w-5 text-center"></i><span class="text-sm font-medium">Announcements</span>
        </a>
        <a href="student_notifications.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'student_notifications.php' ? 'text-white nav-active' : 'text-white/80 hover:text-white hover:bg-white/10'; ?>" <?php echo $current_page == 'student_notifications.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-bell w-5 text-center"></i><span class="text-sm font-medium">Notifications</span>
            <?php if (isset($unread_count) && $unread_count > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="student_settings.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'student_settings.php' ? 'text-white nav-active' : 'text-white/80 hover:text-white hover:bg-white/10'; ?>" <?php echo $current_page == 'student_settings.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-cog w-5 text-center"></i><span class="text-sm font-medium">Settings</span>
        </a>
        
        <div class="border-t border-[#5c0017] my-4 mx-5"></div>
        
        <a href="student_logout.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/60 hover:text-white hover:bg-red-600/20 transition-all">
            <i class="fas fa-sign-out-alt w-5 text-center"></i><span class="text-sm font-medium">Sign Out</span>
        </a>
    </nav>
    
    <div class="p-4 border-t border-[#5c0017] mt-auto">
        <p class="text-[10px] text-white/30 text-center">&copy; <?php echo date('Y'); ?> PUPBC Carelink</p>
    </div>
</aside>

<!-- Main Content -->
<main class="md:ml-64 min-h-screen pb-20 md:pb-6">
    
    <!-- Header with Notification Bell -->
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200">
        <div class="px-4 md:px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Health Records</h1>
                <p class="text-sm text-gray-500">View your visit history and prescriptions</p>
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
                            <a href="<?php echo $notif['link']; ?>?notif_id=<?php echo $notif['id']; ?>" class="notification-item block p-3 border-b hover:bg-gray-50 transition">
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
    
    <!-- Page Content -->
    <div class="p-4 md:p-6">
        <div class="max-w-5xl mx-auto">
            
            <!-- Search -->
            <div class="relative mb-6">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="searchInput" placeholder="Search records..." class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-[#800020]/20 focus:border-[#800020]">
            </div>
            
            <!-- Tabs -->
            <div class="flex gap-2 border-b border-gray-200 pb-2 mb-6">
                <button onclick="showTab('visits')" id="tabVisitsBtn" class="px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 border-[#800020] text-[#800020] transition"><i class="fas fa-history mr-1"></i> Visit History</button>
                <button onclick="showTab('prescriptions')" id="tabPrescriptionsBtn" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 transition"><i class="fas fa-prescription-bottle mr-1"></i> Prescriptions</button>
                <button onclick="showTab('documents')" id="tabDocumentsBtn" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 transition"><i class="fas fa-file-alt mr-1"></i> Documents</button>
            </div>
            
            <!-- Visits Tab -->
            <div id="visitsTab">
                <?php if (empty($visits)): ?>
                    <div class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <i class="fas fa-notes-medical text-5xl text-gray-300 mb-3 block"></i>
                        <p class="text-gray-500">No visits recorded yet.</p>
                        <a href="student_qr.php" class="text-sm text-[#800020] mt-2 inline-block">Check in at the kiosk →</a>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($visits as $visit): ?>
                            <div class="bg-white rounded-xl shadow-sm border p-4 card-hover">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div class="flex items-start gap-3">
                                        <div class="w-10 h-10 rounded-full bg-[#800020]/10 flex items-center justify-center">
                                            <i class="fas fa-stethoscope text-[#800020]"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($visit['symptoms'] ?? 'General Checkup'); ?></p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <i class="far fa-calendar-alt mr-1"></i><?php echo date('M d, Y', strtotime($visit['visit_date'])); ?>
                                                <span class="mx-1">•</span>
                                                <i class="far fa-user mr-1"></i>Nurse <?php echo htmlspecialchars($visit['nurse_first'] ?? 'Staff'); ?>
                                            </p>
                                            <?php if (!empty($visit['diagnosis'])): ?>
                                                <p class="text-xs text-gray-600 mt-1"><span class="font-medium">Diagnosis:</span> <?php echo htmlspecialchars($visit['diagnosis']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-700 self-start sm:self-center">
                                        <?php echo ucfirst($visit['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Prescriptions Tab -->
            <div id="prescriptionsTab" class="hidden">
                <?php if (empty($prescriptions)): ?>
                    <div class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <i class="fas fa-prescription-bottle text-5xl text-gray-300 mb-3 block"></i>
                        <p class="text-gray-500">No prescriptions found.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($prescriptions as $prescription): ?>
                            <div class="bg-white rounded-xl shadow-sm border p-4 card-hover">
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center">
                                        <i class="fas fa-pills text-green-600"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($prescription['medicine_name']); ?></p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <i class="far fa-calendar-alt mr-1"></i>Issued <?php echo date('M d, Y', strtotime($prescription['visit_date'])); ?>
                                        </p>
                                        <div class="flex flex-wrap gap-3 mt-2 text-sm">
                                            <span class="text-gray-600">💊 Dosage: <?php echo htmlspecialchars($prescription['dosage'] ?? 'N/A'); ?></span>
                                            <span class="text-gray-600">📦 Quantity: <?php echo $prescription['quantity']; ?></span>
                                        </div>
                                        <?php if (!empty($prescription['instructions'])): ?>
                                            <p class="text-xs text-gray-500 mt-2"><i class="fas fa-info-circle mr-1"></i><?php echo htmlspecialchars($prescription['instructions']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Documents Tab -->
            <div id="documentsTab" class="hidden">
                <div class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                    <i class="fas fa-file-alt text-5xl text-gray-300 mb-3 block"></i>
                    <p class="text-gray-500">No medical documents uploaded yet.</p>
                    <button class="mt-4 inline-flex items-center gap-2 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018] transition">
                        <i class="fas fa-download"></i> Request a document
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- MOBILE BOTTOM NAVIGATION -->
<nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 lg:hidden z-40 safe-bottom shadow-[0_-4px_12px_rgba(0,0,0,0.05)]" aria-label="Mobile navigation">
    <div class="flex justify-around items-center py-1.5 px-2 max-w-lg mx-auto">
        <a href="student_dashboard.php" class="flex flex-col items-center py-1 px-3 rounded-lg <?php echo $current_page === 'student_dashboard.php' ? 'nav-active-mobile' : 'text-gray-400 hover:text-gray-600 transition-colors'; ?>" <?php echo $current_page === 'student_dashboard.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-th-large text-lg"></i><span class="text-[10px] mt-0.5 font-medium">Home</span>
        </a>
        <a href="student_qr.php" class="flex flex-col items-center py-1 px-3 rounded-lg <?php echo $current_page === 'student_qr.php' ? 'nav-active-mobile' : 'text-gray-400 hover:text-gray-600 transition-colors'; ?>" <?php echo $current_page === 'student_qr.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-qrcode text-lg"></i><span class="text-[10px] mt-0.5 font-medium">QR Code</span>
        </a>
        <a href="student_record.php" class="flex flex-col items-center py-1 px-3 rounded-lg <?php echo $current_page === 'student_record.php' ? 'nav-active-mobile' : 'text-gray-400 hover:text-gray-600 transition-colors'; ?>" <?php echo $current_page === 'student_record.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-notes-medical text-lg"></i><span class="text-[10px] mt-0.5 font-medium">Records</span>
        </a>
        <a href="student_appointments.php" class="flex flex-col items-center py-1 px-3 rounded-lg <?php echo $current_page === 'student_appointments.php' ? 'nav-active-mobile' : 'text-gray-400 hover:text-gray-600 transition-colors'; ?>" <?php echo $current_page === 'student_appointments.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-calendar-alt text-lg"></i><span class="text-[10px] mt-0.5 font-medium">Appts</span>
        </a>
        <a href="student_notifications.php" class="flex flex-col items-center py-1 px-3 rounded-lg relative <?php echo $current_page === 'student_notifications.php' ? 'nav-active-mobile' : 'text-gray-400 hover:text-gray-600 transition-colors'; ?>" <?php echo $current_page === 'student_notifications.php' ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-bell text-lg"></i>
            <?php if (isset($unread_count) && $unread_count > 0): ?>
                <span class="absolute -top-0.5 right-0 bg-red-500 text-white text-[9px] font-bold rounded-full min-w-[16px] h-[16px] flex items-center justify-center px-0.5"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
            <?php endif; ?>
            <span class="text-[10px] mt-0.5 font-medium">Alerts</span>
        </a>
        <a href="student_profile.php" class="flex flex-col items-center py-1 px-3 rounded-lg <?php echo in_array($current_page, ['student_profile.php', 'student_settings.php']) ? 'nav-active-mobile' : 'text-gray-400 hover:text-gray-600 transition-colors'; ?>" <?php echo in_array($current_page, ['student_profile.php', 'student_settings.php']) ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-user-circle text-lg"></i><span class="text-[10px] mt-0.5 font-medium">Profile</span>
        </a>
    </div>
</nav>

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
            }
        });
    }
    
    function showTab(tab) {
        const visits = document.getElementById('visitsTab');
        const prescriptions = document.getElementById('prescriptionsTab');
        const documents = document.getElementById('documentsTab');
        const visitsBtn = document.getElementById('tabVisitsBtn');
        const prescriptionsBtn = document.getElementById('tabPrescriptionsBtn');
        const documentsBtn = document.getElementById('tabDocumentsBtn');
        
        if (tab === 'visits') {
            visits.classList.remove('hidden'); 
            prescriptions.classList.add('hidden'); 
            documents.classList.add('hidden');
            visitsBtn.classList.add('border-[#800020]', 'text-[#800020]'); 
            visitsBtn.classList.remove('text-gray-500');
            prescriptionsBtn.classList.remove('border-[#800020]', 'text-[#800020]'); 
            prescriptionsBtn.classList.add('text-gray-500');
            documentsBtn.classList.remove('border-[#800020]', 'text-[#800020]'); 
            documentsBtn.classList.add('text-gray-500');
        } else if (tab === 'prescriptions') {
            visits.classList.add('hidden'); 
            prescriptions.classList.remove('hidden'); 
            documents.classList.add('hidden');
            prescriptionsBtn.classList.add('border-[#800020]', 'text-[#800020]'); 
            prescriptionsBtn.classList.remove('text-gray-500');
            visitsBtn.classList.remove('border-[#800020]', 'text-[#800020]'); 
            visitsBtn.classList.add('text-gray-500');
            documentsBtn.classList.remove('border-[#800020]', 'text-[#800020]'); 
            documentsBtn.classList.add('text-gray-500');
        } else {
            visits.classList.add('hidden'); 
            prescriptions.classList.add('hidden'); 
            documents.classList.remove('hidden');
            documentsBtn.classList.add('border-[#800020]', 'text-[#800020]'); 
            documentsBtn.classList.remove('text-gray-500');
            visitsBtn.classList.remove('border-[#800020]', 'text-[#800020]'); 
            visitsBtn.classList.add('text-gray-500');
            prescriptionsBtn.classList.remove('border-[#800020]', 'text-[#800020]'); 
            prescriptionsBtn.classList.add('text-gray-500');
        }
    }
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const term = this.value.toLowerCase();
            const cards = document.querySelectorAll('#visitsTab .card-hover, #prescriptionsTab .card-hover');
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(term) ? '' : 'none';
            });
        });
    }
    
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