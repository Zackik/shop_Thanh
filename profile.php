<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = ""; // 'success' hoặc 'error'

// Fetch user information from the database
$stmt = $conn->prepare("SELECT full_name, email, profile_picture, password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'] ?? '';
    $otp_code = trim($_POST['otp_code'] ?? '');

    // Kiểm tra tính hợp lệ của Mật khẩu hiện tại và mã 2FA chung cho mọi hành động thay đổi
    if (empty($current_password) || empty($otp_code)) {
        $message = "Please enter your Current Password and 2FA Security PIN to authorize changes.";
        $message_type = "error";
    } elseif (!password_verify($current_password, $user['password'])) {
        $message = "Incorrect current password. Action denied.";
        $message_type = "error";
    } elseif ($otp_code !== "1234") { // Giả lập mã PIN 2FA bảo mật của hệ thống
        $message = "Invalid 2FA Security PIN. (Default test PIN: 1234)";
        $message_type = "error";
    } else {
        // 1. Cập nhật Profile (Tên, Email, Ảnh đại diện)
        if (isset($_POST['update_profile'])) {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);

            if (!empty($_FILES['profile_picture']['name'])) {
                $target_dir = "profile_picture/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                $target_file = $target_dir . time() . "_" . basename($_FILES["profile_picture"]["name"]);
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $allowedTypes = array('jpg', 'png', 'jpeg', 'gif');

                if (in_array($imageFileType, $allowedTypes)) {
                    if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, profile_picture = ? WHERE id = ?");
                        $stmt->bind_param("sssi", $name, $email, $target_file, $user_id);
                    } else {
                        $message = "Error uploading profile picture.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                    $message_type = "error";
                }
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $email, $user_id);
            }

            if ($message_type !== "error" && isset($stmt) && $stmt->execute()) {
                $message = "Profile updated successfully!";
                $message_type = "success";
                $stmt->close();
            }
        }

        // 2. Đổi mật khẩu mới
        if (isset($_POST['change_password'])) {
            $new_password = $_POST['new_password'] ?? '';
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                if ($stmt->execute()) {
                    $message = "Password changed successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating password.";
                    $message_type = "error";
                }
                $stmt->close();
            } else {
                $message = "New password cannot be empty.";
                $message_type = "error";
            }
        }

        // 3. Xóa tài khoản
        if (isset($_POST['delete_account'])) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $stmt->close();
                session_destroy();
                header("Location: register.php");
                exit();
            } else {
                $message = "Error deleting account.";
                $message_type = "error";
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Buy 🛒 - Secure Profile Settings</title>
    <link rel="icon" href="favicon.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>

<body class="bg-black text-white antialiased min-h-screen flex flex-col justify-between selection:bg-neutral-200 selection:text-black relative">
    
    <!-- Cinematic Background Glow -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[350px] bg-white/[0.03] blur-[140px] pointer-events-none rounded-full"></div>

    <!-- Header -->
    <header class="sticky top-0 z-40 backdrop-blur-xl bg-black/40 border-b border-white/10 transition-all">
        <div class="max-w-7xl mx-auto px-6 h-20 flex justify-between items-center">
            <div class="text-xl font-bold tracking-widest uppercase text-white">
                <a href="user_dashboard.php">Thanh Buy 🛒</a>
            </div>
            <div>
                <a href="logout.php" class="bg-neutral-900 border border-white/20 text-white px-5 py-2.5 rounded-full font-medium hover:bg-white hover:text-black hover:border-white transition-all text-xs tracking-wider uppercase">Logout</a>
            </div>
        </div>
    </header>

    <!-- Profile Section -->
    <main class="flex-grow max-w-4xl w-full mx-auto px-6 py-12 space-y-8 relative z-10">
        
        <div class="bg-neutral-950 p-8 sm:p-10 rounded-3xl border border-white/10 shadow-2xl">
            <div class="text-center mb-8">
                <span class="text-neutral-400 text-xs font-semibold tracking-widest uppercase mb-2 block">Account Security Management</span>
                <h1 class="text-3xl font-extrabold text-white tracking-tight">Welcome, <?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>!</h1>
                <p class="text-neutral-400 text-xs tracking-wide uppercase mt-2">Update your profile settings securely with password & 2FA validation.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-2xl border text-xs tracking-wide uppercase text-center font-bold <?php echo $message_type === 'success' ? 'bg-emerald-950/60 border-emerald-500/40 text-emerald-300' : 'bg-rose-950/60 border-rose-500/40 text-rose-300'; ?>">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                
                <!-- Profile Information Fields -->
                <div class="space-y-4">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-neutral-300 border-b border-white/10 pb-2">1. Personal Information</h2>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-2">Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>" required 
                               class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-white/40 transition text-xs tracking-wide">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-2">Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" required 
                               class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-white/40 transition text-xs tracking-wide">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-2">Profile Picture</label>
                        <input type="file" name="profile_picture" 
                               class="w-full px-4 py-3 bg-neutral-900 border border-white/10 rounded-2xl text-neutral-400 text-xs file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-neutral-800 file:text-white hover:file:bg-neutral-700 transition">
                    </div>
                </div>

                <!-- Security Verification Section (Password & 2FA required for any updates) -->
                <div class="space-y-4 pt-4 border-t border-white/10">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-emerald-400 border-b border-white/10 pb-2">2. Mandatory Security Verification</h2>
                    <p class="text-[11px] text-neutral-400">To apply any changes or perform actions below, you must confirm your current password and 2FA security PIN.</p>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="current_password" class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-2">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" placeholder="••••••••" required 
                                   class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-white/40 transition text-xs tracking-wide">
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label for="otp_code" class="text-xs font-bold uppercase tracking-widest text-emerald-400">2FA Security PIN *</label>
                                <span class="text-[10px] text-neutral-500 uppercase tracking-widest">Test PIN: 1234</span>
                            </div>
                            <input type="password" id="otp_code" name="otp_code" maxlength="4" placeholder="••••" required 
                                   class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white font-mono tracking-widest focus:outline-none focus:border-emerald-500/50 transition text-xs">
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-4 pt-4">
                    <button type="submit" name="update_profile" class="w-full bg-white text-black font-semibold py-4 rounded-2xl shadow-lg hover:bg-neutral-200 transition-all text-xs uppercase tracking-widest">Update Profile</button>
                </div>
            </form>

            <!-- Change Password Form Block (Uses same security context above) -->
            <div class="mt-10 pt-8 border-t border-white/10 space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-widest text-neutral-300">Change Password</h2>
                <form method="POST" action="" class="space-y-4">
                    <div>
                        <input type="password" name="new_password" placeholder="New Password" required 
                               class="w-full px-4 py-3.5 bg-neutral-900 border border-white/10 rounded-2xl text-white placeholder:text-neutral-600 focus:outline-none focus:border-white/40 transition text-xs tracking-wide">
                    </div>
                    <!-- Yêu cầu bảo mật chung: Cần điền lại xác thực ở form phía trên hoặc tích hợp trực tiếp -->
                    <p class="text-[11px] text-neutral-500">* Note: Changing password also requires filling out the Current Password & 2FA PIN security fields above.</p>
                    <button type="submit" name="change_password" class="w-full bg-neutral-800 border border-white/10 text-white font-semibold py-4 rounded-2xl hover:bg-neutral-700 transition-all text-xs uppercase tracking-widest">Change Password</button>
                </form>
            </div>

            <!-- Delete Account Form Block -->
            <div class="mt-10 pt-8 border-t border-rose-500/30 space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-widest text-rose-400">Danger Zone</h2>
                <form method="POST" action="">
                    <button type="submit" name="delete_account" class="w-full bg-rose-950/80 border border-rose-500/40 text-rose-300 font-semibold py-4 rounded-2xl hover:bg-rose-900 transition-all text-xs uppercase tracking-widest"
                        onclick="return confirm('Are you sure? This action cannot be undone and requires valid security credentials!')">Delete Account</button>
                </form>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="text-center py-8 bg-black border-t border-white/10 text-neutral-500 text-xs tracking-widest uppercase mt-12">
        <p>&copy; 2026 Thanh Buy 🛒 &mdash; All Rights Reserved</p>
    </footer>
</body>

</html>