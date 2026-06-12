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
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $contact_error = 'Security verification failed. Please refresh the page.';
    } elseif (!empty($_POST['bot_check'])) {
        $contact_success = 'Message received. We\'ll get back to you soon.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        $errors = [];
        
        if (strlen($name) < 2) $errors[] = 'Please enter your full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if (strlen($message) < 10) $errors[] = 'Message must be at least 10 characters.';
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $last_submit_key = 'contact_' . str_replace('.', '_', $ip);
        
        if (isset($_SESSION[$last_submit_key]) && time() - $_SESSION[$last_submit_key] < 300) {
            $errors[] = 'Please wait 5 minutes before sending another message.';
        }
        
        if (empty($errors) && isset($conn) && $conn) {
            $stmt = mysqli_prepare($conn, "INSERT INTO contact_messages (name, email, message, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $message, $ip);
            
            if (mysqli_stmt_execute($stmt)) {
                $contact_success = 'Thank you! Your message has been sent.';
                $_SESSION[$last_submit_key] = time();
                $name = $email = $message = '';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $contact_error = 'Failed to send message. Please try again.';
            }
            mysqli_stmt_close($stmt);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="description" content="PUPBC Carelink - QR-integrated health information system for PUP Binan Campus">
    <title>PUPBC Carelink | Smart Campus Health System</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #fefaf5;
            color: #1a1a2e;
            line-height: 1.5;
        }
        
        /* Color Variables */
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
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        /* Utility Classes */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }
        
.btn-primary {
            background: var(--maroon);
            color: white;
            padding: 10px 24px;
            line-height: 1;
            min-height: 44px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 15px;
            border: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(128, 0, 32, 0.15);
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }
        
        .btn-primary:hover {
            background: var(--maroon-dark);
            box-shadow: 0 6px 16px rgba(128, 0, 32, 0.25);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: scale(0.96);
            box-shadow: 0 2px 8px rgba(128, 0, 32, 0.1);
        }
        
        .btn-outline {
            background: white;
            color: var(--gray-800);
            padding: 10px 24px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 15px;
            border: 1px solid var(--gray-200);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }
        
        .btn-outline:hover {
            border-color: var(--gray-300);
            background: var(--gray-50);
            transform: translateY(-1px);
        }

        .btn-outline:active {
            transform: scale(0.96);
            background: var(--gray-100);
            box-shadow: none;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 16px;
            color: var(--gray-800);
        }
        
        .section-subtitle {
            text-align: center;
            color: var(--gray-600);
            max-width: 600px;
            margin: 0 auto 48px;
            font-size: 1.125rem;
        }
        
        /* Header */
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--gray-200);
            /* Required so the mobile dropdown positions relative to the header */
            position: sticky;
        }

        /* Mobile nav dropdown */
        .mobile-nav-open {
            display: flex !important;
            flex-direction: column;
            position: absolute;
            top: 70px;
            left: 0;
            right: 0;
            background: white;
            padding: 16px 24px;
            box-shadow: var(--shadow-md);
            z-index: 99;
            gap: 12px;
        }

        .mobile-btns-open {
            display: flex !important;
            position: absolute;
            left: 0;
            right: 0;
            background: white;
            padding: 12px 24px 20px;
            box-shadow: var(--shadow-md);
            z-index: 99;
            justify-content: center;
            gap: 12px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }

        /* Right side: nav + buttons grouped together */
        .header-right {
            display: flex;
            align-items: center;
            gap: 32px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            flex-shrink: 0;
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .logo-text {
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .logo-text span {
            color: var(--gold);
        }
        
        .nav-links {
            display: flex;
            gap: 28px;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--gray-600);
            font-weight: 500;
            font-size: 0.9375rem;
            transition: color 0.2s;
            white-space: nowrap;
        }

        .nav-links a:hover {
            color: var(--maroon);
        }

        .nav-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: nowrap;
        }

        .nav-buttons a {
            white-space: nowrap;
            line-height: 1;
        }

        /* extra safety for tight headers */
        .header-right { min-width: 0; }
        .nav-buttons { min-width: 0; }
        .nav-buttons a { flex: 0 1 auto; }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray-800);
            padding: 4px;
        }
        
        /* Hero logo image */
        .hero-logo-img {
            width: 100%;
            max-width: 380px;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            filter: drop-shadow(0 8px 24px rgba(0,0,0,0.35));
            border-radius: 20px;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #800020 0%, #4a0010 50%, #800020 100%);
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/pupbg.jpg') center/cover;
            opacity: 0.1;
            pointer-events: none;
        }
        
        .hero-content {
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
        }
        
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(201, 168, 76, 0.2);
            border: 1px solid rgba(201, 168, 76, 0.4);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.875rem;
            color: var(--gold);
            margin-bottom: 24px;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            color: white;
            margin-bottom: 20px;
        }
        
        .hero h1 span {
            color: var(--gold);
        }
        
        .hero p {
            font-size: 1.125rem;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 32px;
            max-width: 500px;
        }
        
        .hero-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .hero-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-top: 40px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--gold);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        /* Features Section */
        .features {
            padding: 80px 0;
            background: white;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 32px;
        }
        
        .feature-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 24px;
            padding: 32px;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--gold);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }
        
        .feature-icon i {
            font-size: 28px;
            color: white;
        }
        
        .feature-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--gray-800);
        }
        
        .feature-card p {
            color: var(--gray-600);
            line-height: 1.6;
        }
        
        /* How It Works */
        .how-it-works {
            background: var(--gray-50);
            padding: 80px 0;
        }
        
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px;
        }
        
        .step {
            text-align: center;
            padding: 32px;
        }
        
        .step-number {
            width: 60px;
            height: 60px;
            background: var(--gold);
            color: var(--maroon);
            font-size: 1.75rem;
            font-weight: 800;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .step h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .step p {
            color: var(--gray-600);
        }
        
        /* FAQ Section */
        .faq {
            padding: 80px 0;
            background: white;
        }
        
        .faq-grid {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .faq-item {
            border-bottom: 1px solid var(--gray-200);
            padding: 20px 0;
        }
        
        .faq-question {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.125rem;
            padding: 8px 0;
        }
        
        .faq-question i {
            transition: transform 0.3s;
        }
        
        .faq-question.active i {
            transform: rotate(180deg);
        }
        
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            color: var(--gray-600);
            line-height: 1.6;
            padding-right: 24px;
        }
        
        .faq-answer.show {
            max-height: 600px;
            padding-top: 12px;
        }
        
        /* Contact Section */
        .contact {
            padding: 80px 0;
            background: var(--gray-50);
        }
        
        .contact-wrapper {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 48px;
            background: white;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }
        
        .contact-info {
            background: var(--maroon);
            padding: 48px;
            color: white;
        }
        
        .contact-info h3 {
            font-size: 1.75rem;
            margin-bottom: 16px;
        }
        
        .contact-details {
            margin-top: 40px;
        }
        
        .contact-detail {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .contact-detail i {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .contact-form {
            padding: 48px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--gray-800);
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--gray-300);
            border-radius: 12px;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(128, 0, 32, 0.1);
        }
        
        .alert-success {
            background: #d1fae5;
            border: 1px solid #6ee7b7;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        /* Chatbot */
        .chatbot-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 60px;
            height: 60px;
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
        }
        
        .chatbot-btn:hover {
            transform: scale(1.1);
            background: var(--maroon-dark);
        }
        
        .chatbot-btn i {
            font-size: 28px;
            color: white;
        }
        
        .chatbot-window {
            position: fixed;
            bottom: 100px;
            right: 24px;
            width: 380px;
            height: 500px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            display: none;
            flex-direction: column;
            z-index: 1000;
            overflow: hidden;
            animation: slideUp 0.3s ease;
        }
        
        .chatbot-window.open {
            display: flex;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .chatbot-header {
            background: var(--maroon);
            color: white;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chatbot-header h4 {
            font-size: 1rem;
            font-weight: 600;
        }
        
        .chatbot-header button {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
        }
        
        .chatbot-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: var(--gray-50);
        }
        
        .message {
            max-width: 85%;
            padding: 10px 14px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .message-bot {
            background: white;
            color: var(--gray-800);
            align-self: flex-start;
            border: 1px solid var(--gray-200);
            border-bottom-left-radius: 4px;
        }
        
        .message-user {
            background: var(--maroon);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        
        .chatbot-input {
            padding: 16px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 12px;
            background: white;
        }
        
        .chatbot-input input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid var(--gray-300);
            border-radius: 25px;
            font-family: inherit;
            font-size: 14px;
        }
        
        .chatbot-input input:focus {
            outline: none;
            border-color: var(--maroon);
        }
        
        .chatbot-input button {
            background: var(--maroon);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .chatbot-input button:hover {
            background: var(--maroon-dark);
        }
        
        .typing-indicator {
            display: flex;
            gap: 4px;
            padding: 10px 14px;
            background: white;
            border-radius: 18px;
            width: fit-content;
            border: 1px solid var(--gray-200);
        }
        
        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: var(--gray-400);
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
                opacity: 0.4;
            }
            30% {
                transform: translateY(-8px);
                opacity: 1;
            }
        }
        
        /* Footer */
        .footer {
            background: #1a1a2e;
            color: white;
            padding: 48px 0 24px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 48px;
            margin-bottom: 48px;
        }
        
        .footer-logo .logo-icon {
            background: white;
        }
        
        .footer-logo p {
            margin-top: 16px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
        }
        
        .footer-links h4 {
            margin-bottom: 16px;
            font-size: 1rem;
        }
        
        .footer-links a {
            display: block;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            margin-bottom: 10px;
            font-size: 14px;
            transition: color 0.2s;
        }
        
        .footer-links a:hover {
            color: var(--gold);
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 14px;
            color: rgba(255, 255, 255, 0.5);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 0 16px; }
            .nav-links, .nav-buttons { display: none; }
            .mobile-menu-btn { display: block; }
            .section-title { font-size: 1.75rem; }
            .contact-wrapper { grid-template-columns: 1fr; }
            .chatbot-window { width: calc(100vw - 48px); right: 24px; height: 480px; }
        }

        @media (max-width: 480px) {
            .features-grid { grid-template-columns: 1fr; }
            .steps-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="container">
        <div class="header-content">
            <!-- Left: Logo -->
            <div class="logo">
                <div class="logo-icon">
                    <img src="assets/images/clinic logo.jpg" alt="PUPBC Carelink Clinic Logo">
                </div>
                <div class="logo-text">PUPBC <span>Carelink</span></div>
            </div>

            <!-- Right: Nav links + Buttons grouped together -->
            <div class="header-right">
                <nav class="nav-links">
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

            <!-- Mobile hamburger -->
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
</header>

<!-- Hero Section -->
<section id="home" class="hero">
    <div class="container">
        <div class="hero-content">
            <div>
                <div class="hero-badge">
                    <i class="fas fa-map-marker-alt"></i>
                    Now Serving PUP Binan Campus
                </div>
                <h1>Scan. Care.<br><span>Connect.</span></h1>
                <p>QR-integrated health information system that brings the campus clinic into the digital age — faster check-ins, paperless records, and real-time insights.</p>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="features">
    <div class="container">
        <h2 class="section-title">Everything the clinic needs, in one place</h2>
        <p class="section-subtitle">From front desk to nurse's station, Carelink streamlines every interaction</p>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-qrcode"></i></div>
                <h3>QR-Based Identity</h3>
                <p>Every student gets a unique encrypted Carelink QR for instant clinic check-in.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-clipboard-list"></i></div>
                <h3>Digital Health Records</h3>
                <p>Allergies, conditions, vitals, and visit history kept in one secure place.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-stethoscope"></i></div>
                <h3>Smart Nurse Dashboard</h3>
                <p>Live queue, SOAP notes, prescriptions, and inventory in a single workspace.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Privacy-First</h3>
                <p>Aligned with Data Privacy Act of 2012 (RA 10173). Encrypted tokens, audit logs, role-based access.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3>Health Analytics</h3>
                <p>Spot symptom trends and outbreaks early with real-time campus health insights.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-microchip"></i></div>
                <h3>Self-Service Kiosk</h3>
                <p>Touchscreen check-in that cuts queue time to under 30 seconds.</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section id="how-it-works" class="how-it-works">
    <div class="container">
        <h2 class="section-title">How Carelink works</h2>
        <p class="section-subtitle">Three simple steps from sign-up to consultation</p>
        
        <div class="steps-grid">
            <div class="step">
                <div class="step-number">1</div>
                <i class="fas fa-user-plus" style="font-size: 40px; color: var(--gold); margin-bottom: 20px; display: block;"></i>
                <h3>Register</h3>
                <p>Students enroll once and receive a unique Carelink QR code linked to their secure profile.</p>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <i class="fas fa-qrcode" style="font-size: 40px; color: var(--gold); margin-bottom: 20px; display: block;"></i>
                <h3>Scan & Check-in</h3>
                <p>Scan at the kiosk or web portal. Log symptoms and join the queue in seconds.</p>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <i class="fas fa-heartbeat" style="font-size: 40px; color: var(--gold); margin-bottom: 20px; display: block;"></i>
                <h3>Get Care</h3>
                <p>Nurses pull up records instantly, document the visit, and dispense medicine — all paperless.</p>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section id="faq" class="faq">
    <div class="container">
        <h2 class="section-title">Frequently Asked Questions</h2>
        <p class="section-subtitle">Find answers to common questions about PUPBC Carelink</p>
        
        <div class="faq-grid">
            <div class="faq-item">
                <div class="faq-question">
                    How do I get my Carelink QR code?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Once you register through the student portal, your unique encrypted Carelink QR code will be generated and available on your dashboard. You can download or print it from there.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question">
                    Is my medical information secure?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Yes, your data is protected and strictly follows the Data Privacy Act of 2012 (RA 10173). Only authorized clinic staff can view your health records. All data is encrypted and stored securely.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question">
                    What if I lose my Carelink ID?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    You can easily access and re-download your digital Carelink QR code anytime by logging into the student portal. The old QR code will be invalidated for security.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question">
                    Can I book appointments online?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Yes! Students can book appointments through the student portal. You'll receive email confirmation and reminders for your scheduled appointments.
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section id="contact" class="contact">
    <div class="container">
        <div class="contact-wrapper">
            <div class="contact-info">
                <h3>Contact Us</h3>
                <p>Get in touch with the PUPBC Clinic for inquiries or assistance.</p>
                
                <div class="contact-details">
                    <div class="contact-detail">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <strong>Location</strong><br>
                            Brgy. Zapote, Biñan City, Laguna
                        </div>
                    </div>
                    <div class="contact-detail">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <strong>Email</strong><br>
                            clinic.binan@pup.edu.ph
                        </div>
                    </div>
                    <div class="contact-detail">
                        <i class="fas fa-phone"></i>
                        <div>
                            <strong>Phone</strong><br>
                            (049) 123-4567
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="contact-form">
                <?php if ($contact_success): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($contact_success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($contact_error): ?>
                    <div class="alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($contact_error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$contact_success): ?>
                <form method="POST" id="contactForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="text" name="bot_check" style="display:none;" tabindex="-1" autocomplete="off">
                    
                    <div class="form-group">
                        <label for="contact_name">Your Name <span style="color: var(--maroon);">*</span></label>
                        <input type="text" id="contact_name" name="name" value="<?php echo htmlspecialchars($name); ?>" required placeholder="Juan Dela Cruz" autocomplete="name">
                    </div>
                    <div class="form-group">
                        <label for="contact_email">Email Address <span style="color: var(--maroon);">*</span></label>
                        <input type="email" id="contact_email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="juan@example.com" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="contact_message">Message <span style="color: var(--maroon);">*</span></label>
                        <textarea id="contact_message" name="message" rows="4" required placeholder="How can we help you?"><?php echo htmlspecialchars($message); ?></textarea>
                    </div>
                    <button type="submit" name="contact_submit" class="btn-primary" style="width: 100%; justify-content: center;">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-logo">
                <div class="logo" style="margin-bottom: 16px;">
                    <div class="logo-icon">
                        <img src="assets/images/clinic logo.jpg" alt="PUPBC Carelink Clinic Logo">
                    </div>
                    <div class="logo-text" style="color: white;">PUPBC <span style="color: var(--gold);">Carelink</span></div>
                </div>
                <p>QR-integrated health information system for PUP Binan Campus.</p>
            </div>
            <div class="footer-links">
                <h4>Quick Links</h4>
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#faq">FAQ</a>
            </div>
            <div class="footer-links">
                <h4>Student Portal</h4>
                <a href="pages/student/student_login.php">Sign In</a>
                <a href="pages/student/student_register.php">Register</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo $year; ?> PUPBC Carelink. Now Serving PUP Binan Campus. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- AI Chatbot Button -->
<button class="chatbot-btn" id="chatbotBtn">
    <i class="fas fa-robot"></i>
</button>

<!-- AI Chatbot Window -->
<div class="chatbot-window" id="chatbotWindow">
    <div class="chatbot-header">
        <h4><img src="assets/images/clinic logo.jpg" alt="" style="width:22px;height:22px;border-radius:4px;object-fit:cover;vertical-align:middle;margin-right:6px;"> Carelink Assistant</h4>
        <button id="closeChatbotBtn"><i class="fas fa-times"></i></button>
    </div>
    <div class="chatbot-messages" id="chatMessages">
        <div class="message message-bot">
            Hello! I'm Carelink Assistant. How can I help you today?
        </div>
        <div class="message message-bot">
            You can ask me about:<br>
            &bull; How to register<br>
            &bull; Getting your QR code<br>
            &bull; Booking appointments<br>
            &bull; Clinic hours and location<br>
            &bull; Using the kiosk
        </div>
    </div>
    <div class="chatbot-input">
        <input type="text" id="chatInput" placeholder="Type your question here..." autocomplete="off">
        <button id="sendChatBtn"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navLinks = document.querySelector('.nav-links');
    const navButtons = document.querySelector('.nav-buttons');
    let mobileMenuOpen = false;

    function closeMobileMenu() {
        mobileMenuOpen = false;
        navLinks.classList.remove('mobile-nav-open');
        navButtons.classList.remove('mobile-btns-open');
        // Reset inline styles set by previous toggle logic (if any)
        navLinks.style.cssText = '';
        navButtons.style.cssText = '';
        mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
    }

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileMenuOpen = !mobileMenuOpen;
            if (mobileMenuOpen) {
                navLinks.classList.add('mobile-nav-open');
                // Position navButtons directly below navLinks
                navButtons.style.display = 'flex';
                navButtons.style.flexDirection = 'column';
                navButtons.style.position = 'absolute';
                navButtons.style.left = '0';
                navButtons.style.right = '0';
                navButtons.style.top = (navLinks.offsetTop + navLinks.offsetHeight) + 'px';
                navButtons.style.background = 'white';
                navButtons.style.padding = '0 24px 24px';
                navButtons.style.gap = '12px';
                navButtons.style.boxShadow = '0 10px 15px -3px rgba(0,0,0,0.1)';
                navButtons.style.zIndex = '98';
                
                // Remove shadow from navLinks when menu is open to blend them
                navLinks.style.boxShadow = 'none';
                
                mobileMenuBtn.querySelector('i').className = 'fas fa-times';
            } else {
                closeMobileMenu();
            }
        });

        // Close menu when a nav link is clicked
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => closeMobileMenu());
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (mobileMenuOpen && !e.target.closest('.header')) {
                closeMobileMenu();
            }
        });
    }
    
    // FAQ Accordion
    document.querySelectorAll('.faq-question').forEach(question => {
        question.addEventListener('click', () => {
            const answer = question.nextElementSibling;
            const isActive = question.classList.contains('active');
            
            document.querySelectorAll('.faq-question').forEach(q => {
                q.classList.remove('active');
                q.nextElementSibling.classList.remove('show');
            });
            
            if (!isActive) {
                question.classList.add('active');
                answer.classList.add('show');
            }
        });
    });
    
    // Smooth scroll for anchor links — offset for sticky header
    const HEADER_HEIGHT = 70;
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                const top = target.getBoundingClientRect().top + window.scrollY - HEADER_HEIGHT;
                window.scrollTo({ top, behavior: 'smooth' });
            }
        });
    });
    
    // ============================================
    // AI CHATBOT - Smart Responses
    // ============================================
    
    const chatbotBtn = document.getElementById('chatbotBtn');
    const chatbotWindow = document.getElementById('chatbotWindow');
    const closeChatbotBtn = document.getElementById('closeChatbotBtn');
    const chatInput = document.getElementById('chatInput');
    const sendChatBtn = document.getElementById('sendChatBtn');
    const chatMessages = document.getElementById('chatMessages');
    
    let isBotTyping = false;

    // Open chatbot
    chatbotBtn.addEventListener('click', () => {
        chatbotWindow.classList.add('open');
        chatInput.focus();
    });
    
    // Close chatbot
    closeChatbotBtn.addEventListener('click', () => {
        chatbotWindow.classList.remove('open');
    });
    
    // Send message on button click
    sendChatBtn.addEventListener('click', () => {
        sendMessage();
    });
    
    // Send message on Enter key
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
    
    // AI Response Database
    function getAIResponse(userMessage) {
        const msg = userMessage.toLowerCase().trim();
        
        // Registration questions
        if (msg.includes('register') || msg.includes('sign up') || msg.includes('create account')) {
            return "To register, go to the Student Portal and click 'Get Started' or 'Register'. You'll need your student number, email, and personal information. After registration, you'll receive your unique Carelink QR code!";
        }
        
        // QR Code questions
        if (msg.includes('qr') || msg.includes('qrcode') || msg.includes('qr code')) {
            return "Your Carelink QR code is available on your student dashboard after registration. You can download, print, or save it to your phone. Use it to check in at the kiosk for quick access to clinic services.";
        }
        
        // Appointment questions
        if (msg.includes('appointment') || msg.includes('book') || msg.includes('schedule')) {
            return "You can book appointments through the Student Portal after logging in. Go to 'Appointments' tab, select your preferred date and time, and submit your request. Clinic staff will confirm your appointment.";
        }
        
        // Clinic hours
        if (msg.includes('hours') || msg.includes('open') || msg.includes('clinic hours') || msg.includes('schedule')) {
            return "Clinic Hours:\n- Monday to Friday: 7:30 AM - 5:00 PM\n- Saturday: 8:00 AM - 12:00 PM\n- Sunday: Closed\n\nHolidays follow the official PUP academic calendar.";
        }
        
        // Location
        if (msg.includes('location') || msg.includes('where') || msg.includes('address')) {
            return "PUP Binan Campus is located at Brgy. Zapote, Binan City, Laguna. The clinic is on the ground floor of the main building.";
        }
        
        // Kiosk
        if (msg.includes('kiosk') || msg.includes('check in') || msg.includes('check-in')) {
            return "The self-service kiosk lets you check in using your QR code or student number. Answer the health assessment questions and you'll receive your queue number instantly.";
        }
        
        // Login issues
        if (msg.includes('login') || msg.includes('sign in') || msg.includes('forgot password')) {
            return "For login issues, make sure you're using the correct student number and password. If you forgot your password, contact the clinic administrator to reset it. Use the 'Sign In' button on the homepage.";
        }
        
        // Contact
        if (msg.includes('contact') || msg.includes('call') || msg.includes('email')) {
            return "You can reach us at:\n- Email: clinic.binan@pup.edu.ph\n- Phone: (049) 123-4567\n- Or use the contact form on this page!";
        }
        
        // Hello / Greeting
        if (msg.includes('hello') || msg.includes('hi') || msg.includes('hey') || msg.includes('good morning') || msg.includes('good afternoon')) {
            return "Hello! Welcome to PUPBC Carelink. How can I assist you today?";
        }
        
        // Thank you
        if (msg.includes('thank') || msg.includes('thanks')) {
            return "You're welcome! Is there anything else I can help you with?";
        }
        
        // Help
        if (msg.includes('help') || msg.includes('what can you do')) {
            return "I can help you with:\n- Registration process\n- QR code information\n- Booking appointments\n- Clinic hours and location\n- Kiosk usage\n- Login assistance\n- Contact information\n\nJust type your question!";
        }
        
        // Default response
        return "I'm not sure about that. Please contact the clinic directly for more specific questions. You can call (049) 123-4567 or email clinic.binan@pup.edu.ph";
    }
    
    // Add typing indicator
    function showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'typing-indicator';
        typingDiv.id = 'typingIndicator';
        typingDiv.innerHTML = '<span></span><span></span><span></span>';
        chatMessages.appendChild(typingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    function removeTypingIndicator() {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) {
            indicator.remove();
        }
    }
    
    // Add message to chat
    function addMessage(text, isUser) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isUser ? 'message-user' : 'message-bot'}`;
        messageDiv.textContent = text;
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Send message function
    function sendMessage() {
        const userMessage = chatInput.value.trim();
        if (userMessage === '' || isBotTyping) return;

        isBotTyping = true;
        sendChatBtn.disabled = true;

        addMessage(userMessage, true);
        chatInput.value = '';
        showTypingIndicator();

        setTimeout(() => {
            removeTypingIndicator();
            const response = getAIResponse(userMessage);
            addMessage(response, false);
            isBotTyping = false;
            sendChatBtn.disabled = false;
            chatInput.focus();
        }, 500 + Math.random() * 400);
    }
    
    // Prevent event bubbling on chatbot window
    chatbotWindow.addEventListener('click', (e) => {
        e.stopPropagation();
    });
    
    // Close chatbot when clicking outside (optional)
    document.addEventListener('click', (e) => {
        if (!chatbotWindow.contains(e.target) && !chatbotBtn.contains(e.target)) {
            chatbotWindow.classList.remove('open');
        }
    });
    
    // Contact form inline validation (replaces intrusive alert() calls)
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            const nameEl    = this.querySelector('#contact_name');
            const emailEl   = this.querySelector('#contact_email');
            const messageEl = this.querySelector('#contact_message');
            let valid = true;

            // Clear previous inline errors
            this.querySelectorAll('.inline-error').forEach(el => el.remove());
            [nameEl, emailEl, messageEl].forEach(el => el.style.borderColor = '');

            function showError(el, msg) {
                el.style.borderColor = '#ef4444';
                const err = document.createElement('p');
                err.className = 'inline-error';
                err.style.cssText = 'color:#ef4444;font-size:12px;margin-top:4px;';
                err.textContent = msg;
                el.parentNode.appendChild(err);
                valid = false;
            }

            if (nameEl.value.trim().length < 2)    showError(nameEl, 'Please enter your full name.');
            const emailVal = emailEl.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailVal))         showError(emailEl, 'Please enter a valid email address.');
            if (messageEl.value.trim().length < 10) showError(messageEl, 'Message must be at least 10 characters.');

            if (!valid) { e.preventDefault(); return; }

            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        });
    }
</script>

</body>
</html>