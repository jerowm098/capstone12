<?php
// capstonemain/pages/student/student_login.php

// db_connect.php handles session_start() with secure flags — must be first
require_once '../../config/db_connect.php';

if (isset($_SESSION['student_id']) && !empty($_SESSION['student_id'])) {
    header('Location: student_dashboard.php');
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';
$field_errors = [];
$student_number = '';
$birthdate = '';
$success_message = '';

// Rate limiting: max 5 attempts per 10 minutes per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_key = 'login_attempts_' . str_replace(['.', ':'], '_', $ip);
$rate_time_key = 'login_time_' . str_replace(['.', ':'], '_', $ip);

if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = 0;
    $_SESSION[$rate_time_key] = time();
}

// Reset counter after 10 minutes
if (time() - $_SESSION[$rate_time_key] > 600) {
    $_SESSION[$rate_key] = 0;
    $_SESSION[$rate_time_key] = time();
}

$is_rate_limited = $_SESSION[$rate_key] >= 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Security verification failed. Please refresh and try again.';
    } elseif ($is_rate_limited) {
        $error_message = 'Too many login attempts. Please wait 10 minutes before trying again.';
    } else {
        $student_number = strtoupper(trim($_POST['student_number'] ?? ''));
        $birthdate = trim($_POST['birthdate'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($student_number)) {
            $field_errors['student_number'] = 'Student number is required.';
        } elseif (!preg_match('/^\d{4}-\d{5}-BN-\d$/i', $student_number)) {
            $field_errors['student_number'] = 'Invalid format. Use: YYYY-XXXXX-BN-0';
        }

        if (empty($birthdate)) {
            $field_errors['birthdate'] = 'Birthdate is required.';
        } elseif ($birthdate > date('Y-m-d')) {
            $field_errors['birthdate'] = 'Birthdate cannot be in the future.';
        }

        if (empty($password)) {
            $field_errors['password'] = 'Password is required.';
        }

        if (empty($field_errors)) {
            $stmt = mysqli_prepare($conn,
                "SELECT u.id, u.first_name, u.last_name, u.email, u.password,
                 s.id as student_id, s.student_number, s.birthdate, s.course, s.year_level
                 FROM users u JOIN students s ON u.id = s.user_id
                 WHERE s.student_number = ? AND s.birthdate = ? AND u.role = 'student'"
            );
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $student_number, $birthdate);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                if ($user && password_verify($password, $user['password'])) {
                    // Success — reset rate limit, regenerate session
                    $_SESSION[$rate_key] = 0;
                    session_regenerate_id(true);

                    $_SESSION['student_id']     = $user['student_id'];
                    $_SESSION['user_id']        = $user['id'];
                    $_SESSION['user_email']     = $user['email'];
                    $_SESSION['user_role']      = 'student';
                    $_SESSION['user_name']      = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['student_number'] = $user['student_number'];
                    $_SESSION['course']         = $user['course'];
                    $_SESSION['year_level']     = $user['year_level'];

                    header('Location: student_dashboard.php');
                    exit();
                } else {
                    // Increment failed attempts
                    $_SESSION[$rate_key]++;
                    $remaining = max(0, 5 - $_SESSION[$rate_key]);
                    // Use a generic message to prevent user enumeration
                    $error_message = 'Invalid credentials. Please check your student number, birthdate, and password.';
                    if ($remaining > 0) {
                        $error_message .= ' (' . $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' remaining)';
                    }
                }
            } else {
                $error_message = 'A system error occurred. Please try again later.';
            }
        } else {
            $error_message = 'Please fix the errors below.';
        }
    }

    // Regenerate CSRF token after every POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['registered']) && $_GET['registered'] === 'success') {
    $success_message = 'Registration successful! Please sign in.';
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - PUPBC Carelink</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { 
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { 
                        primary: { DEFAULT: '#800020', hover: '#600018', foreground: '#ffffff' }, 
                        accent: { DEFAULT: '#c9a84c', hover: '#b89945' } 
                    },
                    boxShadow: {
                        'soft': '0 10px 40px -10px rgba(0,0,0,0.08)',
                        'inner-soft': 'inset 0 2px 4px 0 rgba(0, 0, 0, 0.02)',
                    }
                }
            }
        }
    </script>
    <style>
        /* Animations */
        .animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .animate-slide-up { animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Input Transitions */
        .input-field {
            transition: all 0.2s ease-in-out;
        }
        .input-field:focus {
            box-shadow: 0 0 0 4px rgba(128, 0, 32, 0.1);
        }

        /* Decorative Elements */
        .decoration-dots {
            background-image: radial-gradient(rgba(255,255,255,0.2) 1.5px, transparent 1.5px);
            background-size: 30px 30px;
        }
        .floating-shape { animation: float 7s ease-in-out infinite; }
        .floating-shape-delayed { animation: float 9s ease-in-out infinite 1.5s; }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(3deg); }
        }
        
        /* Spinner */
        .spinner {
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 2px solid #ffffff;
            width: 18px;
            height: 18px;
            animation: spin 0.8s linear infinite;
            display: none;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .is-loading .spinner { display: inline-block; }
        .is-loading .btn-text { display: none; }
    </style>
</head>
<body class="min-h-screen font-sans text-gray-800 antialiased bg-gray-50 selection:bg-primary selection:text-white">
    <div class="grid min-h-screen lg:grid-cols-2">
        
        <!-- LEFT PANEL - BRANDING -->
        <div class="relative hidden overflow-hidden lg:flex lg:flex-col bg-primary" aria-hidden="true">
            <!-- Background Image -->
            <img src="../../assets/images/pupbg.jpg" class="absolute inset-0 w-full h-full object-cover opacity-40 mix-blend-overlay" alt="">
            
            <!-- Gradient Overlay -->
            <div class="absolute inset-0 bg-gradient-to-br from-primary/95 via-primary/90 to-[#4a0010]/95"></div>
            
            <!-- Decorative Pattern -->
            <div class="absolute inset-0 decoration-dots opacity-20"></div>
            
            <!-- Decorative Floating Shapes -->
            <div class="absolute top-24 left-12 floating-shape">
                <div class="w-32 h-32 rounded-full bg-white/5 backdrop-blur-md border border-white/10 shadow-2xl"></div>
            </div>
            <div class="absolute bottom-32 right-12 floating-shape-delayed">
                <div class="w-48 h-48 rounded-full bg-gradient-to-tr from-accent/20 to-transparent backdrop-blur-md"></div>
            </div>
            <div class="absolute top-1/3 right-24 w-20 h-20 rounded-2xl bg-white/5 backdrop-blur-md rotate-12 floating-shape"></div>
            
            <!-- Bottom Graphic Waves -->
            <svg class="absolute bottom-0 left-0 w-full text-white/5" viewBox="0 0 1200 120" preserveAspectRatio="none" fill="currentColor">
                <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z"></path>
                <path d="M0,0V15.81c13,21.11,27.64,41.05,47.69,56.24C99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" class="opacity-50"></path>
            </svg>
            
            <!-- Content -->
            <div class="relative z-10 flex flex-col h-full">
                <!-- Logo Area -->
                <div class="flex items-center gap-3 p-10 animate-fade-in">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white shadow-lg p-1.5">
                        <img src="../../assets/images/puplogo.png" alt="PUP Logo" class="h-full w-full object-contain">
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">PUPBC <span class="text-accent">Carelink</span></h1>
                        <p class="text-xs font-medium text-white/70 uppercase tracking-wider">Health Management System</p>
                    </div>
                </div>
                
                <!-- Main Copy -->
                <div class="flex-1 flex flex-col justify-center px-12 pb-24">
                    <div class="animate-slide-up">
                        <div class="mb-6 inline-flex w-fit items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm font-medium text-white backdrop-blur-md shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            Student Portal Access
                        </div>
                        
                        <h2 class="text-5xl font-extrabold leading-[1.15] text-white tracking-tight">
                            Welcome Back,<br>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-accent to-yellow-200">PUPian!</span>
                        </h2>
                        
                        <p class="mt-6 text-lg text-white/80 max-w-md leading-relaxed">
                            Access your medical records, book appointments, and stay updated with campus health advisories seamlessly.
                        </p>
                    </div>
                    
                    <div class="mt-10 space-y-5 animate-slide-up delay-100">
                        <div class="flex items-center gap-4 text-white/90">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 backdrop-blur-sm shadow-sm border border-white/10">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-base font-medium">Instant QR Check-in</span>
                        </div>
                        <div class="flex items-center gap-4 text-white/90">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 backdrop-blur-sm shadow-sm border border-white/10">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <span class="text-base font-medium">Digital Health Records</span>
                        </div>
                        <div class="flex items-center gap-4 text-white/90">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 backdrop-blur-sm shadow-sm border border-white/10">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <span class="text-base font-medium">24/7 Record Access</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL - LOGIN FORM -->
        <div class="flex flex-col bg-white">
            <!-- Header Links -->
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-white/90 backdrop-blur-md sticky top-0 z-10">
                <a href="../../index.php" class="group flex items-center text-sm font-medium text-gray-500 hover:text-gray-900 transition-smooth">
                    <div class="mr-2 flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 group-hover:bg-gray-200 transition-smooth">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    </div>
                    Home
                </a>
                <a href="../nurse/nurse_login.php" class="inline-flex items-center gap-1.5 rounded-full bg-primary/5 px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/10 transition-smooth">
                    Nurse Portal
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
            
            <!-- Form Container -->
            <div class="flex flex-1 items-center justify-center p-6 sm:p-10">
                <div class="w-full max-w-[420px] animate-slide-up delay-200">
                    
                    <div class="text-center mb-8">
                        <div class="mx-auto mb-5 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/5 text-primary ring-1 ring-primary/10">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                        </div>
                        <h3 class="text-3xl font-bold tracking-tight text-gray-900">Student Sign In</h3>
                        <p class="mt-2 text-sm text-gray-500">Enter your credentials to access your dashboard</p>
                    </div>
                    
                    <!-- Alert Messages -->
                    <?php if ($success_message): ?>
                        <div role="alert" class="mb-6 flex items-start gap-3 rounded-xl bg-green-50 border border-green-200 p-4 text-sm text-green-800 shadow-sm animate-fade-in">
                            <svg class="h-5 w-5 shrink-0 text-green-600 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <p class="font-medium"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div role="alert" class="mb-6 flex items-start gap-3 rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-800 shadow-sm animate-fade-in">
                            <svg class="h-5 w-5 shrink-0 text-red-600 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <p class="font-medium"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-5" id="loginForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                        <!-- Student Number -->
                        <div class="space-y-2">
                            <label for="student_number" class="text-sm font-semibold text-gray-900 flex justify-between">
                                Student Number
                                <?php if (!isset($field_errors['student_number'])): ?>
                                    <span class="text-xs font-normal text-gray-400" id="format-hint">Format: 2023-XXXXX-BN-0</span>
                                <?php endif; ?>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
                                </div>
                                <input type="text" name="student_number" id="student_number"
                                    value="<?php echo htmlspecialchars($student_number, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="2023-00482-BN-0" required autocomplete="off"
                                    class="input-field flex h-12 w-full rounded-xl border bg-gray-50 pl-11 pr-10 text-sm outline-none <?php echo isset($field_errors['student_number']) ? 'border-red-300 ring-1 ring-red-100 bg-red-50/50' : 'border-gray-200 focus:bg-white focus:border-primary'; ?>"
                                    aria-invalid="<?php echo isset($field_errors['student_number']) ? 'true' : 'false'; ?>">
                                
                                <div id="format-check" class="absolute inset-y-0 right-0 pr-3.5 flex items-center hidden">
                                    <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                            </div>
                            <?php if (isset($field_errors['student_number'])): ?>
                                <p class="text-sm text-red-600 font-medium mt-1.5 flex items-center gap-1">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <?php echo htmlspecialchars($field_errors['student_number'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Birthdate -->
                        <div class="space-y-2">
                            <label for="birthdate" class="text-sm font-semibold text-gray-900">Birthdate</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9"/><path d="M12 21a9 9 0 0 0 9-9H3a9 9 0 0 0 9 9Z"/><path d="M12 12v.01"/></svg>
                                </div>
                                <input type="date" name="birthdate" id="birthdate"
                                    value="<?php echo htmlspecialchars($birthdate, ENT_QUOTES, 'UTF-8'); ?>"
                                    max="<?php echo $today; ?>" required
                                    class="input-field flex h-12 w-full rounded-xl border bg-gray-50 pl-11 pr-4 text-sm outline-none text-gray-700 <?php echo isset($field_errors['birthdate']) ? 'border-red-300 ring-1 ring-red-100 bg-red-50/50' : 'border-gray-200 focus:bg-white focus:border-primary'; ?>">
                            </div>
                            <?php if (isset($field_errors['birthdate'])): ?>
                                <p class="text-sm text-red-600 font-medium mt-1.5 flex items-center gap-1">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <?php echo htmlspecialchars($field_errors['birthdate'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Password -->
                        <div class="space-y-2">
                            <label for="password" class="text-sm font-semibold text-gray-900">Password</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </div>
                                <input type="password" name="password" id="password"
                                    placeholder="••••••••" required autocomplete="current-password"
                                    class="input-field flex h-12 w-full rounded-xl border bg-gray-50 pl-11 pr-12 text-sm outline-none text-gray-900 <?php echo isset($field_errors['password']) ? 'border-red-300 ring-1 ring-red-100 bg-red-50/50' : 'border-gray-200 focus:bg-white focus:border-primary'; ?>">
                                
                                <button type="button" id="togglePassword" aria-label="Toggle password visibility"
                                    class="absolute inset-y-0 right-0 pr-2 flex items-center h-full">
                                    <div class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-200/50 transition-smooth">
                                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </div>
                                </button>
                            </div>
                            <?php if (isset($field_errors['password'])): ?>
                                <p class="text-sm text-red-600 font-medium mt-1.5 flex items-center gap-1">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <?php echo htmlspecialchars($field_errors['password'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" id="submitBtn" class="group relative flex h-12 w-full items-center justify-center rounded-xl bg-primary text-sm font-semibold text-white shadow-md hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-smooth overflow-hidden mt-6">
                            <span class="absolute inset-0 w-full h-full bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></span>
                            <span class="relative btn-text">Sign In Securely</span>
                            <span class="relative spinner"></span>
                        </button>
                    </form>
                    
                    <!-- Registration Link -->
                    <div class="mt-8 text-center">
                        <p class="text-sm text-gray-500">
                            Don't have an account yet? 
                            <a href="student_register.php" class="font-semibold text-primary hover:text-primary-hover hover:underline transition-smooth decoration-2 underline-offset-4">Create account</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Student Number Auto-formatter & Validator
            const studentInput = document.getElementById('student_number');
            const checkmark    = document.getElementById('format-check');
            const hint         = document.getElementById('format-hint');
            const form         = document.getElementById('loginForm');
            const submitBtn    = document.getElementById('submitBtn');

            function checkFormat(input) {
                // Remove server-side error states visually on user input
                input.classList.remove('border-red-300', 'ring-red-100', 'bg-red-50/50', 'ring-1');
                
                const valid = /^\d{4}-\d{5}-BN-\d$/i.test(input.value);
                if (checkmark) {
                    if (valid) {
                        checkmark.classList.remove('hidden');
                        input.classList.add('border-green-400', 'focus:border-green-500');
                        input.classList.remove('focus:border-primary', 'border-gray-200');
                    } else {
                        checkmark.classList.add('hidden');
                        input.classList.remove('border-green-400', 'focus:border-green-500');
                        input.classList.add('focus:border-primary', 'border-gray-200');
                    }
                }
                
                if (hint) {
                    if (valid) {
                        hint.textContent = 'Valid format';
                        hint.classList.replace('text-gray-400', 'text-green-500');
                        hint.classList.add('font-medium');
                    } else {
                        hint.textContent = 'Format: 2023-XXXXX-BN-0';
                        hint.classList.replace('text-green-500', 'text-gray-400');
                        hint.classList.remove('font-medium');
                    }
                }
            }

            if (studentInput) {
                let isFormatting = false;
                studentInput.addEventListener('input', function (e) {
                    if (isFormatting || e.inputType === 'deleteContentBackward') return;
                    isFormatting = true;

                    // Extract digits
                    let digits = this.value.replace(/[^0-9]/g, '').slice(0, 9);
                    let out = '';
                    if (digits.length > 0) out = digits.slice(0, 4);
                    if (digits.length > 4) out += '-' + digits.slice(4, 9);
                    if (digits.length === 9) out += '-BN-0';
                    
                    this.value = out;
                    checkFormat(this);
                    isFormatting = false;
                });

                studentInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace') {
                        setTimeout(() => {
                            let val = this.value;
                            if (val.endsWith('-BN-0')) {
                                this.value = val.substring(0, val.length - 6); // remove '-BN-0' and last digit to feel natural
                            }
                            checkFormat(this);
                        }, 0);
                    }
                });

                // Initial check on load in case of pre-fill
                if (studentInput.value) {
                    checkFormat(studentInput);
                }
            }

            // Password visibility toggle
            const toggleBtn    = document.getElementById('togglePassword');
            const passwordField = document.getElementById('password');

            if (toggleBtn && passwordField) {
                toggleBtn.addEventListener('click', function () {
                    const isPassword = passwordField.type === 'password';
                    passwordField.type = isPassword ? 'text' : 'password';
                    
                    const eyeIcon = document.getElementById('eyeIcon');
                    if (isPassword) {
                        eyeIcon.innerHTML = '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/>';
                    } else {
                        eyeIcon.innerHTML = '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>';
                    }
                    
                    // Maintain focus on input when toggling
                    passwordField.focus();
                });
            }

            // Loading state on form submission
            if (form && submitBtn) {
                form.addEventListener('submit', function() {
                    // Only show loading if basic HTML5 validation passes
                    if (form.checkValidity()) {
                        submitBtn.classList.add('is-loading');
                        // Prevent multi-clicks while allowing the first submit to go through
                        setTimeout(() => {
                            submitBtn.disabled = true;
                        }, 50);
                    }
                });
            }
            
            // Clear specific error styles on interaction
            document.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('border-red-300', 'ring-red-100', 'bg-red-50/50', 'ring-1');
                    this.classList.add('border-gray-200', 'focus:border-primary');
                });
            });
        });
    </script>
</body>
</html>