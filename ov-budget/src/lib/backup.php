<?php
declare(strict_types=1);

/**
 * Sicherung und Wiederherstellung.
 *
 * Eine Sicherung ist eine ZIP-Datei mit drei Teilen:
 *
 *   sicherung.json   Fassung der Anwendung, Zeitpunkt, Anlass, Zahlen
 *   datenbank.sql    alle Tabellen mit Struktur und Inhalt
 *   dateien/…        die Dateiablage (Angebote, Fahrzeug- und
 *                    Veranstaltungsdateien), ohne die Sicherungen selbst
 *
 * Der Datenbankauszug entsteht in PHP, nicht über mysqldump – das Programm
 * ist nicht überall vorhanden. Das Format ist bewusst schlicht: je Anweisung
 * ein Abschluss mit ";" am Zeilenende, Zeilenumbrüche in Werten sind
 * maskiert. So liest es auch der mysql-Befehl von Hand.
 *
 * Wiederherstellen ersetzt alles: Vorher entsteht automatisch eine
 * Sicherung des aktuellen Stands, danach laufen die Wanderungen, damit eine
 * ältere Sicherung zur laufenden Fassung passt.
 */

/** Dateinamen der Sicherungen: ovbudget-20260925-211500-manuell.zip */
const BACKUP_NAME_MUSTER = '/^ovbudget-\d{8}-\d{6}-[a-z-]{1,30}\.zip$/';
/** Das Wort, das zur Bestätigung einer Wiederherstellung getippt werden muss */
const BACKUP_BESTAETIGUNG = 'WIEDERHERSTELLEN';
/** Größte annehmbare Sicherung beim Hochladen (Byte) */
const BACKUP_MAX_UPLOAD = 512 * 1024 * 1024;

class BackupException extends RuntimeException
{
}

/** "64M" aus der php.ini in Byte. Reine Funktion. */
function backup_ini_bytes(string $wert): int
{
    $wert = trim($wert);
    if ($wert === '' || $wert === '-1') {
        return PHP_INT_MAX;
    }
    $zahl = (int)$wert;
    return match (strtolower(substr($wert, -1))) {
        'g' => $zahl * 1024 * 1024 * 1024,
        'm' => $zahl * 1024 * 1024,
        'k' => $zahl * 1024,
        default => $zahl,
    };
}

/* ==================================================================== */
/* Ablage                                                                */
/* ==================================================================== */

function backup_dir(): string
{
    $dir = upload_dir() . DIRECTORY_SEPARATOR . 'sicherungen';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

/** Geht es überhaupt? ZipArchive muss da sein, die Ablage beschreibbar. */
function backup_problem(): ?string
{
    if (!class_exists('ZipArchive')) {
        return 'Die PHP-Erweiterung „zip" fehlt. Ohne sie lassen sich keine Sicherungen packen.';
    }
    if (!is_writable(backup_dir())) {
        return 'Der Ordner ' . backup_dir() . ' ist nicht beschreibbar.';
    }
    return null;
}

/** Sieht der Name wie eine unserer Sicherungen aus? Reine Funktion. */
function backup_name_gueltig(string $name): bool
{
    return (bool)preg_match(BACKUP_NAME_MUSTER, $name);
}

/** Neuer Dateiname für eine Sicherung. Reine Funktion. */
function backup_name_neu(string $grund, ?int $ts = null): string
{
    $grund = preg_replace('/[^a-z-]/', '', strtolower($grund)) ?: 'manuell';
    return sprintf('ovbudget-%s-%s.zip', date('Ymd-His', $ts ?? time()), mb_substr($grund, 0, 30));
}

/** Pfad zu einer vorhandenen Sicherung – oder null, wenn der Name nicht passt */
function backup_path(string $name): ?string
{
    if (!backup_name_gueltig($name)) {
        return null;
    }
    $pfad = backup_dir() . DIRECTORY_SEPARATOR . $name;
    return is_file($pfad) ? $pfad : null;
}

/** Alle Sicherungen, neueste zuerst */
function backup_list(): array
{
    $out = [];
    foreach (glob(backup_dir() . DIRECTORY_SEPARATOR . 'ovbudget-*.zip') ?: [] as $pfad) {
        $name = basename($pfad);
        if (!backup_name_gueltig($name)) {
            continue;
        }
        $meta = [];
        try {
            $meta = backup_inspect($pfad)['meta'];
        } catch (Throwable) {
            // Beschädigte Datei: trotzdem auflisten, damit man sie löschen kann
        }
        $out[] = [
            'name'    => $name,
            'groesse' => (int)filesize($pfad),
            'zeit'    => (int)filemtime($pfad),
            'meta'    => $meta,
            'kaputt'  => $meta === [],
        ];
    }
    usort($out, static fn($a, $b) => strcmp($b['name'], $a['name']));
    return $out;
}

function backup_delete(string $name): bool
{
    $pfad = backup_path($name);
    return $pfad !== null && @unlink($pfad);
}

/* ==================================================================== */
/* Datenbank auslesen                                                    */
/* ==================================================================== */

/** Alle Tabellen der Datenbank, alphabetisch */
function backup_tabellen(PDO $pdo): array
{
    $tabellen = [];
    foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $r) {
        if (($r[1] ?? 'BASE TABLE') === 'BASE TABLE') {
            $tabellen[] = (string)$r[0];
        }
    }
    sort($tabellen);
    return $tabellen;
}

