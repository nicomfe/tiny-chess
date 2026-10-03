export const POLL_INTERVAL_MS = 1000;

/** Return this from an `onState` handler to stop following a match. */
export const STOP = Symbol('stop');

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Asks the server for a match's public state about once a second, which is as
 * live as this app gets.
 *
 * `urlFor` is called per request so a caller can move its cursor along, and
 * `onState` returns STOP once it has seen enough.
 */
export async function followState(urlFor, onState) {
    for (;;) {
        try {
            const response = await fetch(urlFor(), { headers: { Accept: 'application/json' } });

            // A match that is gone is never coming back, unlike a failed request.
            if (response.status === 404) {
                return;
            }

            if (response.ok && onState(await response.json()) === STOP) {
                return;
            }
        } catch {
            // A dropped connection is not a reason to stop following the game.
        }

        await sleep(POLL_INTERVAL_MS);
    }
}
