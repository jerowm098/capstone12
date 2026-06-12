    <?php
    session_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <title>Scan QR Code - PUPBC Carelink</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            :root { --maroon: #800020; --gold: #c9a84c; }
            body { background: linear-gradient(135deg, var(--maroon) 0%, #4a0010 100%); min-height: 100vh; }
            
            .scanner-wrapper { position: relative; width: 100%; max-width: 450px; margin: 0 auto; border-radius: 1.5rem; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
            #reader { width: 100%; background: #000; min-height: 400px; display: block; }
            #reader video { width: 100%; height: auto; display: block; }
            
            .scan-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none; z-index: 10; }
            .scan-frame { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 260px; height: 260px; border: 3px solid var(--gold); border-radius: 28px; box-shadow: 0 0 0 9999px rgba(0,0,0,0.5); }
            .scan-frame-corner { position: absolute; width: 40px; height: 40px; border: 3px solid var(--gold); }
            .corner-tl { top: -3px; left: -3px; border-right: none; border-bottom: none; border-radius: 28px 0 0 0; }
            .corner-tr { top: -3px; right: -3px; border-left: none; border-bottom: none; border-radius: 0 28px 0 0; }
            .corner-bl { bottom: -3px; left: -3px; border-right: none; border-top: none; border-radius: 0 0 0 28px; }
            .corner-br { bottom: -3px; right: -3px; border-left: none; border-top: none; border-radius: 0 0 28px 0; }
            
            .scan-line-animation { position: absolute; width: calc(100% - 20px); height: 3px; background: linear-gradient(90deg, transparent, var(--gold), transparent); left: 10px; animation: scanMove 2s ease-in-out infinite; }
            @keyframes scanMove { 0% { top: 20px; opacity: 0.5; } 50% { top: calc(100% - 50px); opacity: 1; } 100% { top: 20px; opacity: 0.5; } }
            
            .status-dot { width: 10px; height: 10px; border-radius: 50%; background: #ef4444; transition: all 0.3s; }
            .status-dot.active { background: #22c55e; animation: pulse 1s infinite; }
            .status-dot.scanning { background: var(--gold); animation: pulse 0.5s infinite; }
            @keyframes pulse { 0%,100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.2); } }
            
            .toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(100px); background: rgba(0,0,0,0.9); color: white; padding: 12px 24px; border-radius: 9999px; font-size: 14px; transition: transform 0.3s; z-index: 200; backdrop-filter: blur(8px); }
            .toast.show { transform: translateX(-50%) translateY(0); }
            .toast.error { background: rgba(220,38,38,0.95); }
            .toast.success { background: rgba(34,197,94,0.95); }
            
            .loading { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 100; opacity: 0; visibility: hidden; transition: all 0.3s; backdrop-filter: blur(4px); }
            .loading.show { opacity: 1; visibility: visible; }
            .spinner { width: 50px; height: 50px; border: 4px solid rgba(201,168,76,0.3); border-top-color: var(--gold); border-radius: 50%; animation: spin 0.8s linear infinite; }
            @keyframes spin { to { transform: rotate(360deg); } }
            
            .btn-manual { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 9999px; padding: 12px 28px; color: white; font-weight: 500; transition: all 0.2s; cursor: pointer; }
            .btn-manual:hover { background: rgba(255,255,255,0.2); border-color: var(--gold); }
            
            .helper-text { font-size: 13px; color: rgba(255,255,255,0.6); }
        </style>
    </head>
    <body>

    <div id="loading" class="loading"><div class="spinner"></div><div class="ml-3 text-white font-medium">Verifying...</div></div>
    <div id="toast" class="toast"><i class="fas fa-info-circle mr-2"></i> <span id="toastMsg"></span></div>

    <div class="container mx-auto px-4 py-4 min-h-screen flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <a href="kiosk_options.php" class="flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10 border border-white/20 text-white/80 hover:text-white hover:bg-white/20 transition">
                <i class="fas fa-arrow-left text-lg"></i><span class="hidden sm:inline">Back</span>
            </a>
            <div class="text-center">
                <h1 class="font-bold text-xl text-white">Scan QR Code</h1>
                <p class="text-white/50 text-xs mt-0.5">Position QR code inside the frame</p>
            </div>
            <div class="w-16"></div>
        </div>
        
        <div class="flex-1 flex flex-col items-center justify-center py-4">
            <div class="scanner-wrapper">
                <div id="reader"></div>
                <div class="scan-overlay">
                    <div class="scan-frame">
                        <div class="scan-frame-corner corner-tl"></div>
                        <div class="scan-frame-corner corner-tr"></div>
                        <div class="scan-frame-corner corner-bl"></div>
                        <div class="scan-frame-corner corner-br"></div>
                        <div class="scan-line-animation"></div>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-3 mt-5 bg-black/30 backdrop-blur-md rounded-full px-5 py-2.5">
                <div id="statusDot" class="status-dot"></div>
                <span id="statusText" class="text-white text-sm font-medium">Initializing camera...</span>
            </div>
            
            <p class="helper-text text-center mt-4 max-w-xs">
                <i class="fas fa-lightbulb mr-1 text-yellow-400"></i> 
                Make sure the QR code is well-lit and centered
            </p>
            
            <div class="mt-6 flex items-center gap-4">
                <div class="h-px w-12 bg-white/20"></div>
                <span class="text-white/40 text-xs">OR</span>
                <div class="h-px w-12 bg-white/20"></div>
            </div>
            
            <button onclick="window.location.href='kiosk_manual.php'" class="btn-manual mt-3">
                <i class="fas fa-keyboard mr-2"></i> Enter Student Number Manually
            </button>
        </div>
        
        <div class="text-center py-4 text-white/20 text-xs mt-4">PUPBC CareLink · Self-Service Kiosk</div>
    </div>

    <!-- Hidden form for redirect -->
    <form id="redirectForm" method="POST" action="kiosk_verify.php" style="display: none;">
        <input type="hidden" name="student_number" id="qr_student_number">
    </form>

    <script>
        let html5QrCode = null;
        let isScanning = false;
        let scanTimeout = null;
        
        function showToast(msg, type = 'info') {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').innerHTML = msg;
            toast.className = `toast ${type}`;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
        
        function showLoading(show) {
            document.getElementById('loading').classList.toggle('show', show);
        }
        
        function updateStatus(text, isActive = false, isScanningStatus = false) {
            document.getElementById('statusText').innerHTML = text;
            const dot = document.getElementById('statusDot');
            if (isActive) dot.className = isScanningStatus ? 'status-dot scanning' : 'status-dot active';
            else dot.className = 'status-dot';
        }
        
        function playBeep() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                oscillator.frequency.value = 880;
                gainNode.gain.value = 0.15;
                oscillator.start();
                gainNode.gain.exponentialRampToValueAtTime(0.00001, audioContext.currentTime + 0.2);
                oscillator.stop(audioContext.currentTime + 0.2);
                audioContext.resume();
            } catch(e) { console.log('Beep not supported'); }
        }
        
        async function startScanner() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                updateStatus('Camera not supported', false);
                showToast('Camera not supported. Use manual entry.', 'error');
                return;
            }
            
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
                stream.getTracks().forEach(track => track.stop());
            } catch (err) {
                if (err.name === 'NotAllowedError') {
                    updateStatus('Camera permission denied', false);
                    showToast('Camera access denied. Please allow camera access.', 'error');
                } else {
                    updateStatus('Camera unavailable', false);
                    showToast('Unable to access camera. Use manual entry.', 'error');
                }
                return;
            }
            
            html5QrCode = new Html5Qrcode("reader");
            try {
                await html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    onScanSuccess,
                    onScanError
                );
                isScanning = true;
                updateStatus('Camera ready. Center QR code in frame', true, true);
                showToast('Camera ready! Position QR code inside the frame', 'success');
            } catch (err) {
                updateStatus('Camera error. Use manual entry.', false);
                showToast('Unable to start camera. Use manual entry.', 'error');
            }
        }
        
        function onScanSuccess(decodedText) {
            if (!isScanning || scanTimeout) return;
            
            scanTimeout = setTimeout(() => { scanTimeout = null; }, 2000);
            
            playBeep();
            updateStatus('QR detected! Redirecting...', true, true);
            showLoading(true);
            stopScanner();
            
            let studentNumber = decodedText;
            if (decodedText.startsWith('pupbc:carelink:')) {
                const parts = decodedText.split(':');
                if (parts.length >= 4) studentNumber = parts[3];
            }
            
            document.getElementById('qr_student_number').value = studentNumber;
            document.getElementById('redirectForm').submit();
        }
        
        function onScanError(errorMessage) {
            console.log('Scan error:', errorMessage);
        }
        
        function stopScanner() {
            if (html5QrCode && isScanning) {
                html5QrCode.stop().then(() => { isScanning = false; }).catch(e => {});
            }
        }
        
        window.addEventListener('load', () => setTimeout(startScanner, 500));
        window.addEventListener('beforeunload', () => stopScanner());
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !isScanning && html5QrCode) startScanner();
        });
    </script>
    </body>
    </html>