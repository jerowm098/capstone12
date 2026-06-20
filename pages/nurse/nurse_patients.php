<?php
// capstone1/pages/nurse/nurse_patients.php
session_start();
if (!isset($_SESSION['nurse_id'])) {
    header('Location: nurse_login.php');
    exit();
}
require_once '../../config/db_connect.php';

$nurse_id = $_SESSION['nurse_id'];
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, n.position FROM users u JOIN nurses n ON u.id = n.user_id WHERE n.id = ?");
mysqli_stmt_bind_param($stmt, "i", $nurse_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$nurse = mysqli_fetch_assoc($result);
$first_name = $nurse['first_name'] ?? 'Nurse';
$nurse_name = $first_name . ' ' . ($nurse['last_name'] ?? '');
$nurse_position = $nurse['position'] ?? 'Staff Nurse';
$initials = strtoupper(substr($nurse['first_name'] ?? 'N', 0, 1) . substr($nurse['last_name'] ?? '', 0, 1));

// Get filter values
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course_filter = isset($_GET['course']) ? $_GET['course'] : '';
$year_filter = isset($_GET['year']) ? $_GET['year'] : '';

// Get distinct courses for dropdown
$courses = [];
$course_result = mysqli_query($conn, "SELECT DISTINCT course FROM students ORDER BY course ASC");
while ($row = mysqli_fetch_assoc($course_result)) {
    $courses[] = $row['course'];
}

// Build query with filters
$query = "SELECT s.id, s.student_number, u.first_name, u.last_name, s.course, s.year_level, s.blood_type, s.allergies,
          (SELECT COUNT(*) FROM visits WHERE student_id = s.id) as visit_count,
          (SELECT MAX(visit_date) FROM visits WHERE student_id = s.id) as last_visit
          FROM students s 
          JOIN users u ON s.user_id = u.id 
          WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR s.student_number LIKE ?)";
    $sp = "%$search%";
    $params[] = $sp; $params[] = $sp; $params[] = $sp;
    $types .= "sss";
}

if (!empty($course_filter)) {
    $query .= " AND s.course = ?";
    $params[] = $course_filter;
    $types .= "s";
}

if (!empty($year_filter)) {
    $query .= " AND s.year_level = ?";
    $params[] = $year_filter;
    $types .= "s";
}

$query .= " ORDER BY u.last_name ASC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$patients = [];
while ($row = mysqli_fetch_assoc($result)) $patients[] = $row;

// Get stats
$total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students"))['c'] ?? 0;
$total_year_levels = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT year_level) as c FROM students"))['c'] ?? 0;
$active_visits = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) as c FROM visits WHERE DATE(visit_date) = CURDATE()"))['c'] ?? 0;
$with_allergies = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM students WHERE allergies IS NOT NULL AND allergies != ''"))['c'] ?? 0;

