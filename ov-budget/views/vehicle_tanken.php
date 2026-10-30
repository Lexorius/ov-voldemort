<?php
/** @var array $vehicle @var array $a @var array $rows @var array $blatt @var bool $erfassen @var bool $verwalten @var ?array $user */
$punkte = array_slice($a['punkte'], -24);
$max = 0.0;
foreach ($punkte as $p) {
    $max = max($max, (float)$p['verbrauch']);
}
$letzteVoll = $a['punkte'] ? end($a['punkte']) : null;
$strom = $a['strom'] ?? ['anzahl' => 0, 'menge' => 0.0, 'betrag' => 0.0, 'betrag_fehlt' => 0, 'strecke' => 0, 'verbrauch' => null, 'punkte' => [], 'jahre' => [], 'einheit' => 'kWh'];
$stromPunkte = array_slice($strom['punkte'], -24);
$stromMax = 0.0;
foreach ($stromPunkte as $p) {
    $stromMax = max($stromMax, (float)$p['verbrauch']);
}
$stromLetzte = $strom['punkte'] ? end($strom['punkte']) : null;
$nurStrom = $strom['anzahl'] > 0 && $a['liter'] <= 0;
?>
<div class="pagehead">
  <div>
    <h1>Tanken und Verbrauch</h1>
    <p class="muted small"><?= e((string)$vehicle['bezeichnung']) ?><?= $vehicle['kennzeichen'] ? ' · ' . e((string)$vehicle['kennzeichen']) : '' ?><?= $vehicle['km_stand'] !== null ? ' · Stand ' . e(tank_km_text((int)$vehicle['km_stand'])) : '' ?></p>
  </div>
  <div class="btnrow">
    <?php if ($erfassen): ?><a class="btn" href="<?= e(url('vehicle_tank_edit', ['vehicle_id' => $vehicle['id']])) ?>">+ Tankung</a><?php endif; ?>
    <?php if ($a['anzahl'] > 0): ?><a class="btn btn--sec" href="<?= e(url('vehicle_tanken_export', ['id' => $vehicle['id']])) ?>">CSV</a><?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('vehicle', ['id' => $vehicle['id']])) ?>#tanken">Zur Fahrzeugakte</a>
  </div>
</div>

<?php if (!$nurStrom): ?>
<div class="stats">
  <div class="stat">
    <div class="stat__label">Verbrauch im Mittel</div>
    <div class="stat__value"><?= $a['verbrauch'] !== null ? e(tank_verbrauch_text($a['verbrauch'])) : '–' ?></div>
    <div class="stat__hint"><?= $a['strecke'] > 0 ? 'über ' . e(tank_km_text($a['strecke'])) . ' von Volltankung zu Volltankung' : 'braucht zwei Volltankungen mit Kilometerstand' ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Zuletzt</div>
    <div class="stat__value"><?= $letzteVoll ? e(tank_verbrauch_text((float)$letzteVoll['verbrauch'])) : '–' ?></div>
    <div class="stat__hint"><?= $letzteVoll ? 'Volltankung am ' . e(de_date($letzteVoll['datum'])) . ', ' . e(tank_km_text((int)$letzteVoll['strecke'])) : ($a['letzte'] ? 'letzte Tankung am ' . e(de_date((string)$a['letzte']['datum'])) : 'noch keine Tankung') ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Getankt</div>
    <div class="stat__value"><?= e(tank_liter_text($a['liter'], 0)) ?></div>
    <div class="stat__hint"><?= (int)$a['anzahl'] ?> Tankungen<?= $a['adblue_liter'] > 0 ? ', dazu ' . e(tank_liter_text($a['adblue_liter'], 0)) . ' AdBlue' : '' ?><?= $strom['anzahl'] > 0 ? ', ' . e(tank_liter_text($strom['menge'], 0, 'kWh')) . ' geladen' : '' ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Kosten, soweit bekannt</div>
    <div class="stat__value"><?= $a['betrag'] > 0 ? e(money($a['betrag'])) : '–' ?></div>
    <div class="stat__hint"><?= $a['betrag_fehlt'] > 0 ? (int)$a['betrag_fehlt'] . ' Tankungen ohne Betrag (Tankkarte)' : ($a['anzahl'] > 0 ? 'alle Tankungen mit Betrag' : '') ?></div>
  </div>
</div>
<?php endif; ?>

