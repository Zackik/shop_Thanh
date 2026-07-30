<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db_config.php';
$admin_id = $_SESSION['user_id'];

$toast_message = "";
$toast_type = "success";

// 1. Handle Customer Edit
if (isset($_POST['update_customer'])) {
    $cust_id = intval($_POST['customer_id']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $role = $_POST['role'];

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, role = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $full_name, $email, $phone, $address, $role, $cust_id);
    if ($stmt->execute()) { $toast_message = "Customer updated successfully!"; } 
    else { $toast_message = "Error: " . $stmt->error; $toast_type = "error"; }
    $stmt->close();
}

// 2. Handle Delete Customer
if (isset($_GET['delete_customer'])) {
    $cust_id = intval($_GET['delete_customer']);
    if ($cust_id === intval($admin_id)) {
        $toast_message = "You cannot delete your own admin account!";
        $toast_type = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $cust_id);
        if ($stmt->execute()) { $toast_message = "Customer deleted successfully!"; } 
        else { $toast_message = "Error: " . $stmt->error; $toast_type = "error"; }
        $stmt->close();
    }
}

// 3. Handle Add Product (with Sale Price)
if (isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : NULL;
    $stock = intval($_POST['stock']);
    
    $image_path = "product_images/default.jpg";
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "product_images/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
        $file_name = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) { $image_path = $target_file; }
    }

    $stmt = $conn->prepare("INSERT INTO products (name, description, price, sale_price, stock, image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddis", $name, $description, $price, $sale_price, $stock, $image_path);
    if ($stmt->execute()) { $toast_message = "Product added successfully!"; } 
    else { $toast_message = "Error: " . $stmt->error; $toast_type = "error"; }
    $stmt->close();
}

// 4. Handle Update Product (with Sale Price)
if (isset($_POST['update_product'])) {
    $prod_id = intval($_POST['product_id']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : NULL;
    $stock = intval($_POST['stock']);
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "product_images/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
        $file_name = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, sale_price = ?, stock = ?, image = ? WHERE id = ?");
            $stmt->bind_param("ssddisi", $name, $description, $price, $sale_price, $stock, $image_path, $prod_id);
        } else {
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, sale_price = ?, stock = ? WHERE id = ?");
            $stmt->bind_param("ssddii", $name, $description, $price, $sale_price, $stock, $prod_id);
        }
    } else {
        $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, sale_price = ?, stock = ? WHERE id = ?");
        $stmt->bind_param("ssddii", $name, $description, $price, $sale_price, $stock, $prod_id);
    }
    
    if ($stmt->execute()) { $toast_message = "Product updated successfully!"; } 
    else { $toast_message = "Error: " . $stmt->error; $toast_type = "error"; }
    $stmt->close();
}

// 5. Handle Delete Product
if (isset($_GET['delete_product'])) {
    $prod_id = intval($_GET['delete_product']);
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $prod_id);
    if ($stmt->execute()) { $toast_message = "Product deleted successfully!"; } 
    else { $toast_message = "Error: " . $stmt->error; $toast_type = "error"; }
    $stmt->close();
}

// 6. Handle Send Event Announcement / Message from Admin to Customer
if (isset($_POST['send_broadcast'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $target_user = intval($_POST['target_user']); // 0 = All Users, or Specific User ID

    if ($target_user === 0) {
        // Gửi broadcast cho toàn bộ users
        $users_res = $conn->query("SELECT id FROM users");
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)");
        while ($u = $users_res->fetch_assoc()) {
            $rec_id = $u['id'];
            $stmt->bind_param("iiss", $admin_id, $rec_id, $subject, $message);
            $stmt->execute();
        }
        $stmt->close();
        $toast_message = "Announcement broadcasted to all customers successfully!";
    } else {
        // Gửi riêng cho 1 khách hàng
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $admin_id, $target_user, $subject, $message);
        if ($stmt->execute()) { $toast_message = "Message sent to customer successfully!"; }
        $stmt->close();
    }
}

