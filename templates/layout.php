<?php
/** @var string $title */
/** @var string $content */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="/assets/vendor/chessground.css">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <main>
        <a class="brand" href="/">Casual Chess</a>
        <?= $content ?>
    </main>
    <script src="/assets/app.js" type="module"></script>
</body>
</html>
