<?php
// capstone1/pages/nurse/nurse_patientdetails.php
session_start();
if (!isset($_SESSION['nurse_id'])) {
    header('Location: nurse_login.php');
    exit();
}
require_once '../../config/db_connect.php';

$nurse_id = $_SESSION['nurse_id'];
$message = '';
$message_type = '';

// Get nurse info
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, n.position FROM users u JOIN nurses n ON u.id = n.user_id WHERE n.id = ?");
mysqli_stmt_bind_param($stmt, "i", $nurse_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$nurse = mysqli_fetch_assoc($result);
$nurse_name = ($nurse['first_name'] ?? 'Nurse') . ' ' . ($nurse['last_name'] ?? '');
$nurse_position = $nurse['position'] ?? 'Staff Nurse';
$initials = strtoupper(substr($nurse['first_name'] ?? 'N', 0, 1) . substr($nurse['last_name'] ?? '', 0, 1));

$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// =====================================================
// HANDLE FILE UPLOAD
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['patient_file']) && isset($_POST['upload_document'])) {
    $student_id = intval($_POST['student_id']);
    $document_type = $_POST['document_type'] ?? 'other';
    $description = trim($_POST['description'] ?? '');
    
    $target_dir = "../../uploads/patient_documents/";
    
    $subdir = '';
    switch($document_type) {
        case 'xray': $subdir = 'xrays/'; break;
        case 'prescription': $subdir = 'prescriptions/'; break;
        case 'medcert': $subdir = 'medcerts/'; break;
        case 'lab_result': $subdir = 'lab_results/'; break;
        default: $subdir = 'others/';
    }
    
    $full_dir = $target_dir . $subdir;
    
    if (!file_exists($full_dir)) {
        mkdir($full_dir, 0777, true);
    }
    
    $file = $_FILES['patient_file'];
    $errors = [];
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'image/gif'];
    $max_size = 5 * 1024 * 1024;
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload failed.";
    }
    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = "Invalid file type. Allowed: JPG, PNG, PDF, GIF";
    }
    if ($file['size'] > $max_size) {
        $errors[] = "File too large. Max 5MB only.";
    }
    
    if (empty($errors)) {
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = time() . '_' . uniqid() . '.' . $file_extension;
        $file_path = $subdir . $unique_filename;
        $full_path = $full_dir . $unique_filename;
        
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            $insert_stmt = mysqli_prepare($conn, 
                "INSERT INTO patient_documents (student_id, file_name, original_name, file_path, file_type, file_size, document_type, description, uploaded_by, uploaded_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($insert_stmt, "issssisis", 
                $student_id, $unique_filename, $file['name'], $file_path, $file['type'], $file['size'], $document_type, $description, $nurse_id);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $message = "Document uploaded successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to save to database.";
                $message_type = "error";
                unlink($full_path);
            }
            mysqli_stmt_close($insert_stmt);
        } else {
            $message = "Failed to move uploaded file.";
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

// =====================================================
// HANDLE DELETE DOCUMENT
// =====================================================
if (isset($_GET['delete_doc']) && is_numeric($_GET['delete_doc'])) {
    $doc_id = intval($_GET['delete_doc']);
    
    $doc_stmt = mysqli_prepare($conn, "SELECT file_path FROM patient_documents WHERE id = ? AND student_id = ?");
    mysqli_stmt_bind_param($doc_stmt, "ii", $doc_id, $patient_id);
    mysqli_stmt_execute($doc_stmt);
    $doc_result = mysqli_stmt_get_result($doc_stmt);
    $doc = mysqli_fetch_assoc($doc_result);
    
    if ($doc) {
        $full_path = "../../uploads/patient_documents/" . $doc['file_path'];
        
        $delete_stmt = mysqli_prepare($conn, "DELETE FROM patient_documents WHERE id = ?");
        mysqli_stmt_bind_param($delete_stmt, "i", $doc_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            $message = "Document deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to delete document.";
            $message_type = "error";
        }
        mysqli_stmt_close($delete_stmt);
    }
    mysqli_stmt_close($doc_stmt);
}

// Get patient details
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, u.email, s.* FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
mysqli_stmt_bind_param($stmt, "i", $patient_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$patient = mysqli_fetch_assoc($result);

if (!$patient) {
    header('Location: nurse_patients.php');
    exit();
}

$allergies = !empty($patient['allergies']) ? explode(',', $patient['allergies']) : [];
$conditions = !empty($patient['medical_conditions']) ? explode(',', $patient['medical_conditions']) : [];

// Get visit timeline
$stmt = mysqli_prepare($conn, "SELECT v.*, u2.first_name as nurse_first, u2.last_name as nurse_last 
                      FROM visits v LEFT JOIN nurses n ON v.nurse_id = n.id 
                      LEFT JOIN users u2 ON n.user_id = u2.id 
                      WHERE v.student_id = ? ORDER BY v.visit_date DESC LIMIT 10");
mysqli_stmt_bind_param($stmt, "i", $patient_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$visits = [];
while ($row = mysqli_fetch_assoc($result)) $visits[] = $row;

// Get uploaded documents
$docs_stmt = mysqli_prepare($conn, "SELECT * FROM patient_documents WHERE student_id = ? ORDER BY uploaded_at DESC");
mysqli_stmt_bind_param($docs_stmt, "i", $patient_id);
mysqli_stmt_execute($docs_stmt);
$docs_result = mysqli_stmt_get_result($docs_stmt);
$documents = [];
while ($row = mysqli_fetch_assoc($docs_result)) $documents[] = $row;

$pinitials = strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1));
$current_page = basename($_SERVER['PHP_SELF']);

function getDocIcon($type) {
    switch($type) {
        case 'xray': return 'fa-x-ray';
        case 'prescription': return 'fa-prescription-bottle';
        case 'medcert': return 'fa-file-alt';
        case 'lab_result': return 'fa-flask';
        default: return 'fa-file';
    }
}

function getDocColor($type) {
    switch($type) {
        case 'xray': return 'bg-purple-100 text-purple-700';
        case 'prescription': return 'bg-green-100 text-green-700';
        case 'medcert': return 'bg-blue-100 text-blue-700';
        case 'lab_result': return 'bg-yellow-100 text-yellow-700';
        default: return 'bg-gray-100 text-gray-700';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - PUPBC Carelink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: { primary: { DEFAULT: '#800020' }, accent: { DEFAULT: '#c9a84c' } },
                backgroundImage: { 'gradient-primary': 'linear-gradient(135deg, #800020, #600018)' },
            }}
        }
    </script>
    <style>
        .animate-fade-in{animation:fadeIn 0.5s ease-out}
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        .transition-smooth{transition:all 0.3s ease}
        body{background-color:#f8fafc}
        .modal{transition:opacity 0.3s ease}
    </style>
</head>
<body class="font-sans antialiased text-gray-900">

<!-- Desktop Sidebar -->
<aside class="hidden md:block fixed top-0 left-0 h-full w-64 bg-[#800020] shadow-xl overflow-y-auto z-30">
    <div class="flex items-center gap-2 p-4 border-b border-[#600018]">
        <div class="rounded-lg bg-white/20 p-2"><i class="fas fa-heartbeat text-white text-lg"></i></div>
        <div><span class="font-bold text-white">PUPBC Carelink</span><p class="text-[10px] text-white/60">Health Information System</p></div>
    </div>
    
    <div class="flex items-center gap-3 p-4 border-b border-[#600018] bg-[#600018]">
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#c9a84c] text-[#800020] font-bold text-sm"><?php echo $initials; ?></div>
        <div><div class="text-sm font-semibold text-white"><?php echo htmlspecialchars($nurse_name); ?></div><div class="text-xs text-[#c9a84c]"><?php echo htmlspecialchars($nurse_position); ?></div></div>
    </div>
    
    <nav class="py-4">
        <div class="px-3 mb-2"><p class="text-[10px] font-semibold uppercase tracking-wider text-[#c9a84c] px-3">Main Menu</p></div>
        
        <a href="nurse_dashboard.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-home w-5"></i><span class="text-sm font-medium">Dashboard</span>
        </a>
        <a href="nurse_queue.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-users w-5"></i><span class="text-sm font-medium">Queue</span>
        </a>
        <a href="nurse_patients.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-user-injured w-5"></i><span class="text-sm font-medium">Patients</span>
        </a>
        <a href="nurse_appointments.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-calendar-alt w-5"></i><span class="text-sm font-medium">Appointments</span>
        </a>
        <a href="nurse_announcements.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-bullhorn w-5"></i><span class="text-sm font-medium">Announcements</span>
        </a>
        <a href="nurse_inventory.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-pills w-5"></i><span class="text-sm font-medium">Inventory</span>
        </a>
        <a href="nurse_settings.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all text-white hover:bg-[#600018]">
            <i class="fas fa-user-cog w-5"></i><span class="text-sm font-medium">Settings</span>
        </a>
        
        <div class="border-t border-[#600018] my-4 mx-3"></div>
        <a href="nurse_logout.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg text-white/70 hover:text-white hover:bg-[#600018] transition-all">
            <i class="fas fa-sign-out-alt w-5"></i><span class="text-sm font-medium">Sign Out</span>
        </a>
    </nav>
    
    <div class="p-4 border-t border-[#600018] mt-auto"><p class="text-[10px] text-white/40 text-center">© <?php echo date('Y'); ?> PUPBC Carelink</p></div>
</aside>

<!-- Main Content -->
<main class="md:pl-64 min-h-screen pb-20 md:pb-8">
    <div class="px-4 md:px-8 py-6 space-y-6 animate-fade-in">
        
        <a href="nurse_patients.php" class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-gray-900">
            <i class="fas fa-arrow-left"></i> Back to patients
        </a>
        
        <?php if ($message): ?>
            <div class="rounded-lg p-4 text-sm <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Patient Profile Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-card p-6">
            <div class="flex flex-col md:flex-row gap-6 items-start">
                <div class="flex h-24 w-24 items-center justify-center rounded-full bg-gradient-primary text-3xl font-bold text-white shadow-lg">
                    <?php echo $pinitials; ?>
                </div>
                <div class="flex-1">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Patient Profile</div>
                    <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($patient['student_number'] . ' · ' . $patient['course'] . ' · ' . $patient['year_level']); ?></div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="inline-flex rounded-full bg-yellow-50 px-2.5 py-0.5 text-xs font-medium text-yellow-700">
                            Blood: <?php echo htmlspecialchars($patient['blood_type'] ?? 'N/A'); ?>
                        </span>
                        <?php foreach ($allergies as $a): ?>
                            <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">
                                <i class="fas fa-allergies mr-1"></i> <?php echo htmlspecialchars(trim($a)); ?>
                            </span>
                        <?php endforeach; ?>
                        <?php foreach ($conditions as $c): ?>
                            <span class="inline-flex rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-700">
                                <i class="fas fa-notes-medical mr-1"></i> <?php echo htmlspecialchars(trim($c)); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <button onclick="openUploadModal()" class="inline-flex items-center gap-2 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth">
                        <i class="fas fa-upload"></i> Upload Document
                    </button>
                </div>
            </div>
        </div>
        
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Contact & Medical Info -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-card">
                <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-address-card text-[#800020] mr-2"></i> Contact Information</h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <div class="text-xs uppercase text-gray-500">Email</div>
                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($patient['email']); ?></div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Emergency Contact</div>
                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($patient['emergency_contact']); ?></div>
                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($patient['emergency_phone']); ?></div>
                    </div>
                </div>
                
                <div class="mt-4 pt-4 border-t">
                    <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-notes-medical text-[#800020] mr-2"></i> Medical Information</h3>
                    <div class="space-y-2 text-sm">
                        <div><span class="text-xs uppercase text-gray-500">Blood Type</span><br><span class="font-medium"><?php echo htmlspecialchars($patient['blood_type'] ?? 'N/A'); ?></span></div>
                        <div><span class="text-xs uppercase text-gray-500">Allergies</span><br><span class="font-medium"><?php echo !empty($allergies) ? implode(', ', $allergies) : 'None'; ?></span></div>
                        <div><span class="text-xs uppercase text-gray-500">Medical Conditions</span><br><span class="font-medium"><?php echo !empty($conditions) ? implode(', ', $conditions) : 'None'; ?></span></div>
                    </div>
                </div>
            </div>
            
            <!-- Visit Timeline -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-card lg:col-span-2">
                <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-history text-[#800020] mr-2"></i> Visit History</h3>
                <div class="space-y-3 max-h-80 overflow-y-auto">
                    <?php if (empty($visits)): ?>
                        <p class="text-sm text-gray-500 text-center py-4">No visits recorded.</p>
                    <?php else: foreach ($visits as $v): ?>
                        <div class="rounded-lg border border-gray-200 p-4 hover:shadow-sm transition-smooth">
                            <div class="flex items-start justify-between flex-wrap gap-2">
                                <div>
                                    <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($v['symptoms'] ?? 'General Checkup'); ?></div>
                                    <div class="text-xs text-gray-500">
                                        <i class="far fa-calendar-alt mr-1"></i> <?php echo date('M d, Y', strtotime($v['visit_date'])); ?>
                                        <span class="mx-1">•</span>
                                        <i class="fas fa-user-nurse mr-1"></i> Nurse <?php echo htmlspecialchars(($v['nurse_first'] ?? 'N/A') . ' ' . ($v['nurse_last'] ?? '')); ?>
                                    </div>
                                </div>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium 
                                    <?php echo $v['status'] == 'completed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                    <?php echo ucfirst($v['status']); ?>
                                </span>
                            </div>
                            <?php if (!empty($v['diagnosis'])): ?>
                                <div class="mt-2 text-sm text-gray-600"><strong>Diagnosis:</strong> <?php echo htmlspecialchars($v['diagnosis']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($v['treatment'])): ?>
                                <div class="text-sm text-gray-600"><strong>Treatment:</strong> <?php echo htmlspecialchars($v['treatment']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Documents Section -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-card">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center flex-wrap gap-2">
                <div>
                    <h3 class="font-semibold text-gray-900"><i class="fas fa-folder-open text-[#800020] mr-2"></i> Medical Documents</h3>
                    <p class="text-xs text-gray-500">X-rays, Prescriptions, Medical Certificates, Lab Results</p>
                </div>
                <button onclick="openUploadModal()" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-smooth">
                    <i class="fas fa-plus"></i> Add Document
                </button>
            </div>
            <div class="p-6">
                <?php if (empty($documents)): ?>
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-folder-open text-5xl text-gray-300 mb-3 block"></i>
                        <p>No documents uploaded yet.</p>
                        <p class="text-xs">Click "Add Document" to upload X-rays, prescriptions, or other medical records.</p>
                    </div>
                <?php else: ?>
                    <div class="grid gap-3">
                        <?php foreach ($documents as $doc): ?>
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 hover:bg-gray-50 transition-smooth">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg <?php echo getDocColor($doc['document_type']); ?>">
                                        <i class="fas <?php echo getDocIcon($doc['document_type']); ?> text-lg"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['original_name']); ?></div>
                                        <div class="text-xs text-gray-500">
                                            <span class="capitalize"><?php echo str_replace('_', ' ', $doc['document_type']); ?></span>
                                            <span class="mx-1">•</span>
                                            <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?>
                                            <span class="mx-1">•</span>
                                            <?php echo round($doc['file_size'] / 1024); ?> KB
                                            <?php if (!empty($doc['description'])): ?>
                                                <span class="mx-1">•</span>
                                                <?php echo htmlspecialchars(substr($doc['description'], 0, 50)); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="openDocumentModal('<?php echo '../../uploads/patient_documents/' . $doc['file_path']; ?>', '<?php echo htmlspecialchars($doc['original_name']); ?>', '<?php echo $doc['file_type']; ?>')" 
                                            class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 transition-smooth">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <a href="../../uploads/patient_documents/<?php echo $doc['file_path']; ?>" download
                                       class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200 transition-smooth">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <a href="?id=<?php echo $patient_id; ?>&delete_doc=<?php echo $doc['id']; ?>" 
                                       onclick="return confirm('Delete this document?')"
                                       class="inline-flex items-center gap-1 rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 transition-smooth">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Upload Document Modal -->
<div id="uploadModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl">
        <div class="border-b px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-upload text-[#800020] mr-2"></i> Upload Document</h3>
            <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="student_id" value="<?php echo $patient_id; ?>">
            <input type="hidden" name="upload_document" value="1">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Document Type *</label>
                <select name="document_type" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                    <option value="xray">X-Ray Image</option>
                    <option value="prescription">Prescription</option>
                    <option value="medcert">Medical Certificate</option>
                    <option value="lab_result">Laboratory Result</option>
                    <option value="other">Other Document</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Select File *</label>
                <input type="file" name="patient_file" id="patient_file" required 
                       accept="image/jpeg,image/png,image/jpg,application/pdf,image/gif"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020] file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#800020] file:text-white hover:file:bg-[#600018]">
                <p class="mt-1 text-xs text-gray-400">Allowed: JPG, PNG, PDF, GIF. Max 5MB</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
                <textarea name="description" rows="2" placeholder="e.g., Chest X-ray from June 2024, Prescription for antibiotics..." 
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#800020] focus:ring-1 focus:ring-[#800020]"></textarea>
            </div>
            
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeUploadModal()" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="flex-1 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018]">Upload</button>
            </div>
        </form>
    </div>
</div>

<!-- Document Viewer Modal (POPUP - hindi aalis ng page) -->
<div id="documentModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl mx-4">
        <div class="border-b px-6 py-4 flex items-center justify-between bg-white">
            <h3 class="text-lg font-bold text-gray-900" id="docModalTitle">Document Viewer</h3>
            <button onclick="closeDocumentModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-6 overflow-auto max-h-[70vh] flex items-center justify-center bg-gray-100" id="documentModalContent">
            <div class="text-center text-gray-500">
                <i class="fas fa-spinner fa-spin text-3xl mb-2"></i>
                <p>Loading document...</p>
            </div>
        </div>
        <div class="border-t px-6 py-4 flex justify-end gap-3 bg-gray-50">
            <a href="#" id="docDownloadLink" download class="inline-flex items-center gap-2 rounded-lg bg-[#800020] px-4 py-2 text-sm font-medium text-white hover:bg-[#600018] transition-smooth">
                <i class="fas fa-download"></i> Download
            </a>
            <button onclick="closeDocumentModal()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-smooth">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    function openUploadModal() {
        document.getElementById('uploadModal').classList.remove('hidden');
        document.getElementById('uploadModal').classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
    
    function closeUploadModal() {
        document.getElementById('uploadModal').classList.add('hidden');
        document.getElementById('uploadModal').classList.remove('flex');
        document.body.style.overflow = '';
    }
    
    // Document Viewer Modal - HINDI AALIS NG PAGE
    function openDocumentModal(filePath, fileName, fileType) {
        const modal = document.getElementById('documentModal');
        const contentDiv = document.getElementById('documentModalContent');
        const titleSpan = document.getElementById('docModalTitle');
        const downloadLink = document.getElementById('docDownloadLink');
        
        titleSpan.innerHTML = '<i class="fas fa-file mr-2"></i> ' + fileName;
        downloadLink.href = filePath;
        
        // Check if it's an image or PDF
        if (fileType.includes('image')) {
            contentDiv.innerHTML = `<img src="${filePath}" alt="${fileName}" class="max-w-full max-h-[65vh] object-contain rounded shadow-lg">`;
        } else if (fileType === 'application/pdf') {
            contentDiv.innerHTML = `<iframe src="${filePath}" class="w-full h-[65vh]" frameborder="0"></iframe>`;
        } else {
            contentDiv.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-file-alt text-6xl text-gray-400 mb-4 block"></i>
                    <p class="text-gray-600">Cannot preview this file type.</p>
                    <p class="text-sm text-gray-500 mt-2">Click Download to view the file.</p>
                </div>
            `;
        }
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
    
    function closeDocumentModal() {
        document.getElementById('documentModal').classList.add('hidden');
        document.getElementById('documentModal').classList.remove('flex');
        document.body.style.overflow = '';
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeUploadModal();
            closeDocumentModal();
        }
    });
    
    document.getElementById('uploadModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeUploadModal();
    });
    
    document.getElementById('documentModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeDocumentModal();
    });
</script>

</body>
</html>