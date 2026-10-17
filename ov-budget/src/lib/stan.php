<?php
declare(strict_types=1);

/**
 * STAN – die Stärke- und Ausstattungsnachweisung als Dokumentenpaket.
 *
 * Jedes hochgeladene ZIP wird eine neue Version (1, 2, 3 …); die Dateien
 * darin (PDF, Office, Text, Bilder) werden ausgepackt und einzeln
 * abrufbar, das ZIP selbst bleibt zum Herunterladen liegen. Ältere
 * Versionen bleiben erhalten, die höchste Nummer ist die aktuelle.
 * Einzelne Dateien lassen sich zur aktuellen Version nachreichen.
 */

const STAN_ERLAUBT = ['pdf', 'docx', 'doc', 'xlsx', 'xls', 'pptx', 'odt', 'ods', 'odp', 'txt', 'csv', 'md', 'png', 'jpg', 'jpeg', 'webp'];
const STAN_MAX_DATEIEN = 500;
const STAN_MAX_ENTPACKT = 4 * 1024 * 1024 * 1024;   // 4 GB je Paket ausgepackt

/** Obergrenze für ein STAN-Paket und nachgereichte Dateien – eigene Einstellung, unabhängig von den Anlagen */
function stan_max_bytes(): int
{
    return max(1, setting_int('stan_max_mb', 1024)) * 1024 * 1024;
}

function stan_dir(): string
{
    $dir = upload_dir() . DIRECTORY_SEPARATOR . 'stan';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

/* ==================================================================== */
/* Lesen                                                                 */
/* ==================================================================== */

/** Alle Versionen, neueste zuerst, mit Dateizahl und Größe */
function stan_versionen(): array
{
    return db_all(
        'SELECT v.*, u.display_name AS hochgeladen_von,
                (SELECT COUNT(*) FROM stan_dateien d WHERE d.version_id = v.id) AS anzahl,
                (SELECT COALESCE(SUM(d.size_bytes), 0) FROM stan_dateien d WHERE d.version_id = v.id) AS bytes
         FROM stan_versionen v LEFT JOIN users u ON u.id = v.uploaded_by
         ORDER BY v.nummer DESC, v.id DESC'
    );
}

function stan_version_find(?int $id): ?array
{
    return $id ? db_row('SELECT v.*, u.display_name AS hochgeladen_von FROM stan_versionen v LEFT JOIN users u ON u.id = v.uploaded_by WHERE v.id = ?', [$id]) : null;
}

/** Die aktuelle Version: die mit der höchsten Nummer */
function stan_aktuell(): ?array
{
    return db_row('SELECT v.*, u.display_name AS hochgeladen_von FROM stan_versionen v LEFT JOIN users u ON u.id = v.uploaded_by ORDER BY v.nummer DESC, v.id DESC LIMIT 1');
}

function stan_dateien(int $versionId): array
{
    return db_all('SELECT * FROM stan_dateien WHERE version_id = ? ORDER BY pfad, name, id', [$versionId]);
}

function stan_datei_find(?int $id): ?array
{
    return $id ? db_row('SELECT d.*, v.nummer AS version_nummer FROM stan_dateien d JOIN stan_versionen v ON v.id = d.version_id WHERE d.id = ?', [$id]) : null;
}

/** Pfad zur ausgepackten Datei oder null */
function stan_datei_pfad(array $datei): ?string
{
    $p = stan_dir() . DIRECTORY_SEPARATOR . basename((string)$datei['stored_name']);
    return is_file($p) ? $p : null;
}

/** Pfad zum aufgehobenen ZIP oder null */
function stan_zip_pfad(array $version): ?string
{
    if (empty($version['zip_name'])) {
        return null;
    }
    $p = stan_dir() . DIRECTORY_SEPARATOR . basename((string)$version['zip_name']);
    return is_file($p) ? $p : null;
}

/* ==================================================================== */
/* Reine Helfer                                                          */
/* ==================================================================== */

/** Ordner im Paket als Schlüssel („" = oberste Ebene), darunter die Dateien. Reine Funktion. */
function stan_baum(array $dateien): array
{
    $out = [];
    foreach ($dateien as $d) {
        $ordner = str_replace('\\', '/', dirname((string)$d['pfad']));
        $ordner = $ordner === '.' || $ordner === '/' ? '' : trim($ordner, '/');
        $out[$ordner][] = $d;
    }
    ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($out as &$liste) {
        usort($liste, static fn($a, $b) => strnatcasecmp((string)$a['name'], (string)$b['name']));
    }
    unset($liste);
    return $out;
}

/**
 * Darf dieser ZIP-Eintrag ausgepackt werden? null = ja, sonst der Grund.
 * Ordner, versteckte Dateien, Pfade nach oben und fremde Dateiarten bleiben draußen. Reine Funktion.
 */
function stan_eintrag_pruefen(string $name, int $groesse): ?string
{
    $name = str_replace('\\', '/', $name);
    if ($name === '' || str_ends_with($name, '/')) {
        return 'Ordner';
    }
    $teile = explode('/', $name);
    foreach ($teile as $t) {
        if ($t === '..' || $t === '.') {
            return 'Pfad nach oben';
        }
    }
    if (str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name)) {
        return 'absoluter Pfad';
    }
    if (str_starts_with($name, '__MACOSX/') || str_starts_with(basename($name), '.') || basename($name) === 'Thumbs.db' || basename($name) === 'desktop.ini') {
        return 'Systemdatei';
    }
    $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($endung, STAN_ERLAUBT, true)) {
        return 'Dateiart .' . ($endung ?: '?') . ' nicht erlaubt';
    }
    if ($groesse <= 0) {
        return 'leer';
    }
    return null;
}

