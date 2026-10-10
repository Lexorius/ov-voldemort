<?php
/** @var int $jahr @var array $konten @var array $jeKonto (konto_id => Kategorien, 0 = ohne) */
$jeKonto ??= [];
$chips = static function (array $liste): string {
    $html = '';
    foreach ($liste as $k) {
        $html .= '<span class="chip' . ((int)$k['is_active'] !== 1 ? ' is-muted' : '') . '" style="border-left:4px solid ' . e((string)$k['color']) . '">'
            . e((string)$k['label']) . ' <span class="muted small">' . e(OBJEKTKONTO_LISTEN[(string)$k['list_key']] ?? '') . '</span></span>';
    }
    return $html;
};
?>
<div class="pagehead">
  <div>
    <h1>Objektkonten</h1>
    <p>Ein Objektkonto fasst mehrere Kategorien für die Auswertung zusammen – etwa „Getränke" und „Lebensmittel"
      auf „Verpflegung". Die Statistik nach Kategorien bleibt, wie sie ist; die Auswertung nach Objektkonten kommt dazu.
      Jede Kategorie gehört zu höchstens einem Konto.</p>
  </div>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('objektkonto_edit')) ?>">+ Objektkonto</a>
    <a class="btn btn--sec" href="<?= e(url('budget_objektkonten', ['jahr' => $jahr])) ?>">Zur Auswertung</a>
  </div>
</div>

<?= render_partial('partials/budget_tabs', ['jahr' => $jahr]) ?>

<section class="card">
  <?php if (!$konten): ?>
    <div class="empty">Noch kein Objektkonto angelegt.<br><a href="<?= e(url('objektkonto_edit')) ?>">Erstes Objektkonto anlegen</a> – mit Nummer, Name und den Kategorien, die dazugehören.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Konto</th><th>Kategorien</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($konten as $k): ?>
          <tr<?= (int)$k['is_active'] !== 1 ? ' class="is-muted"' : '' ?>>
            <td>
              <a href="<?= e(url('objektkonto_edit', ['id' => $k['id']])) ?>"><strong><span style="display:inline-block;width:.7rem;height:.7rem;border-radius:50%;background:<?= e((string)$k['color']) ?>;margin-right:.3rem"></span><?= e(objektkonto_titel($k)) ?></strong></a>
              <?php if ((int)$k['is_active'] !== 1): ?><span class="badge badge--muted">stillgelegt</span><?php endif; ?>
              <?php if (trim((string)$k['beschreibung']) !== ''): ?><div class="small muted"><?= e((string)$k['beschreibung']) ?></div><?php endif; ?>
            </td>
            <td><?php if (!empty($jeKonto[(int)$k['id']])): ?><div class="chips"><?= $chips($jeKonto[(int)$k['id']]) ?></div><?php else: ?><span class="muted small">noch keine Kategorie</span><?php endif; ?></td>
            <td class="nowrap" style="text-align:right">
              <a class="btn btn--sec btn--sm" href="<?= e(url('objektkonto_edit', ['id' => $k['id']])) ?>">Bearbeiten</a>
              <form method="post" class="inline-form"><?= csrf_field() ?>
                <input type="hidden" name="action" value="aktiv"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                <button class="btn btn--sec btn--sm" type="submit"><?= (int)$k['is_active'] === 1 ? 'Stilllegen' : 'Aktivieren' ?></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if (!empty($jeKonto[0])): ?>
<section class="card">
  <div class="card__head"><h2>Noch keinem Objektkonto zugeordnet</h2><span class="muted small"><?= count($jeKonto[0]) ?></span></div>
  <p class="small muted">Diese Kategorien laufen in der Auswertung unter „ohne Objektkonto". Zuordnen geht im jeweiligen Konto unter „Bearbeiten".</p>
  <div class="chips"><?= $chips($jeKonto[0]) ?></div>
</section>
<?php endif; ?>
