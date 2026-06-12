<?php
// ============================================
// capstonemain/pages/student/student_profile.php
// PUPBC CARELINK - STUDENT PROFILE MANAGEMENT
// FIXED: Matches actual database schema
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

// --- 2. AUTHENTICATION GUARD ---
if (!isset($_SESSION['student_id']) || empty($_SESSION['student_id'])) {
    error_log("Unauthorized profile access attempt from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header('Location: student_login.php');
    exit();
}

// --- 3. SESSION TIMEOUT VALIDATION (30 minutes) ---
$session_timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    session_unset();
    session_destroy();
    header('Location: student_login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// --- 4. DATABASE CONNECTION ---
require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_errno) {
    error_log("CRITICAL: Database connection failed in student_profile.php");
    die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>System Error</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f8fafc}.card{background:white;padding:40px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.1);text-align:center;max-width:420px;margin:20px}.card h2{color:#800020}.card p{color:#4b5563;margin:12px 0 24px}.card a{color:#800020;font-weight:600;text-decoration:none}</style></head><body><div class="card"><h2>System Unavailable</h2><p>We are experiencing technical difficulties. Please try again in a few minutes.</p><a href="student_login.php">Return to Login</a></div></body></html>');
}

// --- 5. DETECT AVAILABLE DATABASE COLUMNS ---
if (!isset($_SESSION['db_profile_cols_checked'])) {
    $_SESSION['db_has_profile_photo'] = false;
    $_SESSION['db_has_account_status'] = false;
    $_SESSION['db_has_last_login'] = false;
    $_SESSION['db_has_address'] = false;
    $_SESSION['db_has_contact_number'] = false;
    $_SESSION['db_has_gender'] = false;
    
    // Check users table
    $users_cols = mysqli_query($conn, "SHOW COLUMNS FROM users");
    if ($users_cols) {
        while ($col = mysqli_fetch_assoc($users_cols)) {
            $name = $col['Field'];
            if ($name === 'profile_photo') $_SESSION['db_has_profile_photo'] = true;
            if ($name === 'account_status') $_SESSION['db_has_account_status'] = true;
            if ($name === 'last_login') $_SESSION['db_has_last_login'] = true;
        }
        mysqli_free_result($users_cols);
    }
    
    // Check students table
    $students_cols = mysqli_query($conn, "SHOW COLUMNS FROM students");
    if ($students_cols) {
        while ($col = mysqli_fetch_assoc($students_cols)) {
            $name = $col['Field'];
            if ($name === 'address') $_SESSION['db_has_address'] = true;
            if ($name === 'contact_number') $_SESSION['db_has_contact_number'] = true;
            if ($name === 'gender') $_SESSION['db_has_gender'] = true;
        }
        mysqli_free_result($students_cols);
    }
    
    $_SESSION['db_profile_cols_checked'] = true;
}

// --- 6. CSRF PROTECTION ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 7. VARIABLE INITIALIZATION ---
$student_id = (int)$_SESSION['student_id'];
$current_page = basename($_SERVER['PHP_SELF']);

$success_message = '';
$error_message = '';
$profile_errors = [];
$password_errors = [];
$photo_error = '';

// --- 8. BUILD DYNAMIC SELECT QUERY ---
$select_parts = [
    'u.id AS user_id',
    'u.first_name', 
    'u.last_name', 
    'u.email',
    's.student_number',
    's.course',
    's.year_level',
    's.birthdate',
    's.blood_type',
    's.allergies',
    's.medical_conditions',
    's.emergency_contact',
    's.emergency_phone',
    's.emergency_relation',
    's.qr_code'
];

// Optional users columns
if ($_SESSION['db_has_profile_photo']) {
    $select_parts[] = 'u.profile_photo';
}
if ($_SESSION['db_has_account_status']) {
    $select_parts[] = 'u.account_status';
}
if ($_SESSION['db_has_last_login']) {
    $select_parts[] = 'u.last_login';
}

// Optional students columns
if ($_SESSION['db_has_address']) {
    $select_parts[] = 's.address';
}
if ($_SESSION['db_has_contact_number']) {
    $select_parts[] = 's.contact_number';
}
if ($_SESSION['db_has_gender']) {
    $select_parts[] = 's.gender';
}

$select_query = "SELECT " . implode(', ', $select_parts) . " 
                 FROM students s 
                 INNER JOIN users u ON s.user_id = u.id 
                 WHERE s.id = ? 
                 LIMIT 1";

// --- 9. FETCH STUDENT DATA ---
$student = [];
$stmt = mysqli_prepare($conn, $select_query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$student) {
        error_log("Student ID $student_id not found in database");
        session_destroy();
        header('Location: student_login.php?error=profile_not_found');
        exit();
    }
} else {
    error_log("Profile fetch prepare failed: " . mysqli_error($conn));
    die('A system error occurred. Please try again later.');
}

// --- 10. EXTRACT STUDENT DATA ---
$user_id = (int)$student['user_id'];
$first_name = $student['first_name'] ?? '';
$last_name = $student['last_name'] ?? '';
$email = $student['email'] ?? '';
$student_number = $student['student_number'] ?? 'N/A';
$course = $student['course'] ?? 'N/A';
$year_level = $student['year_level'] ?? 'N/A';
$birthdate = $student['birthdate'] ?? '';
$blood_type = $student['blood_type'] ?? '';
$allergies = $student['allergies'] ?? '';
$medical_conditions = $student['medical_conditions'] ?? '';
$emergency_contact = $student['emergency_contact'] ?? '';
$emergency_phone = $student['emergency_phone'] ?? '';
$emergency_relation = $student['emergency_relation'] ?? '';
$qr_code = $student['qr_code'] ?? null;

