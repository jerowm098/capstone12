<?php
// capstonemain/kiosk/triage_form.php
session_start();
require_once '../config/db_connect.php';

// Check if email.php exists before requiring it
$email_config_path = '../config/email.php';
if (file_exists($email_config_path)) {
    require_once $email_config_path;
} else {
    if (!function_exists('sendEmail')) {
        function sendEmail($to, $subject, $message) {
            error_log("Email would be sent to: $to, Subject: $subject");
            return true;
        }
    }
    
    if (!defined('SMTP_HOST')) define('SMTP_HOST', '');
    if (!defined('SMTP_USER')) define('SMTP_USER', '');
    if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
    if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
}

// Check if patient is verified
if (!isset($_SESSION['patient_info']) || !isset($_SESSION['patient_info']['student_id'])) {
    header('Location: kiosk_landing.php');
    exit();
}

$patientInfo = $_SESSION['patient_info'];
$fullName = $patientInfo['full_name'] ?? 'Student';
$studentId = $patientInfo['student_number'] ?? 'N/A';
$course = $patientInfo['course'] ?? 'N/A';
$yearLevel = $patientInfo['year_level'] ?? 'N/A';
$bloodType = $patientInfo['blood_type'] ?? 'N/A';
$email = $patientInfo['email'] ?? '';
$initials = strtoupper(substr($patientInfo['first_name'] ?? 'S', 0, 1) . substr($patientInfo['last_name'] ?? '', 0, 1));

$error_message = '';
$submitted = false;
$queue_number = '';
$visit_id = null;

// Common symptoms list
$symptoms_list = [
    'Fever', 'Cough', 'Colds / Runny Nose', 'Sore Throat', 'Headache', 
    'Body Aches', 'Fatigue', 'Dizziness', 'Nausea', 'Vomiting', 
    'Diarrhea', 'Stomach Pain', 'Chest Pain', 'Shortness of Breath',
    'Rash / Skin Irritation', 'Injury / Wound', 'Eye Irritation', 'Other'
];

// Emergency symptoms (auto-priority)
$emergency_symptoms = ['Chest Pain', 'Shortness of Breath'];

// Handle triage submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_triage'])) {
    $symptoms_from_hidden = $_POST['symptoms_hidden'] ?? '';
    $selected_symptoms = !empty($symptoms_from_hidden) ? explode(',', $symptoms_from_hidden) : [];
    
    $other_symptom = trim($_POST['other_symptom'] ?? '');
    $pain_level = intval($_POST['pain_level'] ?? 0);
    $additional_notes = trim($_POST['additional_notes'] ?? '');
    
    if (empty($selected_symptoms) && empty($other_symptom)) {
        $error_message = "Please select at least one symptom.";
    } else {
        if (!empty($other_symptom)) {
            $selected_symptoms[] = $other_symptom;
        }
        
        $symptoms_text = implode(', ', $selected_symptoms);
        
        $priority = 'normal';
        $priority_label = 'Normal';
        $estimated_wait = 30;
        
        foreach ($selected_symptoms as $symptom) {
            if (in_array(trim($symptom), $emergency_symptoms)) {
                $priority = 'emergency';
                $priority_label = 'EMERGENCY';
                $estimated_wait = 0;
                break;
            }
        }
        
        if ($priority !== 'emergency') {
            if ($pain_level >= 8) {
                $priority = 'emergency';
                $priority_label = 'EMERGENCY';
                $estimated_wait = 0;
            } elseif ($pain_level >= 6) {
                $priority = 'urgent';
                $priority_label = 'URGENT';
                $estimated_wait = 10;
            } elseif ($pain_level >= 4) {
                $priority = 'priority';
                $priority_label = 'Priority';
                $estimated_wait = 20;
            }
        }
        
        $today = date('Y-m-d');
        $prefix = '';
        switch($priority) {
            case 'emergency': $prefix = 'E'; break;
            case 'urgent': $prefix = 'U'; break;
            case 'priority': $prefix = 'P'; break;
            default: $prefix = 'Q'; break;
        }
        
        $count_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM visits WHERE DATE(visit_date) = '$today' AND priority = '$priority'");
        $count = mysqli_fetch_assoc($count_query)['count'] ?? 0;
        $count++;
        $queue_number = $prefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
        
        $stmt = mysqli_prepare($conn, "INSERT INTO visits (student_id, symptoms, pain_level, notes, priority, status, queue_number, visit_date, created_at) 
                                       VALUES (?, ?, ?, ?, ?, 'waiting', ?, NOW(), NOW())");
        mysqli_stmt_bind_param($stmt, "isissi", $patientInfo['student_id'], $symptoms_text, $pain_level, $additional_notes, $priority, $queue_number);
        
        if (mysqli_stmt_execute($stmt)) {
            $visit_id = mysqli_insert_id($conn);
            $_SESSION['visit_id'] = $visit_id;
            $_SESSION['queue_number'] = $queue_number;
            $_SESSION['triage_data'] = [
                'symptoms' => $symptoms_text,
                'pain_level' => $pain_level,
                'priority' => $priority,
                'priority_label' => $priority_label,
                'estimated_wait' => $estimated_wait
            ];
            $submitted = true;
        } else {
            $error_message = "Failed to create visit. Please try again.";
        }
    }
}

