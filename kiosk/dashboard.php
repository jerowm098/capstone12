<?php
session_start();
require_once '../config/db_connect.php';
require_once '../config/email.php'; // For sending email

// Check if patient is verified
if (!isset($_SESSION['patient_info']) || !isset($_SESSION['patient_info']['student_id'])) {
    header('Location: scanner.php');
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
    'Rash / Skin Irritation', 'Injury / Wound', 'Other'
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
        if ($pain_level >= 7) {
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
    $count = mysqli_fetch_assoc($count_query)['count'] + 1;
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
        
        // SEND EMAIL RECEIPT to student
        $to = $email;
        $subject = "PUPBC Carelink - Your Queue Number: $queue_number";
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #800020; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; border: 1px solid #ddd; }
                .queue-number { font-size: 48px; font-weight: bold; color: #800020; text-align: center; margin: 20px 0; }
                .priority { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
                .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>PUPBC Carelink Clinic</h2>
                    <p>Queue Confirmation</p>
                </div>
                <div class='content'>
                    <p>Dear <strong>$fullName</strong>,</p>
                    <p>Your check-in has been recorded successfully.</p>
                    
                    <div class='queue-number'>$queue_number</div>
                    
                    <table width='100%'>
                        <tr><td><strong>Priority:</strong></td><td><span class='priority' style='background: {$priority_color}20; color: $priority_color'>$priority_label</span></td></tr>
                        <tr><td><strong>Estimated Wait Time:</strong></td><td>~{$estimated_wait} minutes</td></tr>
                        <tr><td><strong>Date:</strong></td><td>" . date('F d, Y h:i A') . "</td></tr>
                        <tr><td><strong>Location:</strong></td><td>PUPBC Clinic, 2nd Floor</td></tr>
                    </table>
                    
                    <hr>
                    <p><strong>Your Symptoms:</strong> $symptoms_text</p>
                    <p><strong>Pain Level:</strong> $pain_level/10</p>
                    
                    <p><strong>Next Steps:</strong></p>
                    <ul>
                        <li>Please wait in the designated waiting area</li>
                        <li>A nurse will call your queue number: <strong>$queue_number</strong></li>
                        <li>Keep this email for reference</li>
                    </ul>
                </div>
                <div class='footer'>
                    <p>PUP Biñan Campus | clinic.binan@pup.edu.ph | (049) 123-4567</p>
                    <p>This is an automated message. Please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Redirect to queue status page
        header('Location: queue_status.php');
        exit();
    } else {
        $error_message = "Failed to create visit. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Assessment - PUPBC Carelink Kiosk</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --maroon: #800020;
            --maroon-dark: #4a0010;
            --gold: #c9a84c;
        }
        body {
            background: #f8fafc;
            min-height: 100vh;
        }
        .symptom-btn {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .symptom-btn.selected {
            background: var(--maroon) !important;
            color: white !important;
            border-color: var(--maroon) !important;
        }
        .pain-level {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .pain-level.selected {
            background: var(--maroon) !important;
            color: white !important;
        }
    </style>
</head>
<body class="font-sans">
    <div class="container mx-auto px-4 py-6 max-w-3xl">
        
        <!-- Header -->
        <div class="text-center mb-6">
            <a href="logout.php" class="inline-flex items-center gap-2 text-[var(--maroon)] hover:underline mb-3">
                <i class="fas fa-sign-out-alt"></i> New Patient
            </a>
            <h1 class="font-bold text-2xl" style="color: var(--maroon)">PUPBC CareLink</h1>
            <p class="text-gray-500 text-sm">Self-Service Triage Kiosk</p>
        </div>
        
        <!-- Patient Info Card -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-6 border-l-4" style="border-left-color: var(--maroon)">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-[var(--gold)] text-[var(--maroon)] flex items-center justify-center font-bold text-lg">
                    <?php echo htmlspecialchars($initials); ?>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-900"><?php echo htmlspecialchars($fullName); ?></h2>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($studentId); ?> | <?php echo htmlspecialchars($course); ?> - <?php echo htmlspecialchars($yearLevel); ?></p>
                </div>
            </div>
        </div>
        
        <?php if ($error_message): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-center mb-4"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Triage Form -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r p-4 text-white" style="background: linear-gradient(135deg, var(--maroon), var(--maroon-dark))">
                <h3 class="font-bold"><i class="fas fa-notes-medical"></i> Health Assessment</h3>
                <p class="text-xs text-white/80 mt-1">Please answer the following questions to help us prioritize your visit</p>
            </div>
            
            <form method="POST" class="p-5 space-y-5" id="triageForm">
                
                <!-- Symptoms Selection -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-head-side-medical"></i> What symptoms are you experiencing? (Select all that apply)
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        <?php foreach ($symptoms_list as $symptom): ?>
                            <button type="button" class="symptom-btn text-left px-3 py-2 rounded-lg border text-sm transition-all" 
                                    style="border-color: #ddd; background: #f9f9f9"
                                    data-symptom="<?php echo htmlspecialchars($symptom); ?>">
                                <i class="fas fa-check-circle text-green-500 hidden symptom-check"></i>
                                <?php echo htmlspecialchars($symptom); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="symptoms[]" id="selected_symptoms" value="">
                    <div id="other_symptom_div" class="mt-3 hidden">
                        <input type="text" name="other_symptom" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Please specify other symptom...">
                    </div>
                </div>
                
                <!-- Pain Level -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-waveform"></i> Pain Level (0 = No pain, 10 = Worst pain imaginable)
                    </label>
                    <div class="grid grid-cols-5 gap-2">
                        <?php for ($i = 0; $i <= 10; $i+=2): ?>
                            <button type="button" class="pain-level text-center py-2 rounded-lg border text-sm transition-all" 
                                    style="border-color: #ddd; background: #f9f9f9" data-pain="<?php echo $i; ?>">
                                <div class="font-bold"><?php echo $i; ?></div>
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
                        <i class="fas fa-notes-medical"></i> Additional Notes (Optional)
                    </label>
                    <textarea name="additional_notes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" 
                              placeholder="Any other information you'd like to share..."></textarea>
                </div>
                
                <!-- Warning for emergency symptoms -->
                <div id="emergency_warning" class="hidden bg-red-50 border-l-4 border-red-500 p-3 rounded">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                        <div>
                            <p class="font-bold text-red-700">Emergency symptoms detected!</p>
                            <p class="text-sm text-red-600">Please proceed directly to the clinic. Do not wait in line.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" name="submit_triage" id="submitBtn" 
                        class="w-full py-3 rounded-xl text-white font-bold transition hover:opacity-90 disabled:opacity-50"
                        style="background: var(--maroon)" disabled>
                    <i class="fas fa-paper-plane"></i> Submit & Get Queue Number
                </button>
            </form>
        </div>
        
        <div class="text-center py-4 text-gray-400 text-xs">
            <i class="fas fa-envelope"></i> Your queue number will be sent to your registered email
        </div>
    </div>

    <script>
        // Symptoms selection
        let selectedSymptoms = [];
        const emergencySymptoms = ['Chest Pain', 'Shortness of Breath'];
        
        document.querySelectorAll('.symptom-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const symptom = this.dataset.symptom;
                const idx = selectedSymptoms.indexOf(symptom);
                
                if (idx === -1) {
                    selectedSymptoms.push(symptom);
                    this.classList.add('selected');
                    this.style.background = '#800020';
                    this.style.color = 'white';
                } else {
                    selectedSymptoms.splice(idx, 1);
                    this.classList.remove('selected');
                    this.style.background = '#f9f9f9';
                    this.style.color = '#333';
                }
                
                document.getElementById('selected_symptoms').value = selectedSymptoms.join(',');
                
                // Show other symptom input
                if (symptom === 'Other' && idx === -1) {
                    document.getElementById('other_symptom_div').classList.remove('hidden');
                } else if (symptom === 'Other') {
                    document.getElementById('other_symptom_div').classList.add('hidden');
                }
                
                // Check for emergency symptoms
                const hasEmergency = selectedSymptoms.some(s => emergencySymptoms.includes(s));
                if (hasEmergency) {
                    document.getElementById('emergency_warning').classList.remove('hidden');
                } else {
                    document.getElementById('emergency_warning').classList.add('hidden');
                }
                
                // Enable submit if at least one symptom selected
                document.getElementById('submitBtn').disabled = selectedSymptoms.length === 0;
            });
        });
        
        // Pain level selection
        document.querySelectorAll('.pain-level').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.pain-level').forEach(b => {
                    b.classList.remove('selected');
                    b.style.background = '#f9f9f9';
                    b.style.color = '#333';
                });
                this.classList.add('selected');
                this.style.background = '#800020';
                this.style.color = 'white';
                document.getElementById('pain_level').value = this.dataset.pain;
            });
        });
    </script>
</body>
</html>dd('selected');
                this.style.background = '#800020';
                this.style.color = 'white';
                document.getElementById('pain_level').value = this.dataset.pain;
            });
        });
    </script>
</body>
</html>