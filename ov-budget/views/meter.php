<?php
/** @var array $meter @var int $jahr @var array $jahre @var array $staende @var array $monate
 *  @var array $kosten @var array $abschnitte @var ?array $tarifHeute @var array $alter
 *  @var string $haFehler @var ?array $connector @var array $zaehlerConnectoren */
$darf = can('manage_verbrauch');
$darfAblesen = can('read_meter');
$id = (int)$meter['id'];
$a = METER_ARTEN[(string)$meter['art']];
$einheit = (string)$meter['einheit'];
$maxMonat = max(array_merge([0.0], array_map(static fn($v) => (float)($v ?? 0), $monate)));
$summeJahr = array_sum(array_map(static fn($v) => (float)($v ?? 0), $monate));
$monatsnamen = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)$meter['name']) ?></h1>
    <p>
      <?= badge(['label' => $a['label'], 'color' => $a['color']]) ?>
      <?php if ((int)$meter['is_active'] !== 1): ?><span class="badge badge--muted">stillgelegt</span><?php endif; ?>
      <?= trim((string)$meter['zaehlernummer']) !== '' ? ' · Nr. ' . e((string)$meter['zaehlernummer']) : '' ?>
      <?= trim((string)$meter['standort']) !== '' ? ' · ' . e((string)$meter['standort']) : '' ?>
      · Quelle: <?= e(METER_QUELLEN[(string)$meter['quelle']] ?? '') ?>
      <?= (string)$meter['quelle'] === 'ha' ? '<span class="mono small">(' . e((string)$meter['ha_entity']) . ')</span>' : '' ?>
    </p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?>
      <a class="btn" href="<?= e(url('meter_edit', ['id' => $id])) ?>">Bearbeiten</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('verbrauch_export', ['id' => $id])) ?>">CSV</a>
    <a class="btn btn--sec" href="<?= e(url('verbrauch')) ?>">Alle Zähler</a>
  </div>
</div>

<?php if ($haFehler !== ''): ?>
  <div class="alert alert--error">Home Assistant: <?= e($haFehler) ?></div>
<?php endif; ?>
<?php if ($alter['stufe'] === 'alt'): ?>
  <div class="alert alert--warn">Der letzte Stand ist <?= (int)$alter['tage'] ?> Tage alt.</div>
<?php endif; ?>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Letzter Stand</div>
    <div class="stat__value"><?= $meter['letzter_stand'] === null ? '–' : e(menge($meter['letzter_stand'], '', 3)) ?>
      <span class="small muted"><?= e($einheit) ?></span></div>
    <div class="stat__hint"><?= $meter['letzte_ablesung'] ? e(de_datetime((string)$meter['letzte_ablesung'])) : 'noch keiner' ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Verbrauch <?= (int)$jahr ?></div>
    <div class="stat__value"><?= e(menge($summeJahr, '', 0)) ?> <span class="small muted"><?= e($einheit) ?></span></div>
    <div class="stat__hint"><?= (int)$meter['ablesungen'] ?> Ablesungen gesamt</div>
  </div>
  <div class="stat">
    <div class="stat__label">Kosten <?= (int)$jahr ?></div>
    <div class="stat__value"><?= e(money($kosten['gesamt'], false)) ?></div>
    <div class="stat__hint"><?= $kosten['ohne_tarif'] > 0
        ? '<span style="color:var(--warn)">' . (int)$kosten['ohne_tarif'] . ' Monat(e) ohne Tarif</span>'
        : ($tarifHeute ? 'Tarif: ' . e((string)$tarifHeute['name']) : '<a href="' . e(url('tarif_edit', ['art' => $meter['art']])) . '">Tarif anlegen</a>') ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Tarif heute</div>
    <div class="stat__value" style="font-size:1.1rem"><?= $tarifHeute
        ? e(number_format((float)$tarifHeute['arbeitspreis'], 4, ',', '.')) . ' €/' . e((string)$tarifHeute['einheit'])
        : '–' ?></div>
    <div class="stat__hint"><?= $tarifHeute && (float)$tarifHeute['grundpreis_monat'] > 0
        ? '+ ' . e(money((float)$tarifHeute['grundpreis_monat'])) . ' je Monat' : '' ?>
      <?= (float)$meter['umrechnung'] !== 1.0 ? '· Umrechnung × ' . e(num_input($meter['umrechnung'], true)) : '' ?></div>
  </div>
</div>

