(function () {
    const API_BASE = '/backend/api';

    function setNavFromUser(user) {
        const headAvatar = document.getElementById('nav-avatar');
        const headName = document.getElementById('nav-username');
        const ddAvatar = document.getElementById('dd-avatar');
        const ddName = document.getElementById('dd-name');
        const emailEl = document.getElementById('userEmailDisplay');

        const fullName = user.full_name || user.username || 'Customer';
        const initials = (fullName.split(' ').map(s => s[0]).join('').slice(0, 2) || 'U').toUpperCase();
        const imgPath = user.profile_image ? `/${user.profile_image}?v=${Date.now()}` : null;

        if (headName) headName.textContent = fullName;
        if (ddName) ddName.textContent = fullName;
        if (emailEl) emailEl.textContent = user.email || '—';

        const HEADER_AVATAR = 40; // try 40–48

        // dropdown size
        const DD_AVATAR = 64; // try 56–64

        const headHTML = imgPath ?
            `<img src="${imgPath}" alt="Avatar"
          style="width:${HEADER_AVATAR}px;height:${HEADER_AVATAR}px;border-radius:50%;object-fit:cover;">` :
            `<span class="avatar-initials" style="font-size:${Math.round(HEADER_AVATAR * 0.5)}px;">${initials}</span>`;

        const ddHTML = imgPath ?
            `<img src="${imgPath}" alt="Avatar"
          style="width:${DD_AVATAR}px;height:${DD_AVATAR}px;border-radius:50%;object-fit:cover;">` :
            `<span class="avatar-initials" style="font-size:${Math.round(DD_AVATAR * 0.5)}px;">${initials}</span>`;

        if (headAvatar) headAvatar.innerHTML = headHTML;
        if (ddAvatar) ddAvatar.innerHTML = ddHTML;
    }

    async function loadNavUser() {
        try {
            const res = await fetch(`${API_BASE}/customer_profile.php`, {
                credentials: 'include',
                headers: { 'Accept': 'application/json' }
            });
            const out = await res.json();
            if (out?.success && out.data?.customer) setNavFromUser(out.data.customer);
        } catch (e) {
            // silent fail
        }
    }

    document.addEventListener('DOMContentLoaded', loadNavUser);
})();
