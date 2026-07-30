<?php
// Database Configuration
include 'db_config.php';

$toast_message = "";

// Handle Registration Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $location = trim($_POST['location']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $toast_message = "Email address is already registered!";
        $check_stmt->close();
    } else {
        $check_stmt->close();
        
        // Insert into `users` table matching your exact database columns
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, address, phone) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $name, $email, $password, $role, $location, $phone);

        if ($stmt->execute()) {
            echo "<script>alert('Registration Successful! You can now log in.'); window.location='login.php';</script>";
            exit();
        } else {
            $toast_message = "Error: Unable to register. " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Buy 🛒 - Register</title>
    <link rel="icon" href="favicon.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-panel {
            background: rgba(14, 14, 16, 0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
</head>

<body class="bg-black text-white antialiased min-h-screen flex flex-col justify-between selection:bg-white selection:text-black">
    
    <!-- Background Ambient Glow -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[900px] h-[350px] bg-gradient-to-b from-indigo-600/10 to-transparent blur-[140px] pointer-events-none rounded-full z-0"></div>

    <!-- Toast Alert if any -->
    <?php if (!empty($toast_message)): ?>
        <div id="toast-alert" class="fixed top-6 right-6 z-50 glass-panel px-6 py-4 rounded-2xl border border-rose-500/30 text-rose-400 text-xs font-bold uppercase tracking-wider shadow-2xl flex items-center gap-3">
            <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-ping"></span>
            <span><?= htmlspecialchars($toast_message, ENT_QUOTES, 'UTF-8') ?></span>
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
        <div class="max-w-7xl mx-auto px-6 h-20 flex justify-between items-center">
            <div class="text-base sm:text-lg font-black tracking-widest uppercase text-white">
                <a href="index.php" class="hover:opacity-80 transition">Thanh Buy <span class="text-indigo-400">Store</span></a>
            </div>
            <div class="flex items-center gap-3">
                <a href="login.php" class="bg-white/10 border border-white/20 text-white px-5 py-2.5 rounded-full font-bold hover:bg-white hover:text-black transition-all text-[11px] tracking-widest uppercase">Login</a>
                <a href="index.php" class="hidden sm:inline-block text-neutral-400 hover:text-white transition text-xs font-bold uppercase tracking-wider px-3">Home</a>
            </div>
        </div>
    </header>

    <!-- Main Register Container -->
    <main class="flex-grow flex items-center justify-center px-4 py-12 relative z-10">
        <div class="glass-panel p-8 sm:p-10 rounded-3xl shadow-2xl max-w-lg w-full space-y-8 border border-white/10">
            
            <div class="text-center space-y-2">
                <span class="text-indigo-400 text-[10px] font-extrabold tracking-[0.25em] uppercase block">Secure Portal</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">Create an Account</h1>
                <p class="text-neutral-400 text-xs tracking-wider uppercase">Join Thanh Buy for a seamless shopping experience.</p>
            </div>

            <!-- Social Login Buttons -->
            <div class="grid grid-cols-3 gap-3">
                <button type="button" onclick="alert('Google OAuth Integration Ready.')" class="bg-white/5 border border-white/10 hover:bg-white/10 text-white py-3 rounded-2xl flex items-center justify-center gap-2 transition text-xs font-bold">
                    <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#EA4335" d="M12 5c1.6 0 3 .6 4.1 1.6l3.1-3.1C17.3 1.8 14.8 1 12 1 7.4 1 3.5 3.6 1.6 7.4l3.7 2.9C6.2 7.1 8.9 5 12 5z"/><path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5c-.3 1.5-1.1 2.8-2.4 3.7l3.7 2.9c2.2-2 3.7-5 3.7-8.8z"/><path fill="#FBBC05" d="M5.3 14.7c-.2-.7-.4-1.5-.4-2.7s.2-2 .4-2.7L1.6 6.4C.6 8.4 0 10.6 0 13s.6 4.6 1.6 6.6l3.7-2.9z"/><path fill="#34A853" d="M12 23c3.2 0 6-1.1 8-3l-3.7-2.9c-1.1.7-2.5 1.2-4.3 1.2-3.1 0-5.8-2.1-6.7-5.3L1.6 15.6C3.5 19.4 7.4 23 12 23z"/></svg>
                    <span>Google</span>
                </button>
                <button type="button" onclick="alert('Facebook OAuth Integration Ready.')" class="bg-white/5 border border-white/10 hover:bg-white/10 text-white py-3 rounded-2xl flex items-center justify-center gap-2 transition text-xs font-bold">
                    <svg class="w-4 h-4 fill-[#1877F2]" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    <span>Facebook</span>
                </button>
                <button type="button" onclick="alert('Apple ID Integration Ready.')" class="bg-white/5 border border-white/10 hover:bg-white/10 text-white py-3 rounded-2xl flex items-center justify-center gap-2 transition text-xs font-bold">
                    <svg class="w-4 h-4 fill-white" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.83 5.37c.56-.68.94-1.63.84-2.58-.83.04-1.84.55-2.43 1.23-.52.59-.98 1.55-.85 2.49.93.07 1.88-.47 2.44-1.14z"/></svg>
                    <span>Apple</span>
                </button>
            </div>

            <div class="flex items-center my-4">
                <div class="flex-grow border-t border-white/10"></div>
                <span class="px-3 text-neutral-500 text-[10px] uppercase tracking-widest font-bold">Or register with email</span>
                <div class="flex-grow border-t border-white/10"></div>
            </div>

            <!-- Standard Registration Form -->
            <form method="POST" action="" class="space-y-4">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Full Name</label>
                    <input type="text" name="name" placeholder="Enter your full name..." required 
                           class="w-full px-4.5 py-3.5 bg-black/50 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-indigo-500 transition text-xs">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Email Address</label>
                        <input type="email" name="email" placeholder="name@example.com" required 
                               class="w-full px-4.5 py-3.5 bg-black/50 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-indigo-500 transition text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Phone Number</label>
                        <input type="text" name="phone" placeholder="+880 12345678" required 
                               class="w-full px-4.5 py-3.5 bg-black/50 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-indigo-500 transition text-xs">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Delivery Address</label>
                    <input type="text" name="location" placeholder="Street, City..." required 
                           class="w-full px-4.5 py-3.5 bg-black/50 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-indigo-500 transition text-xs">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Password</label>
                        <input type="password" name="password" placeholder="••••••••" required 
                               class="w-full px-4.5 py-3.5 bg-black/50 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-indigo-500 transition text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-neutral-400 mb-2">Account Role</label>
                        <select name="role" required class="w-full px-4.5 py-3.5 bg-black/50 border border-white/10 rounded-2xl text-white focus:outline-none focus:border-indigo-500 transition text-xs">
                            <option value="" disabled selected>Select Role</option>
                            <option value="user" class="bg-neutral-900 text-white">Customer (User)</option>
                            <option value="admin" class="bg-neutral-900 text-white">Administrator</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="w-full bg-white text-black font-extrabold py-4 rounded-2xl hover:bg-indigo-500 hover:text-white transition-all text-xs uppercase tracking-[0.2em] shadow-lg mt-2">
                    Create Account
                </button>
            </form>

            <div class="text-center pt-2">
                <p class="text-xs text-neutral-400">
                    Already have an account? 
                    <a href="login.php" class="text-indigo-400 font-bold hover:underline ml-1">Sign In</a>
                </p>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="text-center py-8 glass-panel border-t border-white/10 text-neutral-500 text-[11px] tracking-[0.2em] uppercase mt-12">
        <p>&copy; 2026 Thanh Buy 🛒 &mdash; All Rights Reserved</p>
    </footer>

</body>

</html>