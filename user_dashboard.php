<?php
// Bắt đầu session và kiểm tra đăng nhập an toàn
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

include 'db_config.php';
$user_id = $_SESSION['user_id'];

// =======================
// CANCEL ORDER
// =======================
if (isset($_GET['cancel_order'])) {
    $order_id = filter_var($_GET['cancel_order'], FILTER_VALIDATE_INT);
    if ($order_id) {
        $update = $conn->prepare("
            UPDATE orders 
            SET status = 'Canceled' 
            WHERE id = ? AND user_id = ? AND LOWER(status) = 'pending'
        ");
        $update->bind_param("ii", $order_id, $user_id);
        
        if ($update->execute() && $update->affected_rows > 0) {
            $userQuery = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
            $userQuery->bind_param("i", $user_id);
            $userQuery->execute();
            $userData = $userQuery->get_result()->fetch_assoc();
            $username = $userData['full_name'] ?? 'Customer';

            $message = "🔔 " . $username . " canceled Order #" . $order_id;
            $notify = $conn->prepare("
                INSERT INTO notifications (user_id, order_id, message, is_read) 
                VALUES (?, ?, ?, 0)
            ");
            $notify->bind_param("iis", $user_id, $order_id, $message);
            $notify->execute();
            $notify->close();
            $userQuery->close();
        }
        $update->close();
    }
    header("Location: user_dashboard.php");
    exit();
}

// Lấy thông tin cá nhân
$stmt_user = $conn->prepare("SELECT full_name, email, phone, address, profile_picture FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - Thanh Buy 🛒</title>
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
        .chat-scroll::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
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
                <a href="#products" class="hover:text-white transition">Catalog</a>
                <a href="#contact" class="hover:text-white transition">Support</a>
                <a href="cart.php" class="hover:text-white transition flex items-center gap-1 font-semibold text-white">Cart 🛒</a>
            </nav>

            <div>
                <a href="logout.php" class="bg-neutral-900 border border-white/20 text-white px-5 py-2.5 rounded-full font-medium hover:bg-white hover:text-black hover:border-white transition-all text-xs tracking-wider uppercase">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content Section -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-6 py-12 space-y-12 relative z-10">
        
        <!-- Welcome Section -->
        <div class="bg-neutral-950 border border-white/10 p-8 sm:p-12 rounded-3xl shadow-2xl relative overflow-hidden">
            <div class="absolute right-0 top-0 w-96 h-96 bg-white/[0.02] rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative z-10 max-w-2xl">
                <span class="inline-block text-neutral-400 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-widest mb-4 border border-white/10">
                    Member Dashboard
                </span>
                <h1 class="text-3xl sm:text-5xl font-extrabold mb-4 tracking-tight text-white">Welcome, <?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>!</h1>
                <p class="text-neutral-400 text-base mb-6 leading-relaxed">Your secure command center for managing high-end tech orders and exploring minimalist gear.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- User Info Section -->
            <div class="bg-neutral-950 p-8 rounded-3xl border border-white/10 shadow-2xl flex flex-col justify-between">
                <div>
                    <h2 class="text-lg font-bold text-white mb-6 pb-4 border-b border-white/10 tracking-wide uppercase text-xs">Profile Overview</h2>
                    <div class="flex flex-col items-center mb-6">
                        <img src="<?php echo htmlspecialchars(!empty($user['profile_picture']) ? $user['profile_picture'] : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=300&auto=format&fit=crop', ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="w-28 h-28 rounded-full object-cover shadow-2xl border-2 border-white/20 mb-4">
                        <h3 class="text-lg font-bold text-white tracking-wide"><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <span class="text-[10px] text-emerald-400 font-semibold bg-emerald-950/60 border border-emerald-500/30 px-3 py-1 rounded-full mt-2 uppercase tracking-widest">Verified Account</span>
                    </div>
                    <div class="space-y-4 text-xs text-neutral-400 mb-6 tracking-wide">
                        <p class="flex justify-between border-b border-white/5 pb-3"><strong class="text-white uppercase">Email:</strong> <span class="text-neutral-300"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                        <p class="flex justify-between border-b border-white/5 pb-3"><strong class="text-white uppercase">Phone:</strong> <span class="text-neutral-300"><?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                        <p class="flex justify-between"><strong class="text-white uppercase">Location:</strong> <span class="text-right text-neutral-300"><?php echo htmlspecialchars($user['address'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                    </div>
                </div>
                <a href="profile.php" class="w-full text-center bg-white text-black font-semibold py-3.5 rounded-2xl shadow-lg hover:bg-neutral-200 transition-all text-xs tracking-wider uppercase">Edit Profile</a>
            </div>

            <!-- Purchase History -->
            <div class="lg:col-span-2 bg-neutral-950 p-8 rounded-3xl border border-white/10 shadow-2xl">
                <h2 class="text-lg font-bold text-white mb-6 pb-4 border-b border-white/10 tracking-wide uppercase text-xs flex items-center justify-between">
                    <span>Purchase History 📦</span>
                </h2>

                <div class="space-y-6 max-h-[600px] overflow-y-auto chat-scroll pr-2">
                    <?php
                    $stmt_orders = $conn->prepare("SELECT id, status, total, payment_method, address, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC");
                    $stmt_orders->bind_param("i", $user_id);
                    $stmt_orders->execute();
                    $orders_result = $stmt_orders->get_result();

                    if ($orders_result->num_rows > 0) {
                        while ($order = $orders_result->fetch_assoc()) {
                            $order_id = $order['id'];
                            $status = strtolower($order['status']);
                    ?>
                        <div class="border border-white/10 rounded-2xl p-6 bg-black/40 hover:border-white/20 transition">
                            <div class="flex flex-wrap justify-between items-center gap-4 mb-4 pb-4 border-b border-white/10">
                                <div>
                                    <span class="text-[10px] font-bold uppercase text-neutral-500 block tracking-widest">Order ID</span>
                                    <span class="text-base font-bold text-white">#<?= $order_id; ?></span>
                                </div>
                                <div>
                                    <?php
                                    if ($status == "pending") {
                                        echo "<span class='bg-amber-950/60 border border-amber-500/30 text-amber-400 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest'>Pending</span>";
                                    } elseif ($status == "delivered") {
                                        echo "<span class='bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest'>Delivered</span>";
                                    } elseif ($status == "canceled") {
                                        echo "<span class='bg-rose-950/60 border border-rose-500/30 text-rose-400 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest'>Canceled</span>";
                                    } else {
                                        echo "<span class='bg-neutral-900 border border-white/10 text-neutral-400 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest'>" . htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') . "</span>";
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-neutral-400 mb-6">
                                <p><strong>Date:</strong> <?= date("d M Y H:i", strtotime($order['created_at'])) ?></p>
                                <p><strong>Payment:</strong> <?= htmlspecialchars($order['payment_method'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="sm:col-span-2"><strong>Shipping Address:</strong> <?= htmlspecialchars($order['address'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>

                            <div class="overflow-x-auto rounded-xl border border-white/10 bg-neutral-900 mb-4">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-black/60 text-neutral-400 uppercase tracking-widest text-[10px]">
                                            <th class="p-3">Product</th>
                                            <th class="p-3">Price</th>
                                            <th class="p-3">Qty</th>
                                            <th class="p-3 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5 text-neutral-200">
                                        <?php
                                        $stmt_items = $conn->prepare("SELECT product_name, product_price, quantity FROM order_items WHERE order_id = ?");
                                        $stmt_items->bind_param("i", $order_id);
                                        $stmt_items->execute();
                                        $items_result = $stmt_items->get_result();
                                        while ($item = $items_result->fetch_assoc()) {
                                        ?>
                                            <tr>
                                                <td class="p-3 font-medium text-white"><?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="p-3 text-neutral-400"><?= number_format($item['product_price'], 2) ?> USD</td>
                                                <td class="p-3 text-neutral-400"><?= intval($item['quantity']) ?></td>
                                                <td class="p-3 text-right font-bold text-white"><?= number_format($item['product_price'] * $item['quantity'], 2) ?> USD</td>
                                            </tr>
                                        <?php 
                                        }
                                        $stmt_items->close();
                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex flex-wrap justify-between items-center pt-2">
                                <div class="text-sm font-bold text-neutral-400">
                                    Total: <span class="text-white"><?= number_format($order['total'], 2) ?> USD</span>
                                </div>

                                <?php if ($status == "pending") { ?>
                                    <a href="user_dashboard.php?cancel_order=<?= $order_id; ?>"
                                       onclick="return confirm('Are you sure you want to cancel this order?')"
                                       class="bg-rose-950/80 border border-rose-500/40 text-rose-300 text-[10px] font-bold px-4 py-2 rounded-xl hover:bg-rose-900 transition-all uppercase tracking-wider">
                                        Cancel Order
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    <?php 
                        }
                    } else {
                        echo "<div class='text-center py-12 text-neutral-500 text-xs tracking-wider uppercase font-medium'>You have not purchased anything yet.</div>";
                    }
                    $stmt_orders->close();
                    ?>
                </div>
            </div>
            
        </div>
        <?php
if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin') {
    $user_id = $_SESSION['user_id'];
    
    // Xử lý khi khách hàng gửi tin nhắn phản hồi cho Admin
    if (isset($_POST['send_reply'])) {
        $reply_msg = trim($_POST['message']);
        $stmt_r = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, 1, 'Customer Reply', ?)");
        $stmt_r->bind_param("is", $user_id, $reply_msg);
        if($stmt_r->execute()) {
            echo '<script>alert("Reply sent to admin successfully!"); window.location.href="index.php";</script>';
        }
        $stmt_r->close();
    }
    
    // Truy vấn tin nhắn (Lưu ý: Sắp xếp theo ASC để tin cũ ở trên, tin mới trượt xuống dưới cùng)
    $query = "SELECT m.*, COALESCE(u.full_name, 'Admin') as sender_name 
              FROM messages m 
              LEFT JOIN users u ON m.sender_id = u.id 
              WHERE m.receiver_id = $user_id OR m.receiver_id = 0 
              ORDER BY m.id ASC";
              
    $my_msgs = $conn->query($query);
?>
<div class="glass-panel p-6 rounded-3xl mt-10 space-y-4 max-w-2xl mx-auto border border-white/10 shadow-2xl backdrop-blur-md bg-black/40">
    <div class="flex items-center justify-between border-b border-white/5 pb-3">
        <h3 class="text-xs font-extrabold uppercase tracking-widest text-indigo-400 flex items-center gap-2">
            <span>📬</span> Admin Events & Direct Messages
        </h3>
        <span class="text-[10px] text-neutral-500">Live Chat Box</span>
    </div>
    
    <!-- Khung chứa danh sách tin nhắn có thể cuộn -->
    <div id="chat-messages-container" class="space-y-3 max-h-60 overflow-y-auto pr-2 scrollbar-thin scrollbar-thumb-white/10">
        <?php
        if ($my_msgs && $my_msgs->num_rows > 0) {
            while($m = $my_msgs->fetch_assoc()) {
                // Phân biệt màu sắc tin nhắn giữa Khách gửi và Admin gửi để trực quan hơn
                $is_me = ($m['sender_id'] == $user_id);
                $bg_box = $is_me ? 'bg-indigo-950/40 border-indigo-500/20 ml-6' : 'bg-black/60 border-white/5 mr-6';
                
                echo '<div class="'.$bg_box.' p-4 rounded-2xl border text-xs space-y-1 transition-all">';
                echo '<div class="flex justify-between items-center text-[10px] text-neutral-400">';
                echo '<span class="font-bold text-indigo-300">From: ' . htmlspecialchars($m['sender_name']) . '</span>';
                echo '<span>' . ($m['created_at'] ?? '') . '</span>';
                echo '</div>';
                echo '<div class="font-bold text-white text-sm">' . htmlspecialchars($m['subject'] ?? 'Notification') . '</div>';
                echo '<div class="text-neutral-300 leading-relaxed break-words">' . nl2br(htmlspecialchars($m['message'])) . '</div>';
                echo '</div>';
            }
        } else {
            echo '<p class="text-neutral-500 text-xs italic text-center py-6">No notifications or messages found yet.</p>';
        }
        ?>
    </div>

    <!-- Form khách hàng phản hồi lại cho Admin -->
    <form method="POST" action="" class="space-y-3 pt-3 border-t border-white/5">
        <textarea name="message" rows="2" placeholder="Type a message or response to admin..." required class="w-full px-4 py-3 bg-black/60 border border-white/10 rounded-xl text-white text-xs focus:outline-none focus:border-indigo-500 resize-none transition"></textarea>
        <div class="flex justify-end">
            <button type="submit" name="send_reply" class="bg-white text-black font-extrabold px-6 py-2.5 rounded-xl uppercase text-xs tracking-wider hover:bg-indigo-500 hover:text-white transition shadow-lg">Send Reply to Admin</button>
        </div>
    </form>
</div>

<!-- JavaScript giúp tự động cuộn xuống tin nhắn mới nhất khi vừa load trang -->
<script>
    const chatContainer = document.getElementById('chat-messages-container');
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
</script>
<?php } ?>

        <!-- Products Section with Filter & Search -->
        <div id="products" class="bg-neutral-950 p-8 sm:p-12 rounded-3xl border border-white/10 shadow-2xl">
            <div class="text-center max-w-xl mx-auto mb-10">
                <span class="text-neutral-400 text-xs font-semibold tracking-widest uppercase mb-3 block">Catalog</span>
                <h2 class="text-3xl font-extrabold text-white tracking-tight">Our Bestsellers</h2>
            </div>

            <?php
            $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
            $selected_category = isset($_GET['category']) ? trim($_GET['category']) : '';

            $cat_result = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != ''");
            ?>
            <form method="GET" action="user_dashboard.php#products" class="flex flex-col sm:flex-row gap-4 mb-10 max-w-3xl mx-auto">
                <div class="flex-grow">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search products..." 
                           class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-white/40 transition-all text-xs tracking-wide">
                </div>
                <div class="w-full sm:w-56">
                    <select name="category" class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white focus:outline-none focus:border-white/40 transition-all cursor-pointer text-xs tracking-wide">
                        <option value="" class="bg-neutral-900">All Categories</option>
                        <?php
                        if ($cat_result && $cat_result->num_rows > 0) {
                            while ($cat = $cat_result->fetch_assoc()) {
                                $cat_val = $cat['category'];
                                $selected = ($selected_category === $cat_val) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($cat_val, ENT_QUOTES, 'UTF-8') . '" ' . $selected . ' class="bg-neutral-900">' . htmlspecialchars($cat_val, ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="bg-white text-black font-semibold px-6 py-3.5 rounded-2xl shadow-lg hover:bg-neutral-200 transition-all text-xs uppercase tracking-wider">
                    Filter
                </button>
            </form>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
                <?php
                $sql = "SELECT id, name, price, image FROM products WHERE 1=1";
                $params = [];
                $types = "";

                if (!empty($search_query)) {
                    $sql .= " AND name LIKE ?";
                    $params[] = "%" . $search_query . "%";
                    $types .= "s";
                }

                if (!empty($selected_category)) {
                    $sql .= " AND category = ?";
                    $params[] = $selected_category;
                    $types .= "s";
                }

                $stmt_prod = $conn->prepare($sql);
                if (!empty($params)) {
                    $stmt_prod->bind_param($types, ...$params);
                }
                $stmt_prod->execute();
                $prod_result = $stmt_prod->get_result();

                if ($prod_result->num_rows > 0) {
                    while ($row = $prod_result->fetch_assoc()) {
                        echo '
                            <div class="group bg-black/40 border border-white/10 rounded-2xl overflow-hidden hover:border-white/40 transition-all duration-500 flex flex-col justify-between">
                                <div class="aspect-square overflow-hidden bg-neutral-900">
                                    <img src="' . htmlspecialchars($row['image'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 opacity-90 group-hover:opacity-100">
                                </div>
                                <div class="p-6 flex flex-col flex-grow justify-between">
                                    <div>
                                        <h3 class="text-sm font-semibold text-white mb-2 tracking-wide line-clamp-1">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</h3>
                                        <p class="text-white font-bold text-base mb-6 tracking-wider">' . number_format($row['price'], 2) . ' <span class="text-xs font-normal text-neutral-500">USD</span></p>
                                    </div>
                                    <form method="POST" action="add_to_cart.php">
                                        <input type="hidden" name="product_id" value="' . intval($row['id']) . '">
                                        <input type="hidden" name="product_name" value="' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '">
                                        <input type="hidden" name="product_price" value="' . floatval($row['price']) . '">
                                        <button type="submit" class="w-full bg-neutral-900 border border-white/20 text-white font-semibold py-3 rounded-xl hover:bg-white hover:text-black hover:border-white transition-all text-xs uppercase tracking-widest">Add to Cart</button>
                                    </form>
                                </div>
                            </div>
                        ';
                    }
                } else {
                    echo "<p class='col-span-full text-center text-neutral-500 font-medium py-10 text-xs uppercase tracking-widest'>No products found matching your criteria!</p>";
                }
                $stmt_prod->close();
                ?>
            </div>
        </div>



        

        <!-- Contact Us Section -->
        <div id="contact" class="bg-neutral-950 p-8 sm:p-12 rounded-3xl border border-white/10 shadow-2xl max-w-3xl mx-auto w-full">
            <div class="text-center mb-10">
                <span class="text-neutral-400 text-xs font-semibold tracking-widest uppercase mb-2 block">Support</span>
                <h2 class="text-3xl font-extrabold text-white tracking-tight">Contact Us</h2>
            </div>
            <form action="noaction.php" method="POST" class="space-y-4">
                <div>
                    <input type="text" name="name" placeholder="Enter your name" required value="<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                           class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-white/40 transition-all text-xs tracking-wide">
                </div>
                <div>
                    <input type="email" name="email" placeholder="Enter your Email ID" required value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>"
                           class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-white/40 transition-all text-xs tracking-wide">
                </div>
                <div>
                    <input type="text" name="phone" placeholder="Enter your Phone Number" required value="<?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>"
                           class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-white/40 transition-all text-xs tracking-wide">
                </div>
                <div>
                    <textarea name="message" placeholder="Enter your Message" rows="4" required 
                              class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-white/40 transition-all resize-none text-xs tracking-wide"></textarea>
                </div>
                <button type="submit" class="w-full bg-white text-black font-semibold py-4 rounded-2xl shadow-lg hover:bg-neutral-200 transition-all text-xs uppercase tracking-widest">Submit Message</button>
            </form>
        </div>

    </main>

    <!-- TESLA-STYLE AI ASSISTANT WIDGET -->
    <div class="fixed bottom-6 right-6 z-50">
        <button id="ai-toggle-btn" onclick="toggleAIChat()" class="bg-neutral-900 border border-white/20 text-white w-14 h-14 rounded-full flex items-center justify-center shadow-2xl hover:bg-white hover:text-black transition-all duration-300 group">
            <svg class="w-6 h-6 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
            </svg>
        </button>

        <div id="ai-chat-box" class="hidden absolute bottom-20 right-0 w-[360px] sm:w-[400px] h-[520px] bg-neutral-950/95 backdrop-blur-2xl border border-white/15 rounded-3xl shadow-2xl flex flex-col overflow-hidden transition-all duration-300">
            <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between bg-black/40">
                <div class="flex items-center gap-3">
                    <div class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></div>
                    <span class="text-xs font-bold uppercase tracking-widest text-white">Thanh AI (Gemini Powered)</span>
                </div>
                <button onclick="toggleAIChat()" class="text-neutral-400 hover:text-white text-sm font-semibold">✕</button>
            </div>

            <div id="chat-messages" class="flex-1 p-6 overflow-y-auto chat-scroll flex flex-col gap-4 text-sm">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-white/10 border border-white/20 flex items-center justify-center text-xs text-white shrink-0 font-bold">AI</div>
                    <div class="bg-neutral-900 border border-white/10 p-4 rounded-2xl rounded-tl-none text-neutral-200 leading-relaxed max-w-[80%] text-xs tracking-wide">
                        Hello <?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>! I'm connected via Gemini API. How can I assist you with your orders or gear today?
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-white/10 bg-black/40 flex items-center gap-2">
                <input type="text" id="user-input" placeholder="Ask Gemini AI..." onkeypress="handleKeyPress(event)" class="flex-1 bg-neutral-900 border border-white/10 rounded-full px-4 py-3 text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-white/40 transition-all">
                <button onclick="sendMessage()" id="send-btn" class="bg-white text-black w-11 h-11 rounded-full flex items-center justify-center font-bold hover:bg-neutral-200 transition-all shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    

    <!-- Footer -->
    <footer class="text-center py-8 bg-black border-t border-white/10 text-neutral-500 text-xs tracking-widest uppercase mt-12">
        <p>&copy; 2026 Thanh Buy 🛒 &mdash; All Rights Reserved</p>
    </footer>
    

    <!-- Chat JavaScript -->
    <script>
        function toggleAIChat() {
            const chatBox = document.getElementById('ai-chat-box');
            chatBox.classList.toggle('hidden');
        }

        function handleKeyPress(event) {
            if (event.key === 'Enter') {
                sendMessage();
            }
        }

        async function sendMessage() {
            const inputField = document.getElementById('user-input');
            const messageText = inputField.value.trim();
            if (!messageText) return;

            const chatMessages = document.getElementById('chat-messages');

            const userHtml = `
                <div class="flex items-start gap-3 justify-end">
                    <div class="bg-white text-black p-4 rounded-2xl rounded-tr-none leading-relaxed max-w-[80%] font-medium text-xs tracking-wide">
                        ${escapeHtml(messageText)}
                    </div>
                </div>
            `;
            chatMessages.insertAdjacentHTML('beforeend', userHtml);
            inputField.value = '';
            chatMessages.scrollTop = chatMessages.scrollHeight;

            const loadingId = 'loading-' + Date.now();
            const loadingHtml = `
                <div id="${loadingId}" class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-white/10 border border-white/20 flex items-center justify-center text-xs text-white shrink-0 font-bold">AI</div>
                    <div class="bg-neutral-900 border border-white/10 p-4 rounded-2xl rounded-tl-none text-neutral-400 italic text-xs flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 bg-neutral-400 rounded-full animate-bounce"></span>
                        <span class="w-1.5 h-1.5 bg-neutral-400 rounded-full animate-bounce [animation-delay:0.2s]"></span>
                        <span class="w-1.5 h-1.5 bg-neutral-400 rounded-full animate-bounce [animation-delay:0.4s]"></span>
                    </div>
                </div>
            `;
            chatMessages.insertAdjacentHTML('beforeend', loadingHtml);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            try {
                const response = await fetch('gemini_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: messageText })
                });

                const data = await response.json();
                document.getElementById(loadingId).remove();

                const aiHtml = `
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-white/10 border border-white/20 flex items-center justify-center text-xs text-white shrink-0 font-bold">AI</div>
                        <div class="bg-neutral-900 border border-white/10 p-4 rounded-2xl rounded-tl-none text-neutral-200 leading-relaxed max-w-[80%] text-xs tracking-wide">
                            ${escapeHtml(data.reply)}
                        </div>
                    </div>
                `;
                chatMessages.insertAdjacentHTML('beforeend', aiHtml);
                chatMessages.scrollTop = chatMessages.scrollHeight;

            } catch (error) {
                document.getElementById(loadingId).remove();
                const errHtml = `
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-white/10 border border-white/20 flex items-center justify-center text-xs text-white shrink-0 font-bold">AI</div>
                        <div class="bg-neutral-900 border border-white/10 p-4 rounded-2xl rounded-tl-none text-red-400 leading-relaxed max-w-[80%] text-xs tracking-wide">
                            Connection error. Please try again.
                        </div>
                    </div>
                `;
                chatMessages.insertAdjacentHTML('beforeend', errHtml);
            }
        }

        function escapeHtml(text) {
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        }
    </script>
</body>

</html>