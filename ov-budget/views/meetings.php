<?php
/** @var array $kommend @var array $vergangen @var int $speicher @var ?int $typ @var array $serien */
$label = tp_label();

$karte = static function (array $m): string {
    $zeit = $m['beginn'] ? ', ' . substr((string)$m['beginn'], 0, 5) . ' Uhr' : '';
    $abgesagt = $m['status'] === 'abgesagt';
    $html = '<a class="item' . ($abgesagt ? ' item--done' : '') . '" href="' . e(url('meeting', ['id' => $m['id']])) . '" style="border-left-color:'
        . e($m['typ_color'] ?: '#94a3b8') . '">'
        . '<div class="item__top"><div style="min-width:0">'
        . '<div class="item__title">' . (meeting_treffen_text($m) !== '' ? MEETING_TREFFEN[meeting_treffen((string)$m['treffen'])]['icon'] . ' ' : '') . e($m['titel']) . '</div>'
        . '<div class="item__sub">' . e(meeting_datum_text($m) . $zeit) . ($m['ort'] ? ' · ' . e($m['ort']) : '') . '</div>'
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
    if ($abgesagt) {
        $html .= '<span class="badge" style="background:#b91c1c">abgesagt</span>';
    }
    if (!empty($m['series_id'])) {
        $html .= '<span class="badge badge--outline" title="Teil einer Serie">↻ Serie</span>';
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
      <a class="btn btn--sec" href="<?= e(url('meeting_series_edit')) ?>">+ Serie</a>
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

<?php if ($serien): ?>
  <section class="card">
    <h2>Wiederkehrend</h2>
    <div class="tablewrap">
      <table class="data">
        <tbody>
        <?php foreach ($serien as $s): ?>
          <tr>
            <td>
              <strong><?= e($s['titel']) ?></strong>
              <?php if (!(int)$s['is_active']): ?> <span class="badge badge--muted">pausiert</span><?php endif; ?>
              <div class="small muted"><?= e(series_describe($s)) ?><?= $s['ort'] ? ' · ' . e($s['ort']) : '' ?></div>
            </td>
            <td class="small nowrap">
              <?= $s['naechster'] ? 'nächster: ' . e(de_date($s['naechster'])) : '<span class="muted">kein Termin</span>' ?>
            </td>
            <td>
              <?php if (can('manage_meetings')): ?>
                <a class="btn btn--sec btn--sm" href="<?= e(url('meeting_series_edit', ['id' => $s['id']])) ?>">Bearbeiten</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php
$blattK ??= seiten_blaettern(count($kommend), 1, max(1, count($kommend)));
$blattV ??= seiten_blaettern(count($vergangen), 1, max(1, count($vergangen)));
$blattParams = array_filter(['typ_id' => $typ ?? 0, 'seite_k' => (int)$blattK['seite'] > 1 ? (int)$blattK['seite'] : 0, 'seite_v' => (int)$blattV['seite'] > 1 ? (int)$blattV['seite'] : 0]);
?>
<section class="card" id="anstehend">
  <h2>Anstehend<?php if ((int)$blattK['gesamt'] > count($kommend)): ?> <span class="muted small">(<?= (int)$blattK['gesamt'] ?>)</span><?php endif; ?></h2>
  <?php if (!$kommend): ?>
    <div class="empty">Keine Besprechung geplant.
      <?php if (can('manage_meetings')): ?><br><a href="<?= e(url('meeting_edit')) ?>">Besprechung anlegen</a><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="itemlist"><?php foreach ($kommend as $m): ?><?= $karte($m) ?><?php endforeach; ?></div>
    <?= render_partial('partials/seiten', ['blatt' => $blattK, 'route' => 'meetings', 'param' => 'seite_k', 'params' => array_diff_key($blattParams, ['seite_k' => 0]),
        'anker' => 'anstehend', 'zurueck' => '‹ Früher', 'weiter' => 'Später ›']) ?>
  <?php endif; ?>
</section>

<?php if ($vergangen): ?>
  <section class="card" id="zurueckliegend">
    <h2>Zurückliegend<?php if ((int)$blattV['gesamt'] > count($vergangen)): ?> <span class="muted small">(<?= (int)$blattV['gesamt'] ?>)</span><?php endif; ?></h2>
    <div class="itemlist"><?php foreach ($vergangen as $m): ?><?= $karte($m) ?><?php endforeach; ?></div>
    <?= render_partial('partials/seiten', ['blatt' => $blattV, 'route' => 'meetings', 'param' => 'seite_v', 'params' => array_diff_key($blattParams, ['seite_v' => 0]),
        'anker' => 'zurueckliegend']) ?>
  </section>
<?php endif; ?>
