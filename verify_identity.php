
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_config.php';

// Đảm bảo bảng users có các cột cần thiết
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    citizen_id VARCHAR(50) DEFAULT NULL,
    role VARCHAR(50) DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$error_msg = "";

// 1. XỬ LÝ ĐĂNG NHẬP USER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'login') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error_msg = "Please fill in all login fields!";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user_data = $result->fetch_assoc()) {
            if (password_verify($password, $user_data['password']) || $password === $user_data['password']) {
                $_SESSION['user_id'] = $user_data['id'];
                $_SESSION['role'] = $user_data['role'];
                
                $has_citizen = !empty($user_data['citizen_id']);

                if ($has_citizen) {
                    header("Location: user_dashboard.php");
                    exit();
                } else {
                    header("Location: verify_identity.php");
                    exit();
                }
            } else {
                $error_msg = "Incorrect password. Please try again.";
            }
        } else {
            $error_msg = "Email address not found in our system.";
        }
        $stmt->close();
    }
}

// 2. XỬ LÝ XÁC MINH CCCD & HOÀN TẤT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'verify') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: verify_identity.php");
        exit();
    }

    $user_id = intval($_SESSION['user_id']);
    $full_name = trim($_POST['full_name']);
    $citizen_id = trim($_POST['citizen_id']);
    $terms = isset($_POST['terms']) ? true : false;

    if (empty($full_name) || empty($citizen_id)) {
        $error_msg = "Please provide both your full name and citizen ID.";
    } elseif (strlen($citizen_id) !== 12 || !is_numeric($citizen_id)) {
        $error_msg = "Citizen ID (CCCD) must be exactly 12 numeric digits.";
    } elseif (!$terms) {
        $error_msg = "You must accept the terms and conditions to proceed.";
    } else {
        $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, citizen_id = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $full_name, $citizen_id, $user_id);
        
        if ($update_stmt->execute()) {
            $update_stmt->close();
            header("Location: user_dashboard.php");
            exit();
        } else {
            $error_msg = "Database error while saving verification data.";
        }
    }
}

// Kiểm tra trạng thái user hiện tại
$is_logged_in = isset($_SESSION['user_id']);
$user = null;
$needs_verification = false;

