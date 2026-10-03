<?php
/** @var int $jahr @var int $ab @var array $kandidaten @var array $gewaehlt @var string $text @var string $betreff @var string $an @var float $summe */
$mailto = 'mailto:' . rawurlencode($an) . '?subject=' . rawurlencode($betreff) . '&body=' . rawurlencode($text);
?>
<div class="pagehead">
  <div>
    <h1>Anfrage an die Regionalstelle</h1>
    <p>Vorlage für eine E-Mail: Welche Abrechnungen noch auf Geld warten, mit der Bitte um den Bearbeitungsstand.
      Text anpassen, kopieren oder direkt im Mailprogramm öffnen.</p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('expenses', ['jahr' => $jahr, 'art' => 'einnahme'])) ?>#einnahmen-stand">Zu den Einnahmen</a>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="einnahmen_anfrage">
  <input type="hidden" name="jahr" value="<?= (int)$jahr ?>">
  <div class="filters">
    <div class="field">
      <label for="ab">Welche Abrechnungen</label>
      <select id="ab" name="ab">
        <option value="30"<?= $ab === 30 ? ' selected' : '' ?>>nur, was länger als 30 Tage wartet</option>
        <option value="0"<?= $ab === 0 ? ' selected' : '' ?>>alle, die noch nicht eingegangen sind</option>
      </select>
    </div>
  </div>
</form>

<?php if (!$kandidaten): ?>
  <div class="card"><div class="empty">Nichts offen – alle Abrechnungen sind eingegangen<?= $ab > 0 ? ' oder jünger als 30 Tage' : '' ?>.</div></div>
<?php else: ?>
<form method="get" class="card">
  <input type="hidden" name="p" value="einnahmen_anfrage">
  <input type="hidden" name="jahr" value="<?= (int)$jahr ?>">
  <input type="hidden" name="ab" value="<?= (int)$ab ?>">
  <div class="card__head">
    <h2>Abrechnungen in der Anfrage</h2>
    <span class="small muted"><?= count($gewaehlt) ?> von <?= count($kandidaten) ?> · <?= e(money($summe)) ?></span>
  </div>
  <div class="tablewrap">
    <table class="data">
      <thead><tr><th></th><th>Abrechnung</th><th>Stand</th><th>wartet seit</th><th class="num">Betrag</th></tr></thead>
      <tbody>
      <?php foreach ($kandidaten as $e): $a = $e['alter']; ?>
        <tr>
          <td style="width:2.5rem"><input type="checkbox" name="sel[]" value="<?= (int)$e['id'] ?>" id="s<?= (int)$e['id'] ?>"<?= in_array((int)$e['id'], $gewaehlt, true) ? ' checked' : '' ?>></td>
          <td><label for="s<?= (int)$e['id'] ?>"><strong><?= e((string)$e['bezeichnung']) ?></strong></label>
            <div class="small muted"><?= e(trim(implode(' · ', array_filter([$e['referenz'] !== '' ? 'Einsatz/Auftrag ' . $e['referenz'] : '', $e['beleg_nr'] !== '' ? 'Az. ' . $e['beleg_nr'] : '', (string)$e['lieferant']])))) ?></div></td>
          <td><?= buchung_status_badge((string)$e['status']) ?></td>
          <td class="nowrap small"><span style="color:<?= EINNAHME_WARN_FARBEN[$a['stufe']] ?? 'inherit' ?>;font-weight:700"><?= (int)$a['tage'] ?> Tagen</span> <span class="muted">(<?= e(de_date($a['seit'])) ?>)</span></td>
          <td class="num"><?= e(money((float)$e['betrag_brutto'], false)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="btnrow mt"><button class="btn btn--sec" type="submit">Auswahl übernehmen</button></div>
</form>

<section class="card">
  <div class="card__head">
    <h2>E-Mail</h2>
    <div class="btnrow">
      <button class="btn btn--sec btn--sm" type="button" data-kopieren="#anfrage-text">Text kopieren</button>
      <button class="btn btn--sec btn--sm" type="button" onclick="window.print()">Drucken</button>
      <a class="btn btn--sm" href="<?= e($mailto) ?>"<?= $gewaehlt ? '' : ' aria-disabled="true"' ?>>Im Mailprogramm öffnen</a>
    </div>
  </div>
  <div class="grid2">
    <div class="field">
      <label for="anfrage-an">An</label>
      <input type="text" id="anfrage-an" value="<?= e($an) ?>" readonly placeholder="Adresse der Regionalstelle unter Einstellungen → Budget">
    </div>
    <div class="field">
      <label for="anfrage-betreff">Betreff</label>
      <input type="text" id="anfrage-betreff" value="<?= e($betreff) ?>" readonly>
    </div>
  </div>
  <div class="field">
    <label for="anfrage-text">Text</label>
    <textarea id="anfrage-text" rows="<?= min(40, 14 + count($gewaehlt) * 3) ?>" class="mono" style="font-size:.9rem"><?= e($text) ?></textarea>
    <small>Der Text lässt sich hier vor dem Kopieren anpassen. Lange Listen passen nicht immer in den Mail-Link – dann kopieren.</small>
  </div>
</section>
<?php endif; ?>
