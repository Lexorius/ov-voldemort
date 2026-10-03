<?php
/** @var array $b @var array $frei @var array $gewaehlt @var array $errors @var array $wuensche */
$isNew = empty($b['id']);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Bestellung anlegen' : 'Bestellung ' . e((string)$b['nummer']) . ' bearbeiten' ?></h1>
    <?php if ($isNew): ?><p>Freigegebene Wünsche auswählen, die zusammen bestellt werden. Sie bekommen den Status „bestellt".</p><?php endif; ?>
  </div>
  <a class="btn btn--sec" href="<?= e($isNew ? url('bestellungen') : url('bestellung', ['id' => $b['id']])) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form" action="<?= e(url('bestellung_edit', $isNew ? [] : ['id' => $b['id']])) ?>">
  <?= csrf_field() ?>
  <?php if ($isNew): ?>
    <section class="card">
      <h2>Wünsche</h2>
      <?php if (!$frei): ?>
        <div class="empty">Kein freigegebener Wunsch wartet auf Bestellung.</div>
      <?php else: ?>
        <div class="tablewrap">
          <table class="data">
            <thead><tr><th></th><th>Wunsch</th><th>Fachgruppe</th><th>Lieferant</th><th class="num">Betrag</th></tr></thead>
            <tbody>
            <?php foreach ($frei as $w): ?>
              <tr>
                <td style="width:2.5rem"><input type="checkbox" name="wishes[]" value="<?= (int)$w['id'] ?>" id="w<?= (int)$w['id'] ?>"<?= in_array((int)$w['id'], $gewaehlt, true) ? ' checked' : '' ?>></td>
                <td><label for="w<?= (int)$w['id'] ?>"><strong><?= e((string)$w['bezeichnung']) ?></strong></label>
                  <?php if ($w['fahrzeug'] ?? ''): ?><div class="small muted">Fahrzeug: <?= e((string)$w['fahrzeug']) ?></div><?php endif; ?></td>
                <td class="small"><?= e((string)($w['fachgruppe_label'] ?: '–')) ?></td>
                <td class="small"><?= e((string)($w['lieferant'] ?: '–')) ?></td>
                <td class="num"><?= e(money((float)$w['netto_gesamt'], false)) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <section class="card">
      <h2>Wünsche</h2>
      <ul>
        <?php foreach ($wuensche as $w): ?><li><a href="<?= e(url('wish', ['id' => $w['id']])) ?>"><?= e((string)$w['bezeichnung']) ?></a> · <?= e(money((float)$w['netto_gesamt'])) ?></li><?php endforeach; ?>
      </ul>
      <p class="small muted">Welche Wünsche dazugehören, steht beim Anlegen fest.</p>
    </section>
  <?php endif; ?>

  <section class="card">
    <h2>Bestellung</h2>
    <div class="grid3">
      <div class="field">
        <label for="lieferant">Lieferant *</label>
        <input type="text" id="lieferant" name="lieferant" required maxlength="150" value="<?= e((string)$b['lieferant']) ?>">
      </div>
      <div class="field">
        <label for="bestellt_am">Bestellt am</label>
        <input type="date" id="bestellt_am" name="bestellt_am" value="<?= e((string)$b['bestellt_am']) ?>">
      </div>
      <div class="field">
        <label for="bestell_nr">Auftrags- / Bestellnummer</label>
        <input type="text" id="bestell_nr" name="bestell_nr" maxlength="100" value="<?= e((string)$b['bestell_nr']) ?>" placeholder="vom Lieferanten">
      </div>
    </div>
    <div class="field">
      <label for="notiz">Notiz</label>
      <textarea id="notiz" name="notiz"><?= e((string)($b['notiz'] ?? '')) ?></textarea>
    </div>
  </section>

  <div class="btnrow">
    <button class="btn" type="submit"<?= $isNew && !$frei ? ' disabled' : '' ?>><?= $isNew ? 'Bestellung anlegen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e($isNew ? url('bestellungen') : url('bestellung', ['id' => $b['id']])) ?>">Abbrechen</a>
  </div>
</form>
