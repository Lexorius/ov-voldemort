<?php
/** @var array $meters @var array $karten @var array $stats @var array $tarife
 *  @var int $jahr @var array $jahre @var array $filter */
$darf = can('manage_verbrauch');
$darfAblesen = can('read_meter');
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('verbrauch_modul_name', 'Verbrauch')) ?> <?= (int)$jahr ?></h1>
    <p><?= nl2br(e((string)setting('verbrauch_intro', ''))) ?></p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?>
      <a class="btn" href="<?= e(url('meter_edit')) ?>">+ Zähler</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('tarife')) ?>">Tarife</a>
    <a class="btn btn--sec" href="<?= e(url('verbrauch_export')) ?>">CSV</a>
  </div>
</div>

<div class="tabs">
  <?php foreach ($jahre as $j): ?>
    <a class="tab<?= $j === $jahr ? ' is-active' : '' ?>" href="<?= e(url('verbrauch', ['jahr' => $j])) ?>"><?= (int)$j ?></a>
  <?php endforeach; ?>
</div>

<div class="stats">
  <?php foreach (METER_ARTEN as $key => $a): $s = $stats['je_art'][$key]; ?>
    <div class="stat">
      <div class="stat__label"><?= e($a['label']) ?> <?= (int)$jahr ?></div>
      <div class="stat__value" style="color:<?= e($a['color']) ?>"><?= $s['zaehler'] > 0 ? e(menge($s['jahr'], '', 0)) : '–' ?>
        <span class="small muted"><?= $s['zaehler'] > 0 ? e($s['einheit']) : '' ?></span></div>
      <div class="stat__hint"><?= $s['zaehler'] > 0
          ? e(menge($s['tage30'], $s['einheit'], 0)) . ' in 30 Tagen' . ($s['kosten'] > 0 ? ' · ' . e(money($s['kosten'])) : '')
          : 'kein Zähler' ?></div>
    </div>
  <?php endforeach; ?>
  <div class="stat">
    <div class="stat__label">Kosten <?= (int)$jahr ?></div>
    <div class="stat__value"><?= e(money($stats['kosten_jahr'], false)) ?></div>
    <div class="stat__hint"><?= $stats['ohne_tarif'] > 0
        ? '<span style="color:var(--warn)">' . (int)$stats['ohne_tarif'] . ' Zähler ohne Tarif</span>'
        : 'nach den hinterlegten Tarifen' ?></div>
  </div>
</div>

<?php if ($stats['alt'] > 0): ?>
  <div class="alert alert--warn"><strong><?= (int)$stats['alt'] ?> Zähler</strong> seit über
    <?= METER_WARN_TAGE ?> Tagen ohne Stand – bitte ablesen.</div>
