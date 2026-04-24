<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user'])) { header('Location: /index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — SAP Expense System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo"><i class="bi bi-receipt-cutoff"></i></div>
        <h2>Welcome back</h2>
        <p class="sub">Sign in to SAP Expense System</p>

        <div class="login-error" id="loginError"></div>

        <form id="loginForm" autocomplete="on">
            <div class="mb-3">
                <label class="form-label">Email address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="admin@company.com" required autocomplete="email">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="input-group-text" id="togglePwd" style="cursor:pointer;border-left:none">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-lg" id="loginBtn">
                <span id="loginText"><i class="bi bi-box-arrow-in-right me-1"></i>Sign In</span>
                <span id="loginSpinner" class="d-none">
                    <span class="spinner-border spinner-border-sm me-1"></span>Signing in…
                </span>
            </button>
        </form>

        <p class="text-center mt-4" style="font-size:.8rem;color:var(--text-3)">
            Default: admin@company.com / admin123
        </p>
    </div>
</div>

<script>
document.getElementById('togglePwd').addEventListener('click', () => {
    const p = document.getElementById('password');
    const i = document.getElementById('eyeIcon');
    if (p.type === 'password') { p.type = 'text';     i.className = 'bi bi-eye-slash'; }
    else                       { p.type = 'password'; i.className = 'bi bi-eye'; }
});

document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const err = document.getElementById('loginError');
    err.style.display = 'none';
    document.getElementById('loginBtn').disabled = true;
    document.getElementById('loginText').classList.add('d-none');
    document.getElementById('loginSpinner').classList.remove('d-none');

    try {
        const res  = await fetch('/api/auth.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                email:    document.getElementById('email').value,
                password: document.getElementById('password').value,
            })
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = '/index.php';
        } else {
            err.textContent = data.error || 'Login failed.';
            err.style.display = 'block';
        }
    } catch(ex) {
        err.textContent = 'Network error. Please try again.';
        err.style.display = 'block';
    } finally {
        document.getElementById('loginBtn').disabled = false;
        document.getElementById('loginText').classList.remove('d-none');
        document.getElementById('loginSpinner').classList.add('d-none');
    }
});
</script>
</body>
</html>
