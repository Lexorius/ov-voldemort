<?php
/**
 * Seite für das Handy – das Ziel des QR-Codes im Fahrzeug.
 * @var ?array $fz     Fahrzeug zum Zugang (null = ungültig)
 * @var string $token
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
$k = con_kopplung();
$schluessel = (string)($k['ov_pubkey'] ?? '');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>Standort melden</title>
<style>
  :root { --akzent: #003399; --rand: #cbd5e1; --gut: #15803d; --schlecht: #b91c1c; }
  * { box-sizing: border-box; }
  body { margin: 0 auto; max-width: 30rem; padding: 1.2rem 1rem 3rem;
         font: 17px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color: #111827; background: #f8fafc; }
  h1 { font-size: 1.25rem; margin: .2rem 0 .1rem; }
  .fz { font-size: 1.05rem; font-weight: 700; color: var(--akzent); }
  .karte { background: #fff; border: 1px solid var(--rand); border-radius: 12px; padding: 1rem; margin-top: 1rem; }
  button { display: block; width: 100%; padding: .9rem 1rem; margin: .5rem 0 0; font: inherit; font-weight: 600;
           color: #fff; background: var(--akzent); border: 0; border-radius: 10px; cursor: pointer; min-height: 52px; }
  button.zweit { background: #fff; color: var(--akzent); border: 2px solid var(--akzent); }
  button[disabled] { opacity: .6; }
  label { display: block; font-size: .9rem; color: #475569; margin-bottom: .2rem; }
  input[type=text] { width: 100%; padding: .7rem; font: inherit; border: 1px solid var(--rand); border-radius: 10px; }
  .meldung { margin-top: .8rem; padding: .7rem .9rem; border-radius: 10px; background: #eef2ff; font-size: .95rem; }
  .meldung.gut { background: #f0fdf4; color: #14532d; }
  .meldung.schlecht { background: #fef2f2; color: #7f1d1d; }
  .klein { font-size: .82rem; color: #64748b; }
  .zaehler { font-variant-numeric: tabular-nums; }
</style>
</head>
<body>

<?php if ($fz === null || $schluessel === ''): ?>
  <h1>Das klappt nicht</h1>
  <div class="karte">
    <p><?= $fz === null
        ? 'Dieser QR-Code gehört zu keinem Fahrzeug (mehr). Vermutlich wurde er zurückgezogen.'
        : 'Der Dienst ist noch nicht eingerichtet. Bitte im Ortsverband Bescheid geben.' ?></p>
  </div>
<?php else: ?>
  <h1>Standort melden</h1>
  <div class="fz"><?= htmlspecialchars($fz['name'] ?: 'Fahrzeug') ?><?php
    if ($fz['kennzeichen'] !== ''): ?> · <?= htmlspecialchars($fz['kennzeichen']) ?><?php endif; ?></div>
  <p class="klein">Dein Gerät übermittelt seinen Standort als Standort dieses Fahrzeugs. Die Angabe wird
    hier im Browser verschlüsselt; der Server kann sie nicht lesen. Es läuft nur, solange diese Seite offen ist.</p>

  <div class="karte" id="box"
       data-token="<?= htmlspecialchars($token) ?>"
       data-schluessel="<?= htmlspecialchars($schluessel) ?>">
    <label for="melder">Dein Name <span class="klein">(freiwillig, wird auf diesem Gerät gemerkt)</span></label>
    <input type="text" id="melder" maxlength="60" autocomplete="name" placeholder="z. B. Anna">

    <button type="button" id="einmal">Einmal senden</button>
    <button type="button" class="zweit" id="dauer60">Alle 60 Sekunden senden</button>
    <button type="button" class="zweit" id="dauer500">Alle 500 Sekunden senden</button>
    <button type="button" class="zweit" id="stopp" hidden>Senden beenden</button>

    <div class="meldung" id="meldung">Bereit.</div>
  </div>
  <p class="klein">Bleibt die Seite offen, sendet sie im gewählten Takt weiter. Schließen beendet es sofort.</p>
  <script src="assets/melden.js" defer></script>
<?php endif; ?>

</body>
</html>