<?php if ($strom['anzahl'] > 0): ?>
<div class="stats">
  <div class="stat">
    <div class="stat__label">Strom im Mittel</div>
    <div class="stat__value"><?= $strom['verbrauch'] !== null ? e(tank_verbrauch_text($strom['verbrauch'], 'kWh')) : '–' ?></div>
    <div class="stat__hint"><?= $strom['strecke'] > 0 ? 'über ' . e(tank_km_text($strom['strecke'])) . ' von Vollladung zu Vollladung' : 'braucht zwei Vollladungen mit Kilometerstand' ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Zuletzt geladen</div>
    <div class="stat__value"><?= $stromLetzte ? e(tank_verbrauch_text((float)$stromLetzte['verbrauch'], 'kWh')) : '–' ?></div>
    <div class="stat__hint"><?= $stromLetzte ? 'Vollladung am ' . e(de_date($stromLetzte['datum'])) . ', ' . e(tank_km_text((int)$stromLetzte['strecke'])) : 'noch keine zweite Vollladung' ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Geladen</div>
    <div class="stat__value"><?= e(tank_liter_text($strom['menge'], 0, 'kWh')) ?></div>
    <div class="stat__hint"><?= (int)$strom['anzahl'] ?> Ladungen</div>
  </div>
  <?php if ($nurStrom): ?>
  <div class="stat">
    <div class="stat__label">Kosten, soweit bekannt</div>
    <div class="stat__value"><?= $a['betrag'] > 0 ? e(money($a['betrag'])) : '–' ?></div>
    <div class="stat__hint"><?= $a['betrag_fehlt'] > 0 ? (int)$a['betrag_fehlt'] . ' Ladungen ohne Betrag (Ladekarte)' : 'alle Ladungen mit Betrag' ?></div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($punkte): ?>
<section class="card" id="verlauf">
  <div class="card__head">
    <h2>Verlauf</h2>
    <span class="small muted">je Volltankung, <?= count($punkte) < count($a['punkte']) ? 'die letzten ' . count($punkte) : 'alle' ?> · Mittel <?= e(tank_verbrauch_text($a['verbrauch'])) ?></span>
  </div>
  <div class="months months--tank">
    <?php foreach ($punkte as $p): ?>
      <div class="months__col">
        <div class="months__pair">
          <div class="months__bar<?= $p['auffaellig'] ? ' months__bar--hoch' : '' ?>" style="height:<?= $max > 0 ? number_format((float)$p['verbrauch'] / $max * 100, 1, '.', '') : 0 ?>%"
               title="<?= e(de_date($p['datum']) . ': ' . tank_verbrauch_text((float)$p['verbrauch']) . ' auf ' . tank_km_text((int)$p['strecke'])) ?>"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="small muted" style="margin:.4rem 0 0">Von <?= e(de_date($punkte[0]['datum'])) ?> bis <?= e(de_date(end($punkte)['datum'])) ?>.
    <?php if (array_filter($punkte, static fn($p) => $p['auffaellig'])): ?>Orange: mehr als <?= TANK_AUFFAELLIG_PROZENT ?> % neben dem Mittel – Teiltankung vergessen, Stand vertippt oder wirklich mehr gebraucht?<?php endif; ?></p>
</section>
<?php endif; ?>

<?php if ($stromPunkte): ?>
<section class="card" id="verlauf-strom">
  <div class="card__head">
    <h2>Verlauf Strom</h2>
    <span class="small muted">je Vollladung, <?= count($stromPunkte) < count($strom['punkte']) ? 'die letzten ' . count($stromPunkte) : 'alle' ?> · Mittel <?= e(tank_verbrauch_text($strom['verbrauch'], 'kWh')) ?></span>
  </div>
  <div class="months months--tank months--strom">
    <?php foreach ($stromPunkte as $p): ?>
      <div class="months__col">
        <div class="months__pair">
          <div class="months__bar<?= $p['auffaellig'] ? ' months__bar--hoch' : '' ?>" style="height:<?= $stromMax > 0 ? number_format((float)$p['verbrauch'] / $stromMax * 100, 1, '.', '') : 0 ?>%"
               title="<?= e(de_date($p['datum']) . ': ' . tank_verbrauch_text((float)$p['verbrauch'], 'kWh') . ' auf ' . tank_km_text((int)$p['strecke'])) ?>"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="small muted" style="margin:.4rem 0 0">Von <?= e(de_date($stromPunkte[0]['datum'])) ?> bis <?= e(de_date(end($stromPunkte)['datum'])) ?>.</p>
</section>
<?php endif; ?>

