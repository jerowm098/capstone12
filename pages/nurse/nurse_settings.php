<?php
// capstonemain/pages/nurse/nurse_settings.php
session_start();
if (!isset($_SESSION['nurse_id'])) {
    header('Location: nurse_login.php');
    exit();
}
require_once '../../config/db_connect.php';

// Get nurse info
$nurse_id = $_SESSION['nurse_id'];
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, u.email, n.position, n.license_number FROM users u JOIN nurses n ON u.id = n.user_id WHERE n.id = ?");
mysqli_stmt_bind_param($stmt, "i", $nurse_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$nurse = mysqli_fetch_assoc($result);

$nurse_name = ($nurse['first_name'] ?? 'Nurse') . ' ' . ($nurse['last_name'] ?? '');
$nurse_position = $nurse['position'] ?? 'Head Nurse';
$nurse_email = $nurse['email'] ?? '';
$license_number = $nurse['license_number'] ?? 'N/A';
$initials = strtoupper(substr($nurse['first_name'] ?? 'N', 0, 1) . substr($nurse['last_name'] ?? '', 0, 1));

$success_message = '';
$error_message = '';

// Handle Update Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_email = trim($_POST['email'] ?? '');
    $new_position = trim($_POST['position'] ?? '');
    $new_license = trim($_POST['license_number'] ?? '');
    
    $errors = [];
    
    if (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($errors)) {
        // Update users table
        $stmt1 = mysqli_prepare($conn, "UPDATE users SET email = ? WHERE id = (SELECT user_id FROM nurses WHERE id = ?)");
        mysqli_stmt_bind_param($stmt1, "si", $new_email, $nurse_id);
        
        // Update nurses table
        $stmt2 = mysqli_prepare($conn, "UPDATE nurses SET position = ?, license_number = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt2, "ssi", $new_position, $new_license, $nurse_id);
        
        if (mysqli_stmt_execute($stmt1) && mysqli_stmt_execute($stmt2)) {
            $success_message = "Profile updated successfully!";
            // Refresh data
            $nurse_email = $new_email;
            $nurse_position = $new_position;
            $license_number = $new_license;
        } else {
            $error_message = "Failed to update profile.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (empty($current_password)) $errors[] = "Current password is required.";
    if (empty($new_password)) $errors[] = "New password is required.";
    elseif (strlen($new_password) < 8) $errors[] = "Password must be at least 8 characters.";
    if ($new_password !== $confirm_password) $errors[] = "Passwords do not match.";
    
    if (empty($errors)) {
        // Get current password hash
        $stmt = mysqli_prepare($conn, "SELECT u.password FROM nurses n JOIN users u ON n.user_id = u.id WHERE n.id = ?");
        mysqli_stmt_bind_param($stmt, "i", $nurse_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        // Verify current password using password_verify
        if ($user && password_verify($current_password, $user['password'])) {
            $hashed_new = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt2 = mysqli_prepare($conn, "UPDATE nurses n JOIN users u ON n.user_id = u.id SET u.password = ? WHERE n.id = ?");
            mysqli_stmt_bind_param($stmt2, "si", $hashed_new, $nurse_id);
            if (mysqli_stmt_execute($stmt2)) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Failed to change password.";
            }
        } else {
            $error_message = "Current password is incorrect.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle Notification Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notifications'])) {
    $email_notif = isset($_POST['email_notifications']) ? 1 : 0;
    $announcement_notif = isset($_POST['announcement_notifications']) ? 1 : 0;
    $reminder_notif = isset($_POST['reminder_notifications']) ? 1 : 0;
    
    // Save to session or database (you can create a notifications table if needed)
    $_SESSION['notifications'] = [
        'email' => $email_notif,
        'announcement' => $announcement_notif,
        'reminder' => $reminder_notif
    ];
    
    $success_message = "Notification preferences saved!";
}

// Get saved notification preferences
$notifications = $_SESSION['notifications'] ?? [
    'email' => 1,
    'announcement' => 1,
    'reminder' => 1
];

$current_page = basename($_SERVER['PHP_SELF']);
$positions = ['Head Nurse', 'Senior Nurse', 'Staff Nurse', 'Clinic Nurse', 'School Nurse', 'Health Officer'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - PUPBC Carelink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: {
                    primary: { DEFAULT: '#800020', foreground: '#ffffff' },
                    accent: { DEFAULT: '#c9a84c' },
                    border: '#e2e8f0', foreground: '#0f172a', 'muted-foreground': '#64748b'
                },
                backgroundImage: { 'gradient-primary': 'linear-gradient(135deg, #800020, #600018)' }
            }}
        }
    </script>
    <style>
        .animate-fade-in{animation:fadeIn 0.5s ease-out}
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        .transition-smooth{transition:all 0.3s ease}
        body{background-color:#f8fafc}
        .modal{transition:opacity 0.3s ease}
    </style>
</head>
<body class="font-sans antialiased text-gray-900">
    
    <!-- Sidebar -->
    <aside class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 z-50 bg-[#800020] border-r border-[#600018]">
        <div class="flex h-16 items-center gap-3 px-4 border-b border-[#600018]">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            </div>
            <div>
                <div class="font-bold text-white leading-none">PUPBC Carelink</div>
                <div class="text-[9px] text-white/70 uppercase tracking-widest mt-0.5">Health Information System</div>
            </div>
        </div>
        <div class="flex items-center gap-3 px-4 py-4 border-b border-[#600018] bg-[#600018]">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#c9a84c] text-[#800020] font-bold"><?php echo $initials; ?></div>
            <div><div class="text-sm font-semibold text-white"><?php echo htmlspecialchars($nurse_name); ?></div><div class="text-xs text-[#c9a84c]"><?php echo htmlspecialchars($nurse_position); ?></div></div>
        </div>
        <div class="flex-1 overflow-y-auto">
            <nav class="space-y-1 px-3 py-4">
                <a href="nurse_dashboard.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg> Dashboard
                </a>
                <a href="nurse_queue.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Queue
                </a>
                <a href="nurse_patients.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Patients
                </a>
                <a href="nurse_announcements.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> Announcements
                </a>
                <a href="nurse_settings.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium bg-[#c9a84c] text-[#800020] transition-smooth">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/></svg> Settings
                </a>
            </nav>
        </div>
    </aside>

    <div class="md:pl-64 flex flex-col min-h-screen">
        
        <!-- Header -->
        <header class="sticky top-0 z-40 bg-white border-b border-gray-200">
            <div class="flex h-16 items-center justify-between px-6 md:px-8">
                <div>
                    <div class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">Clinic Staff Portal</div>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Settings</h1>
                </div>
                <div class="flex items-center gap-5">
                    <a href="nurse_logout.php" class="hidden sm:flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-smooth">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        Sign out
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 p-4 md:p-8 pb-20 md:pb-8">
            <div class="animate-fade-in max-w-4xl mx-auto">
                
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
                    <button onclick="openEditModal()" class="inline-flex items-center gap-2 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3l4 4-7 7H10v-4l7-7z"/><path d="M4 20h16"/></svg>
                        Edit Profile
                    </button>
                </div>

                <?php if ($success_message): ?>
                    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Profile Information Card (Read-only) -->
                <div class="rounded-xl border border-gray-200 bg-white shadow-card mb-6">
                    <div class="p-6 pb-3 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">Profile Information</h3>
                        <p class="text-sm text-gray-500 mt-1">Your clinic staff information</p>
                    </div>
                    <div class="p-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div><label class="block text-xs font-medium text-gray-500 uppercase mb-1">Full Name</label><p class="text-gray-900 font-medium"><?php echo htmlspecialchars($nurse_name); ?></p></div>
                            <div><label class="block text-xs font-medium text-gray-500 uppercase mb-1">Email</label><p class="text-gray-900 font-medium"><?php echo htmlspecialchars($nurse_email); ?></p></div>
                            <div><label class="block text-xs font-medium text-gray-500 uppercase mb-1">Position</label><p class="text-gray-900 font-medium"><?php echo htmlspecialchars($nurse_position); ?></p></div>
                            <div><label class="block text-xs font-medium text-gray-500 uppercase mb-1">License Number</label><p class="text-gray-900 font-medium"><?php echo htmlspecialchars($license_number); ?></p></div>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="rounded-xl border border-gray-200 bg-white shadow-card mb-6">
                    <div class="p-6 pb-3">
                        <h3 class="text-base font-semibold text-gray-900">Change Password</h3>
                        <p class="text-sm text-gray-500 mt-1">Update your account password</p>
                    </div>
                    <div class="p-6 pt-3">
                        <form method="POST" class="space-y-4">
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label><input type="password" name="current_password" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#800020]/20 focus:border-[#800020]"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">New Password</label><input type="password" name="new_password" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#800020]/20 focus:border-[#800020]"><p class="mt-1 text-xs text-gray-400">Minimum 8 characters</p></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label><input type="password" name="confirm_password" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#800020]/20 focus:border-[#800020]"></div>
                            <div class="flex justify-end"><button type="submit" name="change_password" class="inline-flex items-center gap-2 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018]">Update password</button></div>
                        </form>
                    </div>
                </div>

                <!-- Notification Settings (Email only, no SMS) -->
                <div class="rounded-xl border border-gray-200 bg-white shadow-card mb-6">
                    <div class="p-6 pb-3">
                        <h3 class="text-base font-semibold text-gray-900">Notification Preferences</h3>
                        <p class="text-sm text-gray-500 mt-1">Manage how you receive notifications</p>
                    </div>
                </div>


            </div>
        </main>
    </div>

    <!-- EDIT PROFILE MODAL -->
    <div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between rounded-t-2xl">
                <h3 class="text-lg font-bold text-gray-900">Edit Profile</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label><input type="text" value="<?php echo htmlspecialchars($nurse_name); ?>" disabled class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"><p class="mt-1 text-xs text-gray-400">Cannot be changed. Contact admin for corrections.</p></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Email *</label><input type="email" name="email" value="<?php echo htmlspecialchars($nurse_email); ?>" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                    <select name="position" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?php echo $pos; ?>" <?php echo $nurse_position === $pos ? 'selected' : ''; ?>><?php echo $pos; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">License Number</label><input type="text" name="license_number" value="<?php echo htmlspecialchars($license_number); ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeEditModal()" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" name="update_profile" class="flex-1 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018]">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="fixed bottom-0 left-0 right-0 bg-[#800020] md:hidden z-50 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)]">
        <div class="flex justify-around py-2">
            <a href="nurse_dashboard.php" class="flex flex-col items-center text-[10px] px-1 <?php echo $current_page=='nurse_dashboard.php'?'text-[#c9a84c]':'text-white/70'; ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Home</a>
            <a href="nurse_queue.php" class="flex flex-col items-center text-[10px] px-1 <?php echo $current_page=='nurse_queue.php'?'text-[#c9a84c]':'text-white/70'; ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>Queue</a>
            <a href="nurse_patients.php" class="flex flex-col items-center text-[10px] px-1 <?php echo $current_page=='nurse_patients.php'?'text-[#c9a84c]':'text-white/70'; ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>Patients</a>
            <a href="nurse_announcements.php" class="flex flex-col items-center text-[10px] px-1 <?php echo $current_page=='nurse_announcements.php'?'text-[#c9a84c]':'text-white/70'; ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>News</a>
        </div>
    </div>

    <script>
        function openEditModal() {
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
            document.body.style.overflow = '';
        }
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeEditModal(); });
        document.getElementById('editModal')?.addEventListener('click', function(e) { if (e.target === this) closeEditModal(); });
    </script>
</body>
</html> 