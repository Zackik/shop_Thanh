<?php
session_start();

// Xử lý nút Logout: Xóa session và chuyển hướng về trang login
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php"); // Thay 'login.php' bằng tên file đăng nhập thực tế của bạn
    exit;
}

if (!isset($_SESSION['balance'])) {
    $_SESSION['balance'] = 1000.00;
}

$message = "";
$effect_type = ""; 
$user_cards = [];
$admin_cards = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $bet_amount = floatval($_POST['bet'] ?? 0);

    if ($action === 'start') {
        if ($bet_amount <= 0 || $bet_amount > $_SESSION['balance']) {
            $message = "Invalid bet amount or exceeds current balance!";
        } else {
            $_SESSION['current_bet'] = $bet_amount;
            
            $deck = [];
            $suits = ['♠', '♥', '♦', '♣'];
            $values = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
            
            foreach ($suits as $s) {
                foreach ($values as $v) {
                    $deck[] = ['suit' => $s, 'val' => $v];
                }
            }
            shuffle($deck);

            $_SESSION['user_cards'] = [array_pop($deck), array_pop($deck)];
            $_SESSION['admin_cards'] = [array_pop($deck), array_pop($deck)];
            $_SESSION['deck'] = $deck;
            $_SESSION['game_status'] = 'playing';
        }
    } 
    elseif ($action === 'hit' && $_SESSION['game_status'] === 'playing') {
        $_SESSION['user_cards'][] = array_pop($_SESSION['deck']);
        $user_score = calculate_score($_SESSION['user_cards']);

        if ($user_score > 21) {
            $lost_amount = $_SESSION['current_bet'] * 0.03;
            $_SESSION['balance'] -= $lost_amount;
            $_SESSION['game_status'] = 'ended';
            $effect_type = 'lose';
            $message = "💥 Bust over 21! You lose 3% (-$" . number_format($lost_amount, 2) . ").";
        }
    } 
    elseif ($action === 'stand' && $_SESSION['game_status'] === 'playing') {
        $deck = $_SESSION['deck'];
        $user_cards = $_SESSION['user_cards'];
        $admin_cards = $_SESSION['admin_cards'];

        $user_score = calculate_score($user_cards);
        $admin_score = calculate_score($admin_cards);

        while ($admin_score < 17) {
            $admin_cards[] = array_pop($deck);
            $admin_score = calculate_score($admin_cards);
        }

        $_SESSION['admin_cards'] = $admin_cards;
        $_SESSION['game_status'] = 'ended';

        if ($admin_score > 21 || $user_score > $admin_score) {
            $win_amount = $_SESSION['current_bet'] * 0.07;
            $_SESSION['balance'] += $win_amount;
            $effect_type = 'win';
            $message = "🎉 Blackjack Win! You gained +7% (+$" . number_format($win_amount, 2) . ").";
        } elseif ($user_score < $admin_score) {
            $lost_amount = $_SESSION['current_bet'] * 0.03;
            $_SESSION['balance'] -= $lost_amount;
            $effect_type = 'lose';
            $message = "😢 Lower score than Dealer! You lose 3% (-$" . number_format($lost_amount, 2) . ").";
        } else {
            $effect_type = 'draw';
            $message = "🤝 Push (Tie)! Balance remains unchanged.";
        }
    }
    elseif ($action === 'reset') {
        $_SESSION['game_status'] = 'ready';
    }
}

