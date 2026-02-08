// Toggle sidebar on mobile
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// Toggle submenu (pure CSS alternative possible, but JS is more reliable)
function toggleSubmenu(el) {
    el.parentElement.classList.toggle('open');
}

// Optional: close sidebar when clicking outside (mobile)
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.menu-toggle');
    
    if (window.innerWidth >= 992) return; // desktop always open
    
    if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});

// Future: Offline sync stub
window.addEventListener('online', () => {
    console.log('Back online – sync pending transactions');
    // → send queued sales via fetch to api/sync_offline.php
});

window.addEventListener('load', () => {
    // Optional: Register service worker for PWA/offline
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('../service-worker.js')
            .then(reg => console.log('SW registered'))
            .catch(err => console.log('SW failed', err));
    }
});



function copyToClipboardFallback(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    alert('Receipt copied (fallback mode)!');
}