/** „Version 3" bzw. mit Bemerkung „Version 3 – Stand 10/2026". Reine Funktion. */
function stan_version_text(array $v): string
{
    return 'Version ' . (int)$v['nummer'] . (trim((string)($v['bemerkung'] ?? '')) !== '' ? ' – ' . trim((string)$v['bemerkung']) : '');
}

/* ==================================================================== */
/* Schreiben                                                             */
/* ==================================================================== */

/** Inhalt eines ZIPs sichten: [['index', 'name', 'groesse']], Übergangenes mit Grund. Wirft RuntimeException, wenn es kein ZIP ist. */
function stan_zip_lesen(string $zipPfad): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Die ZIP-Erweiterung von PHP fehlt.');
    }
    $z = new ZipArchive();
    if ($z->open($zipPfad) !== true) {
        throw new RuntimeException('Die Datei ist kein lesbares ZIP.');
    }
    $dateien = [];
    $uebergangen = [];
    $summe = 0;
    for ($i = 0; $i < $z->numFiles; $i++) {
        $st = $z->statIndex($i);
        if (!$st) {
            continue;
        }
        $name = (string)$st['name'];
        $groesse = (int)$st['size'];
        $grund = stan_eintrag_pruefen($name, $groesse);
        if ($grund === 'Ordner') {
            continue;
        }
        if ($grund !== null) {
            $uebergangen[] = ['name' => $name, 'grund' => $grund];
            continue;
        }
        $summe += $groesse;
        $dateien[] = ['index' => $i, 'name' => str_replace('\\', '/', $name), 'groesse' => $groesse];
    }
    $z->close();
    if (count($dateien) > STAN_MAX_DATEIEN) {
        throw new RuntimeException(sprintf('Das Paket hat %d Dateien – mehr als %d gehen nicht.', count($dateien), STAN_MAX_DATEIEN));
    }
    if ($summe > STAN_MAX_ENTPACKT) {
        throw new RuntimeException('Das Paket ist ausgepackt größer als ' . bytes_human(STAN_MAX_ENTPACKT) . '.');
    }
    return ['dateien' => $dateien, 'uebergangen' => $uebergangen];
}

