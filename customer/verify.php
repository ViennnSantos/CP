<?php
// Verification Code Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../backend/config/database.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - RADS Tooling</title>
    <link rel="stylesheet" href="/assets/CSS/login.css">
    <style>
        .verify-container {
            max-width: 450px;
            margin: 50px auto;
            padding: 2.5rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(47, 91, 136, 0.1);
            text-align: center;
        }

        .code-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 2rem 0;
        }

        .code-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            border: 2px solid #e3edfb;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .code-input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(47, 91, 136, 0.1);
            outline: none;
        }

        .timer {
            color: #718096;
            font-size: 0.9rem;
            margin: 1rem 0;
        }

        .resend-btn {
            background: none;
            border: 1px solid var(--brand);
            color: var(--brand);
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
        }

        .resend-btn:hover:not(:disabled) {
            background: var(--brand);
            color: white;
        }

        .resend-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
    <main class="auth-wrap">
        <div class="verify-container">
            <h2 style="color: var(--brand); margin-bottom: 1rem;">Email Verification</h2>
            <p style="color: #666; margin-bottom: 2rem;">
                Enter the 6-digit code we sent to your email
            </p>

            <form id="verifyForm">
                <div class="code-inputs">
                    <input type="text" class="code-input" maxlength="1" pattern="[0-9]" required>
                    <input type="text" class="code-input" maxlength="1" pattern="[0-9]" required>
                    <input type="text" class="code-input" maxlength="1" pattern="[0-9]" required>
                    <input type="text" class="code-input" maxlength="1" pattern="[0-9]" required>
                    <input type="text" class="code-input" maxlength="1" pattern="[0-9]" required>
                    <input type="text" class="code-input" maxlength="1" pattern="[0-9]" required>
                </div>

                <input type="hidden" id="email" value="">

                <button type="submit" class="btn-primary" style="width: 100%;">Verify Email</button>

                <div class="timer" id="timer">Resend code in <span id="countdown">600</span> seconds</div>
                <button type="button" class="resend-btn" id="resendBtn" disabled>Resend Code</button>

                <div class="error-message" id="errorMessage" style="margin-top: 1rem;"></div>
                <div class="loading" id="loadingMessage" style="margin-top: 1rem;">Verifying...</div>
            </form>

            <p style="margin-top: 2rem; font-size: 0.9rem;">
                <a href="/customer/register.php" style="color: var(--brand);">Back to Registration</a>
            </p>
        </div>
    </main>

    <script>
        // Modal fallback: use showModal() if present, else alert()
        // Modal proxy: tawagin ang showModal kapag ready na
        function modal(opts = {}) {
            if (window.showModal) return window.showModal(opts);
            // hintayin next tick (defined later sa page)
            setTimeout(() => window.showModal && window.showModal(opts), 0);
        }

        // Auto-focus next input
        const inputs = document.querySelectorAll('.code-input');
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });

        // Timer for resend (3 minutes)
        let timeLeft = 180; // 3 minutes
        const timerElement = document.getElementById('countdown');
        const resendBtn = document.getElementById('resendBtn');
        const timerContainer = document.getElementById('timer');

        // Initialize display as mm:ss
        (function initTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        })();

        function updateTimer() {
            if (timeLeft > 0) {
                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            } else {
                clearInterval(timerInterval);
                timerContainer.style.display = 'none';
                resendBtn.disabled = false;
            }
        }
        const timerInterval = setInterval(updateTimer, 1000);


        // Get email from URL or session
        const urlParams = new URLSearchParams(window.location.search);
        const email = urlParams.get('email') || sessionStorage.getItem('verify_email');
        if (email) {
            document.getElementById('email').value = email;
        }

        // Verify form submission
        document.getElementById('verifyForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const code = Array.from(inputs).map(input => input.value).join('');
            const errorDiv = document.getElementById('errorMessage');
            const loadingDiv = document.getElementById('loadingMessage');
            const submitBtn = e.target.querySelector('.btn-primary');

            // Reset UI
            errorDiv.style.display = 'none';
            loadingDiv.style.display = 'block';
            submitBtn.disabled = true;

            if (code.length !== 6) {
                loadingDiv.style.display = 'none';
                submitBtn.disabled = false;
                errorDiv.textContent = 'Please enter all 6 digits';
                errorDiv.style.display = 'block';
                return;
            }

            try {
                const response = await fetch('/backend/api/auth.php?action=verify_email', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        email: document.getElementById('email').value,
                        code
                    })
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Verification failed');
                }

                // 
                // ✅ Success — always go back to login
                try {
                    sessionStorage.removeItem('verify_email');
                } catch (_) {}

                const loginUrl = '/customer/cust_login.php';

                if (window.showModal) {
                    modal({
                        title: 'Verified!',
                        html: 'Email verified successfully. Redirecting to sign in…',
                        type: 'success',
                        actions: [{
                            label: 'OK',
                            cls: 'rt-btn rt-btn--primary',
                            onClick: () => location.href = loginUrl
                        }]
                    });
                    setTimeout(() => location.href = loginUrl, 1200);

                } else {
                    location.href = loginUrl;
                }
                return;

            } catch (error) {
                errorDiv.textContent = error.message;
                errorDiv.style.display = 'block';
            } finally {
                loadingDiv.style.display = 'none';
                submitBtn.disabled = false;
            }
        });


        // Resend code
        document.getElementById('resendBtn').addEventListener('click', async () => {
            const emailVal = document.getElementById('email').value;
            if (!emailVal) {
                modal({
                    title: 'Missing email',
                    html: 'Email not found. Please register again.'
                });
                return;
            }

            try {
                const response = await fetch('/backend/api/auth.php?action=resend_verification', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        email: emailVal
                    })
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to resend code');
                }

                // Notify and reset timer to 3:00
                modal({
                    title: 'Code sent',
                    html: 'A new verification code has been sent to your email.'
                });
                timeLeft = 180;
                timerContainer.style.display = 'block';
                resendBtn.disabled = true;
                // Immediately update display to 3:00
                const minutes = Math.floor(timeLeft / 60),
                    seconds = timeLeft % 60;
                timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            } catch (error) {
                modal({
                    title: 'Error',
                    html: (error.message || 'Error resending code. Please try again.')
                });
            }
        });
    </script>

    <!-- Reusable App Modal -->
    <div id="appModal" class="app-modal" style="display:none">
        <div class="app-modal__dialog">
            <div class="app-modal__header">
                <h3 id="appModalTitle">Notice</h3>
                <button type="button" class="app-modal__close" data-appmodal-close aria-label="Close">&times;</button>
            </div>
            <div class="app-modal__body" id="appModalBody"></div>
            <div class="app-modal__footer" id="appModalFooter">
                <button type="button" class="btn btn-primary" data-appmodal-close>OK</button>
            </div>
        </div>
    </div>

    <style>
        .app-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .app-modal__dialog {
            width: min(520px, 92vw);
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .2);
        }

        .app-modal__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            border-bottom: 1px solid #eee;
        }

        .app-modal__body {
            padding: 16px 18px;
        }

        .app-modal__footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding: 12px 18px;
            border-top: 1px solid #eee;
        }

        .app-modal__close {
            border: none;
            background: transparent;
            font-size: 22px;
            cursor: pointer;
            line-height: 1;
        }

        .btn {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #ddd;
            background: #f7f7f7;
            cursor: pointer;
        }

        .btn-primary {
            background: #2f5b88;
            color: #fff;
            border-color: #2f5b88;
        }
    </style>

    <script>
        (function() {
            const el = document.getElementById('appModal');
            const ttl = document.getElementById('appModalTitle');
            const body = document.getElementById('appModalBody');
            const ftr = document.getElementById('appModalFooter');

            if (!el || !ttl || !body || !ftr) {
                console.error('Modal elements not found');
                return;
            }

            function close() {
                el.style.display = 'none';
                ftr.innerHTML = '<button type="button" class="btn btn-primary" data-appmodal-close>OK</button>';
            }

            function open() {
                el.style.display = 'flex';
            }

            document.addEventListener('click', (e) => {
                if (e.target.matches('[data-appmodal-close]') || e.target === el) close();
            });

            // Global API
            window.showModal = function({
                title = 'Notice',
                html = '',
                actions,
                type = 'info'
            } = {}) {
                ttl.textContent = title;
                body.innerHTML = html;
                ftr.innerHTML = '';

                (actions && actions.length ? actions : [{
                    label: 'OK',
                    cls: 'btn btn-primary',
                    onClick: close
                }])
                .forEach(a => {
                    const b = document.createElement('button');
                    b.className = a.cls || 'btn';
                    b.textContent = a.label || 'OK';
                    b.addEventListener('click', () => (a.onClick ? a.onClick() : close()));
                    ftr.appendChild(b);
                });

                open();
            };
        })();
    </script>
    <script src="/assets/JS/register.js"></script>

</body>

</html>