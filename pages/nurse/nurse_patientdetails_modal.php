<?php
// capstone1/pages/nurse/nurse_patientdetails_modal.php
session_start();
if (!isset($_SESSION['nurse_id'])) {
    echo '<p class="text-red-500">Unauthorized</p>';
    exit();
}
require_once '../../config/db_connect.php';

$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get patient details
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name, u.email, s.* FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
mysqli_stmt_bind_param($stmt, "i", $patient_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$patient = mysqli_fetch_assoc($result);

if (!$patient) {
    echo '<p class="text-red-500 text-center py-8">Patient not found.</p>';
    exit();
}

$allergies = !empty($patient['allergies']) ? explode(',', $patient['allergies']) : [];
$conditions = !empty($patient['medical_conditions']) ? explode(',', $patient['medical_conditions']) : [];
$pinitials = strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1));
?>
<div class="space-y-5">
    <!-- Patient Header -->
    <div class="flex items-center gap-4">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-primary text-2xl font-bold text-white shadow-md">
            <?php echo $pinitials; ?>
        </div>
        <div>
            <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($patient['student_number']); ?></p>
            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($patient['course']); ?> · <?php echo htmlspecialchars($patient['year_level']); ?></p>
        </div>
    </div>
    
    <!-- Contact Info -->
    <div class="grid gap-4 md:grid-cols-2">
        <div class="bg-gray-50 rounded-xl p-4">
            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-address-card text-[#800020] mr-1"></i> Contact</h4>
            <div class="space-y-1 text-sm">
                <div><span class="text-xs text-gray-500">Email:</span><br><?php echo htmlspecialchars($patient['email']); ?></div>
                <div><span class="text-xs text-gray-500">Emergency:</span><br><?php echo htmlspecialchars($patient['emergency_contact']); ?><br><span class="text-xs"><?php echo htmlspecialchars($patient['emergency_phone']); ?></span></div>
            </div>
        </div>
        <div class="bg-gray-50 rounded-xl p-4">
            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-notes-medical text-[#800020] mr-1"></i> Medical Info</h4>
            <div class="space-y-1 text-sm">
                <div><span class="text-xs text-gray-500">Blood Type:</span> <?php echo htmlspecialchars($patient['blood_type'] ?? 'N/A'); ?></div>
                <div><span class="text-xs text-gray-500">Allergies:</span> <?php echo !empty($allergies) ? implode(', ', $allergies) : 'None'; ?></div>
                <div><span class="text-xs text-gray-500">Conditions:</span> <?php echo !empty($conditions) ? implode(', ', $conditions) : 'None'; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Recent Visits -->
    <div>
        <h4 class="font-semibold text-sm mb-2"><i class="fas fa-history text-[#800020] mr-1"></i> Recent Visits</h4>
        <?php
        $visits_stmt = mysqli_prepare($conn, "SELECT symptoms, diagnosis, visit_date FROM visits WHERE student_id = ? ORDER BY visit_date DESC LIMIT 3");
        mysqli_stmt_bind_param($visits_stmt, "i", $patient_id);
        mysqli_stmt_execute($visits_stmt);
        $visits_result = mysqli_stmt_get_result($visits_stmt);
        $has_visits = false;
        while ($visit = mysqli_fetch_assoc($visits_result)) {
            $has_visits = true;
            echo '<div class="border-l-4 border-[#800020] pl-3 mb-2 py-1"><p class="text-sm font-medium">' . htmlspecialchars($visit['symptoms'] ?? 'General Checkup') . '</p><p class="text-xs text-gray-500">' . date('M d, Y', strtotime($visit['visit_date'])) . ' · ' . htmlspecialchars($visit['diagnosis'] ?? 'Pending') . '</p></div>';
        }
        if (!$has_visits) echo '<p class="text-sm text-gray-500">No previous visits.</p>';
        ?>
    </div>
    
    <!-- View Full Record Button -->
    <div class="pt-3 border-t">
        <a href="nurse_patientdetails.php?id=<?php echo $patient_id; ?>" 
           class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#800020] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#600018] transition-smooth">
            <i class="fas fa-folder-open"></i> View Full Record
        </a>
        <p class="text-center text-xs text-gray-400 mt-2">Full record includes visit history, documents, and file uploads</p>
    </div>
</div>  