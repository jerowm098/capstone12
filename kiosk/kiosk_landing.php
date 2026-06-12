<?php
session_start();
// Clear any previous session data
session_destroy();
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>PUPBC Carelink | Self-Service Triage Kiosk</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --maroon: #800020; --maroon-dark: #4a0010; --gold: #c9a84c; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-dark) 40%, var(--maroon) 100%);
            min-height: 100vh;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
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
        
        .kiosk-container { position: relative; z-index: 1; max-width: 500px; margin: 0 auto; padding: 24px; min-height: 100vh; display: flex; flex-direction: column; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.9; transform: scale(0.98); } }
        
        .animate-fade-in { animation: fadeIn 0.5s ease-out forwards; }
        .animate-pulse { animation: pulse 1.5s ease-in-out infinite; }
        
        .btn-kiosk { background: var(--maroon); color: white; transition: all 0.3s ease; }
        .btn-kiosk:hover { background: var(--maroon-dark); transform: scale(1.02); }
        .btn-kiosk:active { transform: scale(0.98); }
        
        .modal-overlay { background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); }
        .modal-content { animation: fadeIn 0.3s ease-out; }
        
        .checkbox-custom { width: 20px; height: 20px; border: 2px solid #d1d5db; border-radius: 6px; transition: all 0.2s; cursor: pointer; }
        .checkbox-custom.checked { background: var(--maroon); border-color: var(--maroon); }
        .checkbox-custom.checked i { display: block !important; }
        
        .terms-text { scrollbar-width: thin; }
        .terms-text::-webkit-scrollbar { width: 5px; }
        .terms-text::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .terms-text::-webkit-scrollbar-thumb { background: var(--maroon); border-radius: 10px; }
    </style>
</head>
<body>

<div class="kiosk-container">
    <div class="flex-1 flex flex-col items-center justify-center text-center">
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full bg-[#800020]/8 blur-3xl"></div>
        </div>
        
        <!-- Logo -->
        <div class="relative mb-8 animate-fade-in">
            <div class="w-32 h-32 rounded-3xl bg-white/10 border-2 border-white/30 flex items-center justify-center shadow-[0_0_60px_rgba(128,0,32,0.35)]">
                <i class="fas fa-clinic-medical text-6xl text-white"></i>
            </div>
            <div class="absolute -bottom-3 -right-3 w-10 h-10 rounded-xl bg-[#c9a84c]/20 border border-[#c9a84c]/50 flex items-center justify-center">
                <i class="fas fa-qrcode text-[#c9a84c] text-lg"></i>
            </div>
        </div>
        
        <!-- University name -->
        <div class="text-white/80 font-semibold text-sm tracking-[0.18em] uppercase mb-4 max-w-xs leading-relaxed animate-fade-in">
            Polytechnic University of the Philippines – Biñan Campus
        </div>
        
        <!-- App name -->
        <div class="mb-2 animate-fade-in">
            <h1 class="font-bold text-5xl md:text-6xl text-white leading-none">
                PUPBC <span class="text-[#c9a84c]">CareLink</span>
            </h1>
        </div>
        
        <!-- Subtitle -->
        <div class="text-white/70 text-xl mb-8 animate-fade-in">
            QR Integrated Health Information System
        </div>
        
        <!-- Badge -->
        <div class="inline-block px-5 py-2 rounded-full border border-[#c9a84c]/40 bg-[#c9a84c]/10 text-[#c9a84c] font-semibold tracking-widest text-sm uppercase mb-8 animate-fade-in">
            Self-Service Triage Kiosk
        </div>
        
        <!-- Get Started Button -->
        <button id="getStartedBtn" class="btn-kiosk group flex items-center gap-3 font-bold text-xl px-10 py-5 rounded-2xl shadow-[0_8px_32px_rgba(128,0,32,0.45)] animate-pulse">
            <i class="fas fa-hand-pointer text-2xl"></i>
            <span>Click to Get Started</span>
            <i class="fas fa-chevron-right transition-transform group-hover:translate-x-1"></i>
        </button>
    </div>
    
    <div class="text-center py-4 text-xs text-white/20 border-t border-white/10 mt-auto">
        PUPBC CareLink · Self-Service Triage Kiosk · v1.0
    </div>
</div>

<!-- Terms & Conditions Modal -->
<div id="termsModal" class="fixed inset-0 z-50 hidden items-center justify-center modal-overlay px-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[85vh] flex flex-col shadow-2xl modal-content">
        <div class="flex items-center justify-between px-6 py-5 border-b">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-[#800020]/10 border border-[#800020]/40 flex items-center justify-center">
                    <i class="fas fa-shield-alt text-[#800020] text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 text-lg">Terms and Conditions</h3>
                    <p class="text-gray-500 text-xs">Data Privacy Act of 2012 (RA 10173)</p>
                </div>
            </div>
            <button id="closeModalBtn" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5 text-sm text-gray-600 leading-relaxed terms-text">
            <div>
                <h4 class="text-gray-900 font-semibold mb-2">📋 Purpose of Data Collection</h4>
                <p>The Polytechnic University of the Philippines – Biñan Campus (PUPBC) collects your personal and health information solely for the purpose of facilitating your health consultation at the PUPBC Clinic through the CareLink Self-Service Triage Kiosk.</p>
            </div>
            <div>
                <h4 class="text-gray-900 font-semibold mb-2">📝 Information We Collect</h4>
                <ul class="space-y-1 list-disc list-inside">
                    <li>Full name, student number, course and year level</li>
                    <li>Contact number and email address</li>
                    <li>Chief complaint, symptoms, and vital signs</li>
                    <li>Health conditions and allergies</li>
                </ul>
            </div>
            <div>
                <h4 class="text-gray-900 font-semibold mb-2">🔒 How We Use Your Data</h4>
                <p>Your data will be used exclusively by the PUPBC Health Services Unit to prioritize and process your triage. It will not be shared with third parties without your explicit consent, except as required by law.</p>
            </div>
            <div>
                <h4 class="text-gray-900 font-semibold mb-2">⏱️ Data Retention</h4>
                <p>Health records are retained for a period necessary to fulfill the stated purpose and in accordance with the university's records management policy.</p>
            </div>
            <div>
                <h4 class="text-gray-900 font-semibold mb-2">⚖️ Your Rights</h4>
                <p>Under RA 10173, you have the right to access, correct, and request deletion of your personal data. For concerns, contact the PUPBC Data Protection Officer at the clinic office.</p>
            </div>
            <div>
                <h4 class="text-gray-900 font-semibold mb-2">📌 Terms of Use</h4>
                <p>By using this kiosk, you agree that the information you provide is accurate and truthful. This kiosk is for non-emergency use only. In case of a medical emergency, please alert clinic staff immediately.</p>
            </div>
        </div>
        
        <div class="px-6 py-5 border-t space-y-4">
            <button id="agreeCheckBtn" class="flex items-start gap-3 w-full text-left group">
                <div id="agreeCheckbox" class="checkbox-custom flex items-center justify-center mt-0.5">
                    <i class="fas fa-check text-white text-xs hidden"></i>
                </div>
                <span class="text-sm text-gray-600 group-hover:text-gray-900 transition-colors">
                    I have read and understood the Data Privacy Notice and Terms of Use. I consent to the collection and processing of my personal and health information for the purposes stated above.
                </span>
            </button>
            <button id="acceptBtn" disabled class="w-full py-4 rounded-xl font-bold text-lg transition-all bg-gray-200 text-gray-400 cursor-not-allowed">
                I Agree and Continue
            </button>
        </div>
    </div>
</div>

<script>
    const getStartedBtn = document.getElementById('getStartedBtn');
    const termsModal = document.getElementById('termsModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const agreeCheckBtn = document.getElementById('agreeCheckBtn');
    const agreeCheckbox = document.getElementById('agreeCheckbox');
    const acceptBtn = document.getElementById('acceptBtn');
    let agreed = false;
    
    getStartedBtn.addEventListener('click', () => {
        termsModal.classList.remove('hidden');
        termsModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    });
    
    function closeModal() {
        termsModal.classList.add('hidden');
        termsModal.classList.remove('flex');
        document.body.style.overflow = '';
        agreed = false;
        agreeCheckbox.classList.remove('checked');
        acceptBtn.disabled = true;
        acceptBtn.classList.remove('bg-[#800020]', 'text-white');
        acceptBtn.classList.add('bg-gray-200', 'text-gray-400');
    }
    
    closeModalBtn.addEventListener('click', closeModal);
    termsModal.addEventListener('click', (e) => { if (e.target === termsModal) closeModal(); });
    
    agreeCheckBtn.addEventListener('click', () => {
        agreed = !agreed;
        if (agreed) {
            agreeCheckbox.classList.add('checked');
            acceptBtn.disabled = false;
            acceptBtn.classList.remove('bg-gray-200', 'text-gray-400');
            acceptBtn.classList.add('bg-[#800020]', 'text-white', 'cursor-pointer');
        } else {
            agreeCheckbox.classList.remove('checked');
            acceptBtn.disabled = true;
            acceptBtn.classList.remove('bg-[#800020]', 'text-white');
            acceptBtn.classList.add('bg-gray-200', 'text-gray-400');
        }
    });
    
    acceptBtn.addEventListener('click', () => {
        if (agreed) window.location.href = 'kiosk_options.php';
    });
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && termsModal.classList.contains('flex')) closeModal();
    });
</script>
</body>
</html>