<?php
// capstonemain/index.php
// db_connect.php handles session_start() with secure flags
require_once __DIR__ . '/config/db_connect.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$contact_error = '';
$contact_success = '';
$name = $email = $message = '';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    // CSRF Protection Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $contact_error = 'Security verification failed. Please refresh the page and try again.';
    } 
    // Honeypot Check
    elseif (!empty($_POST['bot_check'])) {
        error_log("Honeypot triggered by IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $contact_success = 'Thank you! Your message has been sent.';
    } 
    else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        $errors = [];
        if (strlen($name) < 2) $errors[] = 'Please enter your full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if (strlen($message) < 10) $errors[] = 'Message must be at least 10 characters.';
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $last_submit_key = 'contact_' . str_replace('.', '_', $ip);
        if (isset($_SESSION[$last_submit_key]) && time() - $_SESSION[$last_submit_key] < 300) {
            $errors[] = 'Please wait 5 minutes before sending another message.';
        }
        
        if (empty($errors) && isset($conn) && $conn) {
            $stmt = mysqli_prepare($conn, "INSERT INTO contact_messages (name, email, message, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $message, $ip);
                
                if (mysqli_stmt_execute($stmt)) {
                    $contact_success = 'Thank you! Your message has been sent successfully.';
                    $_SESSION[$last_submit_key] = time();
                    $name = $email = $message = '';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } else {
                    error_log("Contact form DB insert failed: " . mysqli_stmt_error($stmt));
                    $contact_error = 'Failed to send message due to a server error. Please try again later.';
                }
                mysqli_stmt_close($stmt);
            } else {
                error_log("Contact form DB prepare failed: " . mysqli_error($conn));
                $contact_error = 'A system error occurred. Please try again later.';
            }
        } else {
            $contact_error = implode(' ', array_map('htmlspecialchars', $errors));
        }
    }
}

