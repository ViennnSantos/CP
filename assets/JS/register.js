// File: /assets/JS/register.js
// CUSTOMER REGISTRATION WITH EMAIL VERIFICATION (+63 phone normalization + modal messages)

document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('registerForm');
  if (!form) return;

  const errorDiv = document.getElementById('errorMessage');
  const loadingDiv = document.getElementById('loadingMessage');
  const submitBtn = form.querySelector('.btn-signup-primary');

  // Modal helper (falls back to alert if showModal isn't installed yet)
  const modal = (window.showModal)
    ? window.showModal
    : ({ title = 'Notice', html = '' } = {}) => alert((title ? title + ': ' : '') + html.replace(/<[^>]*>/g, ''));

  const get = (id) => (document.getElementById(id)?.value || '').trim();

  const phoneGrp = document.getElementById('phoneGroup');
  const phoneFb = document.getElementById('phoneLocalFeedback');

  // DIGITS ONLY + MAX 10 habang nagta-type
  const phoneLocalEl = document.getElementById('phoneLocal');
  let phoneTimeout;
  if (phoneLocalEl) {
    phoneLocalEl.addEventListener('input', () => {
      phoneLocalEl.value = phoneLocalEl.value.replace(/\D/g, '').slice(0, 10);

      // clear state habang nagta-type
      phoneGrp?.classList.remove('has-error', 'has-success');
      if (phoneFb) phoneFb.textContent = '';

      clearTimeout(phoneTimeout);
      if (phoneLocalEl.value.length !== 10) return;

      // live duplicate check
      phoneTimeout = setTimeout(async () => {
        try {
          const r = await fetch('/backend/api/auth.php?action=check_phone', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ phone_local: phoneLocalEl.value })
          });
          const j = await r.json();
          if (j.success && j.data && j.data.available) {
            phoneGrp?.classList.add('has-success'); phoneGrp?.classList.remove('has-error');
            if (phoneFb) phoneFb.textContent = '';
          } else {
            phoneGrp?.classList.add('has-error'); phoneGrp?.classList.remove('has-success');
            if (phoneFb) phoneFb.textContent = 'Mobile number is already taken';
          }
        } catch { }
      }, 400);
    });
  }


  function setLoading(on, msg) {
    if (loadingDiv) {
      loadingDiv.style.display = on ? 'block' : 'none';
      if (msg) loadingDiv.textContent = msg;
    }
    if (submitBtn) submitBtn.disabled = !!on;
  }

  function showError(message) {
    if (errorDiv) {
      errorDiv.textContent = message;
      errorDiv.style.display = 'block';
    } else {
      modal({ title: 'Registration failed', html: message });
    }
    setLoading(false);
  }

  function clearError() {
    if (errorDiv) {
      errorDiv.style.display = 'none';
      errorDiv.textContent = '';
    }
  }

  // Submit handler
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearError();
    setLoading(true, 'Creating your account…');

    const firstName = get('firstName');
    const lastName = get('lastName');
    const fullName = `${firstName} ${lastName}`.trim();

    const payload = {
      audience: 'customer',
      full_name: fullName,  // ← CHANGED: combine first + last name
      email: get('email'),
      username: get('username'),
      password: get('password')
    };
    const confirm = get('confirmPassword');

    const local = (phoneLocalEl?.value || '').replace(/\D/g, '').slice(0, 10);
    if (local.length !== 10) {
      phoneGrp?.classList.add('has-error'); phoneGrp?.classList.remove('has-success');
      if (phoneFb) phoneFb.textContent = 'Enter 10 digits after +63 (e.g., 9123456789).';
      setLoading(false);
      return;
    }

    // Client-side validation (server re-validates too)
    if (!firstName || !lastName) return showError('Please enter your first and last name.');
    if (!payload.full_name || payload.full_name.length < 2) return showError('Please enter your full name.');
    if (!payload.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) return showError('Please enter a valid email.');
    if (!payload.username || !/^[A-Za-z0-9_]{3,20}$/.test(payload.username)) return showError('Username must be 3–20 characters (letters, numbers, underscore).');
    if (!payload.password || payload.password.length < 6) return showError('Password must be at least 6 characters long.');
    if (payload.password !== confirm) return showError('Passwords do not match.');

    payload.phone_local = local;

    try {
      const res = await fetch('/backend/api/auth.php?action=register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });

      const text = await res.text();
      let j;
      try { j = JSON.parse(text); } catch { throw new Error('Server returned invalid response.'); }

      if (!res.ok || !j.success) {
        const m = (j.message || '').toLowerCase();
        if (m.includes('mobile number') || m.includes('ph mobile')) {
          phoneGrp?.classList.add('has-error'); phoneGrp?.classList.remove('has-success');
          if (phoneFb) phoneFb.textContent = j.message;
        }
        throw new Error(j.message || 'Registration failed. Please try again.');
      }

      // Store email (optional convenience)
      try { sessionStorage.setItem('verify_email', payload.email); } catch (_) { }

      // Success → modal + redirect to verify
      modal({
        title: 'Account created',
        html: 'Please check your email for the verification code.',
        actions: [
          { label: 'Verify now', cls: 'btn btn-primary', onClick: () => { location.href = `/customer/verify.php?email=${encodeURIComponent(payload.email)}`; } }
        ]
      });

      // Fallback redirect if modal not available
      setTimeout(() => {
        window.location.href = `/customer/verify.php?email=${encodeURIComponent(payload.email)}`;
      }, 1200);

    } catch (err) {
      console.error('Registration error:', err);
      showError(err.message || 'Registration failed. Please try again.');
    } finally {
      setLoading(false);
    }
  });

  /* ===== Password strength (kept from your original) ===== */
  const passwordInput = document.getElementById('password');
  const strengthContainer = document.querySelector('.password-strength');
  const strengthBar = document.querySelector('.password-strength-bar');

  passwordInput?.addEventListener('input', function () {
    const password = this.value;

    if (!strengthContainer || !strengthBar) return;

    if (password.length === 0) { strengthContainer.classList.remove('show'); return; }
    strengthContainer.classList.add('show');

    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;

    strengthBar.className = 'password-strength-bar';
    if (strength <= 1) strengthBar.classList.add('weak');
    else if (strength <= 3) strengthBar.classList.add('medium');
    else strengthBar.classList.add('strong');
  });

  /* ===== Real-time password confirmation ===== */
  document.getElementById('confirmPassword')?.addEventListener('input', function () {
    const pw = document.getElementById('password')?.value || '';
    const conf = this.value;
    const grp = this.closest('.form-group');
    if (!grp) return;
    if (!conf) { grp.classList.remove('has-success', 'has-error'); return; }
    if (pw === conf) { grp.classList.add('has-success'); grp.classList.remove('has-error'); }
    else { grp.classList.add('has-error'); grp.classList.remove('has-success'); }
  });

  /* ===== Debounced email availability check (case-insensitive on server) ===== */
  let emailTimeout;
  document.getElementById('email')?.addEventListener('input', function () {
    const email = this.value.trim();
    const grp = this.closest('.form-group');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (errorDiv?.textContent.includes('Email')) clearError();
    if (!grp) return;

    if (!email) { grp.classList.remove('has-success', 'has-error'); return; }
    if (!emailRegex.test(email)) { grp.classList.add('has-error'); grp.classList.remove('has-success'); return; }

    clearTimeout(emailTimeout);
    emailTimeout = setTimeout(async () => {
      try {
        const r = await fetch('/backend/api/auth.php?action=check_email', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ email })
        });
        const j = await r.json();
        if (j.data && j.data.available) { grp.classList.add('has-success'); grp.classList.remove('has-error'); }
        else { grp.classList.add('has-error'); grp.classList.remove('has-success'); showError('Email is already registered'); }
      } catch (err) { console.error('Email check failed:', err); }
    }, 500);
  });

  /* ===== Debounced username availability check (case-insensitive on server) ===== */
  let usernameTimeout;
  document.getElementById('username')?.addEventListener('input', function () {
    const username = this.value.trim();
    const grp = this.closest('.form-group');
    if (!grp) return;

    if (errorDiv && errorDiv.textContent === 'Username is already taken') clearError();
    if (username.length < 3) { grp.classList.remove('has-success', 'has-error'); return; }

    clearTimeout(usernameTimeout);
    usernameTimeout = setTimeout(async () => {
      try {
        const r = await fetch('/backend/api/auth.php?action=check_username', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ username })
        });
        const j = await r.json();
        if (j.data && j.data.available) { grp.classList.add('has-success'); grp.classList.remove('has-error'); }
        else { grp.classList.add('has-error'); grp.classList.remove('has-success'); showError('Username is already taken'); }
      } catch (err) { console.error('Username check failed:', err); }
    }, 500);
  });

  /* ===== Basic blur validation for other required fields ===== */
  document.querySelectorAll('input[required]')?.forEach(input => {
    const skip = ['password', 'confirmPassword', 'email', 'username', 'phone', 'phoneLocal'];
    if (skip.includes(input.id)) return;
    input.addEventListener('blur', function () {
      const grp = this.closest('.form-group');
      if (!grp) return;
      if (this.value.trim()) { grp.classList.add('has-success'); grp.classList.remove('has-error'); }
      else { grp.classList.remove('has-success'); }
    });
  });
});
