<?php
/** @var array $termin @var string $wann @var string $fuerWen @var bool $darfBearbeiten @var array $standorte @var array $fahrzeuge
 *  @var array $auftraege @var array $dienststellen @var array $kontakte @var array $fachgruppen @var array $dateien @var array $besprochen */
$ort = implode(' · ', array_filter(array_merge(array_map(static fn($s) => (string)$s['pfad'], $standorte), [trim((string)($termin['ort'] ?? ''))]), 'strlen'));
$weitere = implode(', ', array_map(static fn($f) => (string)$f['label'], $fachgruppen));
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)$termin['titel']) ?></h1>
    <p><?= e($wann) ?><?= $ort !== '' ? ' · ' . e($ort) : '' ?></p>
  </div>
  <div class="btnrow">
    <?php if ($darfBearbeiten): ?><a class="btn" href="<?= e(url('kalender_edit', ['id' => $termin['id']])) ?>">Bearbeiten</a><?php endif; ?>
    <?php $navi ??= null; $naviLinks ??= []; if ($navi): ?>
      <a class="btn btn--sec" href="<?= e($naviLinks['google']) ?>" target="_blank" rel="noopener" title="<?= e('Ziel: ' . $navi['text']) ?>">🧭 Navigation starten</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('kalender_termin', ['id' => $termin['id'], 'druck' => 1])) ?>" target="_blank" rel="noopener">Drucken / PDF</a>
    <a class="btn btn--sec" href="<?= e(url('kalender', ['monat' => substr((string)$termin['beginn'], 0, 7)])) ?>">Zum Kalender</a>
  </div>
</div>

<section class="card" style="border-left:4px solid <?= e((string)($termin['farbe'] ?: KALENDER_QUELLEN['termin']['color'])) ?>">
  <dl class="dl">
    <div class="dl__item"><div class="dl__label">Wann</div><div class="dl__value"><?= e($wann) ?></div></div>
    <?php if ($ort !== ''): ?><div class="dl__item"><div class="dl__label">Wo</div><div class="dl__value">
      <?php foreach ($standorte as $s): ?><a href="<?= e(url('standorte', ['id' => $s['id']])) ?>"><?= e((string)$s['pfad']) ?></a><br><?php endforeach; ?>
      <?= trim((string)($termin['ort'] ?? '')) !== '' ? e((string)$termin['ort']) : '' ?></div></div><?php endif; ?>
    <?php if ($navi): ?>
      <div class="dl__item"><div class="dl__label">Navigation</div><div class="dl__value">Ziel: <?= e($navi['text']) ?><?= $navi['koordinaten'] ? ' <span class="muted small">(GPS-Koordinaten des Platzes)' : ' <span class="muted small">(als Adresse)' ?></span><br>
        <span class="small"><a href="<?= e($naviLinks['google']) ?>" target="_blank" rel="noopener">Google Maps</a> · <a href="<?= e($naviLinks['apple']) ?>" target="_blank" rel="noopener">Apple Karten</a><?= isset($naviLinks['geo']) ? ' · <a href="' . e($naviLinks['geo']) . '">Karten-App (Android)</a>' : '' ?></span></div></div>
    <?php endif; ?>
    <div class="dl__item"><div class="dl__label">Für wen</div><div class="dl__value"><?= e($fuerWen) ?><?= $weitere !== '' ? '<br><span class="muted small">auch für ' . e($weitere) . '</span>' : '' ?></div></div>
    <?php if (!empty($termin['erfasser'])): ?><div class="dl__item"><div class="dl__label">Eingetragen von</div><div class="dl__value"><?= e((string)$termin['erfasser']) ?></div></div><?php endif; ?>
  </dl>
  <?php if (trim((string)($termin['beschreibung'] ?? '')) !== ''): ?>
    <h3 class="mt">Beschreibung</h3>
    <div class="comment__body"><?= nl2br(e((string)$termin['beschreibung'])) ?></div>
  <?php endif; ?>
