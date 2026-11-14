<?php
/** @var array $karte @var array $konfig @var array $symbole @var array $darf @var array $staerke */
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
      <?php if (!empty($darf['fahrzeugSetzen'])): ?>
        <button class="btn btn--sec btn--sm" type="button" data-karte-fahrzeug-setzen title="Ein Fahrzeug ohne Position in die Kartenmitte setzen">+ Fahrzeug</button>
      <?php endif; ?>
      <span class="small muted" data-karte-fahrzeuge-stand aria-live="polite"></span>
    <?php endif; ?>
    <?php if ($darf['verwalten']): ?>
      <button class="btn btn--sec btn--sm lagekarte__rechts" type="button" data-karte-merken title="Die Karte öffnet künftig mit dem Ausschnitt, der jetzt zu sehen ist">Ausschnitt merken</button>
    <?php endif; ?>
  </div>
  <div class="lagekarte__koerper">
    <div class="lagekarte__flaeche" data-karte-flaeche tabindex="0" aria-label="Karte <?= e((string)$karte['name']) ?>">
      <noscript><p class="empty">Die Karte braucht JavaScript.</p></noscript>
    </div>
    <aside class="lagekarte__seite" data-karte-seite aria-label="Stärke und Abschnitte">
      <?php /* Das Skript übernimmt die Leiste; bis dahin steht hier die Gesamtstärke aus der Anwendung */ ?>
      <div class="lagekarte__gesamt">
        <div class="lagekarte__gesamt-kopf">Gesamtstärke</div>
        <div class="lagekarte__gesamt-zahl" data-karte-gesamt><?= e($staerke['text']) ?></div>
        <div class="small muted">Führer / Unterführer / Helfer / Gesamt · <?= (int)$staerke['einheiten'] === 1 ? '1 Einheit' : (int)$staerke['einheiten'] . ' Einheiten' ?></div>
      </div>
      <noscript><p class="small muted">Abschnitte und Einheiten brauchen JavaScript.</p></noscript>
    </aside>
  </div>
  <p class="lagekarte__meldung" data-karte-meldung role="status" aria-live="polite" hidden></p>
  <p class="small muted" style="margin:.5rem 0 0">
    <?php if ($darf['setzen']): ?>
      <strong>Rechtsklick</strong> auf die Karte setzt ein Symbol (am Handy lange drücken oder „+ Symbol"). Ein Symbol <strong>ziehen</strong> versetzt es,
      <strong>anklicken</strong> öffnet Beschriftung, Stärke, Abschnitt, Größe und Drehung. Neben der Karte stehen die Einheiten nach Abschnitten mit ihrer Stärke –
      dort lassen sich Abschnitte anlegen und Einheiten hinzufügen, ändern und löschen, auch ohne Stelle auf der Karte.
      <?php if (!$symbole): ?><br>Noch ist die <a href="<?= e(url('karten_symbole')) ?>">Symbolbibliothek</a> leer<?= $darf['verwalten'] ? ' – dort zuerst SVG oder Bilder hochladen' : '' ?>.<?php endif; ?>
    <?php else: ?>
      Ziehen verschiebt die Karte, Mausrad oder + und − zoomen. Neben der Karte stehen die Einheiten nach Abschnitten mit ihrer Stärke.
    <?php endif; ?>
    <?php if (!empty($darf['fahrzeugSetzen'])): ?>
      Ein Fahrzeug, das sich <?= (int)karten_fahrzeug_hand_minuten() ?> Minuten nicht per GPS (Divera, QR-Code) gemeldet hat, lässt sich <strong>ziehen</strong> –
      die Position wird am Fahrzeug gespeichert. „+ Fahrzeug" setzt eines, das noch gar keine Position hat.
    <?php endif; ?>
    Die Kartenkacheln lädt der Browser direkt vom Kartendienst.
  </p>
</div>
