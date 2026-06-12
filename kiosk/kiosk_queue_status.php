<?php
session_start();
require_once '../config/db_connect.php';

// Check if patient has queue number
if (!isset($_SESSION['patient_info']) || !isset($_SESSION['queue_number'])) {
    header('Location: kiosk_options.php');
    exit();
}

$patientInfo = $_SESSION['patient_info'];
$fullName = $patientInfo['full_name'] ?? 'Student';
$studentId = $patientInfo['student_number'] ?? 'N/A';
$course = $patientInfo['course'] ?? 'N/A';
$yearLevel = $patientInfo['year_level'] ?? 'N/A';
$email = $patientInfo['email'] ?? '';
$queueNumber = $_SESSION['queue_number'];
$triageData = $_SESSION['triage_data'] ?? [];

$today = date('Y-m-d');

// Get counts by priority
$emergency_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM visits WHERE priority='emergency' AND status='waiting' AND DATE(visit_date)='$today'"))['c'] ?? 0;
$urgent_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM visits WHERE priority='urgent' AND status='waiting' AND DATE(visit_date)='$today'"))['c'] ?? 0;
$priority_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM visits WHERE priority='priority' AND status='waiting' AND DATE(visit_date)='$today'"))['c'] ?? 0;
$normal_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM visits WHERE priority='normal' AND status='waiting' AND DATE(visit_date)='$today'"))['c'] ?? 0;

// Get position in queue
$priority = $triageData['priority'] ?? 'normal';
$position = 1;
if ($priority == 'emergency') $position = $emergency_count;
elseif ($priority == 'urgent') $position = $urgent_count;
elseif ($priority == 'priority') $position = $priority_count;
else $position = $normal_count;

$priorityColors = [
    'emergency' => ['bg' => '#dc2626', 'label' => 'EMERGENCY'],
    'urgent' => ['bg' => '#f97316', 'label' => 'URGENT'],
    'priority' => ['bg' => '#eab308', 'label' => 'PRIORITY'],
    'normal' => ['bg' => '#22c55e', 'label' => 'NORMAL']
];
$priorityColor = $priorityColors[$priority] ?? $priorityColors['normal'];
$estimatedWait = $position * 5;

