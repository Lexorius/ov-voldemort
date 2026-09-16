<?php
/** @var array $ergebnis @var string $dateiname @var ?array $gruppe */
$gespeichert = $ergebnis['neu'] + $ergebnis['ergaenzt'] + $ergebnis['ueberschrieben'];
?>
<div class="pagehead">
  <div>
    <h1>Import abgeschlossen</h1>
    <p class="muted small"><?= e($dateiname) ?></p>
  </div>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('contacts', ['sort' => 'neu'])) ?>">Zu den Kontakten</a>
    <a class="btn btn--sec" href="<?= e(url('contacts_import')) ?>">Weitere Datei</a>
  </div>
</div>

<div class="alert alert--<?= $ergebnis['fehler'] > 0 ? 'warn' : 'success' ?>">
  <?= (int)$gespeichert ?> Kontakt(e) gespeichert.
  <?php if ($gruppe && $ergebnis['verteiler'] > 0): ?>
    <?= (int)$ergebnis['verteiler'] ?> davon neu auf dem Verteiler
    <a href="<?= e(url('contact_group', ['id' => $gruppe['id']])) ?>"><?= e($gruppe['name']) ?></a>.
  <?php endif; ?>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Neu angelegt</div>
    <div class="stat__value" style="color:var(--ok)"><?= (int)$ergebnis['neu'] ?></div></div>
  <div class="stat"><div class="stat__label">Ergänzt / überschrieben</div>
    <div class="stat__value"><?= (int)$ergebnis['ergaenzt'] + (int)$ergebnis['ueberschrieben'] ?></div>
    <div class="stat__hint"><?= (int)$ergebnis['unveraendert'] ?> ohne Änderung</div></div>
  <div class="stat"><div class="stat__label">Übersprungen</div>
    <div class="stat__value"><?= (int)$ergebnis['uebersprungen'] ?></div></div>
  <div class="stat"><div class="stat__label">Unbrauchbar</div>
    <div class="stat__value" style="<?= $ergebnis['fehler'] > 0 ? 'color:var(--bad)' : '' ?>"><?= (int)$ergebnis['fehler'] ?></div></div>
</div>

<?php if ($ergebnis['hinweise']): ?>
  <section class="card">
    <h2>Nicht übernommen</h2>
    <ul class="small" style="margin:0;padding-left:1.1rem">
      <?php foreach ($ergebnis['hinweise'] as $h): ?><li><?= e($h) ?></li><?php endforeach; ?>
    </ul>
    <?php if (count($ergebnis['hinweise']) >= 200): ?>
      <p class="small muted">Es werden höchstens 200 Hinweise angezeigt.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>
