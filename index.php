<?php
// Start the session
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    include 'db_config.php';
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user['role'] == 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    } else {
        header("Location: user_dashboard.php");
        exit();
    }
}

include 'db_config.php';

// Lấy từ khóa tìm kiếm (nếu có)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
// Lấy danh mục lọc (nếu có)
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Buy 🛒 - Modern Tech & Gadgets</title>
    <link rel="icon" href="favicon.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .chat-scroll::-webkit-scrollbar { width: 5px; }
        .chat-scroll::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.02); }
        .chat-scroll::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 10px; }
    </style>
</head>

<body class="bg-black text-white antialiased selection:bg-neutral-200 selection:text-black">
    <!-- Header -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-black/60 backdrop-blur-xl border-b border-white/10 transition-all">
        <div class="max-w-7xl mx-auto px-6 h-20 flex justify-between items-center">
            <div class="text-xl font-bold tracking-widest uppercase flex items-center gap-2 text-white cursor-pointer" onclick="location.href='index.php'">
                <span>Thanh Buy</span> <span class="text-lg">🛒</span>
            </div>
            <div class="flex items-center gap-4">
                <button class="text-neutral-300 hover:text-white font-medium px-4 py-2 rounded-full transition text-sm tracking-wider uppercase" onclick="location.href='login.php'">Sign In</button>
                <button class="bg-white text-black px-6 py-2.5 rounded-full font-medium shadow-lg hover:bg-neutral-200 active:scale-95 transition-all text-sm tracking-wider uppercase" onclick="location.href='register.php'">Register</button>
            </div>
        </div>
    </header>

    <!-- Hero Section with Automatic Background Slider -->
    <section class="relative h-screen flex flex-col items-center justify-between text-center bg-black overflow-hidden pt-32 pb-16 px-6">
        <!-- Slider Images Container -->
        <div class="absolute inset-0 z-0">
            <div class="hero-slide absolute inset-0 bg-cover bg-center opacity-70 transition-opacity duration-1000" style="background-image: url('https://images.unsplash.com/photo-1523275335684-37898b6baf30?q=80&w=1920&auto=format&fit=crop');"></div>
            <div class="hero-slide absolute inset-0 bg-cover bg-center opacity-0 transition-opacity duration-1000" style="background-image: url('https://images.unsplash.com/photo-1590658268037-6bf12165a8df?q=80&w=1920&auto=format&fit=crop');"></div>
            <div class="hero-slide absolute inset-0 bg-cover bg-center opacity-0 transition-opacity duration-1000" style="background-image: url('https://images.unsplash.com/photo-1505740420928-5e560c06d30e?q=80&w=1920&auto=format&fit=crop');"></div>
        </div>
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-black/60 z-1"></div>

        <div class="relative z-10 max-w-3xl mx-auto mt-12">
            <span class="inline-block text-neutral-400 text-xs font-semibold tracking-widest uppercase mb-4 px-3 py-1 bg-white/10 backdrop-blur-md rounded-full border border-white/10">
                Next-Gen Ecosystem
            </span>
            <h1 class="text-4xl sm:text-6xl font-extrabold text-white mb-4 tracking-tight leading-none">
                Experience Ultimate Innovation
            </h1>
            <p class="text-sm sm:text-base text-neutral-300 font-normal tracking-wide">
                Engineered for maximum endurance, absolute precision, and immersive acoustics.
            </p>
        </div>

        <div class="relative z-10 flex flex-col sm:flex-row items-center gap-4 w-full max-w-md mx-auto">
            <a href="#catalog" class="w-full bg-neutral-800/80 backdrop-blur-md border border-white/25 text-white px-8 py-3.5 rounded-full font-semibold tracking-wider uppercase text-sm text-center hover:bg-white hover:text-black hover:border-white transition-all duration-300">
                Explore Catalog
            </a>
            <button class="w-full bg-white text-black px-8 py-3.5 rounded-full font-semibold tracking-wider uppercase text-sm hover:bg-neutral-200 transition-all duration-300" onclick="location.href='login.php'">
                Custom Order
            </button>
        </div>
    </section>

    <!-- Catalog Section with Search & Categories -->
    <section id="catalog" class="py-24 bg-black border-t border-white/10">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center max-w-xl mx-auto mb-16">
                <span class="text-neutral-400 text-xs font-semibold tracking-widest uppercase mb-3 block">Inventory</span>
                <h2 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight">Our Products</h2>
            </div>

            <!-- Search Bar & Category Filter Form -->
            <form method="GET" action="index.php#catalog" class="max-w-3xl mx-auto mb-16 flex flex-col sm:flex-row gap-4 items-center justify-between">
                <!-- Search Input -->
                <div class="relative w-full sm:flex-1">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search gadgets, smartwatches, earbuds..." class="w-full bg-neutral-900 border border-white/15 rounded-2xl px-5 py-3.5 text-sm text-white placeholder-neutral-500 focus:outline-none focus:border-white/50 transition">
                </div>
                
                <!-- Category Selector -->
                <div class="w-full sm:w-48">
                    <select name="category" onchange="this.form.submit()" class="w-full bg-neutral-900 border border-white/15 rounded-2xl px-4 py-3.5 text-sm text-white focus:outline-none focus:border-white/50 transition cursor-pointer">
                        <option value="">All Categories</option>
                        <option value="Smartwatch" <?php if($category=='Smartwatch') echo 'selected'; ?>>Smartwatch</option>
                        <option value="Audio" <?php if($category=='Audio') echo 'selected'; ?>>Audio / Earbuds</option>
                        <option value="Accessories" <?php if($category=='Accessories') echo 'selected'; ?>>Accessories</option>
                    </select>
                </div>

                <button type="submit" class="w-full sm:w-auto bg-white text-black font-extrabold px-6 py-3.5 rounded-2xl text-xs uppercase tracking-wider hover:bg-neutral-200 transition shadow-lg">Filter</button>
            </form>

            <!-- Product Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php
                // Xây dựng câu lệnh SQL tìm kiếm và phân loại động bảo mật
                $sql = "SELECT * FROM products WHERE 1=1";
                $params = [];
                $types = "";

                if (!empty($search)) {
                    $sql .= " AND (name LIKE ? OR description LIKE ?)";
                    $searchTerm = "%" . $search . "%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $types .= "ss";
                }

                if (!empty($category)) {
                    // Giả định bảng có cột category hoặc kiểm tra theo tên
                    $sql .= " AND category = ?";
                    $params[] = $category;
                    $types .= "s";
                }

                $sql .= " ORDER BY id DESC";

                $stmt = $conn->prepare($sql);
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    while ($p = $result->fetch_assoc()) {
                        $has_sale = !is_null($p['sale_price']) && $p['sale_price'] < $p['price'];
                        // Giả lập số lượng đã mua dựa trên ID hoặc cột có sẵn (ví dụ: sold_count)
                        $sold_count = isset($p['sold']) ? $p['sold'] : (10 + ($p['id'] * 3)); 
                        
                        echo '<div class="glass-panel bg-neutral-950 p-5 rounded-3xl relative flex flex-col justify-between border border-white/10 hover:border-indigo-500/50 transition-all duration-300 group shadow-xl">';
                        
                        // Khung chứa ảnh
                        // Khung chứa ảnh đã được bọc thẻ <a> dẫn sang product_detail.php
echo '<a href="product_detail.php?id=' . $p['id'] . '" class="block relative w-full h-56 overflow-hidden rounded-2xl mb-4 bg-neutral-900 border border-white/5 cursor-pointer">';
echo '<img src="' . htmlspecialchars($p['image']) . '" alt="' . htmlspecialchars($p['name']) . '" class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500">';

if($has_sale) {
    $percent = round((($p['price'] - $p['sale_price']) / $p['price']) * 100);
    echo '<span class="absolute top-3 right-3 bg-rose-500 text-white text-[10px] font-extrabold px-3 py-1 rounded-full uppercase tracking-wider shadow-lg">Save ' . $percent . '%</span>';
}
echo '</a>';

                        // Thông tin sản phẩm
                        echo '<div class="space-y-2">';
                        echo '<div class="flex items-center justify-between text-xs text-neutral-400">';
                        echo '<span>Sold: <strong class="text-white">' . $sold_count . ' units</strong></span>';
                        echo '<span class="text-emerald-400 font-semibold">★ 4.9 (Verified)</span>';
                        echo '</div>';
                        echo '<h3 class="font-extrabold text-white text-base tracking-tight line-clamp-1">' . htmlspecialchars($p['name']) . '</h3>';
                        echo '<p class="text-neutral-400 text-xs line-clamp-2 leading-relaxed">' . htmlspecialchars($p['description'] ?? 'High performance modern gadget engineered for optimal user satisfaction.') . '</p>';
                        echo '</div>';

                        // Giá tiền & Nút mua
                        echo '<div class="pt-4 mt-4 border-t border-white/5 flex items-center justify-between">';
                        if($has_sale) {
                            echo '<div>';
                            echo '<span class="line-through text-neutral-500 text-xs font-mono">$' . number_format($p['price'], 2) . '</span>';
                            echo '<div class="text-rose-400 font-extrabold font-mono text-base">$' . number_format($p['sale_price'], 2) . '</div>';
                            echo '</div>';
                        } else {
                            echo '<div>';
                            echo '<span class="text-neutral-500 text-[10px] uppercase tracking-wider block">Price</span>';
                            echo '<div class="text-indigo-400 font-extrabold font-mono text-base">$' . number_format($p['price'], 2) . '</div>';
                            echo '</div>';
                        }
                        
                        echo '<a href="login.php" class="bg-white/10 hover:bg-white hover:text-black text-white text-xs font-bold px-5 py-2.5 rounded-xl uppercase tracking-wider transition">Buy Now</a>';
                        echo '</div>';

                        echo '</div>';
                    }
                } else {
                    echo "<div class='col-span-full py-16 text-center text-neutral-500 font-medium tracking-wider uppercase text-sm'>No products matched your search criteria.</div>";
                }
                ?>
            </div>
        </div>
    </section>

    <!-- Verified Customer Testimonials & Quantity Purchased Feedback -->
    <section class="py-24 max-w-7xl mx-auto px-6 border-t border-white/10">
        <div class="text-center max-w-xl mx-auto mb-16">
            <span class="text-neutral-400 text-xs font-semibold tracking-widest uppercase mb-3 block">Customer Reviews</span>
            <h2 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight">Verified Buyer Feedback</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-neutral-950 p-8 rounded-3xl border border-white/10 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-1 text-amber-400 text-xs mb-4">★★★★★ <span class="text-neutral-400 ml-2">(Purchased: 2 units)</span></div>
                    <p class="text-neutral-300 text-sm font-light italic leading-relaxed mb-6">"The build quality and precision are unmatched. Bought two for my team and performance is outstanding!"</p>
                </div>
                <div class="flex items-center gap-3 pt-4 border-t border-white/5">
                    <div class="w-9 h-9 bg-neutral-800 rounded-full flex items-center justify-center font-bold text-xs text-white">TB</div>
                    <div>
                        <h4 class="text-white font-semibold text-xs tracking-wide">Thanh Bui</h4>
                        <p class="text-[10px] text-neutral-500 uppercase tracking-widest">Verified Owner</p>
                    </div>
                </div>
            </div>

            <div class="bg-neutral-950 p-8 rounded-3xl border border-white/10 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-1 text-amber-400 text-xs mb-4">★★★★★ <span class="text-neutral-400 ml-2">(Purchased: 1 unit)</span></div>
                    <p class="text-neutral-300 text-sm font-light italic leading-relaxed mb-6">"Flawless sound quality, minimal design, and lightning-fast delivery. Exceeded all expectations."</p>
                </div>
                <div class="flex items-center gap-3 pt-4 border-t border-white/5">
                    <div class="w-9 h-9 bg-neutral-800 rounded-full flex items-center justify-center font-bold text-xs text-white">HN</div>
                    <div>
                        <h4 class="text-white font-semibold text-xs tracking-wide">Hoang Nam</h4>
                        <p class="text-[10px] text-neutral-500 uppercase tracking-widest">Verified Owner</p>
                    </div>
                </div>
            </div>

            <div class="bg-neutral-950 p-8 rounded-3xl border border-white/10 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-1 text-amber-400 text-xs mb-4">★★★★★ <span class="text-neutral-400 ml-2">(Purchased: 5 units)</span></div>
                    <p class="text-neutral-300 text-sm font-light italic leading-relaxed mb-6">"Absolute top tier tech gadgets. Customer support is extremely helpful and shipping was tracked securely."</p>
                </div>
                <div class="flex items-center gap-3 pt-4 border-t border-white/5">
                    <div class="w-9 h-9 bg-neutral-800 rounded-full flex items-center justify-center font-bold text-xs text-white">AL</div>
                    <div>
                        <h4 class="text-white font-semibold text-xs tracking-wide">Alex Morgan</h4>
                        <p class="text-[10px] text-neutral-500 uppercase tracking-widest">Verified Owner</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center py-12 bg-black border-t border-white/10 text-neutral-500 text-xs tracking-widest uppercase">
        <p>&copy; 2026 Thanh Buy 🛒 &mdash; All Rights Reserved</p>
    </footer>

    <!-- TESLA-STYLE AI ASSISTANT WIDGET (GEMINI CONNECTED) -->
    <div class="fixed bottom-6 right-6 z-50">
        <button id="ai-toggle-btn" onclick="toggleAIChat()" class="bg-neutral-900 border border-white/20 text-white w-14 h-14 rounded-full flex items-center justify-center shadow-2xl hover:bg-white hover:text-black transition-all duration-300 group">
            <svg class="w-6 h-6 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
            </svg>
        </button>

        <div id="ai-chat-box" class="hidden absolute bottom-20 right-0 w-[360px] sm:w-[400px] h-[520px] bg-neutral-950/95 backdrop-blur-2xl border border-white/15 rounded-3xl shadow-2xl flex flex-col overflow-hidden transition-all duration-300">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between bg-black/40">
                <div class="flex items-center gap-3">
                    <div class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></div>
                    <span class="text-xs font-bold uppercase tracking-widest text-white">Thanh AI (Gemini Powered)</span>
                </div>
                <button onclick="toggleAIChat()" class="text-neutral-400 hover:text-white text-sm font-semibold">✕</button>
            </div>

            <!-- Messages Area -->
            <div id="chat-messages" class="flex-1 p-6 overflow-y-auto chat-scroll flex flex-col gap-4 text-sm">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-white/10 border border-white/20 flex items-center justify-center text-xs text-white shrink-0 font-bold">AI</div>
                    <div class="bg-neutral-900 border border-white/10 p-4 rounded-2xl rounded-tl-none text-neutral-200 leading-relaxed max-w-[80%]">
                        Hello! I'm connected to Google Gemini. Ask me anything about our gadgets, smartwatches, or inventory!
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="p-4 border-t border-white/10 bg-black/40 flex items-center gap-2">
                <input type="text" id="user-input" placeholder="Ask Gemini AI..." onkeypress="handleKeyPress(event)" class="flex-1 bg-neutral-900 border border-white/10 rounded-full px-4 py-3 text-sm text-white placeholder-neutral-500 focus:outline-none focus:border-white/40 transition-all">
                <button onclick="sendMessage()" id="send-btn" class="bg-white text-black w-11 h-11 rounded-full flex items-center justify-center font-bold hover:bg-neutral-200 transition-all shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript xử lý Slider ảnh nền tự động & AI Chat -->
    <script>
        // 1. Script tự động chuyển hình nền Hero mỗi 5 giây
        let currentSlide = 0;
        const slides = document.querySelectorAll('.hero-slide');
        function nextSlide() {
            if(slides.length === 0) return;
            slides[currentSlide].style.opacity = '0';
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].style.opacity = '0.7';
        }
        setInterval(nextSlide, 5000);

        // 2. Script Chat AI Assistant
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
                    <div class="bg-white text-black p-4 rounded-2xl rounded-tr-none text-neutral-900 leading-relaxed max-w-[80%] font-medium">
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
                        <div class="bg-neutral-900 border border-white/10 p-4 rounded-2xl rounded-tl-none text-neutral-200 leading-relaxed max-w-[80%]">
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
                        <div class="bg-neutral-900 border border-white/10 p-4 rounded-2xl rounded-tl-none text-red-400 leading-relaxed max-w-[80%]">
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