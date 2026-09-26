<?php
/**
 * Seite am Zähler – das Ziel des QR-Codes an Strom-, Gas- oder Wasserzähler.
 *
 * Welcher Zähler es ist, weiß der Server nicht: Name und Nummer stehen im
 * Anker der Adresse (hinter dem #) und werden vom Browser nie mitgeschickt.
 * Bekannt ist hier nur die Einheit – sonst ließe sich „kWh" nicht schreiben.
 *
 * @var ?array $eintrag  ['kennung' => …, 'einheit' => 'kWh', 'art' => 'strom'] oder null
 * @var string $token
 */
declare(strict_types=1);

$k = con_kopplung();
$schluessel = (string)($k['ov_pubkey'] ?? '');
$basis = rtrim(strtr(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '\\', '/'), '/') . '/';
$einheit = (string)($eintrag['einheit'] ?? '');
$art = ['strom' => 'Stromzähler', 'gas' => 'Gaszähler', 'wasser' => 'Wasserzähler'][$eintrag['art'] ?? ''] ?? 'Zähler';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>Zählerstand melden</title>
<style>
  :root { --akzent: #003399; --rand: #cbd5e1; --gut: #15803d; --schlecht: #b91c1c; }
  * { box-sizing: border-box; }
  body { margin: 0 auto; max-width: 30rem; padding: 1.2rem 1rem 3rem;
         font: 17px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color: #111827; background: #f8fafc; }
  h1 { font-size: 1.25rem; margin: .2rem 0 .1rem; }
  .was { font-size: 1.05rem; font-weight: 700; color: var(--akzent); }
  .karte { background: #fff; border: 1px solid var(--rand); border-radius: 12px; padding: 1rem; margin-top: 1rem; }
  button { display: block; width: 100%; padding: .9rem 1rem; margin: .8rem 0 0; font: inherit; font-weight: 600;
           color: #fff; background: var(--akzent); border: 0; border-radius: 10px; cursor: pointer; min-height: 52px; }
  button[disabled] { opacity: .6; }
  label { display: block; font-size: .9rem; color: #475569; margin-bottom: .2rem; }
  input[type=text] { width: 100%; padding: .7rem; font: inherit; border: 1px solid var(--rand); border-radius: 10px; }
  input.stand { font-size: 1.6rem; font-variant-numeric: tabular-nums; text-align: right; padding-right: 3.2rem; }
  .stand-wrap { position: relative; }
  .stand-wrap .einheit { position: absolute; right: .8rem; top: 50%; transform: translateY(-50%); color: #64748b; }
  .feld { margin-top: .8rem; }
  .meldung { margin-top: .8rem; padding: .7rem .9rem; border-radius: 10px; background: #eef2ff; font-size: .95rem; }
  .meldung.gut { background: #f0fdf4; color: #14532d; }
  .meldung.schlecht { background: #fef2f2; color: #7f1d1d; }
  .klein { font-size: .82rem; color: #64748b; }
</style>
</head>
<body>

<?php if ($eintrag === null || $schluessel === ''): ?>
  <h1>Das klappt nicht</h1>
  <div class="karte">
    <p><?= $eintrag === null
        ? 'Dieser QR-Code gehört zu keinem Zähler (mehr). Vermutlich wurde er zurückgezogen.'
        : 'Der Dienst ist noch nicht eingerichtet. Bitte im Ortsverband Bescheid geben.' ?></p>
  </div>
<?php else: ?>
  <h1>Zählerstand melden</h1>
  <div class="was" id="was"><?= htmlspecialchars($art) ?></div>
  <p class="klein">Den Stand so eintippen, wie er auf dem Zähler steht – mit Nachkommastellen, wenn es welche gibt.
    Die Meldung wird hier im Browser verschlüsselt; der Server kann sie nicht lesen.</p>

  <div class="karte" id="box"
       data-token="<?= htmlspecialchars($token) ?>"
       data-basis="<?= htmlspecialchars($basis) ?>"
       data-schluessel="<?= htmlspecialchars($schluessel) ?>">
    <div class="feld">
      <label for="stand">Zählerstand</label>
      <div class="stand-wrap">
        <input type="text" inputmode="decimal" id="stand" class="stand" autocomplete="off" placeholder="0">
        <span class="einheit"><?= htmlspecialchars($einheit) ?></span>
      </div>
    </div>
    <div class="feld">
      <label for="melder">Dein Name <span class="klein">(freiwillig, wird auf diesem Gerät gemerkt)</span></label>
      <input type="text" id="melder" maxlength="60" autocomplete="name" placeholder="z. B. Anna">
    </div>
    <button type="button" id="senden">Stand melden</button>
    <div class="meldung" id="meldung">Bereit.</div>
    <noscript><p class="meldung schlecht">Ohne JavaScript geht es hier nicht – die Meldung wird
      im Browser verschlüsselt.</p></noscript>
  </div>
  <p class="klein">Gemeldet werden nur der Stand, der Zeitpunkt und – wenn eingetragen – der Name.</p>
  <script src="<?= htmlspecialchars($basis) ?>assets/zaehler.js" defer></script>
<?php endif; ?>

</body>
</html>
