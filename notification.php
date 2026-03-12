<style>
    .notification-toast { animation: slideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; transition: all 0.5s ease; }
    .notification-toast.hiding { opacity: 0; transform: translateX(50px); margin-bottom: -60px; }
    @keyframes slideIn { from { opacity: 0; transform: translateX(100px); } to { opacity: 1; transform: translateX(0); } }
</style>

<div id="notification-container" class="fixed top-20 right-6 z-[10000] flex flex-col gap-3 pointer-events-none"></div>

<script>
    function showNotification(title, message, type = 'success') {
        const container = document.getElementById('notification-container');
        if(!container) return;
        
        const types = {
            success: { bg: 'bg-emerald-600', icon: 'fa-circle-check', defaultTitle: 'Success' },
            error:   { bg: 'bg-rose-600',    icon: 'fa-circle-exclamation', defaultTitle: 'Attention' },
            warning: { bg: 'bg-amber-500',   icon: 'fa-triangle-exclamation', defaultTitle: 'Warning' },
            info:    { bg: 'bg-blue-600',    icon: 'fa-circle-info', defaultTitle: 'Notice' }
        };

        const config = types[type] || types.success;
        const toast = document.createElement('div');

        toast.className = `notification-toast pointer-events-auto flex items-center gap-3 min-w-[320px] ${config.bg} text-white px-4 py-3.5 rounded-xl shadow-2xl border border-white/10`;
        toast.innerHTML = `
            <i class="fa-solid ${config.icon} text-lg"></i>
            <div class="flex-1">
                <p class="text-[12px] font-bold leading-tight uppercase tracking-wider">${title || config.defaultTitle}</p>
                <p class="text-[11px] opacity-90 mt-0.5">${message}</p>
            </div>
        `;
        
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 500);
        }, 4500);
    }

    // AUTO-HANDLER FOR ANY PAGE
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        
        // If the URL has ?msg=Something&type=success
        if (urlParams.has('msg')) {
            const msg = urlParams.get('msg');
            const type = urlParams.get('type') || 'success';
            const title = urlParams.get('title') || (type === 'success' ? 'Task Complete' : 'Error');
            
            showNotification(title, msg, type);

            // Clean URL so refresh doesn't spam the toast
            const cleanUrl = window.location.pathname + window.location.search.replace(/[?&]msg=[^&]+|[?&]type=[^&]+|[?&]title=[^&]+/g, '');
            window.history.replaceState({}, document.title, cleanUrl);
        }
    });
</script>