$purpose = $_SESSION['selected_purpose'] ?? 'Consultation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Health Assessment - PUPBC Carelink Kiosk</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #800020 0%, #4a0010 100%);
            min-height: 100vh;
        }
        
        .card {
            background: white;
            border-radius: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .symptom-btn {
            transition: all 0.2s ease;
            cursor: pointer;
            border-radius: 9999px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            background: #f3f4f6;
            color: #374151;
            border: 2px solid transparent;
        }
        
        .symptom-btn.selected {
            background: #800020;
            color: white;
            border-color: #c9a84c;
        }
        
        .pain-level {
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            padding: 12px 8px;
            border-radius: 12px;
            background: #f3f4f6;
            border: 2px solid transparent;
        }
        
        .pain-level.selected {
            background: #800020;
            color: white;
            border-color: #c9a84c;
        }
        
        /* Progress Steps */
        .progress-step {
            transition: all 0.5s ease;
        }
        
        .progress-step.active {
            background: #c9a84c !important;
            color: #800020 !important;
            font-weight: 700;
            box-shadow: 0 0 0 4px rgba(201, 168, 76, 0.3);
        }
        
        .progress-step.completed {
            background: #800020 !important;
            color: white !important;
        }
        
        .progress-line.active {
            background: #c9a84c !important;
        }
        
        .progress-line.completed {
            background: #800020 !important;
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            border-radius: 1.5rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        /* Form sections */
        .form-section {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            padding-left: 12px;
        }
        
        .form-section.active-section {
            border-left-color: #c9a84c;
        }
        
        .form-section.completed-section {
            border-left-color: #800020;
            opacity: 0.8;
        }
        
        /* Countdown bar */
        @keyframes shrinkBar {
            from { width: 100%; }
            to { width: 0%; }
        }
        
        .countdown-bar {
            height: 3px;
            background: #800020;
            animation: shrinkBar 10s linear forwards;
            border-radius: 0 0 1.5rem 1.5rem;
        }
        
        /* Spinner */
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .spinner {
            animation: spin 1s linear infinite;
        }
        
        @media (max-width: 640px) {
            .symptom-btn { padding: 6px 12px; font-size: 12px; }
            .pain-level { padding: 8px 4px; font-size: 12px; }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-3xl mx-auto">
        
        <!-- Progress Indicator -->
        <div class="flex items-center justify-between mb-8 max-w-md mx-auto">
            <div class="text-center">
                <div class="progress-step w-10 h-10 rounded-full flex items-center justify-center font-bold mx-auto mb-2 bg-white/30 text-white/70" id="step1">
                    <span class="step-number">1</span>
                    <span class="step-check hidden">✓</span>
                </div>
                <p class="text-white/80 text-xs">Symptoms</p>
            </div>
            <div class="flex-1 h-1 bg-white/30 mx-2 rounded-full progress-line" id="line1"></div>
            
            <div class="text-center">
                <div class="progress-step w-10 h-10 rounded-full flex items-center justify-center font-bold mx-auto mb-2 bg-white/30 text-white/70" id="step2">
                    <span class="step-number">2</span>
                    <span class="step-check hidden">✓</span>
                </div>
                <p class="text-white/80 text-xs">Assessment</p>
            </div>
            <div class="flex-1 h-1 bg-white/30 mx-2 rounded-full progress-line" id="line2"></div>
            
            <div class="text-center">
                <div class="progress-step w-10 h-10 rounded-full flex items-center justify-center font-bold mx-auto mb-2 bg-white/30 text-white/70" id="step3">
                    <span class="step-number">3</span>
                    <span class="step-check hidden">✓</span>
                </div>
                <p class="text-white/80 text-xs">Queue</p>
            </div>
        </div>
        
        <!-- Triage Form Card -->
        <div class="card overflow-hidden" id="mainCard">
            <div class="bg-gradient-to-r from-[#800020] to-[#600018] p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">Health Assessment</h1>
                        <p class="text-white/80 text-sm mt-1" id="headerSubtitle">Step 1: Select your symptoms</p>
                    </div>
                    <a href="kiosk_options.php" class="text-white/70 hover:text-white">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-gray-50 border-b flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-[#800020]/20 text-[#800020] flex items-center justify-center font-bold">
                        <?php echo $initials; ?>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($fullName); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($studentId); ?> • <?php echo htmlspecialchars($course); ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-gray-400"></i>
                    <span class="text-sm text-gray-600">Purpose: <strong><?php echo htmlspecialchars($purpose); ?></strong></span>
                </div>
            </div>
            
            <div class="p-6">
                <?php if ($error_message): ?>
                    <div class="mb-4 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="triageForm" class="space-y-6">
                    
                    <!-- SECTION 1: SYMPTOMS -->
                    <div class="form-section active-section rounded-lg p-4" id="section1">
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-sm font-semibold text-gray-700">
                                <i class="fas fa-head-side-medical text-[#800020] mr-2"></i>
                                What symptoms are you experiencing? (Select all that apply)
                            </label>
                            <span class="text-xs bg-[#c9a84c] text-[#800020] px-2 py-1 rounded-full font-semibold">Step 1</span>
                        </div>
                        <div class="flex flex-wrap gap-2" id="symptoms_container">
                            <?php foreach ($symptoms_list as $symptom): ?>
                                <button type="button" class="symptom-btn" data-symptom="<?php echo htmlspecialchars($symptom); ?>">
                                    <?php echo htmlspecialchars($symptom); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="symptoms_hidden" id="symptoms_hidden" value="">
                        <div id="other_symptom_div" class="mt-3 hidden">
                            <input type="text" name="other_symptom" id="other_symptom" 
                                   placeholder="Please specify other symptom..."
                                   class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                        </div>
                        <p id="symptoms_error" class="text-red-500 text-xs mt-1 hidden">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Please select at least one symptom
                        </p>
                        <p id="symptoms_success" class="text-[#800020] text-xs mt-1 hidden">
                            <i class="fas fa-check-circle mr-1"></i> <span id="symptoms_count">0</span> symptom(s) selected
                        </p>
                    </div>
                    
                    <!-- SECTION 2: ASSESSMENT -->
                    <div class="form-section rounded-lg p-4 opacity-60" id="section2">
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-sm font-semibold text-gray-700">
                                <i class="fas fa-waveform text-[#800020] mr-2"></i>
                                Pain Level (0 = No pain, 10 = Worst pain imaginable)
                            </label>
                            <span class="text-xs bg-gray-200 text-gray-500 px-2 py-1 rounded-full font-semibold">Step 2</span>
                        </div>
                        <div class="grid grid-cols-5 gap-2" id="pain_container">
                            <?php for ($i = 0; $i <= 10; $i += 2): ?>
                                <button type="button" class="pain-level" data-pain="<?php echo $i; ?>">
                                    <div class="font-bold text-lg"><?php echo $i; ?></div>
                                    <div class="text-xs">
                                        <?php 
                                            if ($i == 0) echo 'None';
                                            elseif ($i == 2) echo 'Mild';
                                            elseif ($i == 4) echo 'Moderate';
                                            elseif ($i == 6) echo 'Severe';
                                            elseif ($i == 8) echo 'Very Severe';
                                            elseif ($i == 10) echo 'Worst';
                                        ?>
                                    </div>
                                </button>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="pain_level" id="pain_level" value="0">
                        
                        <div class="mt-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-notes-medical text-[#800020] mr-2"></i>
                                Additional Notes (Optional)
                            </label>
                            <textarea name="additional_notes" id="additional_notes" rows="3" 
                                      class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020] resize-none"
                                      placeholder="Any other information you'd like to share..."></textarea>
                        </div>
                    </div>
                    
                    <div id="emergency_warning" class="hidden bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-exclamation-triangle text-red-500 text-xl mt-0.5"></i>
                            <div>
                                <p class="font-bold text-red-700">Emergency symptoms detected!</p>
                                <p class="text-sm text-red-600 mt-1">Please proceed directly to the clinic immediately.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs text-gray-600">
                        <i class="fas fa-info-circle mr-1 text-[#800020]"></i>
                        This is a self-assessment kiosk. For emergencies, go directly to the clinic.
                    </div>
                    
                    <div class="flex gap-3 pt-2">
                        <a href="kiosk_options.php" class="flex-1 text-center py-3 rounded-xl border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </a>
                        <button type="button" id="submitBtn" 
                                class="flex-1 py-3 rounded-xl text-white font-bold transition disabled:opacity-50 disabled:cursor-not-allowed"
                                style="background: #800020" disabled>
                            <i class="fas fa-paper-plane mr-2"></i> Submit & Get Queue Number
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="text-center py-4 text-white/40 text-xs mt-4">
            <i class="fas fa-envelope mr-1"></i> Your queue number will be sent to your registered email
        </div>
    </div>
    
    <!-- ===== CONFIRMATION MODAL ===== -->
    <div class="modal-overlay hidden" id="confirmationModal">
        <div class="modal-content">
            <div class="bg-[#800020] p-6 text-white rounded-t-2xl">
                <h2 class="text-xl font-bold">
                    <i class="fas fa-clipboard-check mr-2"></i> Confirm Your Information
                </h2>
                <p class="text-white/80 text-sm mt-1">Please review before submitting</p>
            </div>
            
            <div class="p-6">
                <!-- Queue Number Preview -->
                <div class="mb-4 bg-gray-50 border border-gray-200 rounded-xl p-4 text-center">
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Your Queue Number Will Be</p>
                    <p class="text-3xl font-bold text-[#800020]" id="modal_queue_preview">---</p>
                    <p class="text-xs text-gray-400 mt-1">Generated from your assessment</p>
                </div>
                
                <!-- Patient Info -->
                <div class="mb-4 pb-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Patient Information</h3>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="text-gray-500">Name:</span> <span class="font-semibold"><?php echo htmlspecialchars($fullName); ?></span></div>
                        <div><span class="text-gray-500">Student ID:</span> <span class="font-semibold"><?php echo htmlspecialchars($studentId); ?></span></div>
                        <div><span class="text-gray-500">Course:</span> <span class="font-semibold"><?php echo htmlspecialchars($course); ?></span></div>
                        <div><span class="text-gray-500">Purpose:</span> <span class="font-semibold"><?php echo htmlspecialchars($purpose); ?></span></div>
                    </div>
                </div>
                
                <!-- Symptoms -->
                <div class="mb-4 pb-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Symptoms</h3>
                    <div class="flex flex-wrap gap-1" id="modal_symptoms"></div>
                    <p class="text-sm mt-1 hidden" id="modal_other_symptom"></p>
                </div>
                
                <!-- Assessment -->
                <div class="mb-4 pb-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Assessment</h3>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="text-gray-500">Pain Level:</span> <span class="font-semibold" id="modal_pain">0/10</span></div>
                        <div><span class="text-gray-500">Priority:</span> <span class="font-semibold" id="modal_priority">Normal</span></div>
                    </div>
                    <div class="mt-2 text-sm">
                        <span class="text-gray-500">Notes:</span>
                        <span id="modal_notes" class="text-gray-700 italic">None</span>
                    </div>
                </div>
                
                <!-- Emergency Warning -->
                <div id="modal_emergency_warning" class="hidden bg-red-50 border-l-4 border-red-500 p-3 rounded-lg mb-4">
                    <p class="text-sm text-red-700 font-semibold">
                        <i class="fas fa-exclamation-triangle mr-1"></i> 
                        Emergency detected! Proceed to clinic immediately.
                    </p>
                </div>
                
                <div class="flex gap-3">
                    <button type="button" id="modalCancel" 
                            class="flex-1 py-3 rounded-xl border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition">
                        <i class="fas fa-edit mr-2"></i> Edit
                    </button>
                    <button type="button" id="modalConfirm" 
                            class="flex-1 py-3 rounded-xl text-white font-bold transition" style="background: #800020">
                        <i class="fas fa-check-circle mr-2"></i> Confirm & Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ===== SUCCESS MODAL ===== -->
    <div class="modal-overlay hidden" id="successModal">
        <div class="modal-content text-center">
            <!-- Header -->
            <div class="bg-[#800020] p-6 text-white rounded-t-2xl">
                <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-check-circle text-3xl"></i>
                </div>
                <h2 class="text-xl font-bold">Assessment Submitted!</h2>
                <p class="text-white/80 text-sm mt-1">Your queue number has been generated</p>
            </div>
            
            <div class="p-6">
                <!-- Queue Number Display -->
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-4">
                    <p class="text-sm text-gray-500 mb-2">Your Queue Number</p>
                    <p class="text-5xl font-extrabold text-[#800020] tracking-wider" id="successQueueNumber">
                        <?php echo htmlspecialchars($queue_number); ?>
                    </p>
                    <p class="text-xs text-gray-400 mt-2">Please wait for your number to be called</p>
                </div>
                
                <p class="text-sm text-gray-600 mb-4">
                    <i class="fas fa-envelope mr-1"></i> 
                    Queue details will be sent to your email
                </p>
                
                <!-- Logout Timer -->
                <div class="bg-gray-50 rounded-xl p-4 mb-4">
                    <p class="text-sm text-gray-600 mb-2">
                        <i class="fas fa-clock mr-1"></i> 
                        Auto-logout in <strong id="countdownDisplay">10</strong> seconds
                    </p>
                    
                    <p class="text-xs text-gray-400">
                        For your privacy, you will be automatically logged out
                    </p>
                </div>
                
                <!-- Logout Button - loading state appears here -->
                <button type="button" id="logoutNowBtn"
                   class="block w-full py-3 rounded-xl text-white font-bold transition text-center" style="background: #800020">
                    <span id="logoutBtnText">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout Now
                    </span>
                </button>
            </div>
            
            <!-- Countdown Bar -->
            <div class="countdown-bar" id="countdownBar"></div>
        </div>
    </div>
    
    <script>
        // ===== PROGRESS STEPS =====
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const step3 = document.getElementById('step3');
        const line1 = document.getElementById('line1');
        const line2 = document.getElementById('line2');
        const section1 = document.getElementById('section1');
        const section2 = document.getElementById('section2');
        const headerSubtitle = document.getElementById('headerSubtitle');
        
        let currentStep = 1;
        let step1Complete = false;
        let step2Complete = false;
        
        function updateProgress() {
            [step1, step2, step3].forEach(s => {
                s.classList.remove('active', 'completed');
                s.querySelector('.step-number').classList.remove('hidden');
                s.querySelector('.step-check').classList.add('hidden');
            });
            [line1, line2].forEach(l => l.classList.remove('active', 'completed'));
            
            if (step1Complete && step2Complete) {
                step1.classList.add('completed');
                step1.querySelector('.step-number').classList.add('hidden');
                step1.querySelector('.step-check').classList.remove('hidden');
                step2.classList.add('completed');
                step2.querySelector('.step-number').classList.add('hidden');
                step2.querySelector('.step-check').classList.remove('hidden');
                step3.classList.add('active');
                line1.classList.add('completed');
                line2.classList.add('active');
                currentStep = 3;
                headerSubtitle.textContent = 'Step 3: Ready to submit';
            } else if (step1Complete) {
                step1.classList.add('completed');
                step1.querySelector('.step-number').classList.add('hidden');
                step1.querySelector('.step-check').classList.remove('hidden');
                step2.classList.add('active');
                line1.classList.add('completed');
                currentStep = 2;
                headerSubtitle.textContent = 'Step 2: Rate your pain & add notes';
            } else {
                step1.classList.add('active');
                currentStep = 1;
                headerSubtitle.textContent = 'Step 1: Select your symptoms';
            }
            
            section1.classList.remove('active-section', 'completed-section', 'opacity-60');
            section2.classList.remove('active-section', 'completed-section', 'opacity-60');
            
            if (step1Complete && step2Complete) {
                section1.classList.add('completed-section');
                section2.classList.add('completed-section');
            } else if (step1Complete) {
                section1.classList.add('completed-section');
                section2.classList.add('active-section');
            } else {
                section1.classList.add('active-section');
                section2.classList.add('opacity-60');
            }
        }
        
        // ===== SYMPTOMS SELECTION =====
        let selectedSymptoms = [];
        const emergencySymptoms = ['Chest Pain', 'Shortness of Breath'];
        const symptomsError = document.getElementById('symptoms_error');
        const symptomsSuccess = document.getElementById('symptoms_success');
        const symptomsCount = document.getElementById('symptoms_count');
        const submitBtn = document.getElementById('submitBtn');
        const emergencyWarning = document.getElementById('emergency_warning');
        const symptomsHidden = document.getElementById('symptoms_hidden');
        
        document.querySelectorAll('.symptom-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const symptom = this.dataset.symptom;
                const idx = selectedSymptoms.indexOf(symptom);
                
                if (idx === -1) {
                    selectedSymptoms.push(symptom);
                    this.classList.add('selected');
                } else {
                    selectedSymptoms.splice(idx, 1);
                    this.classList.remove('selected');
                }
                
                symptomsHidden.value = selectedSymptoms.join(',');
                
                if (selectedSymptoms.includes('Other')) {
                    document.getElementById('other_symptom_div').classList.remove('hidden');
                } else {
                    document.getElementById('other_symptom_div').classList.add('hidden');
                    document.getElementById('other_symptom').value = '';
                }
                
                const hasEmergency = selectedSymptoms.some(s => emergencySymptoms.includes(s));
                if (hasEmergency) {
                    emergencyWarning.classList.remove('hidden');
                } else {
                    emergencyWarning.classList.add('hidden');
                }
                
                if (selectedSymptoms.length > 0) {
                    symptomsError.classList.add('hidden');
                    symptomsSuccess.classList.remove('hidden');
                    symptomsCount.textContent = selectedSymptoms.length;
                } else {
                    symptomsSuccess.classList.add('hidden');
                    symptomsError.classList.remove('hidden');
                }
                
                step1Complete = selectedSymptoms.length > 0;
                updateProgress();
                checkAllStepsComplete();
            });
        });
        
        // ===== PAIN LEVEL =====
        const painButtons = document.querySelectorAll('.pain-level');
        const painHidden = document.getElementById('pain_level');
        let painSelected = false;
        
        painButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                painButtons.forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
                painHidden.value = this.dataset.pain;
                painSelected = true;
                checkStep2Complete();
            });
        });
        
        const defaultPain = document.querySelector('.pain-level[data-pain="0"]');
        if (defaultPain) {
            defaultPain.classList.add('selected');
            painHidden.value = '0';
            painSelected = true;
        }
        
        document.getElementById('additional_notes').addEventListener('input', function() {
            checkStep2Complete();
        });
        
        function checkStep2Complete() {
            const notes = document.getElementById('additional_notes').value.trim();
            step2Complete = painSelected || notes !== '';
            updateProgress();
            checkAllStepsComplete();
        }
        
        function checkAllStepsComplete() {
            if (step1Complete && step2Complete) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
        
        // ===== QUEUE PREVIEW =====
        function generateQueuePreview() {
            const painVal = parseInt(painHidden.value);
            let prefix = 'Q';
            
            const hasEmergency = selectedSymptoms.some(s => emergencySymptoms.includes(s));
            
            if (hasEmergency || painVal >= 8) {
                prefix = 'E';
            } else if (painVal >= 6) {
                prefix = 'U';
            } else if (painVal >= 4) {
                prefix = 'P';
            }
            
            const previewNum = String(Math.floor(Math.random() * 999) + 1).padStart(3, '0');
            return prefix + '-' + previewNum;
        }
        
        // ===== CONFIRMATION MODAL =====
        const confirmationModal = document.getElementById('confirmationModal');
        const modalCancel = document.getElementById('modalCancel');
        const modalConfirm = document.getElementById('modalConfirm');
        const successModal = document.getElementById('successModal');
        
        submitBtn.addEventListener('click', function() {
            if (currentStep < 3) {
                if (!step1Complete) alert('Please complete Step 1: Select at least one symptom.');
                else if (!step2Complete) alert('Please complete Step 2: Rate your pain level.');
                return;
            }
            
            if (selectedSymptoms.length === 0) {
                symptomsError.classList.remove('hidden');
                return;
            }
            
            populateModal();
            confirmationModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
        
        function populateModal() {
            document.getElementById('modal_queue_preview').textContent = generateQueuePreview();
            
            const modalSymptoms = document.getElementById('modal_symptoms');
            modalSymptoms.innerHTML = '';
            selectedSymptoms.forEach(s => {
                const span = document.createElement('span');
                span.className = 'bg-[#800020]/10 text-[#800020] px-2 py-1 rounded-full text-xs font-medium';
                span.textContent = s;
                modalSymptoms.appendChild(span);
            });
            
            const otherText = document.getElementById('other_symptom').value.trim();
            const modalOther = document.getElementById('modal_other_symptom');
            if (otherText) {
                modalOther.innerHTML = '<span class="text-gray-500">Other:</span> <span class="font-semibold">' + otherText + '</span>';
                modalOther.classList.remove('hidden');
            } else {
                modalOther.classList.add('hidden');
            }
            
            document.getElementById('modal_pain').textContent = painHidden.value + '/10';
            
            let priority = 'Normal';
            if (selectedSymptoms.some(s => emergencySymptoms.includes(s)) || parseInt(painHidden.value) >= 8) priority = 'EMERGENCY';
            else if (parseInt(painHidden.value) >= 6) priority = 'URGENT';
            else if (parseInt(painHidden.value) >= 4) priority = 'Priority';
            document.getElementById('modal_priority').textContent = priority;
            
            const notes = document.getElementById('additional_notes').value.trim();
            document.getElementById('modal_notes').textContent = notes || 'None';
            
            const hasEmergency = selectedSymptoms.some(s => emergencySymptoms.includes(s)) || parseInt(painHidden.value) >= 8;
            document.getElementById('modal_emergency_warning').classList.toggle('hidden', !hasEmergency);
        }
        
        modalCancel.addEventListener('click', function() {
            confirmationModal.classList.add('hidden');
            document.body.style.overflow = '';
        });
        
        modalConfirm.addEventListener('click', function() {
            modalConfirm.disabled = true;
            modalConfirm.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
            
            const form = document.getElementById('triageForm');
            const formData = new FormData(form);
            formData.append('submit_triage', '1');
            
            fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.text())
            .then(html => {
                confirmationModal.classList.add('hidden');
                successModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                startLogoutCountdown();
            })
            .catch(error => {
                console.error('Error:', error);
                form.submit();
            });
        });
        
        confirmationModal.addEventListener('click', function(e) {
            if (e.target === confirmationModal) {
                confirmationModal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });
        
        // ===== LOGOUT COUNTDOWN + BUTTON STATE =====
        let countdownSeconds = 10;
        let countdownInterval;
        const logoutBtn = document.getElementById('logoutNowBtn');
        const logoutBtnText = document.getElementById('logoutBtnText');
        
        function startLogoutCountdown() {
            const countdownDisplay = document.getElementById('countdownDisplay');
            const countdownBar = document.getElementById('countdownBar');
            
            countdownBar.style.animation = 'none';
            countdownBar.offsetHeight;
            countdownBar.style.animation = `shrinkBar ${countdownSeconds}s linear forwards`;
            
            countdownInterval = setInterval(() => {
                countdownSeconds--;
                countdownDisplay.textContent = countdownSeconds;
                if (countdownSeconds <= 3) countdownDisplay.style.color = '#dc2626';
                if (countdownSeconds <= 0) {
                    clearInterval(countdownInterval);
                    performLogout();
                }
            }, 1000);
        }
        
        function performLogout() {
            // Change button to loading state
            logoutBtn.disabled = true;
            logoutBtn.style.opacity = '0.7';
            logoutBtn.style.cursor = 'not-allowed';
            logoutBtnText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Logging out...';
            
            // Redirect after short delay
            setTimeout(() => {
                window.location.href = 'logout.php';
            }, 1500);
        }
        
        // Logout Now button click
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            clearInterval(countdownInterval);
            
            // Stop countdown bar
            const countdownBar = document.getElementById('countdownBar');
            countdownBar.style.animation = 'none';
            
            // Update countdown display
            document.getElementById('countdownDisplay').textContent = 'now';
            document.getElementById('countdownDisplay').style.color = '#dc2626';
            
            performLogout();
        });
        
        // ===== INIT =====
        updateProgress();
        checkAllStepsComplete();
        
        <?php if ($submitted): ?>
        successModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        startLogoutCountdown();
        <?php endif; ?>
    </script>
</body>
</html>