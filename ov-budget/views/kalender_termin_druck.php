<?php
/** Druckfassung eines Termins: dieselben Angaben, ohne Knöpfe, auf A4 hoch. Variablen wie kalender_termin. */
$ort = implode(' · ', array_filter(array_merge(array_map(static fn($s) => (string)$s['pfad'], $standorte), [trim((string)($termin['ort'] ?? ''))]), 'strlen'));
$weitere = implode(', ', array_map(static fn($f) => (string)$f['label'], $fachgruppen));
echo render_partial('partials/bericht_kopf', [
    'titel'      => (string)$termin['titel'],
    'untertitel' => $wann . ($ort !== '' ? ' · ' . $ort : ''),
    'rueck'      => url('kalender_termin', ['id' => $termin['id']]),
    'quer'       => false,
]);
?>
<div class="abschnitt">
  <h2>Auf einen Blick</h2>
  <table class="liste">
    <tbody>
      <tr><th style="width:28%">Wann</th><td><?= e($wann) ?></td></tr>
      <?php if ($ort !== ''): ?><tr><th>Wo</th><td><?= e($ort) ?></td></tr><?php endif; ?>
      <tr><th>Für wen</th><td><?= e($fuerWen) ?><?= $weitere !== '' ? ' – auch für ' . e($weitere) : '' ?></td></tr>
      <?php if (!empty($termin['erfasser'])): ?><tr><th>Eingetragen von</th><td><?= e((string)$termin['erfasser']) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if (trim((string)($termin['beschreibung'] ?? '')) !== ''): ?>
<div class="abschnitt"><h2>Beschreibung</h2><p><?= nl2br(e((string)$termin['beschreibung'])) ?></p></div>
<?php endif; ?>
<?php if ($fahrzeuge): ?>
<div class="abschnitt"><h2>Fahrzeuge</h2>
  <table class="liste"><thead><tr><th>Fahrzeug</th><th>Funkrufname</th><th>Kennzeichen</th></tr></thead><tbody>
    <?php foreach ($fahrzeuge as $v): ?><tr><td><?= e((string)$v['bezeichnung']) ?></td><td><?= e((string)$v['funkrufname']) ?></td><td><?= e((string)$v['kennzeichen']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
<?php if ($auftraege): ?>
<div class="abschnitt"><h2>Instandsetzungsaufträge</h2>
  <table class="liste"><thead><tr><th>Auftrag</th><th>Fahrzeug</th></tr></thead><tbody>
    <?php foreach ($auftraege as $o): ?><tr><td><?= e($o['nummer'] . ' ' . $o['titel']) ?></td><td><?= e((string)$o['fahrzeug']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
<?php if ($dienststellen): ?>
<div class="abschnitt"><h2>THW-Dienststellen</h2>
  <table class="liste"><thead><tr><th>Dienststelle</th><th>Art</th><th>Telefon</th><th>E-Mail</th></tr></thead><tbody>
    <?php foreach ($dienststellen as $d): ?><tr><td><?= e((string)$d['name']) ?><?= ($d['kurz'] ?? '') !== '' ? ' (' . e((string)$d['kurz']) . ')' : '' ?></td><td><?= e(THW_TYPEN[thw_typ((string)$d['typ'])]['label']) ?></td><td><?= e((string)($d['telefon'] ?? '')) ?></td><td><?= e((string)($d['email'] ?? '')) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
<?php if ($kontakte): ?>
<div class="abschnitt"><h2>Kontakte</h2>
  <table class="liste"><thead><tr><th>Name</th><th>Organisation</th><th>Telefon</th><th>E-Mail</th></tr></thead><tbody>
    <?php foreach ($kontakte as $c): ?><tr><td><?= e(contact_name($c)) ?></td><td><?= e(implode(' · ', array_filter([(string)($c['organisation'] ?? ''), (string)($c['position'] ?? '')]))) ?></td>
      <td><?= e(implode(' / ', array_filter([(string)($c['mobil'] ?? ''), (string)($c['telefon'] ?? '')]))) ?></td><td><?= e((string)($c['email'] ?? '')) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
<?php if ($dateien): ?>
<div class="abschnitt"><h2>Dateien</h2>
  <ul><?php foreach ($dateien as $f): ?><li><?= e((string)($f['titel'] ?: $f['orig_name'])) ?> <span class="klein">(<?= e((string)$f['orig_name']) ?>, <?= e(bytes_human((int)$f['size_bytes'])) ?>)</span></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="fuss"><span><?= e((string)setting('app_name', 'OV-Multitool')) ?></span><span>Termin · <?= e(de_date(substr((string)$termin['beginn'], 0, 10))) ?></span></div>
</body>
</html>
