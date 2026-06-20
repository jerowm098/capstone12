<?php
//capstonemain/pages/student/student_notifications.php
session_start();
if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit();
}
require_once '../../config/db_connect.php';
require_once '../../includes/notification_helper.php';

$student_id = $_SESSION['student_id'];

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

// Mark as read if notification ID is provided
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    markNotificationAsRead($_GET['read'], $student_id);
    header('Location: student_notifications.php');
    exit();
}

// Mark all as read
if (isset($_GET['read_all'])) {
    markAllNotificationsAsRead($student_id);
    header('Location: student_notifications.php');
    exit();
}

// Get all notifications
$notifications = getAllNotifications($student_id, 50);

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Notifications - PUPBC Carelink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { transition: transform 0.3s ease-in-out; }
        .sidebar-hidden { transform: translateX(-100%); }
        .sidebar-visible { transform: translateX(0); }
        .sidebar-overlay { transition: opacity 0.3s ease-in-out; }
        .nav-active { background-color: #c9a84c; color: #800020; }
        .nav-inactive { color: white; }
        .nav-inactive:hover { background-color: #600018; }
        .safe-bottom { padding-bottom: env(safe-area-inset-bottom, 0); }
    </style>
</head>
<body class="bg-gray-50">

<!-- Mobile Menu Button -->
<button id="menuToggle" class="fixed top-4 right-4 z-50 md:hidden bg-[#800020] text-white w-10 h-10 rounded-lg flex items-center justify-center shadow-md">
    <i class="fas fa-bars text-lg"></i>
</button>

<!-- Sidebar Overlay -->
<div id="sidebarOverlay" class="sidebar-overlay fixed inset-0 bg-black/50 z-40 hidden md:hidden opacity-0 transition-opacity"></div>

<!-- Sidebar -->
<aside id="sidebar" class="sidebar flex flex-col fixed top-0 left-0 h-full w-72 bg-[#800020] z-50 shadow-xl overflow-y-auto sidebar-hidden md:sidebar-visible md:translate-x-0">
    <div class="flex items-center justify-end p-4 md:hidden">
        <button id="closeSidebar" class="md:hidden text-white/70 hover:text-white"><i class="fas fa-times text-xl"></i></button>
    </div>
    
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
<main class="md:ml-72 min-h-screen pb-20 md:pb-0">
    
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200 md:hidden">
        <div class="flex items-center px-4 py-3">
            <div class="flex items-center gap-2"><div class="rounded-lg bg-gradient-primary p-1.5"><i class="fas fa-heartbeat text-white text-sm"></i></div><div><span class="font-bold text-gray-900 text-sm">PUPBC Carelink</span></div></div>
        </div>
    </div>
    
    <div class="hidden md:block sticky top-0 z-30 bg-white border-b border-gray-200">
        <div class="px-6 py-3">
            <h1 class="text-xl font-bold text-gray-900">Notifications</h1>
            <p class="text-sm text-gray-500">Stay updated with your latest notifications</p>
        </div>
    </div>
    
    <div class="p-4 md:p-6 lg:p-8">
        <div class="max-w-3xl mx-auto">
            
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-[#800020]/10 flex items-center justify-center">
                        <i class="fas fa-bell text-2xl text-[#800020]"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
                        <p class="text-sm text-gray-500">Stay updated with your latest notifications</p>
                    </div>
                </div>
                <?php if (!empty($notifications)): ?>
                <a href="?read_all=1" class="text-sm text-[#800020] hover:underline">Mark all as read</a>
                <?php endif; ?>
            </div>
            
            <div class="space-y-3">
                <?php if (empty($notifications)): ?>
                    <div class="bg-white rounded-xl p-12 text-center border border-dashed border-gray-300">
                        <i class="fas fa-bell-slash text-5xl text-gray-300 mb-3 block"></i>
                        <p class="text-gray-500">No notifications yet</p>
                        <p class="text-xs text-gray-400 mt-1">When you receive notifications, they will appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                    <div class="bg-white rounded-xl border <?php echo $notif['is_read'] ? 'border-gray-100' : 'border-l-4 border-l-[#800020]'; ?> shadow-sm hover:shadow-md transition">
                        <a href="<?php echo $notif['link']; ?>?notif_id=<?php echo $notif['id']; ?>" class="block p-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5">
                                    <?php if ($notif['type'] == 'announcement'): ?>
                                        <div class="w-10 h-10 rounded-full bg-[#800020]/10 flex items-center justify-center">
                                            <i class="fas fa-bullhorn text-[#800020]"></i>
                                        </div>
                                    <?php elseif ($notif['type'] == 'appointment'): ?>
                                        <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center">
                                            <i class="fas fa-calendar-alt text-blue-500"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                                            <i class="fas fa-info-circle text-gray-500"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($notif['title']); ?></p>
                                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <p class="text-xs text-gray-400 mt-2"><?php echo timeAgo($notif['created_at']); ?></p>
                                </div>
                                <?php if (!$notif['is_read']): ?>
                                    <div class="w-2 h-2 bg-[#800020] rounded-full mt-2"></div>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const closeSidebar = document.getElementById('closeSidebar');
    
    function openSidebar() { sidebar.classList.remove('sidebar-hidden'); sidebar.classList.add('sidebar-visible'); sidebarOverlay.classList.remove('hidden'); setTimeout(() => { sidebarOverlay.style.opacity = '1'; }, 10); document.body.style.overflow = 'hidden'; }
    function closeSidebarFunc() { sidebar.classList.remove('sidebar-visible'); sidebar.classList.add('sidebar-hidden'); sidebarOverlay.style.opacity = '0'; setTimeout(() => { sidebarOverlay.classList.add('hidden'); }, 300); document.body.style.overflow = ''; }
    
    if (menuToggle) menuToggle.addEventListener('click', openSidebar);
    if (closeSidebar) closeSidebar.addEventListener('click', closeSidebarFunc);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebarFunc);
    
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) { sidebar.classList.remove('sidebar-hidden'); sidebar.classList.add('sidebar-visible'); sidebarOverlay.classList.add('hidden'); document.body.style.overflow = ''; }
        else { sidebar.classList.add('sidebar-hidden'); sidebar.classList.remove('sidebar-visible'); }
    });
</script>

</body>
</html>