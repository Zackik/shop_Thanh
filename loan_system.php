<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Auto create loan, spam messages tables if not exists
$conn->query("CREATE TABLE IF NOT EXISTS user_loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    duration_days INT NOT NULL,
    total_due DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS spam_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$success_msg = "";
$error_msg = "";
$game_result_msg = "";
$last_dice_roll = null;

// Handle loan application form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_loan'])) {
    $amount = floatval($_POST['amount']);
    $duration_days = intval($_POST['duration_days']);
    
    if ($amount < 100 || $amount > 1000) {
        $error_msg = "The loan amount must be between $100 and $1,000 USD!";
    } elseif ($duration_days <= 0) {
        $error_msg = "Invalid loan duration period!";
    } else {
        $monthly_interest_rate = 0.05; 
        $total_interest = $amount * $monthly_interest_rate * ($duration_days / 30);
        $total_due = $amount + $total_interest;
        
        $due_date = date('Y-m-d', strtotime("+$duration_days days"));

        $stmt = $conn->prepare("INSERT INTO user_loans (user_id, amount, duration_days, total_due, due_date, status) VALUES (?, ?, ?, ?, ?, 'Active')");
        $stmt->bind_param("iddss", $user_id, $amount, $duration_days, $total_due, $due_date);
        
        if ($stmt->execute()) {
            $success_msg = "Successfully borrowed $" . number_format($amount, 2) . "! Due date: $due_date";
        } else {
            $error_msg = "System error while processing your loan application.";
        }
        $stmt->close();
    }
}

// Handle Mini-Game: Odd/Even Dice Prediction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['play_minigame'])) {
    $user_choice = $_POST['choice']; // 'even' or 'odd'
    $loan_id_to_play = intval($_POST['loan_id_to_play']);

    $verify_loan = $conn->query("SELECT * FROM user_loans WHERE id = $loan_id_to_play AND user_id = $user_id AND status = 'Active'");
    
    if ($verify_loan && $verify_loan->num_rows > 0) {
        $loan_data = $verify_loan->fetch_assoc();
        $current_total_due = $loan_data['total_due'];

        // Random dice roll from 1 to 6
        $last_dice_roll = rand(1, 6);
        $is_even = ($last_dice_roll % 2 === 0);
        $result_type = $is_even ? 'even' : 'odd';

        if ($user_choice === $result_type) {
            $new_total_due = $current_total_due * 0.95;
            $conn->query("UPDATE user_loans SET total_due = $new_total_due WHERE id = $loan_id_to_play");
            $game_result_msg = "WIN! You guessed <strong class='text-white uppercase'>" . $user_choice . "</strong>. Dice rolled <strong class='text-indigo-400'>$last_dice_roll</strong>. <span class='text-emerald-400 font-bold'>5% deducted</span> from your debt!";
        } else {
            $new_total_due = $current_total_due * 1.02;
            $conn->query("UPDATE user_loans SET total_due = $new_total_due WHERE id = $loan_id_to_play");
            $game_result_msg = "LOSE! You guessed <strong class='text-white uppercase'>" . $user_choice . "</strong>. Dice rolled <strong class='text-indigo-400'>$last_dice_roll</strong>. <span class='text-rose-400 font-bold'>2% penalty added</span> to your debt!";
        }
    } else {
        $error_msg = "Please select a valid active loan to play the mini-game!";
    }
}

// Check for overdue loans to trigger auto spam warning messages
$check_overdue = $conn->query("SELECT * FROM user_loans WHERE user_id = $user_id AND status = 'Active'");
$has_overdue = false;

while ($loan = $check_overdue->fetch_assoc()) {
    $current_date = date('Y-m-d');
    if ($current_date > $loan['due_date']) {
        $has_overdue = true;
        $loan_id = $loan['id'];
        
        $conn->query("UPDATE user_loans SET status = 'Overdue' WHERE id = $loan_id");
        
        $spam_text = "URGENT OVERDUE WARNING! Loan #$loan_id amounting to $" . $loan['total_due'] . " expired on " . $loan['due_date'] . ". Please clear your balance immediately!";
        
        $conn->query("INSERT INTO spam_messages (user_id, message) VALUES ($user_id, '$spam_text')");
    }
}

