<?php
/** @var array $kommend @var array $vergangen @var array $jahre
 *  @var ?int $jahr @var string $status @var string $q */

$karte = static function (array $e): string {
    $zeit = substr((string)$e['beginn'], 11, 5);
    $farbe = match ((string)$e['status']) {
        'abgesagt'      => '#b91c1c',
        'abgeschlossen' => '#15803d',
        'laeuft'        => '#0284c7',
        default         => '#94a3b8',
    };
    $html = '<a class="item' . ($e['status'] === 'abgesagt' ? ' item--done' : '') . '"'
        . ' href="' . e(url('event', ['id' => $e['id']])) . '" style="border-left-color:' . $farbe . '">'
        . '<div class="item__top"><div style="min-width:0">'
        . '<div class="item__title">' . e((string)$e['titel']) . '</div>'
        . '<div class="item__sub">' . e(de_date(substr((string)$e['beginn'], 0, 10)))
        . ($zeit !== '00:00' ? ', ' . e($zeit) . ' Uhr' : '')
        . ($e['ort'] ? ' · ' . e((string)$e['ort']) : '') . '</div>'
        . '</div><div class="item__amount">' . (int)$e['zusagen'] . '/' . (int)$e['gaeste'] . '</div></div>'
        . '<div class="item__meta">';
    $html .= '<span class="badge badge--outline">' . e(event_status_label((string)$e['status'])) . '</span>';
    if ((int)$e['gaeste'] > 0) {
        $html .= '<span class="badge badge--outline">' . (int)$e['zusagen'] . ' von '
            . (int)$e['gaeste'] . ' zugesagt</span>';
    }
    if ($e['budget_name']) {
        $html .= '<span class="badge badge--outline">' . e((string)$e['budget_name']) . '</span>';
    }
    if ((float)$e['kosten_geplant'] > 0) {
        $html .= '<span class="badge badge--outline">geplant ' . e(money((float)$e['kosten_geplant'])) . '</span>';
    }
    if ($e['fachgruppe_label']) {
        $html .= '<span class="badge badge--outline">' . e((string)$e['fachgruppe_label']) . '</span>';
    }
    return $html . '</div></a>';
};
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('veranstaltung_modul_name', 'Veranstaltungen')) ?></h1>
    <p><?= nl2br(e((string)setting('veranstaltung_intro', ''))) ?></p>
  </div>
  <div class="btnrow">
    <?php if (can('manage_events')): ?>
      <a class="btn" href="<?= e(url('event_edit')) ?>">+ Veranstaltung</a>
    <?php endif; ?>
  </div>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="events">
  <div class="filters">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Titel, Ort, Beschreibung">
    </div>
    <div class="field">
      <label for="jahr">Jahr</label>
      <select id="jahr" name="jahr">
        <option value="">alle</option>
        <?php foreach ($jahre as $j): ?>
          <option value="<?= (int)$j ?>" <?= (int)$j === (int)$jahr ? 'selected' : '' ?>><?= (int)$j ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="">alle</option>
        <?php foreach (EVENT_STATUS as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === $status ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button class="btn btn--sec" type="submit">Filtern</button>
    </div>
  </div>
</form>

<div class="card">
  <div class="card__head">
    <h2>Kommende Veranstaltungen</h2>
    <span class="muted small"><?= count($kommend) ?></span>
  </div>
  <?php if (!$kommend): ?>
    <div class="empty">Nichts geplant.</div>
  <?php else: ?>
    <div class="itemlist">
      <?php foreach ($kommend as $e): ?><?= $karte($e) ?><?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card__head">
    <h2>Vergangen</h2>
    <span class="muted small"><?= count($vergangen) ?></span>
  </div>
  <?php if (!$vergangen): ?>
    <div class="empty">Noch nichts gewesen.</div>
  <?php else: ?>
    <div class="itemlist">
      <?php foreach ($vergangen as $e): ?><?= $karte($e) ?><?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