</section>

<?php if ($fahrzeuge || $auftraege): ?>
<section class="card" id="fahrzeuge">
  <h2>Fahrzeuge<?= $auftraege ? ' und Aufträge' : '' ?></h2>
  <?php if ($fahrzeuge): ?>
    <div class="chips">
      <?php foreach ($fahrzeuge as $v): ?>
        <a class="chip" href="<?= e(url('vehicle', ['id' => $v['id']])) ?>">⛟ <?= e((string)$v['bezeichnung']) ?><?= $v['funkrufname'] ? ' <span class="muted small">' . e((string)$v['funkrufname']) . '</span>' : '' ?><?= (int)$v['is_active'] !== 1 ? ' <span class="badge badge--muted">ausgemustert</span>' : '' ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($auftraege): ?>
    <div class="chips<?= $fahrzeuge ? ' mt' : '' ?>">
      <?php foreach ($auftraege as $o): ?>
        <a class="chip" href="<?= e(url('vehicle_order', ['id' => $o['id']])) ?>">🔧 <?= e($o['nummer'] . ' ' . $o['titel']) ?> <span class="muted small"><?= e((string)$o['fahrzeug']) ?></span></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($dienststellen): ?>
<section class="card" id="dienststellen">
  <h2>THW-Dienststellen</h2>
  <div class="chips">
    <?php foreach ($dienststellen as $d): $typ = THW_TYPEN[thw_typ((string)$d['typ'])]; ?>
      <a class="chip" href="<?= e(url('thw', ['q' => $d['name']])) ?>"><?= e($typ['icon']) ?> <?= e((string)$d['name']) ?><?= ($d['kurz'] ?? '') !== '' ? ' <span class="muted small">' . e((string)$d['kurz']) . '</span>' : '' ?></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($kontakte): ?>
<section class="card" id="kontakte">
  <h2>Kontakte</h2>
  <ul class="wahl__liste">
    <?php foreach ($kontakte as $c): ?>
      <li class="wahl__eintrag" style="background:transparent">
        <span class="wahl__text"><a href="<?= e(url('contact_edit', ['id' => $c['id']])) ?>">👤 <?= e(contact_name($c)) ?></a>
          <span class="muted small"><?= e(implode(' · ', array_filter([(string)($c['organisation'] ?? ''), (string)($c['position'] ?? '')]))) ?></span>
          <span class="small"><?php foreach (array_filter([$c['mobil'] ?? '', $c['telefon'] ?? '']) as $nr): ?> · <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string)$nr)) ?>"><?= e((string)$nr) ?></a><?php endforeach; ?><?= !empty($c['email']) ? ' · <a href="mailto:' . e((string)$c['email']) . '">' . e((string)$c['email']) . '</a>' : '' ?></span></span>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($dateien): ?>
<section class="card" id="dateien">
  <h2>Dateien</h2>
  <ul class="wahl__liste">
    <?php foreach ($dateien as $f): ?>
      <li class="wahl__eintrag" style="background:transparent">
        <?php if (($f['art'] ?? '') === 'bild' && !empty($f['thumb_name'])): ?>
          <a href="<?= e(url('kalender_file', ['id' => $f['id']])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('kalender_file', ['id' => $f['id'], 'vorschau' => 1])) ?>" alt="" loading="lazy" style="width:2.6rem;height:2.6rem;object-fit:cover;border-radius:6px"></a>
        <?php endif; ?>
        <span class="wahl__text"><a href="<?= e(url('kalender_file', ['id' => $f['id']])) ?>">📎 <?= e((string)($f['titel'] ?: $f['orig_name'])) ?></a>
          <span class="muted small"><?= e((string)$f['orig_name']) ?> · <?= e(bytes_human((int)$f['size_bytes'])) ?></span></span>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if (can('view_meetings')): ?><?= render_partial('partials/tp_besprochen', ['punkte' => $besprochen ?? []]) ?><?php endif; ?>