// Handle new patient
if (isset($_GET['new'])) {
    session_destroy();
    session_start();
    header('Location: kiosk_options.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Queue Status - PUPBC Carelink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --maroon: #800020; --gold: #c9a84c; }
        body { background: linear-gradient(135deg, var(--maroon) 0%, #4a0010 100%); min-height: 100vh; }
        .queue-card { background: white; border-radius: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .queue-number { font-family: monospace; font-size: 4rem; font-weight: 900; letter-spacing: 0.1em; }
        @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        .pulse-animation { animation: pulse 1.5s ease-in-out infinite; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
        .delay-100 { animation-delay: 0.1s; opacity: 0; }
        .delay-200 { animation-delay: 0.2s; opacity: 0; }
        @media print { body { background: white; padding: 20px; } .no-print { display: none; } .queue-card { box-shadow: none; border: 1px solid #ddd; } }
        .progress-bar { transition: width 0.5s ease; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

<div class="flex-1 flex items-center justify-center p-4">
    <div class="w-full max-w-2xl mx-auto animate-fade-in-up">
        
        <!-- Success Animation -->
        <div class="text-center mb-6 delay-100">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-500/20 border-2 border-green-500/50 mb-4">
                <i class="fas fa-check-circle text-5xl text-green-500"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Check-in Successful!</h1>
            <p class="text-white/70 text-sm">Please wait for your queue number to be called</p>
        </div>
        
        <!-- Queue Card -->
        <div class="queue-card overflow-hidden delay-200">
            <div class="bg-gradient-to-r from-[#800020] to-[#600018] p-8 text-center text-white">
                <p class="text-sm font-semibold uppercase tracking-wider text-white/80 mb-2">Your Queue Number</p>
                <div class="queue-number pulse-animation"><?php echo htmlspecialchars($queueNumber); ?></div>
                <div class="h-1 w-24 bg-[#c9a84c] mx-auto mt-4 rounded-full"></div>
            </div>
            
            <div class="px-6 py-4 border-b flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2">
                    <i class="fas fa-flag-checkered text-gray-400"></i>
                    <span class="text-sm text-gray-500">Priority:</span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold" style="background: <?php echo $priorityColor['bg']; ?>20; color: <?php echo $priorityColor['bg']; ?>">
                        <i class="fas fa-circle mr-1" style="color: <?php echo $priorityColor['bg']; ?>; font-size: 8px;"></i>
                        <?php echo $priorityColor['label']; ?>
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-hourglass-half text-gray-400"></i>
                    <span class="text-sm text-gray-500">Est. Wait:</span>
                    <span class="font-semibold text-gray-900">~<?php echo $estimatedWait; ?> minutes</span>
                </div>
            </div>
            
            <div class="p-6 border-b">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-full bg-[#800020]/10 text-[#800020] flex items-center justify-center text-lg font-bold"><?php echo strtoupper(substr($fullName, 0, 1)); ?></div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($fullName); ?></h3>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($studentId); ?> • <?php echo htmlspecialchars($course); ?> - <?php echo htmlspecialchars($yearLevel); ?></p>
                        <p class="text-xs text-gray-400 mt-1"><i class="fas fa-envelope mr-1"></i> <?php echo htmlspecialchars($email ?: 'No email on file'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="p-6 border-b">
                <h4 class="text-sm font-semibold text-gray-900 mb-3"><i class="fas fa-chart-line text-[#800020] mr-2"></i>Live Queue Status</h4>
                <div class="grid grid-cols-4 gap-2 text-center">
                    <div class="bg-red-50 rounded-xl p-2"><p class="text-xs text-red-600 font-semibold">EMERGENCY</p><p class="text-xl font-bold text-red-700"><?php echo $emergency_count; ?></p></div>
                    <div class="bg-orange-50 rounded-xl p-2"><p class="text-xs text-orange-600 font-semibold">URGENT</p><p class="text-xl font-bold text-orange-700"><?php echo $urgent_count; ?></p></div>
                    <div class="bg-yellow-50 rounded-xl p-2"><p class="text-xs text-yellow-600 font-semibold">PRIORITY</p><p class="text-xl font-bold text-yellow-700"><?php echo $priority_count; ?></p></div>
                    <div class="bg-green-50 rounded-xl p-2"><p class="text-xs text-green-600 font-semibold">NORMAL</p><p class="text-xl font-bold text-green-700"><?php echo $normal_count; ?></p></div>
                </div>
                <div class="mt-4 bg-gray-50 rounded-xl p-3 text-center">
                    <p class="text-sm text-gray-500">Your position in <?php echo $priorityColor['label']; ?> queue</p>
                    <p class="text-2xl font-bold text-gray-900">#<?php echo $position; ?></p>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                        <div class="progress-bar h-2 rounded-full" style="width: <?php echo min(100, ($position / max(1, ${$priority . '_count'})) * 100); ?>%; background: <?php echo $priorityColor['bg']; ?>"></div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($triageData['symptoms'])): ?>
            <div class="p-6 border-b">
                <h4 class="text-sm font-semibold text-gray-900 mb-2"><i class="fas fa-notes-medical text-[#800020] mr-2"></i>Your Symptoms</h4>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (explode(', ', $triageData['symptoms']) as $symptom): ?>
                        <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700"><?php echo htmlspecialchars(trim($symptom)); ?></span>
                    <?php endforeach; ?>
                    <?php if (!empty($triageData['pain_level'])): ?>
                        <span class="inline-flex rounded-full bg-yellow-50 px-3 py-1 text-xs font-medium text-yellow-700"><i class="fas fa-waveform mr-1"></i> Pain: <?php echo $triageData['pain_level']; ?>/10</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="p-6 bg-amber-50">
                <div class="flex items-start gap-3">
                    <i class="fas fa-bell text-amber-600 text-xl mt-0.5"></i>
                    <div><p class="text-sm font-semibold text-amber-800">Please wait in the designated area</p><p class="text-xs text-amber-700 mt-1">A nurse will call your queue number: <strong><?php echo htmlspecialchars($queueNumber); ?></strong></p><p class="text-xs text-amber-600 mt-2"><i class="fas fa-envelope mr-1"></i> A confirmation email has been sent to <strong><?php echo htmlspecialchars($email ?: 'your email'); ?></strong></p></div>
                </div>
            </div>
            
            <div class="p-6 flex gap-3 no-print">
                <button onclick="window.print()" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 transition"><i class="fas fa-print"></i> Print Ticket</button>
                <a href="?new=1" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-[#800020] px-4 py-3 text-sm font-medium text-white hover:bg-[#600018] transition text-center"><i class="fas fa-user-plus"></i> New Patient</a>
            </div>
        </div>
        
        <div class="text-center py-4 text-white/30 text-xs mt-4 no-print">
            <i class="fas fa-shield-alt mr-1"></i> Secure & Encrypted
            <span class="mx-2">•</span>
            <i class="fas fa-clock mr-1"></i> Auto-refreshes every 15 seconds
        </div>
    </div>
</div>

<script>
    let refreshInterval;
    function startAutoRefresh() { refreshInterval = setInterval(() => location.reload(), 15000); }
    function stopAutoRefresh() { if (refreshInterval) clearInterval(refreshInterval); }
    window.addEventListener('load', startAutoRefresh);
    window.addEventListener('beforeprint', stopAutoRefresh);
    window.addEventListener('afterprint', startAutoRefresh);
    document.addEventListener('visibilitychange', () => { document.hidden ? stopAutoRefresh() : startAutoRefresh(); });
</script>
</body>
</html>