// Fetch Admin Details
$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Metrics
$total_users = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0;
$total_orders = $conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'] ?? 0;
$total_revenue = $conn->query("SELECT SUM(total) AS rev FROM orders WHERE LOWER(status)='delivered'")->fetch_assoc()['rev'] ?? 0;
$total_products = $conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()['c'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Buy 🛒 - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-panel { background: rgba(14, 14, 16, 0.85); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.08); }
        @keyframes floatGlow1 { 0%, 100% { transform: translate(-50%, 0px) scale(1); opacity: 0.15; } 50% { transform: translate(-30%, 50px) scale(1.2); opacity: 0.3; } }
        @keyframes floatGlow2 { 0%, 100% { transform: translate(0px, 0px) scale(1.1); opacity: 0.1; } 50% { transform: translate(-40px, -30px) scale(0.9); opacity: 0.25; } }
        .glow-orb-1 { animation: floatGlow1 10s ease-in-out infinite; }
        .glow-orb-2 { animation: floatGlow2 12s ease-in-out infinite; }
    </style>
</head>
<body class="bg-black text-white antialiased min-h-screen flex flex-col justify-between selection:bg-white selection:text-black overflow-x-hidden">

    <!-- Ambient Gradients -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[900px] h-[450px] bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 blur-[160px] pointer-events-none rounded-full z-0 glow-orb-1"></div>
    <div class="fixed top-1/3 left-1/4 w-[600px] h-[300px] bg-gradient-to-tr from-blue-600 to-indigo-500 blur-[140px] pointer-events-none rounded-full z-0 glow-orb-2"></div>

    <!-- Toast Notification -->
    <?php if (!empty($toast_message)): ?>
        <div id="toast-alert" class="fixed top-6 right-6 z-50 glass-panel px-6 py-4 rounded-2xl border <?= $toast_type == 'success' ? 'border-emerald-500/30 text-emerald-400' : 'border-rose-500/30 text-rose-400' ?> text-xs font-bold uppercase tracking-wider shadow-2xl flex items-center gap-3">
            <span class="w-2.5 h-2.5 rounded-full <?= $toast_type == 'success' ? 'bg-emerald-500' : 'bg-rose-500' ?> animate-ping"></span>
            <span><?= htmlspecialchars($toast_message, ENT_QUOTES) ?></span>
        </div>
        <script>setTimeout(() => { const t = document.getElementById('toast-alert'); if(t) { t.style.opacity='0'; setTimeout(()=>t.remove(),500); } }, 3500);</script>
    <?php endif; ?>

    <!-- Header -->
    <header class="sticky top-0 z-40 glass-panel border-b border-white/10">
        <div class="max-w-[96rem] mx-auto px-6 h-20 flex justify-between items-center">
            <div class="text-base font-black tracking-widest uppercase"><a href="index.php">Thanh Buy <span class="text-indigo-400">Admin</span></a></div>
            <nav class="hidden md:flex items-center gap-8 font-semibold text-neutral-400 text-xs tracking-widest uppercase">
                <a href="#products-section" class="hover:text-white transition">Inventory & Sales</a>
                <a href="#customers-section" class="hover:text-white transition">Customers</a>
                <a href="#messaging-section" class="hover:text-white transition">Events & Messaging</a>
                <a href="index.php" class="hover:text-white transition">Storefront</a>
                <a href="./admin_orders.php" class="hover:text-white transition">Checkout</a>
                
                <a href="./card_game.php" class="flex-1 group relative bg-gradient-to-r from-amber-500 to-yellow-600 hover:from-amber-400 hover:to-yellow-500 border border-amber-300/40 p-4 rounded-2xl shadow-xl shadow-amber-950/50 transition-all duration-300 transform hover:-translate-y-1 text-center flex items-center justify-center gap-3">
            <span class="text-2xl">🎲</span>
            <div class="text-left">
                <div class="text-[10px] text-black/80 uppercase tracking-widest font-extrabold">Management</div>
                <div class="text-black font-black text-sm tracking-wide">Admin Control</div>
            </div>
        </a>
            </nav>
            <div><a href="logout.php" class="bg-white/10 border border-white/20 text-white px-5 py-2.5 rounded-full font-bold hover:bg-white hover:text-black transition text-[11px] uppercase tracking-widest">Logout</a></div>
        </div>
    </header>

    <main class="flex-grow max-w-[96rem] w-full mx-auto px-4 sm:px-6 py-10 space-y-12 relative z-10">

        <!-- Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="glass-panel p-8 rounded-3xl shadow-2xl flex flex-col justify-center space-y-2">
                <span class="text-indigo-400 text-[10px] font-extrabold tracking-[0.25em] uppercase">Control Center</span>
                <h1 class="text-2xl font-extrabold tracking-tight text-white"><?= htmlspecialchars($admin['full_name']) ?></h1>
                <p class="text-neutral-400 text-xs">Manage inventory sales, announcements & clients.</p>
            </div>
            <div class="glass-panel p-6 rounded-3xl shadow-2xl flex flex-col justify-between border-indigo-500/20">
                <span class="text-indigo-400 text-[10px] font-extrabold tracking-[0.2em] uppercase">Total Revenue</span>
                <h3 class="text-3xl font-black font-mono text-white mt-3">$<?= number_format($total_revenue, 2) ?></h3>
            </div>
            <div class="glass-panel p-6 rounded-3xl shadow-2xl flex flex-col justify-between">
                <span class="text-neutral-400 text-[10px] font-extrabold tracking-[0.2em] uppercase">Products</span>
                <h3 class="text-3xl font-black font-mono text-white mt-3"><?= number_format($total_products) ?></h3>
            </div>
            <div class="glass-panel p-6 rounded-3xl shadow-2xl flex flex-col justify-between">
                <span class="text-neutral-400 text-[10px] font-extrabold tracking-[0.2em] uppercase">Customers</span>
                <h3 class="text-3xl font-black font-mono text-white mt-3"><?= number_format($total_users) ?></h3>
            </div>
        </div>

        <!-- SECTION 1: PRODUCTS & SALE PRICES -->
        <div id="products-section" class="glass-panel p-6 sm:p-8 rounded-3xl shadow-2xl space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-base font-extrabold uppercase tracking-widest text-white">Inventory & Special Sales</h2>
                    <p class="text-neutral-400 text-xs mt-1">Set original price and promotional sale price for vehicles.</p>
                </div>
                <button onclick="openAddProductModal()" class="bg-white text-black px-5 py-3 rounded-2xl font-extrabold text-xs uppercase tracking-wider hover:bg-indigo-500 hover:text-white transition">+ Add Product / Sale</button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-white/10 text-neutral-400 text-[11px] uppercase tracking-widest font-bold">
                            <th class="px-4 py-3.5">ID</th>
                            <th class="px-4 py-3.5">Image</th>
                            <th class="px-4 py-3.5">Name</th>
                            <th class="px-4 py-3.5 text-right">Pricing (Regular / Sale)</th>
                            <th class="px-4 py-3.5 text-center">Stock</th>
                            <th class="px-4 py-3.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-xs">
                        <?php
                        $prod_res = $conn->query("SELECT * FROM products ORDER BY id DESC");
                        while ($prod = $prod_res->fetch_assoc()) {
                            $has_sale = !is_null($prod['sale_price']) && $prod['sale_price'] < $prod['price'];
                            $price_display = $has_sale 
                                ? '<div class="line-through text-neutral-500 text-[10px]">$' . number_format($prod['price'], 2) . '</div><div class="text-rose-400 font-mono font-bold">$' . number_format($prod['sale_price'], 2) . ' <span class="bg-rose-500/20 text-rose-300 text-[9px] px-1.5 py-0.5 rounded ml-1">SALE</span></div>'
                                : '<div class="text-indigo-400 font-mono font-bold">$' . number_format($prod['price'], 2) . '</div>';

                            echo '
                                <tr class="hover:bg-white/[0.02] transition">
                                    <td class="px-4 py-4 font-mono font-bold">#' . $prod['id'] . '</td>
                                    <td class="px-4 py-4"><img src="' . htmlspecialchars($prod['image']) . '" class="w-10 h-10 object-cover rounded-xl border border-white/10"></td>
                                    <td class="px-4 py-4 font-bold text-white">' . htmlspecialchars($prod['name']) . '</td>
                                    <td class="px-4 py-4 text-right">' . $price_display . '</td>
                                    <td class="px-4 py-4 text-center font-mono">' . $prod['stock'] . '</td>
                                    <td class="px-4 py-4 text-center space-x-2">
                                        <button onclick="openEditProductModal(\'' . $prod['id'] . '\', \'' . addslashes($prod['name']) . '\', \'' . addslashes($prod['description']) . '\', \'' . $prod['price'] . '\', \'' . ($prod['sale_price'] ?? '') . '\', \'' . $prod['stock'] . '\')" class="bg-white/10 hover:bg-white hover:text-black text-white px-3 py-2 rounded-xl font-bold text-[10px] uppercase transition">Edit</button>
                                        <a href="admin_dashboard.php?delete_product=' . $prod['id'] . '" onclick="return confirm(\'Delete this product?\')" class="bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white px-3 py-2 rounded-xl font-bold text-[10px] uppercase transition">Delete</a>
                                    </td>
                                </tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 2: CUSTOMERS -->
        <div id="customers-section" class="glass-panel p-6 sm:p-8 rounded-3xl shadow-2xl space-y-6">
            <h2 class="text-base font-extrabold uppercase tracking-widest text-white">Registered Customers</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-white/10 text-neutral-400 text-[11px] uppercase tracking-widest font-bold">
                            <th class="px-4 py-3.5">ID</th>
                            <th class="px-4 py-3.5">Name</th>
                            <th class="px-4 py-3.5">Email</th>
                            <th class="px-4 py-3.5">Role</th>
                            <th class="px-4 py-3.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-xs">
                        <?php
                        $cust_res = $conn->query("SELECT * FROM users ORDER BY id DESC");
                        while ($cust = $cust_res->fetch_assoc()) {
                            echo '
                                <tr class="hover:bg-white/[0.02] transition">
                                    <td class="px-4 py-4 font-mono">#' . $cust['id'] . '</td>
                                    <td class="px-4 py-4 font-bold text-white">' . htmlspecialchars($cust['full_name']) . '</td>
                                    <td class="px-4 py-4 text-neutral-300">' . htmlspecialchars($cust['email']) . '</td>
                                    <td class="px-4 py-4"><span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase ' . ($cust['role']=='admin'?'bg-indigo-500/20 text-indigo-400':'bg-white/5 text-neutral-400') . '">' . $cust['role'] . '</span></td>
                                    <td class="px-4 py-4 text-center space-x-2">
                                        <button onclick="openEditModal(\'' . $cust['id'] . '\', \'' . addslashes($cust['full_name']) . '\', \'' . addslashes($cust['email']) . '\', \'' . addslashes($cust['phone']) . '\', \'' . addslashes($cust['address']) . '\', \'' . $cust['role'] . '\')" class="bg-white/10 hover:bg-white hover:text-black text-white px-3 py-2 rounded-xl text-[10px] font-bold uppercase transition">Edit</button>
                                        <a href="admin_dashboard.php?delete_customer=' . $cust['id'] . '" onclick="return confirm(\'Delete this customer?\')" class="bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white px-3 py-2 rounded-xl text-[10px] font-bold uppercase transition">Delete</a>
                                    </td>
                                </tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 3: ADMIN EVENT MESSAGING & CHAT CENTER -->
        <div id="messaging-section" class="glass-panel p-6 sm:p-8 rounded-3xl shadow-2xl space-y-6">
            <div>
                <h2 class="text-base font-extrabold uppercase tracking-widest text-white">Event Broadcasts & Customer Messages</h2>
                <p class="text-neutral-400 text-xs mt-1">Send event notifications, discount announcements, or reply directly to client inquiries.</p>
            </div>

            <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-black/40 p-6 rounded-2xl border border-white/5">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Target Recipient</label>
                    <select name="target_user" class="w-full px-4 py-3 bg-black/60 border border-white/10 rounded-xl text-white text-xs focus:outline-none">
                        <option value="0">📢 Broadcast to ALL Customers (Events / Sales)</option>
                        <?php
                        $u_list = $conn->query("SELECT id, full_name, email FROM users WHERE role != 'admin'");
                        while ($u = $u_list->fetch_assoc()) {
                            echo '<option value="' . $u['id'] . '">User: ' . htmlspecialchars($u['full_name']) . ' (' . $u['email'] . ')</option>';
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Subject / Event Title</label>
                    <input type="text" name="subject" placeholder="e.g. Special Holiday Mega Sale!" required class="w-full px-4 py-3 bg-black/60 border border-white/10 rounded-xl text-white text-xs focus:outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Action</label>
                    <button type="submit" name="send_broadcast" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold py-3 rounded-xl uppercase text-xs tracking-wider transition">Send Message</button>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Message Content</label>
                    <textarea name="message" rows="3" placeholder="Write your event announcement or reply here..." required class="w-full px-4 py-3 bg-black/60 border border-white/10 rounded-xl text-white text-xs focus:outline-none resize-none"></textarea>
                </div>
            </form>

            <!-- Message Log / History -->
            <div class="space-y-3 pt-4">
                <h3 class="text-xs font-bold uppercase tracking-widest text-neutral-400">Recent Messages Sent & Received</h3>
                <div class="space-y-2 max-h-64 overflow-y-auto pr-2">
                    <?php
                    $msg_res = $conn->query("SELECT m.*, u.full_name as sender_name FROM messages m JOIN users u ON m.sender_id = u.id ORDER BY m.id DESC LIMIT 10");
                    if ($msg_res && $msg_res->num_rows > 0) {
                        while ($msg = $msg_res->fetch_assoc()) {
                            echo '
                            <div class="bg-white/[0.02] p-4 rounded-xl border border-white/5 text-xs space-y-1">
                                <div class="flex justify-between text-neutral-400 text-[10px]">
                                    <span class="font-bold text-white">From: ' . htmlspecialchars($msg['sender_name']) . ' &rarr; To ID: #' . $msg['receiver_id'] . '</span>
                                    <span>' . $msg['created_at'] . '</span>
                                </div>
                                <div class="font-bold text-indigo-300">' . htmlspecialchars($msg['subject'] ?? 'Direct Message') . '</div>
                                <div class="text-neutral-300">' . nl2br(htmlspecialchars($msg['message'])) . '</div>
                            </div>';
                        }
                    } else {
                        echo '<p class="text-neutral-500 text-xs">No messages recorded yet.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>

    </main>

    <!-- ADD PRODUCT MODAL -->
    <div id="addProductModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden flex items-center justify-center p-4">
        <div class="glass-panel p-8 rounded-3xl shadow-2xl max-w-md w-full border border-white/10 space-y-6">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-extrabold uppercase tracking-widest text-white">Add Product & Sale</h3>
                <button onclick="closeAddProductModal()" class="text-neutral-400 hover:text-white">✕</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Product Name</label>
                    <input type="text" name="name" required class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Description</label>
                    <textarea name="description" rows="2" required class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs resize-none"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Regular Price ($)</label>
                        <input type="number" step="0.01" name="price" required class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Sale Price (Optional)</label>
                        <input type="number" step="0.01" name="sale_price" placeholder="Leave empty if no sale" class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Stock</label>
                        <input type="number" name="stock" required class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Image</label>
                        <input type="file" name="image" accept="image/*" class="w-full text-xs text-neutral-400 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:bg-white/10 file:text-white">
                    </div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeAddProductModal()" class="w-1/2 bg-white/10 text-white py-3 rounded-xl text-xs uppercase font-bold">Cancel</button>
                    <button type="submit" name="add_product" class="w-1/2 bg-white text-black py-3 rounded-xl text-xs uppercase font-bold hover:bg-indigo-500 hover:text-white">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT PRODUCT MODAL -->
    <div id="editProductModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden flex items-center justify-center p-4">
        <div class="glass-panel p-8 rounded-3xl shadow-2xl max-w-md w-full border border-white/10 space-y-6">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-extrabold uppercase tracking-widest text-white">Edit Product & Sale</h3>
                <button onclick="closeEditProductModal()" class="text-neutral-400 hover:text-white">✕</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="product_id" id="edit_prod_id">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Product Name</label>
                    <input type="text" name="name" id="edit_prod_name" required class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Description</label>
                    <textarea name="description" id="edit_prod_desc" rows="2" required class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs resize-none"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Regular Price ($)</label>
                        <input type="number" step="0.01" name="price" id="edit_prod_price" required class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Sale Price</label>
                        <input type="number" step="0.01" name="sale_price" id="edit_prod_sale_price" placeholder="Optional" class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Stock</label>
                    <input type="number" name="stock" id="edit_prod_stock" required class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeEditProductModal()" class="w-1/2 bg-white/10 text-white py-3 rounded-xl text-xs uppercase font-bold">Cancel</button>
                    <button type="submit" name="update_product" class="w-1/2 bg-white text-black py-3 rounded-xl text-xs uppercase font-bold hover:bg-indigo-500 hover:text-white">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT CUSTOMER MODAL -->
    <div id="editModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-md hidden flex items-center justify-center p-4">
        <div class="glass-panel p-8 rounded-3xl shadow-2xl max-w-md w-full border border-white/10 space-y-6">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-extrabold uppercase tracking-widest text-white">Edit Customer</h3>
                <button onclick="closeEditModal()" class="text-neutral-400 hover:text-white">✕</button>
            </div>
            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name" required class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Email</label>
                    <input type="email" name="email" id="edit_email" required class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Phone</label>
                    <input type="text" name="phone" id="edit_phone" class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Address</label>
                    <input type="text" name="address" id="edit_address" class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Role</label>
                    <select name="role" id="edit_role" class="w-full px-4 py-3 bg-black/50 border border-white/10 rounded-xl text-white text-xs">
                        <option value="user" class="bg-neutral-900">User</option>
                        <option value="admin" class="bg-neutral-900">Admin</option>
                    </select>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeEditModal()" class="w-1/2 bg-white/10 text-white py-3 rounded-xl text-xs uppercase font-bold">Cancel</button>
                    <button type="submit" name="update_customer" class="w-1/2 bg-white text-black py-3 rounded-xl text-xs uppercase font-bold hover:bg-indigo-500 hover:text-white">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddProductModal() { document.getElementById('addProductModal').classList.remove('hidden'); }
        function closeAddProductModal() { document.getElementById('addProductModal').classList.add('hidden'); }
        function openEditProductModal(id, name, desc, price, salePrice, stock) {
            document.getElementById('edit_prod_id').value = id;
            document.getElementById('edit_prod_name').value = name;
            document.getElementById('edit_prod_desc').value = desc;
            document.getElementById('edit_prod_price').value = price;
            document.getElementById('edit_prod_sale_price').value = salePrice;
            document.getElementById('edit_prod_stock').value = stock;
            document.getElementById('editProductModal').classList.remove('hidden');
        }
        function closeEditProductModal() { document.getElementById('editProductModal').classList.add('hidden'); }
        function openEditModal(id, name, email, phone, address, role) {
            document.getElementById('edit_customer_id').value = id;
            document.getElementById('edit_full_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_address').value = address;
            document.getElementById('edit_role').value = role;
            document.getElementById('editModal').classList.remove('hidden');
        }
        function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); }
    </script>
</body>
<!-- Footer -->
<footer class="bg-black border-t border-amber-500/20 text-neutral-400 text-xs mt-16 pt-12 pb-8">
    <div class="max-w-6xl mx-auto px-4 grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
        
        <!-- Cột 1: Thông tin thương hiệu & Dịch vụ -->
        <div class="space-y-3">
            <h3 class="text-amber-400 font-extrabold uppercase tracking-widest text-sm">VIP Casino Royale</h3>
            <p class="text-neutral-400 leading-relaxed">
                Sân chơi giải trí bài 21 điểm (Blackjack) đỉnh cao, giao lưu trí tuệ và thử thách vận may đẳng cấp quốc tế.
            </p>
            <div class="text-[11px] text-amber-300/80 font-bold uppercase tracking-wider">
                ✨ Dịch vụ: Giải trí trực tuyến, Bàn cược VIP, Sự kiện giải đấu bài lá.
            </div>
        </div>

        <!-- Cột 2: Thời gian mở cửa -->
        <div class="space-y-3">
            <h4 class="text-amber-400 font-extrabold uppercase tracking-widest text-sm">🕒 Giờ Mở Cửa</h4>
            <ul class="space-y-1.5 text-neutral-300">
                <li class="flex justify-between"><span>Thứ Hai - Thứ Sáu:</span> <strong class="text-amber-300">08:00 - 03:00</strong></li>
                <li class="flex justify-between"><span>Thứ Bảy - Chủ Nhật:</span> <strong class="text-amber-300">24/7 (Cả ngày)</strong></li>
                <li class="text-[11px] text-emerald-400 pt-1 font-semibold">● Hệ thống trực tuyến hoạt động liên tục.</li>
            </ul>
        </div>

        <!-- Cột 3: Liên hệ (Email, Số điện thoại, Địa chỉ) -->
        <div class="space-y-3">
            <h4 class="text-amber-400 font-extrabold uppercase tracking-widest text-sm">📞 Thông Tin Liên Hệ</h4>
            <ul class="space-y-2 text-neutral-300">
                <li class="flex items-center gap-2">
                    <span>📍</span> <span>Tầng 68, Landmark Tower, Quận 1, TP. Hồ Chí Minh</span>
                </li>
                <li class="flex items-center gap-2">
                    <span>☎️</span> <a href="tel:+84900000000" class="hover:text-amber-400 transition">+84 (0) 900 000 000</a>
                </li>
                <li class="flex items-center gap-2">
                    <span>✉️</span> <a href="mailto:support@casinoroyale.vip" class="hover:text-amber-400 transition">support@casinoroyale.vip</a>
                </li>
            </ul>
        </div>

        <!-- Cột 4: Bản đồ vị trí (Google Maps Embed) -->
        <div class="space-y-3">
            <h4 class="text-amber-400 font-extrabold uppercase tracking-widest text-sm">🗺️ Bản Đồ Vị Trí</h4>
            <div class="rounded-xl overflow-hidden border border-amber-500/30 h-32 shadow-md">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.507663249015!2d106.70200877688224!3d10.777073289379685!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752f440e1f5c09%3A0x6b63d40e9db6b26d!2zSG9hIE5naeG7iywgQuG6vyBOaOG6vywgUXXhuq1uIDEsIEjhu5MgQ2jDrSBNaW5oLCBWaeG7h3QgTmFt!5e0!3m2!1svi!2svn!4v1700000000000!5m2!1svi!2svn" 
                    width="100%" 
                    height="100%" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>

    </div>

    <!-- Copyright Bar -->
    <div class="max-w-6xl mx-auto px-4 border-t border-white/10 pt-6 text-center text-neutral-500 text-[11px] tracking-widest uppercase">
        <p>&copy; 2026 Thanh Buy 🛒 &mdash; All Rights Reserved</p>
    </div>
</footer>
</html>