<?php
/**
 * Base layout.
 *
 * Escaping convention (docs/decisions.md D-014): ALL dynamic output goes
 * through $this->e(). Plates does not auto-escape; raw output of any
 * non-literal is a defect. Helpers that emit raw HTML must be named *Raw
 * and justified in review.
 *
 * @var \League\Plates\Template\Template $this
 * @var string $title
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title) ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <main>
        <?= $this->section('content') ?>
    </main>
</body>
</html>
