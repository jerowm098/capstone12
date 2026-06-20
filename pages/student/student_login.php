<?php
// ============================================
// capstonemain/pages/student/student_login.php
// PUPBC CARELINK - STUDENT LOGIN
// Production-Ready | Secure | Responsive
// FIXED: Database column compatibility + Full Security
// ============================================

// --- 1. SECURE SESSION INITIALIZATION ---
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
}

// --- 2. DATABASE CONNECTION ---
require_once __DIR__ . '/../../config/db_connect.php';

// Verify database connection
if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_errno) {
    error_log('CRITICAL: Database connection failed in student_login.php - ' . ($conn->connect_error ?? 'Unknown error'));
    die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>System Error</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f8fafc}.card{background:white;padding:40px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.1);text-align:center;max-width:420px;margin:20px}.card h2{color:#800020}.card p{color:#4b5563;margin:12px 0 24px}.card a{color:#800020;font-weight:600;text-decoration:none}</style></head><body><div class="card"><h2>System Unavailable</h2><p>We are experiencing technical difficulties. Please try again in a few minutes.</p><a href="student_login.php">Refresh Page</a></div></body></html>');
}

// --- 3. REDIRECT IF ALREADY LOGGED IN ---
if (isset($_SESSION['student_id']) && !empty($_SESSION['student_id'])) {
    if (!headers_sent()) {
        header('Location: student_dashboard.php');
        exit();
    }
    echo '<script>window.location.href = "student_dashboard.php";</script>';
    exit();
}

// --- 4. CSRF PROTECTION ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 5. DETECT AVAILABLE DATABASE COLUMNS (One-time check per session) ---
if (!isset($_SESSION['db_columns_checked'])) {
    $_SESSION['db_has_is_active'] = false;
    $_SESSION['db_has_failed_attempts'] = false;
    $_SESSION['db_has_locked_until'] = false;
    $_SESSION['db_has_last_login'] = false;
    
    // Check which columns exist in users table
    $columns_result = mysqli_query($conn, "SHOW COLUMNS FROM users");
    if ($columns_result) {
        while ($col = mysqli_fetch_assoc($columns_result)) {
            $column_name = $col['Field'];
            if ($column_name === 'is_active') $_SESSION['db_has_is_active'] = true;
            if ($column_name === 'failed_login_attempts') $_SESSION['db_has_failed_attempts'] = true;
            if ($column_name === 'locked_until') $_SESSION['db_has_locked_until'] = true;
            if ($column_name === 'last_login') $_SESSION['db_has_last_login'] = true;
        }
        mysqli_free_result($columns_result);
    }
    $_SESSION['db_columns_checked'] = true;
}

// --- 6. VARIABLES & STATE MANAGEMENT ---
$error_message = '';
$field_errors = [];
$student_number = '';
$birthdate = '';
$success_message = '';

// Get client IP for rate limiting
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip_key = str_replace(['.', ':'], '_', $ip);

// --- 7. RATE LIMITING & BRUTE-FORCE PROTECTION (Session-based) ---
$rate_limit_key = 'login_attempts_' . $ip_key;
$rate_time_key = 'login_time_' . $ip_key;
$lockout_key = 'login_lockout_' . $ip_key;

// Initialize rate limiting
if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = 0;
    $_SESSION[$rate_time_key] = time();
}

// Reset counter after 15 minutes
if (time() - $_SESSION[$rate_time_key] > 900) {
    $_SESSION[$rate_limit_key] = 0;
    $_SESSION[$rate_time_key] = time();
    unset($_SESSION[$lockout_key]);
}

$is_locked_out = isset($_SESSION[$lockout_key]) && $_SESSION[$lockout_key] > time();
$is_rate_limited = $_SESSION[$rate_limit_key] >= 5;

