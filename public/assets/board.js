import { Chessground } from './vendor/chessground.min.js';
import { STOP, followState } from './poll.js';

const PROMOTION_CHOICES = [
    { piece: 'q', label: 'Queen' },
    { piece: 'r', label: 'Rook' },
    { piece: 'b', label: 'Bishop' },
    { piece: 'n', label: 'Knight' },
];

/**
 * Drives the live board: draws whatever the server last said the position is,
 * offers only the moves the server listed as legal, and sends attempts for it
 * to accept or refuse. Nothing here decides whether a move is legal.
 */
export function startBoard(game) {
    const matchId = game.dataset.matchId;
    const ownColor = game.dataset.color || null;
    const turnLine = game.querySelector('[data-turn]');
    const resultLine = game.querySelector('[data-result]');
    const errorLine = game.querySelector('[data-move-error]');
    const replay = game.dataset.status === 'finished';
    const moveList = game.querySelector('[data-move-list]');
    const clockSides = {
        white: game.querySelector('[data-clock="white"]'),
        black: game.querySelector('[data-clock="black"]'),
    };

    // The query string carries the creator's token, so polls and submissions
    // have to keep it to stay the creator.
    const credentials = window.location.search;
    const plies = new Map();
    let state = null;
    let pliesDrawn = -1;

    const ground = Chessground(game.querySelector('[data-board]'), {
        fen: game.dataset.fen,
        orientation: game.dataset.orientation,
        viewOnly: replay || ownColor === null,
        coordinates: true,
        highlight: { lastMove: true, check: true },
        draggable: { showGhost: true },
        movable: {
            free: false,
            color: undefined,
            dests: new Map(),
            showDests: true,
            events: { after: onPieceDropped },
        },
    });

    function onPieceDropped(from, to) {
        if (!needsPromotion(to)) {
            submit(from + to);
            return;
        }

        // The piece is part of the move, so nothing is sent until it is chosen.
        askPromotion(from, to);
    }

    function needsPromotion(to) {
        const piece = ground.state.pieces.get(to);

        return piece?.role === 'pawn' && (to[1] === '8' || to[1] === '1');
    }

    function askPromotion(from, to) {
        const picker = document.createElement('div');
        picker.className = 'promotion';
        picker.innerHTML = '<p>Promote to</p>';

        for (const { piece, label } of PROMOTION_CHOICES) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.addEventListener('click', () => {
                picker.remove();
                submit(from + to + piece);
            });
            picker.append(button);
        }

        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'promotion__cancel';
        cancel.textContent = 'Cancel';
        cancel.addEventListener('click', () => {
            picker.remove();
            draw(state);
        });
        picker.append(cancel);

        game.querySelector('.play').prepend(picker);
    }

    async function submit(uci) {
        try {
            const response = await fetch(`/api/matches/${matchId}/moves${credentials}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ uci }),
            });
            const payload = await response.json();

            if (!response.ok) {
                showError(payload.message ?? 'That move was not accepted.');
                // The board is already showing the move it let the player make,
                // so put the server's position back.
                draw(state);
                return;
            }

            apply(payload);
        } catch {
            showError('The move could not be sent. Check your connection.');
            draw(state);
        }
    }

    function apply(payload) {
        // A poll and a submission can be in flight together, so the older
        // response may arrive last; drawing it would put a played move back on
        // the board.
        if (payload.moveCount < pliesDrawn) {
            return;
        }

        for (const ply of payload.moves) {
            plies.set(ply.number, ply);
        }

        // Polls arrive every second whether or not anything happened, and
        // touching the board is not free while a player has a piece in hand.
        const changed = state === null
            || state.fen !== payload.fen
            || state.moveCount !== payload.moveCount
            || state.status !== payload.status
            || state.you.canMove !== payload.you.canMove
            || clocksChanged(state?.clocks, payload.clocks);

        state = payload;
        pliesDrawn = payload.moveCount;
        drawTurn(payload);
        drawClocks(payload);

        if (payload.status === 'finished') {
            drawResult(payload);
            ground.set({ viewOnly: true, movable: { color: undefined, dests: new Map() } });
        }

        if (changed) {
            // Whatever the last attempt got wrong, the position has moved on.
            showError('');
            draw(payload);
            drawMoveList();
        }

        if (payload.status === 'finished' && !replay) {
            window.location.reload();
        }
    }

    function draw(current) {
        if (current === null) {
            return;
        }

        ground.set({
            fen: current.fen,
            turnColor: current.turn,
            check: current.check,
            lastMove: lastMoveSquares(),
            movable: {
                color: current.you.canMove ? current.you.color : undefined,
                dests: new Map(Object.entries(current.dests ?? {})),
            },
        });
    }

    function lastMoveSquares() {
        const newest = plies.get(plies.size);

        return newest ? [newest.uci.slice(0, 2), newest.uci.slice(2, 4)] : undefined;
    }

    function drawMoveList() {
        moveList.replaceChildren(...[...plies.keys()].sort((a, b) => a - b).map((number) => {
            const item = document.createElement('li');
            item.textContent = plies.get(number).san;
            item.className = number % 2 === 1 ? 'moves__white' : 'moves__black';

            return item;
        }));
    }

    function clocksChanged(before, after) {
        if (before === undefined || after === undefined) {
            return before !== after;
        }

        return before.white !== after.white
            || before.black !== after.black
            || before.running !== after.running;
    }

    function formatClock(ms) {
        const totalSeconds = Math.max(0, Math.ceil(ms / 1000));
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;

        return `${minutes}:${String(seconds).padStart(2, '0')}`;
    }

    function drawClocks(current) {
        if (!current?.clocks) {
            return;
        }

        for (const color of ['white', 'black']) {
            const side = clockSides[color];
            if (side === null) {
                continue;
            }

            side.querySelector('[data-clock-time]').textContent = formatClock(current.clocks[color]);
            side.classList.toggle('is-running', current.clocks.running === color);
        }
    }

    function drawResult(current) {
        if (!current?.result) {
            return;
        }

        const { winner, reason } = current.result;
        let headline;
        if (winner === null) {
            headline = reason === 'stalemate'
                ? 'Draw by stalemate'
                : reason === 'insufficient_material'
                    ? 'Draw by insufficient material'
                    : 'Draw';
        } else {
            const side = winner === 'white' ? 'White' : 'Black';
            headline = reason === 'checkmate'
                ? `${side} wins by checkmate`
                : reason === 'timeout'
                    ? `${side} wins on time`
                    : `${side} wins`;
        }

        if (resultLine) {
            resultLine.textContent = headline;
        }

        if (turnLine) {
            turnLine.textContent = headline;
        }
    }

    function drawTurn(current) {
        if (current.status === 'finished') {
            drawResult(current);

            return;
        }

        if (current.status === 'ready') {
            turnLine.textContent = ownColor === 'white'
                ? 'Your move — the game and the clocks start when you play it.'
                : 'Waiting for white’s first move.';

            return;
        }

        if (current.you.canMove) {
            turnLine.textContent = current.check ? 'Your move — you are in check.' : 'Your move.';

            return;
        }

        turnLine.textContent = ownColor === null
            ? `${current.turn === 'white' ? 'White' : 'Black'} to move.`
            : 'Waiting for your opponent.';
    }

    function showError(message) {
        errorLine.textContent = message;
        errorLine.hidden = message === '';
    }

    function stateUrl() {
        const params = new URLSearchParams(credentials);
        params.set('since', String(plies.size));

        return `/api/matches/${matchId}?${params}`;
    }

    // Polling starts straight away, so a player whose turn it is does not have
    // to wait a second before their pieces will move.
    followState(stateUrl, (payload) => {
        apply(payload);

        if (replay || payload.status === 'finished') {
            return STOP;
        }
    });
}