$loans_result = $conn->query("SELECT * FROM user_loans WHERE user_id = $user_id ORDER BY created_at DESC");
$active_loans_for_game = $conn->query("SELECT * FROM user_loans WHERE user_id = $user_id AND status = 'Active' ORDER BY created_at DESC");
$spam_result = $conn->query("SELECT * FROM spam_messages WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apex Credit Hub &mdash; Mini-Game Dice Challenge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            50% { transform: translateX(4px); }
            75% { transform: translateX(-4px); }
            100% { transform: translateX(0); }
        }
        .spam-alert-anim { animation: shake 0.35s ease-in-out infinite; }
        .glass-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .glow-effect:hover {
            box-shadow: 0 0 35px rgba(99, 102, 241, 0.35);
        }
        @keyframes diceShake {
            0% { transform: rotate(0deg) scale(1); }
            25% { transform: rotate(-15deg) scale(1.1); }
            50% { transform: rotate(15deg) scale(0.95); }
            75% { transform: rotate(-10deg) scale(1.05); }
            100% { transform: rotate(0deg) scale(1); }
        }
        .rolling { animation: diceShake 0.6s ease-in-out; }
    </style>
</head>
<body class="bg-[#030305] text-neutral-100 antialiased min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white">

    <!-- Glowing Background Ambient Elements -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-[120px]"></div>
        <div class="absolute top-1/3 -right-40 w-96 h-96 bg-violet-600/15 rounded-full blur-[140px]"></div>
    </div>

    <!-- Header -->
    <header class="fixed top-0 left-0 right-0 z-40 bg-[#030305]/80 backdrop-blur-xl border-b border-white/[0.06]">
        <div class="max-w-7xl mx-auto px-6 h-20 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center font-black text-white shadow-lg shadow-indigo-500/30">A</div>
                <span class="text-sm font-extrabold tracking-widest uppercase text-white">Apex Credit <span class="text-indigo-400 font-light">/</span> <span class="text-[10px] text-neutral-400 font-semibold tracking-normal">Global Vault</span></span>
            </div>
            <a href="user_dashboard.php" class="bg-white/5 hover:bg-white/10 border border-white/10 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition">Back to Dashboard</a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-6 pt-36 pb-24 w-full flex-grow space-y-10 relative z-10">
        
        <!-- Overdue Warning & Spam Log Alert -->
        <?php if ($has_overdue || ($spam_result && $spam_result->num_rows > 0)): ?>
            <div class="bg-gradient-to-r from-rose-950/90 to-red-950/90 border-2 border-rose-500/60 p-6 rounded-3xl space-y-4 shadow-2xl spam-alert-anim shadow-rose-900/30">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-rose-500/20 border border-rose-500/40 flex items-center justify-center text-xl">🚨</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-rose-300 uppercase tracking-wider">Overdue Alert & Automated Spam Warnings Active</h2>
                        <p class="text-xs text-rose-200/80">Unsettled accounts detected. Real-time system penalty transmissions:</p>
                    </div>
                </div>
                <div class="bg-black/60 rounded-2xl p-4 max-h-40 overflow-y-auto space-y-2 border border-rose-500/20 text-xs font-mono">
                    <?php while ($spam = $spam_result->fetch_assoc()): ?>
                        <div class="flex justify-between items-center border-b border-rose-500/10 pb-2 gap-4">
                            <span class="text-rose-300">&gt; <?= htmlspecialchars($spam['message']) ?></span>
                            <span class="text-[10px] text-neutral-500 whitespace-nowrap"><?= $spam['created_at'] ?></span>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Success / Error Notifications -->
        <?php if (!empty($success_msg)): ?>
            <div class="glass-card bg-emerald-950/40 border-emerald-500/30 text-emerald-400 p-4 rounded-2xl text-xs font-bold flex items-center gap-3 shadow-lg">
                <span>✅</span> <?= $success_msg ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="glass-card bg-rose-950/40 border-rose-500/30 text-rose-400 p-4 rounded-2xl text-xs font-bold flex items-center gap-3 shadow-lg">
                <span>⚠️</span> <?= $error_msg ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($game_result_msg)): ?>
            <div class="glass-card bg-indigo-950/60 border-indigo-500/40 text-indigo-200 p-5 rounded-2xl text-xs flex items-center gap-4 shadow-2xl">
                <span class="text-3xl">🎲</span> <div><?= $game_result_msg ?></div>
            </div>
        <?php endif; ?>

        <!-- MINI-GAME SECTION: ODD / EVEN DICE CHALLENGE WITH RULER & DICE VISUAL -->
        <div class="glass-card p-8 rounded-3xl shadow-2xl space-y-6 relative overflow-hidden border-indigo-500/30">
            <div class="absolute -top-12 -right-12 w-48 h-48 bg-indigo-500/15 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-1">
                    <span class="text-[10px] font-extrabold uppercase text-indigo-400 tracking-widest block">Interactive Risk Game</span>
                    <h2 class="text-xl font-extrabold text-white tracking-tight">Dealer's Dice Arena 🎲</h2>
                    <p class="text-xs text-neutral-400">Guess <strong class="text-indigo-300">Even / Odd</strong>. Win to get <strong class="text-emerald-400">-5% interest</strong>, Lose to get <strong class="text-rose-400">+2% penalty</strong>.</p>
                </div>

                <!-- Người Xúc & Hình Xúc Xắc Hiển Thị -->
                <div class="flex items-center gap-4 bg-black/40 border border-white/10 px-5 py-3 rounded-2xl">
                    <div class="text-center">
                        <span class="text-[10px] text-neutral-400 uppercase tracking-widest block font-bold">The Dealer</span>
                        <span class="text-xs text-indigo-300 font-extrabold flex items-center gap-1.5 mt-0.5">
                            <span>🤵‍♂️</span> Pit Boss
                        </span>
                    </div>
                    <div class="h-8 w-[1px] bg-white/10"></div>
                    <div class="text-center">
                        <span class="text-[10px] text-neutral-400 uppercase tracking-widest block font-bold">Dice Result</span>
                        <div id="dice-container" class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white font-mono font-black text-base shadow-lg shadow-indigo-500/40 mt-0.5 <?= $last_dice_roll ? 'rolling' : '' ?>">
                            <?= $last_dice_roll ? $last_dice_roll : '🎲' ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($active_loans_for_game && $active_loans_for_game->num_rows > 0): ?>
                <form method="POST" class="space-y-5 pt-2" id="game-form" onsubmit="triggerRolling()">
                    <div>
                        <label class="block text-[11px] uppercase text-neutral-300 mb-2 font-bold tracking-wider">Select Active Loan for This Roll:</label>
                        <select name="loan_id_to_play" class="w-full bg-black/50 border border-white/10 rounded-2xl px-4 py-3.5 text-xs text-white focus:outline-none focus:border-indigo-500 transition shadow-inner font-mono">
                            <?php while ($active_loan = $active_loans_for_game->fetch_assoc()): ?>
                                <option value="<?= $active_loan['id'] ?>">
                                    Loan #<?= $active_loan['id'] ?> &mdash; Principal: $<?= number_format($active_loan['amount'], 2) ?> | Current Due: $<?= number_format($active_loan['total_due'], 2) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <button type="submit" name="play_minigame" value="1" onclick="setChoice('even')" class="bg-indigo-950/60 hover:bg-indigo-600 border border-indigo-500/40 text-indigo-200 hover:text-white font-extrabold py-4 rounded-2xl uppercase text-xs tracking-widest transition shadow-lg flex items-center justify-center gap-2 group cursor-pointer">
                            <span>🪙</span> Bet EVEN (Chẵn)
                        </button>
                        <button type="submit" name="play_minigame" value="1" onclick="setChoice('odd')" class="bg-violet-950/60 hover:bg-violet-600 border border-violet-500/40 text-violet-200 hover:text-white font-extrabold py-4 rounded-2xl uppercase text-xs tracking-widest transition shadow-lg flex items-center justify-center gap-2 group cursor-pointer">
                            <span>🪙</span> Bet ODD (Lẻ)
                        </button>
                    </div>
                    <input type="hidden" name="choice" id="choice_input" value="even">
                </form>
            <?php else: ?>
                <div class="bg-black/40 border border-white/10 p-4 rounded-2xl text-center text-xs text-neutral-400 uppercase tracking-wider">
                    You need an active loan to play the mini-game. Apply below!
                </div>
            <?php endif; ?>
        </div>

        <!-- Loan Application Form Section -->
        <div class="glass-card p-8 rounded-3xl shadow-2xl space-y-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="space-y-1">
                <span class="text-[10px] font-extrabold uppercase text-indigo-400 tracking-widest block">Instant Liquidity</span>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Quick Credit Line ($100 &ndash; $1,000 USD)</h1>
                <p class="text-xs text-neutral-400">Competitive flat rate of 5% monthly, automatically calculated based on your custom active duration.</p>
            </div>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-[11px] uppercase text-neutral-300 mb-2 font-bold tracking-wider">Desired Amount (USD):</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-400 font-mono text-sm">$</span>
                        <input type="number" name="amount" min="100" max="1000" step="1" required 
                               placeholder="Enter amount between 100 and 1000"
                               class="w-full bg-black/50 border border-white/10 rounded-2xl pl-9 pr-4 py-3.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono transition shadow-inner">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] uppercase text-neutral-300 mb-2 font-bold tracking-wider">Repayment Period:</label>
                    <select name="duration_days" class="w-full bg-black/50 border border-white/10 rounded-2xl px-4 py-3.5 text-xs text-white focus:outline-none focus:border-indigo-500 transition shadow-inner">
                        <option value="30">30 Days (1 Month &mdash; 5% Interest)</option>
                        <option value="60">60 Days (2 Months &mdash; 10% Interest)</option>
                        <option value="90">90 Days (3 Months &mdash; 15% Interest)</option>
                        <option value="180">180 Days (6 Months &mdash; 30% Interest)</option>
                    </select>
                </div>

                <button type="submit" name="apply_loan" class="w-full bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold py-4 rounded-2xl uppercase text-xs tracking-widest transition shadow-xl glow-effect">
                    Request Loan Now &rarr;
                </button>
            </form>
        </div>

        <!-- Loan History Section -->
        <div class="glass-card p-8 rounded-3xl shadow-2xl space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-sm font-extrabold text-white uppercase tracking-wider">Active & Historical Loans</h2>
                <span class="text-[10px] text-neutral-400 font-mono uppercase tracking-widest">Secured Archive</span>
            </div>
            
            <div class="overflow-x-auto rounded-2xl border border-white/10 bg-black/40">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-white/[0.03] text-neutral-400 uppercase tracking-widest text-[10px] border-b border-white/10">
                            <th class="p-3.5 font-semibold">Loan ID</th>
                            <th class="p-3.5 font-semibold">Principal</th>
                            <th class="p-3.5 font-semibold">Total Due (Principal + Interest)</th>
                            <th class="p-3.5 font-semibold">Due Date</th>
                            <th class="p-3.5 text-right font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-neutral-200">
                        <?php if ($loans_result && $loans_result->num_rows > 0): ?>
                            <?php while ($loan = $loans_result->fetch_assoc()): ?>
                                <tr class="hover:bg-white/[0.02] transition">
                                    <td class="p-3.5 font-mono font-bold text-white">#<?= $loan['id'] ?></td>
                                    <td class="p-3.5 font-mono text-neutral-400">$<?= number_format($loan['amount'], 2) ?></td>
                                    <td class="p-3.5 font-mono font-bold text-indigo-400">$<?= number_format($loan['total_due'], 2) ?></td>
                                    <td class="p-3.5 font-mono text-neutral-400"><?= $loan['due_date'] ?></td>
                                    <td class="p-3.5 text-right">
                                        <?php if ($loan['status'] === 'Active'): ?>
                                            <span class="bg-amber-950/60 border border-amber-500/30 text-amber-400 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">Active</span>
                                        <?php elseif ($loan['status'] === 'Overdue'): ?>
                                            <span class="bg-rose-950/80 border border-rose-500/40 text-rose-400 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider animate-pulse">Overdue</span>
                                        <?php else: ?>
                                            <span class="bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">Settled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-neutral-500 uppercase tracking-widest text-[11px]">No active credit applications found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="text-center py-8 bg-[#030305] border-t border-white/[0.06] text-neutral-500 text-[10px] tracking-widest uppercase relative z-10">
        <p>&copy; 2026 Apex Credit Global &mdash; Secure Financial Architecture</p>
    </footer>

    <!-- JavaScript for Dice Rolling Animation Effect -->
    <script>
        function setChoice(val) {
            document.getElementById('choice_input').value = val;
        }

        function triggerRolling() {
            const diceEl = document.getElementById('dice-container');
            diceEl.classList.add('rolling');
            diceEl.innerHTML = '🎲';
        }
    </script>
</body>
</html>