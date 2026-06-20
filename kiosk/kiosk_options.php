<?php
session_start();
// Clear any previous patient data when returning to options
unset($_SESSION['patient_info']);
unset($_SESSION['error_message']);

// Check for errors from verification
$error_message = '';
if (isset($_GET['error'])) {
    switch($_GET['error']) {
        case 'empty': $error_message = 'Please enter your student number.'; break;
        case 'invalid': $error_message = 'Invalid student number format. Use: YYYY-XXXXX-BN-0'; break;
        case 'notfound': $error_message = 'Student not found. Please register first at the student portal.'; break;
        case 'active': $error_message = 'You already have an active visit today. Please proceed to the waiting area.'; break;
        case 'camera': $error_message = 'Camera access denied. Please use manual entry.'; break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Choose Option - PUPBC Carelink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --maroon: #800020; --maroon-dark: #4a0010; --gold: #c9a84c; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-dark) 40%, var(--maroon) 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('../assets/images/pupbg.jpg');
            background-size: cover;
            background-position: center;
            opacity: 0.1;
            pointer-events: none;
            z-index: 0;
        }
        
        .kiosk-container {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 24px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.02); } }
        
        .animate-fade-in { animation: fadeIn 0.5s ease-out forwards; }
        
        .option-btn {
            transition: all 0.3s ease;
            cursor: pointer;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .option-btn:hover { background: rgba(255,255,255,0.15); border-color: var(--gold); transform: translateY(-4px); }
        .option-btn:active { transform: translateY(0px); }
        
        .datetime-container { font-family: 'Courier New', monospace; letter-spacing: 1px; }
        
        .error-alert {
            animation: pulse 0.5s ease-out;
        }
        
        @media (orientation: landscape) and (min-width: 768px) {
            .options-wrapper { display: flex !important; flex-direction: row !important; gap: 24px !important; width: 100% !important; }
            .option-btn { flex: 1 !important; }
            .option-content { flex-direction: column !important; text-align: center !important; gap: 16px !important; }
            .option-arrow { display: none !important; }
        }
        
        @media (orientation: portrait) {
            .options-wrapper { display: flex !important; flex-direction: column !important; gap: 20px !important; }
        }
    </style>
</head>
<body>

<div class="kiosk-container">
    <!-- Top Bar -->
    <div class="flex items-center justify-between mb-8 animate-fade-in">
        <a href="kiosk_landing.php" class="flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10 border border-white/20 text-white/80 hover:text-white hover:bg-white/20 transition">
            <i class="fas fa-arrow-left text-lg"></i><span class="hidden sm:inline">Back</span>
        </a>
        <div class="datetime-container text-right">
            <div id="currentTime" class="text-2xl md:text-3xl font-bold text-white tracking-wider">--:-- --</div>
            <div id="currentDate" class="text-sm md:text-base text-white/60">Loading...</div>
        </div>
    </div>
    
    <!-- Error Message -->
    <?php if ($error_message): ?>
    <div class="bg-red-500/20 border border-red-500/50 rounded-xl p-4 mb-6 text-white text-center error-alert animate-fade-in">
        <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>
    
    <!-- Header -->
    <div class="text-center mb-10 animate-fade-in">
        <div class="flex items-center justify-center gap-3 mb-4">
            <div class="w-16 h-16 rounded-2xl bg-white/10 border-2 border-white/30 flex items-center justify-center">
                <i class="fas fa-clinic-medical text-white text-3xl"></i>
            </div>
            <span class="font-bold text-3xl md:text-4xl text-white">PUPBC <span class="text-[#c9a84c]">CareLink</span></span>
        </div>
        <h2 class="font-bold text-3xl md:text-4xl text-white mb-3">How would you like to check in?</h2>
        <p class="text-white/70 text-base md:text-lg">Select an option below to begin your triage process</p>
    </div>
    
    <!-- Options -->
    <div class="options-wrapper w-full mx-auto mb-10">
        <!-- Scan QR Code -->
        <div class="option-btn rounded-2xl p-6 md:p-8 cursor-pointer" onclick="window.location.href='kiosk_scan_qr.php'">
            <div class="option-content flex items-center gap-5 md:gap-6">
                <div class="w-16 h-16 md:w-20 md:h-20 rounded-2xl bg-white/10 border border-white/30 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-qrcode text-3xl md:text-5xl text-white"></i>
                </div>
                <div class="option-text flex-1">
                    <h3 class="font-bold text-white text-xl md:text-2xl lg:text-3xl">Scan QR Code</h3>
                    <p class="text-white/60 text-sm md:text-base lg:text-lg mt-1">Use your CareLink QR code to check in instantly</p>
                </div>
                <i class="option-arrow fas fa-chevron-right text-white/30 text-xl md:text-2xl"></i>
            </div>
        </div>
        
        <!-- Manual Entry -->
        <div class="option-btn rounded-2xl p-6 md:p-8 cursor-pointer" onclick="window.location.href='kiosk_manual.php'">
            <div class="option-content flex items-center gap-5 md:gap-6">
                <div class="w-16 h-16 md:w-20 md:h-20 rounded-2xl bg-white/10 border border-white/30 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-keyboard text-3xl md:text-5xl text-white"></i>
                </div>
                <div class="option-text flex-1">
                    <h3 class="font-bold text-white text-xl md:text-2xl lg:text-3xl">Enter ID Manually</h3>
                    <p class="text-white/60 text-sm md:text-base lg:text-lg mt-1">Forgot your phone? Type your student number</p>
                </div>
                <i class="option-arrow fas fa-chevron-right text-white/30 text-xl md:text-2xl"></i>
            </div>
        </div>
        
        <!-- New Registration -->
        <div class="option-btn rounded-2xl p-6 md:p-8 cursor-pointer" onclick="window.location.href='../pages/student/student_register.php'">
            <div class="option-content flex items-center gap-5 md:gap-6">
                <div class="w-16 h-16 md:w-20 md:h-20 rounded-2xl bg-white/10 border border-white/30 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user-plus text-3xl md:text-5xl text-white"></i>
                </div>
                <div class="option-text flex-1">
                    <h3 class="font-bold text-white text-xl md:text-2xl lg:text-3xl">New Registration</h3>
                    <p class="text-white/60 text-sm md:text-base lg:text-lg mt-1">First time? Register your account here</p>
                </div>
                <i class="option-arrow fas fa-chevron-right text-white/30 text-xl md:text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="text-center py-4 text-xs text-white/20 border-t border-white/10 mt-auto">
        PUPBC CareLink · Self-Service Triage Kiosk · v1.0
    </div>
</div>

<script>
    function updateDateTime() {
        const now = new Date();
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        document.getElementById('currentTime').textContent = `${hours.toString().padStart(2, '0')}:${minutes} ${ampm}`;
        document.getElementById('currentDate').textContent = `${['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][now.getDay()]}, ${['January','February','March','April','May','June','July','August','September','October','November','December'][now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);
</script>
</body>
</html>