<?php

use Chess\Game\Color;
use Chess\Game\GameMatch;
use Chess\Game\MatchStatus;
use Chess\Game\Role;

/** @var GameMatch $match */
/** @var Role $role */
/** @var string $playUrl */
/** @var string|null $creatorUrl */

$isWaiting = $match->status === MatchStatus::Waiting;
$isAbandoned = $match->status === MatchStatus::Abandoned;
$isFinished = $match->status === MatchStatus::Finished;
$showsBoard = $match->allowsMoves() || $isFinished;
$showsCreatorLinks = $role === Role::Creator && $creatorUrl !== null && !$isFinished && !$isAbandoned;
$isCreatorShareWaiting = $showsCreatorLinks && $isWaiting;

// A spectator has no color, which is both the fixed white-at-the-bottom view
// and what tells the board to accept no input at all.
$ownColor = $match->colorFor($role);
$orientation = $ownColor ?? Color::White;
// Clocks sit above and below the board; chessground puts your color at the bottom,
// so the nearer clock must match orientation, not a fixed white-on-top layout.
$clockAbove = $orientation === Color::White ? Color::Black : Color::White;
$clockBelow = $orientation === Color::White ? Color::White : Color::Black;

$heading = match (true) {
    $isAbandoned => 'Challenge expired',
    $isFinished => 'Game over',
    $role === Role::Creator && $isWaiting => 'Your challenge is ready to share',
    $role === Role::Creator => 'Your opponent has joined',
    $role === Role::Joiner => 'You have joined the game',
    default => 'Chess challenge',
};

$whitePlayer = match ($role) {
    Role::Creator => $match->creatorPlaysWhite() ? 'You' : 'Your opponent',
    Role::Joiner => $match->creatorPlaysWhite() ? 'Your opponent' : 'You',
    Role::Spectator => $match->creatorPlaysWhite() ? 'The creator' : 'The opponent',
};

