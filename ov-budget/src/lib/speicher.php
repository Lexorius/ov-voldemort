<?php
declare(strict_types=1);

/*
 * Speicherplatz: wie groß die Datenbank ist, was die Dateiablage belegt und
 * wie voll die Platte darunter ist – für die Verwaltungsseite.
 */

/** Welche Unterordner der Ablage es gibt und wie sie heißen */
const SPEICHER_ORDNER = [
    'fahrzeuge'      => 'Fahrzeuge (Bilder, Dokumente)',
    'veranstaltungen' => 'Veranstaltungen',
    'standorte'      => 'Stell- und Lagerplätze (Bilder, Pläne)',
    'sicherungen'    => 'Sicherungen',
    'stein-debug'    => 'Stein.APP-Mitschnitte',
];

/** Größe der Datenbank je Tabelle, größte zuerst: ['name', 'gesamt', 'tabellen' => [name, zeilen, bytes]] */
function speicher_db(): array
{
    $name = (string)db_val('SELECT DATABASE()', [], '');
    $rows = db_all('SELECT table_name AS name, COALESCE(table_rows, 0) AS zeilen,
                           COALESCE(data_length, 0) + COALESCE(index_length, 0) AS bytes
                    FROM information_schema.tables WHERE table_schema = DATABASE()
                    ORDER BY bytes DESC, table_name');
    return speicher_db_zusammenfassen($name, $rows);
}

/** Aus den Tabellenzeilen die Übersicht bauen. Reine Funktion. */
function speicher_db_zusammenfassen(string $name, array $rows): array
{
    $gesamt = 0;
    $tabellen = [];
    foreach ($rows as $r) {
        $bytes = (int)$r['bytes'];
        $gesamt += $bytes;
        $tabellen[] = ['name' => (string)$r['name'], 'zeilen' => (int)$r['zeilen'], 'bytes' => $bytes];
    }
    usort($tabellen, static fn($a, $b) => $b['bytes'] <=> $a['bytes'] ?: strcmp($a['name'], $b['name']));
    return ['name' => $name, 'gesamt' => $gesamt, 'anzahl' => count($tabellen), 'tabellen' => $tabellen];
}

/** Größe und Zahl der Dateien in einem Ordner samt Unterordnern: ['bytes', 'dateien', 'vorhanden'] */
function speicher_ordner(string $dir): array
{
    $out = ['bytes' => 0, 'dateien' => 0, 'vorhanden' => is_dir($dir)];
    if (!$out['vorhanden']) {
        return $out;
    }
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $out['bytes'] += (int)$f->getSize();
                $out['dateien']++;
            }
        }
    } catch (Throwable) {
        // nicht lesbar – dann bleibt es bei dem, was gezählt wurde
    }
    return $out;
}

/** Die Platte, auf der ein Ordner liegt: ['gesamt', 'frei', 'belegt', 'prozent'] oder null */
function speicher_platte(string $dir): ?array
{
    if (!is_dir($dir)) {
        return null;
    }
    $gesamt = @disk_total_space($dir);
    $frei = @disk_free_space($dir);
    if ($gesamt === false || $frei === false || $gesamt <= 0) {
        return null;
    }
    return speicher_platte_rechnen((int)$gesamt, (int)$frei);
}

/** Belegung aus gesamt und frei. Reine Funktion. */
function speicher_platte_rechnen(int $gesamt, int $frei): array
{
    $frei = max(0, min($frei, $gesamt));
    $belegt = $gesamt - $frei;
    return ['gesamt' => $gesamt, 'frei' => $frei, 'belegt' => $belegt,
            'prozent' => $gesamt > 0 ? (int)round($belegt / $gesamt * 100) : 0,
            // knapp: unter 10 % oder unter 500 MB frei
            'knapp' => $gesamt > 0 && ($frei / $gesamt < 0.10 || $frei < 500 * 1048576)];
}

/**
 * Alles zusammen für die Verwaltungsseite. $basis: die Dateiablage (sonst upload_dir()).
 * ['db' => …, 'ablage' => ['pfad', 'bytes', 'dateien', 'ordner' => [[key, label, bytes, dateien]]], 'platte' => …]
 */
function speicher_uebersicht(?string $basis = null): array
{
    $basis ??= upload_dir();
    $ablage = speicher_ordner($basis);
    $ordner = [];
    $bekannt = 0;
    foreach (SPEICHER_ORDNER as $key => $label) {
        $o = speicher_ordner($basis . DIRECTORY_SEPARATOR . $key);
        if ($o['vorhanden']) {
            $ordner[] = ['key' => $key, 'label' => $label, 'bytes' => $o['bytes'], 'dateien' => $o['dateien']];
            $bekannt += $o['bytes'];
        }
    }
    if ($ablage['bytes'] - $bekannt > 0) {
        $ordner[] = ['key' => 'sonstiges', 'label' => 'Sonstiges (Anlagen zu Wünschen u. a.)', 'bytes' => $ablage['bytes'] - $bekannt, 'dateien' => 0];
    }
    usort($ordner, static fn($a, $b) => $b['bytes'] <=> $a['bytes']);
    return [
        'db'     => speicher_db(),
        'ablage' => ['pfad' => $basis, 'bytes' => $ablage['bytes'], 'dateien' => $ablage['dateien'], 'vorhanden' => $ablage['vorhanden'], 'ordner' => $ordner],
        'platte' => speicher_platte($basis),
    ];
}
