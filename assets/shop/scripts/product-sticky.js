const syncStickyTargets = (sticky) => {
    sticky.querySelectorAll('[data-semaco-sticky-source]').forEach((target) => {
        const source = document.querySelector(target.dataset.semacoStickySource);
        if (source && target.innerHTML !== source.innerHTML) {
            target.innerHTML = source.innerHTML;
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const sticky = document.querySelector('[data-semaco-product-sticky]');
    const product = document.querySelector('.product-single');
    if (!sticky || !product) {
        return;
    }

    new MutationObserver(() => syncStickyTargets(sticky)).observe(product, { childList: true, subtree: true, characterData: true });
});
