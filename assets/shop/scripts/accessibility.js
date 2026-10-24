const LABELS = [
    ['.product-image-full', 'Zvětšit obrázek'],
    ['.owl-prev', 'Předchozí'],
    ['.owl-next', 'Další'],
];

function labelControls() {
    for (const [selector, label] of LABELS) {
        document.querySelectorAll(`${selector}:not([aria-label])`).forEach((element) => {
            element.setAttribute('aria-label', label);
            if (element.getAttribute('role') === 'presentation') {
                element.removeAttribute('role');
            }
            if (element.tagName === 'A' && element.getAttribute('href') === '#') {
                element.setAttribute('role', 'button');
            }
        });
    }

    document.querySelectorAll('.owl-dots').forEach((dots) => {
        dots.querySelectorAll('.owl-dot:not([aria-label])').forEach((dot, index) => {
            dot.setAttribute('aria-label', `Snímek ${index + 1}`);
            dot.removeAttribute('role');
        });
    });
}

let scheduled = false;
new MutationObserver(() => {
    if (scheduled) {
        return;
    }
    scheduled = true;
    setTimeout(() => {
        scheduled = false;
        labelControls();
    }, 100);
}).observe(document.body, { childList: true, subtree: true });

labelControls();
window.addEventListener('load', labelControls);
