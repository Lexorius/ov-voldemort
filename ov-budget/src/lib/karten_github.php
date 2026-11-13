<?php
declare(strict_types=1);

/**
 * Symbole aus einem GitHub-Repository in die Symbolbibliothek holen – nur auf Knopfdruck.
 *
 * Die Anwendung bringt keine Symbole mit und lädt nichts von selbst: Wer die Bibliothek
 * pflegt, nennt ein öffentliches Repository, sieht dessen Lizenz, bestätigt sie und wählt,
 * welche Ordner des Pakets übernommen werden. Geholt wird entweder ein Release-Paket (ZIP,
 * dort liegen oft die fertigen Zeichen) oder der Quelltext eines Zweigs.
 *
 * Sicherheit: Es wird ausschließlich mit GitHub gesprochen (feste Rechnerliste, nur https,
 * jede Weiterleitung wird erneut geprüft). Das Paket hat eine Größengrenze, wird nie
 * entpackt – jede Datei wird einzeln gelesen – und jede SVG läuft durch dieselbe Prüfung
 * wie eine hochgeladene. Rasterbilder werden neu geschrieben.
 */

class KartenGithubException extends RuntimeException
{
}

/** Größtes Paket, das geholt wird */
const KARTEN_GITHUB_MAX_BYTES = 300 * 1024 * 1024;
/** So viele Einträge eines Pakets werden höchstens durchgesehen */
const KARTEN_GITHUB_MAX_EINTRAEGE = 100000;
/** So viele Symbole legt ein Durchgang höchstens ab */
const KARTEN_GITHUB_MAX_IMPORT = 3000;
/** Größtes Rasterbild im Paket */
const KARTEN_GITHUB_BILD_MAX_BYTES = 8 * 1024 * 1024;
/** Mit diesen Rechnern wird gesprochen – dazu alles unter githubusercontent.com (dorthin leitet GitHub Downloads um) */
const KARTEN_GITHUB_HOSTS = ['api.github.com', 'github.com', 'codeload.github.com'];

/* ==================================================================== */
/* Reine Funktionen                                                      */
/* ==================================================================== */

/**
 * „eigner/name" aus einer Eingabe lesen – auch aus der Adresse der Seite oder des Klons.
 * Gibt ['owner', 'repo', 'name'] oder null. Reine Funktion.
 */
function karten_github_repo(string $eingabe): ?array
{
    $s = trim($eingabe);
    $s = (string)preg_replace('~^(?:https?://)?(?:www\.)?github\.com/~i', '', $s);
    $s = (string)preg_replace('~^git@github\.com:~i', '', $s);
    $s = (string)preg_replace('~[?#].*$~', '', $s);
    $teile = explode('/', trim($s, '/'));
    if (count($teile) < 2) {
        return null;
    }
    $owner = $teile[0];
    $repo = (string)preg_replace('~\.git$~i', '', $teile[1]);
    if (!preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})$/', $owner)
        || !preg_match('/^[A-Za-z0-9._-]{1,100}$/', $repo) || $repo === '.' || $repo === '..') {
        return null;
    }
    return ['owner' => $owner, 'repo' => $repo, 'name' => $owner . '/' . $repo];
}

/** Darf diese Adresse abgerufen werden? Nur https, nur GitHub, kein Port, keine Zugangsdaten. Reine Funktion. */
function karten_github_url_erlaubt(string $url): bool
{
    $t = parse_url($url);
    if (!is_array($t) || strtolower((string)($t['scheme'] ?? '')) !== 'https' || isset($t['user']) || isset($t['pass'])) {
        return false;
    }
    if (isset($t['port']) && (int)$t['port'] !== 443) {
        return false;
    }
    $host = strtolower((string)($t['host'] ?? ''));
    return in_array($host, KARTEN_GITHUB_HOSTS, true) || str_ends_with($host, '.githubusercontent.com');
}

/**
 * Taugt dieser Eintrag eines Pakets als Symbol? 'svg', 'bild' oder null.
 * Pfade, die aus dem Paket hinausweisen, versteckte Dateien und Beiwerk fallen weg. Reine Funktion.
 */