$statusLine = match ($match->status) {
    MatchStatus::Waiting => 'Waiting for the opponent to join',
    MatchStatus::Abandoned => 'This challenge expired',
    MatchStatus::Ready => 'Both players are seated — the game starts with white’s first move',
    MatchStatus::Active => 'Game in progress',
    MatchStatus::Finished => $match->resultHeadline(),
};
?>
<div class="game<?= $isCreatorShareWaiting ? ' game--share-waiting' : '' ?>"
     data-match-id="<?= e($match->id) ?>"
     data-status="<?= e($match->status->value) ?>"
     data-role="<?= e($role->value) ?>"
     data-fen="<?= e($match->fen) ?>"
     data-orientation="<?= e($orientation->value) ?>"
     data-color="<?= e($ownColor?->value ?? '') ?>">
    <?php if ($isCreatorShareWaiting): ?>
        <div class="share-wait__backdrop" aria-hidden="true">
            <div class="board-frame share-wait__board-frame">
                <div class="board" data-board-backdrop></div>
            </div>
        </div>
        <div class="share-wait__shell" data-game-shell>
            <div class="share-wait__column">
                <h1 class="share-wait__title">Share the link to your opponent</h1>
                <p class="share-wait__lead">Your game is ready. Clocks stay idle until opponent joins.</p>

                <div class="share-wait__cta" data-share-cta>
                    <button type="button" class="share-wait__share-btn" data-share-play-url>
                        Share invite link
                    </button>
                    <p class="share-wait__share-fallback">
                        Or <button type="button" class="share-wait__share-copy" data-copy>copy link</button>
                    </p>
                    <input type="text"
                           readonly
                           value="<?= e($playUrl) ?>"
                           aria-label="Link to share with your opponent"
                           tabindex="-1"
                           class="share-wait__share-url-sr">
                </div>

                <ul class="share-wait__chips" aria-label="Challenge details">
                    <li><?= e($match->timeControl->label()) ?> each</li>
                    <li>White: <?= e($whitePlayer) ?></li>
                    <li><?= e($statusLine) ?></li>
                </ul>
            </div>
        </div>
    <?php else: ?>
    <div class="game__shell" data-game-shell>
        <div class="game__stage">
            <?php if ($showsBoard): ?>
                <section class="play play--stacked">
                    <div class="game__board-stack">
                        <div class="game__clock clocks__side" data-clock="<?= e($clockAbove->value) ?>">
                            <span class="clocks__label"><?= $clockAbove === Color::White ? 'White' : 'Black' ?></span>
                            <span class="clocks__time" data-clock-time><?= e(sprintf('%d:00', $match->timeControl->minutes())) ?></span>
                        </div>
                        <div class="board-frame">
                            <div class="board" data-board></div>
                        </div>
                        <div class="game__clock clocks__side" data-clock="<?= e($clockBelow->value) ?>">
                            <span class="clocks__label"><?= $clockBelow === Color::White ? 'White' : 'Black' ?></span>
                            <span class="clocks__time" data-clock-time><?= e(sprintf('%d:00', $match->timeControl->minutes())) ?></span>
                        </div>
                    </div>
                    <p class="turn" data-turn role="status"></p>
                    <p class="move-error" data-move-error role="alert" hidden></p>
                </section>
            <?php else: ?>
                <section class="play play--empty" aria-hidden="true">
                    <div class="board-frame board-frame--placeholder">
                        <p class="game__placeholder">Board appears when play begins.</p>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <aside class="game__panel" id="game-panel" data-game-panel>
            <h1><?= e($heading) ?></h1>

            <dl class="summary">
                <dt>Time control</dt>
                <dd><?= e($match->timeControl->label()) ?> per player</dd>
                <dt>White</dt>
                <dd><?= e($whitePlayer) ?></dd>
                <dt>Status</dt>
                <dd><?= e($statusLine) ?></dd>
            </dl>

            <?php if ($isAbandoned): ?>
                <p class="standby" role="status">This challenge expired. Nobody joined in time — ask for a new link.</p>
            <?php elseif ($isWaiting): ?>
                <p class="standby" role="status">Waiting for the opponent to join. Nothing starts until they are here — the clocks stay put.</p>
            <?php endif; ?>

            <?php if ($isFinished): ?>
                <p class="result" role="status" data-result><?= e($match->resultHeadline()) ?></p>
            <?php endif; ?>

            <?php if ($showsCreatorLinks): ?>
                <section class="link-card">
                    <h2>Opponent link</h2>
                    <p>Send this one to the person you want to play. It does not contain your token.</p>
                    <div class="copy-row">
                        <input type="text" readonly value="<?= e($playUrl) ?>" aria-label="Link to share with your opponent">
                        <button type="button" data-copy>Copy</button>
                    </div>
                </section>
            <?php elseif ($role === Role::Joiner): ?>
                <p class="lead">You are the opponent in this game, playing
                    <?= $match->creatorPlaysWhite() ? 'black' : 'white' ?>. Stay in this browser to keep your seat —
                    it is what remembers you, so a refresh is fine but another browser would only be watching.</p>
            <?php elseif ($role === Role::Spectator && !$isAbandoned): ?>
                <p class="lead">You are watching this challenge<?= $isWaiting ? '' : '. Both seats are taken, so you cannot move pieces' ?>.
                    <?= $isWaiting ? 'The board appears here once play begins.' : '' ?></p>
            <?php endif; ?>

            <?php if ($showsBoard): ?>
                <?php if ($ownColor !== null && !$isFinished): ?>
                    <div class="actions" data-actions>
                        <p class="actions__offer" data-draw-status hidden></p>
                        <div class="actions__buttons">
                            <button type="button" data-offer-draw>Offer draw</button>
                            <button type="button" data-accept-draw hidden>Accept draw</button>
                            <button type="button" data-decline-draw hidden>Decline</button>
                            <button type="button" class="actions__resign" data-resign>Resign</button>
                        </div>
                    </div>
                <?php endif; ?>
                <ol class="moves" data-move-list></ol>
            <?php endif; ?>
        </aside>
    </div>

    <button type="button"
            class="game__panel-toggle"
            data-game-panel-toggle
            aria-controls="game-panel"
            aria-expanded="false">
        Match info
    </button>
    <?php endif; ?>
</div>
