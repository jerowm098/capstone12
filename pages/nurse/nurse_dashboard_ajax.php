<?php
// capstonemain/pages/nurse/nurse_dashboard_ajax.php
session_start();
if (!isset($_SESSION['nurse_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
require_once '../../config/db_connect.php';

$nurse_id = $_SESSION['nurse_id'];

// Get nurse info
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, n.position 
                      FROM users u JOIN nurses n ON u.id = n.user_id WHERE n.id = ?");
mysqli_stmt_bind_param($stmt, "i", $nurse_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$nurse = mysqli_fetch_assoc($result);
$nurse_name = ($nurse['first_name'] ?? 'Nurse') . ' ' . ($nurse['last_name'] ?? '');
$nurse_position = $nurse['position'] ?? 'Staff Nurse';
$initials = strtoupper(substr($nurse['first_name'] ?? 'N', 0, 1) . substr($nurse['last_name'] ?? '', 0, 1));

// Stats
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

echo json_encode([
    'success' => true,
    'nurse_name' => $nurse_name,
    'nurse_position' => $nurse_position,
    'nurse_initials' => $initials,
    'stats' => [
        'waiting' => $waiting_count,
        'today_patients' => $today_patients,
        'percent_change' => $percent_change,
        'completed_today' => $completed_today,
        'avg_wait' => $avg_wait
    ],
    'visit_trends' => $visit_trends,
    'top_diagnoses' => $top_diag,
    'live_queue' => $live_queue
]);
?>