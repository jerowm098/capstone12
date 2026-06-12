<?php
// capstonemain/pages/nurse/nurse_queue.php
session_start();
if (!isset($_SESSION['nurse_id'])) {
    header('Location: nurse_login.php');
    exit();
}
require_once '../../config/db_connect.php';

$nurse_id = $_SESSION['nurse_id'];
$message = '';
$message_type = '';

// Handle Consultation Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['consult_submit'])) {
    $visit_id = intval($_POST['visit_id'] ?? 0);
    $temperature = $_POST['temperature'] ?? '';
    $heart_rate = $_POST['heart_rate'] ?? '';
    $bp_systolic = $_POST['bp_systolic'] ?? '';
    $bp_diastolic = $_POST['bp_diastolic'] ?? '';
    $oxygen_saturation = $_POST['oxygen_saturation'] ?? '';
    $diagnosis = $_POST['diagnosis'] ?? '';
    $treatment = $_POST['treatment'] ?? '';
    $additional_notes = $_POST['additional_notes'] ?? '';
    
    $bp = $bp_systolic . '/' . $bp_diastolic;
    $notes = $diagnosis . ' | Treatment: ' . $treatment . ' | ' . $additional_notes;
    
    $stmt = mysqli_prepare($conn, 
        "UPDATE visits SET 
         vitals_temp = ?, 
         vitals_pulse = ?, 
         vitals_bp = ?, 
         oxygen_saturation = ?,
         diagnosis = ?,
         treatment = ?,
         notes = CONCAT(COALESCE(notes, ''), ' | Consultation: ', ?),
         status = 'completed',
         consultation_date = NOW(),
         nurse_id = ?
         WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ssssssssi", 
        $temperature, $heart_rate, $bp, $oxygen_saturation, 
        $diagnosis, $treatment, $notes, $nurse_id, $visit_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "Consultation completed successfully!";
        $message_type = "success";
    } else {
        $message = "Failed to save consultation.";
        $message_type = "error";
    }
}

