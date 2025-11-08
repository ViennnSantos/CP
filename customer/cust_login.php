<?php
require_once __DIR__ . '/../backend/config/app.php';

if (session_status() === PHP_SESSION_NONE) {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
}

if (!empty($_SESSION['user']) && ($_SESSION['user']['aud'] ?? '') === 'customer') {
  header('Location: /customer/homepage.php');
  exit;
}
$next = $_GET['next'] ?? '/customer/homepage.php';
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Customer Login | Rads Tooling</title>
  <link rel="stylesheet" href="/assets/CSS/login.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" />
<style>
     .password-input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }
    .password-input-wrapper input {
      width: 100%;
      padding-right: 45px;
    }
    .toggle-password {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      cursor: pointer;
      padding: 5px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #666;
      transition: color 0.2s;
    }
    .toggle-password:hover {
      color: #2f5b88;
    }
    .toggle-password .eye-icon {
      pointer-events: none;
    }
  </style>
<<<<<<< HEAD
=======

>>>>>>> aaaa32eef604de9e448e442948c715dd8221ca5f
</head>

<body>
  <main class="auth-wrap">
    <div class="auth-form">
      <div class="logo-container">
        <span class="login-logo">R</span><span class="logo-text">ADS</span>
        <span class="login-logo">T</span><span class="logo-text">OOLING</span>
      </div>
      <p class="subtitle">Login to your account</p>

      <form id="loginForm">
        <div class="form-group">
          <label for="username">Username or Email</label>
          <input id="username" name="username" placeholder="Enter your username or email" autocomplete="username" required>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="password-input-wrapper">
            <input id="password" name="password" type="password" placeholder="Enter your password" required>
            <button type="button" class="toggle-password" data-target="password" aria-label="Toggle password visibility">
              <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>
        </div>

        <input type="hidden" id="next" value="<?= htmlspecialchars($next) ?>">
        <button class="btn-primary" type="submit">Sign In</button>

        <div class="error-message" id="errorMessage"></div>
        <div class="loading" id="loadingMessage">Logging in...</div>
      </form>

      <div class="auth-links">
        <p>
          <a href="#" id="forgotLink">Forgot password?</a>
        </p>
        <p>
          No account? <a href="/customer/register.php?next=<?= urlencode($next) ?>">Create one</a>
        </p>
      </div>
    </div>
  </main>

  <!-- Forgot Password Modal: Step 1 (request code) -->
  <div class="modal" id="fpModal">
    <div class="modal-content">
      <h3>Reset Password</h3>
      <p>We'll email a 6-digit code valid for 30 minutes.</p>
      <form id="fpRequestForm">
        <input name="email" type="email" placeholder="Your account email" required>
        <button type="submit">Send Code</button>
        <button type="button" class="close-btn" data-close>Cancel</button>
      </form>
    </div>
  </div>

  <!-- Forgot Password Modal: Step 2 (enter code + new password) -->
  <div class="modal" id="fpResetModal">
    <div class="modal-content">
      <h3>Enter Code</h3>
      <form id="fpResetForm">
        <input name="email" type="email" placeholder="Email" required>
        <input name="code" placeholder="6-digit code" maxlength="6" required>
        <input name="new_password" type="password" placeholder="New password" required>
        <input name="confirm" type="password" placeholder="Confirm new password" required>
        <button type="submit">Reset Password</button>
        <button type="button" class="close-btn" data-close>Cancel</button>
      </form>
    </div>
  </div>

  <script>
	  
 /* Password toggle functionality */
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
          const targetId = this.getAttribute('data-target');
          const input = document.getElementById(targetId);
          if (input) {
            if (input.type === 'password') {
              input.type = 'text';
              this.innerHTML = '<svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            } else {
              input.type = 'password';
              this.innerHTML = '<svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            }
          }
        });
      });
    });
	  
    /* Lightweight modal fallback if showModal() isn't defined globally */
    const __modal = (window.showModal) ?
      window.showModal :
      function({
        title = 'Notice',
        html = '',
        actions
      } = {}) {
        alert((title ? title + ': ' : '') + (html.replace(/<[^>]*>/g, '') || ''));
      };

    /* --- Login --- */
    document.getElementById('loginForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();

      const errorDiv = document.getElementById('errorMessage');
      const loadingDiv = document.getElementById('loadingMessage');
      const submitBtn = e.target.querySelector('.btn-primary'); // keep your class

      // Reset states
      if (errorDiv) {
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
      }
      if (loadingDiv) {
        loadingDiv.style.display = 'block';
        loadingDiv.textContent = 'Signing you in…';
      }
      if (submitBtn) submitBtn.disabled = true;

      // Get form data
      const formData = new FormData(e.target);
      const data = Object.fromEntries(formData.entries());
      const next = document.getElementById('next')?.value || '/customer/homepage.php';

      // Treat "username" field as "username OR email"
      data.audience = 'customer';

      try {
        const res = await fetch('/backend/api/auth.php?action=login', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify(data)
        });

        const responseText = await res.text();
        let j;
        try {
          j = JSON.parse(responseText);
        } catch {
          throw new Error('Server returned invalid response.');
        }

        if (!res.ok || !j.success) {
  const msg = j.message || j.msg || 'Invalid username or password';
  if (errorDiv) {
    errorDiv.textContent = msg;
    errorDiv.style.display = 'block';
  } else {
    __modal({ title: 'Login failed', html: msg }); // fallback if missing
  }
  throw new Error(msg);
}

        // If backend says verification is required, go to verify page instead
        if (j.data && j.data.verify_required) {
          const verifyUrl = j.data.redirect || (
            `/customer/verify.php?email=${encodeURIComponent(j.data.email || data.username)}`
          );
          if (j.data && j.data.verify_required) {
            const verifyUrl = j.data.redirect || (
              `/customer/verify.php?email=${encodeURIComponent(j.data.email || data.username)}`
            );
            // Optional: show a modal, then redirect
            if (window.showModal) {
              showModal({
                title: 'Verification needed',
                html: 'Please verify your email to continue.',
                actions: [{
                  label: 'Verify now',
                  cls: 'btn btn-primary',
                  onClick: () => {
                    location.href = verifyUrl;
                  }
                }]
              });
            } else {
              location.href = verifyUrl;
            }
            return;
          }
        }

        // Successful login - redirect
        window.location.href = next;

      } catch (err) {
        console.error('Login error:', err);
        if (errorDiv) {
          errorDiv.textContent = err.message || 'Login failed';
          errorDiv.style.display = 'block';
        }
      } finally {
        if (loadingDiv) loadingDiv.style.display = 'none';
        if (submitBtn) submitBtn.disabled = false;
      }
    });

    /* --- Forgot Password Modals (unchanged, with modal prompts) --- */
    const $$ = s => document.querySelector(s);
    const open = el => (el.style.display = 'flex');
    const close = el => (el.style.display = 'none');

    $$('#forgotLink')?.addEventListener('click', (e) => {
      e.preventDefault();
      open($$('#fpModal'));
    });

    document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => {
      close($$('#fpModal'));
      close($$('#fpResetModal'));
    }));

    // Step 1: Request code
    $$('#fpRequestForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = new FormData(e.target).get('email');
      try {
        const r = await fetch('/backend/api/password.php?action=request', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            email
          })
        });
        const j = await r.json();
        if (!r.ok || !j.success) throw new Error(j.message || 'Failed to send code');
        __modal({
          title: 'Sent',
          html: 'We sent a reset code to your email.'
        });
        close($$('#fpModal'));
        // Prefill email in step 2
        const emailInput = $$('#fpResetForm [name=email]');
        if (emailInput) emailInput.value = email;
        open($$('#fpResetModal'));
      } catch (err) {
        __modal({
          title: 'Error',
          html: (err.message || 'Failed to send code')
        });
      }
    });

    // Step 2: Submit code + new password
    $$('#fpResetForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const payload = Object.fromEntries(fd.entries());
      if (payload.new_password !== payload.confirm) {
        __modal({
          title: 'Mismatch',
          html: 'Passwords do not match.'
        });
        return;
      }
      try {
        const r = await fetch('/backend/api/password.php?action=reset', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        });
        const j = await r.json();
        if (!r.ok || !j.success) throw new Error(j.message || 'Reset failed');
        __modal({
          title: 'Updated',
          html: 'Password updated. Please login.'
        });
        close($$('#fpResetModal'));
      } catch (err) {
        __modal({
          title: 'Error',
          html: (err.message || 'Reset failed')
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
        <button type="button" class="btn" data-appmodal-close>OK</button>
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
      z-index: 9999
    }

    .app-modal__dialog {
      width: min(520px, 92vw);
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .2)
    }

    .app-modal__header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 14px 18px;
      border-bottom: 1px solid #eee
    }

    .app-modal__body {
      padding: 16px 18px
    }

    .app-modal__footer {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      padding: 12px 18px;
      border-top: 1px solid #eee
    }

    .app-modal__close {
      border: none;
      background: transparent;
      font-size: 22px;
      cursor: pointer
    }

    .btn {
      padding: 10px 14px;
      border-radius: 8px;
      border: 1px solid #ddd;
      background: #f7f7f7;
      cursor: pointer
    }

    .btn-primary {
      background: #2f5b88;
      color: #fff;
      border-color: #2f5b88
    }
  </style>

  <script>
    (function() {
      const el = document.getElementById('appModal');
      const ttl = document.getElementById('appModalTitle');
      const body = document.getElementById('appModalBody');
      const ftr = document.getElementById('appModalFooter');

      function close() {
        el.style.display = 'none';
        ftr.innerHTML = '<button type="button" class="btn" data-appmodal-close>OK</button>';
      }

      function open() {
        el.style.display = 'flex';
      }

      document.addEventListener('click', (e) => {
        if (e.target.matches('[data-appmodal-close]') || e.target === el) close();
      });

      // Global API for pages/scripts
      window.showModal = function({
        title = 'Notice',
        html = '',
        actions
      } = {}) {
        ttl.textContent = title;
        body.innerHTML = html;
        ftr.innerHTML = '';
        (actions && actions.length ? actions : [{
          label: 'OK',
          cls: 'btn',
          onClick: close
        }]).forEach(a => {
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


</body>

</html>