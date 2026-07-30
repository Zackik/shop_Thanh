<?php
// Bắt đầu session an toàn
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Khởi tạo bộ đếm chống Brute-Force nếu chưa có
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['lockout_time'] = 0;
}

// Kiểm tra thời gian khóa tạm thời (Khóa 30 giây nếu sai quá 3 lần)
$lockout_duration = 30;
if ($_SESSION['login_attempts'] >= 3) {
    $time_left = ($_SESSION['lockout_time'] + $lockout_duration) - time();
    if ($time_left > 0) {
        $error_message = "Too many failed attempts. Please wait " . $time_left . " seconds.";
    } else {
        // Hết thời gian khóa, reset lại
        $_SESSION['login_attempts'] = 0;
        $_SESSION['lockout_time'] = 0;
    }
}

// Tạo CSRF Token chống giả mạo request
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = $error_message ?? '';

// Xử lý khi Submit Form đăng nhập
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['login_attempts'] < 3) {
    // Kiểm tra CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security validation failed (CSRF token mismatch).");
    }

    include 'db_config.php';

    // Lọc và làm sạch dữ liệu đầu vào cơ bản
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $role = trim($_POST['role']);

    if (empty($email) || empty($password) || empty($role)) {
        $error_message = "Please fill in all fields.";
    } else {
        // Sử dụng Prepared Statement để chống SQL Injection tuyệt đối
        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ? AND role = ?");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Xác thực mật khẩu đã băm (Password Hashing)
            if (password_verify($password, $user['password'])) {
                // Đăng nhập thành công -> Reset số lần sai
                $_SESSION['login_attempts'] = 0;
                $_SESSION['lockout_time'] = 0;

                // Chống tấn công Session Fixation (Tạo phiên mới an toàn)
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];

                // Phân quyền chuyển hướng
                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: user_dashboard.php");
                }
                exit();
            }
        }

        // Nếu sai email, mật khẩu hoặc role -> Tăng bộ đếm thất bại
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 3) {
            $_SESSION['lockout_time'] = time();
            $error_message = "Too many failed attempts. Account access temporarily restricted for 30s.";
        } else {
            // Thông báo chung chung để không lộ việc email có tồn tại hay không
            $error_message = "Invalid email, password, or role. Please try again.";
        }

        $stmt->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Thanh Buy 🛒</title>
    <link rel="icon" href="favicon.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>

<body class="h-full bg-black text-white antialiased flex flex-col justify-between selection:bg-neutral-200 selection:text-black relative overflow-x-hidden">
    <!-- Background Cinematic Overlay (Tesla Style) -->
    <div class="absolute inset-0 bg-cover bg-center opacity-40 scale-105 pointer-events-none" style="background-image: url('https://images.unsplash.com/photo-1550745165-9bc0b252726f?q=80&w=1920&auto=format&fit=crop');"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-black/80 pointer-events-none"></div>

    <!-- Header -->
    <header class="relative z-10 w-full backdrop-blur-xl bg-black/40 border-b border-white/10">
        <div class="max-w-7xl mx-auto px-6 h-20 flex justify-between items-center">
            <div class="text-xl font-bold tracking-widest uppercase flex items-center gap-2 text-white">
                <a href="index.php">Thanh Buy 🛒</a>
            </div>
            <div class="flex items-center gap-4">
                <button class="text-neutral-300 hover:text-white font-medium px-4 py-2 rounded-full transition text-sm tracking-wider uppercase" onclick="location.href='index.php'">Home</button>
                <button class="bg-white text-black px-6 py-2.5 rounded-full font-medium shadow-lg hover:bg-neutral-200 active:scale-95 transition-all text-sm tracking-wider uppercase" onclick="location.href='register.php'">Register</button>
            </div>
        </div>
    </header>

    <!-- Main Content / Login Card -->
    <main class="relative z-10 flex-grow flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md bg-neutral-950/90 backdrop-blur-2xl p-8 sm:p-10 rounded-3xl shadow-2xl border border-white/15">
            
            <div class="text-center mb-8">
                <span class="text-neutral-400 text-xs font-semibold tracking-widest uppercase mb-2 block">Secure Authentication</span>
                <h1 class="text-3xl font-extrabold text-white tracking-tight">Welcome Back</h1>
                <p class="text-neutral-400 text-sm mt-1">Sign in to manage your high-end gear</p>
            </div>

            <!-- Error message alert if exists -->
            <?php if (!empty($error_message)): ?>
                <div class="mb-6 bg-red-950/50 border border-red-500/30 text-red-400 px-4 py-3 rounded-2xl text-xs font-medium text-center tracking-wide">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <!-- CSRF Token Hidden Field -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-neutral-400 mb-2">Email Address</label>
                    <input type="email" name="email" placeholder="name@example.com" required 
                           class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-white/40 transition-all text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-neutral-400 mb-2">Password</label>
                    <input type="password" name="password" placeholder="••••••••" required 
                           class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-white/40 transition-all text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-neutral-400 mb-2">Account Role</label>
                    <select name="role" required 
                            class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white focus:outline-none focus:border-white/40 transition-all cursor-pointer text-sm">
                        <option value="" disabled selected>Select Role</option>
                        <option value="user" class="bg-neutral-900 text-white">User</option>
                        <option value="admin" class="bg-neutral-900 text-white">Admin</option>
                    </select>
                </div>

                <button type="submit" 
                        class="w-full mt-2 bg-white text-black font-semibold py-4 rounded-2xl shadow-lg hover:bg-neutral-200 active:scale-95 transition-all text-sm tracking-wider uppercase">
                    Sign In
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-white/10 flex items-center justify-between text-xs tracking-wider">
                <span class="text-neutral-400">Don't have an account?</span>
                <a href="register.php" class="font-semibold text-white hover:underline uppercase">Create account</a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="relative z-10 text-center py-8 bg-black border-t border-white/10 text-neutral-500 text-xs tracking-widest uppercase">
        <p>&copy; 2026 Thanh Buy 🛒 &mdash; All Rights Reserved</p>
    </footer>
</body>

</html>