<?php
/** @var array $event @var array $gaeste @var ?array $connector
 *  @var int $ohneAnschrift @var string $wen @var bool $mitQr */
$ovName = (string)setting('ov_name', '');
$beginn = (string)$event['beginn'];
$zeit = substr($beginn, 11, 5);
$wann = de_date(substr($beginn, 0, 10)) . ($zeit !== '00:00' ? ', ' . $zeit . ' Uhr' : '');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Einladungsliste · <?= e((string)$event['titel']) ?></title>
<style>
  @page { size: A4; margin: 14mm 12mm 16mm; }
  * { box-sizing: border-box; }
  body {
    margin: 0 auto; max-width: 195mm; padding: 1.5rem;
    font: 10.5pt/1.4 "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
    color: #111827; background: #fff;
  }
  .kopf { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;
          border-bottom: 2px solid #003399; padding-bottom: .6rem; margin-bottom: .6rem; }
  .kopf .ov { font-size: 9pt; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
  h1 { font-size: 16pt; margin: .1rem 0 0; color: #003399; }
  .art { font-size: 10pt; font-weight: 700; color: #475569; text-align: right; white-space: nowrap; }
  .wann { font-size: 10pt; color: #334155; margin-bottom: .9rem; }
  .hinweis { font-size: 9pt; color: #b45309; margin-bottom: .8rem; }
  .karten { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
  .karte { break-inside: avoid; border: 1px solid #cbd5e1; border-radius: 6px; padding: .55rem .65rem;
           display: grid; grid-template-columns: 1fr 22mm; gap: .5rem; align-items: start; }
  .karte--ohne { border-style: dashed; }
  .adresse { font-size: 10pt; line-height: 1.35; }
  .adresse div:first-child { font-weight: 600; }
  .anrede { font-size: 9pt; color: #334155; margin-top: .3rem; font-style: italic; }
  .link { font-size: 8pt; color: #003399; margin-top: .3rem; word-break: break-all;
          font-family: ui-monospace, "Cascadia Mono", Consolas, monospace; }
  .fehlt { font-size: 8.5pt; color: #b45309; margin-top: .3rem; }
  .qr { width: 22mm; }
  .qr svg { width: 22mm; height: 22mm; display: block; }
  .qr .code { font-size: 8pt; text-align: center; letter-spacing: .06em; margin-top: .1rem; color: #475569; }
  .fuss { margin-top: 1rem; font-size: 8.5pt; color: #64748b; display: flex; justify-content: space-between; }
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
  <?php foreach (['alle' => 'alle', 'post' => 'nur mit Anschrift', 'offen' => 'nur Offene'] as $k => $text): ?>
    <?php if ($k !== $wen): ?>
      <a href="<?= e(url('event_invites', ['id' => $event['id'], 'wen' => $k]
          + ($mitQr ? [] : ['qr' => '0']))) ?>"><?= e($text) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
  <a href="<?= e(url('event_invites', ['id' => $event['id'], 'wen' => $wen]
      + ($mitQr ? ['qr' => '0'] : []))) ?>"><?= $mitQr ? 'ohne QR-Codes' : 'mit QR-Codes' ?></a>
</div>

<div class="kopf">
  <div>
    <?php if ($ovName !== ''): ?><div class="ov"><?= e($ovName) ?></div><?php endif; ?>
    <h1><?= e((string)$event['titel']) ?></h1>
  </div>
  <div class="art">Einladungsliste</div>
</div>

<div class="wann"><?= !empty($event['typ_label']) ? e((string)$event['typ_label']) . ' · ' : '' ?><?= e($wann) ?><?= trim((string)$event['ort']) !== ''
    ? ' · ' . e((string)$event['ort']) : '' ?>
  · <?= count($gaeste) ?> Einladung(en)</div>

<?php if ($connector === null): ?>
  <p class="hinweis">Für diese Veranstaltung ist kein Connector eingetragen – es gibt daher keine
    Adresse, auf die ein QR-Code zeigen könnte. Die Codes stehen trotzdem dabei.</p>
<?php endif; ?>
<?php if ($wen === 'alle' && $ohneAnschrift > 0): ?>
  <p class="hinweis"><?= (int)$ohneAnschrift ?> Eingeladene haben keine Anschrift im Kontaktmodul –
    für einen Serienbrief fehlt dort etwas. Mit „nur mit Anschrift" bleiben sie außen vor.</p>
<?php endif; ?>

<?php if (!$gaeste): ?>
  <p>Für diese Auswahl steht niemand auf der Liste.</p>
<?php else: ?>
<div class="karten">
  <?php foreach ($gaeste as $g): ?>
    <?php
      $zeilen = event_guest_address($g);
      $adresse = event_guest_postfaehig($g);
      $link = $connector !== null ? event_invite_url($connector, (string)$g['code']) : '';
    ?>
    <div class="karte<?= $adresse ? '' : ' karte--ohne' ?>">
      <div>
        <div class="adresse">
          <?php foreach ($zeilen as $zeile): ?>
            <div><?= e((string)$zeile) ?></div>
          <?php endforeach; ?>
        </div>
        <div class="anrede"><?= e(event_guest_salutation($g)) ?></div>
        <?php if ($link !== ''): ?>
          <div class="link"><?= e($link) ?></div>
        <?php endif; ?>
        <?php if (!$adresse): ?>
          <div class="fehlt">ohne Anschrift – für den Brief fehlt die Adresse</div>
        <?php endif; ?>
      </div>
      <div class="qr">
        <?php if ($mitQr && $link !== ''): ?>
          <div data-qr="<?= e($link) ?>"></div>
        <?php endif; ?>
        <div class="code"><?= e((string)$g['code']) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="fuss">
  <div><?= e($ovName) ?></div>
  <div>Stand: <?= e(de_datetime(date('Y-m-d H:i:s'))) ?></div>
</div>

<?php if ($mitQr && $connector !== null): ?>
  <script src="<?= e(asset('js/qrcode.js')) ?>"></script>
  <script src="<?= e(asset('js/qr-liste.js')) ?>"></script>
<?php endif; ?>

</body>
</html>
