<?php
declare(strict_types=1);
/*
 * Führt alle Prüfungen (t_*.php) nacheinander in eigenen PHP-Prozessen aus
 * und fasst zusammen. Jede Prüfung schreibt am Ende "N bestanden, M
 * fehlgeschlagen" und je Fehler eine Zeile "FAIL: …".
 *
 *   php tests/alle.php            alle
 *   php tests/alle.php verbrauch  nur t_verbrauch.php (Teil des Namens reicht)
 *
 * Zusätzliche PHP-Optionen (etwa -d extension=…) kommen aus der Umgebung
 * OVB_PHP_ARGS – nötig nur auf Rechnern ohne die üblichen Erweiterungen.
 */
$filter = $argv[1] ?? '';
$dateien = glob(__DIR__ . '/t_*.php') ?: [];
sort($dateien);
$php = PHP_BINARY;
$args = trim((string)getenv('OVB_PHP_ARGS'));
$gesamtOk = 0;
$gesamtFehl = 0;
$kaputt = [];
$start = microtime(true);
foreach ($dateien as $datei) {
    $name = basename($datei, '.php');
    if ($filter !== '' && !str_contains($name, $filter)) {
        continue;
    }
    $cmd = escapeshellarg($php) . ($args !== '' ? ' ' . $args : '') . ' ' . escapeshellarg($datei) . ' 2>&1';
    $ausgabe = (string)shell_exec($cmd);
    $zeilen = array_values(array_filter(array_map('trim', explode("\n", $ausgabe)), 'strlen'));
    $letzte = $zeilen ? end($zeilen) : '';
    if (!preg_match('/^(\d+) bestanden, (\d+) fehlgeschlagen$/', $letzte, $m)) {
        $kaputt[] = $name;
        printf("%-32s ABBRUCH\n%s\n", $name, $ausgabe);
        continue;
    }
    $gesamtOk += (int)$m[1];
    $gesamtFehl += (int)$m[2];
    $fails = array_filter($zeilen, static fn($z) => str_starts_with($z, 'FAIL:') || str_starts_with($z, 'FEHL') || str_contains($z, 'Warning:') || str_contains($z, 'Deprecated:'));
    printf("%-32s %3d ok%s\n", $name, (int)$m[1], (int)$m[2] > 0 ? ', ' . (int)$m[2] . ' FEHLER' : '');
    foreach ($fails as $f) {
        echo "    $f\n";
    }
}
printf("\n%d bestanden, %d fehlgeschlagen, %d abgebrochen – %.1f s\n", $gesamtOk, $gesamtFehl, count($kaputt), microtime(true) - $start);
exit($gesamtFehl === 0 && $kaputt === [] ? 0 : 1);
