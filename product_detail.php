<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_config.php';

// Lấy ID sản phẩm từ URL và bảo mật số nguyên
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

$msg = "";
$error = "";

// Truy vấn thông tin sản phẩm
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Product not found!'); window.location.href='index.php';</script>";
    exit();
}
$p = $result->fetch_assoc();

// Tự động tạo bảng 'reviews' nếu chưa tồn tại
$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL DEFAULT 5,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Kiểm tra xem người dùng đăng nhập đã từng mua sản phẩm này chưa
$can_review = false;
$has_reviewed = false;

if ($user_id > 0) {
    // Kiểm tra trong bảng order_items & orders
    $check_order = $conn->prepare("
        SELECT oi.id 
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.user_id = ? AND oi.product_id = ? AND LOWER(o.status) IN ('completed', 'delivered')
    ");
    if ($check_order) {
        $check_order->bind_param("ii", $user_id, $product_id);
        $check_order->execute();
        if ($check_order->get_result()->num_rows > 0) {
            $can_review = true;
        }
        $check_order->close();
    }

    // Kiểm tra xem đã gửi đánh giá cho sản phẩm này chưa
    $check_rev = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
    if ($check_rev) {
        $check_rev->bind_param("ii", $user_id, $product_id);
        $check_rev->execute();
        if ($check_rev->get_result()->num_rows > 0) {
            $has_reviewed = true;
        }
        $check_rev->close();
    }
}

// Xử lý gửi đánh giá ngay tại trang chi tiết
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if ($user_id === 0) {
        $error = "Please login to submit a review.";
    } elseif (!$can_review) {
        $error = "You can only review products from completed orders.";
    } elseif ($has_reviewed) {
        $error = "You have already reviewed this product.";
    } else {
        $rating = intval($_POST['rating']);
        $comment = trim($_POST['comment']);

        if (!empty($comment)) {
            $stmt_ins = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt_ins->bind_param("iiis", $product_id, $user_id, $rating, $comment);
            if ($stmt_ins->execute()) {
                $msg = "Thank you for your review!";
                $has_reviewed = true;
            } else {
                $error = "Failed to save review.";
            }
            $stmt_ins->close();
        } else {
            $error = "Review comment cannot be empty.";
        }
    }
}

// Lấy danh sách đánh giá từ CSDL
$reviews_result = null;
$reviews_query = "SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.id DESC";
$stmt_rev = $conn->prepare($reviews_query);
if ($stmt_rev) {
    $stmt_rev->bind_param("i", $product_id);
    $stmt_rev->execute();
    $reviews_result = $stmt_rev->get_result();
}