// Optional fields
$profile_photo = $_SESSION['db_has_profile_photo'] ? ($student['profile_photo'] ?? null) : null;
$account_status = $_SESSION['db_has_account_status'] ? ($student['account_status'] ?? 'active') : 'active';
$last_login = $_SESSION['db_has_last_login'] ? ($student['last_login'] ?? null) : null;
$address = $_SESSION['db_has_address'] ? ($student['address'] ?? '') : '';
$contact_number = $_SESSION['db_has_contact_number'] ? ($student['contact_number'] ?? '') : '';
$gender = $_SESSION['db_has_gender'] ? ($student['gender'] ?? '') : '';

// Calculate age
$age = '';
if (!empty($birthdate)) {
    $birth_date = new DateTime($birthdate);
    $today = new DateTime();
    $age = $birth_date->diff($today)->y;
}

// Initials for avatar
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// --- 11. FETCH HEALTH STATS ---
$total_visits = 0;
$last_visit_date = 'No visits yet';

$stmt_visits = mysqli_prepare($conn, "SELECT COUNT(*) AS count FROM visits WHERE student_id = ?");
if ($stmt_visits) {
    mysqli_stmt_bind_param($stmt_visits, "i", $student_id);
    mysqli_stmt_execute($stmt_visits);
    $res = mysqli_stmt_get_result($stmt_visits);
    $total_visits = mysqli_fetch_assoc($res)['count'] ?? 0;
    mysqli_stmt_close($stmt_visits);
}

$stmt_last = mysqli_prepare($conn, "SELECT visit_date FROM visits WHERE student_id = ? AND status = 'completed' ORDER BY visit_date DESC LIMIT 1");
if ($stmt_last) {
    mysqli_stmt_bind_param($stmt_last, "i", $student_id);
    mysqli_stmt_execute($stmt_last);
    $res = mysqli_stmt_get_result($stmt_last);
    $last = mysqli_fetch_assoc($res);
    $last_visit_date = $last ? date('M d, Y', strtotime($last['visit_date'])) : 'No visits yet';
    mysqli_stmt_close($stmt_last);
}

