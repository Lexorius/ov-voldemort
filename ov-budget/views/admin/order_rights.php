<?php
/** @var array $rows @var array $funktionen @var array $benutzer @var bool $eigene */

$grenzeWert = static function (?array $r): string {
    if (!$r || $r['freigabe_grenze'] === null || $r['freigabe_grenze'] === '') {
        return '';
    }
    return number_format((float)$r['freigabe_grenze'], 2, ',', '');
};

$zeile = static function (string $key, string $label, ?array $r, string $hinweis = '') use ($grenzeWert): string {
    $k = e($key);
    return '<tr><td><strong>' . e($label) . '</strong>'
        . ($hinweis !== '' ? ' <span class="badge badge--muted">' . e($hinweis) . '</span>' : '') . '</td>'
        . '<td><input type="checkbox" aria-label="' . e($label . ' darf freigeben') . '" name="freigeben[' . $k . ']" value="1"'
        . ($r && (int)$r['darf_freigeben'] ? ' checked' : '') . '></td>'
        . '<td><input type="text" inputmode="decimal" style="max-width:9rem" aria-label="' . e('Freigabegrenze ' . $label) . '"'
        . ' name="grenze[' . $k . ']" value="' . e($grenzeWert($r)) . '" placeholder="unbegrenzt"></td>'
        . '<td><input type="checkbox" aria-label="' . e($label . ' darf als bestellt markieren') . '" name="bestellen[' . $k . ']" value="1"'
        . ($r && (int)$r['darf_bestellen'] ? ' checked' : '') . '></td></tr>';
};
?>
<div class="pagehead">
  <div>
    <h1>Bestellberechtigungen</h1>
    <p>Wer Wünsche mit „Freigegeben, bitte bestellen“ freigeben und wer sie als bestellt markieren darf –
       je Rolle und je Funktion im OV. Hat jemand mehrere Rollen oder Funktionen, reicht eine Berechtigung,
       und die höchste Freigabegrenze gilt.</p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
</div>

<form method="post" class="card" action="<?= e(url('admin_order_rights')) ?>">
  <?= csrf_field() ?>

  <div class="tablewrap">
    <table class="data">
      <thead>
        <tr><th>Rolle / Funktion</th><th>Darf freigeben</th><th>bis Betrag (€)</th><th>Darf bestellen</th></tr>
      </thead>
      <tbody>
        <tr><td colspan="4" class="small muted"><strong>Rollen</strong> – gelten für alle Benutzer mit dieser Rolle</td></tr>
        <?php foreach (BESTELL_ROLLEN as $rolle => $label): ?>
          <?= $zeile('rolle_' . $rolle, $label, $rows['rolle:' . $rolle] ?? null) ?>
        <?php endforeach; ?>
        <tr><td colspan="4" class="small muted"><strong>Funktionen</strong> – werden unter
          <a href="<?= e(url('admin_users')) ?>">Benutzer</a> zugeordnet</td></tr>
        <?php foreach ($funktionen as $f): ?>
          <?= $zeile('funktion_' . (int)$f['id'], $f['label'], $rows['funktion:' . (int)$f['id']] ?? null,
                (int)$f['is_active'] ? '' : 'deaktiviert') ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="small muted">Leeres Betragsfeld = unbegrenzt. Die Grenze gilt für den Gesamtbetrag des Wunsches; 0 heißt, nichts freigeben.</p>

  <div class="field field--check">
    <input type="checkbox" id="eigene_freigeben" name="eigene_freigeben" value="1"<?= $eigene ? ' checked' : '' ?>>
    <label for="eigene_freigeben">Eigene Wünsche selbst freigeben erlaubt</label>
  </div>
  <small class="muted">Ausgeschaltet gilt das Vier-Augen-Prinzip: Wer einen Wunsch angelegt hat, kann ihn nicht selbst freigeben.</small>

  <div class="btnrow" style="margin-top:1rem">
    <button class="btn" type="submit">Speichern</button>
  </div>
</form>

<section class="card">
  <h2>So wirkt es gerade</h2>
  <?php if (!$benutzer): ?>
    <div class="empty">Kein aktiver Benutzer darf derzeit freigeben oder bestellen.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Benutzer</th><th>Freigabe</th><th>Bestellen</th></tr></thead>
        <tbody>
        <?php foreach ($benutzer as $b): ?>
          <tr>
            <td><a href="<?= e(url('admin_user_edit', ['id' => $b['id']])) ?>"><?= e($b['name']) ?></a></td>
            <td>
              <?php if ($b['freigeben']): ?>
                <?= e(order_limit_text($b['grenze'])) ?>
                <div class="small muted">über <?= e(implode(', ', $b['freigabe_durch'])) ?></div>
              <?php else: ?><span class="muted">–</span><?php endif; ?>
            </td>
            <td>
              <?php if ($b['bestellen']): ?>
                ja <div class="small muted">über <?= e(implode(', ', $b['bestellen_durch'])) ?></div>
              <?php else: ?><span class="muted">–</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
