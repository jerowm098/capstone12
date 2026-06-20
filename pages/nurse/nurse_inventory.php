<?php
// capstonemain/pages/nurse/nurse_inventory.php
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
$stmt = mysqli_prepare($conn, "SELECT u.first_name, u.last_name 
                      FROM nurses n 
                      JOIN users u ON n.user_id = u.id 
                      WHERE n.id = ?");
mysqli_stmt_bind_param($stmt, "i", $nurse_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$nurse = mysqli_fetch_assoc($result);

$nurse_name = ($nurse['first_name'] ?? 'Nurse') . ' ' . ($nurse['last_name'] ?? '');
$initials = strtoupper(substr($nurse['first_name'] ?? 'N', 0, 1) . substr($nurse['last_name'] ?? 'S', 0, 1));

// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$low_stock_only = isset($_GET['low_stock']) && $_GET['low_stock'] == '1';

// =====================================================
// HANDLE ADD MEDICINE
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medicine'])) {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $quantity = intval($_POST['quantity']);
    $unit = trim($_POST['unit']);
    $threshold = intval($_POST['threshold']);
    $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
    $description = trim($_POST['description']);
    
    $stmt = mysqli_prepare($conn, "INSERT INTO medicines (name, category, quantity, unit, threshold, expiration_date, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssisiss", $name, $category, $quantity, $unit, $threshold, $expiration_date, $description);
    
    if (mysqli_stmt_execute($stmt)) {
        $medicine_id = mysqli_insert_id($conn);
        
        // Log the action
        $log_stmt = mysqli_prepare($conn, "INSERT INTO stock_logs (medicine_id, nurse_name, action, quantity, previous_quantity, new_quantity, reason, notes) VALUES (?, ?, 'add', ?, ?, ?, ?, ?)");
        $reason = "Initial stock entry";
        $notes = "New medicine added to inventory";
        mysqli_stmt_bind_param($log_stmt, "isiiiss", $medicine_id, $nurse_name, $quantity, 0, $quantity, $reason, $notes);
        mysqli_stmt_execute($log_stmt);
        
        $message = "Medicine added successfully!";
        $message_type = "success";
    } else {
        $message = "Failed to add medicine: " . mysqli_error($conn);
        $message_type = "error";
    }
}

// =====================================================
// HANDLE EDIT MEDICINE
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_medicine'])) {
    $medicine_id = intval($_POST['medicine_id']);
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $quantity = intval($_POST['quantity']);
    $unit = trim($_POST['unit']);
    $threshold = intval($_POST['threshold']);
    $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
    $description = trim($_POST['description']);
    
    // Get old quantity
    $old_stmt = mysqli_prepare($conn, "SELECT quantity FROM medicines WHERE id = ?");
    mysqli_stmt_bind_param($old_stmt, "i", $medicine_id);
    mysqli_stmt_execute($old_stmt);
    $old_result = mysqli_stmt_get_result($old_stmt);
    $old_data = mysqli_fetch_assoc($old_result);
    $old_quantity = $old_data['quantity'];
    
    $stmt = mysqli_prepare($conn, "UPDATE medicines SET name = ?, category = ?, quantity = ?, unit = ?, threshold = ?, expiration_date = ?, description = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ssisissi", $name, $category, $quantity, $unit, $threshold, $expiration_date, $description, $medicine_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Log if quantity changed
        if ($old_quantity != $quantity) {
            $change = $quantity - $old_quantity;
            $action = $change > 0 ? 'add' : 'remove';
            $log_stmt = mysqli_prepare($conn, "INSERT INTO stock_logs (medicine_id, nurse_name, action, quantity, previous_quantity, new_quantity, reason, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $reason = "Manual stock adjustment via edit";
            $notes = "Medicine details updated";
            mysqli_stmt_bind_param($log_stmt, "isiiisss", $medicine_id, $nurse_name, $action, abs($change), $old_quantity, $quantity, $reason, $notes);
            mysqli_stmt_execute($log_stmt);
        }
        
        $message = "Medicine updated successfully!";
        $message_type = "success";
    } else {
        $message = "Failed to update medicine.";
        $message_type = "error";
    }
}

// =====================================================
// HANDLE DELETE MEDICINE
// =====================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $medicine_id = intval($_GET['delete']);
    
    $stmt = mysqli_prepare($conn, "DELETE FROM medicines WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $medicine_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "Medicine deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Failed to delete medicine.";
        $message_type = "error";
    }
}