// Tính số lượng đã bán giả định nếu cột sold chưa có
$sold_count = isset($p['sold']) ? $p['sold'] : (15 + ($p['id'] * 4));
$has_sale = !is_null($p['sale_price']) && $p['sale_price'] < $p['price'];
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($p['name']); ?> - Thanh Buy 🛒</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-black text-white antialiased selection:bg-neutral-200 selection:text-black">

    <!-- Header -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-black/60 backdrop-blur-xl border-b border-white/10">
        <div class="max-w-7xl mx-auto px-6 h-20 flex justify-between items-center">
            <a href="index.php" class="text-xl font-bold tracking-widest uppercase flex items-center gap-2 text-white">
                <span>Thanh Buy</span> <span class="text-lg">🛒</span>
            </a>
            <div class="flex items-center gap-4">
                <?php if ($user_id > 0): ?>
                    <a href="user_dashboard.php" class="text-xs text-neutral-400 hover:text-white uppercase tracking-wider transition">Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="text-xs text-neutral-400 hover:text-white uppercase tracking-wider transition">Login</a>
                <?php endif; ?>
                <a href="index.php" class="bg-white/10 hover:bg-white hover:text-black text-white px-5 py-2 rounded-full text-xs font-bold uppercase tracking-wider transition">← Back to Store</a>
            </div>
        </div>
    </header>

    <!-- Main Detail Section -->
    <main class="max-w-7xl mx-auto px-6 pt-32 pb-24">
        
        <?php if(!empty($msg)): ?>
            <div class="mb-8 p-4 bg-emerald-950/60 border border-emerald-500/30 text-emerald-300 rounded-2xl text-xs font-medium"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <?php if(!empty($error)): ?>
            <div class="mb-8 p-4 bg-rose-950/60 border border-rose-500/30 text-rose-300 rounded-2xl text-xs font-medium"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
            
            <!-- Cột Hình Ảnh -->
            <div class="space-y-4">
                <div class="relative w-full aspect-square bg-neutral-900 border border-white/10 rounded-3xl overflow-hidden cursor-pointer group shadow-2xl" onclick="openLightbox('<?php echo htmlspecialchars($p['image']); ?>')">
                    <img id="main-product-img" src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500">
                    <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                        <span class="bg-black/70 backdrop-blur-md text-white text-xs font-bold px-4 py-2 rounded-full uppercase tracking-wider border border-white/20">🔍 Click to Zoom</span>
                    </div>
                    <?php if($has_sale): ?>
                        <span class="absolute top-4 right-4 bg-rose-500 text-white text-xs font-extrabold px-3.5 py-1.5 rounded-full uppercase tracking-wider shadow-lg">Sale Active</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cột Thông Tin Sản Phẩm -->
            <div class="space-y-6">
                <div>
                    <div class="flex items-center gap-3 text-xs text-neutral-400 mb-2">
                        <span class="text-emerald-400 font-bold">★ 4.9 Rating</span>
                        <span>•</span>
                        <span>Already Sold: <strong class="text-white"><?php echo $sold_count; ?> units</strong></span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight"><?php echo htmlspecialchars($p['name']); ?></h1>
                </div>

                <!-- Giá -->
                <div class="flex items-center gap-4 py-4 border-y border-white/10">
                    <?php if($has_sale): ?>
                        <div>
                            <span class="line-through text-neutral-500 text-sm font-mono">$<?php echo number_format($p['price'], 2); ?></span>
                            <div class="text-rose-400 font-extrabold font-mono text-3xl">$<?php echo number_format($p['sale_price'], 2); ?> USD</div>
                        </div>
                    <?php else: ?>
                        <div>
                            <span class="text-neutral-500 text-xs uppercase tracking-wider block">Price</span>
                            <div class="text-indigo-400 font-extrabold font-mono text-3xl">$<?php echo number_format($p['price'], 2); ?> USD</div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mô tả -->
                <div class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-neutral-400">Description</h3>
                    <p class="text-neutral-300 text-sm sm:text-base leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($p['description'] ?? 'No detailed description available for this item. Crafted with premium components and optimized for performance.')); ?>
                    </p>
                </div>

                <!-- Nút Mua -->
                <div class="pt-4">
                    <form method="POST" action="add_to_cart.php">
                        <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                        <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($p['name']); ?>">
                        <input type="hidden" name="product_price" value="<?php echo $has_sale ? $p['sale_price'] : $p['price']; ?>">
                        <button type="submit" class="w-full bg-white text-black font-extrabold py-4 rounded-2xl uppercase text-xs tracking-wider hover:bg-neutral-200 transition shadow-xl text-center">
                            Add to Cart & Order Now
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Phần Đánh Giá Từng Sản Phẩm -->
        <div class="mt-24 border-t border-white/10 pt-16 space-y-12">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <span class="text-neutral-400 text-xs font-semibold tracking-widest uppercase mb-1 block">Feedback</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-white">Customer Reviews for this Product</h2>
                </div>
                <span class="text-xs text-neutral-400 bg-neutral-900 border border-white/10 px-4 py-2 rounded-xl self-start">
                    Total Reviews: <?php echo ($reviews_result ? $reviews_result->num_rows : 0); ?>
                </span>
            </div>

            <!-- Khối Viết Đánh Giá Dành Cho Người Dùng Đã Mua Hàng -->
            <?php if ($user_id > 0): ?>
                <?php if ($can_review && !$has_reviewed): ?>
                    <div class="bg-neutral-950 border border-white/15 p-8 rounded-3xl space-y-4">
                        <h3 class="text-lg font-bold text-white">Leave a Review</h3>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-xs uppercase text-neutral-400 mb-2">Rating</label>
                                <select name="rating" class="w-full sm:w-64 bg-neutral-900 border border-white/15 rounded-xl px-4 py-3 text-xs text-white focus:outline-none focus:border-white">
                                    <option value="5">★★★★★ (5 Stars - Excellent)</option>
                                    <option value="4">★★★★☆ (4 Stars - Good)</option>
                                    <option value="3">★★★☆☆ (3 Stars - Average)</option>
                                    <option value="2">★★☆☆☆ (2 Stars - Poor)</option>
                                    <option value="1">★☆☆☆☆ (1 Star - Terrible)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs uppercase text-neutral-400 mb-2">Your Comment</label>
                                <textarea name="comment" rows="3" placeholder="Share your opinion about this product..." required class="w-full bg-neutral-900 border border-white/15 rounded-2xl p-4 text-xs text-white focus:outline-none focus:border-white resize-none"></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="bg-white text-black font-extrabold px-8 py-3 rounded-xl uppercase text-xs hover:bg-neutral-200 transition">
                                Submit Review
                            </button>
                        </form>
                    </div>
                <?php elseif ($has_reviewed): ?>
                    <div class="p-4 bg-neutral-900/50 border border-white/10 text-neutral-400 rounded-2xl text-xs">
                        ✓ You have already submitted a review for this product.
                    </div>
                <?php else: ?>
                    <div class="p-4 bg-neutral-900/50 border border-white/10 text-neutral-400 rounded-2xl text-xs">
                        💡 Only verified buyers with completed orders can submit a review.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="p-4 bg-neutral-900/50 border border-white/10 text-neutral-400 rounded-2xl text-xs flex justify-between items-center">
                    <span>Log in to review this product after purchase.</span>
                    <a href="login.php" class="bg-white/10 hover:bg-white hover:text-black text-white px-4 py-1.5 rounded-lg text-xs font-bold uppercase transition">Login</a>
                </div>
            <?php endif; ?>

            <!-- Danh sách đánh giá -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                if ($reviews_result && $reviews_result->num_rows > 0) {
                    while ($rev = $reviews_result->fetch_assoc()) {
                        $stars = str_repeat('★', intval($rev['rating'])) . str_repeat('☆', 5 - intval($rev['rating']));
                        echo '
                        <div class="bg-neutral-950 border border-white/10 p-6 rounded-3xl space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-white text-sm">' . htmlspecialchars($rev['full_name']) . '</span>
                                <span class="text-amber-400 text-xs tracking-widest">' . $stars . '</span>
                            </div>
                            <p class="text-neutral-300 text-xs leading-relaxed italic">"' . htmlspecialchars($rev['comment']) . '"</p>
                            <div class="text-[10px] text-neutral-500 pt-2 border-t border-white/5 flex justify-between">
                                <span class="text-emerald-400 font-semibold">✓ Verified Buyer</span>
                                <span>' . ($rev['created_at'] ?? 'Recently') . '</span>
                            </div>
                        </div>';
                    }
                } else {
                    echo '
                    <p class="col-span-full text-center text-neutral-500 text-xs uppercase py-8">
                        No reviews yet for this product.
                    </p>';
                }
                ?>
            </div>
        </div>
    </main>

    <!-- Modal Phóng To Ảnh (Lightbox) -->
    <div id="lightbox-modal" class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md hidden flex items-center justify-center p-4 cursor-pointer" onclick="closeLightbox()">
        <div class="relative max-w-4xl max-h-[90vh]">
            <button class="absolute -top-10 right-0 text-white text-xl font-bold px-3 py-1 bg-white/10 rounded-full">✕ Close</button>
            <img id="lightbox-img" src="" alt="Zoomed Image" class="max-w-full max-h-[85vh] rounded-2xl object-contain border border-white/20 shadow-2xl">
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-12 bg-black border-t border-white/10 text-neutral-500 text-xs tracking-widest uppercase">
        <p>&copy; 2026 Thanh Buy 🛒 &mdash; All Rights Reserved</p>
    </footer>

    <!-- JavaScript Phóng To Ảnh -->
    <script>
        function openLightbox(imgSrc) {
            const modal = document.getElementById('lightbox-modal');
            const lightboxImg = document.getElementById('lightbox-img');
            lightboxImg.src = imgSrc;
            modal.classList.remove('hidden');
        }

        function closeLightbox() {
            const modal = document.getElementById('lightbox-modal');
            modal.classList.add('hidden');
        }
    </script>
</body>
</html>