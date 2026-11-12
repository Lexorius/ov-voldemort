<?php
/** @var array $karte @var array $konfig @var array $symbole @var array $darf */
$json = (string)json_encode($konfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)$karte['name']) ?></h1>
    <?php if ((string)$karte['beschreibung'] !== ''): ?><p><?= e((string)$karte['beschreibung']) ?></p><?php endif; ?>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('karten')) ?>">Alle Karten</a>
    <a class="btn btn--sec" href="<?= e(url('karten_symbole')) ?>">Symbole</a>
    <?php if ($darf['verwalten']): ?><a class="btn btn--sec" href="<?= e(url('karte_edit', ['id' => $karte['id']])) ?>">Bearbeiten</a><?php endif; ?>
  </div>
</div>

<div class="card lagekarte" data-lagekarte="<?= e($json) ?>">
  <div class="lagekarte__leiste">
    <?php if ($darf['setzen']): ?>
      <button class="btn btn--sec btn--sm" type="button" data-karte-setzen title="Öffnet die Symbolauswahl in der Kartenmitte">+ Symbol</button>
    <?php endif; ?>
    <?php if ($darf['fahrzeuge']): ?>
      <label class="chip"><input type="checkbox" data-karte-fahrzeuge> Fahrzeuge</label>
      <span class="small muted" data-karte-fahrzeuge-stand aria-live="polite"></span>
    <?php endif; ?>
    <?php if ($darf['verwalten']): ?>
      <button class="btn btn--sec btn--sm lagekarte__rechts" type="button" data-karte-merken title="Die Karte öffnet künftig mit dem Ausschnitt, der jetzt zu sehen ist">Ausschnitt merken</button>
    <?php endif; ?>
  </div>
  <div class="lagekarte__flaeche" data-karte-flaeche tabindex="0" aria-label="Karte <?= e((string)$karte['name']) ?>">
    <noscript><p class="empty">Die Karte braucht JavaScript.</p></noscript>
  </div>
  <p class="lagekarte__meldung" data-karte-meldung role="status" aria-live="polite" hidden></p>
  <p class="small muted" style="margin:.5rem 0 0">
    <?php if ($darf['setzen']): ?>
      <strong>Rechtsklick</strong> auf die Karte setzt ein Symbol (am Handy lange drücken oder „+ Symbol"). Ein Symbol <strong>ziehen</strong> versetzt es,
      <strong>anklicken</strong> öffnet Beschriftung, Größe und Drehung.
      <?php if (!$symbole): ?><br>Noch ist die <a href="<?= e(url('karten_symbole')) ?>">Symbolbibliothek</a> leer<?= $darf['verwalten'] ? ' – dort zuerst SVG oder Bilder hochladen' : '' ?>.<?php endif; ?>
    <?php else: ?>
      Ziehen verschiebt die Karte, Mausrad oder + und − zoomen.
    <?php endif; ?>
    Die Kartenkacheln lädt der Browser direkt vom Kartendienst.
  </p>
</div>