// Handle Add Walk-in Patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_walkin'])) {
    $student_number = trim($_POST['student_number'] ?? '');
    $symptoms = trim($_POST['symptoms'] ?? '');
    
    $errors = [];
    
    if (empty($student_number)) {
        $errors[] = "Student number is required.";
    } elseif (!preg_match('/^\d{4}-\d{5}-BN-\d$/', $student_number)) {
        $errors[] = "Invalid student number format. Use: YYYY-XXXXX-BN-0";
    }
    
    if (empty($symptoms)) {
        $errors[] = "Symptoms/Reason is required.";
    }
    
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT s.id, s.student_number, u.first_name, u.last_name, s.course 
                              FROM students s 
                              JOIN users u ON s.user_id = u.id 
                              WHERE s.student_number = ?");
        mysqli_stmt_bind_param($stmt, "s", $student_number);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $student = mysqli_fetch_assoc($result);
        
        if ($student) {
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM visits WHERE student_id = ? AND status IN ('waiting', 'in-progress') AND DATE(visit_date) = CURDATE()");
            mysqli_stmt_bind_param($check_stmt, "i", $student['id']);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) > 0) {
                $errors[] = "Student already has an active visit today.";
            } else {
                $insert_stmt = mysqli_prepare($conn, "INSERT INTO visits (student_id, symptoms, status, visit_date, created_at) VALUES (?, ?, 'waiting', NOW(), NOW())");
                mysqli_stmt_bind_param($insert_stmt, "is", $student['id'], $symptoms);
                if (mysqli_stmt_execute($insert_stmt)) {
                    $message = "Walk-in patient added to queue!";
                    $message_type = "success";
                } else {
                    $errors[] = "Failed to add patient to queue.";
                }
            }
        } else {
            $errors[] = "Student number not found. Please check the number or ask student to register first.";
        }
    }
    
    if (!empty($errors)) {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

// Handle Start Consultation
if (isset($_GET['start']) && is_numeric($_GET['start'])) {
    $visit_id = intval($_GET['start']);
    $stmt = mysqli_prepare($conn, "UPDATE visits SET status = 'in-progress', nurse_id = ? WHERE id = ? AND status = 'waiting'");
    mysqli_stmt_bind_param($stmt, "ii", $nurse_id, $visit_id);
    if (mysqli_stmt_execute($stmt)) {
        $message = "Consultation started!";
        $message_type = "success";
        header('Location: nurse_queue.php?consult=' . $visit_id);
        exit();
    }
}

// Handle Complete Consultation
if (isset($_GET['complete']) && is_numeric($_GET['complete'])) {
    $visit_id = intval($_GET['complete']);
    $stmt = mysqli_prepare($conn, "UPDATE visits SET status = 'completed', consultation_date = NOW(), nurse_id = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $nurse_id, $visit_id);
    if (mysqli_stmt_execute($stmt)) {
        $message = "Visit marked as completed!";
        $message_type = "success";
    }
}

// Handle Remove/Cancel from queue
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $visit_id = intval($_GET['remove']);
    $stmt = mysqli_prepare($conn, "UPDATE visits SET status = 'cancelled', notes = CONCAT(COALESCE(notes, ''), ' | Removed from queue by nurse') WHERE id = ? AND status = 'waiting'");
    mysqli_stmt_bind_param($stmt, "i", $visit_id);
    if (mysqli_stmt_execute($stmt)) {
        $message = "Patient removed from queue!";
        $message_type = "success";
    }
}

// Get nurse info
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, n.position FROM users u JOIN nurses n ON u.id = n.user_id WHERE n.id = ?");
mysqli_stmt_bind_param($stmt, "i", $nurse_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$nurse = mysqli_fetch_assoc($result);
$first_name = $nurse['first_name'] ?? 'Nurse';
$nurse_name = $first_name . ' ' . ($nurse['last_name'] ?? '');
$nurse_position = $nurse['position'] ?? 'Head Nurse';
$initials = strtoupper(substr($nurse['first_name'] ?? 'N', 0, 1) . substr($nurse['last_name'] ?? '', 0, 1));

// Get queue with filters
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$query = "SELECT v.*, u.first_name, u.last_name, s.student_number, s.course, s.year_level 
          FROM visits v 
          JOIN students s ON v.student_id = s.id 
          JOIN users u ON s.user_id = u.id 
          WHERE 1=1";
$types = "";
$params = [];

if ($filter !== 'all') {
    $query .= " AND v.status = ?";
    $types .= "s";
    $params[] = $filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR s.student_number LIKE ?)";
    $types .= "sss";
    $sp = "%$search%";
    $params[] = $sp; $params[] = $sp; $params[] = $sp;
}

$query .= " AND DATE(v.visit_date) = CURDATE() ORDER BY 
    CASE WHEN v.status = 'waiting' THEN 1 WHEN v.status = 'in-progress' THEN 2 ELSE 3 END,
    v.created_at ASC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$queue = [];
while ($row = mysqli_fetch_assoc($result)) $queue[] = $row;

// Counts
$counts = ['all' => 0, 'waiting' => 0, 'in-progress' => 0, 'completed' => 0];
$cr = mysqli_query($conn, "SELECT status, COUNT(*) as c FROM visits WHERE DATE(visit_date) = CURDATE() GROUP BY status");
while ($row = mysqli_fetch_assoc($cr)) {
    $counts[$row['status']] = $row['c'];
    $counts['all'] += $row['c'];
}

$current_consult = isset($_GET['consult']) ? intval($_GET['consult']) : null;

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Queue - PUPBC Carelink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: { primary: { DEFAULT: '#800020' }, accent: { DEFAULT: '#c9a84c' } },
                backgroundImage: { 'gradient-primary': 'linear-gradient(135deg, #800020, #600018)', 'gradient-hero': 'linear-gradient(135deg, #800020 0%, #4a0010 40%, #800020 100%)' },
                boxShadow: { 'soft': '0 2px 15px -3px rgba(0,0,0,0.07)', 'card': '0 1px 3px 0 rgba(0,0,0,0.1)' },
            }}
        }
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        .animate-fade-in { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .transition-smooth { transition: all 0.3s ease; }
        body { background-color: #f8fafc; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
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
            <div class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($nurse_name); ?></div>
            <div class="text-xs text-[#c9a84c] truncate"><?php echo htmlspecialchars($nurse_position); ?></div>
        </div>
    </div>
    
    <nav class="py-4">
        <div class="px-3 mb-2"><p class="text-[10px] font-semibold uppercase tracking-wider text-[#c9a84c] px-3">Main Menu</p></div>
        
        <a href="nurse_dashboard.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-home w-5"></i><span class="text-sm font-medium">Dashboard</span>
        </a>
        <a href="nurse_queue.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'nurse_queue.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
            <i class="fas fa-users w-5"></i><span class="text-sm font-medium">Queue</span>
        </a>
        <a href="nurse_patients.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-user-injured w-5"></i><span class="text-sm font-medium">Patients</span>
        </a>
        <a href="nurse_appointments.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-calendar-alt w-5"></i><span class="text-sm font-medium">Appointments</span>
        </a>
        <a href="nurse_announcements.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-bullhorn w-5"></i><span class="text-sm font-medium">Announcements</span>
        </a>
        <a href="nurse_inventory.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'nurse_inventory.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
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

<!-- Main Content -->
<main class="md:ml-64 min-h-screen pb-20 md:pb-6">
    
    <!-- Header -->
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200">
        <div class="px-4 md:px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Queue Management</h1>
                <p class="text-sm text-gray-500">Manage patient queue and consultations</p>
            </div>
            
            <!-- Notification Bell (placeholder) -->
            <div class="relative">
                <button class="relative focus:outline-none p-2 hover:bg-gray-100 rounded-full transition">
                    <i class="fas fa-bell text-gray-600 text-xl"></i>
                </button>
            </div>
        </div>
    </div>
    
    <div class="p-4 md:p-6">
        <div class="space-y-6 animate-fade-in max-w-6xl mx-auto">
            
            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="rounded-lg p-4 text-sm <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
                    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Search and Add Walk-in -->
            <div class="bg-white rounded-xl border shadow-card p-4">
                <form method="GET" class="flex flex-wrap items-center gap-3">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <div class="relative flex-1 min-w-[250px]">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by name or student ID" 
                               class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-10 pr-4 text-sm outline-none focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="openWalkinModal()" class="inline-flex items-center gap-2 rounded-lg bg-[#800020] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#600018] transition-smooth">
                            <i class="fas fa-plus"></i> Add Walk-in
                        </button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-smooth">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>

            <!-- Queue Tabs -->
            <div class="flex gap-2 flex-wrap items-center bg-white p-1.5 rounded-lg border shadow-sm w-max">
                <?php 
                $tabs = [
                    'all' => ['label' => 'All', 'icon' => 'fa-list', 'count' => $counts['all']], 
                    'waiting' => ['label' => 'Waiting', 'icon' => 'fa-clock', 'count' => $counts['waiting']], 
                    'in-progress' => ['label' => 'In Consultation', 'icon' => 'fa-stethoscope', 'count' => $counts['in-progress']], 
                    'completed' => ['label' => 'Completed', 'icon' => 'fa-check-circle', 'count' => $counts['completed']]
                ];
                foreach ($tabs as $key => $data): 
                ?>
                    <a href="?filter=<?php echo $key; ?>&search=<?php echo urlencode($search); ?>" 
                       class="rounded-md px-4 py-1.5 text-sm font-medium transition-smooth flex items-center gap-2 <?php echo $filter === $key ? 'bg-gray-100 text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50'; ?>">
                        <i class="fas <?php echo $data['icon']; ?>"></i>
                        <?php echo $data['label']; ?>
                        <span class="inline-flex items-center justify-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-700">
                            <?php echo $data['count']; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Queue List -->
            <div class="space-y-4">
                <?php if (empty($queue)): ?>
                    <div class="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
                        <i class="fas fa-check-circle text-4xl text-gray-300 mb-3 block"></i>
                        <p>No patients in the queue today.</p>
                        <button onclick="openWalkinModal()" class="block mx-auto mt-3 text-[#800020] hover:underline">Add a walk-in patient</button>
                    </div>
                <?php else: ?>
                    <?php foreach ($queue as $q): 
                        $parts = explode('-', $q['student_number']);
                        $ticketNum = end($parts);
                        $isWaiting = ($q['status'] === 'waiting');
                        $isInProgress = ($q['status'] === 'in-progress');
                        $isCompleted = ($q['status'] === 'completed');
                        $borderColor = $isWaiting ? 'border-l-4 border-l-red-600' : ($isInProgress ? 'border-l-4 border-l-yellow-500' : 'border-l-4 border-l-green-500');
                    ?>
                        <div class="bg-white rounded-xl border <?php echo $borderColor; ?> p-5 shadow-card hover:shadow-md transition-smooth">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                                <div class="flex items-center gap-4 flex-1">
                                    <div class="flex h-[72px] w-[72px] shrink-0 flex-col items-center justify-center rounded-xl bg-gradient-primary text-white overflow-hidden shadow-inner">
                                        <div class="bg-[#600018] w-full text-center py-1 text-[10px] font-bold uppercase tracking-wider">Ticket</div>
                                        <div class="flex-1 flex items-center justify-center text-2xl font-black"><?php echo htmlspecialchars($ticketNum); ?></div>
                                    </div>
                                    
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <div class="text-lg font-bold text-gray-900 truncate"><?php echo htmlspecialchars($q['first_name'] . ' ' . $q['last_name']); ?></div>
                                            <?php if ($isInProgress): ?>
                                                <span class="inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700">Being attended</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-500 mb-2">
                                            <?php echo htmlspecialchars($q['student_number'] . ' · ' . $q['course']); ?> 
                                            <span class="text-gray-300 mx-1">|</span> 
                                            arrived <?php echo date('h:i A', strtotime($q['visit_date'])); ?>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <?php 
                                                $symptoms = explode(',', $q['symptoms'] ?? 'General Checkup');
                                                foreach(array_slice($symptoms, 0, 3) as $symp):
                                            ?>
                                            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700"><?php echo htmlspecialchars(trim($symp)); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($symptoms) > 3): ?>
                                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500">+<?php echo count($symptoms) - 3; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-end gap-2 mt-4 lg:mt-0 pt-4 lg:pt-0 border-t lg:border-t-0 border-gray-100">
                                    <?php if ($isWaiting): ?>
                                        <a href="?start=<?php echo $q['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" 
                                           class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-smooth">
                                            <i class="fas fa-play"></i> Start
                                        </a>
                                        <a href="?remove=<?php echo $q['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" 
                                           onclick="return confirm('Remove this patient from queue?')"
                                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 transition-smooth">
                                            <i class="fas fa-times"></i> Remove
                                        </a>
                                    <?php elseif ($isInProgress): ?>
                                        <a href="?consult=<?php echo $q['id']; ?>" 
                                           class="inline-flex items-center gap-1.5 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth">
                                            <i class="fas fa-notes-medical"></i> Complete
                                        </a>
                                        <a href="?complete=<?php echo $q['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" 
                                           onclick="return confirm('Mark as completed without consultation form?')"
                                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-smooth">
                                            <i class="fas fa-check"></i> Quick
                                        </a>
                                    <?php else: ?>
                                        <a href="nurse_patientdetails.php?id=<?php echo $q['student_id']; ?>" 
                                           class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-smooth">
                                            <i class="fas fa-eye"></i> View Record
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Bottom Navigation Bar (Mobile only) -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden z-40 shadow-lg safe-bottom">
    <div class="flex justify-around py-2">
        <a href="nurse_dashboard.php" class="flex flex-col items-center py-1 px-2 text-gray-400">
            <i class="fas fa-home text-xl"></i><span class="text-[10px] mt-1">Home</span>
        </a>
        <a href="nurse_queue.php" class="flex flex-col items-center py-1 px-2 text-[#800020]">
            <i class="fas fa-users text-xl"></i><span class="text-[10px] mt-1">Queue</span>
        </a>
        <a href="nurse_patients.php" class="flex flex-col items-center py-1 px-2 text-gray-400">
            <i class="fas fa-user-injured text-xl"></i><span class="text-[10px] mt-1">Patients</span>
        </a>
        <a href="nurse_appointments.php" class="flex flex-col items-center py-1 px-2 text-gray-400">
            <i class="fas fa-calendar-alt text-xl"></i><span class="text-[10px] mt-1">Appts</span>
        </a>
        <a href="nurse_settings.php" class="flex flex-col items-center py-1 px-2 text-gray-400">
            <i class="fas fa-user-cog text-xl"></i><span class="text-[10px] mt-1">Profile</span>
        </a>
    </div>
</div>

<!-- Consultation Modal -->
<div id="consultModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold text-gray-900" id="modalPatientName">Patient Name</h3>
                <p class="text-sm text-gray-500" id="modalStudentNumber">Student Number</p>
            </div>
            <button onclick="closeConsultModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
        </div>

        <form method="POST" action="" class="p-6 space-y-6">
            <input type="hidden" name="visit_id" id="consultVisitId">
            <input type="hidden" name="consult_submit" value="1">

            <div class="space-y-4">
                <h4 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-heartbeat text-[#800020]"></i> Vital Signs</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Temperature (°C)</label><input type="number" name="temperature" step="0.1" placeholder="36.5" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Heart Rate (bpm)</label><input type="number" name="heart_rate" placeholder="80" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Blood Pressure (mmHg)</label><div class="flex items-center gap-2"><input type="number" name="bp_systolic" placeholder="120" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"><span class="text-gray-500">/</span><input type="number" name="bp_diastolic" placeholder="80" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Oxygen Saturation (%)</label><input type="number" name="oxygen_saturation" placeholder="98" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></div>
                </div>
            </div>

            <div><label class="block text-sm font-medium text-gray-700 mb-1">Diagnosis</label><textarea name="diagnosis" rows="3" placeholder="Enter diagnosis..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020] resize-none"></textarea></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Treatment / Prescription</label><textarea name="treatment" rows="3" placeholder="Enter treatment plan or prescriptions..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020] resize-none"></textarea></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label><textarea name="additional_notes" rows="2" placeholder="Any additional observations..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020] resize-none"></textarea></div>

            <div class="flex gap-3 pt-4 border-t">
                <button type="button" onclick="closeConsultModal()" class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="flex-1 rounded-lg bg-[#800020] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#600018]">Save Consultation</button>
            </div>
        </form>
    </div>
</div>

<!-- Walk-in Modal -->
<div id="walkinModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl">
        <div class="border-b px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-user-plus mr-2 text-[#800020]"></i> Add Walk-in Patient</h3>
            <button onclick="closeWalkinModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Student Number *</label>
                <input type="text" name="student_number" id="walkin_student_number" required 
                       placeholder="2023-00482-BN-0"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                <p class="mt-1 text-xs text-gray-400">Format: YYYY-XXXXX-BN-0</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Symptoms / Reason *</label>
                <textarea name="symptoms" rows="3" required 
                          placeholder="Describe the patient's symptoms or reason for visit..."
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeWalkinModal()" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" name="add_walkin" class="flex-1 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018]">Add to Queue</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openConsultModal(visitId, patientName, studentNumber) {
        document.getElementById('consultModal').classList.remove('hidden');
        document.getElementById('consultModal').classList.add('flex');
        document.getElementById('modalPatientName').textContent = patientName;
        document.getElementById('modalStudentNumber').textContent = studentNumber;
        document.getElementById('consultVisitId').value = visitId;
        document.body.style.overflow = 'hidden';
    }

    function closeConsultModal() {
        document.getElementById('consultModal').classList.add('hidden');
        document.getElementById('consultModal').classList.remove('flex');
        document.body.style.overflow = '';
    }

    function openWalkinModal() {
        document.getElementById('walkinModal').classList.remove('hidden');
        document.getElementById('walkinModal').classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeWalkinModal() {
        document.getElementById('walkinModal').classList.add('hidden');
        document.getElementById('walkinModal').classList.remove('flex');
        document.body.style.overflow = '';
    }

    // Student number formatting
    const walkinInput = document.getElementById('walkin_student_number');
    if (walkinInput) {
        let isFormatting = false;
        walkinInput.addEventListener('input', function(e) {
            if (isFormatting) return;
            isFormatting = true;
            let numbers = this.value.replace(/[^0-9]/g, '');
            if (numbers.length > 9) numbers = numbers.substring(0, 9);
            let formatted = '';
            if (numbers.length >= 1) formatted += numbers.substring(0, Math.min(4, numbers.length));
            if (numbers.length >= 5) formatted += '-' + numbers.substring(4, Math.min(9, numbers.length));
            if (numbers.length >= 9) formatted += '-BN-0';
            this.value = formatted;
            isFormatting = false;
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeConsultModal();
            closeWalkinModal();
        }
    });

    <?php if ($current_consult): ?>
        <?php 
        $consult_patient = null;
        foreach ($queue as $q) {
            if ($q['id'] == $current_consult) {
                $consult_patient = $q;
                break;
            }
        }
        if ($consult_patient): 
        ?>
        openConsultModal(<?php echo $consult_patient['id']; ?>, '<?php echo htmlspecialchars($consult_patient['first_name'] . ' ' . $consult_patient['last_name']); ?>', '<?php echo htmlspecialchars($consult_patient['student_number']); ?>');
        <?php endif; ?>
    <?php endif; ?>
</script>

</body>
</html>