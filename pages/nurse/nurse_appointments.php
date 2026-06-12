<?php
// capstonemain/pages/nurse/nurse_appointments.php
session_start();
if (!isset($_SESSION['nurse_id'])) {
    header('Location: nurse_login.php');
    exit();
}
require_once '../../config/db_connect.php';
require_once '../../includes/notification_helper.php';

$nurse_id = $_SESSION['nurse_id'];

// Get current filter values (declare early)
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Get nurse info
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, n.position FROM users u JOIN nurses n ON u.id = n.user_id WHERE n.id = ?");
mysqli_stmt_bind_param($stmt, "i", $nurse_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$nurse = mysqli_fetch_assoc($result);
$nurse_name = ($nurse['first_name'] ?? 'Nurse') . ' ' . ($nurse['last_name'] ?? '');
$nurse_position = $nurse['position'] ?? 'Head Nurse';
$initials = strtoupper(substr($nurse['first_name'] ?? 'N', 0, 1) . substr($nurse['last_name'] ?? '', 0, 1));

// Auto-cancel past appointments (no-show after 30 mins)
$past_due_query = "
    SELECT id, student_id, appointment_date, appointment_time 
    FROM appointments 
    WHERE status IN ('pending', 'confirmed') 
    AND CONCAT(appointment_date, ' ', appointment_time) < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
";
$past_due_result = mysqli_query($conn, $past_due_query);
if ($past_due_result) {
    while ($row = mysqli_fetch_assoc($past_due_result)) {
        $upd_stmt = mysqli_prepare($conn, "UPDATE appointments SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($upd_stmt, "i", $row['id']);
        if (mysqli_stmt_execute($upd_stmt)) {
            $notif_title = "Appointment Cancelled (No-Show)";
            $notif_msg = "Your appointment on " . date('F d, Y', strtotime($row['appointment_date'])) . " at " . date('g:i A', strtotime($row['appointment_time'])) . " was automatically cancelled because you did not arrive within your scheduled time.";
            createNotification($row['student_id'], 'appointment', $notif_title, $notif_msg, 'student_appointments.php');
        }
    }
}

// Handle status update
if (isset($_GET['update_status']) && isset($_GET['id'])) {
    $appointment_id = intval($_GET['id']);
    $new_status = $_GET['update_status'];
    
    // Get appointment details for email
    $apt_stmt = mysqli_prepare($conn, "SELECT a.*, s.student_number, u.first_name, u.last_name, u.email 
                                       FROM appointments a 
                                       JOIN students s ON a.student_id = s.id 
                                       JOIN users u ON s.user_id = u.id 
                                       WHERE a.id = ?");
    mysqli_stmt_bind_param($apt_stmt, "i", $appointment_id);
    mysqli_stmt_execute($apt_stmt);
    $apt_result = mysqli_stmt_get_result($apt_stmt);
    $appointment = mysqli_fetch_assoc($apt_result);
    
    if ($appointment) {
        $student_name = $appointment['first_name'] . ' ' . $appointment['last_name'];
        
        // Update appointment status
        $stmt = mysqli_prepare($conn, "UPDATE appointments SET status = ?, nurse_id = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sii", $new_status, $nurse_id, $appointment_id);
        
        if (mysqli_stmt_execute($stmt)) {
            
            if ($new_status == 'confirmed') {
                createNotification($appointment['student_id'], 'appointment', 'Appointment Confirmed', "Your appointment on " . date('F d, Y', strtotime($appointment['appointment_date'])) . " has been confirmed.", 'student_appointments.php');
            } elseif ($new_status == 'cancelled') {
                createNotification($appointment['student_id'], 'appointment', 'Appointment Cancelled', "Your appointment on " . date('F d, Y', strtotime($appointment['appointment_date'])) . " has been cancelled.", 'student_appointments.php');
            } elseif ($new_status == 'completed') {
                createNotification($appointment['student_id'], 'appointment', 'Appointment Completed', "Your appointment on " . date('F d, Y', strtotime($appointment['appointment_date'])) . " has been completed.", 'student_appointments.php');
                
                // Create visit record
                $visit_stmt = mysqli_prepare($conn, "INSERT INTO visits (student_id, nurse_id, symptoms, diagnosis, status, visit_date) VALUES (?, ?, ?, ?, 'completed', NOW())");
                $diagnosis = "Appointment: " . ($appointment['purpose'] ?? 'Consultation');
                mysqli_stmt_bind_param($visit_stmt, "iiss", $appointment['student_id'], $nurse_id, $appointment['symptoms'], $diagnosis);
                mysqli_stmt_execute($visit_stmt);
            }
            
            // Redirect back with filters
            header('Location: nurse_appointments.php?status=' . urlencode($status_filter) . '&search=' . urlencode($search));
            exit();
        }
    }
}

// Handle Medical Certificate Generation
if (isset($_GET['generate_medcert']) && isset($_GET['id'])) {
    // ... keep existing code ...
}

// Get all appointments
$query = "SELECT a.*, s.student_number, s.course, s.year_level, u.first_name, u.last_name, u.email 
          FROM appointments a 
          JOIN students s ON a.student_id = s.id 
          JOIN users u ON s.user_id = u.id 
          WHERE 1=1";
$params = [];
$types = "";

if ($status_filter != 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR s.student_number LIKE ?)";
    $sp = "%$search%";
    $params[] = $sp; $params[] = $sp; $params[] = $sp;
    $types .= "sss";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$appointments = [];
while ($row = mysqli_fetch_assoc($result)) $appointments[] = $row;

$current_page = basename($_SERVER['PHP_SELF']);

// Get counts
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM appointments WHERE status = 'pending'"))['c'] ?? 0;
$confirmed_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM appointments WHERE status = 'confirmed'"))['c'] ?? 0;
$completed_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM appointments WHERE status = 'completed'"))['c'] ?? 0;
?>
<!-- Rest of HTML remains the same -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Appointments - PUPBC Carelink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: {
                    primary: { DEFAULT: '#800020', foreground: '#ffffff' },
                    accent: { DEFAULT: '#c9a84c' },
                },
                backgroundImage: {
                    'gradient-primary': 'linear-gradient(135deg, #800020, #600018)',
                },
            }}
        }
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f8fafc; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        
        .sidebar { transition: transform 0.3s ease-in-out; }
        .sidebar-hidden { transform: translateX(-100%); }
        .sidebar-visible { transform: translateX(0); }
        .sidebar-overlay { transition: opacity 0.3s ease-in-out; }
        
        .nav-active { background-color: #c9a84c; color: #800020; }
        .nav-inactive { color: white; }
        .nav-inactive:hover { background-color: #600018; }
        
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 z-50 bg-[#800020] border-r border-[#600018]">
    <div class="flex h-16 items-center gap-3 px-4 border-b border-[#600018]">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        </div>
        <div><div class="font-bold text-white leading-none">PUPBC Carelink</div><div class="text-[9px] text-white/70 uppercase tracking-widest mt-0.5">Health Information System</div></div>
    </div>
    <div class="flex items-center gap-3 px-4 py-4 border-b border-[#600018] bg-[#600018]">
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#c9a84c] text-[#800020] font-bold"><?php echo $initials; ?></div>
        <div><div class="text-sm font-semibold text-white"><?php echo htmlspecialchars($nurse_name); ?></div><div class="text-xs text-[#c9a84c]"><?php echo htmlspecialchars($nurse_position); ?></div></div>
    </div>
    <div class="flex-1 overflow-y-auto">
        <nav class="space-y-1 px-3 py-4">
            <a href="nurse_dashboard.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg> Dashboard</a>
            <a href="nurse_queue.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Queue</a>
            <a href="nurse_patients.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Patients</a>
            <a href="nurse_appointments.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium bg-[#c9a84c] text-[#800020] transition-smooth"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Appointments</a>
            <a href="nurse_announcements.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> Announcements</a>
            <a href="nurse_inventory.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/></svg> Inventory</a>
            <a href="nurse_settings.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/></svg> Settings</a>
        </nav>
    </div>
</aside>

<!-- Main Content -->
<main class="md:pl-64 min-h-screen pb-20 md:pb-0">
    <div class="p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto">
            
            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 rounded-xl bg-[#800020]/10 flex items-center justify-center">
                    <i class="fas fa-calendar-alt text-2xl text-[#800020]"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Appointments</h1>
                    <p class="text-sm text-gray-500">Manage student appointment requests</p>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <a href="?status=all" class="bg-white rounded-xl p-3 text-center border border-gray-100 shadow-sm hover:shadow-md transition">
                    <p class="text-2xl font-bold text-gray-800"><?php echo $pending_count + $confirmed_count + $completed_count; ?></p>
                    <p class="text-xs text-gray-500">Total</p>
                </a>
                <a href="?status=pending" class="bg-yellow-50 rounded-xl p-3 text-center border border-yellow-100 hover:shadow-md transition">
                    <p class="text-2xl font-bold text-yellow-700"><?php echo $pending_count; ?></p>
                    <p class="text-xs text-yellow-600">Pending</p>
                </a>
                <a href="?status=confirmed" class="bg-green-50 rounded-xl p-3 text-center border border-green-100 hover:shadow-md transition">
                    <p class="text-2xl font-bold text-green-700"><?php echo $confirmed_count; ?></p>
                    <p class="text-xs text-green-600">Confirmed</p>
                </a>
                <a href="?status=completed" class="bg-blue-50 rounded-xl p-3 text-center border border-blue-100 hover:shadow-md transition">
                    <p class="text-2xl font-bold text-blue-700"><?php echo $completed_count; ?></p>
                    <p class="text-xs text-blue-600">Completed</p>
                </a>
            </div>
            
            <!-- Search & Filter Bar -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 mb-6">
                <form method="GET" class="flex flex-wrap items-center gap-3">
                    <div class="relative flex-1 min-w-[200px]">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or student number..." class="w-full pl-10 pr-4 py-2 text-sm rounded-lg border border-gray-200 focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                    </div>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <button type="submit" class="px-4 py-2 bg-[#800020] text-white rounded-lg text-sm font-medium hover:bg-[#600018] transition">Search</button>
                    <?php if (!empty($search) || $status_filter != 'all'): ?>
                        <a href="nurse_appointments.php" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Appointments Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase border-b">
                            <tr>
                                <th class="px-5 py-3 text-left">Student</th>
                                <th class="px-5 py-3 text-left">Student No.</th>
                                <th class="px-5 py-3 text-left">Date & Time</th>
                                <th class="px-5 py-3 text-left">Purpose</th>
                                <th class="px-5 py-3 text-left">Status</th>
                                <th class="px-5 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($appointments)): ?>
                                <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">
                                    <i class="fas fa-calendar-times text-4xl text-gray-300 mb-2 block"></i>
                                    No appointments found.
                                </td>
                                </table>
                            <?php else: ?>
                                <?php foreach ($appointments as $apt): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($apt['course']); ?> - <?php echo htmlspecialchars($apt['year_level']); ?></div>
                                    </td>
                                    <td class="px-5 py-3 font-mono text-xs"><?php echo htmlspecialchars($apt['student_number']); ?></td>
                                    <td class="px-5 py-3">
                                        <div><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium 
                                            <?php echo $apt['purpose'] == 'Medical Certificate' ? 'bg-purple-100 text-purple-700' : 
                                                ($apt['purpose'] == 'Consultation' ? 'bg-blue-100 text-blue-700' : 
                                                ($apt['purpose'] == 'Follow-up' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700')); ?>">
                                            <?php echo htmlspecialchars($apt['purpose'] ?? 'Consultation'); ?>
                                        </span>
                                        <?php if (!empty($apt['symptoms'])): ?>
                                            <div class="text-xs text-gray-400 mt-1 truncate max-w-[150px]"><?php echo htmlspecialchars(substr($apt['symptoms'], 0, 50)); ?></div>
                                        <?php endif; ?>
                                     </td>
                                    <td class="px-5 py-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium 
                                            <?php echo $apt['status'] == 'confirmed' ? 'bg-green-100 text-green-700' : 
                                                ($apt['status'] == 'pending' ? 'bg-yellow-100 text-yellow-700' : 
                                                ($apt['status'] == 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700')); ?>">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
                                     </td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <?php if ($apt['status'] == 'pending'): ?>
                                                <a href="?update_status=confirmed&id=<?php echo $apt['id']; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                                   class="text-green-600 hover:text-green-800 text-xs font-medium bg-green-50 px-2 py-1 rounded">
                                                    <i class="fas fa-check"></i> Confirm
                                                </a>
                                                <a href="?update_status=cancelled&id=<?php echo $apt['id']; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                                   onclick="return confirm('Cancel this appointment?')"
                                                   class="text-red-600 hover:text-red-800 text-xs font-medium bg-red-50 px-2 py-1 rounded">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                            <?php elseif ($apt['status'] == 'confirmed'): ?>
                                                <?php if ($apt['purpose'] == 'Medical Certificate'): ?>
                                                    <a href="?generate_medcert=1&id=<?php echo $apt['id']; ?>" 
                                                       class="text-purple-600 hover:text-purple-800 text-xs font-medium bg-purple-50 px-2 py-1 rounded"
                                                       target="_blank">
                                                        <i class="fas fa-file-pdf"></i> Generate Med Cert
                                                    </a>
                                                <?php else: ?>
                                                    <a href="nurse_queue.php?add_from_appointment=<?php echo $apt['id']; ?>" 
                                                       class="text-blue-600 hover:text-blue-800 text-xs font-medium bg-blue-50 px-2 py-1 rounded">
                                                        <i class="fas fa-stethoscope"></i> Start Consultation
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?update_status=completed&id=<?php echo $apt['id']; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                                   class="text-gray-600 hover:text-gray-800 text-xs font-medium bg-gray-50 px-2 py-1 rounded"
                                                   onclick="return confirm('Mark as completed?')">
                                                    <i class="fas fa-check-double"></i> Complete
                                                </a>
                                                <a href="?update_status=cancelled&id=<?php echo $apt['id']; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                                   onclick="return confirm('Cancel this appointment?')"
                                                   class="text-red-600 hover:text-red-800 text-xs font-medium bg-red-50 px-2 py-1 rounded">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                            <?php elseif ($apt['status'] == 'completed'): ?>
                                                <span class="text-green-600 text-xs">Done</span>
                                                <?php if ($apt['purpose'] == 'Medical Certificate'): ?>
                                                    <a href="?generate_medcert=1&id=<?php echo $apt['id']; ?>" 
                                                       class="text-purple-600 hover:text-purple-800 text-xs font-medium"
                                                       target="_blank">
                                                        <i class="fas fa-file-pdf"></i> Download
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">Cancelled</span>
                                            <?php endif; ?>
                                        </div>
                                     </td>
                                 </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Info Box -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                    <div class="text-sm text-blue-800">
                        <strong>How to handle appointments:</strong><br>
                        • <strong>Medical Certificate:</strong> Click "Generate Med Cert" to create and download a medical certificate.<br>
                        • <strong>Consultation:</strong> Click "Start Consultation" to add the patient to the queue and begin the consultation.<br>
                        • <strong>Other purposes:</strong> Click "Complete" to mark as done after serving the student.<br>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Mobile Bottom Navigation -->
<div class="fixed bottom-0 left-0 right-0 bg-[#800020] md:hidden z-50 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)]">
    <div class="flex justify-around py-2">
        <a href="nurse_dashboard.php" class="flex flex-col items-center text-[10px] px-1 text-white/70"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Home</a>
        <a href="nurse_queue.php" class="flex flex-col items-center text-[10px] px-1 text-white/70"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>Queue</a>
        <a href="nurse_patients.php" class="flex flex-col items-center text-[10px] px-1 text-white/70"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>Patients</a>
        <a href="nurse_appointments.php" class="flex flex-col items-center text-[10px] px-1 <?php echo $current_page=='nurse_appointments.php'?'text-[#c9a84c]':'text-white/70'; ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Appts</a>
    </div>
</div>

</body>
</html>