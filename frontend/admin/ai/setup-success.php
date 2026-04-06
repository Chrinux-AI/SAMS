<?php
/**
 * SAMS AI Setup Success Page
 * Shows confirmation after successful password setup
 */

require_once __DIR__ . '/../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Complete - SAMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 40px 30px;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: #10b981;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .message {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            color: #065f46;
        }
        
        .message h3 {
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .features {
            text-align: left;
            margin-bottom: 30px;
        }
        
        .features h3 {
            margin-bottom: 15px;
            color: #374151;
            font-size: 18px;
        }
        
        .features ul {
            list-style: none;
            padding: 0;
        }
        
        .features li {
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
        }
        
        .features li:last-child {
            border-bottom: none;
        }
        
        .features li:before {
            content: "✓";
            background: #10b981;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 0 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 10px 20px rgba(107, 114, 128, 0.3);
        }
        
        .footer {
            background: #f8fafc;
            padding: 20px;
            color: #6b7280;
            font-size: 14px;
        }
        
        .footer a {
            color: #4F46E5;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .container {
                margin: 10px;
            }
            
            .content {
                padding: 30px 20px;
            }
            
            .btn {
                display: block;
                margin: 10px 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">✓</div>
            <h1>Setup Complete!</h1>
            <p>Your account has been successfully created and activated.</p>
        </div>
        
        <div class="content">
            <div class="message">
                <h3>Welcome to SAMS!</h3>
                <p>Your account is now ready to use. You can log in with your email and the password you just created.</p>
            </div>
            
            <div class="features">
                <h3>What's Next?</h3>
                <ul>
                    <li>Log in to your new account</li>
                    <li>Complete your profile information</li>
                    <li>Explore the dashboard features</li>
                    <li>Check your notifications</li>
                    <li>Contact support if you need help</li>
                </ul>
            </div>
            
            <div class="actions">
                <a href="<?php echo BASE_URL; ?>/login.php" class="btn">Log In Now</a>
                <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-secondary">Visit Homepage</a>
            </div>
        </div>
        
        <div class="footer">
            <p>Need help? <a href="<?php echo BASE_URL; ?>/support">Contact Support</a></p>
        </div>
    </div>
    
    <script>
        // Auto-redirect after 10 seconds
        setTimeout(function() {
            window.location.href = '<?php echo BASE_URL; ?>/login.php';
        }, 10000);
        
        // Countdown timer
        let countdown = 10;
        const countdownElement = document.createElement('div');
        countdownElement.style.cssText = 'margin-top: 20px; color: #6b7280; font-size: 14px;';
        countdownElement.innerHTML = 'Redirecting to login in <strong>' + countdown + '</strong> seconds...';
        document.querySelector('.actions').appendChild(countdownElement);
        
        setInterval(function() {
            countdown--;
            if (countdown > 0) {
                countdownElement.innerHTML = 'Redirecting to login in <strong>' + countdown + '</strong> seconds...';
            } else {
                countdownElement.innerHTML = 'Redirecting...';
            }
        }, 1000);
    </script>
</body>
</html>
