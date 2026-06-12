<?php
// capstone1/pages/nurse/nurse_dashboard.php
session_start();

if (!isset($_SESSION['nurse_id'])) {
    header('Location: nurse_login.php');
    exit();
}

require_once '../../config/db_connect.php';

$nurse_id = $_SESSION['nurse_id'];
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, n.position, n.license_number
                      FROM users u JOIN nurses n ON u.id = n.user_id WHERE n.id = ?");
mysqli_stmt_bind_param($stmt, "i", $nurse_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$nurse = mysqli_fetch_assoc($result);

$nurse_name = ($nurse['first_name'] ?? 'Nurse') . ' ' . ($nurse['last_name'] ?? '');
$first_name = $nurse['first_name'] ?? 'Nurse';
$nurse_position = $nurse['position'] ?? 'Staff Nurse';
$initials = strtoupper(substr($nurse['first_name'] ?? 'N', 0, 1) . substr($nurse['last_name'] ?? '', 0, 1));

// Stats queries
$waiting_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM visits WHERE status='waiting' AND DATE(visit_date)=CURDATE()");
mysqli_stmt_execute($waiting_stmt);
$waiting_count = mysqli_fetch_assoc(mysqli_stmt_get_result($waiting_stmt))['c'] ?? 0;

$today_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM visits WHERE DATE(visit_date)=CURDATE()");
mysqli_stmt_execute($today_stmt);
$today_patients = mysqli_fetch_assoc(mysqli_stmt_get_result($today_stmt))['c'] ?? 0;

$yesterday_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM visits WHERE DATE(visit_date)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
mysqli_stmt_execute($yesterday_stmt);
$yesterday = mysqli_fetch_assoc(mysqli_stmt_get_result($yesterday_stmt))['c'] ?? 0;
$percent_change = $yesterday > 0 ? round((($today_patients - $yesterday) / $yesterday) * 100) : 0;

$completed_today_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM visits WHERE status='completed' AND DATE(visit_date)=CURDATE()");
mysqli_stmt_execute($completed_today_stmt);
$completed_today = mysqli_fetch_assoc(mysqli_stmt_get_result($completed_today_stmt))['c'] ?? 0;

$avg_stmt = mysqli_prepare($conn, "SELECT AVG(TIMESTAMPDIFF(MINUTE,created_at,updated_at)) as a FROM visits WHERE status='completed' AND updated_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)");
mysqli_stmt_execute($avg_stmt);
$avg = mysqli_fetch_assoc(mysqli_stmt_get_result($avg_stmt))['a'] ?? 0;
$avg_wait = $avg ? round($avg).'m' : 'N/A';

// Visit trends
$visit_trends = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $trend_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM visits WHERE DATE(visit_date)=?");
    mysqli_stmt_bind_param($trend_stmt, "s", $d);
    mysqli_stmt_execute($trend_stmt);
    $trend_result = mysqli_stmt_get_result($trend_stmt);
    $count = mysqli_fetch_assoc($trend_result)['c'] ?? 0;
    $visit_trends[] = ['day' => date('D', strtotime("-$i days")), 'visits' => (int)$count];
}

// Top diagnoses
$top_diag = [];
$diag_stmt = mysqli_prepare($conn, "SELECT diagnosis, COUNT(*) as c FROM visits WHERE MONTH(visit_date)=MONTH(CURDATE()) AND diagnosis IS NOT NULL AND diagnosis!='' GROUP BY diagnosis ORDER BY c DESC LIMIT 5");
mysqli_stmt_execute($diag_stmt);
$diag_result = mysqli_stmt_get_result($diag_stmt);
while ($row = mysqli_fetch_assoc($diag_result)) $top_diag[] = $row;

