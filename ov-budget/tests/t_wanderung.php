<?php
declare(strict_types=1);
/*
 * Die Wanderungen laufen ohne bootstrap.php – jede Funktion, die sie
 * aufrufen, muss entweder eingebaut sein oder von einer Datei stammen,
 * die migrate.php selbst lädt. (1.25.0 rief phone_human() aus contacts.php,
 * die Funktion war aber nach util.php umgezogen: Start schlug fehl.)
 */
$app = dirname(__DIR__);
define('APP_ROOT', $app);
$quelle = (string)file_get_contents($app . '/src/cli/migrate.php');

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

// Nur laden, was migrate.php selbst lädt
preg_match_all("#require(?:_once)?\s+APP_ROOT\s*\.\s*'([^']+)'#", $quelle, $m);
foreach ($m[1] as $datei) {
    require_once $app . $datei;
}
$check('Rufnummern-Hilfe geladen', function_exists('phone_human'));
$check('Rufnummer ohne Einstellungen', function_exists('phone_human')
    && phone_human('0151 12345678', '+49') === '+49 151 12345678');

// Alle Aufrufe einsammeln – über den PHP-Zerleger, damit Text und
// Kommentare (etwa SQL mit "VALUES(") nicht mitgezählt werden
$token = token_get_all($quelle);
$aufrufe = [];
$eigene = [];
foreach ($token as $i => $t) {
    if (!is_array($t) || $t[0] !== T_STRING) {
        continue;
    }
    // Name der hier definierten Funktionen merken
    for ($v = $i - 1; $v >= 0; $v--) {
        if (is_array($token[$v]) && in_array($token[$v][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        if (is_array($token[$v]) && $token[$v][0] === T_FUNCTION) {
            $eigene[] = $t[1];
        }
        if (is_array($token[$v]) && in_array($token[$v][0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW], true)) {
            continue 2;
        }
        break;
    }
    // Folgt eine Klammer, ist es ein Aufruf
    for ($n = $i + 1; $n < count($token); $n++) {
        if (is_array($token[$n]) && in_array($token[$n][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        if ($token[$n] === '(') {
            $aufrufe[] = $t[1];
        }
        break;
    }
}

$fehlend = [];
foreach (array_unique($aufrufe) as $name) {
    if (in_array($name, $eigene, true) || function_exists($name)) {
        continue;
    }
    $fehlend[] = $name;
}
$check('alle benutzten Funktionen sind erreichbar: ' . implode(', ', $fehlend), $fehlend === []);
$check('Wanderungen gefunden', count($eigene) > 5 && in_array('ovb_migrate', $eigene, true));

echo "$ok bestanden, $fail fehlgeschlagen\n";