// =====================================================
// HANDLE STOCK ADJUSTMENT (Add/Remove)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $medicine_id = intval($_POST['medicine_id']);
    $adjustment = intval($_POST['adjustment']);
    $reason = trim($_POST['reason']);
    $selected_nurse = trim($_POST['nurse_name']);
    $action = $adjustment > 0 ? 'add' : 'remove';
    $change = abs($adjustment);
    
    // Get current quantity
    $stmt = mysqli_prepare($conn, "SELECT quantity, name FROM medicines WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $medicine_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $medicine = mysqli_fetch_assoc($result);
    
    if ($medicine) {
        $old_quantity = $medicine['quantity'];
        $new_quantity = $old_quantity + $adjustment;
        if ($new_quantity < 0) $new_quantity = 0;
        
        $update_stmt = mysqli_prepare($conn, "UPDATE medicines SET quantity = ? WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, "ii", $new_quantity, $medicine_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            // Log the adjustment
            $log_stmt = mysqli_prepare($conn, "INSERT INTO stock_logs (medicine_id, nurse_name, action, quantity, previous_quantity, new_quantity, reason, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $notes = "Stock adjusted for " . $medicine['name'];
            mysqli_stmt_bind_param($log_stmt, "isiiisss", $medicine_id, $selected_nurse, $action, $change, $old_quantity, $new_quantity, $reason, $notes);
            mysqli_stmt_execute($log_stmt);
            
            $message = "Stock adjusted successfully! " . ($adjustment > 0 ? "Added" : "Removed") . " " . $change . " " . $medicine['name'];
            $message_type = "success";
        } else {
            $message = "Failed to adjust stock.";
            $message_type = "error";
        }
    }
}

// =====================================================
// GET ALL MEDICINES WITH FILTERS
// =====================================================
$query = "SELECT * FROM medicines WHERE 1=1";
if (!empty($search)) {
    $query .= " AND (name LIKE '%$search%' OR description LIKE '%$search%')";
}
if (!empty($category)) {
    $query .= " AND category = '$category'";
}
if ($low_stock_only) {
    $query .= " AND quantity <= threshold";
}
$query .= " ORDER BY 
    CASE WHEN quantity = 0 THEN 0 
         WHEN quantity <= threshold THEN 1 
         ELSE 2 END,
    name ASC";

$medicines_result = mysqli_query($conn, $query);
$medicines = [];
while ($row = mysqli_fetch_assoc($medicines_result)) {
    $medicines[] = $row;
}

// =====================================================
// GET UNIQUE CATEGORIES FOR FILTER
// =====================================================
$categories_result = mysqli_query($conn, "SELECT DISTINCT category FROM medicines ORDER BY category");
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row['category'];
}

// =====================================================
// GET INVENTORY SUMMARY
// =====================================================
$summary_result = mysqli_query($conn, "SELECT 
    COUNT(*) as total,
    SUM(quantity) as total_units,
    SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN quantity <= threshold AND quantity > 0 THEN 1 ELSE 0 END) as low_stock