// Live queue
$live_queue = [];
$queue_stmt = mysqli_prepare($conn, "SELECT v.id, s.student_number, u.first_name, u.last_name, s.course, v.symptoms as reason,
    CASE WHEN v.symptoms LIKE '%emergency%' OR v.symptoms LIKE '%severe%' THEN 'emergency'
         WHEN v.symptoms LIKE '%pain%' OR v.symptoms LIKE '%fever%' THEN 'priority' ELSE 'normal' END as priority
    FROM visits v JOIN students s ON v.student_id=s.id JOIN users u ON s.user_id=u.id
    WHERE v.status='waiting' AND DATE(v.visit_date)=CURDATE()
    ORDER BY FIELD(priority,'emergency','priority','normal'), v.created_at ASC LIMIT 5");
mysqli_stmt_execute($queue_stmt);
$queue_result = mysqli_stmt_get_result($queue_stmt);
while ($row = mysqli_fetch_assoc($queue_result)) $live_queue[] = $row;

function prioClass($p) { 
    return $p=='emergency' ? 'bg-red-100 text-red-700' : ($p=='priority' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600'); 
}
function prioLabel($p) { 
    return $p=='emergency' ? 'Emergency' : ($p=='priority' ? 'Priority' : 'Normal'); 
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Dashboard - PUPBC Carelink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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

<!-- Desktop Sidebar with PUP Clinic Logo -->
<aside class="hidden md:block fixed top-0 left-0 h-full w-64 bg-[#800020] shadow-xl overflow-y-auto z-30">
    <div class="flex items-center gap-3 p-4 border-b border-[#600018] bg-[#600018]">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-white/20">
            <img src="../../assets/images/puplogo.png" alt="PUP Logo" class="h-8 w-8 object-contain">
        </div>
        <div>
            <div class="font-bold text-white text-sm leading-tight">PUPBC <span class="text-[#c9a84c]">Carelink</span></div>
            <p class="text-[9px] text-white/60 uppercase tracking-wider">Health Information System</p>
        </div>
    </div>
    
    <div class="flex items-center gap-3 p-4 border-b border-[#600018] bg-[#800020]">
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
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="text-sm text-gray-500">Welcome back, <?php echo htmlspecialchars($first_name); ?>!</p>
            </div>
        </div>
    </div>
    
    <div class="p-4 md:p-6">
        <div class="space-y-6 animate-fade-in max-w-7xl mx-auto">
            
            <!-- Stats Cards (no welcome card) -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl p-4 shadow-sm border stat-card transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500">In Queue</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $waiting_count; ?></p>
                            <p class="text-xs text-gray-400">Waiting now</p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-yellow-50 flex items-center justify-center"><i class="fas fa-clock text-yellow-500 text-lg"></i></div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-4 shadow-sm border stat-card transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500">Patients Today</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $today_patients; ?></p>
                            <p class="text-xs <?php echo $percent_change >= 0 ? 'text-green-500' : 'text-red-500'; ?>">
                                <?php echo $percent_change >= 0 ? '+' : ''; ?><?php echo $percent_change; ?>% vs yesterday
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center"><i class="fas fa-users text-red-500 text-lg"></i></div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-4 shadow-sm border stat-card transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500">Avg Wait Time</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $avg_wait; ?></p>
                            <p class="text-xs text-gray-400">Last 24 hours</p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center"><i class="fas fa-hourglass-half text-green-500 text-lg"></i></div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-4 shadow-sm border stat-card transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500">Completed Today</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $completed_today; ?></p>
                            <p class="text-xs text-gray-400">Consultations done</p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center"><i class="fas fa-check-circle text-blue-500 text-lg"></i></div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="bg-white rounded-xl shadow-sm border overflow-hidden lg:col-span-2">
                    <div class="px-5 py-4 border-b">
                        <h3 class="font-semibold text-gray-900">Visits This Week</h3>
                        <p class="text-xs text-gray-500">Total consultations per day</p>
                    </div>
                    <div class="p-5">
                        <canvas id="visitChart" height="200"></canvas>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                    <div class="px-5 py-4 border-b">
                        <h3 class="font-semibold text-gray-900">Top Diagnoses</h3>
                        <p class="text-xs text-gray-500">This month</p>
                    </div>
                    <div class="p-5">
                        <canvas id="diagChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Live Queue Section -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="px-5 py-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900"><i class="fas fa-users text-[#800020] mr-2"></i> Live Queue</h3>
                    <a href="nurse_queue.php" class="text-xs text-[#800020] hover:underline">View all →</a>
                </div>
                <div class="p-5 space-y-3">
                    <?php if(empty($live_queue)): ?>
                        <div class="text-center text-gray-400 py-6">
                            <i class="fas fa-check-circle text-3xl mb-2 block"></i>
                            <p class="text-sm">No patients in queue</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($live_queue as $q): 
                            $ticket = explode('-', $q['student_number']); 
                            $num = end($ticket); 
                        ?>
                            <div class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 hover:border-[#800020]/30 transition-smooth">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-primary text-white text-xs font-bold"><?php echo $num; ?></div>
                                <div class="flex-1">
                                    <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($q['first_name'] . ' ' . $q['last_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($q['course']); ?> • <?php echo htmlspecialchars(substr($q['reason'], 0, 40)); ?></div>
                                </div>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium <?php echo prioClass($q['priority']); ?>">
                                    <?php echo prioLabel($q['priority']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
            <i class="fas fa-calendar-alt text-xl"></i><span class="text-[10px] mt-1">Appointments</span>
        </a>
        <a href="nurse_announcements.php" class="flex flex-col items-center py-1 px-2 <?php echo $current_page == 'nurse_announcements.php' ? 'text-[#800020]' : 'text-gray-400'; ?>">
            <i class="fas fa-bullhorn text-xl"></i><span class="text-[10px] mt-1">News</span>
        </a>
    </div>
</div>

<script>
    // Visit Trends Chart
    const vd = <?php echo json_encode($visit_trends); ?>;
    new Chart(document.getElementById('visitChart'), {
        type: 'line',
        data: {
            labels: vd.map(d => d.day),
            datasets: [{
                label: 'Visits',
                data: vd.map(d => d.visits),
                borderColor: '#c9a84c',
                backgroundColor: 'rgba(201,168,76,0.1)',
                borderWidth: 2.5,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#c9a84c',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });
    
    // Top Diagnoses Chart
    const td = <?php echo json_encode($top_diag); ?>;
    if (td.length > 0) {
        new Chart(document.getElementById('diagChart'), {
            type: 'bar',
            data: {
                labels: td.map(d => d.diagnosis.length > 15 ? d.diagnosis.substring(0, 12) + '...' : d.diagnosis),
                datasets: [{
                    label: 'Cases',
                    data: td.map(d => d.c),
                    backgroundColor: '#800020',
                    borderRadius: 6,
                    barPercentage: 0.7
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false } } }
            }
        });
    }
</script>

</body>
</html>