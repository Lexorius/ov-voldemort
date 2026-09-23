<?php
/** @var array $event @var array $gaeste @var array $stats @var array $gezeigt
 *  @var string $wen @var bool $mitKommentaren */
$ovName = (string)setting('ov_name', '');
$beginn = (string)$event['beginn'];
$zeit = substr($beginn, 11, 5);
$ueberschrift = match ($wen) {
    'zusagen' => 'Einlassliste',
    'offen'   => 'Noch keine Rückmeldung',
    default   => 'Gästeliste',
};
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($ueberschrift) ?> · <?= e((string)$event['titel']) ?></title>
<style>
  @page { size: A4; margin: 16mm 14mm 18mm; }
  * { box-sizing: border-box; }
  body {
    margin: 0 auto; max-width: 190mm; padding: 1.5rem;
    font: 11pt/1.4 "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
    color: #111827; background: #fff;
  }
  .kopf { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;
          border-bottom: 2px solid #003399; padding-bottom: .6rem; margin-bottom: .8rem; }
  .kopf .ov { font-size: 9pt; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
  h1 { font-size: 17pt; margin: .1rem 0 0; color: #003399; }
  .art { font-size: 10pt; font-weight: 700; color: #475569; text-align: right; white-space: nowrap; }
  table.eck { width: 100%; border-collapse: collapse; font-size: 10pt; margin-bottom: .8rem; }
  table.eck td { padding: .15rem .4rem .15rem 0; vertical-align: top; }
  table.eck td:first-child { width: 26mm; color: #475569; }
  table.liste { width: 100%; border-collapse: collapse; font-size: 10pt; }
  table.liste th { text-align: left; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .04em;
                   color: #475569; border-bottom: 1.5px solid #003399; padding: .3rem .4rem; }
  table.liste td { padding: .35rem .4rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
  table.liste tr { break-inside: avoid; }
  .haken { width: 9mm; text-align: center; }
  .haken span { display: inline-block; width: 4.5mm; height: 4.5mm; border: 1.2px solid #475569; border-radius: 2px; }
  .nr { width: 8mm; color: #94a3b8; font-variant-numeric: tabular-nums; }
  .zahl { text-align: center; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .name { font-weight: 600; }
  .org, .dazu { font-size: 8.5pt; color: #475569; }
  .status { white-space: nowrap; font-size: 9pt; }
  .status--zu { color: #15803d; font-weight: 700; }
  .status--ab { color: #b91c1c; }
  .status--vt { color: #0369a1; font-weight: 700; }
  .status--of { color: #64748b; }
  tfoot td { border-top: 1.5px solid #003399; border-bottom: 0; font-weight: 700; padding-top: .45rem; }
  .fuss { margin-top: 1.2rem; font-size: 8.5pt; color: #64748b; display: flex; justify-content: space-between; }
  .leiste { position: fixed; top: 1rem; right: 7.5rem; font-size: 9pt; }
  .leiste a { color: #003399; margin-left: .6rem; }
  .knopf { position: fixed; top: 1rem; right: 1rem; padding: .5rem .9rem; border: 0; border-radius: 8px;
           background: #003399; color: #fff; font: inherit; cursor: pointer; }
  @media print { .knopf, .leiste { display: none; } body { padding: 0; } }
</style>
</head>
<body>

<button class="knopf" type="button" onclick="window.print()">Drucken</button>
<div class="leiste">
  <?php foreach (['alle' => 'alle', 'zusagen' => 'nur Zusagen', 'offen' => 'nur Offene'] as $k => $text): ?>
    <?php if ($k !== $wen): ?>
      <a href="<?= e(url('event_print', ['id' => $event['id'], 'wen' => $k]
          + ($mitKommentaren ? [] : ['kommentare' => '0']))) ?>"><?= e($text) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
  <a href="<?= e(url('event_print', ['id' => $event['id'], 'wen' => $wen]
      + ($mitKommentaren ? ['kommentare' => '0'] : []))) ?>">
    <?= $mitKommentaren ? 'ohne Nachrichten' : 'mit Nachrichten' ?></a>
</div>

<div class="kopf">
  <div>
    <?php if ($ovName !== ''): ?><div class="ov"><?= e($ovName) ?></div><?php endif; ?>
    <h1><?= e((string)$event['titel']) ?></h1>
  </div>
  <div class="art"><?= e($ueberschrift) ?></div>
</div>

<table class="eck">
  <?php
  $wann = de_date(substr($beginn, 0, 10)) . ($zeit !== '00:00' ? ', ' . $zeit . ' Uhr' : '');
  if (trim((string)($event['ende'] ?? '')) !== '') {
      $wann .= substr((string)$event['ende'], 0, 10) === substr($beginn, 0, 10)
          ? ' bis ' . substr((string)$event['ende'], 11, 5) . ' Uhr'
          : ' bis ' . de_datetime((string)$event['ende']);
  }
  ?>
  <tr><td>Wann</td><td><?= e($wann) ?></td></tr>
  <?php if (trim((string)$event['ort']) !== ''): ?>
    <tr><td>Wo</td><td><?= e((string)$event['ort']) ?></td></tr>
  <?php endif; ?>
  <tr><td>Rückmeldungen</td><td>
    <?= (int)$stats['zusagen'] + (int)$stats['vertretungen'] ?> Zusagen,
    <?= (int)$stats['absagen'] ?> Absagen,
    <?= (int)$stats['offen'] ?> offen
    <?= (int)$stats['eingeladen'] > 0 ? '(von ' . (int)$stats['eingeladen'] . ' Eingeladenen)' : '' ?>
  </td></tr>
  <tr><td>Erwartet</td><td><strong><?= (int)$stats['personen'] ?> Personen</strong>
    <?= (int)$stats['begleiter'] > 0 ? ', davon ' . (int)$stats['begleiter'] . ' Begleiter' : '' ?></td></tr>
  <?php if (trim((string)($event['rueckmeldung_bis'] ?? '')) !== ''): ?>
    <tr><td>Rückmeldung bis</td><td><?= e(de_date((string)$event['rueckmeldung_bis'])) ?></td></tr>
  <?php endif; ?>
</table>

<?php if (!$gaeste): ?>
  <p>Für diese Auswahl steht niemand auf der Liste.</p>
<?php else: ?>
<table class="liste">
  <thead>
    <tr>
      <th class="haken">Da</th>
      <th class="nr">Nr.</th>
      <th>Name</th>
      <th class="status">Rückmeldung</th>
      <th class="zahl">Pers.</th>
      <th>Bemerkung</th>
    </tr>
  </thead>
  <tbody>
  <?php $nr = 0; foreach ($gaeste as $g): ?>
    <?php
      $nr++;
      $status = (string)$g['status'];
      $personen = in_array($status, ['zusage', 'vertretung'], true) ? 1 + (int)$g['begleiter'] : 0;
      [$klasse, $text] = match ($status) {
          'zusage'     => ['status--zu', 'Zusage'],
          'vertretung' => ['status--vt', 'Vertretung'],
          'absage'     => ['status--ab', 'Absage'],
          default      => ['status--of', 'offen'],
      };
    ?>
    <tr>
      <td class="haken"><span></span></td>
      <td class="nr"><?= $nr ?></td>
      <td>
        <div class="name"><?= e(event_guest_name($g)) ?></div>
        <?php if (trim((string)($g['organisation'] ?? '')) !== ''): ?>
          <div class="org"><?= e((string)$g['organisation']) ?></div>
        <?php endif; ?>
      </td>
      <td class="status <?= $klasse ?>"><?= e($text) ?>
        <?php if ((int)$g['begleiter'] > 0): ?>
          <div class="dazu">+<?= (int)$g['begleiter'] ?> Begleiter</div>
        <?php endif; ?>
      </td>
      <td class="zahl"><?= $personen > 0 ? $personen : '–' ?></td>
      <td>
        <?php if (trim((string)$g['vertretung']) !== ''): ?>
          <div>kommt: <?= e((string)$g['vertretung']) ?></div>
        <?php endif; ?>
        <?php if ($mitKommentaren && trim((string)($g['kommentar'] ?? '')) !== ''): ?>
          <div class="dazu"><?= e((string)$g['kommentar']) ?></div>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="4"><?= count($gaeste) ?> Einträge auf dieser Liste</td>
      <td class="zahl"><?= (int)$gezeigt['personen'] ?></td>
      <td>Personen erwartet</td>
    </tr>
  </tfoot>
</table>
<?php endif; ?>

<div class="fuss">
  <div><?= e($ovName) ?></div>
  <div>Stand: <?= e(de_datetime(date('Y-m-d H:i:s'))) ?></div>
</div>

</body>
</html>
