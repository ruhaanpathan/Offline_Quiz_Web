/**
 * QuizLAN — Common JS Utilities
 */
const QuizLAN = {
    toast(message, type = 'info', duration = 3000) {
        const existing = document.querySelector('.toast-notification');
        if (existing) existing.remove();
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;padding:14px 24px;border-radius:10px;font-size:0.9rem;font-weight:500;animation:slideUp 0.3s ease;max-width:400px;background:${type==='success'?'#10B981':type==='error'?'#EF4444':'#4F8EF7'};color:#fff;box-shadow:0 8px 30px rgba(0,0,0,0.3);`;
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity='0'; setTimeout(()=>toast.remove(),300); }, duration);
    },
    async ajax(url, data = null, method = 'POST') {
        const options = { method, headers: {} };
        if (data) {
            if (data instanceof FormData) { options.body = data; }
            else { options.headers['Content-Type']='application/json'; options.body=JSON.stringify(data); }
        }
        return (await fetch(url, options)).json();
    },
    openModal(id) { document.getElementById(id)?.classList.add('active'); },
    closeModal(id) { document.getElementById(id)?.classList.remove('active'); },
    formatTime(s) { return `${Math.floor(s/60)}:${(s%60).toString().padStart(2,'0')}`; },
    initFlash() {
        document.querySelectorAll('.flash').forEach(el => {
            setTimeout(() => { el.style.opacity='0'; setTimeout(()=>el.remove(),300); }, 5000);
        });
    }
};
document.addEventListener('DOMContentLoaded', () => {
    QuizLAN.initFlash();
    document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('active'); }));
    document.querySelectorAll('.modal-close').forEach(b => b.addEventListener('click', () => b.closest('.modal-overlay').classList.remove('active')));
});
