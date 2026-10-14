<?php
/**
 * Mehrere Stell- und Lagerplätze anhaken – für Termine und Veranstaltungen.
 * @var array  $standorte  alle Plätze (standort_all)
 * @var array  $gewaehlt   ids, die schon dran hängen
 * @var string $hinweis    Satz unter der Auswahl
 */
$standorte ??= [];
$gewaehlt ??= [];
$hinweis ??= '';
$aktive = array_values(array_filter($standorte, static fn($x) => (int)$x['is_active'] === 1 || in_array((int)$x['id'], $gewaehlt, true)));
if (!$aktive || !function_exists('standort_flach')) {
    return;
}
?>
<div class="field">
  <label>Standorte <span class="muted small">(mehrere möglich)</span></label>
  <input type="hidden" name="standorte_feld" value="1">
  <div class="chips">
    <?php foreach (standort_flach(standort_baum($aktive)) as $s): $an = in_array((int)$s['id'], $gewaehlt, true); $eltern = standort_pfad((int)($s['parent_id'] ?? 0), $standorte); ?>
      <label class="chip<?= $an ? ' is-on' : '' ?><?= (int)$s['is_active'] !== 1 ? ' is-muted' : '' ?>" title="<?= e($eltern !== '' ? $eltern . ' › ' . $s['name'] : (string)$s['name']) ?>">
        <input type="checkbox" name="standorte[]" value="<?= (int)$s['id'] ?>"<?= $an ? ' checked' : '' ?>>
        <?= e((string)$s['name']) ?><?= $eltern !== '' ? ' <span class="muted small">' . e($eltern) . '</span>' : '' ?></label>
    <?php endforeach; ?>
  </div>
  <?php if ($hinweis !== ''): ?><small><?= e($hinweis) ?></small><?php endif; ?>
</div>
