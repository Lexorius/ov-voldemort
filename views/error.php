<?php /** @var string $title @var string $message */ ?>
<div class="card" style="max-width:640px;margin:2rem auto;text-align:center">
  <h1><?= e($title) ?></h1>
  <p class="muted"><?= e($message) ?></p>
  <p><a class="btn btn--sec" href="<?= e(url('dashboard')) ?>">Zur Übersicht</a></p>
</div>
