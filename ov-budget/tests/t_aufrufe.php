<?php
declare(strict_types=1);
/*
 * Aufrufe von audit() und flash() über alle Seiten und Ansichten prüfen:
 * beide vertragen kein null als Text (1.25.1 meldete deshalb einen Fehler
 * auf der Home-Assistant-Seite).
 */
$app = dirname(__DIR__);
$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/** Argumente eines Aufrufs grob zerlegen (oberste Klammerebene) */
function argumente(string $quelle, int $start): array {
    $tiefe = 0; $arg = ''; $args = []; $len = strlen($quelle);
    for ($i = $start; $i < $len; $i++) {
        $z = $quelle[$i];
        if ($z === '(' ) { $tiefe++; if ($tiefe === 1) { continue; } }
        if ($z === ')') { $tiefe--; if ($tiefe === 0) { $args[] = trim($arg); break; } }
        if ($z === ',' && $tiefe === 1) { $args[] = trim($arg); $arg = ''; continue; }
        if ($tiefe >= 1) { $arg .= $z; }
    }
    return $args;
}

$dateien = [];
foreach (['/src', '/views', '/public'] as $ordner) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app . $ordner));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $dateien[] = $f->getPathname();
        }
    }
}
$check('Dateien gefunden', count($dateien) > 50);

$fehler = [];
foreach ($dateien as $datei) {
    $quelle = (string)file_get_contents($datei);
    foreach ([['audit(', [1 => 'Kennung', 3 => 'Text']], ['flash(', [0 => 'Art', 1 => 'Text']]] as [$name, $textArgs]) {
        $pos = 0;
        while (($pos = strpos($quelle, $name, $pos)) !== false) {
            $davor = $pos > 0 ? $quelle[$pos - 1] : ' ';
            $pos += strlen($name) - 1;
            if (preg_match('/[a-z0-9_>$]/i', $davor)) {
                continue;   // etwa "function audit(" oder "$x->flash("
            }
            $args = argumente($quelle, $pos);
            foreach ($textArgs as $nr => $bedeutung) {
                if (($args[$nr] ?? '') === 'null') {
                    $fehler[] = sprintf('%s: %s – %s ist null', basename($datei), rtrim($name, '('), $bedeutung);
                }
            }
        }
    }
}
$check('kein null als Text: ' . implode('; ', $fehler), $fehler === []);

/* Profilseite: Testnachrichten dürfen das Speichern nicht auslösen */
$profil = (string)file_get_contents($app . '/src/pages/profile.php');
$posPush = strpos($profil, "'push_test'");
$posApp = strpos($profil, "'notify_test'");
$posSpeichern = strpos($profil, "db_update('users'");
$check('Push-Test vorhanden', $posPush !== false);
$check('App-Test vorhanden', $posApp !== false);
$check('Testknöpfe werden vor dem Speichern abgefangen',
    $posPush !== false && $posApp !== false && $posSpeichern !== false
    && $posPush < $posSpeichern && $posApp < $posSpeichern);
$ansicht = (string)file_get_contents($app . '/views/profile.php');
$check('Knopf nur mit angemeldetem Browser', str_contains($ansicht, 'if ($pushAbos):'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
