<?php
/** @var string|null $error */
/** @var string|null $minutes */
/** @var string|null $white */
$error ??= null;
$minutes ??= '5';
$white ??= 'creator';
?>
<h1>Challenge someone to chess</h1>
<p class="lead">No account needed. Pick a time control, choose colors, and you get two links: one to keep, one to share.</p>

<?php if ($error !== null): ?>
    <p class="error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" action="/challenges">
    <fieldset>
        <legend>Time per player</legend>
        <label>
            <input type="radio" name="minutes" value="5" <?= $minutes === '5' ? 'checked' : '' ?>>
            5 minutes
        </label>
        <label>
            <input type="radio" name="minutes" value="10" <?= $minutes === '10' ? 'checked' : '' ?>>
            10 minutes
        </label>
    </fieldset>

    <fieldset>
        <legend>Who plays white</legend>
        <label>
            <input type="radio" name="white" value="creator" <?= $white === 'creator' ? 'checked' : '' ?>>
            I do
        </label>
        <label>
            <input type="radio" name="white" value="opponent" <?= $white === 'opponent' ? 'checked' : '' ?>>
            My opponent does
        </label>
    </fieldset>

    <button type="submit">Create challenge</button>
</form>
