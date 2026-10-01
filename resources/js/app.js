
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

/**
 * Count-up: angka ber-atribut data-count naik dari 0 ke nilainya saat masuk layar.
 * Teks aslinya sudah berisi angka akhir dari server, jadi tanpa JS tetap benar.
 */
function initCountUp() {
    const items = document.querySelectorAll('[data-count]:not([data-counted])');

    if (!items.length) {
        return;
    }

    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const animate = (element) => {
        const target = Number(element.dataset.count);
        const suffix = element.dataset.suffix || '';
        const duration = 1100;
        const start = performance.now();

        const frame = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = Math.round(target * eased) + suffix;

            if (progress < 1) {
                requestAnimationFrame(frame);
            }
        };

        element.textContent = '0' + suffix;
        requestAnimationFrame(frame);
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                observer.unobserve(entry.target);
                animate(entry.target);
            }
        });
    }, { threshold: 0.5 });

    items.forEach((item) => {
        if (Number.isNaN(Number(item.dataset.count))) {
            return;
        }

        item.dataset.counted = '1';
        observer.observe(item);
    });
}

document.addEventListener('DOMContentLoaded', initCountUp);
document.addEventListener('livewire:navigated', initCountUp);