FROM medicines");
$summary = mysqli_fetch_assoc($summary_result);

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Inventory - PUPBC Carelink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal { transition: opacity 0.3s ease; }
        .transition-smooth { transition: all 0.3s ease; }
        body { background-color: #f8fafc; }
        .sidebar-item-active {
            background-color: #c9a84c;
            color: #800020;
        }
        .sidebar-item {
            color: white;
        }
        .sidebar-item:hover {
            background-color: #600018;
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Top Header -->
<header class="sticky top-0 z-50 border-b border-gray-200 bg-white shadow-sm">
    <div class="flex h-14 items-center justify-between px-4">
        <div class="flex items-center gap-3">
            <button id="mobileMenuBtn" class="block md:hidden text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div class="flex items-center gap-2">
                <div class="rounded-lg bg-gradient-to-r from-[#800020] to-[#600018] p-1.5">
                    <span class="text-white font-bold text-sm">PUP</span>
                </div>
                <div>
                    <span class="font-bold text-gray-900">PUPBC</span>
                    <span class="font-bold text-[#c9a84c]"> Carelink</span>
                </div>
            </div>
            <span class="hidden text-xs text-gray-500 md:inline">Nurse Portal · Inventory Management</span>
        </div>
        <div class="flex items-center gap-4">
            <span class="hidden text-sm text-gray-600 md:block"><?php echo htmlspecialchars($nurse_name); ?></span>
            <a href="nurse_logout.php" class="text-sm text-red-600 hover:underline">Sign out</a>
        </div>
    </div>
</header>

<!-- Desktop Sidebar -->
<aside class="hidden md:block fixed top-0 left-0 h-full w-64 bg-[#800020] shadow-xl overflow-y-auto z-30">
    <div class="flex items-center gap-2 p-4 border-b border-[#600018]">
        <div class="rounded-lg bg-white/20 p-2"><i class="fas fa-heartbeat text-white text-lg"></i></div>
        <div><span class="font-bold text-white">PUPBC Carelink</span><p class="text-[10px] text-white/60">Health Information System</p></div>
    </div>
    
    <div class="flex items-center gap-3 p-4 border-b border-[#600018] bg-[#600018]">
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#c9a84c] text-[#800020] font-bold text-sm"><?php echo $initials; ?></div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($nurse_name); ?></div>
            <div class="text-xs text-[#c9a84c] truncate"><?php echo htmlspecialchars($nurse_position ?? 'Head Nurse'); ?></div>
        </div>
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
        <a href="nurse_inventory.php" class="flex items-center gap-3 mx-3 px-3 py-2.5 rounded-lg transition-all <?php echo $current_page == 'nurse_inventory.php' ? 'bg-[#c9a84c] text-[#800020]' : 'text-white hover:bg-[#600018]'; ?>">
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
<main class="md:ml-64 min-h-screen pb-20 md:pb-6">
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200">
        <div class="px-4 md:px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Medicine Inventory</h1>
                <p class="text-sm text-gray-500">View and manage clinic medicines and supplies</p>
            </div>
            
            <div class="relative">
                <button class="relative focus:outline-none p-2 hover:bg-gray-100 rounded-full transition">
                    <i class="fas fa-bell text-gray-600 text-xl"></i>
                </button>
            </div>
        </div>
    </div>
    
    <div class="p-4 md:p-6">
        <div class="space-y-6 animate-fade-in max-w-6xl mx-auto">
            <!-- Header with Add Button -->
            <div class="mb-6 flex flex-col md:flex-row justify-end items-start md:items-center gap-4">
                <button onclick="openAddModal()" 
                    class="bg-[#800020] text-white px-4 py-2 rounded-lg hover:bg-[#600018] transition-smooth flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    Add Medicine
                </button>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg <?php echo $message_type == 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <p class="text-gray-500 text-sm">Total Medicines</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($summary['total'] ?? 0); ?></p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <p class="text-gray-500 text-sm">Total Stock Units</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($summary['total_units'] ?? 0); ?></p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <p class="text-gray-500 text-sm">Low Stock</p>
                    <p class="text-2xl font-bold text-[#c9a84c]"><?php echo number_format($summary['low_stock'] ?? 0); ?></p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <p class="text-gray-500 text-sm">Out of Stock</p>
                    <p class="text-2xl font-bold text-[#800020]"><?php echo number_format($summary['out_of_stock'] ?? 0); ?></p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
                <form method="GET" class="flex flex-wrap gap-3">
                    <input type="text" name="search" placeholder="Search medicine..." 
                        value="<?php echo htmlspecialchars($search); ?>"
                        class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020] focus:ring-1 focus:ring-[#800020]">
                    
                    <select name="category" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category == $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="bg-[#800020] text-white px-4 py-2 rounded-lg hover:bg-[#600018] transition-smooth">
                        Filter
                    </button>
                    
                    <?php if ($low_stock_only || !empty($search) || !empty($category)): ?>
                        <a href="nurse_inventory.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition-smooth">
                            Clear
                        </a>
                    <?php endif; ?>
                    
                    <a href="?low_stock=1" class="bg-[#c9a84c] text-[#800020] px-4 py-2 rounded-lg hover:bg-[#b8963a] transition-smooth font-medium">
                        Show Low Stock Only
                    </a>
                </form>
            </div>

            <!-- Medicines Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Medicine</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Category</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Stock</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Unit</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Expiration</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($medicines)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        No medicines found. Click "Add Medicine" to get started.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($medicines as $medicine): 
                                    $is_low = $medicine['quantity'] <= $medicine['threshold'];
                                    $is_out = $medicine['quantity'] == 0;
                                    $is_expired = $medicine['expiration_date'] && strtotime($medicine['expiration_date']) < time();
                                    $is_expiring = $medicine['expiration_date'] && strtotime($medicine['expiration_date']) <= strtotime('+30 days') && strtotime($medicine['expiration_date']) >= time();
                                ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50 transition-smooth <?php echo $is_out ? 'bg-red-50' : ($is_low ? 'bg-orange-50' : ''); ?>">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($medicine['name']); ?></div>
                                            <?php if ($medicine['description']): ?>
                                                <div class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars(substr($medicine['description'], 0, 60)); ?></div>
                                            <?php endif; ?>
                                            <div class="flex gap-1 mt-1">
                                                <?php if ($is_out): ?>
                                                    <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">OUT OF STOCK</span>
                                                <?php elseif ($is_low): ?>
                                                    <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">LOW STOCK</span>
                                                <?php endif; ?>
                                                <?php if ($is_expired): ?>
                                                    <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">EXPIRED</span>
                                                <?php elseif ($is_expiring): ?>
                                                    <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">EXPIRING SOON</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 bg-gray-100 rounded-full text-xs text-gray-700"><?php echo htmlspecialchars($medicine['category']); ?></span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="font-bold <?php echo $is_out ? 'text-red-600' : ($is_low ? 'text-orange-600' : 'text-gray-900'); ?>">
                                                <?php echo number_format($medicine['quantity']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 text-sm"><?php echo htmlspecialchars($medicine['unit']); ?></td>
                                        <td class="px-4 py-3 text-sm">
                                            <?php if ($medicine['expiration_date']): ?>
                                                <span class="<?php echo $is_expired ? 'text-red-600 font-bold' : ($is_expiring ? 'text-yellow-600' : 'text-gray-600'); ?>">
                                                    <?php echo date('M d, Y', strtotime($medicine['expiration_date'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400">No expiry</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex justify-center gap-2">
                                                <button onclick="openAdjustModal(<?php echo $medicine['id']; ?>, '<?php echo addslashes($medicine['name']); ?>', <?php echo $medicine['quantity']; ?>)" 
                                                    class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition-smooth">
                                                    Adjust Stock
                                                </button>
                                                <button onclick='openEditModal(<?php echo json_encode($medicine); ?>)' 
                                                    class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700 transition-smooth">
                                                    Edit
                                                </button>
                                                <button onclick="confirmDelete(<?php echo $medicine['id']; ?>, '<?php echo addslashes($medicine['name']); ?>')" 
                                                    class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700 transition-smooth">
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- ADD MEDICINE MODAL -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4 text-gray-900">Add New Medicine</h3>
        <form method="POST">
            <div class="space-y-3">
                <input type="text" name="name" placeholder="Medicine Name *" required 
                    class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                
                <select name="category" required class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                    <option value="">Select Category</option>
                    <option value="Cough & Cold">Cough & Cold</option>
                    <option value="First Aid">First Aid</option>
                    <option value="Pain Relief">Pain Relief</option>
                    <option value="Antibiotics">Antibiotics</option>
                    <option value="Vitamins">Vitamins</option>
                    <option value="Skin Care">Skin Care</option>
                    <option value="Stomach Care">Stomach Care</option>
                </select>
                
                <textarea name="description" placeholder="Description (e.g., for fever, cough, etc.)" rows="2" 
                    class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]"></textarea>
                
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" name="quantity" placeholder="Quantity" value="0" 
                        class="p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                    <select name="unit" class="p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                        <option value="pcs">pcs (pieces)</option>
                        <option value="bottle">bottle</option>
                        <option value="box">box</option>
                        <option value="tablet">tablet</option>
                        <option value="capsule">capsule</option>
                        <option value="roll">roll</option>
                        <option value="tube">tube</option>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" name="threshold" placeholder="Low stock alert (e.g., 10)" value="10" 
                        class="p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                    <input type="date" name="expiration_date" 
                        class="p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeAddModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-smooth">Cancel</button>
                <button type="submit" name="add_medicine" class="px-4 py-2 bg-[#800020] text-white rounded-lg hover:bg-[#600018] transition-smooth">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MEDICINE MODAL -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4 text-gray-900">Edit Medicine</h3>
        <form method="POST">
            <input type="hidden" name="medicine_id" id="edit_id">
            <div class="space-y-3">
                <input type="text" name="name" id="edit_name" required 
                    class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                
                <select name="category" id="edit_category" required class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                    <option value="Cough & Cold">Cough & Cold</option>
                    <option value="First Aid">First Aid</option>
                    <option value="Pain Relief">Pain Relief</option>
                    <option value="Antibiotics">Antibiotics</option>
                    <option value="Vitamins">Vitamins</option>
                    <option value="Skin Care">Skin Care</option>
                    <option value="Stomach Care">Stomach Care</option>
                </select>
                
                <textarea name="description" id="edit_description" rows="2" 
                    class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]"></textarea>
                
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" name="quantity" id="edit_quantity" 
                        class="p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                    <select name="unit" id="edit_unit" class="p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                        <option value="pcs">pcs (pieces)</option>
                        <option value="bottle">bottle</option>
                        <option value="box">box</option>
                        <option value="tablet">tablet</option>
                        <option value="capsule">capsule</option>
                        <option value="roll">roll</option>
                        <option value="tube">tube</option>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" name="threshold" id="edit_threshold" 
                        class="p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                    <input type="date" name="expiration_date" id="edit_expiration" 
                        class="p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-smooth">Cancel</button>
                <button type="submit" name="edit_medicine" class="px-4 py-2 bg-[#800020] text-white rounded-lg hover:bg-[#600018] transition-smooth">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- ADJUST STOCK MODAL -->
<div id="adjustModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-md p-6">
        <h3 class="text-lg font-bold mb-4 text-gray-900">Adjust Stock</h3>
        <form method="POST">
            <input type="hidden" name="medicine_id" id="adjust_id">
            <div class="space-y-3">
                <p class="text-gray-700">Medicine: <strong id="adjust_name" class="text-[#800020]"></strong></p>
                <p class="text-gray-700">Current Stock: <strong id="adjust_current" class="text-blue-600"></strong></p>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nurse Name *</label>
                    <select name="nurse_name" required class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                        <option value="">Select Nurse</option>
                        <option value="Maria Santos">Maria Santos</option>
                        <option value="John Reyes">John Reyes</option>
                        <option value="Ana Cruz">Ana Cruz</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adjustment Amount</label>
                    <input type="number" name="adjustment" id="adjust_amount" placeholder="Example: +10 or -5" required 
                        class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                    <p class="text-xs text-gray-500 mt-1">Use + to add stock, - to remove stock</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason *</label>
                    <input type="text" name="reason" required placeholder="e.g., new delivery, dispensed to patient" 
                        class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#800020]">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeAdjustModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-smooth">Cancel</button>
                <button type="submit" name="adjust_stock" class="px-4 py-2 bg-[#800020] text-white rounded-lg hover:bg-[#600018] transition-smooth">Apply</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Mobile menu toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    
    if (mobileBtn) {
        mobileBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
        });
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (window.innerWidth < 768 && sidebar && !sidebar.contains(e.target) && !mobileBtn.contains(e.target)) {
            sidebar.classList.add('-translate-x-full');
        }
    });

    // Modal functions
    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
        document.getElementById('addModal').classList.add('flex');
    }
    
    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
        document.getElementById('addModal').classList.remove('flex');
    }
    
    function openEditModal(medicine) {
        document.getElementById('edit_id').value = medicine.id;
        document.getElementById('edit_name').value = medicine.name;
        document.getElementById('edit_category').value = medicine.category;
        document.getElementById('edit_description').value = medicine.description || '';
        document.getElementById('edit_quantity').value = medicine.quantity;
        document.getElementById('edit_unit').value = medicine.unit;
        document.getElementById('edit_threshold').value = medicine.threshold;
        document.getElementById('edit_expiration').value = medicine.expiration_date || '';
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('editModal').classList.add('flex');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.getElementById('editModal').classList.remove('flex');
    }
    
    function openAdjustModal(id, name, currentStock) {
        document.getElementById('adjust_id').value = id;
        document.getElementById('adjust_name').innerText = name;
        document.getElementById('adjust_current').innerText = currentStock;
        document.getElementById('adjust_amount').value = '';
        document.getElementById('adjustModal').classList.remove('hidden');
        document.getElementById('adjustModal').classList.add('flex');
    }
    
    function closeAdjustModal() {
        document.getElementById('adjustModal').classList.add('hidden');
        document.getElementById('adjustModal').classList.remove('flex');
    }
    
    function confirmDelete(id, name) {
        if (confirm(`Are you sure you want to delete "${name}"?`)) {
            window.location.href = `?delete=${id}`;
        }
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('fixed')) {
            closeAddModal();
            closeEditModal();
            closeAdjustModal();
        }
    }
    
    // Color code for adjustment input (positive/negative)
    const adjustAmount = document.getElementById('adjust_amount');
    if (adjustAmount) {
        adjustAmount.addEventListener('input', function() {
            let val = parseInt(this.value);
            if (!isNaN(val)) {
                if (val > 0) {
                    this.classList.add('border-green-500');
                    this.classList.remove('border-red-500');
                } else if (val < 0) {
                    this.classList.add('border-red-500');
                    this.classList.remove('border-green-500');
                } else {
                    this.classList.remove('border-green-500', 'border-red-500');
                }
            }
        });
    }
