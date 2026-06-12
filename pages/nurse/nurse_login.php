<?php
// capstonemain/pages/nurse/nurse_login.php

// db_connect.php handles session_start() with secure flags
require_once '../../config/db_connect.php';

if (isset($_SESSION['nurse_logged_in']) && $_SESSION['nurse_logged_in'] === true) {
    header('Location: nurse_dashboard.php');
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';
$field_errors = [];
$email = '';

// Rate limiting: max 5 attempts per 10 minutes per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_key = 'nurse_login_attempts_' . str_replace(['.', ':'], '_', $ip);
$rate_time_key = 'nurse_login_time_' . str_replace(['.', ':'], '_', $ip);

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
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email)) {
            $field_errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $field_errors['email'] = 'Incvalid email format.';
        }

        if (empty($password)) {
            $field_errors['password'] = 'Password is required.';
        }

        if (empty($field_errors)) {
            // HARDCODED ADMIN FOR TESTING
            if ($email === 'admin@gmail.com' && $password === 'admin123') {
                $_SESSION[$rate_key] = 0;
                session_regenerate_id(true);

                $_SESSION['nurse_logged_in'] = true;
                $_SESSION['nurse_id'] = 999;
                $_SESSION['user_id'] = 999;
                $_SESSION['nurse_name'] = 'Admin Nurse';
                $_SESSION['nurse_email'] = 'admin@gmail.com';
                $_SESSION['nurse_license'] = 'N-123456';
                $_SESSION['nurse_position'] = 'Head Nurse';

                header('Location: nurse_dashboard.php');
                exit();
            }

            $stmt = mysqli_prepare($conn, 
                "SELECT u.*, n.id as nurse_id, n.license_number, n.position 
                 FROM users u 
                 JOIN nurses n ON u.id = n.user_id 
                 WHERE u.email = ? AND u.role = 'nurse' LIMIT 1"
            );
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                if ($user && password_verify($password, $user['password'])) {
                    // Success — reset rate limit, regenerate session
                    $_SESSION[$rate_key] = 0;
                    session_regenerate_id(true);

                    $_SESSION['nurse_logged_in'] = true;
                    $_SESSION['nurse_id'] = $user['nurse_id'];
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['nurse_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['nurse_email'] = $user['email'];
                    $_SESSION['nurse_license'] = $user['license_number'];
                    $_SESSION['nurse_position'] = $user['position'];

                    header('Location: nurse_dashboard.php');
                    exit();
                } else {
                    // Increment failed attempts
                    $_SESSION[$rate_key]++;
                    $remaining = max(0, 5 - $_SESSION[$rate_key]);
                    $error_message = 'Invalid email or password.';
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - PUPBC Carelink</title>
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
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                            Clinic Staff Portal
                        </div>
                        
                        <h2 class="text-5xl font-extrabold leading-[1.15] text-white tracking-tight">
                            Care with clarity,<br>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-accent to-yellow-200">every shift.</span>
                        </h2>
                        
                        <p class="mt-6 text-lg text-white/80 max-w-md leading-relaxed">
                            Access real-time queue management, digital patient records, and seamlessly track medicine inventory.
                        </p>
                    </div>
                    
                    <div class="mt-10 space-y-5 animate-slide-up delay-100">
                        <div class="flex items-center gap-4 text-white/90">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 backdrop-blur-sm shadow-sm border border-white/10">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-base font-medium">Real-time Queue Management</span>
                        </div>
                        <div class="flex items-center gap-4 text-white/90">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 backdrop-blur-sm shadow-sm border border-white/10">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <span class="text-base font-medium">Digital Patient Records</span>
                        </div>
                        <div class="flex items-center gap-4 text-white/90">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 backdrop-blur-sm shadow-sm border border-white/10">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <span class="text-base font-medium">Medicine Inventory Tracking</span>
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
                <a href="../student/student_login.php" class="inline-flex items-center gap-1.5 rounded-full bg-primary/5 px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/10 transition-smooth">
                    Student Portal
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
            
            <!-- Form Container -->
            <div class="flex flex-1 items-center justify-center p-6 sm:p-10">
                <div class="w-full max-w-[420px] animate-slide-up delay-200">
                    
                    <div class="text-center mb-8">
                        <div class="mx-auto mb-5 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/5 text-primary ring-1 ring-primary/10">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                        </div>
                        <h3 class="text-3xl font-bold tracking-tight text-gray-900">Staff Sign In</h3>
                        <p class="mt-2 text-sm text-gray-500">Enter your credentials to access the clinic dashboard</p>
                    </div>

                    <?php if ($error_message): ?>
                        <div role="alert" class="mb-6 flex items-start gap-3 rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-800 shadow-sm animate-fade-in">
                            <svg class="h-5 w-5 shrink-0 text-red-600 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <p class="font-medium"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-5" id="loginForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                        <!-- Email -->
                        <div class="space-y-2">
                            <label for="email" class="text-sm font-semibold text-gray-900 flex justify-between">
                                Email Address
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                </div>
                                <input type="email" name="email" id="email"
                                    value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="nurse@pup.edu.ph" required autocomplete="email"
                                    class="input-field flex h-12 w-full rounded-xl border bg-gray-50 pl-11 pr-4 text-sm outline-none <?php echo isset($field_errors['email']) ? 'border-red-300 ring-1 ring-red-100 bg-red-50/50' : 'border-gray-200 focus:bg-white focus:border-primary'; ?>"
                                    aria-invalid="<?php echo isset($field_errors['email']) ? 'true' : 'false'; ?>">
                            </div>
                            <?php if (isset($field_errors['email'])): ?>
                                <p class="text-sm text-red-600 font-medium mt-1.5 flex items-center gap-1">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <?php echo htmlspecialchars($field_errors['email'], ENT_QUOTES, 'UTF-8'); ?>
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
                    
                    <div class="mt-8 rounded-lg border border-dashed border-gray-200 bg-gray-50/50 p-4 text-center">
                        <p class="text-xs text-gray-500 flex items-center justify-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Authorized clinic personnel only
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form         = document.getElementById('loginForm');
            const submitBtn    = document.getElementById('submitBtn');

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