function karten_github_eintrag_art(string $pfad): ?string
{
    if ($pfad === '' || str_ends_with($pfad, '/') || str_contains($pfad, '\\') || str_contains($pfad, "\0") || str_starts_with($pfad, '/')) {
        return null;
    }
    foreach (explode('/', $pfad) as $teil) {
        if ($teil === '' || $teil === '..' || $teil[0] === '.' || $teil === '__MACOSX') {
            return null;
        }
    }
    $endung = strtolower(pathinfo($pfad, PATHINFO_EXTENSION));
    if ($endung === 'svg') {
        return 'svg';
    }
    return in_array($endung, KARTEN_SYMBOL_TYPEN, true) ? 'bild' : null;
}

/** Ordner eines Eintrags – '' für die oberste Ebene. Reine Funktion. */
function karten_github_ordner(string $pfad): string
{
    $o = dirname($pfad);
    return $o === '.' ? '' : $o;
}

/** Gruppe aus dem Ordner: der letzte Teil, Unterstriche werden Leerzeichen, davor der Vorsatz. Reine Funktion. */
function karten_github_gruppe(string $ordner, string $vorsatz = ''): string
{
    $teile = $ordner === '' ? [] : explode('/', $ordner);
    $letzter = trim(str_replace('_', ' ', (string)end($teile)));
    $vorsatz = trim((string)preg_replace('/\s+/u', ' ', $vorsatz));
    $g = trim($vorsatz !== '' && $letzter !== '' ? $vorsatz . ' ' . $letzter : $vorsatz . $letzter);
    return mb_substr((string)preg_replace('/\s+/u', ' ', $g), 0, 100);
}

/** Name des Symbols aus dem Dateinamen: ohne Endung, Unterstriche werden Leerzeichen. Reine Funktion. */
function karten_github_name(string $pfad): string
{
    $n = trim((string)preg_replace('/\s+/u', ' ', str_replace('_', ' ', pathinfo($pfad, PATHINFO_FILENAME))));
    return mb_substr($n !== '' ? $n : 'Symbol', 0, 150);
}

/** Woher ein Symbol stammt, so wie es am Symbol steht: „github:eigner/name@stand". Reine Funktion. */
function karten_github_quelle_text(string $repo, string $ref): string
{
    return mb_substr('github:' . $repo . ($ref !== '' ? '@' . $ref : ''), 0, 200);
}

/** Dateinamen aus einem Paket lesbar machen – ältere Pakete sind nicht in UTF-8 benannt. Reine Funktion. */
function karten_github_pfad_lesbar(string $pfad): string
{
    if (mb_check_encoding($pfad, 'UTF-8')) {
        return $pfad;
    }
    $neu = function_exists('iconv') ? @iconv('CP437', 'UTF-8//IGNORE', $pfad) : false;
    return is_string($neu) && $neu !== '' ? $neu : (string)preg_replace('/[^\x20-\x7E]/', '_', $pfad);
}

/* ==================================================================== */
/* Mit GitHub sprechen                                                   */
/* ==================================================================== */

/**
 * Eine Adresse bei GitHub abrufen. Mit $ziel wird in diese Datei geschrieben (Paket),
 * sonst kommt der Inhalt zurück. Weiterleitungen folgt die Funktion selbst und prüft
 * jedes Ziel neu. Gibt ['status', 'body', 'bytes'].
 * Für die Tests: $GLOBALS['karten_github_attrappe'] = fn(string $url, ?string $ziel): array.
 */
function karten_github_http(string $url, ?string $ziel = null, int $maxBytes = 2097152): array
{
    for ($sprung = 0; $sprung < 6; $sprung++) {
        if (!karten_github_url_erlaubt($url)) {
            throw new KartenGithubException('Die Adresse führt von GitHub weg – abgebrochen.');
        }
        if (isset($GLOBALS['karten_github_attrappe'])) {
            $antwort = ($GLOBALS['karten_github_attrappe'])($url, $ziel);
        } else {
            $antwort = karten_github_curl($url, $ziel, $maxBytes);
        }
        $weiter = (string)($antwort['location'] ?? '');
        if (in_array((int)$antwort['status'], [301, 302, 303, 307, 308], true) && $weiter !== '') {
            if (str_starts_with($weiter, '/')) {
                $t = parse_url($url);
                $weiter = 'https://' . (string)($t['host'] ?? '') . $weiter;
            }
            $url = $weiter;
            continue;
        }
        return ['status' => (int)$antwort['status'], 'body' => (string)($antwort['body'] ?? ''), 'bytes' => (int)($antwort['bytes'] ?? 0)];
    }
    throw new KartenGithubException('GitHub leitet zu oft weiter – abgebrochen.');
}

