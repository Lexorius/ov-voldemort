<?php
/**
 * Seite am Lagerplatz – das Ziel des QR-Codes am Getränkeregal oder am
 * Kühlschrank. Wer vorbeikommt, tippt je Getränk „leer", „wird knapp" oder
 * „reicht noch" und sagt wahlweise, wie viele Kästen noch da sind.
 *
 * Welcher Platz es ist, weiß der Server nicht: Der Name steht im Anker der
 * Adresse (hinter dem #) und wird vom Browser nie mitgeschickt. Bekannt sind
 * hier nur die Getränke mit Gebinde – sonst ließe sich die Liste nicht zeigen.
 *
 * @var ?array $eintrag  ['kennung' => …, 'getraenke' => [[id, name, gebinde], …]] oder null
 * @var string $token
 */
declare(strict_types=1);

$k = con_kopplung();
$schluessel = (string)($k['ov_pubkey'] ?? '');
$basis = rtrim(strtr(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '\\', '/'), '/') . '/';
$h = static fn(string $t): string => htmlspecialchars($t, ENT_QUOTES);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>Getränke melden</title>
<style>
  :root { --akzent: #003399; --rand: #cbd5e1; --gut: #15803d; --schlecht: #b91c1c; --warn: #a16207; }
  * { box-sizing: border-box; }
  body { margin: 0 auto; max-width: 32rem; padding: 1.2rem 1rem 3rem;
         font: 17px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color: #111827; background: #f8fafc; }
  h1 { font-size: 1.25rem; margin: .2rem 0 .1rem; }
  .was { font-size: 1.05rem; font-weight: 700; color: var(--akzent); }
  .karte { background: #fff; border: 1px solid var(--rand); border-radius: 12px; padding: 1rem; margin-top: 1rem; }
  .getraenk { border-top: 1px solid var(--rand); padding: .8rem 0; }
  .getraenk:first-child { border-top: 0; padding-top: 0; }
  .getraenk__name { font-weight: 600; }
  .getraenk__gebinde { color: #64748b; font-size: .85rem; font-weight: 400; }
  .wahl { display: flex; gap: .4rem; margin-top: .4rem; }
  .wahl label { flex: 1; text-align: center; border: 1px solid var(--rand); border-radius: 10px; padding: .55rem .3rem;
                cursor: pointer; font-size: .95rem; }
  .wahl input { position: absolute; opacity: 0; width: 1px; height: 1px; }
  .wahl label:has(input:checked) { border-color: var(--akzent); background: #eef2ff; font-weight: 700; }
  .wahl label.leer:has(input:checked) { border-color: var(--schlecht); background: #fef2f2; color: #7f1d1d; }
  .wahl label.knapp:has(input:checked) { border-color: var(--warn); background: #fffbeb; color: #78350f; }
  .wahl label.ok:has(input:checked) { border-color: var(--gut); background: #f0fdf4; color: #14532d; }
  .noch { display: flex; align-items: center; gap: .5rem; margin-top: .4rem; font-size: .9rem; color: #475569; }
  .noch input { width: 5rem; padding: .45rem; font: inherit; border: 1px solid var(--rand); border-radius: 8px; text-align: right; }
  input[type=text] { width: 100%; padding: .7rem; font: inherit; border: 1px solid var(--rand); border-radius: 10px; }
  label.feld { display: block; font-size: .9rem; color: #475569; margin: .8rem 0 .2rem; }
  button { display: block; width: 100%; padding: .9rem 1rem; margin: .8rem 0 0; font: inherit; font-weight: 600;
           color: #fff; background: var(--akzent); border: 0; border-radius: 10px; cursor: pointer; min-height: 52px; }
  button[disabled] { opacity: .6; }
  .meldung { margin-top: .8rem; padding: .7rem .9rem; border-radius: 10px; background: #eef2ff; font-size: .95rem; }
  .meldung.gut { background: #f0fdf4; color: #14532d; }
  .meldung.schlecht { background: #fef2f2; color: #7f1d1d; }
  .meldung:empty { display: none; }
  .klein { font-size: .82rem; color: #64748b; }
</style>
</head>
<body>

<?php if ($eintrag === null || $schluessel === ''): ?>
  <h1>Das klappt nicht</h1>
  <div class="karte">
    <p><?= $eintrag === null
        ? 'Dieser QR-Code gehört zu keinem Lagerplatz (mehr). Vermutlich wurde er zurückgezogen.'
        : 'Der Dienst ist noch nicht eingerichtet. Bitte im Ortsverband Bescheid geben.' ?></p>
  </div>
<?php else: ?>
  <h1>Getränke melden</h1>
  <div class="was" id="was">Lagerplatz</div>
  <p class="klein">Je Getränk antippen, was zutrifft. Was du nicht antippst, wird nicht gemeldet.
    Die Meldung wird hier im Browser verschlüsselt; der Server kann sie nicht lesen.</p>

  <div class="karte" id="box"
       data-token="<?= $h($token) ?>"
       data-basis="<?= $h($basis) ?>"
       data-schluessel="<?= $h($schluessel) ?>">
    <form id="formular" novalidate>
      <?php foreach ($eintrag['getraenke'] as $g): $id = (int)$g['id']; ?>
        <div class="getraenk" data-getraenk="<?= $id ?>">
          <div class="getraenk__name"><?= $h((string)$g['name']) ?>
            <?php if (trim((string)$g['gebinde']) !== ''): ?><span class="getraenk__gebinde">· <?= $h((string)$g['gebinde']) ?></span><?php endif; ?></div>
          <div class="wahl">
            <label class="leer"><input type="radio" name="s_<?= $id ?>" value="leer">leer</label>
            <label class="knapp"><input type="radio" name="s_<?= $id ?>" value="knapp">wird knapp</label>
            <label class="ok"><input type="radio" name="s_<?= $id ?>" value="ok">reicht noch</label>
          </div>
          <div class="noch"><span>noch</span><input type="number" inputmode="numeric" min="0" max="9999" name="n_<?= $id ?>" placeholder="–"><span><?= trim((string)$g['gebinde']) !== '' ? $h((string)$g['gebinde']) : 'Stück' ?> da <span class="klein">(freiwillig)</span></span></div>
        </div>
      <?php endforeach; ?>
      <label class="feld" for="melder">Dein Name <span class="klein">(freiwillig, wird auf diesem Gerät gemerkt)</span></label>
      <input type="text" id="melder" maxlength="60" autocomplete="name" placeholder="z. B. Anna">
      <button type="submit" id="senden">Melden</button>
    </form>
    <div class="meldung" id="meldung"></div>
    <noscript><p class="meldung schlecht">Ohne JavaScript geht es hier nicht – die Meldung wird
      im Browser verschlüsselt.</p></noscript>
  </div>
  <p class="klein">Gemeldet werden nur die angetippten Getränke, der Zeitpunkt und – wenn eingetragen – der Name.</p>
  <script src="<?= $h($basis) ?>assets/getraenke.js" defer></script>
<?php endif; ?>

<?php require __DIR__ . '/fuss.php'; ?>
</body>
</html>
