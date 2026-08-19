<?php
/**
 * @var \League\Plates\Template\Template $this
 * @var string $title
 * @var string $tagline
 */

$this->layout('layout::base', ['title' => $title]);
?>
<h1><?= $this->e($title) ?></h1>
<p><?= $this->e($tagline) ?></p>
<p><a href="/health">Health check</a></p>
