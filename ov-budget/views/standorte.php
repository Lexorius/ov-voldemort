<?php
/**
 * @var ?array $s      der angeklickte Platz (null = ganz oben)
 * @var array $alle $crumbs $kinder $summen $hier $darunter $zustaendig $titelbilder $zaehlung $kinderZahl
 * @var bool $darfPflegen
 */
$titelbilder ??= []; $hier ??= []; $darunter ??= []; $zustaendig ??= []; $kinderZahl ??= [];
$typVon = static fn(array $p): array => STANDORT_TYPEN[standort_typ((string)$p['typ'])];
$hierText = standort_inventar_text(['fahrzeuge' => count($hier['fahrzeuge'] ?? []), 'zaehler' => count($hier['zaehler'] ?? []) + count($zustaendig), 'funkgruppen' => count($hier['funkgruppen'] ?? []), 'funk' => count($hier['funk'] ?? [])]);
$darunterText = $s ? standort_inventar_text($darunter) : '';
?>
<nav class="small muted" aria-label="Pfad" style="margin-bottom:.4rem">
  <a href="<?= e(url('standorte')) ?>"><?= e((string)setting('standort_modul_name', 'Standorte')) ?></a>
  <?php foreach ($crumbs as $c): ?> › <a href="<?= e(url('standorte', ['id' => $c['id']])) ?>"><?= e((string)$c['name']) ?></a><?php endforeach; ?>
  <?php if ($s): ?> › <strong><?= e((string)$s['name']) ?></strong><?php endif; ?>
</nav>

<div class="pagehead">
  <div>
    <?php if ($s): $typ = $typVon($s); ?>
      <h1><?= e($typ['icon']) ?> <?= e((string)$s['name']) ?>
        <?php if ($s['kurz'] !== ''): ?><span class="mono muted small"><?= e((string)$s['kurz']) ?></span><?php endif; ?>
        <?php if ((int)$s['is_active'] !== 1): ?><span class="badge badge--muted">stillgelegt</span><?php endif; ?></h1>
      <p><?= e($typ['label']) ?><?= $crumbs ? ' in ' . e(standort_pfad((int)end($crumbs)['id'], $alle)) : '' ?>
        <?php if (($s['geo_lat'] ?? null) !== null): ?> · <a href="<?= e(dv_map_url((float)$s['geo_lat'], (float)$s['geo_lng'])) ?>" target="_blank" rel="noopener">📍 auf der Karte</a><?php endif; ?>
        <?php if ($darunterText !== ''): ?><br>Hier und darunter: <?= e($darunterText) ?><?php endif; ?></p>
      <?php if (trim((string)($s['notiz'] ?? '')) !== ''): ?><p><?= nl2br(e((string)$s['notiz'])) ?></p><?php endif; ?>
    <?php else: ?>
      <h1><?= e((string)setting('standort_modul_name', 'Standorte')) ?></h1>
      <p>Wo etwas steht oder liegt: ein Gebäude, eine Halle oder einen Hof anklicken, dann weiter zu Stockwerken,
        Räumen, Stellplätzen, Schränken und Regalen – und sehen, was dort zugeordnet ist.</p>
    <?php endif; ?>
  </div>
  <div class="btnrow">
    <?php if ($s && $crumbs): ?>
      <a class="btn btn--sec" href="<?= e(url('standorte', ['id' => end($crumbs)['id']])) ?>">↑ <?= e((string)end($crumbs)['name']) ?></a>
    <?php elseif ($s): ?>
      <a class="btn btn--sec" href="<?= e(url('standorte')) ?>">↑ Alle Standorte</a>
    <?php endif; ?>
    <?php if (!empty($darfPflegen)): ?>
      <a class="btn btn--sec" href="<?= e($s ? url('admin_standort_edit', ['id' => $s['id']]) : url('admin_standorte')) ?>"><?= $s ? 'Platz bearbeiten' : 'Plätze pflegen' ?></a>
    <?php endif; ?>
  </div>
</div>

<?php if ($s && isset($titelbilder[(int)$s['id']])): ?>
  <a class="standort-titel" href="<?= e(url('standort_bild', ['id' => $titelbilder[(int)$s['id']]['id']])) ?>" target="_blank" rel="noopener">
    <img src="<?= e(url('standort_bild', ['id' => $titelbilder[(int)$s['id']]['id'], 'vorschau' => 1])) ?>" alt="<?= e((string)$s['name']) ?>" loading="lazy"></a>
<?php endif; ?>

<?php if (!$s && $alle): ?>
  <div class="chips" style="margin-bottom:1rem">
    <?php foreach (STANDORT_TYPEN as $key => $t): if (empty($zaehlung[$key])) { continue; } ?>
      <span class="chip"><?= e($t['icon']) ?> <?= (int)$zaehlung[$key] ?> <?= e((int)$zaehlung[$key] === 1 ? $t['label'] : $t['mehrzahl']) ?></span>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$alle): ?>
  <section class="card"><div class="empty">Noch keine Plätze angelegt.
    <?= !empty($darfPflegen) ? 'Unter Verwaltung → Stell- und Lagerplätze beginnt es mit einem Gebäude, einer Halle oder einem Hof.' : 'Die Leitung legt sie unter Verwaltung → Stell- und Lagerplätze an.' ?></div></section>
<?php endif; ?>

