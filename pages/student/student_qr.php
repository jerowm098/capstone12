    <?php
    // capstonemain/pages/student/student_qr.php
    session_start();
    if (!isset($_SESSION['student_id'])) {
        header('Location: student_login.php');
        exit();
    }
    require_once '../../config/db_connect.php';
    require_once '../../includes/notification_helper.php';

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
            'qr_code' => $_SESSION['qr_code'] ?? ''
        ];
    }

    $first_name = $student['first_name'] ?? 'Student';
    $last_name = $student['last_name'] ?? '';
    $full_name = trim($first_name . ' ' . $last_name);
    $student_number = $student['student_number'] ?? 'N/A';
    $course = $student['course'] ?? 'N/A';
    $year_level = $student['year_level'] ?? 'N/A';
    $blood_type = $student['blood_type'] ?? 'N/A';
    $qr_code = $student['qr_code'] ?? 'CARE-DEFAULT';
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

    // Get unread notifications count
    $unread_count = getUnreadCount($student_id);
    $notifications = getUnreadNotifications($student_id, 5);

    // Build QR token
    $token = "pupbc:carelink:" . $student_id . ":" . $student_number . ":" . $qr_code;

    // Handle QR regeneration
    if (isset($_GET['regenerate']) && $_GET['regenerate'] == '1') {
        $new_qr = 'CARE-' . strtoupper(bin2hex(random_bytes(8)));
        $stmt = mysqli_prepare($conn, "UPDATE students SET qr_code = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $new_qr, $student_id);
        mysqli_stmt_execute($stmt);
        $_SESSION['qr_code'] = $new_qr;
        header('Location: student_qr.php?success=1');
        exit();
    }

    $current_page = basename($_SERVER['PHP_SELF']);

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
        <title>QR Code - PUPBC Carelink</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
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
        
        <!-- Header with Notification Bell (no menu toggle button!) -->
        <div class="sticky top-0 z-30 bg-white border-b border-gray-200">
            <div class="px-4 md:px-6 py-4 flex items-center justify-between">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900">QR Code</h1>
                    <p class="text-sm text-gray-500">Your unique Carelink QR code for clinic check-in</p>
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
                    
                    <!-- Notification Dropdown -->
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
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700">
                        <i class="fas fa-check-circle mr-2"></i> QR code regenerated successfully!
                    </div>
                <?php endif; ?>
                
                <div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
                    
                    <!-- QR Card -->
                    <div class="bg-white rounded-xl shadow-sm border overflow-hidden card-hover transition-all">
                        <div class="bg-gradient-to-r from-[#800020] to-[#4a0010] p-5 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-white/80">Carelink ID</div>
                                    <div class="mt-0.5 text-lg font-bold"><?php echo htmlspecialchars($full_name); ?></div>
                                    <div class="text-xs text-white/80"><?php echo htmlspecialchars($student_number); ?></div>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-green-500 px-2 py-0.5 text-xs font-medium text-white">Active</span>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 p-6">
                                <div class="bg-white rounded-lg p-3 shadow-md">
                                    <div id="qrcode"></div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-3 border-y border-gray-100 py-4 mt-4 text-center">
                                <div><p class="text-[10px] font-semibold uppercase text-gray-400">Course</p><p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($course); ?></p></div>
                                <div><p class="text-[10px] font-semibold uppercase text-gray-400">Year</p><p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($year_level); ?></p></div>
                                <div><p class="text-[10px] font-semibold uppercase text-gray-400">Blood</p><p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($blood_type); ?></p></div>
                            </div>
                            
                            <div class="flex flex-wrap gap-2 mt-4">
                                <button onclick="downloadQR()" class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018] transition">
                                    <i class="fas fa-download"></i> Download
                                </button>
                                <button onclick="window.print()" class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <a href="?regenerate=1" onclick="return confirm('Regenerate your QR code? The old code will be invalidated.')" class="inline-flex items-center justify-center gap-1 rounded-lg px-3 py-2 text-gray-500 hover:text-[#800020] transition">
                                    <i class="fas fa-sync-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Instructions -->
                    <div class="space-y-5">
                        <div class="bg-white rounded-xl shadow-sm border p-5 card-hover transition-all">
                            <h3 class="text-base font-semibold text-gray-900 mb-3 flex items-center gap-2"><i class="fas fa-info-circle text-[#800020]"></i> How to use your Carelink QR</h3>
                            <div class="space-y-4">
                                <div class="flex gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#800020] text-white text-sm font-bold">1</div>
                                    <div><p class="text-sm font-semibold text-gray-800">Save it on your phone</p><p class="text-xs text-gray-500">Download or screenshot your QR. Add to wallet for quick access.</p></div>
                                </div>
                                <div class="flex gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#800020] text-white text-sm font-bold">2</div>
                                    <div><p class="text-sm font-semibold text-gray-800">Scan at the kiosk</p><p class="text-xs text-gray-500">Present your QR code to the kiosk scanner for instant check-in.</p></div>
                                </div>
                                <div class="flex gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#800020] text-white text-sm font-bold">3</div>
                                    <div><p class="text-sm font-semibold text-gray-800">Get your queue number</p><p class="text-xs text-gray-500">Complete the triage and receive your queue number via email.</p></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-sm border p-5">
                            <h3 class="text-base font-semibold text-gray-900 mb-3 flex items-center gap-2"><i class="fas fa-shield-alt text-[#800020]"></i> Privacy & Security</h3>
                            <p class="text-sm text-gray-500 mb-2">Your QR contains an encrypted token only — no medical data or personal information is stored on the code itself.</p>
                            <p class="text-sm text-gray-500 mb-2">If you lose your QR, regenerate it instantly. The old code is invalidated.</p>
                            <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-500 border"><strong class="text-gray-700">Compliance:</strong> Aligned with the Philippine Data Privacy Act of 2012 (RA 10173).</div>
                        </div>
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
        
        // Generate QR Code
        const qrContainer = document.getElementById('qrcode');
        new QRCode(qrContainer, {
            text: '<?php echo $token; ?>',
            width: 200,
            height: 200,
            colorDark: '#800020',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
        
        function downloadQR() {
            const canvas = qrContainer.querySelector('canvas');
            if (canvas) {
                const link = document.createElement('a');
                link.download = 'carelink-qr-<?php echo $student_number; ?>.png';
                link.href = canvas.toDataURL();
                link.click();
            }
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