<?php
/** @var string $version @var array $abschnitte @var bool $alle */
?>
<div class="pagehead">
  <div>
    <h1>Was ist neu</h1>
    <p>Es läuft <strong>OV-Budget <?= e($version) ?></strong>.</p>
  </div>
</div>

<?php if (!$abschnitte): ?>
  <div class="card"><div class="empty">Kein Änderungsverlauf vorhanden.</div></div>
<?php endif; ?>

<?php foreach ($abschnitte as $a): ?>
  <section class="card changelog">
    <div class="card__head">
      <h2 style="margin:0">Version <?= e($a['version']) ?></h2>
      <?php if ($a['version'] === $version): ?><span class="badge" style="background:#15803d">läuft gerade</span><?php endif; ?>
    </div>
    <?= markdown_simple($a['text']) ?>
  </section>
<?php endforeach; ?>

<?php if (!$alle && count($abschnitte) >= 5): ?>
  <p><a href="<?= e(url('neu', ['alle' => 1])) ?>">Alle Versionen anzeigen</a></p>
<?php endif; ?>