// --- 12. HANDLE PROFILE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Security verification failed. Please refresh the page and try again.';
    } else {
        $new_first_name = trim($_POST['first_name'] ?? '');
        $new_last_name = trim($_POST['last_name'] ?? '');
        $new_allergies = trim($_POST['allergies'] ?? '');
        $new_medical_conditions = trim($_POST['medical_conditions'] ?? '');
        $new_emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $new_emergency_phone = trim($_POST['emergency_phone'] ?? '');
        $new_emergency_relation = trim($_POST['emergency_relation'] ?? '');
        
        // Optional fields
        $new_contact_number = $_SESSION['db_has_contact_number'] ? trim($_POST['contact_number'] ?? '') : '';
        $new_address = $_SESSION['db_has_address'] ? trim($_POST['address'] ?? '') : '';
        $new_gender = $_SESSION['db_has_gender'] ? trim($_POST['gender'] ?? '') : '';
        
        // Validate
        if (strlen($new_first_name) < 2) {
            $profile_errors['first_name'] = 'First name must be at least 2 characters.';
        }
        if (strlen($new_last_name) < 2) {
            $profile_errors['last_name'] = 'Last name must be at least 2 characters.';
        }
        if (!empty($new_contact_number) && !preg_match('/^[0-9\-\+\s\(\)]{7,20}$/', $new_contact_number)) {
            $profile_errors['contact_number'] = 'Please enter a valid contact number.';
        }
        if (!empty($new_emergency_phone) && !preg_match('/^[0-9\-\+\s\(\)]{7,20}$/', $new_emergency_phone)) {
            $profile_errors['emergency_phone'] = 'Please enter a valid phone number.';
        }
        
        if (empty($profile_errors)) {
            // Update users table
            $update_user = mysqli_prepare($conn, "UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
            if ($update_user) {
                mysqli_stmt_bind_param($update_user, "ssi", $new_first_name, $new_last_name, $user_id);
                mysqli_stmt_execute($update_user);
                mysqli_stmt_close($update_user);
            }
            
            // Build dynamic update for students table
            $update_parts = [
                'allergies = ?',
                'medical_conditions = ?',
                'emergency_contact = ?',
                'emergency_phone = ?',
                'emergency_relation = ?'
            ];
            $update_types = 'sssss';
            $update_values = [$new_allergies, $new_medical_conditions, $new_emergency_contact, $new_emergency_phone, $new_emergency_relation];
            
            if ($_SESSION['db_has_contact_number']) {
                $update_parts[] = 'contact_number = ?';
                $update_types .= 's';
                $update_values[] = $new_contact_number;
            }
            if ($_SESSION['db_has_address']) {
                $update_parts[] = 'address = ?';
                $update_types .= 's';
                $update_values[] = $new_address;
            }
            if ($_SESSION['db_has_gender']) {
                $update_parts[] = 'gender = ?';
                $update_types .= 's';
                $update_values[] = $new_gender;
            }
            
            $update_types .= 'i';
            $update_values[] = $student_id;
            
            $update_query = "UPDATE students SET " . implode(', ', $update_parts) . " WHERE id = ?";
            
            $update_student = mysqli_prepare($conn, $update_query);
            if ($update_student) {
                mysqli_stmt_bind_param($update_student, $update_types, ...$update_values);
                
                if (mysqli_stmt_execute($update_student)) {
                    $success_message = 'Profile updated successfully!';
                    $_SESSION['user_name'] = $new_first_name . ' ' . $new_last_name;
                    
                    // Refresh local variables
                    $first_name = $new_first_name;
                    $last_name = $new_last_name;
                    $allergies = $new_allergies;
                    $medical_conditions = $new_medical_conditions;
                    $emergency_contact = $new_emergency_contact;
                    $emergency_phone = $new_emergency_phone;
                    $emergency_relation = $new_emergency_relation;
                    if ($_SESSION['db_has_contact_number']) $contact_number = $new_contact_number;
                    if ($_SESSION['db_has_address']) $address = $new_address;
                    if ($_SESSION['db_has_gender']) $gender = $new_gender;
                    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
                } else {
                    $error_message = 'Failed to update profile. Please try again.';
                    error_log("Profile update failed: " . mysqli_error($conn));
                }
                mysqli_stmt_close($update_student);
            }
        } else {
            $error_message = 'Please fix the errors below.';
        }
    }
    
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 13. HANDLE PASSWORD CHANGE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Security verification failed. Please refresh the page.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password)) {
            $password_errors['current_password'] = 'Current password is required.';
        }
        if (empty($new_password)) {
            $password_errors['new_password'] = 'New password is required.';
        } elseif (strlen($new_password) < 8) {
            $password_errors['new_password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $password_errors['new_password'] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $password_errors['new_password'] = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $password_errors['new_password'] = 'Password must contain at least one number.';
        }
        if ($new_password !== $confirm_password) {
            $password_errors['confirm_password'] = 'Passwords do not match.';
        }
        
        if (empty($password_errors)) {
            $pwd_stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
            if ($pwd_stmt) {
                mysqli_stmt_bind_param($pwd_stmt, "i", $user_id);
                mysqli_stmt_execute($pwd_stmt);
                $pwd_res = mysqli_stmt_get_result($pwd_stmt);
                $pwd_row = mysqli_fetch_assoc($pwd_res);
                mysqli_stmt_close($pwd_stmt);
                
                if ($pwd_row && password_verify($current_password, $pwd_row['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_pwd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                    if ($update_pwd) {
                        mysqli_stmt_bind_param($update_pwd, "si", $hashed_password, $user_id);
                        if (mysqli_stmt_execute($update_pwd)) {
                            $success_message = 'Password changed successfully!';
                        } else {
                            $error_message = 'Failed to update password. Please try again.';
                        }
                        mysqli_stmt_close($update_pwd);
                    }
                } else {
                    $password_errors['current_password'] = 'Current password is incorrect.';
                }
            }
        } else {
            $error_message = 'Please fix the password errors below.';
        }
    }
    
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 14. HANDLE PROFILE PHOTO UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $photo_error = 'Security verification failed.';
    } elseif (!$_SESSION['db_has_profile_photo']) {
        $photo_error = 'Profile photo feature is not available. Please add profile_photo column to users table.';
    } elseif (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] === UPLOAD_ERR_NO_FILE) {
        $photo_error = 'Please select an image file.';
    } else {
        $file = $_FILES['profile_photo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 2 * 1024 * 1024;
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $photo_error = 'Upload failed. Please try again.';
        } elseif (!in_array($file['type'], $allowed_types)) {
            $photo_error = 'Only JPG, PNG, GIF, and WebP images are allowed.';
        } elseif ($file['size'] > $max_size) {
            $photo_error = 'Image must be less than 2MB.';
        } else {
            $upload_dir = __DIR__ . '/../../uploads/profile_photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'student_' . $student_id . '_' . time() . '.' . $extension;
            $upload_path = $upload_dir . $new_filename;
            
            $image_info = getimagesize($file['tmp_name']);
            if ($image_info === false) {
                $photo_error = 'Invalid image file.';
            } else {
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    if (!empty($profile_photo)) {
                        $old_path = $upload_dir . basename($profile_photo);
                        if (file_exists($old_path)) unlink($old_path);
                    }
                    
                    $relative_path = 'uploads/profile_photos/' . $new_filename;
                    $photo_stmt = mysqli_prepare($conn, "UPDATE users SET profile_photo = ? WHERE id = ?");
                    if ($photo_stmt) {
                        mysqli_stmt_bind_param($photo_stmt, "si", $relative_path, $user_id);
                        if (mysqli_stmt_execute($photo_stmt)) {
                            $profile_photo = $relative_path;
                            $success_message = 'Profile photo updated successfully!';
                        }
                        mysqli_stmt_close($photo_stmt);
                    }
                } else {
                    $photo_error = 'Failed to upload file. Please check directory permissions.';
                }
            }
        }
    }
    
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 15. HELPER FUNCTIONS ---
function getBloodTypeBadge($type) {
    $colors = [
        'A+' => 'bg-red-100 text-red-700', 'A-' => 'bg-red-50 text-red-600',
        'B+' => 'bg-blue-100 text-blue-700', 'B-' => 'bg-blue-50 text-blue-600',
        'AB+' => 'bg-purple-100 text-purple-700', 'AB-' => 'bg-purple-50 text-purple-600',
        'O+' => 'bg-green-100 text-green-700', 'O-' => 'bg-green-50 text-green-600',
    ];
    $color = $colors[$type] ?? 'bg-gray-100 text-gray-700';
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $color . '">' . htmlspecialchars($type) . '</span>';
}

