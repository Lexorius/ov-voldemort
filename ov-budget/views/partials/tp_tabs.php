<?php
/** Reiter im Themenspeicher  @var string $tab */
?>
<div class="tabs">
  <a class="tab<?= $tab === 'offen' ? ' is-active' : '' ?>" href="<?= e(url('talking_points')) ?>">Offen</a>
  <a class="tab<?= $tab === 'archiv' ? ' is-active' : '' ?>" href="<?= e(url('talking_points', ['tab' => 'archiv'])) ?>">Archiv</a>
</div>
