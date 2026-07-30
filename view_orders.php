<?php
// Start the session
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch user information from the database
include 'db_config.php';
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$toast_message = "";

// Handle order deletion
if (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] == 'delete') {
    $order_id = intval($_GET['id']);

    // Delete related order items first
    $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    if ($stmt->execute()) {
        // Now delete the order itself
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute()) {
            header("Location: view_orders.php?msg=deleted");
            exit();
        } else {
            $toast_message = "Error deleting order.";
        }
    } else {
        $toast_message = "Error deleting related order items.";
    }
    $stmt->close();
}

// Handle order status update
if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $status = $_POST['status'];

    // Update status in the database
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $order_id);

    if ($stmt->execute()) {
        header("Location: view_orders.php?msg=updated");
        exit();
    } else {
        $toast_message = "Error updating order status.";
    }
    $stmt->close();
}

// 1. Fetch Orders Count grouped by Month (For Chart 1)
$order_months_sql = "SELECT YEAR(created_at) AS year, MONTH(created_at) AS month, COUNT(*) AS order_count FROM orders GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY year ASC, month ASC";
$order_months_result = $conn->query($order_months_sql);

$months = [];
$order_counts = [];

if ($order_months_result && $order_months_result->num_rows > 0) {
    while ($row = $order_months_result->fetch_assoc()) {
        $months[] = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
        $order_counts[] = $row['order_count'];
    }
} else {
    $months = ['No Data'];
    $order_counts = [0];
}

// 2. Fetch Best-Selling Products (For Chart 2)
$top_products_sql = "SELECT product_name, SUM(quantity) AS total_sold FROM order_items GROUP BY product_name ORDER BY total_sold DESC LIMIT 5";
$top_products_result = $conn->query($top_products_sql);

$top_product_names = [];
$top_product_sales = [];

if ($top_products_result && $top_products_result->num_rows > 0) {
    while ($row = $top_products_result->fetch_assoc()) {
        $top_product_names[] = $row['product_name'];
        $top_product_sales[] = $row['total_sold'];
    }
} else {
    $top_product_names = ['No Sales Yet'];
    $top_product_sales = [0];
}

// 3. Financial Statistics (Total Revenue & Today Revenue)
// Total Delivered Revenue
$rev_total_res = $conn->query("SELECT SUM(total) AS total_rev FROM orders WHERE LOWER(status)='delivered'");
$total_revenue = $rev_total_res ? ($rev_total_res->fetch_assoc()['total_rev'] ?? 0) : 0;

// Today's Delivered Revenue
$rev_today_res = $conn->query("SELECT SUM(total) AS today_rev FROM orders WHERE LOWER(status)='delivered' AND DATE(created_at) = CURDATE()");
$today_revenue = $rev_today_res ? ($rev_today_res->fetch_assoc()['today_rev'] ?? 0) : 0;

$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Buy 🛒 - Admin Orders & Analytics</title>
    <link rel="icon" href="favicon.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-panel {
            background: rgba(14, 14, 16, 0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .table-scroll::-webkit-scrollbar { height: 6px; width: 6px; }
        .table-scroll::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 4px; }
    </style>
</head>

