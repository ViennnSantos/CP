<?php
require_once __DIR__ . '/../backend/config/app.php';
require __DIR__ . '/../includes/phone_util.php';

try {
  $phone = normalize_ph_phone($_POST['mobile'] ?? '');
} catch (RuntimeException $e) {
  $errors['mobile'] = 'Enter a valid PH mobile (+63 + 10 digits).';
}

if (empty($errors) && phone_exists($pdo, $phone)) {
  $errors['mobile'] = 'Mobile number is already taken.';
}
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (!empty($_SESSION['user']) && ($_SESSION['user']['aud'] ?? '') === 'customer') {
  header('Location: /customer/index.php');
  exit;
}
$next = $_GET['next'] ?? '/customer/homepage.php';
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Customer Registration | Rads Tooling</title>
  <link rel="stylesheet" href="/assets/CSS/login.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
  <style>
    .auth-form.register-form {
      max-width: 800px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem 1.5rem;
      margin-bottom: 1.5rem;
    }

    .form-grid .form-group {
      margin-bottom: 0;
    }

    .form-grid .full-width {
      grid-column: 1 / -1;
    }

    /* Password strength indicator styles */
    .password-strength {
      margin-top: 0.5rem;
      height: 4px;
      background: #e8f0fe;
      border-radius: 2px;
      overflow: hidden;
      display: none;
    }

    .password-strength.show {
      display: block;
    }

    .password-strength-bar {
      height: 100%;
      transition: width 0.3s ease, background 0.3s ease;
      width: 0;
    }

    .password-strength-bar.weak {
      width: 33%;
      background: #f56565;
    }

    .password-strength-bar.medium {
      width: 66%;
      background: #f6ad55;
    }

    .password-strength-bar.strong {
      width: 100%;
      background: #48bb78;
    }

    /* Validation states */
    .form-group.has-success input {
      border-color: #48bb78;
    }

    .form-group.has-error input {
      border-color: #f56565;
    }

    @media (max-width: 768px) {
      .auth-form.register-form {
        max-width: 420px;
      }

      .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }

      .form-grid .form-group {
        margin-bottom: 1rem;
      }
    }

    .phone-group {
      display: flex;
      align-items: center;
      gap: .5rem
    }

    .phone-prefix {
      background: #f3f4f6;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 10px 12px;
      white-space: nowrap
    }

    .phone-group input {
      flex: 1;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 10px 12px
    }

    .field-error {
      display: none;
      color: #f56565;
      font-size: .85rem;
      margin-top: .25rem
    }

    .field-error {
      display: none;
      color: #f56565;
      font-size: .85rem;
      margin-top: .25rem
    }

    .form-group.has-error input,
    .form-group.has-error .phone-group input {
      border-color: #f56565 !important;
      box-shadow: 0 0 0 3px rgba(245, 101, 101, .08) !important
    }

    .form-group.has-success input,
    .form-group.has-success .phone-group input {
      border-color: #48bb78 !important;
      box-shadow: 0 0 0 3px rgba(72, 187, 120, .08) !important
    }

    .form-group.has-error .field-error {
      display: block
    }
/* Password toggle styles */

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
</head>

<body>
  <main class="auth-wrap">
    <div class="auth-form register-form">
      <div class="logo-container">
        <span class="login-logo">R</span><span class="logo-text">ADS</span>
        <span class="login-logo">T</span><span class="logo-text">OOLING</span>
      </div>
      <p class="subtitle">Create your account to get started</p>

      <form id="registerForm">
        <div class="form-grid">
          <div class="form-group">
            <label for="firstName">First Name</label>
            <input id="firstName" name="first_name" placeholder="Enter your first name" required>
          </div>

          <div class="form-group">
            <label for="lastName">Last Name</label>
            <input id="lastName" name="last_name" placeholder="Enter your last name" required>
          </div>

          <div class="form-group">
            <label for="email">Email Address</label>
            <input id="email" name="email" type="email" placeholder="your.email@example.com" required>
          </div>

          <div class="form-group" id="phoneGroup">
            <label for="phoneLocal">Mobile Number</label>
            <div class="phone-group">
              <span class="phone-prefix">+63</span>
              <input id="phoneLocal" name="phone_local" type="tel"
                inputmode="numeric" maxlength="10" pattern="[0-9]{10}"
                placeholder="9123456789" required>
            </div>
            <div class="field-error" id="phoneLocalFeedback"></div>
            <input id="phone" name="phone" type="hidden">
          </div>

          <div class="form-group full-width">
            <label for="username">Username</label>
            <input id="username" name="username" placeholder="Choose a unique username" required>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
             <div class="password-input-wrapper">
              <input id="password" name="password" type="password" placeholder="Min. 6 characters" required>
              <button type="button" class="toggle-password" data-target="password" aria-label="Toggle password visibility">
                <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
              </button>
            </div>
            <div class="password-strength">
              <div class="password-strength-bar"></div>
            </div>
          </div>

          <div class="form-group">
            <label for="confirmPassword">Confirm Password</label>
            <div class="password-input-wrapper">
              <input id="confirmPassword" name="confirm_password" type="password" placeholder="Re-enter password" required>
              <button type="button" class="toggle-password" data-target="confirmPassword" aria-label="Toggle password visibility">
                <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <input type="hidden" id="next" value="<?= htmlspecialchars($next) ?>">

        <button type="submit" class="btn-signup-primary" id="signupBtn">
          <span class="material-symbols-rounded">person_add</span>
          <span>Create Free Account</span>
        </button>

        <div class="error-message" id="errorMessage"></div>
        <div class="loading" id="loadingMessage">Creating your account...</div>
      </form>

      <div class="auth-links">
        <p>Already have an account? <a href="/customer/cust_login.php?next=<?= urlencode($next) ?>">Sign in here</a></p>
      </div>
    </div>
  </main>

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
      cursor: pointer;
      line-height: 1;
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
        actions
      } = {}) {
        ttl.textContent = title;
        body.innerHTML = html;
        ftr.innerHTML = '';
        (actions && actions.length ? actions : [{
          label: 'OK',
          cls: 'btn btn-primary',
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
	
 <!-- Password toggle functionality -->
  <script>
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

  </script>
  <!-- Use external JS file -->
  <script src="/assets/JS/register.js"></script>
</body>

</html>