/**
 * Datenbank in eine Datei schreiben. Rückgabe: Zeilen je Tabelle.
 * Fremdschlüssel sind beim Einspielen abgeschaltet, deshalb ist die
 * Reihenfolge der Tabellen egal.
 */
function backup_dump_schreiben(PDO $pdo, string $ziel): array
{
    $h = fopen($ziel, 'wb');
    if ($h === false) {
        throw new BackupException('Der Datenbankauszug lässt sich nicht schreiben.');
    }
    $zeilen = [];
    fwrite($h, "-- OV-Budget " . app_version() . " – Datenbankauszug vom " . date('c') . "\n");
    fwrite($h, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

    foreach (backup_tabellen($pdo) as $tabelle) {
        $create = $pdo->query('SHOW CREATE TABLE `' . $tabelle . '`')->fetch(PDO::FETCH_NUM);
        fwrite($h, "DROP TABLE IF EXISTS `" . $tabelle . "`;\n");
        fwrite($h, (string)$create[1] . ";\n");

        $st = $pdo->query('SELECT * FROM `' . $tabelle . '`');
        $n = 0;
        $puffer = [];
        while (($row = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
            if ($n === 0) {
                $spalten = '`' . implode('`,`', array_keys($row)) . '`';
            }
            $werte = [];
            foreach ($row as $w) {
                $werte[] = $w === null ? 'NULL' : $pdo->quote((string)$w);
            }
            $puffer[] = '(' . implode(',', $werte) . ')';
            $n++;
            if (count($puffer) >= 200) {
                fwrite($h, 'INSERT INTO `' . $tabelle . '` (' . $spalten . ") VALUES\n" . implode(",\n", $puffer) . ";\n");
                $puffer = [];
            }
        }
        if ($puffer) {
            fwrite($h, 'INSERT INTO `' . $tabelle . '` (' . $spalten . ") VALUES\n" . implode(",\n", $puffer) . ";\n");
        }
        fwrite($h, "\n");
        $zeilen[$tabelle] = $n;
    }
    fwrite($h, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($h);
    return $zeilen;
}

/**
 * SQL-Text in einzelne Anweisungen zerlegen. Achtet auf Anführungszeichen
 * (einfach, doppelt, Backtick) und Maskierungen mit Backslash, überspringt
 * Kommentarzeilen. Reine Funktion.
 */
function backup_sql_teilen(string $sql): array
{
    $out = [];
    $akt = '';
    $quote = null;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($quote === null) {
            // Kommentarzeile am Anweisungsanfang überspringen
            if (trim($akt) === '' && $c === '-' && ($sql[$i + 1] ?? '') === '-') {
                $ende = strpos($sql, "\n", $i);
                $i = $ende === false ? $len : $ende;
                continue;
            }
            if ($c === ';') {
                $s = trim($akt);
                if ($s !== '') {
                    $out[] = $s;
                }
                $akt = '';
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
            }
            $akt .= $c;
            continue;
        }
        $akt .= $c;
        if ($c === '\\' && $quote !== '`') {
            $akt .= $sql[$i + 1] ?? '';
            $i++;
            continue;
        }
        if ($c === $quote) {
            // '' innerhalb einer Zeichenkette ist ein maskiertes Anführungszeichen
            if (($sql[$i + 1] ?? '') === $quote) {
                $akt .= $quote;
                $i++;
                continue;
            }
            $quote = null;
        }
    }
    $s = trim($akt);
    if ($s !== '') {
        $out[] = $s;
    }
    return $out;
}

/** SQL-Datei Anweisung für Anweisung ausführen. Rückgabe: Anzahl. */
function backup_sql_ausfuehren(PDO $pdo, string $sql): int
{
    $n = 0;
    foreach (backup_sql_teilen($sql) as $anweisung) {
        $pdo->exec($anweisung);
        $n++;
    }
    return $n;
}

/* ==================================================================== */
/* Paket packen und lesen                                                */
/* ==================================================================== */

/**
 * Alle Dateien der Ablage, die in die Sicherung gehören:
 * [relativer Pfad => absoluter Pfad]. Die Sicherungen selbst bleiben draußen.
 */
function backup_dateien(string $wurzel, string $ausser = 'sicherungen'): array
{
    $out = [];
    if (!is_dir($wurzel)) {
        return $out;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($wurzel, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $f) use ($wurzel, $ausser): bool {
                // Den Ordner der Sicherungen auf oberster Ebene auslassen
                return !($f->isDir() && $f->getPath() === $wurzel && $f->getFilename() === $ausser);
            }
        )
    );
    foreach ($it as $f) {
        if (!$f->isFile()) {
            continue;
        }
        $rel = substr($f->getPathname(), strlen($wurzel) + 1);
        $out[str_replace('\\', '/', $rel)] = $f->getPathname();
    }
    ksort($out);
    return $out;
}

/**
 * Paket schreiben. $dateien wie von backup_dateien(). Reine Funktion bis
 * auf das Schreiben der Zieldatei.
 */
function backup_paket_schreiben(string $ziel, string $sqlDatei, array $dateien, array $meta): void
{
    $zip = new ZipArchive();
    $tmp = $ziel . '.tmp';
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new BackupException('Die Sicherungsdatei lässt sich nicht anlegen.');
    }
    $meta['dateien'] = count($dateien);
    $zip->addFromString('sicherung.json', (string)json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $zip->addFile($sqlDatei, 'datenbank.sql');
    foreach ($dateien as $rel => $abs) {
        $zip->addFile($abs, 'dateien/' . $rel);
    }
    if (!$zip->close()) {
        @unlink($tmp);
        throw new BackupException('Die Sicherungsdatei konnte nicht abgeschlossen werden.');
    }
    if (!rename($tmp, $ziel)) {
        @unlink($tmp);
        throw new BackupException('Die Sicherungsdatei konnte nicht abgelegt werden.');
    }
}

/**
 * Paket prüfen und beschreiben, ohne etwas zu verändern.
 * Rückgabe: ['meta' => …, 'sql_bytes' => int, 'dateien' => int, 'tabellen' => int]
 */
function backup_inspect(string $pfad): array
{
    $zip = new ZipArchive();
    if ($zip->open($pfad, ZipArchive::RDONLY) !== true) {
        throw new BackupException('Die Datei ist keine lesbare ZIP-Datei.');
    }
    try {
        $metaRoh = $zip->getFromName('sicherung.json');
        $meta = is_string($metaRoh) ? json_decode($metaRoh, true) : null;
        if (!is_array($meta) || ($meta['programm'] ?? '') !== 'OV-Budget') {
            throw new BackupException('Das ist keine Sicherung von OV-Budget (sicherung.json fehlt oder passt nicht).');
        }
        $sqlStat = $zip->statName('datenbank.sql');
        if ($sqlStat === false || (int)$sqlStat['size'] === 0) {
            throw new BackupException('In der Sicherung fehlt der Datenbankauszug (datenbank.sql).');
        }
        $dateien = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (str_starts_with($name, 'dateien/') && !str_ends_with($name, '/')) {
                if (!backup_pfad_sicher(substr($name, 8))) {
                    throw new BackupException('Die Sicherung enthält einen unzulässigen Dateipfad: ' . $name);
                }
                $dateien++;
            }
        }
        return [
            'meta'      => $meta,
            'sql_bytes' => (int)$sqlStat['size'],
            'dateien'   => $dateien,
            'tabellen'  => count((array)($meta['zeilen'] ?? [])),
        ];
    } finally {
        $zip->close();
    }
}