<?php if (count($a['jahre']) > 1 || count($strom['jahre']) > 1): ?>
<section class="card" id="jahre">
  <div class="card__head"><h2>Je Jahr</h2></div>
  <div class="tablewrap">
    <table class="data">
      <thead><tr><th>Jahr</th><th class="num">Tankungen</th><th class="num">Menge</th><th class="num">Strecke</th><th class="num">Verbrauch</th><th class="num">Kosten</th></tr></thead>
      <tbody>
      <?php foreach ($a['jahre'] as $jahr => $j): ?>
        <tr>
          <td><strong><?= e((string)$jahr) ?></strong></td>
          <td class="num"><?= (int)$j['anzahl'] ?></td>
          <td class="num"><?= e(tank_liter_text($j['liter'], 0)) ?></td>
          <td class="num"><?= $j['strecke'] > 0 ? e(tank_km_text((int)$j['strecke'])) : '–' ?></td>
          <td class="num"><?= $j['verbrauch'] !== null ? e(tank_verbrauch_text($j['verbrauch'])) : '–' ?></td>
          <td class="num"><?= $j['betrag'] > 0 ? e(money($j['betrag'])) : '–' ?><?= $j['betrag_fehlt'] > 0 ? '<div class="small muted">' . (int)$j['betrag_fehlt'] . ' ohne Betrag</div>' : '' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php foreach ($strom['jahre'] as $jahr => $j): ?>
        <tr>
          <td><strong><?= e((string)$jahr) ?></strong> <span class="badge" style="background:#0369a1">⚡ Strom</span></td>
          <td class="num"><?= (int)$j['anzahl'] ?></td>
          <td class="num"><?= e(tank_liter_text($j['menge'], 0, 'kWh')) ?></td>
          <td class="num"><?= $j['strecke'] > 0 ? e(tank_km_text((int)$j['strecke'])) : '–' ?></td>
          <td class="num"><?= $j['verbrauch'] !== null ? e(tank_verbrauch_text($j['verbrauch'], 'kWh')) : '–' ?></td>
          <td class="num"><?= $j['betrag'] > 0 ? e(money($j['betrag'])) : '–' ?><?= $j['betrag_fehlt'] > 0 ? '<div class="small muted">' . (int)$j['betrag_fehlt'] . ' ohne Betrag</div>' : '' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<section class="card" id="tankungen">
  <div class="card__head">
    <h2>Tankungen</h2>
    <span class="small muted">neueste zuerst</span>
  </div>
  <?php if (!$rows): ?>
    <div class="empty">Noch keine Tankung. Die erste Quittung mit Kilometerstand und Litern eintragen – ab der zweiten Volltankung
      steht hier der Verbrauch.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Datum</th><th class="num">Stand</th><th class="num">Menge</th><th class="num">Verbrauch</th><th class="num">Betrag</th><th>Karte · Tankstelle · Beleg</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $t): ?>
          <tr id="t<?= (int)$t['id'] ?>">
            <td class="nowrap small"><?= e(de_date((string)$t['datum'])) ?></td>
            <td class="num nowrap"><?= $t['km_stand'] !== null ? e(tank_km_text((int)$t['km_stand'])) : '<span class="muted">–</span>' ?></td>
            <td class="num nowrap"><?= e(tank_menge_text($t)) ?>
              <?php if ((string)$t['art'] === 'adblue'): ?><span class="badge badge--outline">AdBlue</span><?php elseif ((string)$t['art'] === 'strom'): ?><span class="badge" style="background:#0369a1" title="Laden">⚡</span><?php endif; ?>
              <?php if ((int)$t['voll'] !== 1 && (string)$t['art'] !== 'adblue'): ?><span class="badge badge--outline" title="Nicht voll – zählt zur Strecke bis zur nächsten Volltankung">Teil</span><?php endif; ?></td>
            <td class="num nowrap"><?= $t['verbrauch'] !== null ? e(tank_verbrauch_text($t['verbrauch'], (string)$t['einheit'])) : '<span class="muted">–</span>' ?>
              <?php if ($t['auffaellig']): ?> <span class="badge badge--warn" title="weicht mehr als <?= TANK_AUFFAELLIG_PROZENT ?> % vom Mittel ab">auffällig</span><?php endif; ?>
              <?php if ($t['strecke'] !== null): ?><div class="small muted"><?= e(tank_km_text((int)$t['strecke'])) ?></div><?php endif; ?></td>
            <td class="num nowrap"><?= $t['betrag'] !== null ? e(money((float)$t['betrag'])) : '<span class="muted" title="ohne Betrag, etwa Tankkarte">–</span>' ?>
              <?php if ($t['preis_je_liter'] !== null): ?><div class="small muted"><?= e(number_format($t['preis_je_liter'], 3, ',', '.')) ?> €/<?= e((string)$t['einheit']) ?></div><?php endif; ?></td>
            <td class="small">
              <?php $karte = tank_karte_text($t); ?>
              <?php if ($karte !== ''): ?><span class="badge badge--outline" title="Tankkarte"><?= e($karte) ?></span><?php endif; ?>
              <?= e((string)$t['tankstelle']) ?><?= (string)$t['beleg_nr'] !== '' ? ($t['tankstelle'] !== '' ? ' · ' : '') . '<span class="mono">' . e((string)$t['beleg_nr']) . '</span>' : '' ?>
              <?php if ((int)($t['belege'] ?? 0) > 0): ?><span class="muted" title="Belege">📎 <?= (int)$t['belege'] ?></span><?php endif; ?>
              <?php if ((string)$t['notiz'] !== ''): ?><div class="muted"><?= e((string)$t['notiz']) ?></div><?php endif; ?>
              <div class="muted"><?= e((string)($t['erfasst_von'] ?? '')) ?></div></td>
            <td class="nowrap"><?php if (tank_darf_aendern($t, $user)): ?><a class="btn btn--sm btn--sec" href="<?= e(url('vehicle_tank_edit', ['id' => $t['id']])) ?>">Ändern</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= render_partial('partials/seiten', ['blatt' => $blatt, 'route' => 'vehicle_tanken', 'params' => ['id' => $vehicle['id']], 'anker' => 'tankungen']) ?>
  <?php endif; ?>
</section>
