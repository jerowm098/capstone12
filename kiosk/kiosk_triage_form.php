<?php
// capstonemain/kiosk/triage_form.php
session_start();
require_once '../config/db_connect.php';

// Check if email.php exists before requiring it
$email_config_path = '../config/email.php';
if (file_exists($email_config_path)) {
    require_once $email_config_path;
} else {
    // Define a fallback email function if email.php doesn't exist
    if (!function_exists('sendEmail')) {
        function sendEmail($to, $subject, $message) {
            // Simple fallback - just log or return true
            error_log("Email would be sent to: $to, Subject: $subject");
            return true;
        }
    }
    
    // Define any other constants that might be expected
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
    $selected_symptoms = $_POST['symptoms'] ?? [];
    $other_symptom = trim($_POST['other_symptom'] ?? '');
    $pain_level = intval($_POST['pain_level'] ?? 0);
    $additional_notes = trim($_POST['additional_notes'] ?? '');
    
    // Combine symptoms
    if (!empty($other_symptom)) {
        $selected_symptoms[] = $other_symptom;
    }
    
    $symptoms_text = implode(', ', $selected_symptoms);
    
    // AUTO-TRIAGE ASSESSMENT (based on symptoms only)
    $priority = 'normal';
    $priority_label = 'Normal';
    $priority_color = 'green';
    $estimated_wait = 30;
    
    // Check for emergency symptoms
    foreach ($selected_symptoms as $symptom) {
        if (in_array($symptom, $emergency_symptoms)) {
            $priority = 'emergency';
            $priority_label = 'EMERGENCY';
            $priority_color = 'red';
            $estimated_wait = 0;
            break;
        }
    }
    
    // Check pain level for urgency
    if ($priority !== 'emergency') {
        if ($pain_level >= 8) {
            $priority = 'emergency';
            $priority_label = 'EMERGENCY';
            $priority_color = 'red';
            $estimated_wait = 0;
        } elseif ($pain_level >= 6) {
            $priority = 'urgent';
            $priority_label = 'URGENT';
            $priority_color = 'orange';
            $estimated_wait = 10;
        } elseif ($pain_level >= 4) {
            $priority = 'priority';
            $priority_label = 'Priority';
            $priority_color = 'yellow';
            $estimated_wait = 20;
        }
    }
    
    // Generate queue number
    $today = date('Y-m-d');
    $prefix = '';
    switch($priority) {
        case 'emergency': $prefix = 'E'; break;
        case 'urgent': $prefix = 'U'; break;
        case 'priority': $prefix = 'P'; break;
        default: $prefix = 'R'; break;
    }
    
    // Get today's count for this priority
    $count_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM visits WHERE DATE(visit_date) = '$today' AND priority = '$priority'");
    $count = mysqli_fetch_assoc($count_query)['count'] ?? 0;
    $count++;
    $queue_number = $prefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    // Create visit record
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

        // Redirect to queue status page
        header('Location: queue_status.php');
        exit();
    } else {
        $error_message = "Failed to create visit. Please try again.";
    }
}

