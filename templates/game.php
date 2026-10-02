<?php

use Chess\Game\GameMatch;
use Chess\Game\MatchStatus;
use Chess\Game\Role;

/** @var GameMatch $match */
/** @var Role $role */
/** @var string $playUrl */
/** @var string|null $creatorUrl */

$isWaiting = $match->status === MatchStatus::Waiting;
$showsCreatorLinks = $role === Role::Creator && $creatorUrl !== null;

$heading = match ($role) {
    Role::Creator => $isWaiting ? 'Your challenge is ready to share' : 'Your opponent has joined',
    Role::Joiner => 'You have joined the game',
    Role::Spectator => 'Chess challenge',
};

$whitePlayer = match ($role) {
    Role::Creator => $match->creatorPlaysWhite() ? 'You' : 'Your opponent',
    Role::Joiner => $match->creatorPlaysWhite() ? 'Your opponent' : 'You',
    Role::Spectator => $match->creatorPlaysWhite() ? 'The creator' : 'The opponent',
};

$statusLine = match ($match->status) {
    MatchStatus::Waiting => 'Waiting for the opponent to join',
    MatchStatus::Ready => 'Both players are seated — the game starts with white’s first move',
    default => ucfirst($match->status->value),
};
?>
<div class="game"
     data-match-id="<?= e($match->id) ?>"
     data-status="<?= e($match->status->value) ?>"
     data-role="<?= e($role->value) ?>">
    <h1><?= e($heading) ?></h1>

    <dl class="summary">
        <dt>Time control</dt>
        <dd><?= e($match->timeControl->label()) ?> per player</dd>
        <dt>White</dt>
        <dd><?= e($whitePlayer) ?></dd>
        <dt>Status</dt>
        <dd><?= e($statusLine) ?></dd>
    </dl>

    <?php if ($isWaiting): ?>
        <p class="standby" role="status">Waiting for the opponent to join. Nothing starts until they are here — the clocks stay put.</p>
    <?php endif; ?>

    <?php if ($showsCreatorLinks): ?>
        <section class="link-card link-card--private">
            <h2>Your link</h2>
            <p>Keep this one. It is private and always identifies you as the creator, even on another device.</p>
            <div class="copy-row">
                <input type="text" readonly value="<?= e($creatorUrl) ?>" aria-label="Your private creator link">
                <button type="button" data-copy>Copy</button>
            </div>
            <p class="hint">Do not share this link — it contains your secret token.</p>
        </section>

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
    <?php else: ?>
        <p class="lead">You are watching this challenge<?= $isWaiting ? '' : '. Both seats are taken, so you cannot move pieces' ?>.
            The board appears here once play begins.</p>
    <?php endif; ?>
</div>
