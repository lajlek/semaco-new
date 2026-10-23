document.addEventListener('click', (event) => {
    const button = event.target.closest('.quantity-plus, .quantity-minus');
    if (!button) {
        return;
    }

    const input = button.parentElement.querySelector('input.quantity');
    if (input) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
});
