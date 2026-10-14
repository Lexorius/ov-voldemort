<?php
/**
 * Mehrere Einträge wählen: Dropdown + „Hinzufügen", die gewählten als Liste
 * mit Mülleimer. Ohne JavaScript: wählen und speichern fügt den Eintrag hinzu.
 *
 * @var string $name      Feldname, etwa „standorte" oder „vehicles" ([] für die gewählten, _neu für das Dropdown, _feld als Marke)
 * @var string $feld      Name der Marke, die sagt: das Feld war im Formular (standorte_feld, fahrzeuge_feld)
 * @var string $label
 * @var array  $optionen  [[id, text, sub, gruppe, warn]] – warn: Text, wenn der Eintrag schon anderswo belegt ist
 * @var array  $gewaehlt  ids
 * @var string $leer      erster Eintrag des Dropdowns
 * @var string $hinweis
 */
$optionen ??= [];
$gewaehlt ??= [];
$leer ??= '– bitte wählen –';
$hinweis ??= '';
$feld ??= $name . '_feld';
if (!$optionen) {
    return;
}
$nachId = array_column($optionen, null, 'id');
$gruppen = [];
foreach ($optionen as $o) {
    $gruppen[(string)($o['gruppe'] ?? '')][] = $o;
}
$zeile = static function (array $o) use ($name): string {
    $warn = trim((string)($o['warn'] ?? ''));
    return '<li class="wahl__eintrag' . ($warn !== '' ? ' wahl__eintrag--warn' : '') . '" data-id="' . (int)$o['id'] . '">'
        . '<input type="hidden" name="' . e($name) . '[]" value="' . (int)$o['id'] . '">'
        . '<span class="wahl__text">' . e((string)$o['text']) . (($o['sub'] ?? '') !== '' ? ' <span class="muted small">' . e((string)$o['sub']) . '</span>' : '')
        . ($warn !== '' ? ' <span class="small wahl__warn">⚠ ' . e($warn) . '</span>' : '') . '</span>'
        . '<button type="button" class="wahl__weg" title="Eintrag entfernen" aria-label="' . e((string)$o['text']) . ' entfernen">🗑</button></li>';
};
?>
<div class="field wahl" data-wahl="<?= e($name) ?>">
  <label for="<?= e($name) ?>_neu"><?= e($label) ?></label>
  <input type="hidden" name="<?= e($feld) ?>" value="1">
  <ul class="wahl__liste" data-wahl-liste>
    <?php foreach ($gewaehlt as $gid): if (isset($nachId[(int)$gid])): ?><?= $zeile($nachId[(int)$gid]) ?><?php endif; endforeach; ?>
  </ul>
  <div class="wahl__zeile">
    <select id="<?= e($name) ?>_neu" name="<?= e($name) ?>_neu" data-wahl-select>
      <option value=""><?= e($leer) ?></option>
      <?php foreach ($gruppen as $gruppe => $liste): ?>
        <?php if ($gruppe !== ''): ?><optgroup label="<?= e($gruppe) ?>"><?php endif; ?>
        <?php foreach ($liste as $o): $drin = in_array((int)$o['id'], array_map('intval', $gewaehlt), true); $warn = trim((string)($o['warn'] ?? '')); ?>
          <option value="<?= (int)$o['id'] ?>" data-text="<?= e((string)$o['text']) ?>" data-sub="<?= e((string)($o['sub'] ?? '')) ?>" data-warn="<?= e($warn) ?>"<?= $drin ? ' disabled' : '' ?>><?= e((string)$o['text']) ?><?= ($o['sub'] ?? '') !== '' ? ' (' . e((string)$o['sub']) . ')' : '' ?><?= $warn !== '' ? ' ⚠ ' . e($warn) : '' ?></option>
        <?php endforeach; ?>
        <?php if ($gruppe !== ''): ?></optgroup><?php endif; ?>
      <?php endforeach; ?>
    </select>
    <button type="button" class="btn btn--sec" data-wahl-add title="zur Liste hinzufügen">+ Hinzufügen</button>
  </div>
  <?php if ($hinweis !== ''): ?><small><?= e($hinweis) ?></small><?php endif; ?>
</div>
