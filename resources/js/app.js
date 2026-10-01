
/**
 * Scroll reveal: elemen ber-atribut data-reveal muncul pelan saat masuk layar.
 * Class .reveal ditambah lewat JS, jadi kalau JS gagal kontennya tetap kelihatan.
 */
function initScrollReveal() {
    const items = document.querySelectorAll('[data-reveal]:not(.reveal)');

    if (!items.length) {
        return;
    }

    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

    items.forEach((item) => {
        item.style.setProperty('--reveal-delay', `${item.dataset.reveal || 0}ms`);
        item.classList.add('reveal');
        // Lepas class setelah selesai biar transisi hover bawaan elemennya (misal kartu) gak ketimpa.
        item.addEventListener('transitionend', (event) => {
            if (event.propertyName === 'opacity') {
                item.classList.remove('reveal', 'is-visible');
                item.style.removeProperty('--reveal-delay');
            }
        });
        observer.observe(item);
    });
}

document.addEventListener('DOMContentLoaded', initScrollReveal);
document.addEventListener('livewire:navigated', initScrollReveal);
