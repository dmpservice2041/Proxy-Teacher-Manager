<?php
require_once 'config/app.php';
require_once 'models/User.php';
require_once 'services/MailService.php';

$schoolName = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Proxy System';
$schoolLogo = null;

try {
    require_once 'models/Settings.php';
    $settingsModel = new Settings();
    $settings = $settingsModel->getAll();
    
    if (!empty($settings['school_name'])) {
        $schoolName = $settings['school_name'];
    }
    if (!empty($settings['school_logo'])) {
        $schoolLogo = $settings['school_logo'];
    }
} catch (Throwable $e) {
    // Fallback to constants if database fetch fails
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $userModel = new User();
    $result = $userModel->login($username, $password);
    
    // Handle rate limiting response
    if (is_array($result)) {
        $error = $result['message'];
    } elseif ($result === true) {
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    // DEBUG: Remove after fixing
    error_log("Reset requested for: " . $_POST['email']);
    
    $email = trim($_POST['email'] ?? '');
    $userModel = new User();
    $user = $userModel->getUserByEmail($email);

    if ($user) {
        try {
            $token = bin2hex(random_bytes(32));
            $userModel->setResetToken($email, $token);
            
            $resetLink = BASE_URL . "reset_password.php?token=" . $token;
            // ... (rest of content)
            $subject = "Password Reset Request";
            $body = "
                <h3>Password Reset Request</h3>
                <p>Hello,</p>
                <p>You requested to reset your password for " . SCHOOL_NAME . ".</p>
                <p>Click the link below to reset it (valid for 1 hour):</p>
                <p><a href='$resetLink' style='background:#4F46E5;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Reset Password</a></p>
                <p>If you did not request this, please ignore this email.</p>
            ";

            $mailService = new MailService();
            if ($mailService->send($email, $subject, $body)) {
                $success = "Password reset link has been sent to your email.";
            } else {
                $error = "Failed to send email. Please check server logs.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        // For now, simple message
        $success = "If an account matches that email, a reset link has been sent.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxy Manager</title>
    
    <!-- Favicon -->
    <?php if ($schoolLogo): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($schoolLogo); ?>">
    <?php endif; ?>
    
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
            overflow: hidden;
            background: #fff;
        }
        
        .login-wrapper {
            height: 100vh;
        }
        
        /* Left Section: Feature Showcase */
        .feature-section {
            background: var(--primary-gradient);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .feature-content {
            position: relative;
            z-index: 2;
            padding: 4rem;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin-top: 3rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            opacity: 0;
            animation: slideIn 0.5s ease forwards;
        }
        
        .feature-icon {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-right: 1.5rem;
        }
        
        .feature-item:nth-child(1) { animation-delay: 0.2s; }
        .feature-item:nth-child(2) { animation-delay: 0.4s; }
        .feature-item:nth-child(3) { animation-delay: 0.6s; }
        
        /* Decorative Circles */
        .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            z-index: 1;
        }
        .circle-1 { width: 400px; height: 400px; top: -100px; right: -100px; }
        .circle-2 { width: 300px; height: 300px; bottom: -50px; left: -50px; }
        
        /* Right Section: Login Form */
        .form-section {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #ffffff;
        }
        
        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 2rem;
            transition: all 0.3s ease;
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

        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .hidden { display: none; }
    </style>
</head>
<body>

<div class="row g-0 login-wrapper">
    <!-- Left Side: Feature Showcase -->
    <div class="col-lg-7 d-none d-lg-flex feature-section flex-column justify-content-center">
        <div class="circle circle-1"></div>
        <div class="circle circle-2"></div>
        
        <div class="feature-content">
            <h1 class="display-4 fw-bold mb-3">Welcome Back!</h1>
            <p class="lead opacity-75 mb-5">Manage your school's staffing and substitution needs seamlessly with our advanced proxy management system.</p>
            
            <ul class="feature-list">
                <li class="feature-item">
                    <div class="feature-icon"><i class="fas fa-magic"></i></div>
                    <div>
                        <h5 class="fw-bold mb-1">Smart Allocation</h5>
                        <p class="small opacity-75 mb-0">Automated clash-free proxy teacher assignment.</p>
                    </div>
                </li>
                <li class="feature-item">
                    <div class="feature-icon"><i class="fas fa-chart-pie"></i></div>
                    <div>
                        <h5 class="fw-bold mb-1">Real-time Insights</h5>
                        <p class="small opacity-75 mb-0">Live dashboards for attendance analytics and teacher load.</p>
                    </div>
                </li>
                <li class="feature-item">
                    <div class="feature-icon"><i class="fas fa-file-invoice"></i></div>
                    <div>
                        <h5 class="fw-bold mb-1">Comprehensive Reports</h5>
                        <p class="small opacity-75 mb-0">Detailed monthly logs and payroll-ready exports.</p>
                    </div>
                </li>
            </ul>
        </div>
    </div>

<?php
// ... determines visibility based on logic
$showForgot = isset($_POST['request_reset']);
?>
    <!-- Right Side: Login Form -->
    <div class="col-lg-5 form-section">
        <!-- Login Card -->
        <div class="login-card <?php echo $showForgot ? 'hidden' : ''; ?>" id="loginCard">
            <div class="text-center mb-5">
                <?php if ($schoolLogo): ?>
                    <img src="<?php echo htmlspecialchars($schoolLogo); ?>" alt="<?php echo htmlspecialchars($schoolName); ?>" class="mb-3" style="max-width: 120px; max-height: 120px; object-fit: contain;">
                <?php else: ?>
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-primary" style="width: 80px; height: 80px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                        <i class="fas fa-school fa-3x"></i>
                    </div>
                <?php endif; ?>
                <h2 class="fw-bold text-dark mb-1" style="font-size: clamp(1.25rem, 2.5vw, 1.75rem); line-height: 1.2; word-wrap: break-word;"><?php echo htmlspecialchars($schoolName); ?></h2>
                <p class="text-muted">Enter your credentials to access the portal.</p>
            </div>

            <?php if ($error && !$showForgot): ?>
                <div class="alert alert-danger border-0 d-flex align-items-center mb-4 shadow-sm" style="background-color: #FEF2F2; color: #991B1B;" role="alert">
                    <i class="fas fa-exclamation-circle me-3 fs-5"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>
             <?php if ($success && !$showForgot): ?>
                <div class="alert alert-success border-0 d-flex align-items-center mb-4 shadow-sm" style="background-color: #ECFDF5; color: #065F46;" role="alert">
                    <i class="fas fa-check-circle me-3 fs-5"></i>
                    <div><?php echo $success; ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <input type="hidden" name="login" value="1">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="floatingInput" name="username" placeholder="Username or Email" required autofocus autocomplete="username">
                    <label for="floatingInput">Username or Email</label>
                </div>
                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="floatingPassword" name="password" placeholder="Password" required autocomplete="current-password">
                    <label for="floatingPassword">Password</label>
                </div>

                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-primary btn-lg rounded-3">
                        Sign In <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </form>
            
            <div class="text-center mt-4">
                <a href="#" class="text-decoration-none text-muted small" onclick="toggleForms()">Forgot password?</a>
            </div>
        </div>
        
        <!-- Forgot Password Card -->
        <div class="login-card <?php echo $showForgot ? '' : 'hidden'; ?>" id="forgotCard">
             <div class="text-center mb-5">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-primary" style="width: 80px; height: 80px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                    <i class="fas fa-envelope fa-3x"></i>
                </div>
                <h2 class="fw-bold text-dark mb-1">Reset Password</h2>
                <p class="text-muted">Enter your email to receive a reset link.</p>
            </div>
            
            <?php if ($error && $showForgot): ?>
                <div class="alert alert-danger border-0 d-flex align-items-center mb-4 shadow-sm" style="background-color: #FEF2F2; color: #991B1B;" role="alert">
                    <i class="fas fa-exclamation-circle me-3 fs-5"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>
             <?php if ($success && $showForgot): ?>
                <div class="alert alert-success border-0 d-flex align-items-center mb-4 shadow-sm" style="background-color: #ECFDF5; color: #065F46;" role="alert">
                    <i class="fas fa-check-circle me-3 fs-5"></i>
                    <div><?php echo $success; ?></div>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="request_reset" value="1">
                <div class="form-floating mb-4">
                    <input type="email" class="form-control" id="floatingEmail" name="email" placeholder="name@example.com" required>
                    <label for="floatingEmail">Email Address</label>
                </div>

                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-primary btn-lg rounded-3">
                        Send Link <i class="fas fa-paper-plane ms-2"></i>
                    </button>
                </div>
            </form>
            
            <div class="text-center mt-4">
                <a href="#" class="text-decoration-none text-muted small" onclick="toggleForms()"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleForms() {
        const loginCard = document.getElementById('loginCard');
        const forgotCard = document.getElementById('forgotCard');
        
        loginCard.classList.toggle('hidden');
        forgotCard.classList.toggle('hidden');
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