/** Ein einzelner Abruf ohne Weiterleitung. Gibt ['status', 'body', 'bytes', 'location']. */
function karten_github_curl(string $url, ?string $ziel, int $maxBytes): array
{
    if (!function_exists('curl_init')) {
        throw new KartenGithubException('Für den Abruf fehlt die PHP-Erweiterung curl.');
    }
    $fh = null;
    if ($ziel !== null) {
        $fh = @fopen($ziel, 'wb');
        if ($fh === false) {
            throw new KartenGithubException('Das Paket lässt sich auf dem Server nicht ablegen.');
        }
    }
    $body = '';
    $bytes = 0;
    $zuGross = false;
    $location = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => $ziel !== null ? 840 : 30,
        // nichts über den Ortsverband: ein neutraler Name genügt
        CURLOPT_USERAGENT      => 'OV-Multitool',
        CURLOPT_HTTPHEADER     => [$ziel !== null ? 'Accept: application/octet-stream, application/zip, */*' : 'Accept: application/vnd.github+json'],
        CURLOPT_HEADERFUNCTION => static function ($ch, string $zeile) use (&$location): int {
            if (stripos($zeile, 'location:') === 0) {
                $location = trim(substr($zeile, 9));
            }
            return strlen($zeile);
        },
        CURLOPT_WRITEFUNCTION  => static function ($ch, string $teil) use (&$body, &$bytes, &$zuGross, $fh, $maxBytes): int {
            $bytes += strlen($teil);
            if ($bytes > $maxBytes) {
                $zuGross = true;
                return 0;   // bricht die Übertragung ab
            }
            if ($fh) {
                return (int)fwrite($fh, $teil);
            }
            $body .= $teil;
            return strlen($teil);
        },
    ]);
    $ok = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $fehler = curl_error($ch);
    curl_close($ch);
    if ($fh) {
        fclose($fh);
    }
    if ($zuGross) {
        throw new KartenGithubException('Das Paket ist größer als erlaubt (höchstens ' . bytes_human($maxBytes) . ').');
    }
    if ($ok === false) {
        throw new KartenGithubException('GitHub ist nicht erreichbar: ' . ($fehler !== '' ? $fehler : 'Zeitüberschreitung'));
    }
    return ['status' => $status, 'body' => $body, 'bytes' => $bytes, 'location' => $location];
}

/** Eine Auskunft der GitHub-Schnittstelle als Feld – null, wenn es den Eintrag nicht gibt */
function karten_github_json(string $url): ?array
{
    $r = karten_github_http($url);
    if ($r['status'] === 404) {
        return null;
    }
    if ($r['status'] === 403 || $r['status'] === 429) {
        throw new KartenGithubException('GitHub nimmt gerade keine weiteren Anfragen von diesem Anschluss an (Grenze je Stunde) – bitte später noch einmal.');
    }
    if ($r['status'] !== 200) {
        throw new KartenGithubException('GitHub antwortete mit HTTP ' . $r['status'] . '.');
    }
    $d = json_decode($r['body'], true);
    if (!is_array($d)) {
        throw new KartenGithubException('GitHub antwortete unlesbar.');
    }
    return $d;
}

/**
 * Was GitHub über das Repository sagt: Name, Beschreibung, Lizenz und die Pakete, die sich
 * holen lassen – die ZIP-Anhänge des neuesten Release und der Quelltext des Hauptzweigs.
 */
