<?php
/**
 * @var ?array $b @var array $errors @var array $lieferanten @var int $lieferantId @var int $standortId @var string $notiz
 * @var array $getraenke @var array $pos @var array $summe @var array $plaetze
 */
$isNew = $b === null;
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Bestellung anlegen' : 'Bestellung ' . e((string)$b['nummer']) . ' bearbeiten' ?></h1>
    <p>Lieferant wählen, Anzahl je Getränk eintragen. Vorgeschlagen wird, was bis zum Mindestbestand fehlt oder
       leer gemeldet ist. Verschickt wird erst auf der nächsten Seite nach dem Prüfen.</p>
  </div>
  <div class="btnrow"><a class="btn btn--sec" href="<?= e($isNew ? url('getraenke') : url('getraenke_bestellung', ['id' => $b['id']])) ?>">Abbrechen</a></div>
</div>

<?php foreach ($errors as $f): ?><div class="alert alert--error"><?= e($f) ?></div><?php endforeach; ?>
<?php if (!$lieferanten): ?>
  <div class="alert alert--warn">Noch kein Lieferant – erst einen <a href="<?= e(url('getraenke_lieferant_edit')) ?>">anlegen</a>.</div>
<?php endif; ?>

<form method="post" class="form" id="bestellung">
  <?= csrf_field() ?>
  <section class="card">
    <div class="grid2">
      <div class="field">
        <label for="lieferant_id">Lieferant</label>
        <select id="lieferant_id" name="lieferant_id" required<?= $isNew ? ' onchange="window.location = ' . e(json_encode(url('getraenke_bestellung_edit'))) . ' + \'&amp;lieferant_id=\' + this.value"' : '' ?>>
          <option value="">– Lieferant wählen –</option>
          <?php foreach ($lieferanten as $l): ?>
            <option value="<?= (int)$l['id'] ?>"<?= (int)$l['id'] === $lieferantId ? ' selected' : '' ?>><?= e((string)$l['name']) ?><?= trim((string)$l['email']) === '' ? ' (ohne E-Mail)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="standort_id">Abladen an <span class="muted">(steht in der E-Mail, freiwillig)</span></label>
        <select id="standort_id" name="standort_id">
          <option value="">– kein Platz –</option>
          <?php foreach ($plaetze as $p): ?>
            <option value="<?= (int)$p['id'] ?>"<?= (int)$p['id'] === $standortId ? ' selected' : '' ?>><?= e((string)$p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="grid-column:1/-1">
        <label for="notiz">Nachricht an den Lieferanten <span class="muted">(steht in der E-Mail)</span></label>
        <textarea id="notiz" name="notiz" rows="2" maxlength="1000" placeholder="z. B. Lieferung bitte Donnerstag vormittags, Leergut steht bereit."><?= e($notiz) ?></textarea>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h2>Getränke</h2><span class="small muted">Anzahl je Gebinde; 0 = nicht bestellen</span></div>
    <?php $anzeige = array_values(array_filter($getraenke, static fn($g) => $lieferantId <= 0 || (int)($g['lieferant_id'] ?? 0) === $lieferantId)); ?>
    <?php if (!$anzeige): ?>
      <div class="empty"><?= $lieferantId > 0 ? 'Dieser Lieferant hat noch keine Getränke.' : 'Erst einen Lieferanten wählen.' ?></div>
    <?php else: ?>
      <div class="tablewrap">
        <table class="data">
          <thead><tr><th>Getränk</th><th class="num">Bestand</th><th class="num">Mindest</th><th class="num">Preis</th><th class="num">Bestellen</th></tr></thead>
          <tbody>
          <?php foreach ($anzeige as $g): $gid = (int)$g['id']; ?>
            <tr>
              <td><?= e((string)$g['name']) ?> <span class="small muted"><?= e((string)$g['gebinde']) ?></span></td>
              <td class="num"><?= (int)($summe[$gid] ?? 0) ?></td>
              <td class="num muted"><?= (int)$g['mindestbestand'] ?></td>
              <td class="num"><?= (float)$g['preis'] > 0 ? e(money((float)$g['preis'])) : '–' ?></td>
              <td class="num"><input type="number" name="pos[<?= $gid ?>]" min="0" max="9999" value="<?= (int)($pos[$gid] ?? 0) ?>" style="width:6rem" aria-label="Anzahl <?= e((string)$g['name']) ?>"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Als Entwurf anlegen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e($isNew ? url('getraenke') : url('getraenke_bestellung', ['id' => $b['id']])) ?>">Abbrechen</a>
  </div>
</form>