$current_page = basename($_SERVER['PHP_SELF']);
$year_levels = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Patients - PUPBC Carelink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: { primary: { DEFAULT: '#800020' }, accent: { DEFAULT: '#c9a84c' } },
                backgroundImage: { 'gradient-primary': 'linear-gradient(135deg, #800020, #600018)' },
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
        
        <a href="nurse_dashboard.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'nurse_dashboard.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
            <i class="fas fa-home w-5"></i><span class="text-sm font-medium">Dashboard</span>
        </a>
        <a href="nurse_queue.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'nurse_queue.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
            <i class="fas fa-users w-5"></i><span class="text-sm font-medium">Queue</span>
        </a>
        <a href="nurse_patients.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'nurse_patients.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
            <i class="fas fa-user-injured w-5"></i><span class="text-sm font-medium">Patients</span>
        </a>
        <a href="nurse_appointments.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'nurse_appointments.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
            <i class="fas fa-calendar-alt w-5"></i><span class="text-sm font-medium">Appointments</span>
        </a>
        <a href="nurse_announcements.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'nurse_announcements.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
            <i class="fas fa-bullhorn w-5"></i><span class="text-sm font-medium">Announcements</span>
        </a>
        <a href="nurse_inventory.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'nurse_inventory.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
            <i class="fas fa-pills w-5"></i><span class="text-sm font-medium">Inventory</span>
        </a>
        <a href="nurse_settings.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'nurse_settings.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
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
        <div class="px-4 md:px-6 py-4">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Patients</h1>
                <p class="text-sm text-gray-500">Manage student patient records</p>
            </div>
        </div>
    </div>
    
    <div class="p-4 md:p-6">
        <div class="space-y-6 animate-fade-in max-w-7xl mx-auto">
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl p-4 shadow-sm border stat-card transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500">Total Students</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_students); ?></p>
                            <p class="text-xs text-gray-400">Enrolled</p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center"><i class="fas fa-users text-blue-500 text-lg"></i></div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-4 shadow-sm border stat-card transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500">Year Levels</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_year_levels); ?></p>
                            <p class="text-xs text-gray-400">Active levels</p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center"><i class="fas fa-layer-group text-green-500 text-lg"></i></div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-4 shadow-sm border stat-card transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500">Active Today</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($active_visits); ?></p>
                            <p class="text-xs text-gray-400">With visits</p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-yellow-50 flex items-center justify-center"><i class="fas fa-activity text-yellow-500 text-lg"></i></div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-4 shadow-sm border stat-card transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500">With Allergies</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($with_allergies); ?></p>
                            <p class="text-xs text-gray-400">Need attention</p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center"><i class="fas fa-exclamation-triangle text-red-500 text-lg"></i></div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <form method="GET" class="bg-white rounded-xl border shadow-sm p-4" id="filterForm">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative flex-1 min-w-[200px]">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by name or student number..." 
                               class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-10 pr-4 text-sm outline-none focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                    </div>
                    
                    <select name="course" id="courseSelect" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm outline-none focus:border-[#800020]">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $course_filter == $c ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="year" id="yearSelect" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm outline-none focus:border-[#800020]">
                        <option value="">All Year Levels</option>
                        <?php foreach ($year_levels as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year_filter == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-[#800020] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#600018] transition-smooth">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <?php if (!empty($search) || !empty($course_filter) || !empty($year_filter)): ?>
                        <a href="nurse_patients.php" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-smooth">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Patients Table -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase border-b">
                            <tr>
                                <th class="px-5 py-3 text-left">Student</th>
                                <th class="px-5 py-3 text-left">Student Number</th>
                                <th class="px-5 py-3 text-left">Course</th>
                                <th class="px-5 py-3 text-left">Year Level</th>
                                <th class="px-5 py-3 text-left">Blood Type</th>
                                <th class="px-5 py-3 text-left">Last Visit</th>
                                <th class="px-5 py-3 text-center">Visits</th>
                                <th class="px-5 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($patients)): ?>
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-gray-500">
                                        <i class="fas fa-users-slash text-4xl text-gray-300 mb-2 block"></i>
                                        No patients found.
                                    </td>
                                </tr>
                            <?php else: foreach ($patients as $p): 
                                $pinitials = strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'], 0, 1));
                                $lastVisit = $p['last_visit'] ? date('M d, Y', strtotime($p['last_visit'])) : 'Never';
                                $hasAllergy = !empty($p['allergies']);
                            ?>
                                <tr class="hover:bg-gray-50 transition <?php echo $hasAllergy ? 'bg-red-50/30' : ''; ?>">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-primary text-white text-sm font-bold shadow-sm">
                                                <?php echo $pinitials; ?>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></div>
                                                <?php if ($hasAllergy): ?>
                                                    <div class="text-xs text-red-500"><i class="fas fa-allergies"></i> Has allergy</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 font-mono text-xs"><?php echo htmlspecialchars($p['student_number']); ?></td>
                                    <td class="px-5 py-3 text-gray-700"><?php echo htmlspecialchars($p['course']); ?></td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700">
                                            <?php echo htmlspecialchars($p['year_level']); ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex rounded-full bg-teal-50 px-2.5 py-1 text-xs font-bold text-teal-700">
                                            <?php echo htmlspecialchars($p['blood_type'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-gray-500"><?php echo $lastVisit; ?></td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full <?php echo $p['visit_count'] > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400'; ?> text-xs font-bold">
                                            <?php echo $p['visit_count']; ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <button onclick="openPatientModal(<?php echo $p['id']; ?>)" 
                                                class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200 transition-smooth">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Results Count -->
            <div class="text-center text-xs text-gray-400">
                Showing <?php echo count($patients); ?> patient(s)
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
            <i class="fas fa-calendar-alt text-xl"></i><span class="text-[10px] mt-1">Appointments</span>
        </a>
        <a href="nurse_announcements.php" class="flex flex-col items-center py-1 px-2 <?php echo $current_page == 'nurse_announcements.php' ? 'text-[#800020]' : 'text-gray-400'; ?>">
            <i class="fas fa-bullhorn text-xl"></i><span class="text-[10px] mt-1">News</span>
        </a>
    </div>
</div>

<!-- Patient Modal (Summary only - NO UPLOAD, NO DOCUMENTS) -->
<div id="patientModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[85vh] overflow-y-auto shadow-2xl m-4">
        <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="font-bold text-lg">Patient Summary</h3>
            <button onclick="closePatientModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div id="patientModalContent" class="p-6">
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#800020]"></div>
                <span class="ml-3 text-gray-500 font-medium">Loading patient details...</span>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-submit when course or year dropdown changes
    document.getElementById('courseSelect').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
    
    document.getElementById('yearSelect').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
    
    function openPatientModal(patientId) {
        document.getElementById('patientModal').classList.remove('hidden');
        document.getElementById('patientModal').classList.add('flex');
        document.body.style.overflow = 'hidden';
        fetch('nurse_patientdetails_modal.php?id=' + patientId)
            .then(r => r.text())
            .then(html => { document.getElementById('patientModalContent').innerHTML = html; })
            .catch(() => { document.getElementById('patientModalContent').innerHTML = '<p class="text-red-500 text-center py-8">Failed to load patient details.</p>'; });
    }
    
    function closePatientModal() {
        document.getElementById('patientModal').classList.add('hidden');
        document.getElementById('patientModal').classList.remove('flex');
        document.body.style.overflow = '';
    }
    
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closePatientModal(); });
    document.getElementById('patientModal').addEventListener('click', function(e) { if (e.target === this) closePatientModal(); });
</script>

</body>
</html>