$year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>PUPBC Carelink | Smart Campus Health System</title>
    <meta name="description" content="PUPBC Carelink - A QR-integrated health information system for PUP Binan Campus, enabling faster check-ins, paperless records, and real-time health insights.">
    <meta name="keywords" content="PUPBC, Carelink, Health System, PUP Binan, QR Code, Clinic, Student Health, Electronic Health Records">
    <meta name="theme-color" content="#800020">

    <!-- Open Graph -->
    <meta property="og:title" content="PUPBC Carelink | Smart Campus Health System">
    <meta property="og:description" content="QR-integrated health information system for PUP Binan Campus. Scan. Care. Connect.">
    <meta property="og:type" content="website">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="assets/images/clinic_logo.png">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --maroon: #800020;
            --maroon-dark: #5c0017;
            --maroon-light: #a0002a;
            --gold: #c9a84c;
            --gold-dark: #b8943a;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --white: #ffffff;
            --bg-body: #fefaf5;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --header-height: 70px;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: var(--header-height);
            -webkit-text-size-adjust: 100%;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-body);
            color: #1a1a2e;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            width: 100%;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }
        @media (min-width: 640px) { .container { padding: 0 24px; } }
        @media (min-width: 1024px) { .container { padding: 0 32px; } }

        .section-title {
            font-size: clamp(1.75rem, 5vw, 2.5rem);
            font-weight: 700;
            text-align: center;
            margin-bottom: 16px;
            color: var(--gray-800);
            line-height: 1.3;
        }
        
        .section-subtitle {
            text-align: center;
            color: var(--gray-600);
            max-width: 650px;
            margin: 0 auto 40px;
            font-size: 1rem;
            line-height: 1.6;
        }
        @media (min-width: 640px) { .section-subtitle { font-size: 1.125rem; margin-bottom: 48px; } }

        /* Buttons */
        .btn-primary, .btn-outline {
            padding: 10px 20px;
            min-height: 44px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            white-space: nowrap;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            line-height: 1;
            border: 1px solid transparent;
        }
        @media (min-width: 640px) { .btn-primary, .btn-outline { padding: 10px 24px; font-size: 0.9375rem; min-height: 48px; } }

        .btn-primary {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            box-shadow: 0 4px 12px rgba(128, 0, 32, 0.15);
        }
        .btn-primary:hover { background: var(--maroon-dark); border-color: var(--maroon-dark); box-shadow: 0 6px 16px rgba(128, 0, 32, 0.25); transform: translateY(-1px); }
        .btn-primary:active { transform: scale(0.97); }

        .btn-outline {
            background: transparent;
            color: var(--gray-800);
            border-color: var(--gray-300);
        }
        .btn-outline:hover { background: var(--gray-50); border-color: var(--gray-400); transform: translateY(-1px); }
        .btn-outline:active { transform: scale(0.97); }

        /* Header */
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--gray-200);
            height: var(--header-height);
        }
        .header-content { display: flex; justify-content: space-between; align-items: center; height: 100%; }
        .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; z-index: 102; flex-shrink: 0; }
        .logo-icon { width: 38px; height: 38px; border-radius: 10px; overflow: hidden; flex-shrink: 0; box-shadow: var(--shadow-sm); }
        @media (min-width: 640px) { .logo-icon { width: 42px; height: 42px; } }
        .logo-icon img { width: 100%; height: 100%; object-fit: cover; }
        .logo-text { font-size: 1rem; font-weight: 700; color: var(--gray-800); }
        @media (min-width: 640px) { .logo-text { font-size: 1.25rem; } }
        .logo-text span { color: var(--gold); }

        /* Desktop Nav */
        .header-right { display: none; align-items: center; gap: 24px; }
        @media (min-width: 1024px) { .header-right { display: flex; } }
        .nav-links { display: flex; gap: 24px; align-items: center; list-style: none; }
        .nav-links a { text-decoration: none; color: var(--gray-600); font-weight: 500; font-size: 0.9375rem; transition: color 0.2s; white-space: nowrap; }
        .nav-links a:hover { color: var(--maroon); }
        .nav-buttons { display: flex; gap: 8px; align-items: center; }

        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray-800);
            padding: 8px;
            border-radius: 8px;
            transition: background-color 0.2s;
            z-index: 102;
            width: 44px;
            height: 44px;
        }
        .mobile-menu-btn:active { background-color: var(--gray-100); }
        @media (min-width: 1024px) { .mobile-menu-btn { display: none; } }

        /* Mobile Menu Overlay */
        .menu-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 99;
        }
        .menu-overlay.active { display: block; animation: fadeInOverlay 0.3s ease-out forwards; }
        @keyframes fadeInOverlay { from { opacity: 0; } to { opacity: 1; } }

        /* Mobile Menu Dropdown */
        .mobile-menu {
            display: none;
            position: fixed;
            top: var(--header-height);
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--white);
            z-index: 101;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            box-shadow: var(--shadow-xl);
        }
        .mobile-menu.active { display: block; animation: slideDownMenu 0.3s ease-out forwards; }
        @keyframes slideDownMenu { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .mobile-menu-inner {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .mobile-nav-links {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .mobile-nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 15px 18px;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 500;
            color: var(--gray-800);
            text-decoration: none;
            transition: all 0.2s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .mobile-nav-link i {
            width: 22px;
            text-align: center;
            color: var(--maroon);
            font-size: 16px;
            flex-shrink: 0;
        }
        .mobile-nav-link:active { background: var(--gray-100); transform: scale(0.98); }

        .mobile-menu-divider {
            height: 1px;
            background: var(--gray-200);
            margin: 16px 0;
        }

        .mobile-menu-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .mobile-btn-outline, .mobile-btn-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 24px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .mobile-btn-outline {
            background: var(--white);
            color: var(--gray-800);
            border: 2px solid var(--gray-300);
        }
        .mobile-btn-outline:active { background: var(--gray-50); border-color: var(--gray-400); }

        .mobile-btn-primary {
            background: var(--maroon);
            color: var(--white);
            border: 2px solid var(--maroon);
            box-shadow: 0 4px 16px rgba(128, 0, 32, 0.2);
        }
        .mobile-btn-primary:active { background: var(--maroon-dark); border-color: var(--maroon-dark); }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #800020 0%, #4a0010 50%, #800020 100%);
            padding: 60px 0;
            position: relative;
            overflow: hidden;
        }
        @media (min-width: 640px) { .hero { padding: 80px 0; } }
        .hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('assets/images/pupbg.jpg') center/cover no-repeat;
            opacity: 0.08;
            pointer-events: none;
        }
        .hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr;
            gap: 40px;
            align-items: center;
        }
        @media (min-width: 768px) { .hero-grid { grid-template-columns: 1fr 1fr; gap: 40px; } }
        @media (min-width: 1024px) { .hero-grid { grid-template-columns: 1.1fr 0.9fr; gap: 60px; } }
        .hero-text { display: flex; flex-direction: column; align-items: center; text-align: center; }
        @media (min-width: 768px) { .hero-text { align-items: flex-start; text-align: left; } }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(201, 168, 76, 0.2);
            border: 1px solid rgba(201, 168, 76, 0.4);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.8125rem;
            color: var(--gold);
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        @media (min-width: 640px) { .hero-badge { padding: 8px 20px; font-size: 0.875rem; margin-bottom: 24px; } }
        .hero h1 { font-size: clamp(2rem, 6vw, 3.8rem); font-weight: 800; line-height: 1.15; color: white; margin-bottom: 16px; }
        @media (min-width: 640px) { .hero h1 { margin-bottom: 20px; } }
        .hero h1 span { color: var(--gold); }
        .hero p { font-size: 1rem; color: rgba(255, 255, 255, 0.85); line-height: 1.7; max-width: 480px; }
        @media (min-width: 640px) { .hero p { font-size: 1.125rem; } }

        .hero-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (min-width: 640px) { .hero-stats { gap: 16px; } }
        @media (min-width: 1024px) { .hero-stats { gap: 20px; } }
        .stat-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px 16px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: background 0.3s ease, transform 0.3s ease;
        }
        @media (min-width: 640px) { .stat-card { padding: 28px 24px; border-radius: 20px; } }
        .stat-card:hover { background: rgba(255, 255, 255, 0.14); transform: translateY(-4px); }
        .stat-number { font-size: 1.75rem; font-weight: 800; color: var(--gold); }
        @media (min-width: 640px) { .stat-number { font-size: 2.2rem; } }
        .stat-label { font-size: 0.75rem; color: rgba(255, 255, 255, 0.7); margin-top: 4px; font-weight: 500; }
        @media (min-width: 640px) { .stat-label { font-size: 0.875rem; margin-top: 6px; } }

        /* Features */
        .features { padding: 60px 0; background: var(--white); }
        @media (min-width: 640px) { .features { padding: 80px 0; } }
        .features-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
        @media (min-width: 640px) { .features-grid { grid-template-columns: repeat(2, 1fr); gap: 24px; } }
        @media (min-width: 1024px) { .features-grid { grid-template-columns: repeat(3, 1fr); gap: 32px; } }
        .feature-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            padding: 24px;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        @media (min-width: 640px) { .feature-card { padding: 32px; border-radius: 24px; } }
        .feature-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-xl); border-color: var(--gold); }
        .feature-icon {
            width: 50px; height: 50px;
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            flex-shrink: 0;
        }
        @media (min-width: 640px) { .feature-icon { width: 60px; height: 60px; border-radius: 18px; margin-bottom: 24px; } }
        .feature-icon i { font-size: 22px; color: white; }
        @media (min-width: 640px) { .feature-icon i { font-size: 28px; } }
        .feature-card h3 { font-size: 1.125rem; font-weight: 700; margin-bottom: 8px; color: var(--gray-800); }
        @media (min-width: 640px) { .feature-card h3 { font-size: 1.25rem; margin-bottom: 12px; } }
        .feature-card p { color: var(--gray-600); line-height: 1.7; font-size: 0.875rem; flex-grow: 1; }
        @media (min-width: 640px) { .feature-card p { font-size: 1rem; } }

        /* How It Works */
        .how-it-works { background: var(--gray-50); padding: 60px 0; }
        @media (min-width: 640px) { .how-it-works { padding: 80px 0; } }
        .steps-grid { display: grid; grid-template-columns: 1fr; gap: 32px; }
        @media (min-width: 640px) { .steps-grid { grid-template-columns: repeat(2, 1fr); gap: 40px; } }
        @media (min-width: 768px) { .steps-grid { grid-template-columns: repeat(3, 1fr); gap: 40px; } }
        @media (min-width: 640px) and (max-width: 767px) {
            .step:last-child { grid-column: span 2; max-width: 50%; margin: 0 auto; }
        }
        .step { text-align: center; padding: 24px 20px; }
        @media (min-width: 640px) { .step { padding: 32px 24px; } }
        .step-number {
            width: 56px; height: 56px;
            background: var(--gold);
            color: var(--maroon-dark);
            font-size: 1.5rem;
            font-weight: 800;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 4px 12px rgba(201, 168, 76, 0.3);
        }
        @media (min-width: 640px) { .step-number { width: 64px; height: 64px; font-size: 1.75rem; margin-bottom: 20px; } }
        .step i { font-size: 32px; color: var(--gold); margin-bottom: 16px; display: block; }
        @media (min-width: 640px) { .step i { font-size: 40px; margin-bottom: 20px; } }
        .step h3 { font-size: 1.125rem; font-weight: 700; margin-bottom: 8px; }
        @media (min-width: 640px) { .step h3 { font-size: 1.25rem; margin-bottom: 12px; } }
        .step p { color: var(--gray-600); font-size: 0.875rem; }
        @media (min-width: 640px) { .step p { font-size: 1rem; } }

        /* FAQ */
        .faq { padding: 60px 0; background: var(--white); }
        @media (min-width: 640px) { .faq { padding: 80px 0; } }
        .faq-grid { max-width: 800px; margin: 0 auto; }
        .faq-item { border-bottom: 1px solid var(--gray-200); }
        .faq-question {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            padding: 20px 8px;
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            color: var(--gray-800);
            transition: color 0.2s;
            gap: 12px;
        }
        @media (min-width: 640px) { .faq-question { font-size: 1.125rem; padding: 24px 8px; } }
        .faq-question:hover { color: var(--maroon); }
        .faq-question i { font-size: 0.875rem; transition: transform 0.3s ease; color: var(--gray-600); flex-shrink: 0; }
        .faq-question[aria-expanded="true"] i { transform: rotate(180deg); color: var(--maroon); }
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out, padding 0.4s ease-out;
            color: var(--gray-600);
            line-height: 1.7;
            padding: 0 8px;
            padding-right: 32px;
            font-size: 0.875rem;
        }
        @media (min-width: 640px) { .faq-answer { font-size: 1rem; } }
        .faq-answer.open { max-height: 600px; padding-bottom: 20px; }

        /* Contact */
        .contact { padding: 60px 0; background: var(--gray-50); }
        @media (min-width: 640px) { .contact { padding: 80px 0; } }
        .contact-wrapper {
            display: grid;
            grid-template-columns: 1fr;
            background: var(--white);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }
        @media (min-width: 768px) { .contact-wrapper { grid-template-columns: 1fr 1.5fr; border-radius: 32px; } }
        .contact-info { background: var(--maroon); padding: 32px 24px; color: var(--white); }
        @media (min-width: 640px) { .contact-info { padding: 40px; } }
        @media (min-width: 768px) { .contact-info { padding: 48px; } }
        .contact-info h3 { font-size: 1.5rem; margin-bottom: 12px; }
        @media (min-width: 640px) { .contact-info h3 { font-size: 1.75rem; margin-bottom: 16px; } }
        .contact-details { margin-top: 32px; display: flex; flex-direction: column; gap: 20px; }
        @media (min-width: 640px) { .contact-details { margin-top: 40px; gap: 24px; } }
        .contact-detail { display: flex; align-items: flex-start; gap: 14px; }
        .contact-detail i {
            width: 40px; height: 40px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        @media (min-width: 640px) { .contact-detail i { width: 44px; height: 44px; font-size: 18px; } }
        .contact-form { padding: 32px 24px; }
        @media (min-width: 640px) { .contact-form { padding: 40px; } }
        @media (min-width: 768px) { .contact-form { padding: 48px; } }
        .form-group { margin-bottom: 20px; }
        @media (min-width: 640px) { .form-group { margin-bottom: 24px; } }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 0.875rem; color: var(--gray-800); }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--gray-300);
            border-radius: 12px;
            font-family: inherit;
            font-size: 16px;
            transition: all 0.2s;
            background: var(--gray-50);
            -webkit-appearance: none;
            appearance: none;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(128, 0, 32, 0.1);
            background: var(--white);
        }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .alert-success, .alert-error { padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500; font-size: 0.875rem; }
        .alert-success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
        .alert-error { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }
        .inline-error { color: #dc2626; font-size: 0.75rem; margin-top: 4px; font-weight: 500; }

        /* Chatbot */
        .chatbot-toggle {
            position: fixed;
            bottom: 20px; right: 20px;
            width: 56px; height: 56px;
            background: var(--maroon);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-xl);
            transition: all 0.3s;
            z-index: 1000;
            border: none;
            color: var(--white);
            font-size: 24px;
        }
        .chatbot-toggle:hover { transform: scale(1.1); background: var(--maroon-dark); }
        .chatbot-window {
            position: fixed;
            bottom: 90px; right: 20px;
            width: calc(100vw - 40px);
            max-width: 380px;
            height: 480px;
            max-height: calc(100vh - 140px);
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            display: none;
            flex-direction: column;
            z-index: 999;
            overflow: hidden;
        }
        .chatbot-window.active { display: flex; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .chatbot-header { background: var(--maroon); color: white; padding: 14px 16px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .chatbot-header h4 { font-size: 0.9375rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .chatbot-header button { background: none; border: none; color: white; font-size: 18px; cursor: pointer; padding: 8px; border-radius: 8px; }
        .chatbot-messages { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 10px; background: var(--gray-50); -webkit-overflow-scrolling: touch; }
        .message { max-width: 85%; padding: 10px 14px; border-radius: 18px; font-size: 13px; line-height: 1.5; word-break: break-word; }
        .message-bot { background: white; color: var(--gray-800); align-self: flex-start; border: 1px solid var(--gray-200); border-bottom-left-radius: 4px; }
        .message-user { background: var(--maroon); color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .chatbot-input-area { padding: 12px 14px; border-top: 1px solid var(--gray-200); display: flex; gap: 10px; background: white; flex-shrink: 0; }
        .chatbot-input-area input { flex: 1; padding: 10px 14px; border: 1px solid var(--gray-300); border-radius: 25px; font-family: inherit; font-size: 16px; -webkit-appearance: none; }
        .chatbot-input-area button { background: var(--maroon); border: none; width: 44px; height: 44px; border-radius: 50%; color: white; cursor: pointer; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }

        /* Footer */
        .footer { background: #1a1a2e; color: rgba(255, 255, 255, 0.7); padding: 48px 0 24px; }
        @media (min-width: 640px) { .footer { padding: 60px 0 24px; } }
        .footer-grid { display: grid; grid-template-columns: 1fr; gap: 32px; margin-bottom: 40px; }
        @media (min-width: 640px) { .footer-grid { grid-template-columns: repeat(2, 1fr); gap: 40px; margin-bottom: 48px; } }
        @media (min-width: 768px) { .footer-grid { grid-template-columns: 2fr 1fr 1fr; gap: 48px; } }
        @media (min-width: 640px) and (max-width: 767px) {
            .footer-brand { grid-column: span 2; }
        }
        .footer-brand p { margin-top: 16px; font-size: 0.875rem; line-height: 1.6; color: rgba(255, 255, 255, 0.5); }
        .footer-links h4 { color: var(--white); margin-bottom: 16px; font-size: 0.9375rem; font-weight: 600; }
        @media (min-width: 640px) { .footer-links h4 { margin-bottom: 20px; font-size: 1rem; } }
        .footer-links ul { list-style: none; }
        .footer-links a { display: block; color: rgba(255, 255, 255, 0.5); text-decoration: none; margin-bottom: 10px; font-size: 0.875rem; transition: color 0.2s; }
        .footer-links a:hover { color: var(--gold); }
        .footer-bottom { border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 24px; display: flex; flex-direction: column; align-items: center; gap: 12px; font-size: 0.8125rem; text-align: center; }
        @media (min-width: 640px) { .footer-bottom { flex-direction: row; justify-content: space-between; font-size: 0.875rem; text-align: left; } }
        .footer-bottom a { color: rgba(255, 255, 255, 0.4); text-decoration: none; font-size: 0.8125rem; transition: color 0.2s; }
        .footer-bottom a:hover { color: var(--gold); }
        .footer-legal { display: flex; flex-wrap: wrap; justify-content: center; gap: 16px; }
        @media (min-width: 640px) { .footer-legal { justify-content: flex-end; } }

        /* Accessibility */
        a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible { outline: 2px solid var(--maroon-light); outline-offset: 2px; }
        .visually-hidden { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); border: 0; }
        img, video, iframe, table { max-width: 100%; height: auto; }

        @media (max-width: 480px) {
            .hero-stats { gap: 10px; }
            .stat-card { padding: 16px 12px; border-radius: 14px; }
            .stat-number { font-size: 1.35rem; }
            .stat-label { font-size: 0.6875rem; }
        }
    </style>
</head>
<body>

<!-- ============================================
     HEADER & NAVIGATION
     ============================================ -->
<header class="header" role="banner">
    <div class="container header-content">
        <!-- Logo -->
        <a href="/capstone1/index.php" class="logo" aria-label="PUPBC Carelink Homepage">
            <div class="logo-icon">
                <img src="assets/images/clinic logo.jpg" alt="PUPBC Carelink Clinic Logo" width="42" height="42" loading="eager">
            </div>
            <div class="logo-text">PUPBC <span>Carelink</span></div>
        </a>

        <!-- Desktop Navigation -->
        <div class="header-right" id="desktopNav">
            <nav class="nav-links" aria-label="Main Navigation">
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#faq">FAQ</a>
                <a href="#contact">Contact</a>
            </nav>
            <div class="nav-buttons">
                <a href="pages/student/student_login.php" class="btn-outline">Sign In</a>
                <a href="pages/student/student_register.php" class="btn-primary">Get Started</a>
            </div>
        </div>

        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobileMenu">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
    </div>
</header>

    <!-- Overlay for mobile menu -->
    <div class="menu-overlay" id="menuOverlay"></div>

    <!-- Mobile Menu Container -->
    <div class="mobile-menu" id="mobileMenu" role="navigation" aria-label="Mobile Navigation">
        <div class="mobile-menu-inner">
            <!-- Navigation Links -->
            <nav class="mobile-nav-links">
                <a href="#home" class="mobile-nav-link">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="#features" class="mobile-nav-link">
                    <i class="fas fa-star"></i> Features
                </a>
                <a href="#how-it-works" class="mobile-nav-link">
                    <i class="fas fa-cogs"></i> How It Works
                </a>
                <a href="#faq" class="mobile-nav-link">
                    <i class="fas fa-question-circle"></i> FAQ
                </a>
                <a href="#contact" class="mobile-nav-link">
                    <i class="fas fa-envelope"></i> Contact
                </a>
            </nav>
            
            <!-- Divider -->
            <div class="mobile-menu-divider"></div>
            
            <!-- Action Buttons -->
            <div class="mobile-menu-buttons">
                <a href="pages/student/student_login.php" class="mobile-btn-outline">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </a>
                <a href="pages/student/student_register.php" class="mobile-btn-primary">
                    <i class="fas fa-user-plus"></i> Get Started
                </a>
            </div>
        </div>
    </div>

<main id="main-content">
    <!-- ============================================
         HERO SECTION
         ============================================ -->
    <section id="home" class="hero" aria-labelledby="hero-heading">
        <div class="container">
            <div class="hero-grid">
                <div class="hero-text">
                    <div class="hero-badge" aria-label="Location Badge">
                        <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                        Now Serving PUP Binan Campus
                    </div>
                    <h1 id="hero-heading">Scan. Care.<br><span>Connect.</span></h1>
                    <p>QR-integrated health information system that brings the campus clinic into the digital age — faster check-ins, paperless records, and real-time insights.</p>
                </div>

                <div class="hero-stats">
                    <div class="stat-card">
                        <div class="stat-number">30s</div>
                        <div class="stat-label">Average Check-in Time</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">Paperless Records</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Portal Access</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">RA 10173</div>
                        <div class="stat-label">Data Privacy Compliant</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================
         FEATURES SECTION
         ============================================ -->
    <section id="features" class="features" aria-labelledby="features-heading">
        <div class="container">
            <h2 class="section-title" id="features-heading">Everything the clinic needs, in one place</h2>
            <p class="section-subtitle">From front desk to nurse's station, Carelink streamlines every interaction</p>
            
            <div class="features-grid">
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true"><i class="fas fa-qrcode"></i></div>
                    <h3>QR-Based Identity</h3>
                    <p>Every student gets a unique encrypted Carelink QR for instant clinic check-in.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></div>
                    <h3>Digital Health Records</h3>
                    <p>Allergies, conditions, vitals, and visit history kept in one secure place.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true"><i class="fas fa-stethoscope"></i></div>
                    <h3>Smart Nurse Dashboard</h3>
                    <p>Live queue, SOAP notes, prescriptions, and inventory in a single workspace.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true"><i class="fas fa-shield-alt"></i></div>
                    <h3>Privacy-First</h3>
                    <p>Aligned with Data Privacy Act of 2012 (RA 10173). Encrypted tokens, audit logs, role-based access.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true"><i class="fas fa-chart-line"></i></div>
                    <h3>Health Analytics</h3>
                    <p>Spot symptom trends and outbreaks early with real-time campus health insights.</p>
                </article>
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true"><i class="fas fa-microchip"></i></div>
                    <h3>Self-Service Kiosk</h3>
                    <p>Touchscreen check-in that cuts queue time to under 30 seconds.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ============================================
         HOW IT WORKS SECTION
         ============================================ -->
    <section id="how-it-works" class="how-it-works" aria-labelledby="how-heading">
        <div class="container">
            <h2 class="section-title" id="how-heading">How Carelink works</h2>
            <p class="section-subtitle">Three simple steps from sign-up to consultation</p>
            
            <div class="steps-grid">
                <div class="step">
                    <div class="step-number" aria-hidden="true">1</div>
                    <i class="fas fa-user-plus" aria-hidden="true"></i>
                    <h3>Register</h3>
                    <p>Students enroll once and receive a unique Carelink QR code linked to their secure profile.</p>
                </div>
                <div class="step">
                    <div class="step-number" aria-hidden="true">2</div>
                    <i class="fas fa-qrcode" aria-hidden="true"></i>
                    <h3>Scan & Check-in</h3>
                    <p>Scan at the kiosk or web portal. Log symptoms and join the queue in seconds.</p>
                </div>
                <div class="step">
                    <div class="step-number" aria-hidden="true">3</div>
                    <i class="fas fa-heartbeat" aria-hidden="true"></i>
                    <h3>Get Care</h3>
                    <p>Nurses pull up records instantly, document the visit, and dispense medicine — all paperless.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================
         FAQ SECTION
         ============================================ -->
    <section id="faq" class="faq" aria-labelledby="faq-heading">
        <div class="container">
            <h2 class="section-title" id="faq-heading">Frequently Asked Questions</h2>
            <p class="section-subtitle">Find answers to common questions about PUPBC Carelink</p>
            
            <div class="faq-grid" role="list">
                <div class="faq-item" role="listitem">
                    <button class="faq-question" aria-expanded="false" id="faq-btn-1">
                        <span>How do I get my Carelink QR code?</span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="faq-answer" role="region" aria-labelledby="faq-btn-1">
                        <p>Once you register through the student portal, your unique encrypted Carelink QR code will be generated and available on your dashboard. You can download or print it from there.</p>
                    </div>
                </div>
                <div class="faq-item" role="listitem">
                    <button class="faq-question" aria-expanded="false" id="faq-btn-2">
                        <span>Is my medical information secure?</span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="faq-answer" role="region" aria-labelledby="faq-btn-2">
                        <p>Yes, your data is protected and strictly follows the Data Privacy Act of 2012 (RA 10173). Only authorized clinic staff can view your health records. All data is encrypted and stored securely.</p>
                    </div>
                </div>
                <div class="faq-item" role="listitem">
                    <button class="faq-question" aria-expanded="false" id="faq-btn-3">
                        <span>What if I lose my Carelink ID?</span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="faq-answer" role="region" aria-labelledby="faq-btn-3">
                        <p>You can easily access and re-download your digital Carelink QR code anytime by logging into the student portal. The old QR code will be invalidated for security.</p>
                    </div>
                </div>
                <div class="faq-item" role="listitem">
                    <button class="faq-question" aria-expanded="false" id="faq-btn-4">
                        <span>Can I book appointments online?</span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="faq-answer" role="region" aria-labelledby="faq-btn-4">
                        <p>Yes! Students can book appointments through the student portal. You'll receive email confirmation and reminders for your scheduled appointments.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================
         CONTACT SECTION
         ============================================ -->
    <section id="contact" class="contact" aria-labelledby="contact-heading">
        <div class="container">
            <div class="contact-wrapper">
                <div class="contact-info">
                    <h3 id="contact-heading">Contact Us</h3>
                    <p>Get in touch with the PUPBC Clinic for inquiries or assistance.</p>
                    
                    <div class="contact-details">
                        <div class="contact-detail">
                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                            <div>
                                <strong>Location</strong><br>
                                Brgy. Zapote, Biñan City, Laguna
                            </div>
                        </div>
                        <div class="contact-detail">
                            <i class="fas fa-envelope" aria-hidden="true"></i>
                            <div>
                                <strong>Email</strong><br>
                                clinic.binan@pup.edu.ph
                            </div>
                        </div>
                        <div class="contact-detail">
                            <i class="fas fa-phone" aria-hidden="true"></i>
                            <div>
                                <strong>Phone</strong><br>
                                (049) 123-4567
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="contact-form">
                    <?php if ($contact_success): ?>
                        <div class="alert-success" role="alert">
                            <i class="fas fa-check-circle" aria-hidden="true"></i> 
                            <span><?php echo htmlspecialchars($contact_success, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($contact_error): ?>
                        <div class="alert-error" role="alert">
                            <i class="fas fa-exclamation-circle" aria-hidden="true"></i> 
                            <span><?php echo htmlspecialchars($contact_error, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!$contact_success): ?>
                    <form method="POST" id="contactForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="text" name="bot_check" class="visually-hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                        
                        <div class="form-group">
                            <label for="contact_name">Your Name <span style="color: var(--maroon);">*</span></label>
                            <input type="text" id="contact_name" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" required placeholder="Juan Dela Cruz" autocomplete="name" aria-required="true" pattern="[a-zA-Z\s\-'.\/]+" title="Name should only contain letters, spaces, hyphens, apostrophes, periods, or slashes">
                        </div>
                        <div class="form-group">
                            <label for="contact_email">Email Address <span style="color: var(--maroon);">*</span></label>
                            <input type="email" id="contact_email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required placeholder="juan@example.com" autocomplete="email" aria-required="true">
                        </div>
                        <div class="form-group">
                            <label for="contact_message">Message <span style="color: var(--maroon);">*</span></label>
                            <textarea id="contact_message" name="message" rows="5" required placeholder="How can we help you?" aria-required="true"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <button type="submit" name="contact_submit" class="btn-primary" style="width: 100%; justify-content: center;">
                            <i class="fas fa-paper-plane" aria-hidden="true"></i> Send Message
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- ============================================
     FOOTER
     ============================================ -->
<footer class="footer" role="contentinfo">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="logo">
                    <div class="logo-icon">
                        <img src="assets/images/clinic logo.jpg" alt="PUPBC Carelink Logo" width="42" height="42" loading="lazy">
                    </div>
                    <div class="logo-text" style="color: white;">PUPBC <span style="color: var(--gold);">Carelink</span></div>
                </div>
                <p>QR-integrated health information system for PUP Binan Campus. Bringing faster check-ins, paperless records, and real-time insights.</p>
            </div>
            <div class="footer-links">
                <h4>Quick Links</h4>
                <nav aria-label="Footer Quick Links">
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#faq">FAQ</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </nav>
            </div>
            <div class="footer-links">
                <h4>Student Portal</h4>
                <nav aria-label="Footer Student Links">
                    <ul>
                        <li><a href="pages/student/student_login.php">Sign In</a></li>
                        <li><a href="pages/student/student_register.php">Register</a></li>
                    </ul>
                </nav>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo $year; ?> PUPBC Carelink. All rights reserved.</p>
            <div class="footer-legal">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Use</a>
                <a href="#">Data Privacy Notice</a>
            </div>
        </div>
    </div>
</footer>

<!-- ============================================
     AI CHATBOT
     ============================================ -->
<button class="chatbot-toggle" id="chatbotToggle" aria-label="Open chat assistant" aria-expanded="false" aria-controls="chatbotWindow">
    <i class="fas fa-robot" aria-hidden="true"></i>
</button>

<div class="chatbot-window" id="chatbotWindow" role="dialog" aria-modal="true" aria-labelledby="chatbotTitle">
    <div class="chatbot-header">
        <h4 id="chatbotTitle">
            <img src="assets/images/clinic logo.jpg" alt="" width="22" height="22" style="border-radius:4px; object-fit:cover;" loading="lazy" aria-hidden="true">
            Carelink Assistant
        </h4>
        <button id="closeChatbot" aria-label="Close chat assistant">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>
    <div class="chatbot-messages" id="chatMessages" role="log" aria-live="polite">
        <div class="message message-bot">
            Hello! I'm Carelink Assistant. How can I help you today?
        </div>
        <div class="message message-bot">
            You can ask me about:<br>
            • How to register<br>
            • Getting your QR code<br>
            • Booking appointments<br>
            • Clinic hours and location<br>
            • Using the kiosk
        </div>
    </div>
    <div class="chatbot-input-area">
        <input type="text" id="chatInput" placeholder="Type your question here..." autocomplete="off" aria-label="Message input">
        <button id="sendChatBtn" aria-label="Send message">
            <i class="fas fa-paper-plane" aria-hidden="true"></i>
        </button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ============================================
        // MOBILE NAVIGATION
        // ============================================
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        const menuOverlay = document.getElementById('menuOverlay');
        const body = document.body;
        
        function openMobileMenu() {
            mobileMenu.classList.add('active');
            menuOverlay.classList.add('active');
            mobileMenuBtn.setAttribute('aria-expanded', 'true');
            mobileMenuBtn.querySelector('i').className = 'fas fa-times';
            body.style.overflow = 'hidden';
        }
        
        function closeMobileMenu() {
            mobileMenu.classList.remove('active');
            menuOverlay.classList.remove('active');
            mobileMenuBtn.setAttribute('aria-expanded', 'false');
            mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
            body.style.overflow = '';
        }
        
        mobileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (mobileMenu.classList.contains('active')) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        });
        
        // Close menu when clicking overlay
        menuOverlay.addEventListener('click', closeMobileMenu);
        
        // Close menu when clicking any link inside mobile menu
        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                setTimeout(closeMobileMenu, 150);
            });
        });
        
        // Close menu on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && mobileMenu.classList.contains('active')) {
                closeMobileMenu();
                mobileMenuBtn.focus();
            }
        });
        
        // Ensure menu closes on window resize to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                closeMobileMenu();
            }
        });

        // ============================================
        // FAQ ACCORDION
        // ============================================
        document.querySelectorAll('.faq-question').forEach(button => {
            button.addEventListener('click', function() {
                const expanded = this.getAttribute('aria-expanded') === 'true';
                
                document.querySelectorAll('.faq-question').forEach(btn => {
                    btn.setAttribute('aria-expanded', 'false');
                    btn.nextElementSibling?.classList.remove('open');
                });
                
                if (!expanded) {
                    this.setAttribute('aria-expanded', 'true');
                    this.nextElementSibling?.classList.add('open');
                }
            });
        });

        // ============================================
        // SMOOTH SCROLL
        // ============================================
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    const offset = document.querySelector('.header').offsetHeight;
                    const top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({ top, behavior: 'smooth' });
                }
            });
        });

        // ============================================
        // AI CHATBOT
        // ============================================
        const chatbotToggle = document.getElementById('chatbotToggle');
        const chatbotWindow = document.getElementById('chatbotWindow');
        const closeChatbot = document.getElementById('closeChatbot');
        const chatInput = document.getElementById('chatInput');
        const sendChatBtn = document.getElementById('sendChatBtn');
        const chatMessages = document.getElementById('chatMessages');
        
        let isBotTyping = false;

        function openChatbot() {
            chatbotWindow.classList.add('active');
            chatbotToggle.setAttribute('aria-expanded', 'true');
            chatbotToggle.style.display = 'none';
            setTimeout(() => chatInput.focus(), 300);
        }

        function closeChatbotWindow() {
            chatbotWindow.classList.remove('active');
            chatbotToggle.setAttribute('aria-expanded', 'false');
            chatbotToggle.style.display = 'flex';
        }

        chatbotToggle.addEventListener('click', openChatbot);
        closeChatbot.addEventListener('click', closeChatbotWindow);

        function getAIResponse(msg) {
            const m = msg.toLowerCase().trim();
            if (m.includes('register') || m.includes('sign up')) return "To register, go to the Student Portal and click 'Get Started'. You'll need your student number and email. After registration, you'll receive your unique Carelink QR code!";
            if (m.includes('qr')) return "Your Carelink QR code is available on your student dashboard after registration. Use it to check in at the kiosk for quick access to clinic services.";
            if (m.includes('appointment') || m.includes('book')) return "You can book appointments through the Student Portal after logging in. Go to 'Appointments' tab and select your preferred date and time.";
            if (m.includes('hours') || m.includes('open')) return "Clinic Hours:\n- Monday to Friday: 7:30 AM - 5:00 PM\n- Saturday: 8:00 AM - 12:00 PM\n- Sunday: Closed";
            if (m.includes('location') || m.includes('where')) return "PUP Binan Campus is located at Brgy. Zapote, Binan City, Laguna. The clinic is on the ground floor of the main building.";
            if (m.includes('kiosk') || m.includes('check in')) return "The self-service kiosk lets you check in using your QR code. Answer the health assessment and receive your queue number.";
            if (m.includes('login') || m.includes('password')) return "Use the 'Sign In' button on the homepage. If you forgot your password, contact the clinic administrator.";
            if (m.includes('contact') || m.includes('email')) return "Email: clinic.binan@pup.edu.ph\nPhone: (049) 123-4567\nOr use the contact form!";
            if (m.includes('hello') || m.includes('hi')) return "Hello! Welcome to PUPBC Carelink. How can I assist you today?";
            if (m.includes('thank')) return "You're welcome! Is there anything else I can help you with?";
            return "I'm not sure about that. Please contact the clinic at clinic.binan@pup.edu.ph or call (049) 123-4567.";
        }

        function addMessage(text, isUser) {
            const div = document.createElement('div');
            div.className = `message ${isUser ? 'message-user' : 'message-bot'}`;
            div.textContent = text;
            chatMessages.appendChild(div);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function sendMessage() {
            const userMsg = chatInput.value.trim();
            if (!userMsg || isBotTyping) return;
            
            isBotTyping = true;
            sendChatBtn.disabled = true;
            addMessage(userMsg, true);
            chatInput.value = '';
            
            const typingDiv = document.createElement('div');
            typingDiv.className = 'message message-bot';
            typingDiv.textContent = '...';
            typingDiv.id = 'typingIndicator';
            chatMessages.appendChild(typingDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            setTimeout(() => {
                document.getElementById('typingIndicator')?.remove();
                addMessage(getAIResponse(userMsg), false);
                isBotTyping = false;
                sendChatBtn.disabled = false;
                chatInput.focus();
            }, 800 + Math.random() * 500);
        }

        sendChatBtn.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage();
        });

        document.addEventListener('click', function(e) {
            if (chatbotWindow.classList.contains('active') && 
                !chatbotWindow.contains(e.target) && 
                !chatbotToggle.contains(e.target)) {
                closeChatbotWindow();
            }
        });

        // ============================================
        // CONTACT FORM VALIDATION
        // ============================================
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                this.querySelectorAll('.inline-error').forEach(el => el.remove());
                const fields = this.querySelectorAll('input:not([type=hidden]), textarea');
                fields.forEach(f => f.style.borderColor = '');
                
                let isValid = true;
                const nameEl = document.getElementById('contact_name');
                const emailEl = document.getElementById('contact_email');
                const messageEl = document.getElementById('contact_message');

                function showError(el, msg) {
                    el.style.borderColor = '#dc2626';
                    const err = document.createElement('p');
                    err.className = 'inline-error';
                    err.textContent = msg;
                    el.parentNode.appendChild(err);
                    isValid = false;
                }

                if (nameEl.value.trim().length < 2) showError(nameEl, 'Please enter your full name.');
                
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(emailEl.value.trim())) showError(emailEl, 'Please enter a valid email address.');
                
                if (messageEl.value.trim().length < 10) showError(messageEl, 'Message must be at least 10 characters.');

                if (!isValid) {
                    e.preventDefault();
                    this.querySelector('.inline-error')?.previousElementSibling?.focus();
                } else {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                }
            });
        }
    });
</script>

</body>
</html>