function karten_github_info(array $repo): array
{
    $basis = 'https://api.github.com/repos/' . rawurlencode($repo['owner']) . '/' . rawurlencode($repo['repo']);
    $r = karten_github_json($basis);
    if ($r === null) {
        throw new KartenGithubException('Dieses Repository gibt es bei GitHub nicht – oder es ist nicht öffentlich.');
    }
    $zweig = (string)($r['default_branch'] ?? 'main');
    $quellen = [];
    $release = karten_github_json($basis . '/releases/latest');
    foreach ((array)($release['assets'] ?? []) as $a) {
        $name = (string)($a['name'] ?? '');
        $url = (string)($a['browser_download_url'] ?? '');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'zip' && karten_github_url_erlaubt($url)) {
            $quellen[] = ['art' => 'release', 'ref' => (string)($release['tag_name'] ?? ''), 'name' => $name,
                          'bytes' => (int)($a['size'] ?? 0), 'url' => $url];
        }
    }
    $quellen[] = ['art' => 'zweig', 'ref' => $zweig, 'name' => 'Quelltext des Zweigs ' . $zweig,
                  // GitHub nennt die Größe des Repositorys in KiB – ein Anhaltswert
                  'bytes' => (int)($r['size'] ?? 0) * 1024,
                  'url' => 'https://codeload.github.com/' . rawurlencode($repo['owner']) . '/' . rawurlencode($repo['repo'])
                      . '/zip/refs/heads/' . implode('/', array_map('rawurlencode', explode('/', $zweig)))];
    $spdx = (string)($r['license']['spdx_id'] ?? '');
    return [
        'name'         => (string)($r['full_name'] ?? $repo['name']),
        'beschreibung' => mb_substr((string)($r['description'] ?? ''), 0, 300),
        'url'          => 'https://github.com/' . $repo['owner'] . '/' . $repo['repo'],
        'lizenz'       => $spdx === 'NOASSERTION' ? '' : $spdx,
        'lizenz_name'  => (string)($r['license']['name'] ?? ''),
        'release'      => (string)($release['tag_name'] ?? ''),
        'quellen'      => $quellen,
    ];
}

/* ==================================================================== */
/* Das Paket                                                             */
/* ==================================================================== */

