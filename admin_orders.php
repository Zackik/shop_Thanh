<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = intval($_SESSION['user_id']);
$chk_admin = $conn->prepare("SELECT role FROM users WHERE id = ?");
$chk_admin->bind_param("i", $admin_id);
$chk_admin->execute();
$admin_data = $chk_admin->get_result()->fetch_assoc();
$chk_admin->close();

if (!$admin_data || strtolower($admin_data['role'] ?? '') !== 'admin') {
    echo "<script>alert('Access denied! Admin privileges required.'); window.location.href='index.php';</script>";
    exit();
}

// Xử lý cập nhật trạng thái & lưu thông báo vào Session để hiển thị toast
$toast_msg = "";
if (isset($_GET['action']) && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    $new_status = '';
    $action_text = '';
    
    if ($action === 'deliver') {
        $new_status = 'Delivered';
        $action_text = "Order #$order_id marked as Delivered";
    } elseif ($action === 'cancel') {
        $new_status = 'Canceled';
        $action_text = "Order #$order_id has been Canceled";
    } elseif ($action === 'pending') {
        $new_status = 'Pending';
        $action_text = "Order #$order_id reset to Pending";
    }

    if (!empty($new_status)) {
        $upd = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $upd->bind_param("si", $new_status, $order_id);
        if ($upd->execute()) {
            $_SESSION['toast'] = $action_text;
        }
        $upd->close();
        header("Location: admin_orders.php");
        exit();
    }
}

// Lấy danh sách đơn hàng
$orders_query = "
    SELECT o.*, u.full_name as customer_name, u.email as customer_email 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC
";
$all_orders = $conn->query($orders_query);
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard &mdash; Manage Orders</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0a0a0a; }
        ::-webkit-scrollbar-thumb { background: #262626; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #404040; }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .toast-animate { animation: slideIn 0.3s ease forwards; }
    </style>
</head>
<body class="bg-[#050505] text-neutral-100 antialiased selection:bg-white selection:text-black min-h-screen flex flex-col justify-between">

    <!-- Toast Notification Nhỏ ở góc trên bên phải -->
    <?php if (isset($_SESSION['toast'])): ?>
        <div id="toastMessage" class="fixed top-24 right-6 z-50 bg-neutral-900 border border-white/15 text-white px-5 py-3.5 rounded-2xl shadow-2xl flex items-center gap-3 text-xs font-semibold toast-animate backdrop-blur-xl">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            <span><?php echo htmlspecialchars($_SESSION['toast']); ?></span>
        </div>
        <script>
            setTimeout(() => {
                const toast = document.getElementById('toastMessage');
                if(toast) {
                    toast.style.transition = 'opacity 0.4s ease';
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 400);
                }
            }, 3000);
        </script>
        <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>

    <!-- Header -->
    <header class="fixed top-0 left-0 right-0 z-40 bg-[#050505]/75 backdrop-blur-2xl border-b border-white/[0.08]">
        <div class="max-w-7xl mx-auto px-6 h-20 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <span class="text-base font-extrabold tracking-wider uppercase text-white flex items-center gap-2">
                    Thanh Buy <span class="text-neutral-500 font-light">/</span> <span class="text-xs bg-white/10 px-2.5 py-1 rounded-md text-neutral-300 font-semibold tracking-normal">Admin Panel</span>
                </span>
            </div>
            <div class="flex items-center gap-3">
                <a href="index.php" class="text-xs text-neutral-400 hover:text-white uppercase tracking-wider px-4 py-2 rounded-xl hover:bg-white/5 transition">Storefront</a>
                <a href="logout.php" class="bg-white text-black hover:bg-neutral-200 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-sm">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Section -->
    <main class="max-w-7xl mx-auto px-6 pt-36 pb-24 w-full flex-grow space-y-10">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-white/[0.08] pb-6">
            <div>
                <span class="text-neutral-500 text-[11px] font-bold tracking-widest uppercase block mb-1">System Control</span>
                <h1 class="text-3xl font-extrabold text-white tracking-tight">Order Management</h1>
            </div>
            <div class="flex items-center gap-3 bg-neutral-900/80 border border-white/10 px-4 py-2.5 rounded-2xl text-xs backdrop-blur-md">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-neutral-400">Total System Orders:</span>
                <strong class="text-white font-mono text-sm"><?php echo $all_orders ? $all_orders->num_rows : 0; ?></strong>
            </div>
        </div>

        <!-- Danh sách đơn hàng -->
        <div class="space-y-6">
            <?php if ($all_orders && $all_orders->num_rows > 0): ?>
                <div class="grid grid-cols-1 gap-6">
                    <?php while ($order = $all_orders->fetch_assoc()): ?>
                        <?php 
                            $order_id = $order['id'];
                            $status = strtolower($order['status'] ?? 'pending');
                        ?>
                        <div class="group bg-[#0a0a0a] border border-white/[0.08] hover:border-white/25 rounded-3xl p-7 transition-all duration-300 shadow-2xl space-y-6 relative overflow-hidden">
                            
                            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/[0.06] pb-5 text-xs">
                                <div class="flex items-center gap-6">
                                    <div>
                                        <span class="text-[10px] font-bold uppercase text-neutral-500 block tracking-widest mb-0.5">Order ID</span>
                                        <span class="font-mono font-bold text-white text-base">#<?php echo $order_id; ?></span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-bold uppercase text-neutral-500 block tracking-widest mb-0.5">Customer</span>
                                        <span class="font-bold text-white text-sm"><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></span>
                                        <span class="text-neutral-500 text-[10px] block font-mono"><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-6">
                                    <div class="hidden sm:block text-right">
                                        <span class="text-[10px] font-bold uppercase text-neutral-500 block tracking-widest mb-0.5">Timestamp</span>
                                        <span class="text-neutral-400 font-mono text-[11px]"><?php echo $order['created_at']; ?></span>
                                    </div>
                                    <div>
                                        <?php 
                                            $badge_class = "bg-neutral-900 border border-white/10 text-neutral-300";
                                            if ($status === 'delivered' || $status === 'completed') {
                                                $badge_class = "bg-emerald-950/70 border border-emerald-500/30 text-emerald-400";
                                            } elseif ($status === 'canceled') {
                                                $badge_class = "bg-rose-950/70 border border-rose-500/30 text-rose-400";
                                            } elseif ($status === 'pending') {
                                                $badge_class = "bg-amber-950/70 border border-amber-500/30 text-amber-400";
                                            }
                                        ?>
                                        <span class="font-extrabold uppercase px-3.5 py-1.5 rounded-full text-[10px] tracking-wider inline-block <?php echo $badge_class; ?>">
                                            ● <?php echo htmlspecialchars($order['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs bg-neutral-900/40 p-4 rounded-2xl border border-white/[0.04]">
                                <div>
                                    <span class="text-neutral-500 uppercase tracking-wider text-[10px] block mb-0.5 font-bold">Payment Method</span>
                                    <span class="text-white font-medium"><?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></span>
                                </div>
                                <div>
                                    <span class="text-neutral-500 uppercase tracking-wider text-[10px] block mb-0.5 font-bold">Shipping Address</span>
                                    <span class="text-white font-medium"><?php echo htmlspecialchars($order['address'] ?? 'N/A'); ?></span>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-2xl border border-white/[0.06] bg-[#050505]">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-neutral-900/60 text-neutral-400 uppercase tracking-widest text-[10px] border-b border-white/[0.06]">
                                            <th class="p-3.5 pl-4 font-semibold">Product Name</th>
                                            <th class="p-3.5 font-semibold">Unit Price</th>
                                            <th class="p-3.5 font-semibold">Qty</th>
                                            <th class="p-3.5 pr-4 text-right font-semibold">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/[0.04] text-neutral-300">
                                        <?php
                                        $items_stmt = $conn->prepare("SELECT product_name, product_price, quantity FROM order_items WHERE order_id = ?");
                                        $items_stmt->bind_param("i", $order_id);
                                        $items_stmt->execute();
                                        $items_res = $items_stmt->get_result();
                                        while ($item = $items_res->fetch_assoc()) {
                                        ?>
                                            <tr class="hover:bg-white/[0.01] transition">
                                                <td class="p-3.5 pl-4 font-medium text-white"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                <td class="p-3.5 font-mono text-neutral-400">$<?php echo number_format($item['product_price'], 2); ?></td>
                                                <td class="p-3.5 font-mono text-neutral-400"><?php echo intval($item['quantity']); ?></td>
                                                <td class="p-3.5 pr-4 text-right font-bold font-mono text-white">$<?php echo number_format($item['product_price'] * $item['quantity'], 2); ?></td>
                                            </tr>
                                        <?php 
                                        }
                                        $items_stmt->close();
                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Tổng tiền và Nút Hủy (Cancel) nằm ở đây -->
                            <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
                                <div class="text-xs font-bold text-neutral-400">
                                    Total Amount: <span class="text-white font-mono text-base ml-1">$<?php echo number_format($order['total'] ?? 0, 2); ?> USD</span>
                                </div>

                                <div class="flex items-center gap-2.5">
                                    <?php if ($status !== 'delivered'): ?>
                                        <a href="admin_orders.php?action=deliver&id=<?php echo $order_id; ?>" 
                                           onclick="return confirm('Mark Order #<?php echo $order_id; ?> as Delivered?')"
                                           class="bg-white hover:bg-neutral-200 text-black px-4 py-2.5 rounded-xl text-[11px] font-extrabold uppercase tracking-wider transition shadow-md">
                                            ✓ Deliver
                                        </a>
                                    <?php endif; ?>

                                    <!-- NÚT CANCEL ĐƠN HÀNG DÀNH CHO ADMIN Ở ĐÂY -->
                                    <?php if ($status !== 'canceled'): ?>
                                        <a href="admin_orders.php?action=cancel&id=<?php echo $order_id; ?>" 
                                           onclick="return confirm('Are you sure you want to cancel Order #<?php echo $order_id; ?>?')"
                                           class="bg-neutral-900 border border-white/10 hover:bg-rose-950/50 hover:border-rose-500/30 hover:text-rose-400 text-neutral-300 px-4 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition">
                                            ✕ Cancel
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($status !== 'pending'): ?>
                                        <a href="admin_orders.php?action=pending&id=<?php echo $order_id; ?>" 
                                           onclick="return confirm('Reset Order #<?php echo $order_id; ?> to Pending?')"
                                           class="bg-neutral-900 border border-white/10 hover:bg-neutral-800 text-neutral-400 px-3.5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition" title="Reset to Pending">
                                            ↻
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="bg-[#0a0a0a] border border-white/[0.08] rounded-3xl p-16 text-center space-y-3">
                    <p class="text-xs uppercase tracking-widest text-neutral-500 font-semibold">No active orders found in database.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

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

</body>
</html>