$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta name="description" content="PUPBC Carelink Student Profile - Manage your personal and health information">
    <meta name="robots" content="noindex, nofollow">
    <title>My Profile | PUPBC Carelink</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'] },
                    colors: {
                        primary: { DEFAULT: '#800020', light: '#a0002a', dark: '#5c0017', foreground: '#ffffff' },
                        accent: { DEFAULT: '#c9a84c', light: '#d4b96a', dark: '#b89945' },
                    },
                    boxShadow: { 'card': '0 1px 3px 0 rgba(0,0,0,0.05), 0 1px 2px -1px rgba(0,0,0,0.05)' },
                },
            },
        }
    </script>
    
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; -webkit-font-smoothing: antialiased; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.5s ease forwards; }
        .tab-btn.active { color: #800020; border-bottom: 2px solid #800020; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .password-strength-bar { height: 4px; border-radius: 2px; transition: all 0.3s ease; width: 0; }
        .strength-weak { width: 25%; background: #ef4444; }
        .strength-fair { width: 50%; background: #f59e0b; }
        .strength-good { width: 75%; background: #84cc16; }
        .strength-strong { width: 100%; background: #22c55e; }
        a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible { outline: 2px solid #800020; outline-offset: 2px; border-radius: 4px; }
        .nav-active { background-color: rgba(201,168,76,0.15); color: #c9a84c; font-weight: 600; }
        .safe-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
    </style>
</head>
<body class="min-h-screen bg-gray-50">

<!-- DESKTOP SIDEBAR -->
<aside class="hidden lg:flex lg:flex-col fixed top-0 left-0 h-full w-64 bg-primary shadow-xl z-40 overflow-y-auto" role="navigation" aria-label="Main navigation">
    
    <nav class="flex-1 py-4 space-y-1">
        <div class="px-5 mb-2"><p class="text-[10px] font-semibold uppercase tracking-wider text-accent/80">Main Menu</p></div>
        <a href="student_dashboard.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all"><i class="fas fa-th-large w-5 text-center"></i><span class="text-sm font-medium">Dashboard</span></a>
        <a href="student_profile.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white nav-active" aria-current="page"><i class="fas fa-user w-5 text-center"></i><span class="text-sm font-medium">My Profile</span></a>
        <a href="student_qr.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all"><i class="fas fa-qrcode w-5 text-center"></i><span class="text-sm font-medium">My QR Code</span></a>
        <a href="student_record.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all"><i class="fas fa-notes-medical w-5 text-center"></i><span class="text-sm font-medium">Health Records</span></a>
        <a href="student_appointments.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all"><i class="fas fa-calendar-alt w-5 text-center"></i><span class="text-sm font-medium">Appointments</span></a>
        <a href="student_announcement.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all"><i class="fas fa-bullhorn w-5 text-center"></i><span class="text-sm font-medium">Announcements</span></a>
        <a href="student_notifications.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all"><i class="fas fa-bell w-5 text-center"></i><span class="text-sm font-medium">Notifications</span></a>
        <a href="student_settings.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-all"><i class="fas fa-cog w-5 text-center"></i><span class="text-sm font-medium">Settings</span></a>
        <div class="border-t border-primary-dark my-4 mx-5"></div>
        <a href="logout.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/60 hover:text-white hover:bg-red-600/20 transition-all"><i class="fas fa-sign-out-alt w-5 text-center"></i><span class="text-sm font-medium">Sign Out</span></a>
    </nav>
    <div class="p-4 border-t border-primary-dark mt-auto"><p class="text-[10px] text-white/30 text-center">&copy; <?php echo date('Y'); ?> PUPBC Carelink</p></div>
</aside>

<!-- MAIN CONTENT -->
<main class="lg:ml-64 min-h-screen pb-24 lg:pb-8">
    
    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-sm border-b border-gray-200 shadow-sm">
        <div class="px-4 lg:px-6 py-4 flex items-center justify-between">
            <div><h1 class="text-xl lg:text-2xl font-bold text-gray-900">My Profile</h1><p class="text-sm text-gray-500 hidden sm:block">Manage your personal and health information</p></div>
            <a href="student_dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>
    </header>
    
    <div class="p-4 lg:p-6 space-y-6">
        
        <?php if ($success_message): ?>
            <div class="animate-fade-in flex items-start gap-3 p-4 rounded-xl bg-green-50 border border-green-200 text-green-800" role="alert">
                <i class="fas fa-check-circle text-green-500 mt-0.5"></i><p class="font-medium text-sm flex-1"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></p>
                <button onclick="this.parentElement.remove()" class="p-1 hover:bg-black/5 rounded" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="animate-fade-in flex items-start gap-3 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800" role="alert">
                <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i><p class="font-medium text-sm flex-1"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
                <button onclick="this.parentElement.remove()" class="p-1 hover:bg-black/5 rounded" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
        
        <!-- Profile Header Card -->
        <div class="bg-white rounded-2xl shadow-card border border-gray-100 overflow-hidden">
            <div class="h-32 bg-gradient-to-r from-primary via-primary-dark to-primary-light"></div>
            <div class="px-6 pb-6">
                <div class="flex flex-col sm:flex-row items-center sm:items-end gap-4 -mt-16">
                    <div class="relative group">
                        <?php if ($profile_photo): ?>
                            <img src="../../<?php echo htmlspecialchars($profile_photo, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile photo" class="h-28 w-28 rounded-2xl object-cover border-4 border-white shadow-lg" loading="lazy">
                        <?php else: ?>
                            <div class="h-28 w-28 rounded-2xl bg-accent flex items-center justify-center border-4 border-white shadow-lg text-primary text-3xl font-bold"><?php echo htmlspecialchars($initials); ?></div>
                        <?php endif; ?>
                        <?php if ($_SESSION['db_has_profile_photo']): ?>
                        <button onclick="document.getElementById('photoUploadTab').click(); switchTab('photo-tab');" class="absolute inset-0 rounded-2xl bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer" aria-label="Change profile photo"><i class="fas fa-camera text-white text-xl"></i></button>
                        <?php endif; ?>
                    </div>
                    <div class="text-center sm:text-left flex-1">
                        <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></h2>
                        <p class="text-gray-500"><?php echo htmlspecialchars($student_number); ?> • <?php echo htmlspecialchars($course); ?></p>
                        <div class="flex flex-wrap gap-2 mt-2 justify-center sm:justify-start">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary"><?php echo htmlspecialchars($year_level); ?></span>
                            <?php if (!empty($blood_type)): ?><?php echo getBloodTypeBadge($blood_type); ?><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-card border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-200">
                <nav class="flex overflow-x-auto -mb-px" role="tablist" aria-label="Profile sections">
                    <button class="tab-btn active px-4 py-3 text-sm whitespace-nowrap transition-colors" onclick="switchTab('info-tab')" role="tab" aria-selected="true" aria-controls="info-tab"><i class="fas fa-info-circle mr-1"></i> Personal Info</button>
                    <button class="tab-btn px-4 py-3 text-sm whitespace-nowrap text-gray-500 hover:text-gray-700 transition-colors" onclick="switchTab('health-tab')" role="tab" aria-selected="false" aria-controls="health-tab"><i class="fas fa-heartbeat mr-1"></i> Health Info</button>
                    <button class="tab-btn px-4 py-3 text-sm whitespace-nowrap text-gray-500 hover:text-gray-700 transition-colors" onclick="switchTab('edit-tab')" role="tab" aria-selected="false" aria-controls="edit-tab"><i class="fas fa-edit mr-1"></i> Edit Profile</button>
                    <button class="tab-btn px-4 py-3 text-sm whitespace-nowrap text-gray-500 hover:text-gray-700 transition-colors" onclick="switchTab('password-tab')" role="tab" aria-selected="false" aria-controls="password-tab"><i class="fas fa-lock mr-1"></i> Password</button>
                    <?php if ($_SESSION['db_has_profile_photo']): ?>
                    <button class="tab-btn px-4 py-3 text-sm whitespace-nowrap text-gray-500 hover:text-gray-700 transition-colors" onclick="switchTab('photo-tab')" role="tab" aria-selected="false" aria-controls="photo-tab" id="photoUploadTab"><i class="fas fa-camera mr-1"></i> Photo</button>
                    <?php endif; ?>
                </nav>
            </div>
            
            <div class="p-6">
                
                <!-- PERSONAL INFO TAB -->
                <div class="tab-content active" id="info-tab" role="tabpanel" aria-labelledby="tab-info">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-id-card text-primary"></i> Student Information</h3>
                            <div class="space-y-3">
                                <?php 
                                $info_rows = [
                                    ['Student Number', $student_number],
                                    ['Full Name', $first_name . ' ' . $last_name],
                                    ['Email', $email],
                                    ['Birthdate', !empty($birthdate) ? date('F d, Y', strtotime($birthdate)) . ' (' . $age . ' years)' : '<span class="text-gray-400">Not set</span>'],
                                    ['Course', $course],
                                    ['Year Level', $year_level],
                                ];
                                if ($_SESSION['db_has_gender']) {
                                    $info_rows[] = ['Gender', !empty($gender) ? $gender : '<span class="text-gray-400">Not set</span>'];
                                }
                                foreach ($info_rows as $row): ?>
                                <div class="flex justify-between py-2 border-b border-gray-50">
                                    <span class="text-sm text-gray-500"><?php echo $row[0]; ?></span>
                                    <span class="text-sm font-medium text-gray-900"><?php echo $row[1]; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-address-book text-primary"></i> Contact & Emergency</h3>
                            <div class="space-y-3">
                                <?php if ($_SESSION['db_has_contact_number']): ?>
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-500">Contact Number</span><span class="text-sm font-medium text-gray-900"><?php echo !empty($contact_number) ? htmlspecialchars($contact_number) : '<span class="text-gray-400">Not set</span>'; ?></span></div>
                                <?php endif; ?>
                                <?php if ($_SESSION['db_has_address']): ?>
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-500">Address</span><span class="text-sm font-medium text-gray-900"><?php echo !empty($address) ? htmlspecialchars($address) : '<span class="text-gray-400">Not set</span>'; ?></span></div>
                                <?php endif; ?>
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-500">Emergency Contact</span><span class="text-sm font-medium text-gray-900"><?php echo !empty($emergency_contact) ? htmlspecialchars($emergency_contact) . (!empty($emergency_relation) ? ' (' . htmlspecialchars($emergency_relation) . ')' : '') : '<span class="text-gray-400">Not set</span>'; ?></span></div>
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-500">Emergency Phone</span><span class="text-sm font-medium text-gray-900"><?php echo !empty($emergency_phone) ? htmlspecialchars($emergency_phone) : '<span class="text-gray-400">Not set</span>'; ?></span></div>
                                <?php if ($last_login): ?>
                                <div class="flex justify-between py-2"><span class="text-sm text-gray-500">Last Login</span><span class="text-sm font-medium text-gray-900"><?php echo date('M d, Y h:i A', strtotime($last_login)); ?></span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($qr_code): ?>
                    <div class="mt-6 p-4 bg-gray-50 rounded-xl flex items-center justify-between">
                        <div class="flex items-center gap-3"><div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center"><i class="fas fa-qrcode text-primary"></i></div><div><p class="text-sm font-medium text-gray-900">Your QR Code</p><p class="text-xs text-gray-500">Use this for clinic check-ins</p></div></div>
                        <a href="student_qr.php" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-xl hover:bg-primary-dark transition-colors">View QR</a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- HEALTH INFO TAB -->
                <div class="tab-content" id="health-tab" role="tabpanel" aria-labelledby="tab-health">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-notes-medical text-primary"></i> Medical Information</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-500">Blood Type</span><span class="text-sm font-medium"><?php echo !empty($blood_type) ? getBloodTypeBadge($blood_type) : '<span class="text-gray-400">Not set</span>'; ?></span></div>
                                <div class="py-2 border-b border-gray-50"><span class="text-sm text-gray-500 block mb-1">Allergies</span><p class="text-sm text-gray-900"><?php echo !empty($allergies) && $allergies !== 'None' ? htmlspecialchars($allergies) : '<span class="text-gray-400">No known allergies</span>'; ?></p></div>
                                <div class="py-2"><span class="text-sm text-gray-500 block mb-1">Medical Conditions</span><p class="text-sm text-gray-900"><?php echo !empty($medical_conditions) && $medical_conditions !== 'None' ? htmlspecialchars($medical_conditions) : '<span class="text-gray-400">No existing conditions</span>'; ?></p></div>
                            </div>
                            <?php if (!empty($allergies) && $allergies !== 'None'): ?>
                            <div class="p-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2"><i class="fas fa-exclamation-triangle text-red-500 mt-0.5"></i><p class="text-xs text-red-700 font-medium">Allergy Alert: Inform clinic staff of your allergies during visits.</p></div>
                            <?php endif; ?>
                        </div>
                        <div class="space-y-4">
                            <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="fas fa-chart-bar text-primary"></i> Health Summary</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-500">Total Clinic Visits</span><span class="text-sm font-bold text-gray-900"><?php echo $total_visits; ?></span></div>
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-500">Last Visit</span><span class="text-sm font-medium text-gray-900"><?php echo $last_visit_date; ?></span></div>
                                <div class="flex justify-between py-2"><span class="text-sm text-gray-500">QR Code</span><span class="text-sm font-medium text-gray-900"><?php echo $qr_code ? 'Active' : '<span class="text-gray-400">Not generated</span>'; ?></span></div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <a href="student_record.php" class="inline-flex items-center gap-2 text-sm text-primary hover:underline font-medium"><i class="fas fa-file-medical"></i> View Health Records</a>
                                <a href="student_appointments.php" class="inline-flex items-center gap-2 text-sm text-primary hover:underline font-medium"><i class="fas fa-calendar-alt"></i> Manage Appointments</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- EDIT PROFILE TAB -->
                <div class="tab-content" id="edit-tab" role="tabpanel" aria-labelledby="tab-edit">
                    <form method="POST" class="space-y-6" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <h3 class="font-semibold text-gray-900">Personal Information</h3>
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'); ?>" required class="w-full px-4 py-2.5 rounded-xl border <?php echo isset($profile_errors['first_name']) ? 'border-red-300 bg-red-50' : 'border-gray-200'; ?> focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors">
                                    <?php if (isset($profile_errors['first_name'])): ?><p class="text-xs text-red-600 mt-1"><?php echo htmlspecialchars($profile_errors['first_name']); ?></p><?php endif; ?>
                                </div>
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8'); ?>" required class="w-full px-4 py-2.5 rounded-xl border <?php echo isset($profile_errors['last_name']) ? 'border-red-300 bg-red-50' : 'border-gray-200'; ?> focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors">
                                    <?php if (isset($profile_errors['last_name'])): ?><p class="text-xs text-red-600 mt-1"><?php echo htmlspecialchars($profile_errors['last_name']); ?></p><?php endif; ?>
                                </div>
                                <?php if ($_SESSION['db_has_gender']): ?>
                                <div>
                                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                                    <select id="gender" name="gender" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors">
                                        <option value="">Prefer not to say</option>
                                        <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <?php if ($_SESSION['db_has_contact_number']): ?>
                                <div>
                                    <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                                    <input type="tel" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($contact_number, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., 09123456789" class="w-full px-4 py-2.5 rounded-xl border <?php echo isset($profile_errors['contact_number']) ? 'border-red-300 bg-red-50' : 'border-gray-200'; ?> focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors">
                                    <?php if (isset($profile_errors['contact_number'])): ?><p class="text-xs text-red-600 mt-1"><?php echo htmlspecialchars($profile_errors['contact_number']); ?></p><?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($_SESSION['db_has_address']): ?>
                                <div>
                                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                    <textarea id="address" name="address" rows="2" placeholder="Your current address" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors"><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="space-y-4">
                                <h3 class="font-semibold text-gray-900">Emergency & Medical</h3>
                                <div>
                                    <label for="emergency_contact" class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Name</label>
                                    <input type="text" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($emergency_contact, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Full name of emergency contact" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors">
                                </div>
                                <div>
                                    <label for="emergency_relation" class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                                    <select id="emergency_relation" name="emergency_relation" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors">
                                        <option value="">Select relationship</option>
                                        <?php foreach (['Parent', 'Guardian', 'Spouse', 'Sibling', 'Relative', 'Friend'] as $rel): ?>
                                            <option value="<?php echo $rel; ?>" <?php echo $emergency_relation === $rel ? 'selected' : ''; ?>><?php echo $rel; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="emergency_phone" class="block text-sm font-medium text-gray-700 mb-1">Emergency Phone</label>
                                    <input type="tel" id="emergency_phone" name="emergency_phone" value="<?php echo htmlspecialchars($emergency_phone, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., 09123456789" class="w-full px-4 py-2.5 rounded-xl border <?php echo isset($profile_errors['emergency_phone']) ? 'border-red-300 bg-red-50' : 'border-gray-200'; ?> focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors">
                                    <?php if (isset($profile_errors['emergency_phone'])): ?><p class="text-xs text-red-600 mt-1"><?php echo htmlspecialchars($profile_errors['emergency_phone']); ?></p><?php endif; ?>
                                </div>
                                <div>
                                    <label for="allergies" class="block text-sm font-medium text-gray-700 mb-1">Allergies</label>
                                    <textarea id="allergies" name="allergies" rows="2" placeholder="List any allergies" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors"><?php echo htmlspecialchars($allergies, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                                <div>
                                    <label for="medical_conditions" class="block text-sm font-medium text-gray-700 mb-1">Medical Conditions</label>
                                    <textarea id="medical_conditions" name="medical_conditions" rows="2" placeholder="List any existing medical conditions" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors"><?php echo htmlspecialchars($medical_conditions, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                            <button type="reset" class="px-6 py-2.5 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                            <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-dark transition-colors shadow-md"><i class="fas fa-save mr-2"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- PASSWORD TAB -->
                <div class="tab-content" id="password-tab" role="tabpanel" aria-labelledby="tab-password">
                    <div class="max-w-md">
                        <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fas fa-lock text-primary"></i> Change Password</h3>
                        <form method="POST" class="space-y-4" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                <div class="relative">
                                    <input type="password" id="current_password" name="current_password" required class="w-full px-4 py-2.5 pr-12 rounded-xl border <?php echo isset($password_errors['current_password']) ? 'border-red-300 bg-red-50' : 'border-gray-200'; ?> focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors">
                                    <button type="button" onclick="togglePassword('current_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" aria-label="Toggle visibility"><i class="fas fa-eye"></i></button>
                                </div>
                                <?php if (isset($password_errors['current_password'])): ?><p class="text-xs text-red-600 mt-1"><?php echo htmlspecialchars($password_errors['current_password']); ?></p><?php endif; ?>
                            </div>
                            
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                <div class="relative">
                                    <input type="password" id="new_password" name="new_password" required oninput="checkPasswordStrength()" class="w-full px-4 py-2.5 pr-12 rounded-xl border <?php echo isset($password_errors['new_password']) ? 'border-red-300 bg-red-50' : 'border-gray-200'; ?> focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors">
                                    <button type="button" onclick="togglePassword('new_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" aria-label="Toggle visibility"><i class="fas fa-eye"></i></button>
                                </div>
                                <div class="mt-2 w-full bg-gray-200 rounded-full password-strength-bar" id="passwordStrengthBar"></div>
                                <p class="text-xs text-gray-500 mt-1" id="passwordStrengthText">Enter a strong password</p>
                                <?php if (isset($password_errors['new_password'])): ?><p class="text-xs text-red-600 mt-1"><?php echo htmlspecialchars($password_errors['new_password']); ?></p><?php endif; ?>
                                <ul class="text-xs text-gray-500 mt-2 space-y-1">
                                    <li id="req-length"><i class="fas fa-circle text-[4px] mr-1"></i> At least 8 characters</li>
                                    <li id="req-uppercase"><i class="fas fa-circle text-[4px] mr-1"></i> One uppercase letter</li>
                                    <li id="req-lowercase"><i class="fas fa-circle text-[4px] mr-1"></i> One lowercase letter</li>
                                    <li id="req-number"><i class="fas fa-circle text-[4px] mr-1"></i> One number</li>
                                </ul>
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                <div class="relative">
                                    <input type="password" id="confirm_password" name="confirm_password" required class="w-full px-4 py-2.5 pr-12 rounded-xl border <?php echo isset($password_errors['confirm_password']) ? 'border-red-300 bg-red-50' : 'border-gray-200'; ?> focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors">
                                    <button type="button" onclick="togglePassword('confirm_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" aria-label="Toggle visibility"><i class="fas fa-eye"></i></button>
                                </div>
                                <?php if (isset($password_errors['confirm_password'])): ?><p class="text-xs text-red-600 mt-1"><?php echo htmlspecialchars($password_errors['confirm_password']); ?></p><?php endif; ?>
                            </div>
                            
                            <button type="submit" class="w-full px-6 py-3 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-dark transition-colors shadow-md"><i class="fas fa-key mr-2"></i> Update Password</button>
                        </form>
                    </div>
                </div>
                
                <!-- PHOTO TAB (only if column exists) -->
                <?php if ($_SESSION['db_has_profile_photo']): ?>
                <div class="tab-content" id="photo-tab" role="tabpanel" aria-labelledby="tab-photo">
                    <div class="max-w-md">
                        <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fas fa-camera text-primary"></i> Profile Photo</h3>
                        <?php if ($photo_error): ?><div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"><?php echo htmlspecialchars($photo_error); ?></div><?php endif; ?>
                        <div class="mb-6 flex items-center gap-4">
                            <?php if ($profile_photo): ?>
                                <img src="../../<?php echo htmlspecialchars($profile_photo, ENT_QUOTES, 'UTF-8'); ?>" alt="Current photo" class="h-24 w-24 rounded-2xl object-cover border-2 border-gray-200" loading="lazy">
                            <?php else: ?>
                                <div class="h-24 w-24 rounded-2xl bg-accent flex items-center justify-center text-primary text-2xl font-bold border-2 border-gray-200"><?php echo htmlspecialchars($initials); ?></div>
                            <?php endif; ?>
                            <div><p class="text-sm font-medium text-gray-900">Current Photo</p><p class="text-xs text-gray-500">JPG, PNG, GIF, or WebP. Max 2MB.</p></div>
                        </div>
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="upload_photo" value="1">
                            <div>
                                <label for="profile_photo" class="block text-sm font-medium text-gray-700 mb-1">Choose Photo</label>
                                <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/gif,image/webp" class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-dark file:cursor-pointer">
                                <p id="fileInfo" class="text-xs text-gray-400 mt-1"></p>
                            </div>
                            <button type="submit" class="w-full px-6 py-3 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary-dark transition-colors shadow-md"><i class="fas fa-upload mr-2"></i> Upload Photo</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- MOBILE BOTTOM NAV -->
<nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 lg:hidden z-40 safe-bottom" aria-label="Mobile navigation">
    <div class="flex justify-around items-center py-1.5 px-2 max-w-lg mx-auto">
        <a href="student_dashboard.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-gray-400"><i class="fas fa-th-large text-lg"></i><span class="text-[10px] mt-0.5">Home</span></a>
        <a href="student_qr.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-gray-400"><i class="fas fa-qrcode text-lg"></i><span class="text-[10px] mt-0.5">QR</span></a>
        <a href="student_record.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-gray-400"><i class="fas fa-notes-medical text-lg"></i><span class="text-[10px] mt-0.5">Records</span></a>
        <a href="student_appointments.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-gray-400"><i class="fas fa-calendar-alt text-lg"></i><span class="text-[10px] mt-0.5">Appts</span></a>
        <a href="student_profile.php" class="flex flex-col items-center py-1 px-3 rounded-lg" style="color:#800020;" aria-current="page"><i class="fas fa-user-circle text-lg"></i><span class="text-[10px] mt-0.5 font-medium border-t-2 border-primary">Profile</span></a>
    </div>
</nav>

<script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('active'); b.setAttribute('aria-selected', 'false'); });
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        const content = document.getElementById(tabId);
        const btn = document.querySelector(`[onclick*="${tabId}"]`);
        if (content) content.classList.add('active');
        if (btn) { btn.classList.add('active'); btn.setAttribute('aria-selected', 'true'); }
    }
    
    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') { input.type = 'text'; icon.className = 'fas fa-eye-slash'; btn.setAttribute('aria-label', 'Hide password'); }
        else { input.type = 'password'; icon.className = 'fas fa-eye'; btn.setAttribute('aria-label', 'Show password'); }
    }
    
    function checkPasswordStrength() {
        const password = document.getElementById('new_password').value;
        const bar = document.getElementById('passwordStrengthBar');
        const text = document.getElementById('passwordStrengthText');
        let strength = 0;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        bar.className = 'password-strength-bar';
        if (password.length === 0) { bar.style.width = '0'; text.textContent = 'Enter a strong password'; }
        else if (strength <= 2) { bar.classList.add('strength-weak'); text.textContent = 'Weak password'; }
        else if (strength === 3) { bar.classList.add('strength-fair'); text.textContent = 'Fair password'; }
        else if (strength === 4) { bar.classList.add('strength-good'); text.textContent = 'Good password'; }
        else { bar.classList.add('strength-strong'); text.textContent = 'Strong password!'; }
        
        ['req-length','req-uppercase','req-lowercase','req-number'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                const icon = el.querySelector('i');
                let met = false;
                if (id === 'req-length') met = password.length >= 8;
                else if (id === 'req-uppercase') met = /[A-Z]/.test(password);
                else if (id === 'req-lowercase') met = /[a-z]/.test(password);
                else if (id === 'req-number') met = /[0-9]/.test(password);
                if (met) { icon.className = 'fas fa-check-circle text-green-500 text-xs mr-1'; el.classList.add('text-green-600'); }
                else { icon.className = 'fas fa-circle text-[4px] mr-1'; el.classList.remove('text-green-600'); }
            }
        });
    }
    
    document.getElementById('profile_photo')?.addEventListener('change', function() {
        const file = this.files[0];
        const info = document.getElementById('fileInfo');
        if (file) { const size = (file.size/1024/1024).toFixed(2); info.textContent = `Selected: ${file.name} (${size} MB)`; info.className = file.size > 2097152 ? 'text-xs text-red-500 mt-1' : 'text-xs text-gray-500 mt-1'; }
    });
    
    document.querySelectorAll('[role="alert"]').forEach(alert => {
        setTimeout(() => { alert.style.transition = 'opacity 0.5s ease'; alert.style.opacity = '0'; setTimeout(() => alert.remove(), 500); }, 6000);
    });
    
    function adjustPadding() {
        const nav = document.querySelector('nav[aria-label="Mobile navigation"]');
        document.body.style.paddingBottom = (nav && window.innerWidth < 1024) ? nav.offsetHeight + 'px' : '0px';
    }
    window.addEventListener('resize', adjustPadding);
    adjustPadding();
</script>
</body>
</html>