<?php
/**
 * Einladungsseite – das Ziel der kurzen Adresse (z. B. https://i.example.de/AB23CD).
 *
 * Der Server weiß, zu welcher Veranstaltung der Code gehört, aber nicht, wer
 * ihn bekommen hat: Von den Codes liegen hier nur Prüfsummen, Namen gar nicht.
 * Die Rückmeldung wird im Browser für OV-Budget verschlüsselt.
 *
 * @var ?array $einladung  ['kennung' => …, 'veranstaltung' => […]] oder null
 * @var string $code
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');

$k = con_kopplung();
$schluessel = (string)($k['ov_pubkey'] ?? '');
$v = $einladung['veranstaltung'] ?? null;
$vorbei = $v !== null && con_frist_vorbei($v);
// Die kurze Adresse (/AB23CD) hat kein Verzeichnis – Pfade deshalb selbst bilden
$basis = rtrim(strtr(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '\\', '/'), '/') . '/';
$abgesagt = $v !== null && in_array((string)$v['status'], ['abgesagt', 'abgeschlossen'], true);

/** "Sa., 5. Dezember 2026, 18:00 Uhr" – ohne intl, die Namen stehen hier */
$zeitpunkt = static function (string $wert, bool $mitZeit = true): string {
    $t = strtotime($wert);
    if ($t === false) {
        return '';
    }
    $tage = ['So.', 'Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.'];
    $monate = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
               'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    $text = sprintf('%s %d. %s %s', $tage[(int)date('w', $t)], (int)date('j', $t),
        $monate[(int)date('n', $t)], date('Y', $t));
    if ($mitZeit && date('H:i', $t) !== '00:00') {
        $text .= ', ' . date('H:i', $t) . ' Uhr';
    }
    return $text;
};
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $v !== null ? htmlspecialchars((string)$v['titel']) . ' – Einladung' : 'Einladung' ?></title>
<style>
  :root { --akzent: #003399; --rand: #cbd5e1; --gut: #15803d; --schlecht: #b91c1c; }
  * { box-sizing: border-box; }
  body { margin: 0 auto; max-width: 32rem; padding: 1.2rem 1rem 3rem;
         font: 17px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color: #111827; background: #f8fafc; }
  h1 { font-size: 1.35rem; margin: .2rem 0 .4rem; }
  .wann { font-size: 1.02rem; color: var(--akzent); font-weight: 700; }
  .karte { background: #fff; border: 1px solid var(--rand); border-radius: 12px; padding: 1rem; margin-top: 1rem; }
  .hinweis { white-space: pre-wrap; }
  button { display: block; width: 100%; padding: .9rem 1rem; margin: .8rem 0 0; font: inherit; font-weight: 600;
           color: #fff; background: var(--akzent); border: 0; border-radius: 10px; cursor: pointer; min-height: 52px; }
  button[disabled] { opacity: .6; }
  label { display: block; margin-bottom: .2rem; }
  .wahl { display: block; border: 1px solid var(--rand); border-radius: 10px; padding: .75rem .8rem;
          margin-bottom: .5rem; cursor: pointer; }
  .wahl input { margin-right: .5rem; }
  .wahl:has(input:checked) { border-color: var(--akzent); box-shadow: inset 0 0 0 1px var(--akzent); }
  input[type=text], select, textarea { width: 100%; padding: .7rem; font: inherit;
          border: 1px solid var(--rand); border-radius: 10px; }
  textarea { min-height: 5rem; }
  .feld { margin-top: .8rem; }
  .meldung { margin-top: .8rem; padding: .7rem .9rem; border-radius: 10px; background: #eef2ff; font-size: .95rem; }
  .meldung.gut { background: #f0fdf4; color: #14532d; }
  .meldung.schlecht { background: #fef2f2; color: #7f1d1d; }
  .klein { font-size: .82rem; color: #64748b; }
</style>
</head>
<body>

<?php if ($v === null || $schluessel === ''): ?>
  <h1>Das klappt nicht</h1>
  <div class="karte">
    <p><?= $v === null
        ? 'Diese Einladung gilt nicht (mehr). Vielleicht stimmt die Adresse nicht, vielleicht wurde der Code zurückgezogen.'
        : 'Der Dienst ist noch nicht eingerichtet. Bitte im Ortsverband Bescheid geben.' ?></p>
  </div>
<?php else: ?>
  <h1><?= htmlspecialchars((string)$v['titel']) ?></h1>
  <div class="wann"><?= htmlspecialchars($zeitpunkt((string)$v['beginn'])) ?>
    <?php if (trim((string)$v['ende']) !== ''): ?>
      <?php $bis = $zeitpunkt((string)$v['ende']); ?>
      <?= $bis !== '' ? '<br>bis ' . htmlspecialchars($bis) : '' ?>
    <?php endif; ?>
  </div>
  <?php if (trim((string)$v['ort']) !== ''): ?>
    <p><?= htmlspecialchars((string)$v['ort']) ?></p>
  <?php endif; ?>
  <?php if (trim((string)$v['hinweis']) !== ''): ?>
    <p class="hinweis"><?= htmlspecialchars((string)$v['hinweis']) ?></p>
  <?php endif; ?>

  <?php if ($abgesagt): ?>
    <div class="karte"><p>Diese Veranstaltung findet nicht statt. Eine Rückmeldung ist nicht mehr nötig.</p></div>
  <?php elseif ($vorbei): ?>
    <div class="karte">
      <p>Die Rückmeldefrist ist am <?= htmlspecialchars($zeitpunkt((string)$v['bis'], false)) ?> abgelaufen.
        Bitte wenden Sie sich direkt an den Ortsverband.</p>
    </div>
  <?php else: ?>
    <div class="karte" id="box"
         data-code="<?= htmlspecialchars($code) ?>"
         data-basis="<?= htmlspecialchars($basis) ?>"
         data-schluessel="<?= htmlspecialchars($schluessel) ?>"
         data-begleiter="<?= (int)$v['begleiter_max'] ?>">
      <form id="formular">
        <label class="wahl"><input type="radio" name="status" value="zusage" checked>
          Ich nehme teil</label>
        <?php if ((int)$v['vertretung'] === 1): ?>
          <label class="wahl"><input type="radio" name="status" value="vertretung">
            Ich kann nicht, es kommt jemand für mich</label>
        <?php endif; ?>
        <label class="wahl"><input type="radio" name="status" value="absage">
          Ich kann leider nicht teilnehmen</label>

        <?php if ((int)$v['begleiter_max'] > 0): ?>
          <div class="feld" id="feld-begleiter">
            <label for="begleiter">Mit wie vielen Begleitern kommen Sie?</label>
            <select id="begleiter" name="begleiter">
              <?php for ($i = 0; $i <= (int)$v['begleiter_max']; $i++): ?>
                <option value="<?= $i ?>"><?= $i === 0 ? 'ohne Begleitung' : $i ?></option>
              <?php endfor; ?>
            </select>
          </div>
        <?php endif; ?>

        <?php if ((int)$v['vertretung'] === 1): ?>
          <div class="feld" id="feld-vertretung" hidden>
            <label for="vertretung">Wer kommt für Sie?</label>
            <input type="text" id="vertretung" name="vertretung" maxlength="150" placeholder="z. B. Frau Muster">
          </div>
        <?php endif; ?>

        <?php if ((int)$v['kommentare'] === 1): ?>
          <div class="feld">
            <label for="kommentar">Nachricht <span class="klein">(freiwillig)</span></label>
            <textarea id="kommentar" name="kommentar" maxlength="2000"
                      placeholder="z. B. Ich komme etwas später"></textarea>
          </div>
        <?php endif; ?>

        <button type="submit" id="senden">Rückmeldung senden</button>
      </form>
      <div class="meldung" id="meldung">Bitte wählen Sie aus, ob Sie kommen.</div>
    </div>
    <p class="klein">Ihre Rückmeldung wird hier im Browser verschlüsselt. Dieser Server kann sie nicht
      lesen und weiß auch nicht, wer Sie sind – er kennt nur diese Einladung.
      <?php if (trim((string)$v['bis']) !== ''): ?>
        Rückmeldung bitte bis zum <?= htmlspecialchars($zeitpunkt((string)$v['bis'], false)) ?>.
      <?php endif; ?>
    </p>
    <script src="<?= htmlspecialchars($basis) ?>assets/einladung.js" defer></script>
  <?php endif; ?>
<?php endif; ?>

</body>
</html>