/** Darf ein Pfad aus dem Paket in die Ablage geschrieben werden? Reine Funktion. */
function backup_pfad_sicher(string $rel): bool
{
    if ($rel === '' || str_starts_with($rel, '/') || str_contains($rel, '\\') || str_contains($rel, "\0")) {
        return false;
    }
    if (preg_match('#(^|/)\.\.(/|$)#', $rel)) {
        return false;
    }
    return !str_starts_with($rel, 'sicherungen/');
}

/* ==================================================================== */
/* Sichern                                                               */
/* ==================================================================== */

/**
 * Sicherung erstellen. Rückgabe: ['name' => …, 'groesse' => int, 'meta' => …]
 * $grund landet im Dateinamen: manuell, automatisch, vor-wiederherstellung.
 */
function backup_create(string $grund = 'manuell', ?array $user = null): array
{
    if (($problem = backup_problem()) !== null) {
        throw new BackupException($problem);
    }
    $name = backup_name_neu($grund);
    $ziel = backup_dir() . DIRECTORY_SEPARATOR . $name;
    $sqlTmp = tempnam(sys_get_temp_dir(), 'ovb-dump-');
    if ($sqlTmp === false) {
        throw new BackupException('Kein Platz für den Datenbankauszug.');
    }
    try {
        $zeilen = backup_dump_schreiben(db(), $sqlTmp);
        $dateien = backup_dateien(upload_dir());
        $meta = [
            'programm'  => 'OV-Budget',
            'fassung'   => app_version(),
            'format'    => 1,
            'zeit'      => date('c'),
            'grund'     => $grund,
            'von'       => $user ? (string)($user['display_name'] ?? $user['username'] ?? '') : '',
            'ov'        => (string)setting('ov_name', ''),
            'tabellen'  => count($zeilen),
            'zeilen'    => $zeilen,
        ];
        backup_paket_schreiben($ziel, $sqlTmp, $dateien, $meta);
    } finally {
        @unlink($sqlTmp);
    }
    state_save('backup_letzte', date('Y-m-d H:i:s'));
    return ['name' => $name, 'groesse' => (int)filesize($ziel), 'meta' => $meta];
}