<?php endif; ?>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="verbrauch">
  <input type="hidden" name="jahr" value="<?= (int)$jahr ?>">
  <div class="filters">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e((string)$filter['q']) ?>" placeholder="Name, Nummer, Standort">
    </div>
    <div class="field">
      <label for="art">Art</label>
      <select id="art" name="art">
        <option value="">alle</option>
        <?php foreach (METER_ARTEN as $key => $a): ?>
          <option value="<?= e($key) ?>"<?= $filter['art'] === $key ? ' selected' : '' ?>><?= e($a['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="aktiv">Anzeigen</label>
      <select id="aktiv" name="aktiv">
        <option value="">in Betrieb</option>
        <option value="alle"<?= $filter['aktiv'] === 'alle' ? ' selected' : '' ?>>auch stillgelegte</option>
      </select>
    </div>
    <div class="field"><label>&nbsp;</label><button class="btn btn--sec" type="submit">Filtern</button></div>
  </div>
</form>

<?php if (!$meters): ?>
  <div class="card"><div class="empty">Noch kein Zähler angelegt.
    <?php if ($darf): ?><br><a href="<?= e(url('meter_edit')) ?>">Ersten Zähler anlegen</a><?php endif; ?>
  </div></div>
<?php else: ?>
  <div class="grid2">
  <?php foreach ($meters as $m): $k = $karten[(int)$m['id']]; $a = METER_ARTEN[(string)$m['art']]; ?>
    <section class="card" style="border-left:4px solid <?= e($a['color']) ?>">
      <div class="card__head">
        <h2><a href="<?= e(url('meter', ['id' => $m['id']])) ?>"><?= e((string)$m['name']) ?></a></h2>
        <span class="small">
          <?= badge(['label' => $a['label'], 'color' => $a['color']]) ?>
          <?php if ((int)$m['is_active'] !== 1): ?><span class="badge badge--muted">stillgelegt</span><?php endif; ?>
          <?php if ((string)$m['quelle'] === 'ha'): ?><span class="badge badge--outline">Home Assistant</span><?php endif; ?>
          <?php if (trim((string)$m['qr_token']) !== ''): ?><span class="badge badge--outline">QR</span><?php endif; ?>
        </span>
      </div>
      <?php if ($k['ha_fehler'] !== ''): ?>
        <div class="alert alert--error small">Home Assistant: <?= e($k['ha_fehler']) ?></div>
      <?php endif; ?>
      <dl class="dl">
        <div class="dl__item"><div class="dl__label">Letzter Stand</div>
          <div class="dl__value">
            <?php if ($m['letzter_stand'] === null): ?>
              <span class="muted">noch keiner</span>
            <?php else: ?>
              <strong><?= e(menge($m['letzter_stand'], (string)$m['einheit'], 3)) ?></strong>
              <div class="small<?= $k['alter']['stufe'] === 'alt' ? '' : ' muted' ?>"<?= $k['alter']['stufe'] === 'alt' ? ' style="color:var(--warn)"' : '' ?>>
                <?= e(de_datetime((string)$m['letzte_ablesung'])) ?>
                · <?= e(READING_QUELLEN[(string)$m['letzte_quelle']] ?? '') ?>
                <?= $k['alter']['tage'] !== null && $k['alter']['tage'] > 0 ? '· vor ' . (int)$k['alter']['tage'] . ' Tagen' : '' ?>
              </div>
            <?php endif; ?>
          </div></div>
        <div class="dl__item"><div class="dl__label">Letzte 30 Tage</div>
          <div class="dl__value"><?= $k['tage30'] === null ? '<span class="muted">–</span>' : e(menge($k['tage30'], (string)$m['einheit'])) ?></div></div>
        <div class="dl__item"><div class="dl__label"><?= (int)$jahr ?></div>
          <div class="dl__value"><?= $k['jahr'] === null ? '<span class="muted">–</span>' : e(menge($k['jahr'], (string)$m['einheit'])) ?>
            <?php if ($k['kosten'] > 0): ?><span class="muted small">· <?= e(money($k['kosten'])) ?></span><?php endif; ?>
            <?php if ($k['ohne_tarif'] > 0): ?><span class="small" style="color:var(--warn)">· Monate ohne Tarif</span><?php endif; ?>
          </div></div>
      </dl>
      <?php if ($darfAblesen && (int)$m['is_active'] === 1 && (string)$m['quelle'] !== 'ha'): ?>
        <details class="mt">
          <summary class="small">Stand eintragen …</summary>
          <form method="post" action="<?= e(url('verbrauch_action')) ?>" class="form mt">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="ablesen">
            <input type="hidden" name="meter_id" value="<?= (int)$m['id'] ?>">
            <input type="hidden" name="zurueck" value="uebersicht">
            <div class="grid3">
              <div class="field"><label for="stand-<?= (int)$m['id'] ?>">Stand (<?= e((string)$m['einheit']) ?>)</label>
                <input type="text" inputmode="decimal" id="stand-<?= (int)$m['id'] ?>" name="stand" required placeholder="<?= e(num_input($m['letzter_stand'])) ?>"></div>
              <div class="field"><label for="datum-<?= (int)$m['id'] ?>">Datum</label>
                <input type="date" id="datum-<?= (int)$m['id'] ?>" name="datum" value="<?= date('Y-m-d') ?>"></div>
              <div class="field"><label for="zeit-<?= (int)$m['id'] ?>">Uhrzeit</label>
                <input type="time" id="zeit-<?= (int)$m['id'] ?>" name="zeit" value="<?= date('H:i') ?>"></div>
            </div>
            <div class="btnrow"><button class="btn btn--sm" type="submit">Eintragen</button></div>
          </form>
        </details>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