function calculate_score($cards) {
    $score = 0;
    $aces = 0;
    foreach ($cards as $card) {
        $v = $card['val'];
        if ($v === 'A') {
            $aces++;
            $score += 11;
        } elseif (in_array($v, ['J', 'Q', 'K'])) {
            $score += 10;
        } else {
            $score += intval($v);
        }
    }
    while ($score > 21 && $aces > 0) {
        $score -= 10;
        $aces--;
    }
    return $score;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIP Casino Royale - 21 Points</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #032115;
            background-image: 
                radial-gradient(circle at center, rgba(4, 120, 87, 0.45) 0%, rgba(2, 20, 13, 0.95) 100%),
                radial-gradient(at 50% 10%, rgba(16, 185, 129, 0.2) 0px, transparent 60%);
            color: #f3f4f6;
        }

        .casino-table {
            background: rgba(3, 37, 24, 0.75);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(212, 175, 55, 0.35);
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.85), inset 0 0 50px rgba(16, 185, 129, 0.25);
        }

        .card-item {
            animation: cardAppear 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        @keyframes cardAppear {
            0% { transform: translateY(40px) scale(0.8); opacity: 0; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }

        @keyframes moneyGlow {
            0% { transform: scale(1); color: #fbbf24; text-shadow: 0 0 0px rgba(251, 191, 36, 0); }
            50% { transform: scale(1.15); color: #34d399; text-shadow: 0 0 25px rgba(52, 211, 153, 0.9); }
            100% { transform: scale(1); color: #fbbf24; text-shadow: 0 0 0px rgba(251, 191, 36, 0); }
        }
        @keyframes moneyLoss {
            0% { transform: scale(1); color: #fbbf24; text-shadow: 0 0 0px rgba(251, 191, 36, 0); }
            50% { transform: scale(1.15); color: #f87171; text-shadow: 0 0 25px rgba(248, 113, 113, 0.9); }
            100% { transform: scale(1); color: #fbbf24; text-shadow: 0 0 0px rgba(251, 191, 36, 0); }
        }
        .animate-win { animation: moneyGlow 1s ease-in-out; }
        .animate-loss { animation: moneyLoss 1s ease-in-out; }

        @keyframes floatUp {
            0% { transform: translateY(0) scale(0.5); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: translateY(-70px) scale(1.3); opacity: 0; }
        }
        .floating-coin {
            position: absolute;
            animation: floatUp 1.2s ease-out forwards;
            pointer-events: none;
            font-weight: 900;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4 relative overflow-x-hidden">

    <!-- Top Navigation Bar with Logout Button -->
    <div class="max-w-xl w-full flex justify-between items-center mb-4 px-2">
        <span class="text-xs text-emerald-400 font-bold uppercase tracking-wider">♠️ VIP Casino Royale Suite</span>
        <a href="?action=logout" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 px-3.5 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider transition flex items-center gap-1.5 shadow-md">
            <span>🚪</span> Back to Login
        </a>
    </div>

    <div class="max-w-xl w-full casino-table rounded-3xl p-6 sm:p-8 relative overflow-hidden">
        
        <!-- Header Balance -->
        <div class="flex justify-between items-center pb-6 border-b border-amber-500/20 relative">
            <div>
                <span class="text-[11px] text-amber-400 uppercase tracking-widest font-extrabold">VIP Chip Balance</span>
                <h2 id="balance-display" class="text-3xl font-black text-amber-300 <?= ($effect_type === 'win') ? 'animate-win' : (($effect_type === 'lose') ? 'animate-loss' : '') ?>">
                    $<?= number_format($_SESSION['balance'], 2) ?>
                </h2>
            </div>
            <div class="text-right">
                <span class="text-[10px] bg-amber-500/10 text-amber-300 border border-amber-500/40 px-3 py-1.5 rounded-full font-bold uppercase tracking-wider">VIP Table • Win +7% | Lose -3%</span>
            </div>

            <!-- Floating chip container -->
            <div id="coin-container" class="absolute left-6 top-0"></div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mt-5 p-4 rounded-2xl bg-black/40 border border-amber-500/30 text-xs font-bold text-amber-200 text-center shadow-lg animate-pulse">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Betting Screen -->
        <?php if (!isset($_SESSION['game_status']) || $_SESSION['game_status'] === 'ready'): ?>
            <form method="POST" class="mt-8 space-y-6">
                <input type="hidden" name="action" value="start">
                <div class="space-y-2">
                    <label class="block text-xs uppercase font-extrabold text-amber-400/80 tracking-wider">Enter Chip Bet Amount ($):</label>
                    <input type="number" name="bet" step="10" min="10" value="100" required
                           class="w-full bg-black/50 border border-amber-500/30 rounded-2xl px-4 py-3.5 text-amber-300 font-bold focus:outline-none focus:border-amber-400 shadow-inner">
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-yellow-600 hover:from-amber-400 hover:to-yellow-500 text-black font-black py-4 rounded-2xl uppercase tracking-wider shadow-xl shadow-amber-500/20 transition transform active:scale-95 cursor-pointer">
                    Deal Cards &rarr;
                </button>
            </form>

        <!-- Gameplay Screen -->
        <?php else: ?>
            <div class="mt-6 space-y-6">
                
                <!-- Dealer Area -->
                <div class="bg-black/30 p-4 rounded-2xl border border-emerald-500/20">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">🎲 Dealer Hand</span>
                        <span class="text-xs font-bold bg-black/40 text-amber-200 px-2.5 py-1 rounded-lg border border-amber-500/20">
                            Score: <?= ($_SESSION['game_status'] === 'ended') ? calculate_score($_SESSION['admin_cards']) : '??' ?>
                        </span>
                    </div>
                    <div class="flex gap-2.5 flex-wrap">
                        <?php foreach ($_SESSION['admin_cards'] as $index => $card): ?>
                            <?php if ($_SESSION['game_status'] !== 'ended' && $index === 1): ?>
                                <div class="card-item w-14 h-20 bg-emerald-950 border border-amber-500/40 rounded-xl flex items-center justify-center text-lg font-bold shadow-md text-amber-400">?</div>
                            <?php else: ?>
                                <div class="card-item w-14 h-20 bg-white text-slate-900 rounded-xl flex flex-col justify-between p-2 font-black shadow-md">
                                    <span class="text-xs"><?= $card['val'] ?></span>
                                    <span class="text-center text-lg"><?= $card['suit'] ?></span>
                                    <span class="text-xs self-end"><?= $card['val'] ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Player Area -->
                <div class="bg-black/30 p-4 rounded-2xl border border-emerald-500/20">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-xs font-bold text-emerald-300 uppercase tracking-wider">👤 Player Hand</span>
                        <span class="text-xs font-bold bg-emerald-950/80 text-emerald-300 px-2.5 py-1 rounded-lg border border-emerald-500/40">
                            Score: <?= calculate_score($_SESSION['user_cards']) ?>
                        </span>
                    </div>
                    <div class="flex gap-2.5 flex-wrap">
                        <?php foreach ($_SESSION['user_cards'] as $card): ?>
                            <div class="card-item w-14 h-20 bg-white text-slate-900 rounded-xl flex flex-col justify-between p-2 font-black shadow-md">
                                <span class="text-xs"><?= $card['val'] ?></span>
                                <span class="text-center text-lg"><?= $card['suit'] ?></span>
                                <span class="text-xs self-end"><?= $card['val'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Game Controls -->
                <?php if ($_SESSION['game_status'] === 'playing'): ?>
                    <form method="POST" class="flex gap-3 pt-2">
                        <button type="submit" name="action" value="hit" class="flex-1 bg-amber-500 hover:bg-amber-400 text-black font-black py-3.5 rounded-xl uppercase text-xs tracking-wider shadow-lg shadow-amber-500/20 transition cursor-pointer">
                            Hit 📥
                        </button>
                        <button type="submit" name="action" value="stand" class="flex-1 bg-emerald-600 hover:bg-emerald-500 text-white font-black py-3.5 rounded-xl uppercase text-xs tracking-wider shadow-lg shadow-emerald-600/20 transition cursor-pointer">
                            Stand 🛑
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" class="pt-2">
                        <input type="hidden" name="action" value="reset">
                        <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-yellow-600 hover:from-amber-400 hover:to-yellow-500 text-black font-black py-4 rounded-2xl uppercase text-xs tracking-widest shadow-xl shadow-amber-500/20 transition cursor-pointer">
                            New Hand 🔄
                        </button>
                    </form>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </div>

    <!-- Floating coin animation script -->
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const effectType = "<?= $effect_type ?>";
            const container = document.getElementById('coin-container');

            if (effectType === 'win') {
                for (let i = 0; i < 5; i++) {
                    setTimeout(() => {
                        const coin = document.createElement('div');
                        coin.className = 'floating-coin text-amber-400 text-lg';
                        coin.innerText = '+7%';
                        coin.style.left = (Math.random() * 60) + 'px';
                        container.appendChild(coin);
                        setTimeout(() => coin.remove(), 1200);
                    }, i * 150);
                }
            } else if (effectType === 'lose') {
                for (let i = 0; i < 3; i++) {
                    setTimeout(() => {
                        const coin = document.createElement('div');
                        coin.className = 'floating-coin text-rose-500 text-lg';
                        coin.innerText = '-3%';
                        coin.style.left = (Math.random() * 60) + 'px';
                        container.appendChild(coin);
                        setTimeout(() => coin.remove(), 1200);
                    }, i * 150);
                }
            }
        });
    </script>
</body>
</html>