// Get saved purpose from session (from dashboard)
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
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
        
        .progress-step {
            transition: all 0.3s ease;
        }
        
        .progress-step.active {
            background: #800020;
            color: white;
        }
        
        .progress-step.completed {
            background: #22c55e;
            color: white;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        .emergency-warning {
            animation: pulse 1s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; transform: scale(1.02); }
        }
        
        @media (max-width: 640px) {
            .symptom-btn { padding: 6px 12px; font-size: 12px; }
            .pain-level { padding: 8px 4px; font-size: 12px; }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-3xl mx-auto animate-fade-in">
        
        <!-- Progress Indicator -->
        <div class="flex items-center justify-between mb-8 max-w-md mx-auto">
            <div class="text-center">
                <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-[#800020] font-bold mx-auto mb-2">1</div>
                <p class="text-white/80 text-xs">Identity</p>
            </div>
            <div class="flex-1 h-1 bg-white/30 mx-2 rounded-full"></div>
            <div class="text-center">
                <div class="w-10 h-10 rounded-full bg-[#c9a84c] text-[#800020] font-bold flex items-center justify-center mx-auto mb-2">2</div>
                <p class="text-white font-semibold text-xs">Assessment</p>
            </div>
            <div class="flex-1 h-1 bg-white/30 mx-2 rounded-full"></div>
            <div class="text-center">
                <div class="w-10 h-10 rounded-full bg-white/30 text-white/70 font-bold flex items-center justify-center mx-auto mb-2">3</div>
                <p class="text-white/80 text-xs">Queue</p>
            </div>
        </div>
        
        <!-- Triage Form Card -->
        <div class="card overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-[#800020] to-[#600018] p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">Health Assessment</h1>
                        <p class="text-white/80 text-sm mt-1">Please tell us about your current condition</p>
                    </div>
                    <a href="kiosk_options.php" class="text-white/70 hover:text-white">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                </div>
            </div>
            
            <!-- Patient Info Summary -->
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
            
            <!-- Form Body -->
            <div class="p-6">
                <?php if ($error_message): ?>
                    <div class="mb-4 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="triageForm" class="space-y-6">
                    
                    <!-- Symptoms Selection -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-head-side-medical text-[#800020] mr-2"></i>
                            What symptoms are you experiencing? (Select all that apply)
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($symptoms_list as $symptom): ?>
                                <button type="button" class="symptom-btn" data-symptom="<?php echo htmlspecialchars($symptom); ?>">
                                    <?php echo htmlspecialchars($symptom); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="symptoms[]" id="selected_symptoms" value="">
                        <div id="other_symptom_div" class="mt-3 hidden">
                            <input type="text" name="other_symptom" id="other_symptom" 
                                   placeholder="Please specify other symptom..."
                                   class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                        </div>
                        <p id="symptoms_error" class="text-red-500 text-xs mt-1 hidden">Please select at least one symptom</p>
                    </div>
                    
                    <!-- Pain Level -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-waveform text-[#800020] mr-2"></i>
                            Pain Level (0 = No pain, 10 = Worst pain imaginable)
                        </label>
                        <div class="grid grid-cols-5 gap-2">
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
                    </div>
                    
                    <!-- Additional Notes -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-notes-medical text-[#800020] mr-2"></i>
                            Additional Notes (Optional)
                        </label>
                        <textarea name="additional_notes" id="additional_notes" rows="3" 
                                  class="w-full rounded-xl border border-gray-300 px-4 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020] resize-none"
                                  placeholder="Any other information you'd like to share..."></textarea>
                    </div>
                    
                    <!-- Emergency Warning -->
                    <div id="emergency_warning" class="hidden bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-exclamation-triangle text-red-500 text-xl mt-0.5"></i>
                            <div>
                                <p class="font-bold text-red-700">Emergency symptoms detected!</p>
                                <p class="text-sm text-red-600 mt-1">Please proceed directly to the clinic. Do not wait in line. Inform the nurse immediately.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Disclaimer -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 text-xs text-yellow-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Note:</strong> This is a self-assessment kiosk. For medical emergencies, please go directly to the clinic or call for immediate assistance.
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-2">
                        <a href="kiosk_options.php" class="flex-1 text-center py-3 rounded-xl border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </a>
                        <button type="submit" name="submit_triage" id="submitBtn" 
                                class="flex-1 py-3 rounded-xl text-white font-bold transition disabled:opacity-50 disabled:cursor-not-allowed"
                                style="background: #800020" disabled>
                            <i class="fas fa-paper-plane mr-2"></i> Submit & Get Queue Number
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center py-4 text-white/40 text-xs mt-4">
            <i class="fas fa-envelope mr-1"></i> Your queue number will be sent to your registered email
        </div>
    </div>
    
    <script>
        // Symptoms selection
        let selectedSymptoms = [];
        const emergencySymptoms = ['Chest Pain', 'Shortness of Breath'];
        
        // Get all symptom buttons
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
                
                // Update hidden input
                document.getElementById('selected_symptoms').value = selectedSymptoms.join(',');
                
                // Show/hide other symptom input
                if (symptom === 'Other' && idx === -1) {
                    document.getElementById('other_symptom_div').classList.remove('hidden');
                } else if (symptom === 'Other') {
                    document.getElementById('other_symptom_div').classList.add('hidden');
                    document.getElementById('other_symptom').value = '';
                }
                
                // Check for emergency symptoms
                const hasEmergency = selectedSymptoms.some(s => emergencySymptoms.includes(s));
                const emergencyWarning = document.getElementById('emergency_warning');
                
                if (hasEmergency) {
                    emergencyWarning.classList.remove('hidden');
                    emergencyWarning.classList.add('emergency-warning');
                } else {
                    emergencyWarning.classList.add('hidden');
                    emergencyWarning.classList.remove('emergency-warning');
                }
                
                // Enable/disable submit button
                const submitBtn = document.getElementById('submitBtn');
                const symptomsError = document.getElementById('symptoms_error');
                
                if (selectedSymptoms.length === 0) {
                    submitBtn.disabled = true;
                    symptomsError.classList.remove('hidden');
                } else {
                    submitBtn.disabled = false;
                    symptomsError.classList.add('hidden');
                }
            });
        });
        
        // Pain level selection
        document.querySelectorAll('.pain-level').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove selected class from all pain buttons
                document.querySelectorAll('.pain-level').forEach(b => {
                    b.classList.remove('selected');
                });
                
                // Add selected class to clicked button
                this.classList.add('selected');
                
                // Update hidden input
                document.getElementById('pain_level').value = this.dataset.pain;
            });
        });
        
        // Form validation before submit
        document.getElementById('triageForm').addEventListener('submit', function(e) {
            if (selectedSymptoms.length === 0) {
                e.preventDefault();
                document.getElementById('symptoms_error').classList.remove('hidden');
                document.getElementById('symptoms_error').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
        });
        
        // Auto-select pain level 0 by default
        document.querySelector('.pain-level[data-pain="0"]').classList.add('selected');
    </script>
</body>
</html>