<?php
/**
 * Gemeinsamer Kopf und Stil der Verbrauchsberichte.
 * @var string $titel  @var string $untertitel  @var string $rueck  URL zurück
 * @var bool   $quer   Querformat (Jahresbericht)
 */
$ovName = (string)setting('ov_name', '');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titel) ?></title>
<style>
  @page { size: A4 <?= !empty($quer) ? 'landscape' : 'portrait' ?>; margin: 14mm 14mm 16mm; }
  * { box-sizing: border-box; }
  body { margin: 0 auto; max-width: <?= !empty($quer) ? '270mm' : '190mm' ?>; padding: 1.5rem;
         font: 11pt/1.4 "Segoe UI", system-ui, -apple-system, Arial, sans-serif; color: #111827; background: #fff;
         -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .kopf { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem;
          border-bottom: 2px solid #003399; padding-bottom: .6rem; margin-bottom: 1rem; }
  .kopf .ov { font-size: 9pt; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
  h1 { font-size: 20pt; margin: .1rem 0 0; color: #003399; }
  h2 { font-size: 13pt; margin: 1.4rem 0 .5rem; color: #003399; break-after: avoid; }
  .unter { font-size: 10pt; color: #475569; }
  .kacheln { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .8rem; margin: .4rem 0 1rem; }
  .kachel { border: 1px solid #e2e8f0; border-radius: 10px; padding: .7rem .9rem; break-inside: avoid; }
  .kachel__label { font-size: 8.5pt; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
  .kachel__wert { font-size: 20pt; font-weight: 700; line-height: 1.15; margin-top: .15rem; font-variant-numeric: tabular-nums; }
  .kachel__wert small { font-size: 10pt; font-weight: 600; color: #475569; }
  .kachel__hinweis { font-size: 9pt; color: #475569; margin-top: .2rem; }
  .plus { color: #b91c1c; } .minus { color: #15803d; }
  .balken { display: grid; grid-template-columns: repeat(12, 1fr); gap: 4px; height: 150px; align-items: end;
            border-bottom: 1px solid #cbd5e1; padding-bottom: 2px; margin-top: .6rem; }
  .balken__monat { display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; min-width: 0; }
  .balken__paar { display: flex; align-items: flex-end; gap: 2px; width: 100%; height: 100%; justify-content: center; }
  .balken__stab { width: 45%; background: #003399; border-radius: 3px 3px 0 0; }
  .balken__stab--vorjahr { background: #cbd5e1; }
  .balken__wert { font-size: 7.5pt; color: #475569; font-variant-numeric: tabular-nums; margin-top: 2px; white-space: nowrap; }
  .balken__name { font-size: 8pt; color: #64748b; }
  .legende { font-size: 8.5pt; color: #475569; margin-top: .3rem; }
  .legende__feld { display: inline-block; width: 10px; height: 10px; border-radius: 2px; vertical-align: middle; margin: 0 .2rem 0 .6rem; background: #003399; }
  .legende__feld--vorjahr { background: #cbd5e1; }
  table.liste { width: 100%; border-collapse: collapse; font-size: 10pt; }
  table.liste th { text-align: left; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .04em; color: #475569;
                   border-bottom: 1.5px solid #003399; padding: .3rem .4rem; }
  table.liste td { padding: .3rem .4rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
  table.liste tr { break-inside: avoid; }
  table.liste .num, table.liste th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  tfoot td { border-top: 1.5px solid #003399; border-bottom: 0; font-weight: 700; }
  .klein { font-size: 8.5pt; color: #64748b; }
  .profil { display: grid; grid-template-columns: 1fr 2fr; gap: 1.2rem; }
  .profil__titel { font-size: 9pt; font-weight: 700; color: #475569; margin-bottom: .3rem; }
  .profil__einheit { font-weight: 400; }
  .profil__balken { display: grid; gap: 3px; height: 90px; align-items: end; border-bottom: 1px solid #cbd5e1; }
  .profil__balken--tage { grid-template-columns: repeat(7, 1fr); }
  .profil__balken--stunden { grid-template-columns: repeat(24, 1fr); gap: 1px; }
  .profil__spalte { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-width: 0; }
  .profil__stab { width: 70%; border-radius: 2px 2px 0 0; }
  .profil__wert { font-size: 7pt; color: #475569; white-space: nowrap; }
  .profil__name { font-size: 7.5pt; color: #64748b; margin-top: 2px; }
  .profil__name--spitze { font-weight: 700; color: #111827; }
  table.eck, dl.eck { font-size: 9.5pt; }
  dl.eck .dl__item { margin: .25rem 0; } dl.eck .dl__label { font-size: 8pt; text-transform: uppercase; color: #64748b; }
  .abschnitt { break-inside: avoid; }
  .fuss { margin-top: 1.4rem; font-size: 8.5pt; color: #64748b; display: flex; justify-content: space-between; border-top: 1px solid #e2e8f0; padding-top: .4rem; }
  .leiste { position: fixed; top: 1rem; right: 7.5rem; font-size: 9pt; }
  .leiste a { color: #003399; margin-left: .6rem; }
  .knopf { position: fixed; top: 1rem; right: 1rem; padding: .5rem .9rem; border: 0; border-radius: 8px;
           background: #003399; color: #fff; font: inherit; cursor: pointer; }
  @media print { .knopf, .leiste { display: none; } body { padding: 0; max-width: none; } }
</style>
</head>
<body>
<button class="knopf" type="button" onclick="window.print()">Drucken / PDF</button>
<div class="leiste"><a href="<?= e($rueck) ?>">Zurück</a></div>
<div class="kopf">
  <div>
    <?php if ($ovName !== ''): ?><div class="ov"><?= e($ovName) ?></div><?php endif; ?>
    <h1><?= e($titel) ?></h1>
    <?php if ($untertitel !== ''): ?><div class="unter"><?= e($untertitel) ?></div><?php endif; ?>
  </div>
  <div class="unter">Stand <?= e(de_date(date('Y-m-d'))) ?></div>
</div>