/** Eine Datei aus dem Paket in die Ablage schreiben und eintragen. Gibt die Datei-ID oder null (Inhalt nicht erlaubt). */
function stan_datei_ablegen(int $versionId, string $innerPfad, string $quelle, int $groesse): ?int
{
    $endung = strtolower(pathinfo($innerPfad, PATHINFO_EXTENSION));
    $gespeichert = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $endung;
    $ziel = stan_dir() . DIRECTORY_SEPARATOR . $gespeichert;
    if (!rename($quelle, $ziel) && !copy($quelle, $ziel)) {
        return null;
    }
    $mime = class_exists('finfo') ? (string)(new finfo(FILEINFO_MIME_TYPE))->file($ziel) : '';
    if ($mime === 'text/html' || $mime === 'image/svg+xml' || str_contains($mime, 'php')) {
        @unlink($ziel);
        return null;
    }
    return db_insert('stan_dateien', [
        'version_id'  => $versionId,
        'pfad'        => mb_substr($innerPfad, 0, 500),
        'name'        => mb_substr(basename($innerPfad), 0, 255),
        'stored_name' => $gespeichert,
        'mime'        => $mime,
        'size_bytes'  => $groesse,
    ]);
}

/**
 * Ein hochgeladenes ZIP als neue Version einspielen.
 * Rückgabe: [version_id oder null, Fehler[], Hinweise[]]
 */
