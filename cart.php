<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db_config.php';
$user_id = $_SESSION['user_id'];

// 1. Xử lý xóa sản phẩm khỏi giỏ hàng
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: cart.php");
    exit();
}

// 2. Xử lý làm sạch toàn bộ giỏ hàng
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: cart.php");
    exit();
}

// 3. Xử lý cập nhật số lượng sản phẩm (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    $id = filter_var($_POST['cart_item_id'], FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);

    if ($id && $quantity > 0) {
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $quantity, $id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: cart.php");
    exit();
}

// 4. Xử lý Thanh toán & Xác thực 2FA (Bước xác thực mã PIN/OTP trước khi đặt hàng)
$checkout_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_action'])) {
    $payment_method = trim($_POST['payment_method'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $otp_code = trim($_POST['otp_code'] ?? '');

    // Kiểm tra dữ liệu đầu vào cơ bản
    if (empty($payment_method) || empty($address)) {
        $checkout_error = "Please provide both delivery address and payment method.";
    } elseif (empty($otp_code)) {
        $checkout_error = "Please enter your 2FA Security PIN to confirm payment.";
    } elseif ($otp_code !== "1234") { // Giả lập mã PIN 2FA bảo mật của hệ thống (hoặc check từ bảng users)
        $checkout_error = "Invalid 2FA Security PIN. (Default test PIN: 1234)";
    } else {
        // Tính toán tổng tiền và lấy chi tiết giỏ hàng
        $total = 0;
        $product_details = [];

        $stmt_cart = $conn->prepare("SELECT * FROM cart WHERE user_id = ?");
        $stmt_cart->bind_param("i", $user_id);
        $stmt_cart->execute();
        $result_cart = $stmt_cart->get_result();

        if ($result_cart->num_rows > 0) {
            while ($row = $result_cart->fetch_assoc()) {
                $total += $row['product_price'] * $row['quantity'];
                $product_details[] = [
                    'product_name' => $row['product_name'],
                    'product_price' => $row['product_price'],
                    'quantity' => $row['quantity']
                ];
            }
            $stmt_cart->close();

            // Lưu đơn hàng vào bảng orders
            $status = 'Pending';
            $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total, status, payment_method, address) VALUES (?, ?, ?, ?, ?)");
            $stmt_order->bind_param("idsss", $user_id, $total, $status, $payment_method, $address);

            if ($stmt_order->execute()) {
                $order_id = $stmt_order->insert_id;
                $stmt_order->close();

                // Lưu chi tiết sản phẩm và trừ kho
                foreach ($product_details as $product) {
                    $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_name, product_price, quantity) VALUES (?, ?, ?, ?)");
                    $stmt_item->bind_param("isdi", $order_id, $product['product_name'], $product['product_price'], $product['quantity']);
                    $stmt_item->execute();
                    $stmt_item->close();

                    $stmt_stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE name = ?");
                    $stmt_stock->bind_param("is", $product['quantity'], $product['product_name']);
                    $stmt_stock->execute();
                    $stmt_stock->close();
                }

                // Xóa giỏ hàng
                $stmt_clear = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt_clear->bind_param("i", $user_id);
                $stmt_clear->execute();
                $stmt_clear->close();

                header("Location: user_dashboard.php?success=order_placed");
                exit();
            } else {
                $checkout_error = "Error processing your order. Please try again.";
            }
        } else {
            $checkout_error = "Your cart is empty.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Cart & Checkout - Thanh Buy 🛒</title>
    <link rel="icon" href="favicon.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .chat-scroll::-webkit-scrollbar {
            width: 5px;
        }
        .chat-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
        }
    </style>
</head>