/**
 * Alte Sicherungen eines Anlasses wegwerfen, bis nur noch $behalten übrig
 * sind. Rückgabe: Anzahl gelöschter.
 */
function backup_ausduennen(string $grund, int $behalten): int
{
    if ($behalten < 1) {
        return 0;
    }
    $passend = array_values(array_filter(backup_list(),
        static fn($b) => str_ends_with($b['name'], '-' . $grund . '.zip')));
    $weg = 0;
    foreach (array_slice($passend, $behalten) as $b) {
        if (backup_delete($b['name'])) {
            $weg++;
        }
    }
    return $weg;
}

/**
 * Für den automatischen Abruf: Ist eine Sicherung fällig? Dann eine
 * anlegen und alte ausdünnen. Rückgabe null, wenn nichts zu tun war.
 */
function backup_automatisch(): ?string
{
    $tage = setting_int('backup_automatisch_tage', 0);
    if ($tage <= 0) {
        return null;
    }
    $letzte = (int)strtotime(state_get('backup_letzte_automatik', '') ?: '2000-01-01');
    if (time() - $letzte < $tage * 86400) {
        return null;
    }
    // Nachts, damit der Server nicht mitten im Tag zu tun hat – aber nicht
    // ewig warten, falls der Abruf nachts nie kommt
    $stunde = (int)date('G');
    if ($stunde >= 6 && time() - $letzte < ($tage + 1) * 86400) {
        return null;
    }
    if (!db_lock('ovb_backup', 0)) {
        return null;
    }
    try {
        $res = backup_create('automatisch');
        state_save('backup_letzte_automatik', date('Y-m-d H:i:s'));
        $weg = backup_ausduennen('automatisch', max(1, setting_int('backup_aufheben_anzahl', 7)));
        audit('sicherung.automatisch', 'backup', null, $res['name']);
        return sprintf('%s angelegt (%s)%s', $res['name'], bytes_human($res['groesse']),
            $weg ? sprintf(', %d alte entfernt', $weg) : '');
    } finally {
        db_unlock('ovb_backup');
    }
}

/* ==================================================================== */
/* Wiederherstellen                                                      */
/* ==================================================================== */

/**
 * Hochgeladene Sicherung prüfen und in die Ablage übernehmen.
 * Rückgabe: der neue Dateiname.
 */
