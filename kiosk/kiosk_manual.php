\<?php
session_start();
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Manual Entry - PUPBC Carelink Kiosk</title>
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
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            padding: 24px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.5s ease-out forwards; }
        
        .keypad-btn {
            background: white;
            border: 2px solid rgba(128, 0, 32, 0.15);
            border-radius: 16px;
            padding: 20px;
            font-size: 28px;
            font-weight: 700;
            transition: all 0.15s ease;
            width: 100%;
            color: var(--maroon);
            cursor: pointer;
        }
        .keypad-btn:active {
            transform: scale(0.94);
            background: var(--gold);
            color: var(--maroon-dark);
        }
        
        .student-input {
            width: 100%;
            border-radius: 20px;
            border: 2px solid #e2e8f0;
            padding: 16px 20px;
            text-align: center;
            font-family: 'Courier New', monospace;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 2px;
            outline: none;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        .student-input:focus {
            border-color: var(--maroon);
            box-shadow: 0 0 0 4px rgba(128, 0, 32, 0.1);
            background: white;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            color: white;
            border: none;
            border-radius: 50px;
            padding: 16px;
            font-size: 1.1rem;
            font-weight: 700;
            width: 100%;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #a30029, var(--maroon));
            transform: translateY(-2px);
        }
        
        .error-alert {
            background: rgba(220, 38, 38, 0.9);
            border-radius: 16px;
            padding: 12px 20px;
            margin-bottom: 20px;
            color: white;
            text-align: center;
            animation: fadeIn 0.3s ease-out;
        }
        
        .back-btn {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50px;
            padding: 10px 20px;
            color: white;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateX(-3px);
            color: white;
        }
        
        .datetime-container { font-family: 'Courier New', monospace; letter-spacing: 1px; }
        
        @media (max-width: 640px) {
            .keypad-btn { padding: 14px; font-size: 22px; }
            .student-input { font-size: 18px; padding: 12px 16px; }
            .back-btn span { display: none; }
            .back-btn { padding: 10px 14px; }
        }
    </style>
</head>
<body>

<div class="kiosk-container">
    
    <!-- TOP BAR: Back sa LEFT, Oras at Petsa sa RIGHT -->
    <div class="flex items-center justify-between mb-8 animate-fade-in">
        <!-- LEFT CORNER: Back Button -->
        <a href="kiosk_options.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            <span>Back</span>
        </a>
        
        <!-- RIGHT CORNER: Date and Time -->
        <div class="datetime-container text-right">
            <div id="currentTime" class="text-2xl font-bold text-white tracking-wider">--:-- --</div>
            <div id="currentDate" class="text-sm text-white/60">Loading...</div>
        </div>
    </div>
    
    <!-- Error Message -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error-alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Header -->
    <div class="text-center mb-6 animate-fade-in">
        <h2 class="font-bold text-2xl text-white mb-1">Manual Entry</h2>
        <p class="text-white/60 text-sm">Enter your Student ID number</p>
    </div>
    
    <!-- Main Card -->
    <div class="flex-1 flex flex-col items-center justify-center">
        <div class="bg-white/95 backdrop-blur rounded-2xl w-full p-6 shadow-2xl">
            <form action="kiosk_verify.php" method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-bold uppercase tracking-wider mb-2" style="color: var(--maroon)">
                        <i class="fas fa-id-card me-2"></i>Student Number
                    </label>
                    <input type="text" 
                           name="student_number" 
                           id="student_number" 
                           required 
                           class="student-input"
                           placeholder="2023-00482-BN-0"
                           maxlength="20"
                           autofocus>
                    <p class="text-xs text-gray-500 text-center mt-2">
                        <i class="fas fa-info-circle me-1"></i>Format: YYYY-XXXXX-BN-0
                    </p>
                </div>
                
                <!-- Virtual Keypad -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-2 text-center">
                        <i class="fas fa-keyboard me-1"></i>Virtual Keypad
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        <?php
                        $keys = ['1','2','3','4','5','6','7','8','9','-','0','back'];
                        foreach ($keys as $key):
                        ?>
                        <button type="button" class="keypad-btn" data-key="<?php echo $key; ?>">
                            <?php echo $key === 'back' ? '<i class="fas fa-backspace"></i>' : $key; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-check-circle me-2"></i> Verify & Continue
                    <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </form>
        </div>
        
        <!-- Helper Text -->
        <p class="text-white/50 text-xs text-center mt-4">
            <i class="fas fa-shield-alt me-1"></i> Your information is secure and encrypted
        </p>
    </div>
    
    <!-- Footer -->
    <div class="text-center py-4 text-white/20 text-xs mt-auto">
        PUPBC CareLink · Self-Service Kiosk · v1.0
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
    
    const input = document.getElementById('student_number');
    
    // Auto-format student number
    input.addEventListener('input', function() {
        let numbers = this.value.replace(/[^0-9]/g, '');
        if (numbers.length > 9) numbers = numbers.substring(0, 9);
        let formatted = '';
        if (numbers.length >= 1) formatted += numbers.substring(0, Math.min(4, numbers.length));
        if (numbers.length >= 5) formatted += '-' + numbers.substring(4, Math.min(9, numbers.length));
        if (numbers.length >= 9) formatted += '-BN-0';
        this.value = formatted;
    });
    
    // Virtual keypad
    document.querySelectorAll('.keypad-btn').forEach(btn => {
        btn.addEventListener('click', function() { });
    });
    
    input.focus();
</script>

</body>
</html>