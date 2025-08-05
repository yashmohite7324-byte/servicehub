// index.js - Handles login logic for user and admin

function showTab(tab) {
    document.getElementById('userTab').classList.remove('active');
    document.getElementById('adminTab').classList.remove('active');
    document.getElementById('userForm').style.display = 'none';
    document.getElementById('adminForm').style.display = 'none';
    if (tab === 'user') {
        document.getElementById('userTab').classList.add('active');
        document.getElementById('userForm').style.display = '';
    } else {
        document.getElementById('adminTab').classList.add('active');
        document.getElementById('adminForm').style.display = '';
    }
}

async function loginUser(event) {
    event.preventDefault();
    document.getElementById('userError').textContent = '';
    document.getElementById('userSuccess').textContent = '';
    const mobile = document.getElementById('userMobile').value.trim();
    const password = document.getElementById('userPassword').value;
    if (!/^\d{10}$/.test(mobile)) {
        document.getElementById('userError').textContent = 'Mobile number must be exactly 10 digits.';
        return false;
    }
    try {
        const res = await fetch('../api/user_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ mobile, password })
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            document.getElementById('userError').textContent = 'Server error: Invalid response.';
            return false;
        }
        if (data.success) {
            document.getElementById('userSuccess').textContent = 'Login successful! Redirecting...';
            document.getElementById('userError').textContent = '';
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 500);
        } else {
            document.getElementById('userError').textContent = data.error || 'Login failed.';
        }
    } catch (err) {
        document.getElementById('userError').textContent = 'Network/server error.';
    }
    return false;
}

async function loginAdmin(event) {
    event.preventDefault();
    document.getElementById('adminError').textContent = '';
    document.getElementById('adminSuccess').textContent = '';
    const username = document.getElementById('adminUsername').value.trim();
    const password = document.getElementById('adminPassword').value;
    try {
        const res = await fetch('../api/admin_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ username, password })
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            document.getElementById('adminError').textContent = 'Server error: Invalid response.';
            return false;
        }
        if (data.success) {
            document.getElementById('adminSuccess').textContent = 'Admin login successful! Redirecting...';
            document.getElementById('adminError').textContent = '';
            setTimeout(() => {
                window.location.href = 'admin_dashboard.php';
            }, 500);
        } else {
            document.getElementById('adminError').textContent = data.error || 'Login failed.';
        }
    } catch (err) {
        document.getElementById('adminError').textContent = 'Network/server error.';
    }
    return false;
} 