<body class="bg-black text-white antialiased min-h-screen flex flex-col justify-between selection:bg-white selection:text-black">

    <!-- Background Ambient Glow -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[400px] bg-gradient-to-b from-indigo-600/10 to-transparent blur-[150px] pointer-events-none rounded-full z-0"></div>

    <!-- Toast Notification Alert -->
    <?php if ($msg || $toast_message): ?>
        <div id="toast-alert" class="fixed top-6 right-6 z-50 glass-panel px-6 py-4 rounded-2xl border border-emerald-500/30 text-emerald-400 text-xs font-bold uppercase tracking-wider shadow-2xl flex items-center gap-3">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></span>
            <span>
                <?php 
                    if ($msg == 'updated') echo 'Order status successfully updated!';
                    elseif ($msg == 'deleted') echo 'Order successfully deleted!';
                    else echo $toast_message;
                ?>
            </span>
        </div>
        <script>
            setTimeout(() => {
                const t = document.getElementById('toast-alert');
                if(t) { t.style.opacity = '0'; t.style.transition = 'opacity 0.5s ease'; setTimeout(() => t.remove(), 500); }
            }, 3500);
        </script>
    <?php endif; ?>

    <!-- Header Navigation -->
    <header class="sticky top-0 z-40 glass-panel border-b border-white/10">
        <div class="max-w-[96rem] mx-auto px-6 h-20 flex justify-between items-center">
            <div class="text-base sm:text-lg font-black tracking-widest uppercase text-white">
                <a href="index.php" class="hover:opacity-80 transition">Thanh Buy <span class="text-indigo-400">Admin</span></a>
            </div>
            <nav class="hidden md:flex items-center gap-8 font-semibold text-neutral-400 text-xs tracking-widest uppercase">
                <a href="admin_dashboard.php" class="hover:text-white transition">Dashboard</a>
                <a href="view_orders.php" class="text-white transition">Orders & Analytics</a>
            </nav>
            <div>
                <a href="logout.php" class="bg-white/10 border border-white/20 text-white px-5 py-2.5 rounded-full font-bold hover:bg-white hover:text-black transition-all text-[11px] tracking-widest uppercase">Logout</a>
            </div>
        </div>
    </header>

    <!-- Expanded Main Content Container -->
    <main class="flex-grow max-w-[96rem] w-full mx-auto px-4 sm:px-6 py-10 space-y-10 relative z-10">

        <!-- Page Title & Revenue Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Title Card -->
            <div class="glass-panel p-8 rounded-3xl shadow-2xl flex flex-col justify-center space-y-2">
                <span class="text-indigo-400 text-[10px] font-extrabold tracking-[0.25em] uppercase">Financial & Orders</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">Order Management</h1>
                <p class="text-neutral-400 text-xs tracking-wider">Monitor metrics, analyze sales performance, and manage fulfillment statuses.</p>
            </div>

            <!-- Today's Revenue Card -->
            <div class="glass-panel p-8 rounded-3xl shadow-2xl flex flex-col justify-between border-emerald-500/30">
                <div class="flex justify-between items-center">
                    <span class="text-emerald-400 text-[10px] font-extrabold tracking-[0.2em] uppercase">Today's Revenue</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                </div>
                <div>
                    <h3 class="text-3xl sm:text-4xl font-black font-mono text-white mt-2">$<?= number_format($today_revenue, 2) ?></h3>
                    <p class="text-neutral-400 text-[11px] tracking-wider uppercase mt-1">Delivered orders today</p>
                </div>
            </div>

            <!-- Total Revenue Card -->
            <div class="glass-panel p-8 rounded-3xl shadow-2xl flex flex-col justify-between border-indigo-500/30">
                <div class="flex justify-between items-center">
                    <span class="text-indigo-400 text-[10px] font-extrabold tracking-[0.2em] uppercase">Total Revenue (Delivered)</span>
                    <span class="text-indigo-400 font-bold text-xs">📈</span>
                </div>
                <div>
                    <h3 class="text-3xl sm:text-4xl font-black font-mono text-white mt-2">$<?= number_format($total_revenue, 2) ?></h3>
                    <p class="text-neutral-400 text-[11px] tracking-wider uppercase mt-1">Cumulative store earnings</p>
                </div>
            </div>
        </div>

        <!-- Advanced Charts Section (Grid of 2 Charts) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Chart 1: Orders Trend by Month -->
            <div class="glass-panel p-6 sm:p-8 rounded-3xl shadow-2xl space-y-4">
                <h2 class="text-xs font-extrabold uppercase tracking-widest text-white flex items-center gap-2">
                    <span>📈 Monthly Order Trends</span>
                </h2>
                <div class="relative h-72 w-full">
                    <canvas id="orderChart"></canvas>
                </div>
            </div>

            <!-- Chart 2: Best-Selling Products -->
            <div class="glass-panel p-6 sm:p-8 rounded-3xl shadow-2xl space-y-4">
                <h2 class="text-xs font-extrabold uppercase tracking-widest text-white flex items-center gap-2">
                    <span>🏆 Top 5 Best-Selling Products</span>
                </h2>
                <div class="relative h-72 w-full">
                    <canvas id="bestSellerChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Orders Table Section -->
        <div class="glass-panel p-6 sm:p-8 rounded-3xl shadow-2xl space-y-6">
            <h2 class="text-base font-extrabold uppercase tracking-widest text-white">Order Records</h2>
            
            <div class="overflow-x-auto table-scroll">
                <table class="min-w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-white/10 text-neutral-400 text-[11px] uppercase tracking-widest font-bold">
                            <th class="px-4 py-3.5">Order ID</th>
                            <th class="px-4 py-3.5">Products Ordered</th>
                            <th class="px-4 py-3.5">Total Price</th>
                            <th class="px-4 py-3.5">Delivery Address</th>
                            <th class="px-4 py-3.5">Status & Update</th>
                            <th class="px-4 py-3.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-xs">
                        <?php
                        $sql = "SELECT * FROM orders ORDER BY id DESC";
                        $result = $conn->query($sql);

                        if ($result && $result->num_rows > 0) {
                            while ($order = $result->fetch_assoc()) {
                                // Get product names for this order
                                $order_items_sql = "SELECT product_name FROM order_items WHERE order_id = ?";
                                $stmt = $conn->prepare($order_items_sql);
                                $stmt->bind_param("i", $order['id']);
                                $stmt->execute();
                                $order_items_result = $stmt->get_result();
                                $products = '';
                                while ($item = $order_items_result->fetch_assoc()) {
                                    $products .= $item['product_name'] . ', ';
                                }
                                $products = rtrim($products, ', ');
                                $stmt->close();

                                echo '
                                    <tr class="hover:bg-white/[0.02] transition">
                                        <td class="px-4 py-4 font-mono font-bold text-white">#' . htmlspecialchars($order['id'], ENT_QUOTES) . '</td>
                                        <td class="px-4 py-4 text-neutral-300 font-medium max-w-xs truncate" title="' . htmlspecialchars($products, ENT_QUOTES) . '">' . htmlspecialchars($products, ENT_QUOTES) . '</td>
                                        <td class="px-4 py-4 font-mono font-bold text-indigo-400">$' . number_format($order['total'], 2) . '</td>
                                        <td class="px-4 py-4 text-neutral-400 max-w-xs truncate" title="' . htmlspecialchars($order['address'], ENT_QUOTES) . '">' . htmlspecialchars($order['address'], ENT_QUOTES) . '</td>
                                        <td class="px-4 py-4">
                                            <form method="POST" action="" class="flex items-center gap-2">
                                                <select name="status" class="bg-black/50 border border-white/10 text-white rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-indigo-500 transition">
                                                    <option value="pending" ' . ($order['status'] == 'pending' ? 'selected' : '') . '>Pending</option>
                                                    <option value="delivered" ' . ($order['status'] == 'delivered' ? 'selected' : '') . '>Delivered</option>
                                                    <option value="canceled" ' . ($order['status'] == 'canceled' ? 'selected' : '') . '>Canceled</option>
                                                </select>
                                                <input type="hidden" name="order_id" value="' . $order['id'] . '">
                                                <button type="submit" name="update_status" class="bg-white/10 hover:bg-white hover:text-black text-white px-3 py-2 rounded-xl font-bold uppercase tracking-wider text-[10px] transition">Save</button>
                                            </form>
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <a href="view_orders.php?id=' . $order['id'] . '&action=delete" onclick="return confirm(\'Are you sure you want to delete this order?\')" class="bg-rose-500/10 border border-rose-500/20 text-rose-400 hover:bg-rose-500 hover:text-white px-3 py-2 rounded-xl font-bold uppercase tracking-wider text-[10px] transition inline-block">Delete</a>
                                        </td>
                                    </tr>
                                ';
                            }
                        } else {
                            echo '<tr><td colspan="6" class="text-center text-neutral-500 text-xs tracking-widest uppercase py-8">No orders found in database.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="text-center py-8 glass-panel border-t border-white/10 text-neutral-500 text-[11px] tracking-[0.2em] uppercase mt-12">
        <p>&copy; 2026 Thanh Buy 🛒 &mdash; All Rights Reserved</p>
    </footer>

    <!-- Chart Scripts -->
    <script>
        // Common Chart.js styling config for Dark Tesla UI
        Chart.defaults.color = '#a3a3a3';
        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";

        // 1. Monthly Order Trends Chart
        const months = <?php echo json_encode($months); ?>;
        const orderCounts = <?php echo json_encode($order_counts); ?>;

        const ctx1 = document.getElementById('orderChart').getContext('2d');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [{
                    label: 'Orders Count',
                    data: orderCounts,
                    backgroundColor: 'rgba(99, 102, 241, 0.25)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Best-Selling Products Chart
        const topProductNames = <?php echo json_encode($top_product_names); ?>;
        const topProductSales = <?php echo json_encode($top_product_sales); ?>;

        const ctx2 = document.getElementById('bestSellerChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: topProductNames,
                datasets: [{
                    data: topProductSales,
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(168, 85, 247, 0.8)'
                    ],
                    borderColor: 'rgba(14, 14, 16, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 10 } }
                    }
                }
            }
        });
    </script>

</body>

</html>