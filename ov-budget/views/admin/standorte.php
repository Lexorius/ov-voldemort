<?php
/** @var array $baum @var array $alle @var array $zaehlung @var array $titelbilder */
$titelbilder ??= [];
$zeile = static function (array $s, array $geschwister) use (&$zeile, $titelbilder): void {
    $typ = STANDORT_TYPEN[standort_typ((string)$s['typ'])];
    $ids = array_map('intval', array_column($geschwister, 'id'));
    $pos = array_search((int)$s['id'], $ids, true);
    $kindtypen = standort_kindtypen((string)$s['typ']);
    ?>
    <div class="standort<?= (int)$s['is_active'] !== 1 ? ' standort--aus' : '' ?>" style="margin-left:<?= (int)$s['tiefe'] * 1.4 ?>rem">
      <div class="standort__zeile">
        <?php if (isset($titelbilder[(int)$s['id']])): ?>
          <a class="standort__icon" href="<?= e(url('standort_bild', ['id' => $titelbilder[(int)$s['id']]['id']])) ?>" target="_blank" rel="noopener" title="<?= e($typ['label']) ?> – Bild öffnen">
            <img src="<?= e(url('standort_bild', ['id' => $titelbilder[(int)$s['id']]['id'], 'vorschau' => 1])) ?>" alt="" loading="lazy" class="standort__thumb"></a>
        <?php else: ?>
          <span class="standort__icon" title="<?= e($typ['label']) ?>"><?= e($typ['icon']) ?></span>
        <?php endif; ?>
        <span class="standort__name"><a href="<?= e(url('admin_standort_edit', ['id' => $s['id']])) ?>"><?= e((string)$s['name']) ?></a>
          <?php if ($s['kurz'] !== ''): ?><span class="mono muted small"><?= e((string)$s['kurz']) ?></span><?php endif; ?>
          <span class="muted small"><?= e($typ['label']) ?></span>
          <?php if (($s['geo_lat'] ?? null) !== null): ?><a class="small" href="<?= e(dv_map_url((float)$s['geo_lat'], (float)$s['geo_lng'])) ?>" target="_blank" rel="noopener" title="auf der Karte">📍</a><?php endif; ?>
          <?php if ((int)$s['is_active'] !== 1): ?><span class="badge badge--muted">stillgelegt</span><?php endif; ?>
          <?php if ($s['kinder']): ?><span class="muted small">· <?= count($s['kinder']) ?> darunter</span><?php endif; ?></span>
        <span class="standort__knoepfe">
          <?php if ($kindtypen): ?>
            <a class="btn btn--sec btn--sm" href="<?= e(url('admin_standort_edit', ['parent_id' => $s['id']])) ?>" title="Unterplatz anlegen">+</a>
          <?php endif; ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="hoch"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn--sec btn--sm" type="submit" aria-label="nach oben"<?= $pos === 0 ? ' disabled' : '' ?>>▲</button></form>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="runter"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn--sec btn--sm" type="submit" aria-label="nach unten"<?= $pos === count($ids) - 1 ? ' disabled' : '' ?>>▼</button></form>
        </span>
      </div>
    </div>
    <?php
    foreach ($s['kinder'] as $k) {
        $zeile($k, $s['kinder']);
    }
};
?>
<div class="pagehead">
  <div>
    <h1>Stell- und Lagerplätze</h1>
    <p>Wo etwas steht oder liegt – als Baum: Gebäude mit Stockwerken und Räumen, Hallen mit Fahrzeugstellplätzen,
      Höfe mit Stellplätzen, Schränke mit Regalen. Oben stehen Gebäude, Hallen und Höfe; alles andere hängt darunter.</p>
  </div>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('admin_standort_edit')) ?>">+ Gebäude, Halle oder Hof</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Zur Verwaltung</a>
  </div>
</div>

<?php if ($alle): ?>
  <div class="chips" style="margin-bottom:1rem">
    <?php foreach (STANDORT_TYPEN as $key => $t): if (empty($zaehlung[$key])) { continue; } ?>
      <span class="chip"><?= e($t['icon']) ?> <?= (int)$zaehlung[$key] ?> <?= e((int)$zaehlung[$key] === 1 ? $t['label'] : $t['mehrzahl']) ?></span>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="card">
  <?php if (!$baum): ?>
    <div class="empty">Noch kein Platz angelegt. Beginne mit einem Gebäude, einer Halle oder einem Hof – darunter kommen
      Stockwerke, Räume und Stellplätze, gern als Serie („Raum" 1 bis 16).</div>
  <?php else: ?>
    <?php foreach ($baum as $s) { $zeile($s, $baum); } ?>
  <?php endif; ?>
</section>

<p class="small muted">Löschen und Stilllegen geht auf der Seite des Platzes. Ein Platz mit Unterplätzen lässt sich nicht löschen,
  wohl aber stilllegen – dann bleibt er sichtbar, ist aber nicht mehr auswählbar.</p>
