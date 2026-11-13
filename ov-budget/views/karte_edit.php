<?php
/** @var array $karte @var array $errors @var int $marker */
$isNew = empty($karte['id']);
$zurueck = $isNew ? url('karten') : url('karte', ['id' => $karte['id']]);
$mittelpunkt = (string)($karte['mittelpunkt'] ?? sprintf('%.6f, %.6f', (float)$karte['lat'], (float)$karte['lng']));
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Karte anlegen' : 'Karte bearbeiten' ?></h1>
    <p class="muted small">Eine Karte ist ein benannter Ausschnitt mit eigenen Symbolen. Mittelpunkt und Zoom lassen sich hier eintragen – einfacher ist es, die Karte zurechtzuschieben und dort „Ausschnitt merken" zu drücken.</p>
  </div>
  <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form" action="<?= e($isNew ? url('karte_edit') : url('karte_edit', ['id' => $karte['id']])) ?>">
  <?= csrf_field() ?>
  <section class="card">
    <div class="field">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" required maxlength="150" value="<?= e((string)($karte['name'] ?? '')) ?>" placeholder="z. B. Gelände der Unterkunft" autofocus>
    </div>
    <div class="field">
      <label for="beschreibung">Beschreibung <span class="muted">(freiwillig)</span></label>
      <textarea id="beschreibung" name="beschreibung" rows="2" maxlength="500"><?= e((string)($karte['beschreibung'] ?? '')) ?></textarea>
    </div>
    <div class="grid2">
      <div class="field">
        <label for="mittelpunkt">Mittelpunkt <span class="muted">(Breite, Länge)</span></label>
        <input type="text" id="mittelpunkt" name="mittelpunkt" value="<?= e($mittelpunkt) ?>" placeholder="53.0793, 8.8017" inputmode="decimal">
        <?php if ($isNew): ?><small class="muted">Vorbelegt ist die Unterkunft, wenn sie unter Stell- und Lagerplätze Koordinaten hat – sonst die Mitte Deutschlands.</small><?php endif; ?>
      </div>
      <div class="field">
        <label for="zoom">Zoomstufe <span class="muted">(<?= KARTEN_ZOOM_MIN ?> = Welt, <?= KARTEN_ZOOM_MAX ?> = Haus)</span></label>
        <input type="number" id="zoom" name="zoom" min="<?= KARTEN_ZOOM_MIN ?>" max="<?= KARTEN_ZOOM_MAX ?>" value="<?= (int)($karte['zoom'] ?? 14) ?>">
      </div>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="fahrzeuge" name="fahrzeuge" value="1" <?= !empty($karte['fahrzeuge']) ? 'checked' : '' ?>>
      <label for="fahrzeuge">Fahrzeuge mit gemeldeter Position von Anfang an einblenden</label>
    </div>
    <p class="small muted">Jeder kann die Fahrzeuge auf der Karte selbst ein- und ausschalten; der Haken ist nur die Vorgabe. Solange sie eingeblendet sind, erneuern sich die Positionen von selbst. Zu sehen sind sie für alle, die die Karten sehen – auch ohne Zugang zum Fahrzeugmodul.</p>
    <div class="btnrow">
      <button class="btn" type="submit"><?= $isNew ? 'Anlegen' : 'Speichern' ?></button>
      <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
    </div>
  </section>
</form>

<?php if (!$isNew): ?>
  <form method="post" class="card" action="<?= e(url('karte_edit', ['id' => $karte['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="loeschen">
    <button class="btn btn--danger" type="submit" data-confirm="Karte „<?= e((string)$karte['name']) ?>“<?= $marker > 0 ? ' samt ' . ($marker === 1 ? 'ihrem gesetzten Symbol' : 'ihren ' . (int)$marker . ' gesetzten Symbolen') : '' ?> löschen?">Karte löschen</button>
    <span class="small muted" style="margin-left:.6rem">Die Symbole in der Bibliothek bleiben.</span>
  </form>
<?php endif; ?>