function backup_uebernehmen(array $datei): string
{
    if ((int)($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new BackupException('Die Datei ist nicht angekommen. Vielleicht ist sie größer, als der Webserver annimmt.');
    }
    if ((int)$datei['size'] > BACKUP_MAX_UPLOAD) {
        throw new BackupException('Die Datei ist größer als ' . bytes_human(BACKUP_MAX_UPLOAD) . '.');
    }
    $tmp = (string)$datei['tmp_name'];
    backup_inspect($tmp);   // wirft, wenn es keine Sicherung ist
    $name = backup_name_neu('hochgeladen');
    $ziel = backup_dir() . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $ziel)) {
        throw new BackupException('Die Datei konnte nicht abgelegt werden.');
    }
    return $name;
}

/**
 * Sicherung einspielen. Reihenfolge:
 *   1. Sicherung des jetzigen Stands (vor-wiederherstellung)
 *   2. alle Tabellen leeren, Auszug einspielen
 *   3. Wanderungen und Grunddaten nachziehen, falls die Sicherung älter ist
 *   4. Dateiablage ersetzen
 * $say bekommt Zwischenmeldungen. Rückgabe: Zusammenfassung.
 */
function backup_restore(string $name, callable $say): array
{
    $pfad = backup_path($name);
    if ($pfad === null) {
        throw new BackupException('Diese Sicherung gibt es nicht.');
    }
    $info = backup_inspect($pfad);
    $pdo = db();
    $wurzel = dirname(__DIR__, 2);

    $vorher = backup_create('vor-wiederherstellung');
    $say('Sicherheitskopie des bisherigen Stands: ' . $vorher['name']);

    $zip = new ZipArchive();
    if ($zip->open($pfad, ZipArchive::RDONLY) !== true) {
        throw new BackupException('Die Sicherung lässt sich nicht öffnen.');
    }
    try {
        $sql = (string)$zip->getFromName('datenbank.sql');

        // Datenbank: erst alles weg, was da ist – auch Tabellen, die die
        // Sicherung nicht kennt, sonst bleiben Reste einer neueren Fassung
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (backup_tabellen($pdo) as $t) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`');
        }
        $anweisungen = backup_sql_ausfuehren($pdo, $sql);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $say(sprintf('Datenbank eingespielt: %d Anweisungen, %d Tabellen.', $anweisungen, $info['tabellen']));

        // Wanderungen und Grunddaten, damit eine ältere Sicherung zur Fassung passt
        require_once $wurzel . '/src/cli/migrate.php';
        ovb_migrate($pdo, static function (string $m) use ($say): void { $say('Wanderung: ' . $m); });
        backup_sql_ausfuehren($pdo, (string)file_get_contents($wurzel . '/sql/seed.sql'));
        ovb_refresh_setting_texts($pdo, $wurzel . '/sql/seed.sql');
        $say('Wanderungen und Grunddaten nachgezogen.');

        // Dateiablage: alte Dateien weg (die Sicherungen bleiben), neue hinein
        $alt = backup_dateien(upload_dir());
        foreach ($alt as $abs) {
            @unlink($abs);
        }
        $dateien = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $eintrag = (string)$zip->getNameIndex($i);
            if (!str_starts_with($eintrag, 'dateien/') || str_ends_with($eintrag, '/')) {
                continue;
            }
            $rel = substr($eintrag, 8);
            if (!backup_pfad_sicher($rel)) {
                continue;
            }
            $ziel = upload_dir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_dir(dirname($ziel))) {
                @mkdir(dirname($ziel), 0770, true);
            }
            $quelle = $zip->getStream($eintrag);
            if ($quelle === false) {
                continue;
            }
            $h = fopen($ziel, 'wb');
            if ($h !== false) {
                stream_copy_to_stream($quelle, $h);
                fclose($h);
                $dateien++;
            }
            fclose($quelle);
        }
        $say(sprintf('Dateiablage ersetzt: %d Datei(en) entfernt, %d eingespielt.', count($alt), $dateien));
    } finally {
        $zip->close();
    }

    // Zwischengespeicherte Einstellungen gelten nicht mehr
    settings_reset_cache();

    return [
        'sicherung'    => $name,
        'vorher'       => $vorher['name'],
        'tabellen'     => $info['tabellen'],
        'dateien'      => $dateien,
        'fassung'      => (string)($info['meta']['fassung'] ?? ''),
        'zeit'         => (string)($info['meta']['zeit'] ?? ''),
    ];
}
