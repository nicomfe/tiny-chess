document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) {
        return;
    }

    const input = button.parentElement.querySelector('input');
    if (!input) {
        return;
    }

    try {
        await navigator.clipboard.writeText(input.value);
    } catch {
        input.select();
        document.execCommand('copy');
    }

    const original = button.textContent;
    button.textContent = 'Copied';
    setTimeout(() => {
        button.textContent = original;
    }, 1500);
});