<body class="bg-black text-white antialiased min-h-screen flex flex-col justify-between selection:bg-neutral-200 selection:text-black relative">
    
    <!-- Cinematic Background Glow -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[350px] bg-white/[0.03] blur-[140px] pointer-events-none rounded-full"></div>

    <!-- Header -->
    <header class="sticky top-0 z-40 backdrop-blur-xl bg-black/40 border-b border-white/10 transition-all">
        <div class="max-w-7xl mx-auto px-6 h-20 flex justify-between items-center">
            <div class="text-xl font-bold tracking-widest uppercase flex items-center gap-2 text-white">
                <a href="index.php">Thanh Buy 🛒</a>
            </div>

            <nav class="hidden md:flex items-center gap-8 font-medium text-neutral-400 text-sm tracking-wider uppercase">
                <a href="index.php" class="hover:text-white transition">Home</a>
                <a href="user_dashboard.php#products" class="hover:text-white transition">Catalog</a>
                <a href="user_dashboard.php#contact" class="hover:text-white transition">Support</a>
                <a href="cart.php" class="hover:text-white transition font-semibold text-white">Cart 🛒</a>
            </nav>

            <div>
                <a href="logout.php" class="bg-neutral-900 border border-white/20 text-white px-5 py-2.5 rounded-full font-medium hover:bg-white hover:text-black hover:border-white transition-all text-xs tracking-wider uppercase">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow max-w-4xl w-full mx-auto px-6 py-12 space-y-8 relative z-10">
        
        <div class="bg-neutral-950 p-8 sm:p-10 rounded-3xl border border-white/10 shadow-2xl">
            <div class="text-center mb-8">
                <span class="text-neutral-400 text-xs font-semibold tracking-widest uppercase mb-2 block">Secure Checkout</span>
                <h2 class="text-3xl font-extrabold text-white tracking-tight">Your Shopping Cart</h2>
            </div>

            <?php if (!empty($checkout_error)): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs tracking-wide uppercase text-center font-bold">
                    <?= htmlspecialchars($checkout_error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <!-- Cart Table -->
            <div class="overflow-x-auto rounded-2xl border border-white/10 bg-neutral-900 mb-8">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-black/60 text-neutral-400 uppercase tracking-widest text-[10px]">
                            <th class="p-4">Product Name</th>
                            <th class="p-4">Price (USD)</th>
                            <th class="p-4">Quantity</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-neutral-200">
                        <?php
                        $total = 0;
                        $stmt_view = $conn->prepare("SELECT * FROM cart WHERE user_id = ?");
                        $stmt_view->bind_param("i", $user_id);
                        $stmt_view->execute();
                        $result_view = $stmt_view->get_result();

                        if ($result_view->num_rows > 0) {
                            while ($row = $result_view->fetch_assoc()) {
                                $total += $row['product_price'] * $row['quantity'];
                        ?>
                                <tr class="hover:bg-white/[0.02] transition">
                                    <td class="p-4 font-semibold text-white"><?= htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="p-4 text-neutral-400"><?= number_format($row['product_price'], 2) ?> USD</td>
                                    <td class="p-4">
                                        <form method="POST" action="cart.php" class="flex items-center gap-2">
                                            <input type="hidden" name="cart_item_id" value="<?= intval($row['id']) ?>">
                                            <input type="number" name="quantity" value="<?= intval($row['quantity']) ?>" min="1" class="w-16 p-2 bg-black border border-white/10 rounded-xl text-center text-white focus:outline-none focus:border-white/40">
                                            <button type="submit" name="update_quantity" class="bg-neutral-800 border border-white/10 text-white px-3 py-2 rounded-xl hover:bg-white hover:text-black transition text-[10px] uppercase font-bold">Update</button>
                                        </form>
                                    </td>
                                    <td class="p-4 text-right">
                                        <a href="cart.php?id=<?= intval($row['id']) ?>" class="bg-rose-950/80 border border-rose-500/40 text-rose-300 px-3 py-2 rounded-xl hover:bg-rose-900 transition text-[10px] font-bold uppercase tracking-wider">Delete</a>
                                    </td>
                                </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="4" class="text-center py-10 text-neutral-500 tracking-wider uppercase font-medium">Your cart is empty!</td></tr>';
                        }
                        $stmt_view->close();
                        ?>
                        <tr class="bg-black/40 font-bold">
                            <td colspan="3" class="p-4 text-right text-neutral-400 uppercase tracking-widest text-[10px]">Total Amount</td>
                            <td class="p-4 text-right text-white text-sm"><?= number_format($total, 2); ?> USD</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Checkout Form with 2FA & Multi-Payment Options -->
            <form method="POST" action="cart.php" class="space-y-6">
                <input type="hidden" name="checkout_action" value="1">
                
                <!-- Address Input -->
                <div>
                    <label for="address" class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-2">Delivery Address</label>
                    <input type="text" id="address" name="address" required placeholder="Enter your full street address" 
                           class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-white/40 transition text-xs tracking-wide">
                </div>

                <!-- Multi-Payment Methods Selection -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-3">Select Secure Payment Method</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <label class="flex items-center gap-3 p-3.5 bg-neutral-900 border border-white/10 rounded-2xl cursor-pointer hover:border-white/30 transition">
                            <input type="radio" name="payment_method" value="card" required class="accent-white">
                            <span class="text-xs font-semibold tracking-wide text-white">Credit / Debit Card</span>
                        </label>
                        <label class="flex items-center gap-3 p-3.5 bg-neutral-900 border border-white/10 rounded-2xl cursor-pointer hover:border-white/30 transition">
                            <input type="radio" name="payment_method" value="apple_pay" class="accent-white">
                            <span class="text-xs font-semibold tracking-wide text-white">Apple Pay</span>
                        </label>
                        <label class="flex items-center gap-3 p-3.5 bg-neutral-900 border border-white/10 rounded-2xl cursor-pointer hover:border-white/30 transition">
                            <input type="radio" name="payment_method" value="crypto" class="accent-white">
                            <span class="text-xs font-semibold tracking-wide text-white">Crypto (USDT)</span>
                        </label>
                        <label class="flex items-center gap-3 p-3.5 bg-neutral-900 border border-white/10 rounded-2xl cursor-pointer hover:border-white/30 transition">
                            <input type="radio" name="payment_method" value="bkash" class="accent-white">
                            <span class="text-xs font-semibold tracking-wide text-white">Bkash</span>
                        </label>
                        <label class="flex items-center gap-3 p-3.5 bg-neutral-900 border border-white/10 rounded-2xl cursor-pointer hover:border-white/30 transition">
                            <input type="radio" name="payment_method" value="bank" class="accent-white">
                            <span class="text-xs font-semibold tracking-wide text-white">Direct Bank Transfer</span>
                        </label>
                        <label class="flex items-center gap-3 p-3.5 bg-neutral-900 border border-white/10 rounded-2xl cursor-pointer hover:border-white/30 transition">
                            <input type="radio" name="payment_method" value="cod" class="accent-white">
                            <span class="text-xs font-semibold tracking-wide text-white">Cash on Delivery</span>
                        </label>
                    </div>
                </div>

                <!-- 2FA Authentication PIN Input -->
                <div class="p-5 rounded-2xl bg-black/50 border border-emerald-500/30 space-y-3">
                    <div class="flex items-center justify-between">
                        <label for="otp_code" class="text-xs font-bold uppercase tracking-widest text-emerald-400 flex items-center gap-2">
                            <span>🔒 2FA Security PIN Required</span>
                        </label>
                        <span class="text-[10px] text-neutral-500 uppercase tracking-widest">Test PIN: 1234</span>
                    </div>
                    <p class="text-[11px] text-neutral-400">Enter your 4-digit security PIN to authorize this transaction securely.</p>
                    <input type="password" id="otp_code" name="otp_code" maxlength="4" placeholder="••••" required
                           class="w-full sm:w-48 px-4 py-3 bg-neutral-900 border border-white/10 rounded-xl text-white text-center font-mono tracking-widest focus:outline-none focus:border-emerald-500/50 transition">
                </div>

                <!-- Checkout & Clear Cart Actions -->
                <div class="flex flex-col sm:flex-row gap-4 pt-4">
                    <button type="submit" class="flex-1 bg-white text-black font-semibold py-4 rounded-2xl shadow-lg hover:bg-neutral-200 transition-all text-xs uppercase tracking-widest">Confirm & Pay Securely</button>
                    <a href="cart.php?action=clear" class="bg-neutral-900 border border-white/20 text-neutral-400 hover:text-white font-semibold py-4 px-8 rounded-2xl text-center hover:bg-neutral-800 transition-all text-xs uppercase tracking-widest">Clear Cart</a>
                </div>
            </form>
        </div>

    </main>

    <!-- Footer -->
    <footer class="text-center py-8 bg-black border-t border-white/10 text-neutral-500 text-xs tracking-widest uppercase mt-12">
        <p>&copy; 2026 Thanh Buy 🛒 &mdash; All Rights Reserved</p>
    </footer>
</body>

</html>