<?php
/** @var array $kontakte @var int $ziel @var array $verweise @var array $vorschlag @var array $extraFelder */
$ids = array_keys($kontakte);

$felder = DUBLETTE_FELDER;
foreach ($extraFelder as $k => $def) {
    $felder['extra:' . $k] = $def['label'];
}

/** Anzeigewert eines Feldes */
$zeige = static function (array $c, string $feld) use ($extraFelder): string {
    $w = dublette_wert($c, $feld);
    if ($w === '') {
        return '';
    }
    if ($feld === 'kategorie_id') {
        return list_label((int)$w, '');
    }
    if (str_starts_with($feld, 'extra:')) {
        $def = $extraFelder[substr($feld, 6)] ?? null;
        if ($def && $def['type'] === 'bool') {
            return $w === '1' ? 'ja' : 'nein';
        }
        if ($def && $def['type'] === 'date') {
            return de_date($w);
        }
    }
    return $w;
};
?>
<div class="pagehead">
  <div>
    <h1>Kontakte zusammenführen</h1>
    <p>Ein Kontakt bleibt, die anderen werden gelöscht. Je Feld lässt sich wählen, welcher Wert
       bleibt; Verteiler, Termine, Gästelisten und Anwesenheiten aller Beteiligten hängen danach
       am verbliebenen Kontakt.</p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('contacts_dubletten')) ?>">Zurück zu den Dubletten</a>
</div>

<form method="post" class="form" action="<?= e(url('contacts_dubletten')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="merge">
  <?php foreach ($ids as $id): ?><input type="hidden" name="ids[]" value="<?= (int)$id ?>"><?php endforeach; ?>

  <section class="card">
    <div class="tablewrap">
      <table class="data">
        <thead>
          <tr>
            <th>Feld</th>
            <?php foreach ($ids as $id): $c = $kontakte[$id]; ?>
              <th>
                <label class="wahl" style="margin:0">
                  <input type="radio" name="ziel" value="<?= (int)$id ?>"<?= $id === $ziel ? ' checked' : ''?>>
                  <span>
                    <strong style="text-transform:none;letter-spacing:0"><?= e(contact_name($c)) ?></strong>
                    <span class="small muted" style="text-transform:none;letter-spacing:0;font-weight:400;display:block">
                      #<?= (int)$id ?> · angelegt <?= e(de_date($c['created_at'])) ?>
                      <?php if (!(int)$c['is_active']): ?> · inaktiv<?php endif; ?>
                    </span>
                    <span class="small" style="text-transform:none;letter-spacing:0;font-weight:400;display:block">bleibt erhalten</span>
                  </span>
                </label>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="small muted">Hängt daran</td>
            <?php foreach ($ids as $id): $v = $verweise[$id] ?? []; ?>
              <td class="small">
                <?php if (!$v): ?><span class="muted">nichts</span>
                <?php else: ?>
                  <?php $teile = []; foreach ($v as $lbl => $n) { $teile[] = $n . '× ' . $lbl; } echo e(implode(', ', $teile)); ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
          <?php foreach ($felder as $feld => $label):
              $werte = [];
              foreach ($ids as $id) {
                  $werte[$id] = $zeige($kontakte[$id], $feld);
              }
              $gefuellt = array_filter($werte, 'strlen');
              if (!$gefuellt) {
                  continue;   // nirgends ein Wert – nichts zu wählen
              }
              $alleGleich = count(array_unique($gefuellt)) === 1 && count($gefuellt) === count($ids);
              $gewaehlt = $vorschlag[$feld] ?? $ziel;
              $notizAlle = $feld === 'notiz' && count(array_unique($gefuellt)) > 1;
          ?>
            <tr>
              <td><strong class="small"><?= e($label) ?></strong></td>
              <?php foreach ($ids as $id): $w = $werte[$id]; ?>
                <td class="small">
                  <?php if ($alleGleich): ?>
                    <?= e($w) ?>
                    <?php if ($id === $ids[0]): ?><input type="hidden" name="feld[<?= e($feld) ?>]" value="<?= (int)$ziel ?>"><?php endif; ?>
                  <?php else: ?>
                    <label class="wahl" style="margin:0<?= $w === '' ? ';opacity:.6' : '' ?>">
                      <input type="radio" name="feld[<?= e($feld) ?>]" value="<?= (int)$id ?>"<?= $gewaehlt === $id ? ' checked' : '' ?>>
                      <span><?= $w === '' ? '<span class="muted">– leer –</span>' : nl2br(e(mb_substr($w, 0, 300))) ?></span>
                    </label>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <?php if ($notizAlle): ?>
              <tr class="zeile-ohne-rand">
                <td></td>
                <td colspan="<?= count($ids) ?>" class="small">
                  <label class="wahl" style="margin:0">
                    <input type="radio" name="feld[notiz]" value="alle"<?= $gewaehlt === 'alle' ? ' checked' : '' ?>>
                    <span>Alle Notizen untereinander behalten</span>
                  </label>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small muted mt mb0">Vorgeschlagen ist jeweils der Wert des Kontakts, der erhalten bleibt;
      hat er dort nichts stehen, der erste gefüllte. Der Haken „Aktiv" bleibt, wenn einer der Kontakte aktiv war.</p>
  </section>

  <div class="btnrow">
    <button class="btn btn--danger" type="submit"
            data-confirm="<?= e(sprintf('%d Kontakte zu einem zusammenführen? Die anderen werden gelöscht, ihre Verweise umgehängt.', count($ids))) ?>">
      Zusammenführen</button>
    <a class="btn btn--sec" href="<?= e(url('contacts_dubletten')) ?>">Abbrechen</a>
  </div>
</form>