<div class="grid2">
  <?php if ($darfAblesen && (int)$meter['is_active'] === 1): ?>
    <section class="card" id="ablesen">
      <h2>Stand eintragen</h2>
      <?php if ((string)$meter['quelle'] === 'ha'): ?>
        <p class="small muted">Dieser Zähler wird aus Home Assistant gelesen – alle
          <?= (int)setting_int('verbrauch_ha_intervall_stunden', 24) ?> Stunden mit dem Abruf.</p>
        <?php if ($darf): ?>
          <form method="post" action="<?= e(url('verbrauch_action')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="ha_lesen">
            <input type="hidden" name="meter_id" value="<?= $id ?>">
            <button class="btn btn--sec" type="submit">Jetzt aus Home Assistant lesen</button>
          </form>
        <?php endif; ?>
        <details class="mt"><summary class="small">Trotzdem von Hand eintragen …</summary>
      <?php endif; ?>
      <form method="post" action="<?= e(url('verbrauch_action')) ?>" class="form mt">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="ablesen">
        <input type="hidden" name="meter_id" value="<?= $id ?>">
        <div class="field">
          <label for="stand">Zählerstand (<?= e($einheit) ?>)</label>
          <input type="text" inputmode="decimal" id="stand" name="stand" required placeholder="<?= e(num_input($meter['letzter_stand'])) ?>">
          <?php if ($meter['letzter_stand'] !== null): ?>
            <small>Zuletzt <?= e(menge($meter['letzter_stand'], $einheit, 3)) ?> am <?= e(de_datetime((string)$meter['letzte_ablesung'])) ?>.</small>
          <?php endif; ?>
        </div>
        <div class="grid2">
          <div class="field"><label for="datum">Datum</label>
            <input type="date" id="datum" name="datum" value="<?= date('Y-m-d') ?>"></div>
          <div class="field"><label for="zeit">Uhrzeit</label>
            <input type="time" id="zeit" name="zeit" value="<?= date('H:i') ?>"></div>
        </div>
        <div class="field"><label for="notiz">Notiz</label>
          <input type="text" id="notiz" name="notiz" maxlength="255" placeholder="z. B. Zähler getauscht"></div>
        <div class="field field--check">
          <input type="checkbox" id="ruecklauf" name="ruecklauf" value="1">
          <label for="ruecklauf">Rücklauf zulassen (neuer Zähler beginnt niedriger)</label>
        </div>
        <div class="btnrow"><button class="btn" type="submit">Eintragen</button></div>
      </form>
      <?php if ((string)$meter['quelle'] === 'ha'): ?></details><?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="card">
    <div class="card__head">
      <h2>Verbrauch je Monat</h2>
      <div class="tabs" style="margin:0">
        <?php foreach ($jahre as $j): ?>
          <a class="tab<?= $j === $jahr ? ' is-active' : '' ?>" href="<?= e(url('meter', ['id' => $id, 'jahr' => $j])) ?>"><?= (int)$j ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($maxMonat <= 0): ?>
      <div class="empty">Für <?= (int)$jahr ?> noch kein Verbrauch – dafür braucht es zwei Stände.</div>
    <?php else: ?>
      <div class="months">
        <?php foreach ($monate as $m => $v): $h = $v === null ? 0 : max(1, (float)$v / $maxMonat * 100); ?>
          <div class="months__col">
            <div class="months__pair">
              <div class="months__bar" style="height:<?= number_format($h, 1, '.', '') ?>%;background:<?= e($a['color']) ?>"
                   title="<?= e($monatsnamen[$m] . ': ' . ($v === null ? 'keine Daten' : menge($v, $einheit))
                       . ($kosten['monate'][$m] !== null ? ' · ' . money($kosten['monate'][$m]) : '')) ?>"></div>
            </div>
            <div class="months__label"><?= e($monatsnamen[$m]) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="small muted" style="margin:.6rem 0 0">Höchster Monat: <?= e(menge($maxMonat, $einheit)) ?>.
        Zwischen zwei Ablesungen wird der Verbrauch gleichmäßig auf die Tage verteilt.</p>
    <?php endif; ?>
  </section>
</div>

<?php if ($abschnitte): ?>
<section class="card">
  <h2>Abschnitte</h2>
  <div class="tablewrap">
    <table class="data">
      <thead><tr><th>Von</th><th>Bis</th><th class="num">Tage</th><th class="num">Verbrauch</th><th class="num">je Tag</th></tr></thead>
      <tbody>
      <?php foreach ($abschnitte as $ab): ?>
        <tr>
          <td class="small nowrap"><?= e(de_date(substr($ab['von'], 0, 10))) ?></td>
          <td class="small nowrap"><?= e(de_date(substr($ab['bis'], 0, 10))) ?></td>
          <td class="num small"><?= e(number_format($ab['tage'], 1, ',', '.')) ?></td>
          <td class="num"><?= $ab['menge'] === null ? '<span class="muted">Zählerwechsel</span>' : e(menge($ab['menge'], $einheit)) ?></td>
          <td class="num small"><?= $ab['je_tag'] === null ? '–' : e(menge($ab['je_tag'], $einheit, 2)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<section class="card">
  <div class="card__head">
    <h2>Zählerstände</h2>
    <span class="muted small">die letzten <?= count($staende) ?></span>
  </div>
  <?php if (!$staende): ?>
    <div class="empty">Noch kein Stand eingetragen.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Zeitpunkt</th><th class="num">Stand</th><th>Quelle</th><th>Von</th><th>Notiz</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($staende as $r): ?>
          <tr>
            <td class="nowrap small"><?= e(de_datetime((string)$r['gelesen_am'])) ?></td>
            <td class="num"><strong><?= e(menge($r['stand'], '', 3)) ?></strong> <span class="small muted"><?= e($einheit) ?></span></td>
            <td class="small"><?= badge(['label' => READING_QUELLEN[(string)$r['quelle']] ?? (string)$r['quelle'],
                'color' => (string)$r['quelle'] === 'ha' ? '#0369a1' : ((string)$r['quelle'] === 'qr' ? '#7c3aed' : '#64748b')]) ?></td>
            <td class="small"><?= e((string)($r['melder'] ?: ($r['erfasser'] ?? '–'))) ?></td>
            <td class="small"><?= e((string)$r['notiz']) ?></td>
            <td class="nowrap">
              <?php if ($darf): ?>
                <form method="post" action="<?= e(url('verbrauch_action')) ?>" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="stand_weg">
                  <input type="hidden" name="meter_id" value="<?= $id ?>">
                  <input type="hidden" name="reading_id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn--sec btn--sm" type="submit" data-confirm="Diesen Stand entfernen?">Entfernen</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?= render_partial('partials/zaehler_qr', [
    'meter' => $meter, 'connector' => $connector, 'zaehlerConnectoren' => $zaehlerConnectoren,
]) ?>
