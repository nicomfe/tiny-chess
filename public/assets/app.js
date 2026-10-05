import { startBoard } from './board.js';
import { STOP, followState } from './poll.js';

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

/**
 * Someone waiting for their opponent has nothing to click, so watch the match
 * from here and let the server re-render once the status moves on. Keeping the
 * query string means a creator's poll still carries their token.
 */
const waitingGame = document.querySelector('.game[data-status="waiting"]');
if (waitingGame) {
    const url = `/api/matches/${waitingGame.dataset.matchId}${window.location.search}`;

    followState(() => url, (state) => {
        if (state.status !== waitingGame.dataset.status) {
            window.location.reload();

            return STOP;
        }
    });
}

const playableGame = document.querySelector('.game [data-board]')?.closest('.game');
if (playableGame) {
    startBoard(playableGame);
}

const gameShell = document.querySelector('[data-game-shell]');
const gameRoot = gameShell?.closest('.game');
if (gameShell && gameRoot) {
    const panel = gameShell.querySelector('[data-game-panel]');
    const toggle = gameRoot.querySelector('[data-game-panel-toggle]');
    if (panel && toggle) {
        const close = () => {
            gameShell.classList.remove('is-panel-open');
            toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', () => {
            const open = gameShell.classList.toggle('is-panel-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        gameShell.addEventListener('click', (event) => {
            if (event.target === gameShell && gameShell.classList.contains('is-panel-open')) {
                close();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && gameShell.classList.contains('is-panel-open')) {
                close();
            }
        });
    }
}
