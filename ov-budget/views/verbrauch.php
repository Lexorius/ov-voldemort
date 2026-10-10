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
    <a class="btn btn--sec" href="<?= e(url('verbrauch_bericht', ['jahr' => $jahr])) ?>">Jahresbericht</a>
    <a class="btn btn--sec" href="<?= e(url('verbrauch_bereiche', ['jahr' => $jahr])) ?>">Kosten je Bereich</a>
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
  <?php if ($stats['solar']['vorhanden']): $so = $stats['solar']; ?>
    <div class="stat">
      <div class="stat__label">Solar <?= (int)$jahr ?></div>
      <div class="stat__value" style="color:#ca8a04"><?= e(menge($so['erzeugung'], '', 0)) ?> <span class="small muted">kWh erzeugt</span></div>
      <div class="stat__hint"><?= e(menge($so['einspeisung'], 'kWh', 0)) ?> eingespeist · <?= e(menge($so['eigenverbrauch'], 'kWh', 0)) ?> selbst genutzt
        · Autarkie <?= (int)$so['autarkie'] ?> %<?= $so['erloes'] > 0 ? ' · Erlös ' . e(money($so['erloes'])) : '' ?></div>
    </div>
  <?php endif; ?>
  <div class="stat">
    <div class="stat__label">Kosten <?= (int)$jahr ?></div>
    <div class="stat__value"><?= e(money($stats['kosten_jahr'], false)) ?></div>
    <div class="stat__hint"><?= $stats['ohne_tarif'] > 0
        ? '<span style="color:var(--warn)">' . (int)$stats['ohne_tarif'] . ' Zähler ohne Tarif</span>'
        : ($stats['erloes_jahr'] > 0 ? 'abzüglich Erlös ' . e(money($stats['erloes_jahr'])) . ' = ' . e(money($stats['kosten_jahr'] - $stats['erloes_jahr'])) : 'nach den hinterlegten Tarifen') ?></div>
  </div>
</div>

<?php if ($stats['alt'] > 0): ?>
  <div class="alert alert--warn"><strong><?= (int)$stats['alt'] ?> Zähler</strong> seit über
    <?= METER_WARN_TAGE ?> Tagen ohne Stand – bitte ablesen.</div>
<?php endif; ?>
<?php if (!empty($anomalien)): ?>
  <div class="alert alert--warn">
    <strong>Auffälliger Verbrauch</strong>
    <ul style="margin:.3rem 0 0 1.1rem">
      <?php foreach ($anomalien as $a): ?>
        <li><a href="<?= e(url('meter', ['id' => $a['meter']['id']])) ?>"><?= e((string)$a['meter']['name']) ?></a><?= e(substr(verbrauch_anomalie_text($a), strlen((string)$a['meter']['name']))) ?></li>
      <?php endforeach; ?>
    </ul>
    <div class="small muted" style="margin-top:.3rem">Verglichen wird die letzte Woche mit den Wochen davor; ein Übungswochenende kann das auslösen.</div>
  </div>
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
          <?php $rolle = (string)($m['rolle'] ?? 'bezug'); if ($rolle === 'unter'): ?>
            <span class="badge badge--outline">Unterzähler<?= !empty($m['parent_name']) ? ' von ' . e((string)$m['parent_name']) : '' ?></span>
          <?php elseif ($rolle === 'erzeugung'): ?><span class="badge" style="background:#ca8a04">Erzeugung</span>
          <?php elseif ($rolle === 'einspeisung'): ?><span class="badge" style="background:#ca8a04">Einspeisung</span><?php endif; ?>
          <?php if ((int)$m['is_active'] !== 1): ?><span class="badge badge--muted">stillgelegt</span><?php endif; ?>
          <?php if (!empty($m['bereich_name'])): ?><span class="muted small">· für <?= e((string)$m['bereich_name']) ?></span><?php endif; ?>
          <?php if (!empty($m['stellplatz_name'])): ?><span class="muted small">· in <?= e((string)$m['stellplatz_name']) ?></span><?php endif; ?>
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
          <div class="dl__value"><?= $k['tage30'] === null ? '<span class="muted">–</span>' : e(menge($k['tage30'], (string)$m['einheit'])) . ' <span class="muted small">· ' . e(menge($k['tage30'] / 30, (string)$m['einheit'], 2)) . ' je Tag</span>' ?></div></div>
        <div class="dl__item"><div class="dl__label"><?= (int)$jahr ?></div>
          <div class="dl__value"><?= $k['jahr'] === null ? '<span class="muted">–</span>' : e(menge($k['jahr'], (string)$m['einheit'])) ?>
            <?php if (isset($k['anteil']) && $k['anteil'] !== null): ?><span class="muted small">· <?= e(number_format($k['anteil'], 1, ',', '.')) ?> % des Hauptzählers</span><?php endif; ?>
            <?php if ($k['kosten'] > 0): ?><span class="muted small">· <?= (string)($m['rolle'] ?? '') === 'einspeisung' ? 'Erlös ' : '' ?><?= e(money($k['kosten'])) ?></span><?php endif; ?>
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
