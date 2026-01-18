<?php
require_once 'config/app.php';
require_once 'models/User.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

$userModel = new User();
$user = $userModel->getUserByResetToken($token);

if (!$user && empty($success)) {
    $error = "This password reset link is invalid or has expired.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $pass1 = $_POST['password'];
    $pass2 = $_POST['confirm_password'];
    
    if (strlen($pass1) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($pass1 !== $pass2) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        if ($userModel->updatePassword($user['id'], $hash)) {
            $userModel->clearResetToken($user['id']);
            $success = "Password has been reset successfully! You can now login.";
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Proxy System'; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4F46E5 0%, #312E81 100%);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
        }
        
        .reset-card {
            width: 100%;
            max-width: 450px;
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        
        .btn-primary {
            background: #4F46E5;
            border: none;
            padding: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #4F46E5;
        }
        
        .form-control:focus {
            border-color: #4F46E5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
    </style>
</head>
<body>

<div class="reset-card">
    <div class="text-center mb-4">
        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-primary" style="width: 70px; height: 70px;">
            <i class="fas fa-key fa-2x"></i>
        </div>
        <h3 class="fw-bold text-dark">Set New Password</h3>
        <?php if ($user): ?>
             <p class="text-muted small">for <?php echo htmlspecialchars($user['email']); ?></p>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success border-0 d-flex align-items-center mb-4 shadow-sm" style="background-color: #ECFDF5; color: #065F46;" role="alert">
            <i class="fas fa-check-circle me-3 fs-5"></i>
            <div><?php echo $success; ?></div>
        </div>
        <div class="d-grid">
            <a href="login.php" class="btn btn-primary rounded-3">Go to Login</a>
        </div>
    <?php elseif ($error && !$user): ?>
        <div class="alert alert-danger border-0 d-flex align-items-center mb-4 shadow-sm" style="background-color: #FEF2F2; color: #991B1B;" role="alert">
            <i class="fas fa-exclamation-circle me-3 fs-5"></i>
            <div><?php echo $error; ?></div>
        </div>
        <div class="d-grid">
            <a href="login.php" class="btn btn-outline-secondary rounded-3">Back to Login</a>
        </div>
    <?php else: ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="pass1" name="password" placeholder="New Password" required minlength="6">
                <label for="pass1">New Password</label>
            </div>
            <div class="form-floating mb-4">
                <input type="password" class="form-control" id="pass2" name="confirm_password" placeholder="Confirm Password" required minlength="6">
                <label for="pass2">Confirm Password</label>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary btn-lg rounded-3">
                    Reset Password
                </button>
            </div>
            <div class="text-center">
                 <a href="login.php" class="text-decoration-none text-muted small">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
