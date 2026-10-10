<?php
/**
 * @var ?array $s      der angeklickte Platz (null = ganz oben)
 * @var array $alle $crumbs $kinder $summen $hier $darunter $zustaendig $titelbilder $zaehlung $kinderZahl
 * @var bool $darfPflegen
 */
$titelbilder ??= []; $hier ??= []; $darunter ??= []; $zustaendig ??= []; $kinderZahl ??= []; $fzTitelbilder ??= []; $fzFristen ??= []; $kindFahrzeuge ??= [];
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

<?php $plan ??= null; $planMarker ??= []; if ($s && $plan): ?>
  <section class="card" id="plan">
    <div class="card__head">
      <h2>Plan</h2>
      <span class="muted small"><?= count($planMarker) ?> von <?= count($kinder) ?> Plätzen verortet</span>
    </div>
    <div class="plan">
      <img src="<?= e(url('standort_bild', ['id' => $plan['id']])) ?>" alt="<?= e((string)$plan['titel']) ?>">
      <?php foreach ($planMarker as $m): $fz = $kindFahrzeuge[$m['id']] ?? []; ?>
        <?php if ($fz): $v = $fz[0]; $stempel = vehicle_stamp($v); $vAktiv = (int)($v['is_active'] ?? 1) === 1; ?>
          <a class="plan__marker plan__marker--fz<?= !$vAktiv ? ' plan__marker--aus' : '' ?>" style="left:<?= number_format($m['x'], 2, '.', '') ?>%;top:<?= number_format($m['y'], 2, '.', '') ?>%;border-color:<?= e((string)($v['status_color'] ?? '') ?: '#94a3b8') ?>"
             href="<?= e(url('vehicle', ['id' => $v['id']])) ?>" title="<?= e($m['name'] . ': ' . (string)$v['bezeichnung'] . ($stempel ? ' – ' . $stempel : '')) ?>">
            <?php if (isset($fzTitelbilder[(int)$v['id']])): ?>
              <img alt="" loading="lazy" src="<?= e(url('vehicle_file', ['id' => $fzTitelbilder[(int)$v['id']]['id'], 'vorschau' => 1])) ?>">
            <?php else: ?><span class="plan__fzleer">⛟</span><?php endif; ?>
            <span class="plan__fzname"><?= e((string)$v['bezeichnung']) ?><?= count($fz) > 1 ? ' +' . (count($fz) - 1) : '' ?></span>
            <?php if ($stempel !== null): ?><span class="plan__stempel"><?= e($stempel) ?></span><?php endif; ?>
            <span class="plan__platz"><?= e($m['name']) ?></span>
          </a>
        <?php else: ?>
          <a class="plan__marker<?= $m['is_active'] !== 1 ? ' plan__marker--aus' : '' ?>" style="left:<?= number_format($m['x'], 2, '.', '') ?>%;top:<?= number_format($m['y'], 2, '.', '') ?>%" href="<?= e(url('standorte', ['id' => $m['id']])) ?>" title="<?= e($m['name']) ?>">
            <span class="plan__pin"></span><span class="plan__label"><?= e($m['name']) ?></span></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <p class="small muted" style="margin:.5rem 0 0">Farbige Kante: Status des Fahrzeugs. Leere Markierung: Platz ohne Fahrzeug. Klick führt zur Akte bzw. zum Platz.</p>
  </section>
<?php endif; ?>

<?php if ($kinder): ?>
  <section class="card" id="darunter">
    <div class="card__head">
      <h2><?= $s ? 'Darunter' : 'Gebäude, Hallen und Höfe' ?></h2>
      <span class="muted small"><?= count($kinder) ?></span>
    </div>
    <div class="grid3">
      <?php foreach ($kinder as $k): $typ = $typVon($k); $text = standort_inventar_text($summen[(int)$k['id']] ?? []); $unter = (int)($kinderZahl[(int)$k['id']] ?? 0); ?>
        <div class="item<?= (int)$k['is_active'] !== 1 ? ' item--done' : '' ?>">
          <a class="item__link" href="<?= e(url('standorte', ['id' => $k['id']])) ?>">
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
          <?php if (!empty($kindFahrzeuge[(int)$k['id']])): ?>
            <div class="platz__fz">
              <?php foreach ($kindFahrzeuge[(int)$k['id']] as $v): $stempel = vehicle_stamp($v); $vAktiv = (int)($v['is_active'] ?? 1) === 1; ?>
                <a class="platz__fzeintrag<?= $vAktiv ? '' : ' platz__fzeintrag--aus' ?>" href="<?= e(url('vehicle', ['id' => $v['id']])) ?>">
                  <span class="kachel__bild platz__fzbild">
                    <?php if ($stempel !== null): ?><span class="stempel<?= $vAktiv ? '' : ' stempel--grau' ?>"><?= e($stempel) ?></span><?php endif; ?>
                    <?php if (isset($fzTitelbilder[(int)$v['id']])): ?>
                      <img alt="" loading="lazy" src="<?= e(url('vehicle_file', ['id' => $fzTitelbilder[(int)$v['id']]['id'], 'vorschau' => 1])) ?>">
                    <?php else: ?>
                      <span class="kachel__leer" aria-hidden="true">⛟</span>
                    <?php endif; ?>
                  </span>
                  <span class="platz__fztext">
                    <strong><?= e((string)$v['bezeichnung']) ?></strong>
                    <?php $sub = implode(' · ', array_filter([(string)($v['funkrufname'] ?? ''), (string)($v['kennzeichen'] ?? '')])); if ($sub !== ''): ?><span class="small muted"><?= e($sub) ?></span><?php endif; ?>
                    <?= badge(!empty($v['status_label']) ? ['label' => $v['status_label'], 'color' => $v['status_color'] ?? ''] : null, 'ohne Status') ?>
                  </span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
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
    <div class="kacheln">
      <?php foreach ($hier['fahrzeuge'] as $v): ?>
        <?= render_partial('partials/fahrzeug_kachel', ['v' => $v, 'titelbilder' => $fzTitelbilder, 'fristen' => $fzFristen[(int)$v['id']] ?? [], 'mitPlatz' => false]) ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php $energie ??= null; if ($energie && $energie['teile']): ?>
  <section class="card" id="energie">
    <div class="card__head"><h2>Energie und Kosten <?= (int)$energie['jahr'] ?></h2>
      <a class="small" href="<?= e(url('verbrauch_bereiche', ['jahr' => $energie['jahr']])) ?>">Alle Bereiche</a></div>
    <?php if ($energie['geerbt_von']): ?>
      <p class="small muted">Kein eigener Zähler – die Zahlen gelten für <?= e($energie['geerbt_von']) ?>, zu dem dieser Platz gehört.</p>
    <?php endif; ?>
    <dl class="dl">
      <?php foreach ($energie['teile'] as $t): ?>
        <div class="dl__item"><div class="dl__label"><?= e($t['label']) ?></div><div class="dl__value"><?= e($t['text']) ?></div></div>
      <?php endforeach; ?>
      <?php if ($energie['kosten'] !== '' && count($energie['teile']) > 1): ?>
        <div class="dl__item"><div class="dl__label">Kosten gesamt</div><div class="dl__value"><strong><?= e($energie['kosten']) ?></strong></div></div>
      <?php endif; ?>
      <?php if ($energie['anteil'] !== ''): ?>
        <div class="dl__item"><div class="dl__label">Anteil</div><div class="dl__value"><?= e($energie['anteil']) ?></div></div>
      <?php endif; ?>
    </dl>
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
