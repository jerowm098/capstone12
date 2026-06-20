<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../PHPMailer/src/Exception.php';
require '../../PHPMailer/src/PHPMailer.php';
require '../../PHPMailer/src/SMTP.php';

// capstonemain/pages/student/student_register.php

// db_connect.php handles session_start() with secure flags — must be first
require_once '../../config/db_connect.php';

if (isset($_SESSION['student_id'])) {
    header('Location: student_dashboard.php');
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = '';

$suffixes    = ['', 'Jr.', 'Sr.', 'II', 'III', 'IV'];
$courses     = ['BSIT', 'BSCS', 'BSCE', 'BSME', 'BSEE', 'BSN', 'BSED', 'BSBA', 'BSOA', 'BSTM', 'BSHM', 'AB English', 'AB Communication'];
$year_levels = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
$blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$relations   = ['Parent', 'Sibling', 'Spouse', 'Guardian', 'Relative', 'Friend', 'Other'];
$today       = date('Y-m-d');

$form_data = [
    'student_number' => '', 'first_name' => '', 'middle_name' => '', 'last_name' => '', 'suffix' => '',
    'email' => '', 'birthdate' => '', 'course' => '', 'year_level' => '', 'blood_type' => '',
    'allergies' => '', 'medical_conditions' => '', 'emergency_contact' => '',
    'emergency_phone' => '', 'emergency_relation' => ''
];

// ─── Handle AJAX Requests for OTP ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'send_otp') {
        // THIS GETS THE EMAIL FROM THE USER'S INPUT
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit();
        }
        
        // Generate 6-digit OTP
        $otp = sprintf("%06d", random_int(0, 999999));
        
        // Store OTP in session
        $_SESSION['otp'] = [
            'code' => $otp,
            'email' => $email,
            'expires' => time() + 300 // 5 minutes
        ];
        
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'jjjjema098@gmail.com';     // SENDER email (your Gmail)
            $mail->Password = 'thyn haox nxhm vfml';       // SENDER app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            $mail->setFrom('jjjjema098@gmail.com', 'PUPBC CareLink');  // FROM: your email
            $mail->addAddress($email);                                  // TO: the user's email (DYNAMIC!)
            $mail->isHTML(true);
            $mail->Subject = 'Your OTP Code - PUPBC CareLink Registration';
            
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
                    .header { background: #800020; color: white; padding: 30px; text-align: center; }
                    .content { padding: 30px; }
                    .otp-code { font-size: 36px; font-weight: bold; color: #800020; text-align: center; letter-spacing: 5px; margin: 20px 0; padding: 15px; background: #f9fafb; border-radius: 8px; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; border-top: 1px solid #eee; }
                    .warning { color: #d54d63; font-size: 13px; margin-top: 20px; padding: 10px; background: #fdf3f4; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>PUPBC CareLink</h1>
                        <p>Email Verification</p>
                    </div>
                    <div class="content">
                        <h2>Hello!</h2>
                        <p>Thank you for registering with PUPBC CareLink. Please use the following OTP code to verify your email address:</p>
                        <div class="otp-code">' . $otp . '</div>
                        <p style="text-align: center; color: #666;">This code will expire in 5 minutes.</p>
                        <div class="warning">
                            <strong>⚠ Security Notice:</strong> If you did not request this verification, please ignore this email.
                        </div>
                    </div>
                    <div class="footer">
                        <p>© ' . date('Y') . ' PUPBC CareLink. All rights reserved.</p>
                        <p>This is an automated message, please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>';
            
            $mail->send();
            echo json_encode(['success' => true, 'message' => 'OTP sent successfully to ' . $email]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Error: ' . $mail->ErrorInfo]);
        }
        exit();
    }
    
    if ($_GET['action'] === 'verify_otp') {
        $user_otp = $_POST['otp'] ?? '';
        
        if (!isset($_SESSION['otp'])) {
            echo json_encode(['success' => false, 'message' => 'No OTP found. Please request a new one.']);
            exit();
        }
        
        if (time() > $_SESSION['otp']['expires']) {
            unset($_SESSION['otp']);
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
            exit();
        }
        
        if ($user_otp === $_SESSION['otp']['code']) {
            $_SESSION['email_verified'] = true;
            $_SESSION['verified_email'] = $_SESSION['otp']['email'];
            unset($_SESSION['otp']);
            echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP code']);
        }
        exit();
    }
}

// ─── Handle Registration Form Submission ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {

    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Security verification failed. Please refresh and try again.';
    } else {
        $form_data = [
            'student_number'    => trim($_POST['student_number'] ?? ''),
            'first_name'        => trim($_POST['first_name'] ?? ''),
            'middle_name'       => trim($_POST['middle_name'] ?? ''),
            'last_name'         => trim($_POST['last_name'] ?? ''),
            'suffix'            => trim($_POST['suffix'] ?? ''),
            'email'             => trim($_POST['email'] ?? ''),
            'birthdate'         => trim($_POST['birthdate'] ?? ''),
            'course'            => trim($_POST['course'] ?? ''),
            'year_level'        => trim($_POST['year_level'] ?? ''),
            'blood_type'        => trim($_POST['blood_type'] ?? ''),
            'allergies'         => trim($_POST['allergies'] ?? ''),
            'medical_conditions'=> trim($_POST['medical_conditions'] ?? ''),
            'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
            'emergency_phone'   => trim($_POST['emergency_phone'] ?? ''),
            'emergency_relation'=> trim($_POST['emergency_relation'] ?? '')
        ];

        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $errors = [];

        // Required field validations
        if (empty($form_data['student_number'])) $errors[] = 'Student number is required.';
        if (empty($form_data['first_name']))      $errors[] = 'First name is required.';
        if (empty($form_data['last_name']))       $errors[] = 'Last name is required.';
        if (empty($form_data['email']))           $errors[] = 'Email is required.';
        if (empty($form_data['birthdate']))       $errors[] = 'Birthdate is required.';
        if (empty($form_data['course']))          $errors[] = 'Course is required.';
        if (empty($form_data['year_level']))      $errors[] = 'Year level is required.';

        // Name format validations
        if (!empty($form_data['first_name']) && !preg_match("/^[a-zA-Z\s\-'.\/]+$/u", $form_data['first_name']))
            $errors[] = 'First name must contain letters only.';
        if (!empty($form_data['middle_name']) && !preg_match("/^[a-zA-Z\s\-'.\/]+$/u", $form_data['middle_name']))
            $errors[] = 'Middle name must contain letters only.';
        if (!empty($form_data['last_name']) && !preg_match("/^[a-zA-Z\s\-'.\/]+$/u", $form_data['last_name']))
            $errors[] = 'Last name must contain letters only.';
        if (!empty($form_data['emergency_contact']) && !preg_match("/^[a-zA-Z\s\-'.\/]+$/u", $form_data['emergency_contact']))
            $errors[] = 'Contact person name must contain letters only.';

        // Format validations
        if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL))
            $errors[] = 'Please enter a valid email address.';

        if (!empty($form_data['student_number']) && !preg_match('/^\d{4}-\d{5}-BN-\d$/', $form_data['student_number']))
            $errors[] = 'Student number format: YYYY-XXXXX-BN-0 (e.g., 2023-00482-BN-0)';

        if (!empty($form_data['birthdate']) && $form_data['birthdate'] > $today)
            $errors[] = 'Birthdate cannot be in the future.';

        if (!empty($form_data['emergency_phone'])) {
            $phone = $form_data['emergency_phone'];
            if (preg_match('/^\+639\d{9}$/', $phone)) {
                $phone = '0' . substr($phone, 3);
                $form_data['emergency_phone'] = $phone;
            }
            if (!preg_match('/^09\d{9}$/', $phone)) {
                $errors[] = 'Phone must be 09XXXXXXXXX format (11 digits) or +639XXXXXXXXX.';
            }
        }

        // Password validations
        if (empty($password)) {
            $errors[] = 'Password is required.';
        } else {
            if (strlen($password) < 8)                              $errors[] = 'Password must be at least 8 characters.';
            if (!preg_match('/[A-Z]/', $password))                  $errors[] = 'Password must contain at least 1 uppercase letter.';
            if (!preg_match('/[a-z]/', $password))                  $errors[] = 'Password must contain at least 1 lowercase letter.';
            if (!preg_match('/[0-9]/', $password))                  $errors[] = 'Password must contain at least 1 number.';
            if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $errors[] = 'Password must contain at least 1 special character.';
        }

        if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';

        // OTP Verification
        if (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== true) {
            $errors[] = 'Please verify your email address with the OTP code sent to your email.';
        } elseif ($_SESSION['verified_email'] !== $form_data['email']) {
            $errors[] = 'The verified email does not match. Please verify your email again.';
            unset($_SESSION['email_verified']);
            unset($_SESSION['verified_email']);
        }

        if (empty($errors)) {
            // Check duplicate email
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $form_data['email']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) $errors[] = 'This email is already registered.';
            mysqli_stmt_close($stmt);

            // Check duplicate student number
            $stmt2 = mysqli_prepare($conn, "SELECT id FROM students WHERE student_number = ?");
            mysqli_stmt_bind_param($stmt2, "s", $form_data['student_number']);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_store_result($stmt2);
            if (mysqli_stmt_num_rows($stmt2) > 0) $errors[] = 'This student number is already registered.';
            mysqli_stmt_close($stmt2);
        }

        if (empty($errors)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $qr_token = 'CARE-' . strtoupper(bin2hex(random_bytes(8)));

            $stmt3 = mysqli_prepare($conn, "INSERT INTO users (email, password, role, first_name, last_name, created_at) VALUES (?, ?, 'student', ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt3, "ssss", $form_data['email'], $hashed_password, $form_data['first_name'], $form_data['last_name']);
            mysqli_stmt_execute($stmt3);
            $user_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt3);

            $stmt4 = mysqli_prepare($conn, "INSERT INTO students (user_id, student_number, course, year_level, birthdate, blood_type, allergies, medical_conditions, emergency_contact, emergency_phone, emergency_relation, qr_code, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt4, "isssssssssss", $user_id, $form_data['student_number'], $form_data['course'], $form_data['year_level'], $form_data['birthdate'], $form_data['blood_type'], $form_data['allergies'], $form_data['medical_conditions'], $form_data['emergency_contact'], $form_data['emergency_phone'], $form_data['emergency_relation'], $qr_token);
            mysqli_stmt_execute($stmt4);
            mysqli_stmt_close($stmt4);

            // Clear email verification after successful registration
            unset($_SESSION['email_verified']);
            unset($_SESSION['verified_email']);

            // Regenerate CSRF before redirect
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: student_login.php?registered=success');
            exit();
        }

        if (!empty($errors)) {
            $error_message = '<ul class="list-disc list-inside space-y-1">' .
                implode('', array_map(fn($e) => '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>', $errors)) .
                '</ul>';
        }
    }

    // Regenerate CSRF token after every POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - PUPBC Carelink</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        maroon: { 50: '#fdf3f4', 100: '#fbe5e8', 200: '#f7ced4', 300: '#f0abb5', 400: '#e57a8b', 500: '#d54d63', 600: '#be314b', 700: '#9f223a', 800: '#800020', 900: '#711b2b', 950: '#400010' },
                        gold: { 400: '#c9a84c', 500: '#b89436' }
                    },
                    boxShadow: { 'glass': '0 10px 40px -10px rgba(128,0,32,0.1)' }
                }
            }
        }
    </script>
    <style>
        .transition-smooth { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        @keyframes slideUpFade { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: slideUpFade 0.5s ease-out forwards; }
        
        .step-panel { display: none; }
        .step-panel.active { display: block; animation: slideUpFade 0.4s ease-out forwards; }
        
        .field-label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.375rem; }
        .field-input { 
            width: 100%; border: 1px solid #d1d5db; border-radius: 0.5rem; background-color: #f9fafb;
            padding: 0.625rem 0.875rem; font-size: 0.875rem; color: #111827; 
            transition: all 0.2s;
        }
        .field-input:focus { background-color: #ffffff; border-color: #800020; outline: none; box-shadow: 0 0 0 4px rgba(128,0,32,0.1); }
        select.field-input { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; padding-right: 2.5rem; }
        
        .step-item { position: relative; display: flex; flex-direction: column; align-items: center; flex: 1; z-index: 10; }
        .step-circle { width: 2.25rem; height: 2.25rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 600; background: #ffffff; border: 2px solid #e5e7eb; color: #9ca3af; transition: all 0.3s; z-index: 2; }
        .step-line { position: absolute; top: 1.125rem; left: -50%; right: 50%; height: 2px; background: #e5e7eb; z-index: 1; transition: all 0.3s; }
        
        .step-item.active .step-circle { border-color: #800020; background: #800020; color: #ffffff; box-shadow: 0 0 0 4px rgba(128,0,32,0.15); }
        .step-item.done .step-circle { border-color: #16a34a; background: #16a34a; color: #ffffff; }
        .step-item.done + .step-item .step-line { background: #16a34a; }
        .step-label { margin-top: 0.5rem; font-size: 0.75rem; font-weight: 500; color: #9ca3af; transition: all 0.3s; }
        .step-item.active .step-label { color: #800020; font-weight: 600; }
        .step-item.done .step-label { color: #16a34a; }
        
        .btn-primary { background-color: #800020; color: white; padding: 0.625rem 1.25rem; font-size: 0.875rem; font-weight: 600; border-radius: 0.5rem; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: inline-flex; align-items: center; justify-content: center; }
        .btn-primary:hover { background-color: #600018; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .btn-primary:active { transform: translateY(0); }
        
        .btn-outline { background-color: #ffffff; color: #374151; border: 1px solid #d1d5db; padding: 0.625rem 1.25rem; font-size: 0.875rem; font-weight: 600; border-radius: 0.5rem; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; }
        .btn-outline:hover { background-color: #f9fafb; border-color: #9ca3af; }
        
        .pw-req { font-size: 0.75rem; display: flex; align-items: center; gap: 0.375rem; transition: color 0.2s; }
        .pw-req.valid { color: #16a34a; }
        .pw-req.invalid { color: #9ca3af; }
        
        #sendOTPBtn:disabled { background-color: #9ca3af !important; cursor: not-allowed; }
        #sendOTPBtn.loading { background-color: #6b7280 !important; pointer-events: none; }
        #verifyOTPBtn:disabled { opacity: 0.7; cursor: not-allowed; }
        
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .sending-pulse { animation: pulse 1.5s ease-in-out infinite; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 flex flex-col font-sans text-slate-900 selection:bg-maroon-800 selection:text-white relative">
    <div class="absolute top-0 left-0 right-0 h-64 bg-maroon-800 -z-10" style="clip-path: polygon(0 0, 100% 0, 100% 100%, 0 85%);"></div>

    <header class="sticky top-0 z-50 border-b border-white/10 bg-maroon-800/95 backdrop-blur-md shadow-sm">
        <div class="max-w-5xl mx-auto flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <img src="../../assets/images/clinic logo.jpg" alt="Clinic Logo" class="h-10 w-10 object-contain rounded-full border border-white/20 shadow-sm">
                <div class="flex flex-col">
                    <span class="font-bold text-white text-sm leading-tight tracking-wide">PUPBC</span>
                    <span class="font-medium text-gold-400 text-xs leading-tight">Carelink</span>
                </div>
            </div>
            <a href="student_login.php" class="text-sm text-white/80 font-medium hover:text-white transition-colors flex items-center gap-1">
                <span>Sign in</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>
    </header>

    <main class="flex-1 flex flex-col items-center justify-center px-4 py-8 sm:px-6 lg:px-8 z-10 w-full">
        <div class="w-full max-w-4xl animate-fade-in">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-white drop-shadow-md">Create Student Account</h1>
                <p class="text-maroon-100 mt-1.5 text-sm">Register to access PUPBC Carelink services and appointments.</p>
            </div>

            <?php if ($error_message): ?>
                <div id="errorBox" class="mb-6 rounded-lg bg-red-50 border-l-4 border-red-500 p-4 shadow-sm flex gap-3">
                    <svg class="h-5 w-5 text-red-500 flex-shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    <div class="text-sm text-red-700"><?php echo $error_message; ?></div>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-glass border border-gray-100 overflow-hidden">
                <div class="px-6 py-6 sm:px-8 border-b border-gray-100 bg-gray-50/50">
                    <div class="flex items-center max-w-2xl mx-auto">
                        <div class="step-item active" id="tab1" onclick="if(currentStep > 1) goToStep(1)" style="cursor:pointer">
                            <div class="step-circle" id="circle1">1</div>
                            <span class="step-label text-center">Personal Info</span>
                        </div>
                        <div class="step-item" id="tab2" onclick="if(currentStep > 2) goToStep(2)" style="cursor:pointer">
                            <div class="step-line" id="line1"></div>
                            <div class="step-circle" id="circle2">2</div>
                            <span class="step-label text-center">Account details</span>
                        </div>
                        <div class="step-item" id="tab3">
                            <div class="step-line" id="line2"></div>
                            <div class="step-circle" id="circle3">3</div>
                            <span class="step-label text-center">Medical setup</span>
                        </div>
                    </div>
                </div>

                <form method="POST" id="registerForm" novalidate class="p-6 sm:p-8">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <!-- STEP 1: Personal Info -->
                    <div class="step-panel active" id="panel1">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6">Personal Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                            <div class="col-span-1 md:col-span-4">
                                <label for="first_name" class="field-label">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($form_data['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="given-name" placeholder="Juan" class="field-input">
                            </div>
                            <div class="col-span-1 md:col-span-3">
                                <label for="middle_name" class="field-label">Middle Name</label>
                                <input type="text" name="middle_name" id="middle_name" value="<?php echo htmlspecialchars($form_data['middle_name'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="additional-name" placeholder="Optional" class="field-input">
                            </div>
                            <div class="col-span-1 md:col-span-3">
                                <label for="last_name" class="field-label">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($form_data['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="family-name" placeholder="Dela Cruz" class="field-input">
                            </div>
                            <div class="col-span-1 md:col-span-2">
                                <label for="suffix" class="field-label">Suffix</label>
                                <select name="suffix" id="suffix" class="field-input">
                                    <?php foreach ($suffixes as $suf): ?>
                                        <option value="<?php echo htmlspecialchars($suf, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form_data['suffix'] === $suf ? 'selected' : ''; ?>><?php echo htmlspecialchars($suf ?: 'None', ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-span-1 md:col-span-4">
                                <label for="student_number" class="field-label">Student Number <span class="text-red-500">*</span></label>
                                <input type="text" name="student_number" id="student_number" value="<?php echo htmlspecialchars($form_data['student_number'], ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="off" placeholder="2023-00482-BN-0" class="field-input font-mono">
                            </div>
                            <div class="col-span-1 md:col-span-4">
                                <label for="reg_birthdate" class="field-label">Birthdate <span class="text-red-500">*</span></label>
                                <input type="date" name="birthdate" id="reg_birthdate" value="<?php echo htmlspecialchars($form_data['birthdate'], ENT_QUOTES, 'UTF-8'); ?>" required max="<?php echo $today; ?>" class="field-input">
                            </div>
                            <div class="col-span-1 md:col-span-4">
                                <label for="reg_email" class="field-label">Email Address <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="email" name="email" id="reg_email" value="<?php echo htmlspecialchars($form_data['email'], ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="email" placeholder="student@example.com" class="field-input pr-28">
                                    <button type="button" id="sendOTPBtn" onclick="sendOTP()" class="absolute right-1 top-1 bottom-1 px-3 bg-maroon-800 text-white text-xs font-medium rounded-md hover:bg-maroon-700 transition-colors">
                                        Send OTP
                                    </button>
                                </div>
                                <div id="otpSection" class="mt-2 hidden">
                                    <div class="flex gap-2">
                                        <input type="text" id="otpInput" placeholder="Enter 6-digit OTP" maxlength="6" class="field-input flex-1 text-center font-mono text-lg tracking-widest" autocomplete="off">
                                        <button type="button" id="verifyOTPBtn" onclick="verifyOTP()" class="px-4 bg-green-600 text-white text-xs font-medium rounded-md hover:bg-green-700 transition-colors whitespace-nowrap">
                                            Verify
                                        </button>
                                    </div>
                                    <p id="otpMessage" class="text-xs mt-1.5"></p>
                                    <input type="hidden" id="otpVerified" name="otp_verified" value="0">
                                </div>
                            </div>
                            <div class="col-span-1 md:col-span-6">
                                <label for="course" class="field-label">Course <span class="text-red-500">*</span></label>
                                <select name="course" id="course" required class="field-input">
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form_data['course'] === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-span-1 md:col-span-6">
                                <label for="year_level" class="field-label">Year Level <span class="text-red-500">*</span></label>
                                <select name="year_level" id="year_level" required class="field-input">
                                    <option value="">-- Select Year --</option>
                                    <?php foreach ($year_levels as $yl): ?>
                                        <option value="<?php echo htmlspecialchars($yl, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form_data['year_level'] === $yl ? 'selected' : ''; ?>><?php echo htmlspecialchars($yl, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end mt-8 border-t border-gray-100 pt-6">
                            <button type="button" onclick="nextStep(1)" class="btn-primary">
                                Next Step
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: Account & Security -->
                    <div class="step-panel" id="panel2">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6">Account Security</h2>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div class="space-y-5">
                                <div>
                                    <label for="password" class="field-label">Password <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" name="password" id="password" required autocomplete="new-password" placeholder="Create a strong password" class="field-input pr-10">
                                        <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                                            <svg id="eyeIcon1" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </button>
                                    </div>
                                    <div class="mt-3">
                                        <div class="flex gap-1 h-1.5 w-full">
                                            <div id="str-1" class="h-full flex-1 rounded-full bg-gray-200 transition-colors duration-300"></div>
                                            <div id="str-2" class="h-full flex-1 rounded-full bg-gray-200 transition-colors duration-300"></div>
                                            <div id="str-3" class="h-full flex-1 rounded-full bg-gray-200 transition-colors duration-300"></div>
                                            <div id="str-4" class="h-full flex-1 rounded-full bg-gray-200 transition-colors duration-300"></div>
                                            <div id="str-5" class="h-full flex-1 rounded-full bg-gray-200 transition-colors duration-300"></div>
                                        </div>
                                        <p id="strengthLabel" class="text-xs font-medium text-gray-500 mt-1.5 text-right">Password strength</p>
                                    </div>
                                </div>
                                <div>
                                    <label for="confirm_password" class="field-label">Confirm Password <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password" placeholder="Re-enter your password" class="field-input pr-10">
                                        <button type="button" id="toggleConfirmPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                                            <svg id="eyeIcon2" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </button>
                                    </div>
                                    <div id="confirmMatch" class="mt-2 text-xs text-gray-500 flex items-center gap-1.5 opacity-0 transition-opacity">
                                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span>Passwords match</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-5 border border-gray-100 flex flex-col justify-center">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3">Your password must contain:</h3>
                                <div class="space-y-3">
                                    <div id="req-length" class="pw-req invalid items-start"><svg class="flex-shrink-0 mt-0.5 w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> <span class="leading-snug">At least 8 characters</span></div>
                                    <div id="req-upper" class="pw-req invalid items-start"><svg class="flex-shrink-0 mt-0.5 w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> <span class="leading-snug">One uppercase letter (A-Z)</span></div>
                                    <div id="req-lower" class="pw-req invalid items-start"><svg class="flex-shrink-0 mt-0.5 w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> <span class="leading-snug">One lowercase letter (a-z)</span></div>
                                    <div id="req-number" class="pw-req invalid items-start"><svg class="flex-shrink-0 mt-0.5 w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> <span class="leading-snug">One number (0-9)</span></div>
                                    <div id="req-special" class="pw-req invalid items-start"><svg class="flex-shrink-0 mt-0.5 w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> <span class="leading-snug">One special character (!@#$%^&*)</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between mt-8 border-t border-gray-100 pt-6">
                            <button type="button" onclick="goToStep(1)" class="btn-outline">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                Back
                            </button>
                            <button type="button" onclick="nextStep(2)" class="btn-primary">
                                Next Step
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 3: Medical & Emergency -->
                    <div class="step-panel" id="panel3">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6">Medical & Emergency Setup</h2>
                        <div class="space-y-6">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Medical Information (Optional)</h3>
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                                    <div class="col-span-1 md:col-span-3">
                                        <label for="blood_type" class="field-label">Blood Type</label>
                                        <select name="blood_type" id="blood_type" class="field-input">
                                            <option value="">Unknown</option>
                                            <?php foreach ($blood_types as $bt): ?>
                                                <option value="<?php echo htmlspecialchars($bt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form_data['blood_type'] === $bt ? 'selected' : ''; ?>><?php echo htmlspecialchars($bt, ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-span-1 md:col-span-9">
                                        <label for="allergies" class="field-label">Allergies</label>
                                        <input type="text" name="allergies" id="allergies" value="<?php echo htmlspecialchars($form_data['allergies'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., Penicillin, Peanuts (comma separated)" class="field-input">
                                    </div>
                                    <div class="col-span-1 md:col-span-12">
                                        <label for="medical_conditions" class="field-label">Medical Conditions</label>
                                        <input type="text" name="medical_conditions" id="medical_conditions" value="<?php echo htmlspecialchars($form_data['medical_conditions'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., Asthma, Hypertension (comma separated)" class="field-input">
                                    </div>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Emergency Contact</h3>
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                                    <div class="col-span-1 md:col-span-5">
                                        <label for="emergency_contact" class="field-label">Contact Person</label>
                                        <input type="text" name="emergency_contact" id="emergency_contact" value="<?php echo htmlspecialchars($form_data['emergency_contact'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Full Name" class="field-input">
                                    </div>
                                    <div class="col-span-1 md:col-span-3">
                                        <label for="emergency_relation" class="field-label">Relationship</label>
                                        <select name="emergency_relation" id="emergency_relation" class="field-input">
                                            <option value="">-- Select --</option>
                                            <?php foreach ($relations as $rel): ?>
                                                <option value="<?php echo htmlspecialchars($rel, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form_data['emergency_relation'] === $rel ? 'selected' : ''; ?>><?php echo htmlspecialchars($rel, ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-span-1 md:col-span-4">
                                        <label for="emergency_phone" class="field-label">Phone Number</label>
                                        <div class="relative">
                                            <input type="tel" name="emergency_phone" id="emergency_phone" value="<?php echo htmlspecialchars($form_data['emergency_phone'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="09XXXXXXXXX" autocomplete="tel" maxlength="11" inputmode="numeric" class="field-input font-mono pl-10">
                                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                            </div>
                                        </div>
                                        <p class="mt-1 text-[11px] text-gray-500" id="phone-hint">Format: 09XXXXXXXXX (11 digits)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-8 bg-gray-50 p-4 rounded-lg border border-gray-100 flex items-start gap-3 transition-colors hover:bg-gray-100">
                            <div class="flex items-center h-5 mt-0.5">
                                <input id="terms" type="checkbox" required class="w-4 h-4 text-[#800020] bg-white border-gray-300 rounded focus:ring-[#800020]/20 focus:ring-2 cursor-pointer transition-all">
                            </div>
                            <label for="terms" class="text-sm text-gray-600 leading-snug cursor-pointer select-none">
                                By registering, you agree to the <a href="#" class="text-[#800020] font-medium hover:underline">Terms of Service</a> and <a href="#" class="text-[#800020] font-medium hover:underline">Privacy Policy</a> of PUPBC Carelink.
                            </label>
                        </div>
                        <div class="flex justify-between mt-8 border-t border-gray-100 pt-6">
                            <button type="button" onclick="goToStep(2)" class="btn-outline">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                Back
                            </button>
                            <button type="submit" id="submitBtn" class="btn-primary shadow-lg shadow-[#800020]/20">
                                Create Account
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="mt-8 text-center text-sm text-gray-500 mb-8 pb-8">
                Already have an account? <a href="student_login.php" class="text-maroon-800 font-semibold hover:underline transition-colors">Sign in here</a>
            </div>
        </div>
    </main>
    
    <script>
        let currentStep = 1;
        const TOTAL_STEPS = 3;

        <?php if ($error_message && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])): ?>
        window.addEventListener('DOMContentLoaded', () => {
            const step1Fields = ['first_name','last_name','student_number','reg_birthdate','reg_email','course','year_level'];
            const step1Empty  = step1Fields.some(id => { const el = document.getElementById(id); return el && !el.value.trim(); });
            if (step1Empty) { goToStep(1); }
            else {
                const step2Fields = ['password','confirm_password'];
                const step2Empty  = step2Fields.some(id => { const el = document.getElementById(id); return el && !el.value.trim(); });
                goToStep(step2Empty ? 2 : 3);
            }
        });
        <?php endif; ?>

        function goToStep(n) {
            for (let i = 1; i <= TOTAL_STEPS; i++) {
                const panel = document.getElementById('panel' + i);
                const item = document.getElementById('tab' + i);
                const circle = document.getElementById('circle' + i);
                if(panel) panel.classList.toggle('active', i === n);
                if (item && circle) {
                    item.classList.remove('active', 'done');
                    if (i < n) { item.classList.add('done'); circle.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>'; }
                    else if (i === n) { item.classList.add('active'); circle.innerHTML = i; }
                    else { circle.innerHTML = i; }
                }
            }
            currentStep = n;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function nextStep(from) {
            document.querySelectorAll('.error-text').forEach(e => e.remove());
            document.querySelectorAll('.border-red-500').forEach(e => e.classList.remove('border-red-500'));
            let isValid = true;

            if (from === 1) {
                const otpVerified = document.getElementById('otpVerified');
                if (!otpVerified || otpVerified.value !== '1') {
                    showError(document.getElementById('reg_email'), 'Please verify your email with OTP before proceeding');
                    isValid = false;
                }
                const required = [
                    { id: 'first_name', msg: 'First name is required' },
                    { id: 'last_name', msg: 'Last name is required' },
                    { id: 'student_number', msg: 'Student number is required' },
                    { id: 'reg_birthdate', msg: 'Birthdate is required' },
                    { id: 'reg_email', msg: 'Email is required' },
                    { id: 'course', msg: 'Course is required' },
                    { id: 'year_level', msg: 'Year level is required' },
                ];
                required.forEach(f => {
                    const el = document.getElementById(f.id);
                    if (!el || !el.value.trim()) { showError(el, f.msg); isValid = false; }
                });
                if (isValid) {
                    const sn = document.getElementById('student_number');
                    if (!/^\d{4}-\d{5}-BN-\d$/.test(sn.value)) { showError(sn, 'Format must be YYYY-XXXXX-BN-0'); isValid = false; }
                    const em = document.getElementById('reg_email');
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em.value)) { showError(em, 'Valid email required'); isValid = false; }
                }
            } else if (from === 2) {
                const pw = document.getElementById('password');
                const cpw = document.getElementById('confirm_password');
                const pwVal = pw.value;
                if (!pwVal) { showError(pw, 'Password is required'); isValid = false; }
                else if (pwVal.length < 8) { showError(pw, 'Min 8 characters'); isValid = false; }
                else if (!/[A-Z]/.test(pwVal)) { showError(pw, 'Needs uppercase'); isValid = false; }
                else if (!/[a-z]/.test(pwVal)) { showError(pw, 'Needs lowercase'); isValid = false; }
                else if (!/[0-9]/.test(pwVal)) { showError(pw, 'Needs a number'); isValid = false; }
                else if (!/[!@#$%^&*(),.?":{}|<>]/.test(pwVal)) { showError(pw, 'Needs special char'); isValid = false; }
                if (isValid && pwVal !== cpw.value) { showError(cpw, 'Passwords do not match'); isValid = false; }
            }
            if (isValid) goToStep(from + 1);
        }

        function showError(el, msg) {
            if (!el) return;
            el.classList.add('border-red-500');
            const err = document.createElement('p');
            err.className = 'error-text text-red-500 text-xs mt-1.5 font-medium animate-fade-in flex items-center gap-1';
            err.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> ' + msg;
            el.parentNode.appendChild(err);
            el.addEventListener('input', function() {
                this.classList.remove('border-red-500');
                if (this.parentNode.querySelector('.error-text')) this.parentNode.querySelector('.error-text').remove();
            }, { once: true });
        }

        // ── OTP Functions ─────────────────────────────────────────────────
        let otpTimer;
        let otpTimeout;

        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.getElementById('otpInput');
            if (otpInput) {
                otpInput.addEventListener('keydown', function(e) {
                    const allowedKeys = ['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
                    if (allowedKeys.includes(e.key)) return;
                    if ((e.ctrlKey || e.metaKey) && ['a', 'c', 'v', 'x'].includes(e.key.toLowerCase())) return;
                    if (!/^\d$/.test(e.key)) e.preventDefault();
                    if (this.value.length >= 6 && !allowedKeys.includes(e.key)) e.preventDefault();
                });
                otpInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                });
                otpInput.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = (e.clipboardData || window.clipboardData).getData('text');
                    const numbersOnly = pastedData.replace(/[^0-9]/g, '').slice(0, 6);
                    this.value = numbersOnly;
                    if (this.value.length === 6) setTimeout(() => verifyOTP(), 100);
                });
                otpInput.addEventListener('keyup', function(e) {
                    if (this.value.length === 6 && /^\d{6}$/.test(this.value)) verifyOTP();
                });
            }
        });

        function sendOTP() {
            const emailInput = document.getElementById('reg_email');
            const email = emailInput.value.trim();
            const sendBtn = document.getElementById('sendOTPBtn');
            const otpSection = document.getElementById('otpSection');
            const otpMessage = document.getElementById('otpMessage');
            
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                otpSection.classList.remove('hidden');
                otpMessage.textContent = 'Please enter a valid email first';
                otpMessage.className = 'text-xs mt-1.5 text-red-500 font-medium';
                emailInput.focus();
                return;
            }
            
            sendBtn.disabled = true;
            sendBtn.textContent = 'Sending...';
            sendBtn.classList.add('loading', 'sending-pulse');
            
            const formData = new FormData();
            formData.append('email', email);
            
            fetch('student_register.php?action=send_otp', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    otpSection.classList.remove('hidden');
                    otpMessage.textContent = 'OTP sent to ' + email + '! Valid for 5 minutes.';
                    otpMessage.className = 'text-xs mt-1.5 text-green-600 font-medium';
                    document.getElementById('otpInput').value = '';
                    document.getElementById('otpInput').focus();
                    
                    let seconds = 60;
                    sendBtn.textContent = 'Resend (' + seconds + 's)';
                    sendBtn.classList.remove('sending-pulse');
                    
                    clearInterval(otpTimer);
                    otpTimer = setInterval(() => {
                        seconds--;
                        sendBtn.textContent = 'Resend (' + seconds + 's)';
                        if (seconds <= 0) {
                            clearInterval(otpTimer);
                            sendBtn.disabled = false;
                            sendBtn.textContent = 'Send OTP';
                            sendBtn.classList.remove('loading');
                        }
                    }, 1000);
                    
                    clearTimeout(otpTimeout);
                    otpTimeout = setTimeout(() => {
                        if (document.getElementById('otpVerified').value !== '1') {
                            otpMessage.textContent = 'OTP has expired. Please request a new one.';
                            otpMessage.className = 'text-xs mt-1.5 text-red-500 font-medium';
                            document.getElementById('otpInput').value = '';
                            document.getElementById('otpVerified').value = '0';
                        }
                    }, 300000);
                    
                } else {
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'Send OTP';
                    sendBtn.classList.remove('loading', 'sending-pulse');
                    otpSection.classList.remove('hidden');
                    otpMessage.textContent = data.message || 'Failed to send OTP';
                    otpMessage.className = 'text-xs mt-1.5 text-red-500 font-medium';
                }
            })
            .catch(error => {
                sendBtn.disabled = false;
                sendBtn.textContent = 'Send OTP';
                sendBtn.classList.remove('loading', 'sending-pulse');
                otpSection.classList.remove('hidden');
                otpMessage.textContent = 'Error sending OTP. Please try again.';
                otpMessage.className = 'text-xs mt-1.5 text-red-500 font-medium';
            });
        }

        function verifyOTP() {
            const otpInput = document.getElementById('otpInput');
            const verifyBtn = document.getElementById('verifyOTPBtn');
            const otpMessage = document.getElementById('otpMessage');
            const otpVerified = document.getElementById('otpVerified');
            const otp = otpInput.value.trim();
            
            if (otp.length !== 6) {
                otpMessage.textContent = 'Please enter a 6-digit OTP';
                otpMessage.className = 'text-xs mt-1.5 text-red-500 font-medium';
                return;
            }
            
            verifyBtn.disabled = true;
            verifyBtn.textContent = 'Verifying...';
            
            const formData = new FormData();
            formData.append('otp', otp);
            
            fetch('student_register.php?action=verify_otp', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    otpVerified.value = '1';
                    otpInput.classList.add('border-green-500', 'bg-green-50');
                    otpInput.readOnly = true;
                    
                    const sendBtn = document.getElementById('sendOTPBtn');
                    sendBtn.disabled = true;
                    sendBtn.textContent = 'Verified ✓';
                    sendBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                    sendBtn.classList.remove('bg-maroon-800', 'loading', 'sending-pulse');
                    
                    otpMessage.textContent = '✓ Email verified successfully!';
                    otpMessage.className = 'text-xs mt-1.5 text-green-600 font-medium flex items-center gap-1';
                    
                    verifyBtn.textContent = '✓';
                    verifyBtn.classList.add('bg-green-600');
                    verifyBtn.disabled = true;
                    
                    clearInterval(otpTimer);
                    clearTimeout(otpTimeout);
                } else {
                    otpVerified.value = '0';
                    otpMessage.textContent = data.message || 'Invalid OTP';
                    otpMessage.className = 'text-xs mt-1.5 text-red-500 font-medium';
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'Verify';
                    otpInput.value = '';
                    otpInput.focus();
                }
            })
            .catch(error => {
                otpMessage.textContent = 'Error verifying OTP';
                otpMessage.className = 'text-xs mt-1.5 text-red-500 font-medium';
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify';
            });
        }

        // ── Password Strength ─────────────────────────────────────────────
        const pwdInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        
        function updateReq(id, valid) {
            const el = document.getElementById(id);
            if (!el) return;
            if (valid) { el.classList.remove('invalid', 'text-[#9ca3af]'); el.classList.add('valid', 'text-green-600'); }
            else { el.classList.remove('valid', 'text-green-600'); el.classList.add('invalid', 'text-[#9ca3af]'); }
        }

        if (pwdInput) {
            pwdInput.addEventListener('input', function() {
                const val = this.value;
                const checks = { length: val.length >= 8, upper: /[A-Z]/.test(val), lower: /[a-z]/.test(val), number: /[0-9]/.test(val), special: /[!@#$%^&*(),.?":{}|<>]/.test(val) };
                updateReq('req-length', checks.length); updateReq('req-upper', checks.upper); updateReq('req-lower', checks.lower);
                updateReq('req-number', checks.number); updateReq('req-special', checks.special);
                const score = Object.values(checks).filter(Boolean).length;
                const colors = ['bg-gray-200', 'bg-red-500', 'bg-orange-400', 'bg-yellow-400', 'bg-blue-500', 'bg-green-500'];
                const labels = ['Password strength', 'Weak', 'Fair', 'Good', 'Strong', 'Excellent'];
                for(let i=1; i<=5; i++) { const bar = document.getElementById('str-' + i); if(bar) bar.className = 'h-full flex-1 rounded-full transition-colors duration-300 ' + (i <= score ? colors[score] : 'bg-gray-200'); }
                const labelEl = document.getElementById('strengthLabel');
                if(labelEl) { labelEl.textContent = labels[score]; labelEl.className = 'text-xs font-medium mt-1.5 text-right ' + (score === 0 ? 'text-gray-500' : 'text-gray-700'); }
                checkMatch();
            });
        }

        if (confirmInput) confirmInput.addEventListener('input', checkMatch);

        function checkMatch() {
            const pw = pwdInput?.value || '';
            const cpw = confirmInput?.value || '';
            const matchEl = document.getElementById('confirmMatch');
            if(!matchEl) return;
            if (!cpw) { matchEl.style.opacity = '0'; }
            else if (pw === cpw) { matchEl.style.opacity = '1'; matchEl.className = 'mt-2 text-xs font-medium flex items-center gap-1.5 transition-opacity text-green-600'; matchEl.querySelector('span').textContent = 'Passwords match'; }
            else { matchEl.style.opacity = '1'; matchEl.className = 'mt-2 text-xs font-medium flex items-center gap-1.5 transition-opacity text-red-500'; matchEl.querySelector('span').textContent = 'Passwords do not match'; }
        }

        // ── Input Formatters ─────────────────────────────────────────────
        const studInp = document.getElementById('student_number');
        if (studInp) {
            studInp.addEventListener('input', function() { let digits = this.value.replace(/[^0-9]/g, '').slice(0, 9); let out = ''; if (digits.length > 0) out = digits.slice(0, 4); if (digits.length > 4) out += '-' + digits.slice(4, 9); if (digits.length === 9) out += '-BN-0'; this.value = out; });
        }

        const phoneInp = document.getElementById('emergency_phone');
        if (phoneInp) {
            phoneInp.addEventListener('input', function() { let raw = this.value; if (raw.startsWith('+63')) raw = '09' + raw.slice(3); else if (raw.startsWith('63')) raw = '09' + raw.slice(2); let digits = raw.replace(/[^0-9]/g, ''); if (digits.length >= 2 && digits.slice(0, 2) !== '09') digits = '09' + digits.replace(/^0+/, ''); this.value = digits.slice(0, 11); });
        }

        function bindToggle(btnId, inpId) {
            const btn = document.getElementById(btnId); const inp = document.getElementById(inpId);
            if(!btn || !inp) return;
            btn.addEventListener('click', () => { inp.type = inp.type === 'password' ? 'text' : 'password'; });
        }
        bindToggle('togglePassword', 'password');
        bindToggle('toggleConfirmPassword', 'confirm_password');
        
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            if (!document.getElementById('otpVerified') || document.getElementById('otpVerified').value !== '1') { e.preventDefault(); goToStep(1); showError(document.getElementById('reg_email'), 'Please verify your email with OTP before submitting'); return; }
            if(!document.getElementById('terms').checked) { e.preventDefault(); showError(document.getElementById('terms').parentNode, 'You must agree to the terms'); }
        });
    </script>
</body>
</html>