<?php
// capstonemain/pages/nurse/nurse_announcements.php
session_start();
if (!isset($_SESSION['nurse_id'])) {
    header('Location: nurse_login.php');
    exit();
}
require_once '../../config/db_connect.php';
require_once '../../includes/notification_helper.php';

$nurse_id = $_SESSION['nurse_id'];

// Get nurse info
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, n.position FROM users u JOIN nurses n ON u.id = n.user_id WHERE n.id = ?");
mysqli_stmt_bind_param($stmt, "i", $nurse_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$nurse = mysqli_fetch_assoc($result);
$nurse_name = ($nurse['first_name'] ?? 'Nurse') . ' ' . ($nurse['last_name'] ?? '');
$nurse_position = $nurse['position'] ?? 'Head Nurse';
$initials = strtoupper(substr($nurse['first_name'] ?? 'N', 0, 1) . substr($nurse['last_name'] ?? '', 0, 1));

$message = '';
$message_type = '';

// Handle Add Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_announcement'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = $_POST['category'] ?? 'General';
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    $errors = [];
    if (empty($title)) $errors[] = "Title is required.";
    if (empty($content)) $errors[] = "Content is required.";
    
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO announcements (title, content, category, expiry_date, created_at) VALUES (?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "ssss", $title, $content, $category, $expiry_date);
        if (mysqli_stmt_execute($stmt)) {
            $announcement_id = mysqli_insert_id($conn);
            
            // CREATE NOTIFICATIONS FOR ALL STUDENTS
            $notif_title = "New Announcement: " . $title;
            $notif_message = substr($content, 0, 150) . (strlen($content) > 150 ? '...' : '');
            createNotificationForAllStudents('announcement', $notif_title, $notif_message, 'student_announcement.php');
            
            $message = "Announcement posted and notifications sent to all students!";
            $message_type = "success";
        } else {
            $message = "Failed to post announcement.";
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

// Handle Edit Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_announcement'])) {
    $announcement_id = intval($_POST['announcement_id']);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = $_POST['category'] ?? 'General';
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    $stmt = mysqli_prepare($conn, "UPDATE announcements SET title = ?, content = ?, category = ?, expiry_date = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ssssi", $title, $content, $category, $expiry_date, $announcement_id);
    if (mysqli_stmt_execute($stmt)) {
        $message = "Announcement updated successfully!";
        $message_type = "success";
    } else {
        $message = "Failed to update announcement.";
        $message_type = "error";
    }
}

// Handle Delete Announcement
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $announcement_id = intval($_GET['delete']);
    $stmt = mysqli_prepare($conn, "DELETE FROM announcements WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $announcement_id);
    if (mysqli_stmt_execute($stmt)) {
        $message = "Announcement deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Failed to delete announcement.";
        $message_type = "error";
    }
}

// Get all announcements
$stmt = mysqli_prepare($conn, "SELECT * FROM announcements ORDER BY created_at DESC");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$announcements = [];
while ($row = mysqli_fetch_assoc($result)) $announcements[] = $row;

$categories = ['Important', 'Announcement', 'Health Advisory', 'Clinic Schedule', 'Event', 'Reminder'];
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - PUPBC Carelink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: {
                    primary: { DEFAULT: '#800020', foreground: '#ffffff' },
                    accent: { DEFAULT: '#c9a84c' },
                    success: '#228b22', destructive: '#dc2626', warning: '#f59e0b', border: '#e2e8f0',
                },
                backgroundImage: { 'gradient-primary': 'linear-gradient(135deg, #800020, #600018)' },
            }}
        }
    </script>
    <style>
        .animate-fade-in{animation:fadeIn 0.5s ease-out}
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        .transition-smooth{transition:all 0.3s ease}
        body{background-color:#f8fafc}
    </style>
</head>
<body class="font-sans antialiased text-gray-900">
    
<!-- Desktop Sidebar -->
<aside class="hidden md:block fixed top-0 left-0 h-full w-64 bg-[#800020] shadow-xl overflow-y-auto z-30">
    <div class="flex items-center gap-2 p-4 border-b border-[#600018]">
        <div class="rounded-lg bg-white/20 p-2"><i class="fas fa-heartbeat text-white text-lg"></i></div>
        <div><span class="font-bold text-white">PUPBC Carelink</span><p class="text-[10px] text-white/60">Health Information System</p></div>
    </div>
    
    <div class="flex items-center gap-3 p-4 border-b border-[#600018] bg-[#600018]">
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#c9a84c] text-[#800020] font-bold text-sm"><?php echo $initials; ?></div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($nurse_name); ?></div>
            <div class="text-xs text-[#c9a84c] truncate"><?php echo htmlspecialchars($nurse_position); ?></div>
        </div>
    </div>
    
    <nav class="py-4">
        <div class="px-3 mb-2"><p class="text-[10px] font-semibold uppercase tracking-wider text-[#c9a84c] px-3">Main Menu</p></div>
        
        <a href="nurse_dashboard.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-home w-5"></i><span class="text-sm font-medium">Dashboard</span>
        </a>
        <a href="nurse_queue.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-users w-5"></i><span class="text-sm font-medium">Queue</span>
        </a>
        <a href="nurse_patients.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-user-injured w-5"></i><span class="text-sm font-medium">Patients</span>
        </a>
        <a href="nurse_appointments.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-calendar-alt w-5"></i><span class="text-sm font-medium">Appointments</span>
        </a>
        <a href="nurse_announcements.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'nurse_announcements.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
            <i class="fas fa-bullhorn w-5"></i><span class="text-sm font-medium">Announcements</span>
        </a>
        <a href="nurse_inventory.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-pills w-5"></i><span class="text-sm font-medium">Inventory</span>
        </a>
        <a href="nurse_settings.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-user-cog w-5"></i><span class="text-sm font-medium">Settings</span>
        </a>
        
        <div class="border-t border-[#600018] my-4 mx-3"></div>
        <a href="nurse_logout.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/70 hover:text-white hover:bg-[#600018] transition-all">
            <i class="fas fa-sign-out-alt w-5"></i><span class="text-sm font-medium">Sign Out</span>
        </a>
    </nav>
    
    <div class="p-4 border-t border-[#600018] mt-auto"><p class="text-[10px] text-white/40 text-center">© <?php echo date('Y'); ?> PUPBC Carelink</p></div>
</aside>

    <div class="md:pl-64 flex flex-col min-h-screen">
        <header class="sticky top-0 z-40 bg-white border-b border-gray-200">
            <div class="flex h-16 items-center justify-between px-6 md:px-8">
                <div><div class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">Clinic Staff Portal</div><h1 class="text-xl font-bold text-gray-900">Manage Announcements</h1></div>
                <a href="nurse_logout.php" class="hidden sm:flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Sign out</a>
            </div>
        </header>

        <main class="flex-1 p-4 md:p-8">
            <div class="space-y-6 animate-fade-in max-w-6xl mx-auto">
                
                <?php if ($message): ?>
                    <div class="rounded-lg p-4 text-sm <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <!-- Add Announcement Button -->
                <div class="flex justify-end">
                    <button onclick="openAddModal()" class="inline-flex items-center gap-2 rounded-lg bg-[#800020] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#600018] transition-smooth">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Post New Announcement
                    </button>
                </div>

                <!-- Announcements List -->
                <div class="space-y-4">
                    <?php if (empty($announcements)): ?>
                        <div class="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" class="mx-auto mb-3"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            No announcements yet. Click "Post New Announcement" to create one.
                        </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $a): 
                            $categoryColors = [
                                'Important' => 'bg-red-100 text-red-700',
                                'Announcement' => 'bg-blue-100 text-blue-700',
                                'Health Advisory' => 'bg-yellow-100 text-yellow-700',
                                'Clinic Schedule' => 'bg-green-100 text-green-700',
                                'Event' => 'bg-purple-100 text-purple-700',
                                'Reminder' => 'bg-orange-100 text-orange-700'
                            ];
                            $colorClass = $categoryColors[$a['category']] ?? 'bg-gray-100 text-gray-700';
                        ?>
                            <div class="bg-white rounded-xl border border-gray-200 shadow-card overflow-hidden">
                                <div class="p-5">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2 flex-wrap">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?php echo $colorClass; ?>"><?php echo htmlspecialchars($a['category']); ?></span>
                                                <span class="text-xs text-gray-400">Posted <?php echo date('M d, Y h:i A', strtotime($a['created_at'])); ?></span>
                                                <?php if ($a['expiry_date'] && strtotime($a['expiry_date']) < time()): ?><span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-700">Expired</span><?php endif; ?>
                                            </div>
                                            <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($a['title']); ?></h3>
                                            <p class="text-gray-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($a['content'])); ?></p>
                                            <?php if ($a['expiry_date']): ?>
                                                <p class="mt-2 text-xs text-gray-400">Expires: <?php echo date('M d, Y', strtotime($a['expiry_date'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex gap-2">
                                            <button onclick="openEditModal(<?php echo $a['id']; ?>, '<?php echo htmlspecialchars(addslashes($a['title'])); ?>', '<?php echo htmlspecialchars(addslashes($a['content'])); ?>', '<?php echo $a['category']; ?>', '<?php echo $a['expiry_date']; ?>')" class="p-2 text-gray-500 hover:text-[#800020] transition-smooth">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3l4 4-7 7H10v-4l7-7z"/><path d="M4 20h16"/></svg>
                                            </button>
                                            <a href="?delete=<?php echo $a['id']; ?>" onclick="return confirm('Delete this announcement?')" class="p-2 text-gray-500 hover:text-red-600 transition-smooth">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Info Box -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex gap-3">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <div class="text-sm text-blue-800">
                            <strong>Announcements will appear on the student portal dashboard.</strong><br>
                            Students will also receive a notification when you post a new announcement.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

<!-- Bottom Navigation Bar (Mobile only) -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden z-40 shadow-lg safe-bottom">
    <div class="flex justify-around py-2">
        <a href="nurse_dashboard.php" class="flex flex-col items-center py-1 px-2 <?php echo $current_page == 'nurse_dashboard.php' ? 'text-[#800020]' : 'text-gray-400'; ?>">
            <i class="fas fa-home text-xl"></i><span class="text-[10px] mt-1">Home</span>
        </a>
        <a href="nurse_queue.php" class="flex flex-col items-center py-1 px-2 <?php echo $current_page == 'nurse_queue.php' ? 'text-[#800020]' : 'text-gray-400'; ?>">
            <i class="fas fa-users text-xl"></i><span class="text-[10px] mt-1">Queue</span>
        </a>
        <a href="nurse_patients.php" class="flex flex-col items-center py-1 px-2 <?php echo $current_page == 'nurse_patients.php' ? 'text-[#800020]' : 'text-gray-400'; ?>">
            <i class="fas fa-user-injured text-xl"></i><span class="text-[10px] mt-1">Patients</span>
        </a>
        <a href="nurse_appointments.php" class="flex flex-col items-center py-1 px-2 <?php echo $current_page == 'nurse_appointments.php' ? 'text-[#800020]' : 'text-gray-400'; ?>">
            <i class="fas fa-calendar-alt text-xl"></i><span class="text-[10px] mt-1">Appts</span>
        </a>
        <a href="nurse_settings.php" class="flex flex-col items-center py-1 px-2 <?php echo $current_page == 'nurse_settings.php' ? 'text-[#800020]' : 'text-gray-400'; ?>">
            <i class="fas fa-user-cog text-xl"></i><span class="text-[10px] mt-1">Profile</span>
        </a>
    </div>
</div>

    <!-- Add Announcement Modal -->
    <div id="addModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4">
        <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">Post New Announcement</h3>
                <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Title *</label><input type="text" name="title" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                    <select name="category" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                        <?php foreach ($categories as $cat): ?><option value="<?php echo $cat; ?>"><?php echo $cat; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Content *</label><textarea name="content" rows="5" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date (Optional)</label><input type="date" name="expiry_date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"><p class="mt-1 text-xs text-gray-400">Leave empty if no expiry</p></div>
                <div class="flex gap-3 pt-2"><button type="button" onclick="closeAddModal()" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button><button type="submit" name="add_announcement" class="flex-1 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018]">Post Announcement</button></div>
            </form>
        </div>
    </div>

    <!-- Edit Announcement Modal -->
    <div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4">
        <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">Edit Announcement</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="announcement_id" id="edit_id">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Title *</label><input type="text" name="title" id="edit_title" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                    <select name="category" id="edit_category" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                        <?php foreach ($categories as $cat): ?><option value="<?php echo $cat; ?>"><?php echo $cat; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Content *</label><textarea name="content" id="edit_content" rows="5" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label><input type="date" name="expiry_date" id="edit_expiry" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                <div class="flex gap-3 pt-2"><button type="button" onclick="closeEditModal()" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button><button type="submit" name="edit_announcement" class="flex-1 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018]">Save Changes</button></div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() { document.getElementById('addModal').classList.remove('hidden'); document.getElementById('addModal').classList.add('flex'); document.body.style.overflow = 'hidden'; }
        function closeAddModal() { document.getElementById('addModal').classList.add('hidden'); document.getElementById('addModal').classList.remove('flex'); document.body.style.overflow = ''; }
        function openEditModal(id, title, content, category, expiry) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_content').value = content;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_expiry').value = expiry || '';
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); document.getElementById('editModal').classList.remove('flex'); document.body.style.overflow = ''; }
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeAddModal(); closeEditModal(); } });
    </script>
</body>
</html>