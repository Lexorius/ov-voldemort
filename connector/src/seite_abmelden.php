<?php
/**
 * Abmeldeseite des Newsletters – das Ziel des Abmeldelinks in jeder Mail.
 *
 * Der Server kennt nur die Kennung aus dem Link, nicht die Adresse. Ein
 * Klick auf „Abmelden" legt die Kennung ab; OV-Multitool holt sie und setzt
 * die zugehörige Adresse auf seine Sperrliste. Ohne JavaScript, nur ein
 * Formular – das muss auch auf dem ältesten Handy gehen.
 *
 * @var string $kennung
 * @var bool   $erledigt
 * @var string $fehlerText
 */
declare(strict_types=1);

$basis = rtrim(strtr(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '\\', '/'), '/') . '/';
$gueltig = con_abmeldung_gueltig($kennung);
$h = static fn(string $t): string => htmlspecialchars($t, ENT_QUOTES);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>Newsletter abbestellen</title>
<style>
  :root { --akzent: #003399; --rand: #cbd5e1; }
  * { box-sizing: border-box; }
  body { margin: 0 auto; max-width: 30rem; padding: 1.5rem 1rem 3rem;
         font: 17px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color: #111827; background: #f8fafc; }
  h1 { font-size: 1.3rem; margin: .2rem 0 .4rem; }
  .karte { background: #fff; border: 1px solid var(--rand); border-radius: 12px; padding: 1rem; margin-top: 1rem; }
  button { display: block; width: 100%; padding: .9rem 1rem; margin: .8rem 0 0; font: inherit; font-weight: 600;
           color: #fff; background: var(--akzent); border: 0; border-radius: 10px; cursor: pointer; min-height: 52px; }
  .meldung { padding: .7rem .9rem; border-radius: 10px; background: #eef2ff; }
  .meldung.gut { background: #f0fdf4; color: #14532d; }
  .meldung.schlecht { background: #fef2f2; color: #7f1d1d; }
  .klein { font-size: .82rem; color: #64748b; }
</style>
</head>
<body>

<h1>Newsletter abbestellen</h1>

<?php if ($erledigt): ?>
  <div class="karte"><p class="meldung gut">Erledigt. Diese Adresse bekommt keinen Newsletter mehr; die Sperre greift beim
    nächsten Abgleich, spätestens vor dem nächsten Versand.</p></div>
<?php elseif (!$gueltig): ?>
  <div class="karte"><p class="meldung schlecht">Dieser Abmeldelink ist nicht vollständig. Bitte den Link aus der E-Mail
    ganz kopieren oder im Ortsverband Bescheid geben.</p></div>
<?php else: ?>
  <div class="karte">
    <p>Du möchtest den Newsletter nicht mehr bekommen? Dann bestätige das hier – ohne Anmeldung, ohne Angabe
      deiner Adresse.</p>
    <?php if ($fehlerText !== ''): ?><p class="meldung schlecht"><?= $h($fehlerText) ?></p><?php endif; ?>
    <form method="post" action="<?= $h($basis) ?>index.php?p=abmelden">
      <input type="hidden" name="a" value="<?= $h($kennung) ?>">
      <button type="submit">Abmelden</button>
    </form>
  </div>
  <p class="klein">Dieser Server kennt deine Adresse nicht – nur die Kennung aus dem Link. Erst der Ortsverband ordnet sie
    seiner Sperrliste zu.</p>
<?php endif; ?>

<?php require __DIR__ . '/fuss.php'; ?>
</body>
</html>