</script>
</body>
</html> hold').value = medicine.threshold;
        document.getElementById('edit_expiration').value = medicine.expiration_date || '';
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('editModal').classList.add('flex');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.getElementById('editModal').classList.remove('flex');
    }
    
    function openAdjustModal(id, name, currentStock) {
        document.getElementById('adjust_id').value = id;
        document.getElementById('adjust_name').innerText = name;
        document.getElementById('adjust_current').innerText = currentStock;
        document.getElementById('adjust_amount').value = '';
        document.getElementById('adjustModal').classList.remove('hidden');
        document.getElementById('adjustModal').classList.add('flex');
    }
    
    function closeAdjustModal() {
        document.getElementById('adjustModal').classList.add('hidden');
        document.getElementById('adjustModal').classList.remove('flex');
    }
    
    function confirmDelete(id, name) {
        if (confirm(`Are you sure you want to delete "${name}"?`)) {
            window.location.href = `?delete=${id}`;
        }
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('fixed')) {
            closeAddModal();
            closeEditModal();
            closeAdjustModal();
        }
    }
    
    // Color code for adjustment input (positive/negative)
    const adjustAmount = document.getElementById('adjust_amount');
    if (adjustAmount) {
        adjustAmount.addEventListener('input', function() {
            let val = parseInt(this.value);
            if (!isNaN(val)) {
                if (val > 0) {
                    this.classList.add('border-green-500');
                    this.classList.remove('border-red-500');
                } else if (val < 0) {
                    this.classList.add('border-red-500');
                    this.classList.remove('border-green-500');
                } else {
                    this.classList.remove('border-green-500', 'border-red-500');
                }
            }
        });
    }
</script>
</body>
</html> 