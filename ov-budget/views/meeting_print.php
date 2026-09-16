<?php
/** @var array $meeting @var array $punkte @var array $zeiten @var int $gesamt @var string $art */
$protokoll = $art === 'protokoll';
$label = tp_label();
$ovName = (string)setting('ov_name', '');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $protokoll ? 'Protokoll' : 'Tagesordnung' ?> · <?= e($meeting['titel']) ?></title>
<style>
  @page { size: A4; margin: 18mm 16mm 20mm; }
  * { box-sizing: border-box; }
  body {
    margin: 0 auto; max-width: 180mm; padding: 1.5rem;
    font: 11pt/1.45 "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
    color: #111827; background: #fff;
  }
  .kopf { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;
          border-bottom: 2px solid #003399; padding-bottom: .6rem; margin-bottom: 1rem; }
  .kopf .ov { font-size: 9pt; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
  h1 { font-size: 17pt; margin: .1rem 0 0; color: #003399; }
  .art { font-size: 10pt; font-weight: 700; color: #475569; text-align: right; white-space: nowrap; }
  table.eck { width: 100%; border-collapse: collapse; font-size: 10pt; margin-bottom: 1rem; }
  table.eck td { padding: .2rem .4rem .2rem 0; vertical-align: top; }
  table.eck td:first-child { width: 28mm; color: #475569; }
  .top { break-inside: avoid; border-top: 1px solid #cbd5e1; padding: .6rem 0; }
  .top__kopf { display: grid; grid-template-columns: 16mm 1fr auto; gap: .5rem; align-items: baseline; }
  .top__nr { font-weight: 700; color: #003399; }
  .top__titel { font-weight: 700; }
  .top__zeit { font-size: 9pt; color: #475569; white-space: nowrap; }
  .top__text { margin: .25rem 0 0 16mm; white-space: pre-wrap; font-size: 10pt; color: #334155; }
  .ergebnis { margin: .4rem 0 0 16mm; padding: .35rem .6rem; border-left: 3px solid #15803d; background: #f0fdf4; }
  .ergebnis--vertagt { border-left-color: #b45309; background: #fffbeb; }
  .ergebnis__kopf { font-size: 9pt; font-weight: 700; color: #334155; }
  .ergebnis__text { white-space: pre-wrap; }
  .leer { margin: .4rem 0 0 16mm; height: 14mm; border-bottom: 1px dotted #94a3b8; }
  .notizen { margin-top: 1rem; border-top: 2px solid #003399; padding-top: .6rem; }
  .fuss { margin-top: 1.5rem; font-size: 8.5pt; color: #64748b; display: flex; justify-content: space-between; }
  .knopf { position: fixed; top: 1rem; right: 1rem; padding: .5rem .9rem; border: 0; border-radius: 8px;
           background: #003399; color: #fff; font: inherit; cursor: pointer; }
  @media print { .knopf { display: none; } body { padding: 0; } }
</style>
</head>
<body>

<button class="knopf" type="button" onclick="window.print()">Drucken</button>

<div class="kopf">
  <div>
    <?php if ($ovName !== ''): ?><div class="ov"><?= e($ovName) ?></div><?php endif; ?>
    <h1><?= e($meeting['titel']) ?></h1>
  </div>
  <div class="art"><?= $protokoll ? 'Protokoll' : 'Tagesordnung' ?></div>
</div>

<table class="eck">
  <?php
  // In einer Zeile zusammensetzen – Zeilenumbrüche im Template würden vor dem Komma als Leerzeichen erscheinen
  $wann = de_date($meeting['datum'])
      . ($meeting['beginn'] ? ', ' . substr((string)$meeting['beginn'], 0, 5) . ' Uhr' : '')
      . ($meeting['ende'] ? ' bis ' . substr((string)$meeting['ende'], 0, 5) . ' Uhr' : '');
  ?>
  <tr><td>Datum</td><td><?= e($wann) ?></td></tr>
  <?php if ($meeting['ort']): ?><tr><td>Ort</td><td><?= e($meeting['ort']) ?></td></tr><?php endif; ?>
  <?php if ($meeting['typ_label']): ?><tr><td>Art</td><td><?= e($meeting['typ_label']) ?></td></tr><?php endif; ?>
  <?php if ($meeting['leitung']): ?><tr><td>Leitung</td><td><?= e($meeting['leitung']) ?></td></tr><?php endif; ?>
  <?php if ($protokoll): ?>
    <tr><td>Protokoll</td><td><?= e((string)$meeting['protokoll_von'] ?: '–') ?></td></tr>
    <?php if ($meeting['teilnehmer']): ?>
      <tr><td>Anwesend</td><td><?= nl2br(e((string)$meeting['teilnehmer'])) ?></td></tr>
    <?php endif; ?>
  <?php else: ?>
    <tr><td>Dauer</td><td>etwa <?= e(minutes_human($gesamt)) ?></td></tr>
  <?php endif; ?>
</table>

<?php if ($meeting['beschreibung'] && !$protokoll): ?>
  <p style="white-space:pre-wrap"><?= e((string)$meeting['beschreibung']) ?></p>
<?php endif; ?>

<?php if (!$punkte): ?>
  <p><em>Keine <?= e($label) ?> auf der Tagesordnung.</em></p>
<?php endif; ?>

<?php foreach ($punkte as $i => $p):
    $vertagt = $p['status_slug'] === 'vertagt';
?>
  <div class="top">
    <div class="top__kopf">
      <span class="top__nr">TOP <?= $i + 1 ?></span>
      <span class="top__titel"><?= e($p['titel']) ?></span>
      <?php
      $meta = [];
      if (!$protokoll && $zeiten[$i] !== '') {
          $meta[] = $zeiten[$i] . ' Uhr';
      }
      if ($p['fachgruppe_label']) {
          $meta[] = $p['fachgruppe_label'];
      }
      ?>
      <span class="top__zeit"><?= e(implode(' · ', $meta)) ?></span>
    </div>
    <?php if ($p['beschreibung']): ?>
      <div class="top__text"><?= e((string)$p['beschreibung']) ?></div>
    <?php endif; ?>

    <?php if ($protokoll): ?>
      <?php if ($p['ergebnis'] || $p['status_label']): ?>
        <div class="ergebnis<?= $vertagt ? ' ergebnis--vertagt' : '' ?>">
          <div class="ergebnis__kopf">
            <?= e((string)$p['status_label']) ?>
            <?php if ($p['verantwortlich']): ?> · verantwortlich: <?= e($p['verantwortlich']) ?><?php endif; ?>
            <?php if ($p['todo_id']): ?> · Aufgabe #<?= (int)$p['todo_id'] ?><?php endif; ?>
          </div>
          <?php if ($p['ergebnis']): ?><div class="ergebnis__text"><?= e((string)$p['ergebnis']) ?></div><?php endif; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="leer"></div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if ($protokoll && $meeting['notizen']): ?>
  <div class="notizen">
    <strong>Weitere Notizen</strong>
    <div style="white-space:pre-wrap"><?= e((string)$meeting['notizen']) ?></div>
  </div>
<?php endif; ?>

<div class="fuss">
  <span><?= e($ovName) ?></span>
  <span>Stand <?= e(date('d.m.Y H:i')) ?></span>
</div>

</body>
</html>
