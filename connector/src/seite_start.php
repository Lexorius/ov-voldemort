<?php
/**
 * Startseite des Connectors.
 *
 * Sie verrät bewusst nichts: keine Zahlen, keine Fassung, nicht einmal, ob
 * gerade etwas wartet. Wer hier landet, hat meistens eine Einladung in der
 * Hand und nur die Adresse abgetippt – deshalb steht hier ein Feld für den
 * Einladungscode.
 *
 * Wie es dem Connector geht, fragt OV-Budget signiert ab (?p=zustand).
 * Einzige Ausnahme hier: Solange die Kopplung aussteht, steht die Anleitung
 * dafür da. Zu dem Zeitpunkt gibt es noch nichts zu verraten.
 *
 * @var bool $gekoppelt
 * @var bool $schreibbar
 */
declare(strict_types=1);

$basis = rtrim(strtr(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '\\', '/'), '/') . '/';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>Einladung öffnen</title>
<style>
  :root { --akzent: #003399; --rand: #cbd5e1; }
  * { box-sizing: border-box; }
  body { margin: 0 auto; max-width: 30rem; padding: 1.5rem 1rem 3rem;
         font: 17px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color: #111827; background: #f8fafc; }
  h1 { font-size: 1.3rem; margin: .2rem 0 .4rem; }
  .karte { background: #fff; border: 1px solid var(--rand); border-radius: 12px; padding: 1rem; margin-top: 1rem; }
  label { display: block; margin-bottom: .3rem; }
  input[type=text] { width: 100%; padding: .8rem; font: inherit; font-size: 1.3rem; letter-spacing: .12em;
         text-align: center; text-transform: uppercase; border: 1px solid var(--rand); border-radius: 10px; }
  button { display: block; width: 100%; padding: .9rem 1rem; margin: .8rem 0 0; font: inherit; font-weight: 600;
           color: #fff; background: var(--akzent); border: 0; border-radius: 10px; cursor: pointer; min-height: 52px; }
  .klein { font-size: .82rem; color: #64748b; }
  code { background: #eef2ff; padding: .1rem .3rem; border-radius: 4px; }
</style>
</head>
<body>

<h1>Einladung öffnen</h1>
<p>Auf der Einladung steht eine Adresse mit einem kurzen Code am Ende. Wer nur
   den Code zur Hand hat, kann ihn hier eintragen.</p>

<div class="karte">
  <form method="get" action="<?= htmlspecialchars($basis) ?>index.php">
    <label for="e">Einladungscode</label>
    <input type="text" id="e" name="e" required maxlength="16" autocomplete="off"
           autocapitalize="characters" autocorrect="off" spellcheck="false"
           inputmode="latin" placeholder="z. B. AB23CD">
    <button type="submit">Weiter</button>
  </form>
</div>

<p class="klein">Dieser Dienst nimmt nur entgegen, was für den Ortsverband bestimmt ist, und
   reicht es verschlüsselt weiter. Lesen kann er es nicht.</p>

<?php if (!$gekoppelt): ?>
  <div class="karte">
    <h2 style="font-size:1rem;margin:0 0 .4rem">Noch einzurichten</h2>
    <p class="klein">Dieser Connector ist noch mit keinem OV-Budget gekoppelt. Der Kopplungscode
      steht auf dem Server in <code>daten/kopplungscode.txt</code> – er wird dort in der
      Verwaltung unter „Connectoren" eingetragen.</p>
    <?php if (!$schreibbar): ?>
      <p class="klein"><strong>Achtung:</strong> Der Ordner <code>daten/</code> ist für den
        Webserver nicht beschreibbar. So kann der Connector nichts annehmen.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

</body>
</html>
