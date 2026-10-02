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

const POLL_INTERVAL_MS = 1000;

/**
 * Someone waiting for their opponent has nothing to click, so watch the match
 * from here and let the server re-render once the status moves on. Keeping the
 * query string means a creator's poll still carries their token.
 */
async function followStatus(game) {
    const url = `/api/matches/${game.dataset.matchId}${window.location.search}`;
    const known = game.dataset.status;

    for (;;) {
        await new Promise((resolve) => setTimeout(resolve, POLL_INTERVAL_MS));

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (response.status === 404) {
                return;
            }

            if (!response.ok) {
                continue;
            }

            const state = await response.json();
            if (state.status !== known) {
                window.location.reload();
                return;
            }
        } catch {
            // A dropped connection is not a reason to stop waiting for the game.
        }
    }
}

const waitingGame = document.querySelector('.game[data-status="waiting"]');
if (waitingGame) {
    followStatus(waitingGame);
}
