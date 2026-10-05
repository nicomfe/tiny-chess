<?php
/** @var string|null $error */
/** @var string|null $minutes */
/** @var string|null $white */

$error ??= null;
$minutes ??= '5';
$white ??= 'creator';
?>
<div class="create-home">
    <div class="share-wait__shell create-home__shell">
        <div class="share-wait__column">
            <h1 class="share-wait__title">Challenge someone to chess</h1>
            <p class="share-wait__lead">No account needed</p>

            <?php if ($error !== null): ?>
                <p class="error create-home__error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <form method="post" action="/challenges" class="create-home__form">
                <section class="create-home__block" aria-labelledby="create-time">
                    <h2 id="create-time" class="create-home__heading">Time per player</h2>
                    <div class="create-home__outline-pair" role="radiogroup" aria-labelledby="create-time">
                        <label class="create-home__outline-opt">
                            <input type="radio" name="minutes" value="5" <?= $minutes === '5' ? 'checked' : '' ?>>
                            <span>5 min</span>
                        </label>
                        <label class="create-home__outline-opt">
                            <input type="radio" name="minutes" value="10" <?= $minutes === '10' ? 'checked' : '' ?>>
                            <span>10 min</span>
                        </label>
                    </div>
                </section>

                <section class="create-home__block" aria-labelledby="create-white">
                    <h2 id="create-white" class="create-home__heading">Who plays white</h2>
                    <div class="create-home__outline-pair" role="radiogroup" aria-labelledby="create-white">
                        <label class="create-home__outline-opt">
                            <input type="radio" name="white" value="creator" <?= $white === 'creator' ? 'checked' : '' ?>>
                            <span>I do</span>
                        </label>
                        <label class="create-home__outline-opt">
                            <input type="radio" name="white" value="opponent" <?= $white === 'opponent' ? 'checked' : '' ?>>
                            <span>Opponent</span>
                        </label>
                    </div>
                </section>

                <div class="share-wait__cta create-home__submit-wrap">
                    <button type="submit" class="share-wait__share-btn">Create challenge</button>
                </div>
            </form>
        </div>
    </div>
</div>