function stan_version_aus_zip(string $feld, string $bemerkung, array $user): array
{
    $f = $_FILES[$feld] ?? null;
    if (!$f || (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || (string)($f['name'] ?? '') === '') {
        return [null, ['Es kam keine Datei an. Bei sehr großen Paketen bricht der Upload ab, bevor die Anwendung ihn sieht – im Add-on sind bis 2 GB vorgesehen; über die Home-Assistant-Oberfläche kann es helfen, die Anwendung direkt über ihren Port aufzurufen.'], []];
    }
    if ((int)$f['error'] !== UPLOAD_ERR_OK) {
        return [null, [sprintf('„%s" konnte nicht hochgeladen werden (Fehlercode %d).', $f['name'], (int)$f['error'])], []];
    }
    $tmp = (string)$f['tmp_name'];
    if (!is_uploaded_file($tmp) && !defined('OVB_TEST_UPLOADS')) {
        return [null, ['Die Datei wurde nicht über das Formular hochgeladen.'], []];
    }
    return stan_version_aus_datei($tmp, (string)$f['name'], (int)$f['size'], $bemerkung, $user);
}

/**
 * Eine ZIP-Datei in der Ablage (hochgeladen oder aus Stücken zusammengesetzt)
 * als neue Version einspielen. Die Datei wird dabei verschoben.
 * Rückgabe: [version_id oder null, Fehler[], Hinweise[]]
 */
function stan_version_aus_datei(string $tmp, string $origName, int $groesse, string $bemerkung, array $user): array
{
    if (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'zip') {
        return [null, ['Das Paket muss ein ZIP sein (.zip). Einzelne PDFs gehen über „Dateien nachreichen".'], []];
    }
    if ($groesse > stan_max_bytes()) {
        return [null, [sprintf('Das Paket ist zu groß (höchstens %s – einstellbar unter Einstellungen → Allgemein → „STAN-Paket höchstens … MB").', bytes_human(stan_max_bytes()))], []];
    }
    $f = ['name' => $origName, 'size' => $groesse];
    try {
        $inhalt = stan_zip_lesen($tmp);
    } catch (RuntimeException $e) {
        return [null, [$e->getMessage()], []];
    }
    if (!$inhalt['dateien']) {
        return [null, ['Im Paket steckt keine Datei, die hier abgelegt werden kann (' . implode(', ', array_map('strtoupper', STAN_ERLAUBT)) . ').'], []];
    }

    if (!db_lock('stan_version', 5)) {
        return [null, ['Gerade wird ein anderes Paket eingespielt – bitte gleich noch einmal.'], []];
    }
    try {
        $nummer = (int)db_val('SELECT COALESCE(MAX(nummer), 0) FROM stan_versionen', [], 0) + 1;
        $zipName = sprintf('v%03d_%s.zip', $nummer, bin2hex(random_bytes(6)));
        $zipZiel = stan_dir() . DIRECTORY_SEPARATOR . $zipName;
        if (!(defined('OVB_TEST_UPLOADS') ? copy($tmp, $zipZiel) : (rename($tmp, $zipZiel) || copy($tmp, $zipZiel)))) {
            return [null, ['Das Paket ließ sich nicht in der Ablage speichern.'], []];
        }
        $id = db_insert('stan_versionen', [
            'nummer'      => $nummer,
            'bemerkung'   => mb_substr(trim($bemerkung), 0, 200),
            'zip_name'    => $zipName,
            'orig_name'   => mb_substr((string)$f['name'], 0, 255),
            'zip_bytes'   => (int)$f['size'],
            'uploaded_by' => (int)$user['id'],
        ]);
        // Auspacken: jede Datei einzeln in die Ablage, flach und unter zufälligem Namen
        $z = new ZipArchive();
        $z->open($zipZiel);
        $n = 0;
        $abgelehnt = [];
        foreach ($inhalt['dateien'] as $d) {
            $tmpDatei = tempnam(sys_get_temp_dir(), 'stan');
            $strom = $z->getStream((string)$z->getNameIndex($d['index']));
            if (!$strom || $tmpDatei === false) {
                $abgelehnt[] = $d['name'];
                continue;
            }
            file_put_contents($tmpDatei, $strom);
            fclose($strom);
            if (stan_datei_ablegen($id, $d['name'], $tmpDatei, $d['groesse']) === null) {
                @unlink($tmpDatei);
                $abgelehnt[] = $d['name'];
                continue;
            }
            $n++;
        }
        $z->close();
    } finally {
        db_unlock('stan_version');
    }
    audit('stan.version', 'stan', $id, sprintf('Version %d: %d Datei(en) aus %s', $nummer, $n, $f['name']));

    $hinweise = [];
    if ($inhalt['uebergangen']) {
        $hinweise[] = sprintf('%d Eintrag/Einträge übergangen: %s', count($inhalt['uebergangen']),
            implode(', ', array_slice(array_map(static fn($u) => $u['name'] . ' (' . $u['grund'] . ')', $inhalt['uebergangen']), 0, 8)) . (count($inhalt['uebergangen']) > 8 ? ' …' : ''));
    }
    if ($abgelehnt) {
        $hinweise[] = 'Nicht abgelegt, weil der Inhalt nicht zur Endung passt: ' . implode(', ', array_slice($abgelehnt, 0, 8));
    }
    return [$id, [], $hinweise];
}

/** Einzelne Dateien zur Version nachreichen ($_FILES[$feld] als Mehrfachfeld). Gibt [anzahl, fehler[]]. */
function stan_dateien_nachreichen(int $versionId, string $feld, string $ordner, array $user): array
{
    if (empty($_FILES[$feld]) || !is_array($_FILES[$feld]['name'])) {
        return [0, ['Es kam keine Datei an.']];
    }
    $ordner = trim(str_replace('\\', '/', $ordner), '/ ');
    $fehler = [];
    $n = 0;
    foreach ($_FILES[$feld]['name'] as $i => $name) {
        $name = (string)$name;
        $err = (int)$_FILES[$feld]['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE || $name === '') {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $fehler[] = sprintf('„%s" konnte nicht hochgeladen werden (Fehlercode %d).', $name, $err);
            continue;
        }
        $tmp = (string)$_FILES[$feld]['tmp_name'][$i];
        if (!is_uploaded_file($tmp) && !defined('OVB_TEST_UPLOADS')) {
            $fehler[] = sprintf('„%s" wurde nicht über das Formular hochgeladen.', $name);
            continue;
        }
        $grund = stan_datei_nachreichen_pfad($versionId, $tmp, $name, (int)$_FILES[$feld]['size'][$i], $ordner);
        if ($grund !== null) {
            $fehler[] = $grund;
            continue;
        }
        $n++;
    }
    if ($n > 0) {
        audit('stan.nachgereicht', 'stan', $versionId, sprintf('%d Datei(en)', $n));
    }
    return [$n, $fehler];
}

/** Eine Datei aus der Ablage zur Version nachreichen (verschiebt sie). null = gut, sonst der Grund. */
function stan_datei_nachreichen_pfad(int $versionId, string $tmp, string $name, int $groesse, string $ordner): ?string
{
    $ordner = trim(str_replace('\\', '/', $ordner), '/ ');
    $grund = stan_eintrag_pruefen(basename($name), $groesse);
    if ($grund !== null) {
        return sprintf('„%s": %s.', $name, $grund);
    }
    if ($groesse > stan_max_bytes()) {
        return sprintf('„%s" ist zu groß (höchstens %s).', $name, bytes_human(stan_max_bytes()));
    }
    $innen = ($ordner !== '' && stan_eintrag_pruefen($ordner . '/x.pdf', 1) === null ? $ordner . '/' : '') . basename($name);
    if (stan_datei_ablegen($versionId, $innen, $tmp, $groesse) === null) {
        return sprintf('„%s": dieser Inhalt ist nicht erlaubt.', $name);
    }
    return null;
}

/* ==================================================================== */
/* Stückweiser Upload (Home Assistant lässt nur 16 MB je Anfrage durch)  */
/* ==================================================================== */

const STAN_STUECK_MB = 8;                       // so groß schneidet der Browser
const STAN_STUECK_MAX = 15 * 1024 * 1024;       // mehr nimmt die Anwendung je Stück nicht an
const STAN_STUECKE_MAX = 4096;

function stan_tmp_dir(): string
{
    $dir = stan_dir() . DIRECTORY_SEPARATOR . 'tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

/** Upload-Kennung aus dem Browser: 32 Hex-Zeichen, sonst nichts. Reine Funktion. */
function stan_upload_id_ok(string $id): bool
{
    return (bool)preg_match('/^[a-f0-9]{32}$/', $id);
}

/**
 * Ein Stück anhängen. Die Stücke müssen der Reihe nach kommen (index 0, 1, 2 …).
 * Rückgabe: ['ok' => bool, 'next' => int, 'fehler' => string]
 */
function stan_teil_anfuegen(string $id, int $index, int $total, string $name, string $quelle): array
{
    if (!stan_upload_id_ok($id)) {
        return ['ok' => false, 'next' => 0, 'fehler' => 'Ungültige Upload-Kennung.'];
    }
    if ($total < 1 || $total > STAN_STUECKE_MAX || $index < 0 || $index >= $total) {
        return ['ok' => false, 'next' => 0, 'fehler' => 'Stückzahl oder Nummer passt nicht.'];
    }
    $groesse = (int)@filesize($quelle);
    if ($groesse <= 0 || $groesse > STAN_STUECK_MAX) {
        return ['ok' => false, 'next' => 0, 'fehler' => 'Das Stück ist leer oder zu groß.'];
    }
    $teil = stan_tmp_dir() . DIRECTORY_SEPARATOR . $id . '.part';
    $metaPfad = stan_tmp_dir() . DIRECTORY_SEPARATOR . $id . '.meta';
    $meta = is_file($metaPfad) ? json_decode((string)file_get_contents($metaPfad), true) : null;
    if ($index === 0) {
        $meta = ['name' => mb_substr(basename(str_replace('\\', '/', $name)), 0, 255), 'total' => $total, 'next' => 0, 'size' => 0];
        if (is_file($teil)) {
            unlink($teil);
        }
    }
    if (!is_array($meta) || (int)($meta['next'] ?? -1) !== $index || (int)($meta['total'] ?? 0) !== $total) {
        return ['ok' => false, 'next' => (int)($meta['next'] ?? 0), 'fehler' => 'Die Stücke kamen durcheinander – bitte den Upload neu starten.'];
    }
    if ((int)$meta['size'] + $groesse > stan_max_bytes()) {
        if (is_file($teil)) {
            unlink($teil);
        }
        if (is_file($metaPfad)) {
            unlink($metaPfad);
        }
        return ['ok' => false, 'next' => 0, 'fehler' => sprintf('Die Datei ist größer als erlaubt (höchstens %s).', bytes_human(stan_max_bytes()))];
    }
    $ziel = fopen($teil, 'ab');
    $von = fopen($quelle, 'rb');
    if (!$ziel || !$von) {
        return ['ok' => false, 'next' => $index, 'fehler' => 'Das Stück ließ sich nicht ablegen.'];
    }
    stream_copy_to_stream($von, $ziel);
    fclose($von);
    fclose($ziel);
    @unlink($quelle);
    $meta['next'] = $index + 1;
    $meta['size'] = (int)$meta['size'] + $groesse;
    $meta['zeit'] = time();
    file_put_contents($metaPfad, json_encode($meta));
    return ['ok' => true, 'next' => $index + 1, 'fehler' => ''];
}

/** Sind alle Stücke da? Dann ['pfad', 'name', 'size'] der zusammengesetzten Datei, sonst null. */
function stan_teile_fertig(string $id): ?array
{
    if (!stan_upload_id_ok($id)) {
        return null;
    }
    $teil = stan_tmp_dir() . DIRECTORY_SEPARATOR . $id . '.part';
    $metaPfad = stan_tmp_dir() . DIRECTORY_SEPARATOR . $id . '.meta';
    $meta = is_file($metaPfad) ? json_decode((string)file_get_contents($metaPfad), true) : null;
    if (!is_array($meta) || !is_file($teil) || (int)$meta['next'] !== (int)$meta['total']) {
        return null;
    }
    @unlink($metaPfad);
    return ['pfad' => $teil, 'name' => (string)$meta['name'], 'size' => (int)filesize($teil)];
}

/** Liegengebliebene Stücke (abgebrochene Uploads) nach einem Tag wegräumen */
function stan_teile_aufraeumen(int $alterSek = 86400): int
{
    $n = 0;
    // ohne Klammer-Muster im Glob – das gibt es auf Alpine (musl) nicht
    $dateien = array_merge(glob(stan_tmp_dir() . DIRECTORY_SEPARATOR . '*.part') ?: [], glob(stan_tmp_dir() . DIRECTORY_SEPARATOR . '*.meta') ?: []);
    foreach ($dateien as $f) {
        if (is_file($f) && filemtime($f) < time() - $alterSek) {
            @unlink($f);
            $n++;
        }
    }
    return $n;
}

function stan_datei_loeschen(array $datei): void
{
    $p = stan_dir() . DIRECTORY_SEPARATOR . basename((string)$datei['stored_name']);
    if (is_file($p)) {
        @unlink($p);
    }
    db_exec('DELETE FROM stan_dateien WHERE id = ?', [(int)$datei['id']]);
}

/** Eine Version samt Dateien und ZIP entfernen – die nächsthöhere wird dann die aktuelle */
function stan_version_loeschen(array $version): void
{
    foreach (stan_dateien((int)$version['id']) as $d) {
        stan_datei_loeschen($d);
    }
    $zip = stan_zip_pfad($version);
    if ($zip !== null) {
        @unlink($zip);
    }
    db_exec('DELETE FROM stan_versionen WHERE id = ?', [(int)$version['id']]);
    audit('stan.version_geloescht', 'stan', (int)$version['id'], stan_version_text($version));
}