<?php if ($kinder): ?>
  <section class="card" id="darunter">
    <div class="card__head">
      <h2><?= $s ? 'Darunter' : 'Gebäude, Hallen und Höfe' ?></h2>
      <span class="muted small"><?= count($kinder) ?></span>
    </div>
    <div class="grid3">
      <?php foreach ($kinder as $k): $typ = $typVon($k); $text = standort_inventar_text($summen[(int)$k['id']] ?? []); $unter = (int)($kinderZahl[(int)$k['id']] ?? 0); ?>
        <a class="item<?= (int)$k['is_active'] !== 1 ? ' item--done' : '' ?>" href="<?= e(url('standorte', ['id' => $k['id']])) ?>">
          <div class="item__top">
            <?php if (isset($titelbilder[(int)$k['id']])): ?>
              <img class="item__bild" alt="" loading="lazy" src="<?= e(url('standort_bild', ['id' => $titelbilder[(int)$k['id']]['id'], 'vorschau' => 1])) ?>">
            <?php else: ?>
              <span class="item__bild standort-icon"><?= e($typ['icon']) ?></span>
            <?php endif; ?>
            <div style="min-width:0;flex:1">
              <div class="item__title"><?= e((string)$k['name']) ?><?= $k['kurz'] !== '' ? ' <span class="mono muted small">' . e((string)$k['kurz']) . '</span>' : '' ?></div>
              <div class="item__sub"><?= e($typ['label']) ?><?= $unter > 0 ? ' · ' . $unter . ' darunter' : '' ?><?= (int)$k['is_active'] !== 1 ? ' · stillgelegt' : '' ?></div>
              <?php if ($text !== ''): ?><div class="item__sub"><?= e($text) ?></div><?php endif; ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($s): ?>
  <?php if ($hierText === ''): ?>
    <section class="card"><div class="empty">Direkt an diesem Platz ist nichts zugeordnet<?= $darunterText !== '' ? ' – aber in den Plätzen darunter: ' . e($darunterText) : '' ?>.</div></section>
  <?php endif; ?>

  <?php if (!empty($hier['fahrzeuge'])): ?>
  <section class="card" id="fahrzeuge">
    <div class="card__head"><h2>Fahrzeuge</h2><span class="muted small"><?= count($hier['fahrzeuge']) ?></span></div>
    <div class="chips">
      <?php foreach ($hier['fahrzeuge'] as $v): ?>
        <a class="chip" href="<?= e(url('vehicle', ['id' => $v['id']])) ?>"><?= e((string)$v['bezeichnung']) ?><?= $v['funkrufname'] ? ' <span class="muted small">' . e((string)$v['funkrufname']) . '</span>' : '' ?><?= (int)$v['is_active'] !== 1 ? ' <span class="badge badge--muted">ausgemustert</span>' : '' ?></a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($zustaendig || !empty($hier['zaehler'])): ?>
  <section class="card" id="zaehler">
    <h2>Zähler</h2>
    <?php if ($zustaendig): ?>
      <h3>Zuständig für diesen Platz</h3>
      <div class="chips">
        <?php foreach ($zustaendig as $z): ?>
          <a class="chip" href="<?= e(url('meter', ['id' => $z['id']])) ?>"><?= e((string)$z['name']) ?> <span class="muted small"><?= e(METER_ARTEN[(string)$z['art']]['label'] ?? $z['art']) ?><?= (string)($z['rolle'] ?? 'bezug') === 'unter' ? ', Unterzähler' : '' ?><?= $z['geerbt_von'] !== null ? ' · über ' . e($z['geerbt_von']) : '' ?></span><?= (int)$z['is_active'] !== 1 ? ' <span class="badge badge--muted">stillgelegt</span>' : '' ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($hier['zaehler'])): ?>
      <h3 class="mt">Hier eingebaut</h3>
      <div class="chips">
        <?php foreach ($hier['zaehler'] as $z): ?>
          <a class="chip" href="<?= e(url('meter', ['id' => $z['id']])) ?>"><?= e((string)$z['name']) ?> <span class="muted small"><?= e(METER_ARTEN[(string)$z['art']]['label'] ?? $z['art']) ?></span><?= (int)$z['is_active'] !== 1 ? ' <span class="badge badge--muted">stillgelegt</span>' : '' ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if (!empty($hier['funkgruppen']) || !empty($hier['funk'])): ?>
  <section class="card" id="funk">
    <div class="card__head"><h2>Funk</h2><span class="muted small"><?= count($hier['funkgruppen'] ?? []) + count($hier['funk'] ?? []) ?></span></div>
    <?php if (!empty($hier['funkgruppen'])): ?>
      <h3>Gruppen</h3>
      <div class="chips">
        <?php foreach ($hier['funkgruppen'] as $g): ?>
          <a class="chip" href="<?= e(url('radio_group', ['id' => $g['id']])) ?>"><?= e((string)$g['name']) ?><?= (int)$g['is_active'] !== 1 ? ' <span class="badge badge--muted">stillgelegt</span>' : '' ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($hier['funk'])): ?>
      <h3 class="mt">Geräte</h3>
      <div class="chips">
        <?php foreach ($hier['funk'] as $r): ?>
          <a class="chip" href="<?= e(url('radio', ['id' => $r['id']])) ?>"><?= e((string)$r['bezeichnung']) ?><?= $r['funkrufname'] ? ' <span class="muted small">' . e((string)$r['funkrufname']) . '</span>' : '' ?><?= empty($r['eigener_standort_id']) && !empty($r['gruppe_name']) ? ' <span class="muted small">· über ' . e((string)$r['gruppe_name']) . '</span>' : '' ?><?= (int)$r['is_active'] !== 1 ? ' <span class="badge badge--muted">ausgemustert</span>' : '' ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
<?php endif; ?>
