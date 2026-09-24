<?php
/**
 * Seite am Lagerort – das Ziel des QR-Codes an einem Funkgerät oder an
 * einem Koffer.
 *
 * Was da hängt, weiß der Server nicht: Die Bezeichnung steht im Anker der
 * Adresse (hinter dem #) und wird vom Browser nie mitgeschickt. Bekannt ist
 * hier nur, ob es ein einzelnes Gerät oder eine Gruppe ist und wie viele
 * Geräte dazugehören – sonst ließe sich „alle 8 Geräte" nicht schreiben.
 *
 * @var ?array $eintrag  ['kennung' => …, 'art' => 'geraet'|'gruppe', 'anzahl' => int]
 * @var string $token
 */
declare(strict_types=1);

$k = con_kopplung();
$schluessel = (string)($k['ov_pubkey'] ?? '');
$basis = rtrim(strtr(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '\\', '/'), '/') . '/';
$gruppe = $eintrag !== null && $eintrag['art'] === 'gruppe';
$anzahl = (int)($eintrag['anzahl'] ?? 0);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>Am Lagerort melden</title>
<style>
  :root { --akzent: #003399; --rand: #cbd5e1; --gut: #15803d; --schlecht: #b91c1c; }
  * { box-sizing: border-box; }
  body { margin: 0 auto; max-width: 30rem; padding: 1.2rem 1rem 3rem;
         font: 17px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color: #111827; background: #f8fafc; }
  h1 { font-size: 1.25rem; margin: .2rem 0 .1rem; }
  .was { font-size: 1.05rem; font-weight: 700; color: var(--akzent); }
  .karte { background: #fff; border: 1px solid var(--rand); border-radius: 12px; padding: 1rem; margin-top: 1rem; }
  button { display: block; width: 100%; padding: .9rem 1rem; margin: .5rem 0 0; font: inherit; font-weight: 600;
           color: #fff; background: var(--akzent); border: 0; border-radius: 10px; cursor: pointer; min-height: 52px; }
  button.zweit { background: #fff; color: var(--akzent); border: 2px solid var(--akzent); }
  button[disabled] { opacity: .6; }
  label { display: block; font-size: .9rem; color: #475569; margin-bottom: .2rem; }
  input[type=text], input[type=number] { width: 100%; padding: .7rem; font: inherit;
           border: 1px solid var(--rand); border-radius: 10px; }
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
        ? 'Dieser QR-Code gehört zu keinem Gerät (mehr). Vermutlich wurde er zurückgezogen.'
        : 'Der Dienst ist noch nicht eingerichtet. Bitte im Ortsverband Bescheid geben.' ?></p>
  </div>
<?php else: ?>
  <h1>Am Lagerort melden</h1>
  <div class="was" id="was"><?= $gruppe ? 'Gruppe' : 'Funkgerät' ?></div>
  <p class="klein">Ein Tipp genügt: Damit ist festgehalten, dass
    <?= $gruppe ? 'die Geräte' : 'das Gerät' ?> am Lagerort <?= $gruppe ? 'sind' : 'ist' ?>.
    Die Meldung wird hier im Browser verschlüsselt; der Server kann sie nicht lesen.</p>

  <div class="karte" id="box"
       data-token="<?= htmlspecialchars($token) ?>"
       data-basis="<?= htmlspecialchars($basis) ?>"
       data-schluessel="<?= htmlspecialchars($schluessel) ?>"
       data-gruppe="<?= $gruppe ? '1' : '' ?>"
       data-anzahl="<?= $anzahl ?>">

    <div class="feld">
      <label for="melder">Dein Name <span class="klein">(freiwillig, wird auf diesem Gerät gemerkt)</span></label>
      <input type="text" id="melder" maxlength="60" autocomplete="name" placeholder="z. B. Anna">
    </div>

    <button type="button" id="alle">
      <?= $gruppe
          ? ($anzahl > 0 ? 'Alle ' . $anzahl . ' Geräte sind da' : 'Alle Geräte sind da')
          : 'Gerät ist am Lagerort' ?></button>

    <?php if ($gruppe): ?>
      <button type="button" class="zweit" id="teilweise">Nicht alle – Anzahl eintragen</button>
      <div class="feld" id="feld-anzahl" hidden>
        <label for="anzahl">Wie viele sind da?</label>
        <input type="number" id="anzahl" min="0" max="<?= max(1, $anzahl) ?>" value="<?= $anzahl ?>">
        <button type="button" id="senden-anzahl">Diese Anzahl melden</button>
      </div>
    <?php endif; ?>

    <div class="meldung" id="meldung">Bereit.</div>
    <noscript><p class="meldung schlecht">Ohne JavaScript geht es hier nicht – die Meldung wird
      im Browser verschlüsselt.</p></noscript>
  </div>
  <p class="klein">Gemeldet wird nur, dass hier jemand nachgesehen hat – mit Zeitpunkt und,
    wenn eingetragen, dem Namen.</p>
  <script src="<?= htmlspecialchars($basis) ?>assets/bestand.js" defer></script>
<?php endif; ?>

</body>
</html>
