<?php
declare(strict_types=1);
/*
 * Meldungen (flash) dürfen kein fremdes HTML ausliefern.
 *
 * Bis 2.1.84.0 gab das Layout die Meldung roh aus; ein Dokument, eine
 * Kategorie oder ein Platz namens <img src=x onerror=alert(1)> lief damit als
 * Skript in der Sitzung dessen, der löschte oder verschob. Seitdem gilt:
 *
 *   flash()       reiner Text, maskiert wird bei der Ausgabe – kein e() davor
 *   flash_html()  gewolltes HTML; jede Variable in e() oder hinter (int)
 *
 * Geprüft wird beides: die Ausgabe selbst und jeder Aufruf im Quelltext.
 */
$app = dirname(__DIR__);
require $app . '/src/lib/util.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ------------------------------------------------------------------ */
/* Ausgabe                                                             */
/* ------------------------------------------------------------------ */
$_SESSION = [];
$boese = '<img src=x onerror=alert(1)>';

// So bauen die Seiten ihre Meldungen: Verkettung, sprintf, Fehlertext allein
flash('success', '„' . $boese . '" entfernt.');
flash('success', sprintf('Postfach „%s" gelöscht.', $boese));
flash('error', $boese);
flash('warn', 'Connector „x"><script>alert(1)</script>" nicht erreichbar: \'" onmouseover="alert(1)');
$aus = flash_ausgabe();
$check('Name mit <img …> kommt in keiner Meldung roh an', !str_contains($aus, '<img') && !str_contains($aus, '<script'));
$check('… sondern dreimal maskiert', substr_count($aus, '&lt;img src=x onerror=alert(1)&gt;') === 3);
$check('Anführungszeichen maskiert', !str_contains($aus, '" onmouseover=') && !str_contains($aus, "'\""));
$check('vier Meldungen, sonst kein Element', substr_count($aus, '<div class="alert alert--') === 4 && substr_count($aus, '<') === 8);

flash('info', 'Müller & Söhne – 3 < 5');
$aus = flash_ausgabe();
$check('einmal maskiert, nicht doppelt', str_contains($aus, 'Müller &amp; Söhne – 3 &lt; 5') && !str_contains($aus, '&amp;amp;') && !str_contains($aus, '&amp;lt;'));
$check('Umlaute und Gedankenstrich bleiben', str_contains($aus, 'Müller') && str_contains($aus, '–'));

flash_html('success', 'Aufgabe angelegt: <a href="' . e('/todo?id=7&x=1') . '">' . e($boese) . '</a>');
$aus = flash_ausgabe();
$check('flash_html: gewolltes HTML bleibt', str_contains($aus, '<a href="/todo?id=7&amp;x=1">') && str_contains($aus, '</a>'));
$check('flash_html: der Name darin ist maskiert', !str_contains($aus, '<img') && str_contains($aus, '&lt;img'));

flash('"><script>alert(1)</script>', 'Text');
$aus = flash_ausgabe();
$check('Art der Meldung maskiert', !str_contains($aus, '<script'));

// Einträge, die nicht über flash_html() kamen, gelten als Text – auch alte ohne Kennzeichen
$_SESSION['_flash'] = [
    ['type' => 'success', 'msg' => $boese],
    ['type' => 'success', 'msg' => $boese, 'html' => 1],
    ['type' => 'success', 'msg' => $boese, 'html' => 'true'],
    ['msg' => $boese],
];
$aus = flash_ausgabe();
$check('Eintrag ohne oder mit unechtem Kennzeichen wird maskiert', !str_contains($aus, '<img') && substr_count($aus, '&lt;img') === 4);
$check('nach der Ausgabe ist die Liste leer', flash_ausgabe() === '' && !isset($_SESSION['_flash']));