/** Hier liegt ein geholtes Paket, bis es übernommen oder verworfen ist – außerhalb des Web-Verzeichnisses */
function karten_github_dir(): string
{
    $dir = karten_symbol_dir() . DIRECTORY_SEPARATOR . 'import';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

/** Pfad zu einem geholten Paket – oder null. Der Name ist immer ein selbst vergebener. */
function karten_github_zip_pfad(string $name): ?string
{
    if (!preg_match('/^[a-f0-9]{24}\.zip$/', $name)) {
        return null;
    }
    $pfad = karten_github_dir() . DIRECTORY_SEPARATOR . $name;
    return is_file($pfad) ? $pfad : null;
}

function karten_github_zip_weg(string $name): void
{
    $pfad = karten_github_zip_pfad($name);
    if ($pfad !== null) {
        @unlink($pfad);
    }
}

/** Liegengebliebene Pakete entfernen – ein abgebrochener Abruf soll keinen Platz belegen */
function karten_github_aufraeumen(int $aelterAls = 86400): int
{
    $n = 0;
    foreach (glob(karten_github_dir() . DIRECTORY_SEPARATOR . '*.zip') ?: [] as $datei) {
        if (time() - (int)@filemtime($datei) > $aelterAls && @unlink($datei)) {
            $n++;
        }
    }
    return $n;
}

/** Ein Paket holen. Gibt den Namen der abgelegten Datei zurück. */
function karten_github_laden(array $quelle): string
{
    if ((int)($quelle['bytes'] ?? 0) > KARTEN_GITHUB_MAX_BYTES && ($quelle['art'] ?? '') === 'release') {
        throw new KartenGithubException('Das Paket ist größer als erlaubt (höchstens ' . bytes_human(KARTEN_GITHUB_MAX_BYTES) . ').');
    }
    if (!class_exists('ZipArchive')) {
        throw new KartenGithubException('Für das Paket fehlt die PHP-Erweiterung zip.');
    }
    $name = bin2hex(random_bytes(12)) . '.zip';
    $pfad = karten_github_dir() . DIRECTORY_SEPARATOR . $name;
    try {
        $r = karten_github_http((string)$quelle['url'], $pfad, KARTEN_GITHUB_MAX_BYTES);
        if ($r['status'] !== 200) {
            throw new KartenGithubException('GitHub lieferte das Paket nicht (HTTP ' . $r['status'] . ').');
        }
        $z = new ZipArchive();
        if (!is_file($pfad) || $z->open($pfad) !== true) {
            throw new KartenGithubException('Was GitHub lieferte, ist kein ZIP-Paket.');
        }
        $z->close();
    } catch (Throwable $ex) {
        @unlink($pfad);
        throw $ex instanceof KartenGithubException ? $ex : new KartenGithubException($ex->getMessage());
    }
    return $name;
}

/**
 * Was im Paket als Symbol taugt, nach Ordnern: ['ordner' => [pfad => ['svg' => n, 'bild' => n]],
 * 'svg' => n, 'bild' => n, 'abgeschnitten' => bool]. Es wird nichts entpackt.
 */
function karten_github_zip_inhalt(string $pfad): array
{
    $z = new ZipArchive();
    if ($z->open($pfad) !== true) {
        throw new KartenGithubException('Das Paket lässt sich nicht öffnen.');
    }
    $ordner = [];
    $summe = ['svg' => 0, 'bild' => 0];
    $n = min($z->numFiles, KARTEN_GITHUB_MAX_EINTRAEGE);
    for ($i = 0; $i < $n; $i++) {
        $st = $z->statIndex($i);
        $name = is_array($st) ? karten_github_pfad_lesbar((string)$st['name']) : '';
        $art = karten_github_eintrag_art($name);
        if ($art === null) {
            continue;
        }
        $o = karten_github_ordner($name);
        $ordner[$o] ??= ['svg' => 0, 'bild' => 0];
        $ordner[$o][$art]++;
        $summe[$art]++;
    }
    $abgeschnitten = $z->numFiles > KARTEN_GITHUB_MAX_EINTRAEGE;
    $z->close();
    uksort($ordner, 'strnatcasecmp');
    return ['ordner' => $ordner, 'svg' => $summe['svg'], 'bild' => $summe['bild'], 'abgeschnitten' => $abgeschnitten];
}

/**
 * Die Symbole der gewählten Ordner in die Bibliothek legen.
 * $typen: 'svg', 'bild' oder 'beides'. $quelle: ['repo', 'ref', 'lizenz'].
 * Gibt ['neu', 'doppelt', 'abgelehnt' => [grund => anzahl], 'beispiele' => [], 'rest'] zurück.
 * Was aus derselben Quelle schon unter gleichem Namen in der Gruppe liegt, wird übersprungen –
 * ein zweiter Durchgang legt nichts doppelt an.
 */
function karten_github_importieren(string $pfad, array $ordner, string $typen, string $vorsatz, array $quelle, array $user): array
{
    $ergebnis = ['neu' => 0, 'doppelt' => 0, 'abgelehnt' => [], 'beispiele' => [], 'rest' => 0];
    $z = new ZipArchive();
    if ($z->open($pfad) !== true) {
        throw new KartenGithubException('Das Paket lässt sich nicht öffnen.');
    }
    $dir = karten_symbol_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        $z->close();
        throw new KartenGithubException(sprintf('Der Ordner %s ist nicht beschreibbar.', $dir));
    }
    $gewaehlt = array_fill_keys(array_map('strval', $ordner), true);
    $quelleText = karten_github_quelle_text((string)($quelle['repo'] ?? ''), (string)($quelle['ref'] ?? ''));
    $lizenz = mb_substr(trim((string)($quelle['lizenz'] ?? '')), 0, 80);
    $vorhandeneGruppen = karten_gruppen();
    $da = [];
    foreach (db_all('SELECT gruppe, name FROM karten_symbole WHERE quelle = ?', [$quelleText]) as $r) {
        $da[mb_strtolower((string)$r['gruppe']) . "\n" . mb_strtolower((string)$r['name'])] = true;
    }
    $ablehnen = static function (string $grund, string $name) use (&$ergebnis): void {
        $ergebnis['abgelehnt'][$grund] = ($ergebnis['abgelehnt'][$grund] ?? 0) + 1;
        if (count($ergebnis['beispiele']) < 5) {
            $ergebnis['beispiele'][] = basename($name) . ': ' . $grund;
        }
    };
    $gruppenCache = [];
    $n = min($z->numFiles, KARTEN_GITHUB_MAX_EINTRAEGE);
    for ($i = 0; $i < $n; $i++) {
        $st = $z->statIndex($i);
        $name = is_array($st) ? karten_github_pfad_lesbar((string)$st['name']) : '';
        $art = karten_github_eintrag_art($name);
        $o = $art !== null ? karten_github_ordner($name) : '';
        if ($art === null || !isset($gewaehlt[$o]) || ($typen !== 'beides' && $typen !== $art)) {
            continue;
        }
        if ($ergebnis['neu'] >= KARTEN_GITHUB_MAX_IMPORT) {
            $ergebnis['rest']++;
            continue;
        }
        $groesse = (int)$st['size'];
        if ($groesse <= 0 || $groesse > ($art === 'svg' ? KARTEN_SVG_MAX_BYTES : KARTEN_GITHUB_BILD_MAX_BYTES)) {
            $ablehnen($groesse <= 0 ? 'die Datei ist leer' : 'die Datei ist für ein Symbol zu groß', $name);
            continue;
        }
        $gruppe = $gruppenCache[$o] ??= karten_gruppe_angleichen(karten_github_gruppe($o, $vorsatz), $vorhandeneGruppen);
        $symbolName = karten_github_name($name);
        $schluessel = mb_strtolower($gruppe) . "\n" . mb_strtolower($symbolName);
        if (isset($da[$schluessel])) {
            $ergebnis['doppelt']++;
            continue;
        }
        $inhalt = $z->getFromIndex($i);
        if (!is_string($inhalt) || strlen($inhalt) !== $groesse) {
            $ablehnen('die Datei ließ sich nicht lesen', $name);
            continue;
        }
        $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $gespeichert = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $endung;
        $ziel = $dir . DIRECTORY_SEPARATOR . $gespeichert;
        if ($art === 'svg') {
            $grund = karten_svg_pruefen($inhalt);
            if ($grund !== null) {
                $ablehnen($grund, $name);
                continue;
            }
            $mime = 'image/svg+xml';
            if (@file_put_contents($ziel, $inhalt) === false) {
                $ablehnen('die Datei ließ sich nicht ablegen', $name);
                continue;
            }
        } else {
            $mime = class_exists('finfo') ? (string)(new finfo(FILEINFO_MIME_TYPE))->buffer($inhalt) : '';
            if (!str_starts_with($mime, 'image/') || $mime === 'image/svg+xml') {
                $ablehnen('die Datei trägt eine Bild-Endung, ist aber kein solches Bild', $name);
                continue;
            }
            // Rasterbilder werden neu geschrieben: ohne Metadaten, höchstens KARTEN_SYMBOL_MAX Kantenlänge
            $roh = $ziel . '.roh';
            if (@file_put_contents($roh, $inhalt) === false) {
                $ablehnen('die Datei ließ sich nicht ablegen', $name);
                continue;
            }
            if (function_exists('vfile_process_image') && vfile_process_image($roh, $ziel, $mime, KARTEN_SYMBOL_MAX, 90) !== null) {
                @unlink($roh);
            } elseif (!@rename($roh, $ziel)) {
                @unlink($roh);
                $ablehnen('die Datei ließ sich nicht ablegen', $name);
                continue;
            }
            $groesse = (int)filesize($ziel);
        }
        db_insert('karten_symbole', [
            'name'        => $symbolName,
            'gruppe'      => $gruppe,
            'orig_name'   => mb_substr(basename($name), 0, 255),
            'stored_name' => $gespeichert,
            'mime'        => $mime,
            'size_bytes'  => $groesse,
            'quelle'      => $quelleText,
            'lizenz'      => $lizenz,
            'uploaded_by' => (int)$user['id'],
        ]);
        $da[$schluessel] = true;
        if ($gruppe !== '' && !in_array($gruppe, $vorhandeneGruppen, true)) {
            $vorhandeneGruppen[] = $gruppe;
        }
        $ergebnis['neu']++;
    }
    $z->close();
    audit('karten.github_import', 'karten_symbol', null, sprintf('%s: %d neu, %d schon da, %d abgelehnt',
        $quelleText, $ergebnis['neu'], $ergebnis['doppelt'], array_sum($ergebnis['abgelehnt'])));
    return $ergebnis;
}
