<?php

use Chess\Game\GameMatch;
use Chess\Game\MatchStatus;
use Chess\Game\Role;

/** @var GameMatch $match */
/** @var Role $role */
/** @var string $playUrl */
/** @var string|null $creatorUrl */

$isCreator = $role === Role::Creator && $creatorUrl !== null;
?>
<h1><?= $isCreator ? 'Your challenge is ready' : 'Chess challenge' ?></h1>

<dl class="summary">
    <dt>Time control</dt>
    <dd><?= e($match->timeControl->label()) ?> per player</dd>
    <dt>White</dt>
    <dd><?= $isCreator
        ? ($match->creatorPlaysWhite() ? 'You' : 'Your opponent')
        : ($match->creatorPlaysWhite() ? 'The creator' : 'The opponent') ?></dd>
    <dt>Status</dt>
    <dd><?= $match->status === MatchStatus::Waiting
        ? 'Waiting for the opponent to join'
        : e(ucfirst($match->status->value)) ?></dd>
</dl>

<?php if ($isCreator): ?>
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
<?php else: ?>
    <p class="lead">You are watching this challenge. It has not started yet — the board appears once both players are seated.</p>
<?php endif; ?>