if ($is_logged_in) {
    $user_id = intval($_SESSION['user_id']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $has_citizen_id = !empty($user['citizen_id']);
        if ($has_citizen_id) {
            header("Location: user_dashboard.php");
            exit();
        } else {
            $needs_verification = true;
        }
    } else {
        session_destroy();
        header("Location: verify_identity.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apex Global &mdash; Secure User Verification</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-panel {
            background: rgba(18, 18, 24, 0.75);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        }
        .glow-button {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            transition: all 0.3s ease;
        }
        .glow-button:hover:not(:disabled) {
            box-shadow: 0 0 25px rgba(99, 102, 241, 0.5);
            transform: translateY(-1px);
        }
        canvas {
            background: #09090b;
            display: block;
            margin: 0 auto;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
</head>
<body class="bg-[#030305] text-neutral-100 antialiased min-h-screen flex items-center justify-center p-6 selection:bg-indigo-500 selection:text-white">

    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-32 -left-32 w-[500px] h-[500px] bg-indigo-600/15 rounded-full blur-[140px]"></div>
        <div class="absolute -bottom-32 -right-32 w-[500px] h-[500px] bg-violet-600/15 rounded-full blur-[140px]"></div>
    </div>

    <div class="glass-panel max-w-lg w-full p-8 sm:p-10 rounded-[2.5rem] space-y-8 relative z-10">

        <div class="flex flex-col items-center text-center space-y-3">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center font-black text-white text-lg shadow-lg shadow-indigo-500/30">U</div>
            <div>
                <span class="text-[10px] font-extrabold uppercase text-indigo-400 tracking-[0.2em] block mb-1">User Portal Authentication</span>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">
                    <?= !$is_logged_in ? 'Customer Sign In' : 'Identity Verification' ?>
                </h1>
                <p class="text-xs text-neutral-400 mt-1 max-w-xs">
                    <?= !$is_logged_in ? 'Access your user account dashboard securely.' : 'Complete your legal ID and pass the security Dino Captcha challenge.' ?>
                </p>
            </div>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="bg-rose-950/60 border border-rose-500/40 text-rose-300 p-4 rounded-2xl text-xs font-semibold flex items-center gap-3 shadow-lg">
                <span class="text-base">⚠️</span>
                <span><?= $error_msg ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$is_logged_in): ?>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="action_type" value="login">
                
                <div class="space-y-1.5">
                    <label class="block text-[11px] uppercase text-neutral-300 font-bold tracking-wider">Email Address</label>
                    <input type="email" name="email" required placeholder="user@example.com"
                           class="w-full bg-black/40 border border-white/10 rounded-2xl px-4 py-3.5 text-xs text-white placeholder:text-neutral-600 focus:outline-none focus:border-indigo-500 transition shadow-inner">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[11px] uppercase text-neutral-300 font-bold tracking-wider">Password</label>
                    <input type="password" name="password" required placeholder="••••••••••••"
                           class="w-full bg-black/40 border border-white/10 rounded-2xl px-4 py-3.5 text-xs text-white placeholder:text-neutral-600 focus:outline-none focus:border-indigo-500 transition shadow-inner">
                </div>

                <button type="submit" class="w-full glow-button text-white font-extrabold py-4 rounded-2xl uppercase text-xs tracking-[0.15em] shadow-xl cursor-pointer mt-2">
                    Sign In to Dashboard &rarr;
                </button>
            </form>

            <div class="text-center text-xs text-neutral-400 pt-1">
                New customer? <a href="register.php" class="text-indigo-400 font-bold hover:underline">Create an account</a>
            </div>

        <?php elseif ($needs_verification): ?>
            <form method="POST" id="verify-form" class="space-y-5">
                <input type="hidden" name="action_type" value="verify">
                
                <div class="space-y-1.5">
                    <label class="block text-[11px] uppercase text-neutral-300 font-bold tracking-wider">Full Legal Name</label>
                    <input type="text" name="full_name" required placeholder="Enter your full name as on ID" 
                           value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
                           class="w-full bg-black/40 border border-white/10 rounded-2xl px-4 py-3.5 text-xs text-white focus:outline-none focus:border-indigo-500 transition shadow-inner">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[11px] uppercase text-neutral-300 font-bold tracking-wider">Citizen ID Card Number (CCCD)</label>
                    <input type="text" name="citizen_id" id="citizen_id" maxlength="12" required placeholder="Enter exact 12-digit ID number"
                           class="w-full bg-black/40 border border-white/10 rounded-2xl px-4 py-3.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500 transition shadow-inner tracking-widest">
                </div>

                <div class="space-y-2.5 pt-1">
                    <div class="flex justify-between items-center text-xs px-1">
                        <span class="font-bold uppercase tracking-wider text-neutral-300">Dino Captcha Verification:</span>
                        <span id="game-status" class="text-rose-400 font-bold uppercase text-[10px] bg-rose-950/60 px-3 py-1 rounded-full border border-rose-500/30">Locked (Score: 0/10)</span>
                    </div>
                    <div class="relative group">
                        <canvas id="dinoCanvas" width="420" height="140"></canvas>
                        <div id="game-overlay" class="absolute inset-0 bg-black/80 backdrop-blur-md flex flex-col items-center justify-center rounded-2xl cursor-pointer transition group-hover:bg-black/75" onclick="startGame()">
                            <span class="text-white font-extrabold text-xs uppercase tracking-wider mb-1 flex items-center gap-2">
                                <span>🦖</span> Click to Start Dino Game
                            </span>
                            <span class="text-[10px] text-neutral-400">Press <kbd class="px-1.5 py-0.5 bg-white/10 rounded text-white font-mono">SPACE</kbd> or Click to Jump over 10 cacti</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-1 px-1">
                    <input type="checkbox" name="terms" id="terms" required class="w-4 h-4 rounded bg-black border-white/20 text-indigo-600 focus:ring-0 cursor-pointer accent-indigo-500">
                    <label for="terms" class="text-xs text-neutral-300 cursor-pointer">I verify my legal compliance and accept system terms.</label>
                </div>

                <button type="submit" id="submit-btn" disabled 
                        class="w-full bg-neutral-900 border border-white/10 text-neutral-600 font-extrabold py-4 rounded-2xl uppercase text-xs tracking-[0.15em] transition cursor-not-allowed">
                    Verify & Enter Dashboard
                </button>
            </form>
            
            <div class="text-center pt-2">
                <a href="logout.php" class="text-[11px] text-neutral-500 hover:text-neutral-300 transition underline">Sign out / Use another account</a>
            </div>
        <?php endif; ?>

    </div>

    <?php if ($needs_verification): ?>
    <script>
        const canvas = document.getElementById('dinoCanvas');
        const ctx = canvas.getContext('2d');
        const overlay = document.getElementById('game-overlay');
        const statusEl = document.getElementById('game-status');
        const submitBtn = document.getElementById('submit-btn');

        let gameRunning = false;
        let score = 0;
        let targetScore = 10;
        let isVerified = false;

        let dino = { x: 35, y: 95, width: 22, height: 26, vy: 0, gravity: 0.6, jumpPower: -9.5, grounded: true };
        let obstacles = [];
        let frameCount = 0;

        function startGame() {
            if (isVerified) return;
            gameRunning = true;
            score = 0;
            obstacles = [];
            dino.y = 95;
            dino.vy = 0;
            overlay.style.display = 'none';
            loop();
        }

        document.addEventListener('keydown', function(e) {
            if ((e.code === 'Space' || e.code === 'ArrowUp') && gameRunning) {
                if (dino.grounded) {
                    dino.vy = dino.jumpPower;
                    dino.grounded = false;
                }
                e.preventDefault();
            }
        });

        canvas.addEventListener('click', function() {
            if (gameRunning && dino.grounded) {
                dino.vy = dino.jumpPower;
                dino.grounded = false;
            }
        });

        function update() {
            if (!gameRunning) return;

            dino.vy += dino.gravity;
            dino.y += dino.vy;

            if (dino.y >= 95) {
                dino.y = 95;
                dino.vy = 0;
                dino.grounded = true;
            }

            if (frameCount % 100 === 0) {
                obstacles.push({ x: canvas.width, y: 97, width: 14, height: 24, speed: 4.2 });
            }

            for (let i = obstacles.length - 1; i >= 0; i--) {
                obstacles[i].x -= obstacles[i].speed;

                if (
                    dino.x < obstacles[i].x + obstacles[i].width &&
                    dino.x + dino.width > obstacles[i].x &&
                    dino.y < obstacles[i].y + obstacles[i].height &&
                    dino.y + dino.height > obstacles[i].y
                ) {
                    gameRunning = false;
                    overlay.style.display = 'flex';
                    overlay.innerHTML = '<span class="text-rose-400 font-bold text-xs uppercase mb-1">💥 Collided! Score Reset.</span><span class="text-[10px] text-neutral-300">Click anywhere here to retry</span>';
                    return;
                }

                if (obstacles[i].x + obstacles[i].width < dino.x && !obstacles[i].passed) {
                    obstacles[i].passed = true;
                    score++;
                    if (score >= targetScore) {
                        gameRunning = false;
                        isVerified = true;
                        overlay.style.display = 'flex';
                        overlay.innerHTML = '<span class="text-emerald-400 font-extrabold text-xs uppercase mb-1">🎉 Captcha Verified!</span><span class="text-[10px] text-neutral-300">You may now submit and enter dashboard.</span>';
                        
                        statusEl.className = "text-emerald-400 font-bold uppercase text-[10px] bg-emerald-950/60 px-3 py-1 rounded-full border border-emerald-500/30";
                        statusEl.innerText = "Passed (Score: " + score + "/10)";

                        submitBtn.disabled = false;
                        submitBtn.className = "w-full glow-button text-white font-extrabold py-4 rounded-2xl uppercase text-xs tracking-[0.15em] shadow-xl cursor-pointer mt-2";
                        return;
                    }
                }

                if (obstacles[i].x < -25) {
                    obstacles.splice(i, 1);
                }
            }

            if (!isVerified) {
                statusEl.innerText = "Playing... (" + score + "/" + targetScore + ")";
            }

            frameCount++;
        }

        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            ctx.strokeStyle = "rgba(255, 255, 255, 0.15)";
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(0, 121);
            ctx.lineTo(canvas.width, 121);
            ctx.stroke();

            ctx.fillStyle = "#818cf8";
            ctx.fillRect(dino.x, dino.y, dino.width, dino.height);

            ctx.fillStyle = "#f43f5e";
            for (let obs of obstacles) {
                ctx.fillRect(obs.x, obs.y, obs.width, obs.height);
            }

            ctx.fillStyle = "#71717a";
            ctx.font = "11px 'Plus Jakarta Sans', sans-serif";
            ctx.fillText("Score: " + score + " / " + targetScore, canvas.width - 85, 25);
        }

        function loop() {
            update();
            draw();
            if (gameRunning) {
                requestAnimationFrame(loop);
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>