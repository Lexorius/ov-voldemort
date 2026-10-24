<?php
/**
 * Umfrageseite einer Abstimmung – das Ziel von QR-Code und kurzer Adresse.
 *
 * Der Server kennt die Fragen, denn er muss sie zeigen. Die Antworten kennt
 * er nicht: Sie werden im Browser für OV-Multitool verschlüsselt. Bei
 * mehreren Seiten blättert das Skript; ohne JavaScript geht es nicht, denn
 * dann ließe sich nichts verschlüsseln.
 *
 * @var ?array $umfrage  gesäuberte Angaben aus umfragen.json oder null
 * @var string $code
 */
declare(strict_types=1);

$k = con_kopplung();
$schluessel = (string)($k['ov_pubkey'] ?? '');
$basis = rtrim(strtr(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '\\', '/'), '/') . '/';
$zustand = $umfrage !== null ? con_umfrage_zustand($umfrage) : '';
$seiten = $umfrage['seiten'] ?? [];
$h = static fn(string $t): string => htmlspecialchars($t, ENT_QUOTES);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $umfrage !== null ? $h((string)$umfrage['titel']) . ' – Abstimmung' : 'Abstimmung' ?></title>
<style>
  :root { --akzent: #003399; --rand: #cbd5e1; --gut: #15803d; --schlecht: #b91c1c; }
  * { box-sizing: border-box; }
  body { margin: 0 auto; max-width: 34rem; padding: 1.2rem 1rem 3rem;
         font: 17px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color: #111827; background: #f8fafc; }
  h1 { font-size: 1.35rem; margin: .2rem 0 .4rem; }
  h2 { font-size: 1.1rem; margin: 0 0 .6rem; color: var(--akzent); }
  .karte { background: #fff; border: 1px solid var(--rand); border-radius: 12px; padding: 1rem; margin-top: 1rem; }
  .beschreibung, .seitentext { white-space: pre-wrap; }
  .fortschritt { display: flex; align-items: center; gap: .6rem; font-size: .85rem; color: #64748b; margin-top: 1rem; }
  .fortschritt .balken { flex: 1; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
  .fortschritt .balken span { display: block; height: 100%; background: var(--akzent); width: 0; transition: width .2s; }
  fieldset { border: 0; padding: 0; margin: 1rem 0 0; }
  legend { font-weight: 600; margin-bottom: .4rem; padding: 0; }
  .pflicht { color: var(--schlecht); }
  .wahl { display: block; border: 1px solid var(--rand); border-radius: 10px; padding: .7rem .8rem;
          margin-bottom: .45rem; cursor: pointer; }
  .wahl input { margin-right: .5rem; }
  .wahl:has(input:checked) { border-color: var(--akzent); box-shadow: inset 0 0 0 1px var(--akzent); }
  .skala { display: flex; gap: .35rem; flex-wrap: wrap; }
  .skala label { flex: 1 1 2.6rem; text-align: center; border: 1px solid var(--rand); border-radius: 10px;
                 padding: .6rem .2rem; cursor: pointer; min-width: 2.6rem; }
  .skala label:has(input:checked) { border-color: var(--akzent); background: #eef2ff; font-weight: 700; }
  .skala input { position: absolute; opacity: 0; width: 1px; height: 1px; }
  .skala-rand { display: flex; justify-content: space-between; font-size: .82rem; color: #64748b; margin-top: .3rem; }
  .hinweis { background: #eef2ff; border-radius: 10px; padding: .7rem .9rem; margin-top: 1rem; white-space: pre-wrap; }
  input[type=text], textarea { width: 100%; padding: .7rem; font: inherit; border: 1px solid var(--rand); border-radius: 10px; }
  textarea { min-height: 6rem; }
  textarea.kurz { min-height: 3rem; }
  .knoepfe { display: flex; gap: .6rem; margin-top: 1rem; }
  button { flex: 1; padding: .9rem 1rem; font: inherit; font-weight: 600; color: #fff; background: var(--akzent);
           border: 0; border-radius: 10px; cursor: pointer; min-height: 52px; }
  button.zurueck { background: #fff; color: var(--akzent); border: 1px solid var(--akzent); flex: 0 0 auto; }
  button[disabled] { opacity: .6; }
  .meldung { margin-top: .8rem; padding: .7rem .9rem; border-radius: 10px; background: #eef2ff; font-size: .95rem; }
  .meldung.gut { background: #f0fdf4; color: #14532d; }
  .meldung.schlecht { background: #fef2f2; color: #7f1d1d; }
  .meldung:empty { display: none; }
  .klein { font-size: .82rem; color: #64748b; }
  .seite[hidden] { display: none; }
  .fehlt legend, .fehlt > label { color: var(--schlecht); }
</style>
</head>
<body>

<?php if ($umfrage === null || $schluessel === ''): ?>
  <h1>Das klappt nicht</h1>
  <div class="karte">
    <p><?= $umfrage === null
        ? 'Diese Abstimmung gibt es nicht (mehr). Vielleicht stimmt die Adresse nicht, vielleicht wurde sie zurückgezogen.'
        : 'Der Dienst ist noch nicht eingerichtet. Bitte im Ortsverband Bescheid geben.' ?></p>
  </div>
<?php else: ?>
  <h1><?= $h((string)$umfrage['titel']) ?></h1>
  <?php if (trim((string)$umfrage['beschreibung']) !== ''): ?>
    <p class="beschreibung"><?= $h((string)$umfrage['beschreibung']) ?></p>
  <?php endif; ?>
  <p class="klein" id="zeitraum" data-beginn="<?= (int)$umfrage['beginn_ts'] ?>" data-ende="<?= (int)$umfrage['ende_ts'] ?>"></p>

  <?php if ($zustand === 'wartet'): ?>
    <div class="karte"><p>Diese Abstimmung ist noch nicht geöffnet. Bitte später noch einmal vorbeischauen.</p></div>
  <?php elseif ($zustand === 'vorbei'): ?>
    <div class="karte"><p>Diese Abstimmung ist beendet. Es werden keine Antworten mehr angenommen.</p></div>
  <?php else: ?>
    <div class="karte" id="box"
         data-code="<?= $h($code) ?>"
         data-kennung="<?= $h((string)$umfrage['kennung']) ?>"
         data-basis="<?= $h($basis) ?>"
         data-schluessel="<?= $h($schluessel) ?>"
         data-ende="<?= (int)$umfrage['ende_ts'] ?>"
         data-dank="<?= $h((string)$umfrage['dank']) ?>">
      <form id="formular" novalidate>
        <?php foreach ($seiten as $nr => $seite): ?>
          <section class="seite" data-seite="<?= (int)$nr ?>"<?= $nr > 0 ? ' hidden' : '' ?>>
            <?php if (trim((string)$seite['titel']) !== ''): ?><h2><?= $h((string)$seite['titel']) ?></h2><?php endif; ?>
            <?php if (trim((string)$seite['text']) !== ''): ?><p class="seitentext"><?= $h((string)$seite['text']) ?></p><?php endif; ?>
            <?php foreach ($seite['felder'] as $f): $name = 'f_' . $f['id']; $stern = !empty($f['pflicht']) ? ' <span class="pflicht" aria-label="Pflichtangabe">*</span>' : ''; ?>
              <?php if ($f['typ'] === 'hinweis'): ?>
                <div class="hinweis"><?= $h((string)$f['frage']) ?></div>
              <?php elseif ($f['typ'] === 'einfach' || $f['typ'] === 'mehrfach'): ?>
                <fieldset data-feld="<?= $h($f['id']) ?>" data-typ="<?= $h($f['typ']) ?>" data-pflicht="<?= (int)$f['pflicht'] ?>">
                  <legend><?= $h((string)$f['frage']) ?><?= $stern ?></legend>
                  <?php foreach ($f['optionen'] as $i => $o): ?>
                    <label class="wahl"><input type="<?= $f['typ'] === 'einfach' ? 'radio' : 'checkbox' ?>"
                      name="<?= $h($name) ?><?= $f['typ'] === 'mehrfach' ? '[]' : '' ?>" value="<?= (int)$i ?>"> <?= $h((string)$o) ?></label>
                  <?php endforeach; ?>
                </fieldset>
              <?php elseif ($f['typ'] === 'janein'): ?>
                <fieldset data-feld="<?= $h($f['id']) ?>" data-typ="janein" data-pflicht="<?= (int)$f['pflicht'] ?>">
                  <legend><?= $h((string)$f['frage']) ?><?= $stern ?></legend>
                  <label class="wahl"><input type="radio" name="<?= $h($name) ?>" value="ja"> Ja</label>
                  <label class="wahl"><input type="radio" name="<?= $h($name) ?>" value="nein"> Nein</label>
                </fieldset>
              <?php elseif ($f['typ'] === 'skala'): ?>
                <fieldset data-feld="<?= $h($f['id']) ?>" data-typ="skala" data-pflicht="<?= (int)$f['pflicht'] ?>">
                  <legend><?= $h((string)$f['frage']) ?><?= $stern ?></legend>
                  <div class="skala">
                    <?php for ($i = (int)$f['min']; $i <= (int)$f['max']; $i++): ?>
                      <label><input type="radio" name="<?= $h($name) ?>" value="<?= $i ?>"><?= $i ?></label>
                    <?php endfor; ?>
                  </div>
                  <?php if ($f['links'] !== '' || $f['rechts'] !== ''): ?>
                    <div class="skala-rand"><span><?= $h((string)$f['links']) ?></span><span><?= $h((string)$f['rechts']) ?></span></div>
                  <?php endif; ?>
                </fieldset>
              <?php elseif ($f['typ'] === 'text'): ?>
                <fieldset data-feld="<?= $h($f['id']) ?>" data-typ="text" data-pflicht="<?= (int)$f['pflicht'] ?>">
                  <legend><?= $h((string)$f['frage']) ?><?= $stern ?></legend>
                  <textarea name="<?= $h($name) ?>" maxlength="<?= !empty($f['lang']) ? 4000 : 500 ?>"
                            class="<?= !empty($f['lang']) ? '' : 'kurz' ?>" rows="<?= !empty($f['lang']) ? 5 : 2 ?>"></textarea>
                </fieldset>
              <?php endif; ?>
            <?php endforeach; ?>

            <?php if ((int)$nr === count($seiten) - 1 && (int)$umfrage['name_feld'] === 1): ?>
              <fieldset data-feld="_name" data-typ="name" data-pflicht="0">
                <legend>Dein Name <span class="klein">(freiwillig)</span></legend>
                <input type="text" name="_name" maxlength="60" autocomplete="name" placeholder="z. B. Anna">
              </fieldset>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>

        <div class="fortschritt"<?= count($seiten) < 2 ? ' hidden' : '' ?>>
          <span id="seitenzahl">Seite 1 von <?= count($seiten) ?></span>
          <div class="balken"><span id="balken"></span></div>
        </div>
        <div class="knoepfe">
          <button type="button" class="zurueck" id="zurueck" hidden>Zurück</button>
          <button type="submit" id="weiter"><?= count($seiten) > 1 ? 'Weiter' : 'Antwort senden' ?></button>
        </div>
      </form>
      <div class="meldung" id="meldung"></div>
      <noscript><p class="meldung schlecht">Ohne JavaScript geht es hier nicht – die Antwort wird im Browser
        verschlüsselt. Bitte im Ortsverband Bescheid geben.</p></noscript>
    </div>
    <p class="klein">Deine Antwort wird hier im Browser verschlüsselt. Dieser Server kann sie nicht lesen;
      er weiß nur, dass jemand geantwortet hat. Felder mit <span class="pflicht">*</span> sind Pflicht.</p>
  <?php endif; ?>
  <script src="<?= $h($basis) ?>assets/umfrage.js" defer></script>
<?php endif; ?>

<?php require __DIR__ . '/fuss.php'; ?>
</body>
</html>
