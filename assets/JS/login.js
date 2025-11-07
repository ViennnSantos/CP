// ADMIN LOGIN JS
document.getElementById('loginForm').addEventListener('submit', async (e) => { 
    e.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const loginBtn = document.getElementById('loginBtn');
    const errorMessage = document.getElementById('errorMessage');
    const loadingMessage = document.getElementById('loadingMessage');

    // Clear previous errors
    errorMessage.style.display = 'none';
    errorMessage.textContent = '';

    // Show loading state
    loginBtn.disabled = true;
    loadingMessage.style.display = 'block';

    try {
        // Use relative path from current location (/admin/login.php)
        const res = await fetch('../backend/api/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ username, password, audience: 'staff' })
        });

        // Read response as text first to handle potential HTML errors
        const raw = await res.text();
        let data;
        
        try { 
            data = JSON.parse(raw); 
        } catch { 
            throw new Error(`Server returned invalid JSON: ${raw.slice(0, 120)}`); 
        }

        // Check if login was successful
        const success = res.ok && (data?.success === true || data?.ok === true);
        
        if (!success) {
            const msg = data?.message || data?.msg || `HTTP ${res.status}: Login failed`;
            throw new Error(msg);
        }

        // Store session data if provided
        const token = data.token || data.data?.token;
        const user = data.user || data.data?.user;
        
        if (token) {
            sessionStorage.setItem('rads_admin_session', token);
        }
        
        if (user) {
            sessionStorage.setItem('admin_name', user.full_name || user.name || user.username);
        }

        // Redirect to dashboard
        window.location.href = 'index.php';

    } catch (err) {
        console.error('Login error:', err);
        
        // Show error message
        errorMessage.textContent = err.message || 'Login failed. Please check your credentials.';
        errorMessage.style.display = 'block';
        
    } finally {
        // Reset button state
        loginBtn.disabled = false;
        loadingMessage.style.display = 'none';
    }
});