/* ------------------------------------------------------------------ */
/* Quelltext                                                           */
/* ------------------------------------------------------------------ */
/** Nächstes bedeutsames Token (ohne Leerraum und Kommentare), -1 am Rand */
function flash_nachbar(array $t, int $i, int $richtung): int
{
    for ($i += $richtung; $i >= 0 && $i < count($t); $i += $richtung) {
        if (!(is_array($t[$i]) && in_array($t[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))) {
            return $i;
        }
    }
    return -1;
}

/** Schließende Klammer zur öffnenden an Stelle $auf */
function flash_klammer_zu(array $t, int $auf): int
{
    $tiefe = 0;
    for ($k = $auf; $k < count($t); $k++) {
        if ($t[$k] === '(') { $tiefe++; }
        if ($t[$k] === ')' && --$tiefe === 0) { return $k; }
    }
    return count($t) - 1;
}

/**
 * Mängel an den flash()- und flash_html()-Aufrufen eines Quelltexts:
 * je Mangel eine Zeile "Zeile N: …". Zweiter Rückgabewert: Zahl der Aufrufe.
 */
function flash_maengel(string $quelle): array
{
    $t = token_get_all($quelle);
    $maengel = []; $aufrufe = 0;
    foreach ($t as $i => $tok) {
        if (!is_array($tok) || $tok[0] !== T_STRING || !in_array($tok[1], ['flash', 'flash_html'], true)) {
            continue;
        }
        $v = flash_nachbar($t, $i, -1);
        if ($v >= 0 && is_array($t[$v]) && in_array($t[$v][0], [T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
            continue;   // die Definition selbst oder eine Methode
        }
        $auf = flash_nachbar($t, $i, 1);
        if ($auf < 0 || $t[$auf] !== '(') {
            continue;
        }
        $ende = flash_klammer_zu($t, $auf);
        $zeile = 'Zeile ' . $tok[2] . ': ';
        $aufrufe++;

        if ($tok[1] === 'flash') {
            for ($k = $auf + 1; $k < $ende; $k++) {
                $x = $t[$k];
                if (!is_array($x)) { continue; }
                $n = flash_nachbar($t, $k, 1);
                if ($x[0] === T_STRING && in_array(strtolower($x[1]), ['e', 'htmlspecialchars', 'htmlentities'], true) && $t[$n] === '(') {
                    $maengel[] = $zeile . 'flash() mit ' . $x[1] . '() – wird bei der Ausgabe noch einmal maskiert';
                }
                if (in_array($x[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)
                    && preg_match('~<[a-z/!]|&#?[a-z0-9]+;~i', $x[1])) {
                    $maengel[] = $zeile . 'flash() mit HTML im Text – dafür ist flash_html() da';
                }
            }
            continue;
        }

        // flash_html(): ab dem zweiten Argument darf nichts Veränderliches unmaskiert stehen
        $tiefe = 0; $start = $ende;
        for ($k = $auf + 1; $k < $ende; $k++) {
            if (in_array($t[$k], ['(', '[', '{'], true)) { $tiefe++; }
            if (in_array($t[$k], [')', ']', '}'], true)) { $tiefe--; }
            if ($t[$k] === ',' && $tiefe === 0) { $start = $k + 1; break; }
        }
        for ($k = $start; $k < $ende; $k++) {
            $x = $t[$k];
            if (!is_array($x)) {
                if ($x === '`') { $maengel[] = $zeile . 'flash_html() mit Shell-Aufruf'; }
                continue;
            }
            if ($x[0] === T_STRING) {
                $n = flash_nachbar($t, $k, 1);
                $name = strtolower($x[1]);
                if ($name === 'e' && $t[$n] === '(') {
                    $k = flash_klammer_zu($t, $n);   // was in e() steht, ist maskiert
                    continue;
                }
                if (!in_array($name, ['sprintf', 'true', 'false', 'null'], true)) {
                    $maengel[] = $zeile . 'flash_html() mit ' . $x[1] . ' außerhalb von e()';
                }
                continue;
            }
            if ($x[0] === T_VARIABLE) {
                $v = flash_nachbar($t, $k, -1);
                if (!(is_array($t[$v]) && $t[$v][0] === T_INT_CAST)) {
                    $maengel[] = $zeile . 'flash_html() mit ' . $x[1] . ' außerhalb von e() und ohne (int)';
                }
            }
        }
    }
    return [$maengel, $aufrufe];
}

/* Der Prüfer selbst: findet er, was er finden soll? */
$probe = static fn(string $code): array => flash_maengel("<?php\n" . $code)[0];
$check('Prüfer: schlichter Text ist in Ordnung', $probe('flash("success", "„" . $name . "“ entfernt.");') === []);
$check('Prüfer: sprintf mit Namen ist in Ordnung', $probe("flash(\$f ? 'error' : 'success', \$f ?? sprintf('„%s\" gelöscht.', (string)\$s['name']));") === []);
$check('Prüfer: e() in flash() fällt auf', count($probe("flash('error', e(\$f));")) === 1);
$check('Prüfer: e() tief in flash() fällt auf', count($probe("flash('ok', sprintf('„%s\"', e(\$titel)));")) === 1);
$check('Prüfer: HTML in flash() fällt auf', count($probe("flash('ok', 'Passwort: <strong>' . \$neu . '</strong>');")) === 2);
$check('Prüfer: Entität in flash() fällt auf', count($probe("flash('ok', 'A&nbsp;B');")) === 1);
$check('Prüfer: flash_html mit e() und (int) ist in Ordnung',
    $probe("flash_html(\$n > 0 ? 'warn' : 'success', sprintf('<strong>%d</strong> %s <a href=\"%s\">x</a>', (int)\$r['neu'], e(\$name), e(url('todo', ['id' => \$id]))));") === []);
$check('Prüfer: rohe Variable in flash_html fällt auf', count($probe("flash_html('ok', '<b>' . \$name . '</b>');")) === 1);
$check('Prüfer: rohe Variable neben e() fällt auf', count($probe("flash_html('ok', '<b>' . e(\$a) . \$b['name'] . '</b>');")) === 1);
$check('Prüfer: Variable im doppelten Anführungszeichen fällt auf', count($probe('flash_html("ok", "<b>$name</b>");')) === 1);
$check('Prüfer: fremde Funktion in flash_html fällt auf', count($probe("flash_html('ok', '<b>' . contact_name(\$c) . '</b>');")) === 2);
$check('Prüfer: (string) reicht nicht', count($probe("flash_html('ok', '<b>' . (string)\$s['name'] . '</b>');")) === 1);
$check('Prüfer: Definition und Methode zählen nicht', flash_maengel("<?php function flash(string \$a, string \$b): void {} \$x->flash('a', e(\$b));")[1] === 0);

/* Alle Seiten, Bibliotheken und Ansichten */
$maengel = []; $aufrufe = 0; $html = 0; $fremd = [];
foreach (['/src', '/views', '/public'] as $ordner) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app . $ordner, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || $f->getExtension() !== 'php') {
            continue;
        }
        $kurz = ltrim(strtr(substr($f->getPathname(), strlen($app)), '\\', '/'), '/');
        $quelle = (string)file_get_contents($f->getPathname());
        [$m, $n] = flash_maengel($quelle);
        $aufrufe += $n;
        $html += preg_match_all('/(?<![a-z0-9_>$])flash_html\(/i', $quelle);
        foreach ($m as $text) {
            $maengel[] = $kurz . ' ' . $text;
        }
        // Am maskierenden Ausgang vorbei kommt nur, wer die Liste selbst anfasst
        if ($kurz !== 'src/lib/util.php' && (str_contains($quelle, '_flash') || str_contains($quelle, 'flash_take('))) {
            $fremd[] = $kurz;
        }
    }
}
$check('Aufrufe gefunden', $aufrufe > 300);
$check("alle Aufrufe sauber:\n  " . implode("\n  ", $maengel), $maengel === []);
$check('flash_html() bleibt die Ausnahme (' . $html . ' Aufrufe)', $html >= 1 && $html <= 10);
$check('niemand greift an flash_ausgabe() vorbei auf die Meldungen zu: ' . implode(', ', $fremd), $fremd === []);

$layout = (string)file_get_contents($app . '/views/layout.php');
$check('Layout gibt die Meldungen über flash_ausgabe() aus', substr_count($layout, 'flash_ausgabe()') === 1 && !str_contains($layout, "['msg']"));
$util = (string)file_get_contents($app . '/src/lib/util.php');
$check('flash() kennzeichnet nie als HTML',
    (bool)preg_match('/function flash\(string \$type, string \$message\): void\s*\{\s*\$_SESSION\[\'_flash\'\]\[\] = \[\'type\' => \$type, \'msg\' => \$message, \'html\' => false\];\s*\}/', $util));

echo "$ok bestanden, $fail fehlgeschlagen\n";
