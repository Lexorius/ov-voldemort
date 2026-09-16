<?php
/** @var array $kommend @var array $vergangen @var int $speicher @var ?int $typ */
$label = tp_label();

$karte = static function (array $m): string {
    $zeit = $m['beginn'] ? ', ' . substr((string)$m['beginn'], 0, 5) . ' Uhr' : '';
    $html = '<a class="item" href="' . e(url('meeting', ['id' => $m['id']])) . '" style="border-left-color:'
        . e($m['typ_color'] ?: '#94a3b8') . '">'
        . '<div class="item__top"><div style="min-width:0">'
        . '<div class="item__title">' . e($m['titel']) . '</div>'
        . '<div class="item__sub">' . e(de_date($m['datum']) . $zeit) . ($m['ort'] ? ' · ' . e($m['ort']) : '') . '</div>'
        . '</div><div class="item__amount">' . (int)$m['punkte'] . '</div></div>'
        . '<div class="item__meta">';
    if ($m['typ_label']) {
        $html .= badge(['label' => $m['typ_label'], 'color' => $m['typ_color']]);
    }
    $html .= '<span class="badge badge--outline">' . (int)$m['punkte'] . ' Themen</span>';
    if ($m['status'] === 'geplant' && (int)$m['offen'] > 0) {
        $html .= '<span class="badge" style="background:#0284c7">' . (int)$m['offen'] . ' offen</span>';
    }
    if ($m['status'] === 'abgeschlossen') {
        $html .= '<span class="badge" style="background:#15803d">abgeschlossen</span>';
    }
    return $html . '</div></a>';
};
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('besprechung_modul_name', 'Besprechungen')) ?></h1>
    <p><?= nl2br(e((string)setting('besprechung_intro', ''))) ?></p>
  </div>
  <div class="btnrow">
    <?php if (can('manage_meetings')): ?>
      <a class="btn" href="<?= e(url('meeting_edit')) ?>">+ Besprechung</a>
    <?php endif; ?>
    <?php if (can('create_talking_point')): ?>
      <a class="btn btn--sec" href="<?= e(url('talking_point_edit')) ?>">+ <?= e($label) ?></a>
    <?php endif; ?>
  </div>
</div>

<a class="card" href="<?= e(url('talking_points')) ?>" style="display:block;text-decoration:none;color:inherit">
  <div class="card__head" style="margin:0">
    <div>
      <h2 style="margin:0">Themenspeicher</h2>
      <div class="muted small">Eingebrachte und vertagte <?= e($label) ?>, die noch auf eine Besprechung warten</div>
    </div>
    <span class="stat__value"><?= (int)$speicher ?></span>
  </div>
</a>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="meetings">
  <div class="field">
    <label for="typ_id">Art der Besprechung</label>
    <select id="typ_id" name="typ_id"><?= list_options('besprechung_typ', $typ, 'alle') ?></select>
  </div>
</form>

<section class="card">
  <h2>Anstehend</h2>
  <?php if (!$kommend): ?>
    <div class="empty">Keine Besprechung geplant.
      <?php if (can('manage_meetings')): ?><br><a href="<?= e(url('meeting_edit')) ?>">Besprechung anlegen</a><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="itemlist"><?php foreach ($kommend as $m): ?><?= $karte($m) ?><?php endforeach; ?></div>
  <?php endif; ?>
</section>

<?php if ($vergangen): ?>
  <section class="card">
    <h2>Zurückliegend</h2>
    <div class="itemlist"><?php foreach ($vergangen as $m): ?><?= $karte($m) ?><?php endforeach; ?></div>
  </section>
<?php endif; ?>