// --- 8. HANDLE POST REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 8.1 CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Security verification failed. Please refresh the page and try again.';
        error_log("CSRF failure for IP: $ip");
    } 
    // 8.2 Check Lockout
    elseif ($is_locked_out) {
        $wait_minutes = ceil(($_SESSION[$lockout_key] - time()) / 60);
        $error_message = "Account temporarily locked. Please try again in {$wait_minutes} minute(s).";
    }
    // 8.3 Check Rate Limit
    elseif ($is_rate_limited) {
        $error_message = 'Too many login attempts. Please wait 15 minutes before trying again.';
    }
    // 8.4 Process Login
    else {
        $student_number = strtoupper(trim($_POST['student_number'] ?? ''));
        $birthdate = trim($_POST['birthdate'] ?? '');
        $password = $_POST['password'] ?? '';

        // Field Validation
        if (empty($student_number)) {
            $field_errors['student_number'] = 'Student number is required.';
        } elseif (!preg_match('/^\d{4}-\d{5}-BN-\d$/i', $student_number)) {
            $field_errors['student_number'] = 'Invalid format. Please use: YYYY-XXXXX-BN-0';
        }

        if (empty($birthdate)) {
            $field_errors['birthdate'] = 'Birthdate is required.';
        } elseif ($birthdate > date('Y-m-d')) {
            $field_errors['birthdate'] = 'Birthdate cannot be in the future.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
            $field_errors['birthdate'] = 'Please enter a valid date.';
        }

        if (empty($password)) {
            $field_errors['password'] = 'Password is required.';
        }

        // If no field errors, attempt database verification
        if (empty($field_errors)) {
            
            // DYNAMIC QUERY: Build based on available columns
            $select_fields = "u.id, u.first_name, u.last_name, u.email, u.password";
            
            // Add optional columns if they exist
            if ($_SESSION['db_has_is_active']) {
                $select_fields .= ", u.is_active";
            }
            if ($_SESSION['db_has_failed_attempts']) {
                $select_fields .= ", u.failed_login_attempts";
            }
            if ($_SESSION['db_has_locked_until']) {
                $select_fields .= ", u.locked_until";
            }
            
            $select_fields .= ", s.id as student_id, s.student_number, s.birthdate, s.course, s.year_level";
            
            $query = "SELECT $select_fields 
                      FROM users u 
                      INNER JOIN students s ON u.id = s.user_id 
                      WHERE s.student_number = ? 
                        AND s.birthdate = ? 
                        AND u.role = 'student' 
                      LIMIT 1";
            
            $stmt = mysqli_prepare($conn, $query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $student_number, $birthdate);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                if ($user) {
                    
                    // Check if account is inactive (if column exists)
                    $is_active = true;
                    if ($_SESSION['db_has_is_active']) {
                        $is_active = (bool)($user['is_active'] ?? true);
                    }
                    
                    // Check if account is locked (if column exists)
                    $is_db_locked = false;
                    if ($_SESSION['db_has_locked_until']) {
                        $is_db_locked = !empty($user['locked_until']) && strtotime($user['locked_until']) > time();
                    }
                    
                    if (!$is_active) {
                        $error_message = 'Your account is currently inactive. Please contact the clinic administrator.';
                    }
                    elseif ($is_db_locked) {
                        $error_message = 'Your account has been temporarily locked due to multiple failed attempts. Please try again later.';
                    }
                    // Verify Password
                    elseif (password_verify($password, $user['password'])) {
                        
                        // *** SUCCESSFUL LOGIN ***
                        
                        // Reset failed attempts in DB (if column exists)
                        if ($_SESSION['db_has_failed_attempts']) {
                            $reset_stmt = mysqli_prepare($conn, "UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
                            if ($reset_stmt) {
                                mysqli_stmt_bind_param($reset_stmt, "i", $user['id']);
                                mysqli_stmt_execute($reset_stmt);
                                mysqli_stmt_close($reset_stmt);
                            }
                        }
                        
                        // Update last login timestamp (if column exists)
                        if ($_SESSION['db_has_last_login']) {
                            $update_stmt = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE id = ?");
                            if ($update_stmt) {
                                mysqli_stmt_bind_param($update_stmt, "i", $user['id']);
                                mysqli_stmt_execute($update_stmt);
                                mysqli_stmt_close($update_stmt);
                            }
                        }
                        
                        // Reset IP-based rate limiting
                        $_SESSION[$rate_limit_key] = 0;
                        unset($_SESSION[$lockout_key]);
                        
                        // Session Fixation Protection: Regenerate Session ID
                        if (!headers_sent()) {
                            session_regenerate_id(true);
                        }
                        
                        // Set session variables
                        $_SESSION['student_id']     = $user['student_id'];
                        $_SESSION['user_id']        = $user['id'];
                        $_SESSION['user_email']     = $user['email'];
                        $_SESSION['user_role']      = 'student';
                        $_SESSION['user_name']      = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['student_number'] = $user['student_number'];
                        $_SESSION['course']         = $user['course'];
                        $_SESSION['year_level']     = $user['year_level'];
                        $_SESSION['last_activity']  = time();
                        
                        // Redirect to Dashboard
                        if (!headers_sent()) {
                            header('Location: student_dashboard.php');
                            exit();
                        }
                        echo '<script>window.location.href = "student_dashboard.php";</script>';
                        exit();
                        
                    } else {
                        // Failed password
                        $error_message = 'Invalid credentials. Please check your student number, birthdate, and password.';
                        
                        // Update failed attempts in DB (if column exists)
                        if ($_SESSION['db_has_failed_attempts']) {
                            $current_failed = (int)($user['failed_login_attempts'] ?? 0);
                            $new_failed_attempts = $current_failed + 1;
                            $locked_until = null;
                            
                            if ($new_failed_attempts >= 5) {
                                $locked_until = date('Y-m-d H:i:s', time() + 1800);
                                $error_message = 'Your account has been locked due to multiple failed attempts. Please try again in 30 minutes.';
                            }
                            
                            $update_fail_stmt = mysqli_prepare($conn, "UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
                            if ($update_fail_stmt) {
                                mysqli_stmt_bind_param($update_fail_stmt, "isi", $new_failed_attempts, $locked_until, $user['id']);
                                mysqli_stmt_execute($update_fail_stmt);
                                mysqli_stmt_close($update_fail_stmt);
                            }
                        }
                        
                        // Increment IP-based rate limiting
                        $_SESSION[$rate_limit_key]++;
                    }
                } else {
                    // User not found
                    $error_message = 'Invalid credentials. Please check your student number, birthdate, and password.';
                    $_SESSION[$rate_limit_key]++;
                }
                
                // Set IP lockout if rate limited
                if ($_SESSION[$rate_limit_key] >= 5) {
                    $_SESSION[$lockout_key] = time() + 900;
                }
                
            } else {
                // Database statement preparation failed
                error_log("Login prepare statement failed: " . mysqli_error($conn));
                $error_message = 'A system error occurred. Please try again later.';
            }
        } else {
            $error_message = 'Please fix the errors highlighted below.';
        }
    }

    // Regenerate CSRF token after every POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 9. HANDLE GET PARAMETERS ---
if (isset($_GET['registered']) && $_GET['registered'] === 'success') {
    $success_message = 'Registration successful! Please sign in with your credentials.';
}
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error_message = 'Your session has expired due to inactivity. Please sign in again.';
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta name="description" content="PUPBC Carelink Student Portal - Secure login for PUP Binan Campus students">
    <meta name="robots" content="noindex, nofollow">
    <title>Student Login | PUPBC Carelink</title>
    
    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { 
                extend: {
                    fontFamily: { 
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'] 
                    },
                    colors: { 
                        primary: { 
                            DEFAULT: '#800020', 
                            hover: '#600018', 
                            foreground: '#ffffff' 
                        }, 
                        accent: { 
                            DEFAULT: '#c9a84c', 
                            hover: '#b89945' 
                        } 
                    },
                    boxShadow: {
                        'soft': '0 10px 40px -10px rgba(0,0,0,0.08)',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .animate-slide-up { animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }
        
        .spinner {
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 2px solid #ffffff;
            width: 20px;
            height: 20px;
            animation: spin 0.8s linear infinite;
            display: none;
        }
        .is-loading .spinner { display: inline-block; }
        .is-loading .btn-text { display: none; }
        .is-loading { pointer-events: none; opacity: 0.8; }
        
        .input-field:focus {
            box-shadow: 0 0 0 4px rgba(128, 0, 32, 0.1);
        }
    </style>
</head>
<body class="min-h-screen font-sans text-gray-800 antialiased bg-gray-50 selection:bg-primary selection:text-white">

    <div class="grid min-h-screen lg:grid-cols-2">
        
        <!-- LEFT PANEL - BRANDING -->
        <div class="relative hidden overflow-hidden lg:flex lg:flex-col bg-primary" aria-hidden="true">
            <img src="../../assets/images/pupbg.jpg" 
                 class="absolute inset-0 w-full h-full object-cover opacity-40 mix-blend-overlay" 
                 alt="" 
                 loading="lazy">
            
            <div class="absolute inset-0 bg-gradient-to-br from-primary/95 via-primary/90 to-[#4a0010]/95"></div>
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(rgba(255,255,255,0.2) 1.5px, transparent 1.5px); background-size: 30px 30px;"></div>
            
            <div class="relative z-10 flex flex-col h-full p-10">
                <div class="flex items-center gap-3 animate-fade-in">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white shadow-lg p-1.5">
                        <img src="../../assets/images/puplogo.png" alt="PUP Logo" class="h-full w-full object-contain" loading="lazy">
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">PUPBC <span class="text-accent">Carelink</span></h1>
                        <p class="text-xs font-medium text-white/70 uppercase tracking-wider">Health Management System</p>
                    </div>
                </div>
                
                <div class="flex-1 flex flex-col justify-center animate-slide-up">
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
            </div>
        </div>

        <!-- RIGHT PANEL - LOGIN FORM -->
        <div class="flex flex-col bg-white">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-white/90 backdrop-blur-md sticky top-0 z-10">
                <a href="../../index.php" class="group flex items-center text-sm font-medium text-gray-500 hover:text-gray-900 transition-colors">
                    <div class="mr-2 flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 group-hover:bg-gray-200 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    </div>
                    Home
                </a>
                <a href="../nurse/nurse_login.php" class="inline-flex items-center gap-1.5 rounded-full bg-primary/5 px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/10 transition-colors">
                    Nurse Portal
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
            
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

                    <form method="POST" class="space-y-5" id="loginForm" novalidate autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                        <!-- Student Number Field -->
                        <div class="space-y-2">
                            <label for="student_number" class="text-sm font-semibold text-gray-900 flex justify-between">
                                Student Number
                                <span id="format-hint" class="text-xs font-normal text-gray-400">Format: 2023-XXXXX-BN-0</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
                                </div>
                                <input type="text" name="student_number" id="student_number"
                                    value="<?php echo htmlspecialchars($student_number, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="2023-00482-BN-0" required 
                                    class="input-field flex h-12 w-full rounded-xl border bg-gray-50 pl-11 pr-10 text-sm outline-none transition-all <?php echo isset($field_errors['student_number']) ? 'border-red-300 ring-1 ring-red-100 bg-red-50/50' : 'border-gray-200 focus:bg-white focus:border-primary'; ?>"
                                    aria-invalid="<?php echo isset($field_errors['student_number']) ? 'true' : 'false'; ?>"
                                    aria-describedby="format-hint">
                                
                                <div id="format-check" class="absolute inset-y-0 right-0 pr-3.5 flex items-center hidden">
                                    <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                            </div>
                            <?php if (isset($field_errors['student_number'])): ?>
                                <p class="text-sm text-red-600 font-medium mt-1.5 flex items-center gap-1" role="alert">
                                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <?php echo htmlspecialchars($field_errors['student_number'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Birthdate Field -->
                        <div class="space-y-2">
                            <label for="birthdate" class="text-sm font-semibold text-gray-900">Birthdate</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </div>
                                <input type="date" name="birthdate" id="birthdate"
                                    value="<?php echo htmlspecialchars($birthdate, ENT_QUOTES, 'UTF-8'); ?>"
                                    max="<?php echo $today; ?>" required
                                    class="input-field flex h-12 w-full rounded-xl border bg-gray-50 pl-11 pr-4 text-sm outline-none transition-all <?php echo isset($field_errors['birthdate']) ? 'border-red-300 ring-1 ring-red-100 bg-red-50/50' : 'border-gray-200 focus:bg-white focus:border-primary'; ?>"
                                    aria-invalid="<?php echo isset($field_errors['birthdate']) ? 'true' : 'false'; ?>">
                            </div>
                            <?php if (isset($field_errors['birthdate'])): ?>
                                <p class="text-sm text-red-600 font-medium mt-1.5 flex items-center gap-1" role="alert">
                                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <?php echo htmlspecialchars($field_errors['birthdate'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Password Field -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <label for="password" class="text-sm font-semibold text-gray-900">Password</label>
                                <a href="forgot_password.php" class="text-xs font-medium text-primary hover:text-primary-hover transition-colors">Forgot Password?</a>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </div>
                                <input type="password" name="password" id="password"
                                    placeholder="••••••••" required autocomplete="current-password"
                                    class="input-field flex h-12 w-full rounded-xl border bg-gray-50 pl-11 pr-12 text-sm outline-none transition-all <?php echo isset($field_errors['password']) ? 'border-red-300 ring-1 ring-red-100 bg-red-50/50' : 'border-gray-200 focus:bg-white focus:border-primary'; ?>"
                                    aria-invalid="<?php echo isset($field_errors['password']) ? 'true' : 'false'; ?>">
                                
                                <button type="button" id="togglePassword" aria-label="Show password"
                                    class="absolute inset-y-0 right-0 pr-2 flex items-center">
                                    <span class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-200/50 transition-colors">
                                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </span>
                                </button>
                            </div>
                            <?php if (isset($field_errors['password'])): ?>
                                <p class="text-sm text-red-600 font-medium mt-1.5 flex items-center gap-1" role="alert">
                                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <?php echo htmlspecialchars($field_errors['password'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Remember Me Checkbox -->
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="remember_me" id="remember_me" value="1"
                                   class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary accent-primary">
                            <label for="remember_me" class="text-sm text-gray-600 cursor-pointer select-none">Keep me signed in</label>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" id="submitBtn" 
                                class="group relative flex h-12 w-full items-center justify-center rounded-xl bg-primary text-sm font-semibold text-white shadow-md hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-all overflow-hidden mt-6">
                            <span class="absolute inset-0 w-full h-full bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></span>
                            <span class="relative btn-text flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                Sign In Securely
                            </span>
                            <span class="relative spinner"></span>
                        </button>
                    </form>
                    
                    <!-- Registration Link -->
                    <div class="mt-8 text-center">
                        <p class="text-sm text-gray-500">
                            Don't have an account yet? 
                            <a href="student_register.php" class="font-semibold text-primary hover:text-primary-hover hover:underline transition-colors decoration-2 underline-offset-4">Create account</a>
                        </p>
                    </div>
                    
                    <!-- Security Notice -->
                    <p class="mt-4 text-center text-xs text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block mr-1"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Secured by PUPBC Carelink
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // ============================================
            // STUDENT NUMBER FORMATTER & VALIDATOR
            // ============================================
            const studentInput = document.getElementById('student_number');
            const checkmark = document.getElementById('format-check');
            const hint = document.getElementById('format-hint');
            const form = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');

            function checkFormat(input) {
                input.classList.remove('border-red-300', 'ring-red-100', 'bg-red-50/50', 'ring-1');
                
                const valid = /^\d{4}-\d{5}-BN-\d$/i.test(input.value);
                if (checkmark) {
                    if (valid) {
                        checkmark.classList.remove('hidden');
                        input.classList.add('border-green-400');
                        input.classList.remove('focus:border-primary', 'border-gray-200');
                    } else {
                        checkmark.classList.add('hidden');
                        input.classList.remove('border-green-400');
                        input.classList.add('focus:border-primary', 'border-gray-200');
                    }
                }
                
                if (hint) {
                    if (valid) {
                        hint.textContent = 'Valid format ✓';
                        hint.className = 'text-xs font-medium text-green-600';
                    } else {
                        hint.textContent = 'Format: 2023-XXXXX-BN-0';
                        hint.className = 'text-xs font-normal text-gray-400';
                    }
                }
            }

            if (studentInput) {
                let isFormatting = false;
                studentInput.addEventListener('input', function(e) {
                    if (isFormatting || e.inputType === 'deleteContentBackward' || e.inputType === 'deleteByCut') return;
                    isFormatting = true;

                    let digits = this.value.replace(/[^0-9]/g, '').slice(0, 9);
                    let out = '';
                    if (digits.length > 0) out = digits.slice(0, 4);
                    if (digits.length > 4) out += '-' + digits.slice(4, 9);
                    if (digits.length === 9) out += '-BN-0';
                    
                    this.value = out;
                    checkFormat(this);
                    isFormatting = false;
                });

                studentInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace') {
                        setTimeout(() => {
                            let val = this.value;
                            if (val.endsWith('-BN-0')) {
                                this.value = val.substring(0, val.length - 6);
                            }
                            checkFormat(this);
                        }, 0);
                    }
                });

                if (studentInput.value) checkFormat(studentInput);
            }

            // ============================================
            // PASSWORD TOGGLE VISIBILITY
            // ============================================
            const toggleBtn = document.getElementById('togglePassword');
            const passwordField = document.getElementById('password');

            if (toggleBtn && passwordField) {
                toggleBtn.addEventListener('click', function() {
                    const isPassword = passwordField.type === 'password';
                    passwordField.type = isPassword ? 'text' : 'password';
                    
                    const eyeIcon = document.getElementById('eyeIcon');
                    if (eyeIcon) {
                        if (isPassword) {
                            eyeIcon.innerHTML = '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/>';
                            this.setAttribute('aria-label', 'Hide password');
                        } else {
                            eyeIcon.innerHTML = '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>';
                            this.setAttribute('aria-label', 'Show password');
                        }
                    }
                    passwordField.focus();
                });
            }

            // ============================================
            // FORM SUBMISSION LOADING STATE
            // ============================================
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    if (form.checkValidity()) {
                        submitBtn.classList.add('is-loading');
                        setTimeout(() => { submitBtn.disabled = true; }, 100);
                    }
                });
            }
            
            // ============================================
            // CLEAR ERROR STYLES ON INPUT
            // ============================================
            document.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('border-red-300', 'ring-red-100', 'bg-red-50/50', 'ring-1');
                    if (!this.classList.contains('border-green-400')) {
                        this.classList.add('border-gray-200');
                    }
                });
            });
        });
    </script>
</body>
</html>