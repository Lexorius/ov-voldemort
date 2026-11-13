<?php
declare(strict_types=1);
/*
 * Karten: Symbole aus einem GitHub-Repository holen (nur auf Knopfdruck, nur GitHub,
 * mit Lizenzbestätigung), taktisches Zeichen je Fahrzeug.
 */
session_start();
date_default_timezone_set('Europe/Berlin');
define('OVB_TEST_UPLOADS', true);

$tmp = sys_get_temp_dir() . '/ovb-kartengithub-test-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OVB_UPLOAD_DIR=' . $tmp);
$_ENV['OVB_UPLOAD_DIR'] = $tmp;

$GLOBALS['settings'] = ['waehrung' => 'EUR', 'upload_max_mb' => '10', 'karten_modul_name' => 'Karten'];
$GLOBALS['rechte'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['gruppen'] = [['gruppe' => 'Fahrzeuge']];
$GLOBALS['vorhanden'] = [];
$GLOBALS['gruppenSymbole'] = [];
$GLOBALS['marker'] = [];
$GLOBALS['fahrzeuge'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    if (str_contains($sql, 'SELECT DISTINCT gruppe')) { return $GLOBALS['gruppen']; }
    if (str_contains($sql, 'WHERE quelle = ?')) { $GLOBALS['quelleGefragt'] = $p[0]; return $GLOBALS['vorhanden']; }
    if (str_contains($sql, 'FROM karten_symbole WHERE gruppe = ?')) { return $GLOBALS['gruppenSymbole']; }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, 'FROM karten_marker')) { return $GLOBALS['marker'][(int)$p[0]] ?? 0; }
    if (str_contains($sql, 'FROM vehicles')) { return $GLOBALS['fahrzeuge'][(int)$p[0]] ?? 0; }
    return $d;
}
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 20 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
final class StubStmtG { public function execute(array $p): bool { return true; } }
final class StubPdoG { public function prepare(string $s): StubStmtG { return new StubStmtG(); } }
function db(): StubPdoG { return new StubPdoG(); }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung', 'display_name' => 'Tester']; }
function geo_heim(): ?array { return null; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'uploads', 'vehicle_files', 'standorte', 'karten', 'karten_github'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$wirft = static function (callable $f): string {
    try { $f(); } catch (KartenGithubException $ex) { return $ex->getMessage(); }
    return '';
};
$ich = ['id' => 1, 'role' => 'leitung'];

/* ---------- Repository aus der Eingabe ---------- */
$r = karten_github_repo('jonas-koeritz/Taktische-Zeichen');
$check('eigner/name', $r === ['owner' => 'jonas-koeritz', 'repo' => 'Taktische-Zeichen', 'name' => 'jonas-koeritz/Taktische-Zeichen']);
foreach (['https://github.com/jonas-koeritz/Taktische-Zeichen', 'http://www.github.com/jonas-koeritz/Taktische-Zeichen/', 'github.com/jonas-koeritz/Taktische-Zeichen.git',
          'https://github.com/jonas-koeritz/Taktische-Zeichen/tree/master/symbols?x=1#y', 'git@github.com:jonas-koeritz/Taktische-Zeichen.git', '  jonas-koeritz/Taktische-Zeichen  '] as $e) {
    $check("Adresse erkannt: $e", (karten_github_repo($e)['name'] ?? '') === 'jonas-koeritz/Taktische-Zeichen');
}
foreach (['', 'nur-ein-teil', 'a/..', 'a/.', '-vorn/repo', 'eigner/re po', 'https://evil.example/eigner/repo', 'eigner/repo<script>', '../../etc/passwd', 'a_b/repo'] as $e) {
    $check("abgelehnt: $e", karten_github_repo($e) === null);
}

/* ---------- Nur GitHub ---------- */
foreach (['https://api.github.com/repos/a/b', 'https://github.com/a/b/releases/download/v1/release.zip', 'https://codeload.github.com/a/b/zip/refs/heads/main',
          'https://release-assets.githubusercontent.com/x?sig=1', 'https://objects.githubusercontent.com/x', 'https://API.GitHub.com:443/x'] as $u) {
    $check("erlaubt: $u", karten_github_url_erlaubt($u));
}
foreach (['http://api.github.com/repos/a/b', 'https://github.com.evil.example/a/b', 'https://evilgithubusercontent.com/x', 'https://githubusercontent.com.evil.example/x',
          'https://github.com@evil.example/a', 'https://user:pw@github.com/a', 'https://github.com:8443/a', 'file:///etc/passwd', 'ftp://github.com/a', 'https://192.168.1.1/a',
          'https://localhost/a', '//github.com/a', 'github.com/a', ''] as $u) {
    $check("verboten: $u", !karten_github_url_erlaubt($u));
}

/* ---------- Einträge eines Pakets ---------- */
$check('SVG und Bild', karten_github_eintrag_art('svg/THW_Fahrzeuge/GKW.svg') === 'svg' && karten_github_eintrag_art('png/256/GKW.PNG') === 'bild' && karten_github_eintrag_art('a.webp') === 'bild');
foreach (['../raus.svg', 'a/../b.svg', '/abs.svg', 'a\\b.svg', '.github/logo.svg', 'a/.versteckt.svg', '__MACOSX/a.svg', 'README.md', 'ordner/', 'vorlage.j2', 'a//b.svg', "a\0.svg", ''] as $e) {
    $check('kein Symbol: ' . json_encode($e), karten_github_eintrag_art($e) === null);
}
$check('Ordner', karten_github_ordner('svg/THW_Fahrzeuge/GKW.svg') === 'svg/THW_Fahrzeuge' && karten_github_ordner('GKW.svg') === '');
$check('Gruppe aus dem Ordner', karten_github_gruppe('svg/THW_Fahrzeuge') === 'THW Fahrzeuge' && karten_github_gruppe('svg/THW_Fahrzeuge', ' TZ ') === 'TZ THW Fahrzeuge'
    && karten_github_gruppe('') === '' && karten_github_gruppe('', 'TZ') === 'TZ' && mb_strlen(karten_github_gruppe(str_repeat('x', 300))) === 100);
$check('Name aus der Datei', karten_github_name('svg/THW_Fahrzeuge/GKW_7t.svg') === 'GKW 7t' && karten_github_name('a/Anhänger_Kühl.svg') === 'Anhänger Kühl' && karten_github_name('a/.svg') === 'Symbol');
$check('Quelle am Symbol', karten_github_quelle_text('a/b', 'v2.0.0') === 'github:a/b@v2.0.0' && karten_github_quelle_text('a/b', '') === 'github:a/b');
$check('lesbarer Pfad bleibt, kaputter wird lesbar', karten_github_pfad_lesbar('svg/Kühl.svg') === 'svg/Kühl.svg' && mb_check_encoding(karten_github_pfad_lesbar("svg/K\x81hl.svg"), 'UTF-8'));

/* ---------- SVG mit eingebetteter Schrift ---------- */
$svgGut = '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . "\n"
    . '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256"><title>GKW</title><defs><style type="text/css"><![CDATA['
    . "text { font-family: 'Roboto Slab'; } @font-face { font-family: 'Roboto Slab'; src: local('Roboto Slab Bold'), url(\"data:application/font-woff;charset=utf-8;base64,d09GRgABAAAA\") format('woff'); }"
    . ']]></style><clipPath id="symbol"><path d="M10,64 L246,64 Z" /></clipPath></defs>'
    . '<path d="M10,64 L10,192 L246,192 Z" fill="#003399" clip-path="url(#symbol)" /><text x="128" y="145">GKW</text></svg>';
$check('eingebettete Schrift ist erlaubt', karten_svg_pruefen($svgGut) === null);
$check('auch als font/woff2', karten_svg_pruefen(str_replace('data:application/font-woff;charset=utf-8;', 'data:font/woff2;', $svgGut)) === null);
$check('Schrift von außen bleibt verboten', karten_svg_pruefen(str_replace('data:application/font-woff;charset=utf-8;base64,d09GRgABAAAA', 'https://fonts.example/x.woff', $svgGut)) !== null);
$check('anderes data: bleibt verboten', karten_svg_pruefen(str_replace('data:application/font-woff;charset=utf-8;', 'data:text/html;', $svgGut)) !== null);
$check('Skript bleibt verboten', karten_svg_pruefen(str_replace('<title>', '<script>alert(1)</script><title>', $svgGut)) !== null);

/* ---------- Abruf: Weiterleitungen nur innerhalb von GitHub ---------- */
$GLOBALS['abrufe'] = [];
$GLOBALS['karten_github_attrappe'] = static function (string $url, ?string $ziel): array {
    $GLOBALS['abrufe'][] = $url;
    return match (true) {
        str_contains($url, '/start')   => ['status' => 302, 'location' => 'https://release-assets.githubusercontent.com/ziel'],
        str_contains($url, '/relativ') => ['status' => 301, 'location' => '/ziel'],
        str_contains($url, '/fremd')   => ['status' => 302, 'location' => 'https://evil.example/paket.zip'],
        str_contains($url, '/intern')  => ['status' => 302, 'location' => 'http://169.254.169.254/latest/meta-data'],
        str_contains($url, '/kreis')   => ['status' => 302, 'location' => 'https://github.com/kreis'],
        default                        => ['status' => 200, 'body' => 'angekommen', 'bytes' => 10],
    };
};
$a = karten_github_http('https://github.com/start');
$check('Weiterleitung zu GitHub wird gefolgt', $a['status'] === 200 && $a['body'] === 'angekommen' && $GLOBALS['abrufe'] === ['https://github.com/start', 'https://release-assets.githubusercontent.com/ziel']);
$GLOBALS['abrufe'] = [];
karten_github_http('https://github.com/relativ');
$check('relative Weiterleitung bleibt auf dem Rechner', $GLOBALS['abrufe'][1] === 'https://github.com/ziel');
$GLOBALS['abrufe'] = [];
$check('Weiterleitung nach außen bricht ab', str_contains($wirft(fn() => karten_github_http('https://github.com/fremd')), 'von GitHub weg') && $GLOBALS['abrufe'] === ['https://github.com/fremd']);
$check('Weiterleitung ins eigene Netz bricht ab', str_contains($wirft(fn() => karten_github_http('https://github.com/intern')), 'von GitHub weg'));
$check('Kreis bricht ab', str_contains($wirft(fn() => karten_github_http('https://github.com/kreis')), 'zu oft'));
$GLOBALS['abrufe'] = [];
$check('fremde Adresse wird gar nicht erst abgerufen', $wirft(fn() => karten_github_http('https://evil.example/x')) !== '' && $GLOBALS['abrufe'] === []);

/* ---------- Auskunft über das Repository ---------- */
$GLOBALS['api'] = [
    'https://api.github.com/repos/jonas-koeritz/Taktische-Zeichen' => [200, ['full_name' => 'jonas-koeritz/Taktische-Zeichen', 'description' => 'Taktische Zeichen <b>als</b> Vektorgrafiken',
        'default_branch' => 'master', 'size' => 15972, 'license' => ['spdx_id' => 'CC-BY-4.0', 'name' => 'Creative Commons Attribution 4.0 International']]],
    'https://api.github.com/repos/jonas-koeritz/Taktische-Zeichen/releases/latest' => [200, ['tag_name' => 'v2.0.0', 'assets' => [
        ['name' => 'release.zip', 'size' => 66600671, 'browser_download_url' => 'https://github.com/jonas-koeritz/Taktische-Zeichen/releases/download/v2.0.0/release.zip'],
        ['name' => 'hinweise.pdf', 'size' => 100, 'browser_download_url' => 'https://github.com/jonas-koeritz/Taktische-Zeichen/releases/download/v2.0.0/hinweise.pdf'],
        ['name' => 'fremd.zip', 'size' => 100, 'browser_download_url' => 'https://evil.example/fremd.zip'],
    ]]],
    'https://api.github.com/repos/ohne/release' => [200, ['full_name' => 'ohne/release', 'default_branch' => 'feature/neu', 'size' => 10, 'license' => null]],
    'https://api.github.com/repos/ohne/release/releases/latest' => [404, ['message' => 'Not Found']],
    'https://api.github.com/repos/gibt/esnicht' => [404, ['message' => 'Not Found']],
    'https://api.github.com/repos/zu/viel' => [403, ['message' => 'API rate limit exceeded']],
];
$GLOBALS['karten_github_attrappe'] = static function (string $url, ?string $ziel): array {
    [$status, $daten] = $GLOBALS['api'][$url] ?? [500, []];
    return ['status' => $status, 'body' => json_encode($daten)];
};
$info = karten_github_info(karten_github_repo('jonas-koeritz/Taktische-Zeichen'));
$check('Name, Lizenz, Adresse', $info['name'] === 'jonas-koeritz/Taktische-Zeichen' && $info['lizenz'] === 'CC-BY-4.0' && $info['url'] === 'https://github.com/jonas-koeritz/Taktische-Zeichen' && $info['release'] === 'v2.0.0');
$check('Release-ZIP zuerst, kein PDF, kein fremder Anhang', count($info['quellen']) === 2 && $info['quellen'][0] === ['art' => 'release', 'ref' => 'v2.0.0', 'name' => 'release.zip', 'bytes' => 66600671,
    'url' => 'https://github.com/jonas-koeritz/Taktische-Zeichen/releases/download/v2.0.0/release.zip']);
$check('Quelltext des Hauptzweigs', $info['quellen'][1]['art'] === 'zweig' && $info['quellen'][1]['ref'] === 'master'
    && $info['quellen'][1]['url'] === 'https://codeload.github.com/jonas-koeritz/Taktische-Zeichen/zip/refs/heads/master');
$ohne = karten_github_info(karten_github_repo('ohne/release'));
$check('ohne Release nur der Zweig, ohne Lizenz leer', count($ohne['quellen']) === 1 && $ohne['lizenz'] === '' && $ohne['lizenz_name'] === ''
    && str_ends_with($ohne['quellen'][0]['url'], '/zip/refs/heads/feature/neu'));
$check('unbekanntes Repository', str_contains($wirft(fn() => karten_github_info(karten_github_repo('gibt/esnicht'))), 'gibt es bei GitHub nicht'));
$check('Anfragegrenze verständlich', str_contains($wirft(fn() => karten_github_info(karten_github_repo('zu/viel'))), 'später noch einmal'));

/* ---------- Paket holen ---------- */
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$baue = static function (string $pfad) use ($svgGut, $png): void {
    $z = new ZipArchive();
    $z->open($pfad, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $z->addFromString('svg/THW_Fahrzeuge/GKW.svg', $svgGut);
    $z->addFromString('svg/THW_Fahrzeuge/MTW_OV.svg', str_replace('GKW', 'MTW', $svgGut));
    $z->addFromString('svg/THW_Fahrzeuge/Schlecht.svg', str_replace('<title>', '<script>alert(1)</script><title>', $svgGut));
    $z->addFromString('svg/THW_Fahrzeuge/Leer.svg', '');
    $z->addFromString('svg/Personen/Helfer.svg', $svgGut);
    $z->addFromString('png/256/THW_Fahrzeuge/GKW.png', $png);
    $z->addFromString('png/256/THW_Fahrzeuge/Falsch.png', 'das ist kein Bild');
    $z->addFromString('README.md', '# Zeichen');
    $z->addFromString('.github/logo.svg', $svgGut);
    $z->addFromString('symbols/THW_Fahrzeuge/GKW.j2', 'Vorlage');
    $z->close();
};
$GLOBALS['paket'] = 'zip';
$GLOBALS['karten_github_attrappe'] = static function (string $url, ?string $ziel) use ($baue): array {
    $GLOBALS['geholt'][] = $url;
    if ($GLOBALS['paket'] === '404') { file_put_contents((string)$ziel, 'Not Found'); return ['status' => 404]; }
    if ($GLOBALS['paket'] === 'html') { file_put_contents((string)$ziel, '<html>Wartung</html>'); return ['status' => 200]; }
    $baue((string)$ziel);
    return ['status' => 200, 'bytes' => (int)filesize((string)$ziel)];
};
$importDir = karten_github_dir();
$check('Pakete liegen in der Ablage der Symbole', str_starts_with($importDir, $tmp) && is_dir($importDir));
$GLOBALS['paket'] = '404';
$check('nicht geliefert: Fehler, nichts bleibt liegen', str_contains($wirft(fn() => karten_github_laden($info['quellen'][0])), 'HTTP 404') && (glob($importDir . '/*') ?: []) === []);
$GLOBALS['paket'] = 'html';
$check('kein ZIP: Fehler, nichts bleibt liegen', str_contains($wirft(fn() => karten_github_laden($info['quellen'][0])), 'kein ZIP') && (glob($importDir . '/*') ?: []) === []);
$check('zu großes Release wird nicht erst geholt', str_contains($wirft(fn() => karten_github_laden(['bytes' => KARTEN_GITHUB_MAX_BYTES + 1] + $info['quellen'][0])), 'größer als erlaubt'));
$GLOBALS['paket'] = 'zip';
$GLOBALS['geholt'] = [];
$zip = karten_github_laden($info['quellen'][0]);
$pfad = karten_github_zip_pfad($zip);
$check('Paket geholt, eigener Name', (bool)preg_match('/^[a-f0-9]{24}\.zip$/', $zip) && $pfad !== null && is_file($pfad) && $GLOBALS['geholt'] === [$info['quellen'][0]['url']]);
$check('fremde Namen führen zu keinem Paket', karten_github_zip_pfad('../' . $zip) === null && karten_github_zip_pfad('x.zip') === null && karten_github_zip_pfad('') === null
    && karten_github_zip_pfad(str_repeat('a', 24) . '.zip') === null);

/* ---------- Durchsicht ---------- */
$inhalt = karten_github_zip_inhalt((string)$pfad);
$check('Ordner mit Symbolen, ohne Beiwerk', array_keys($inhalt['ordner']) === ['png/256/THW_Fahrzeuge', 'svg/Personen', 'svg/THW_Fahrzeuge']);
$check('Zählung je Ordner und gesamt', $inhalt['ordner']['svg/THW_Fahrzeuge'] === ['svg' => 4, 'bild' => 0] && $inhalt['ordner']['png/256/THW_Fahrzeuge'] === ['svg' => 0, 'bild' => 2]
    && $inhalt['svg'] === 5 && $inhalt['bild'] === 2 && $inhalt['abgeschnitten'] === false);

/* ---------- Übernehmen ---------- */
$quelle = ['repo' => 'jonas-koeritz/Taktische-Zeichen', 'ref' => 'v2.0.0', 'lizenz' => 'CC0-1.0'];
$GLOBALS['inserts'] = [];
$e = karten_github_importieren((string)$pfad, ['svg/THW_Fahrzeuge'], 'svg', 'TZ', $quelle, $ich);
$neu = array_column(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'karten_symbole'), 1);
$check('zwei saubere SVG übernommen', $e['neu'] === 2 && count($neu) === 2 && array_column($neu, 'name') === ['GKW', 'MTW OV']);
$check('Gruppe, Quelle, Lizenz, Typ am Symbol', $neu[0]['gruppe'] === 'TZ THW Fahrzeuge' && $neu[0]['quelle'] === 'github:jonas-koeritz/Taktische-Zeichen@v2.0.0' && $neu[0]['lizenz'] === 'CC0-1.0'
    && $neu[0]['mime'] === 'image/svg+xml' && $neu[0]['orig_name'] === 'GKW.svg' && $neu[0]['uploaded_by'] === 1);
$check('Datei liegt unter eigenem Namen in der Ablage', (bool)preg_match('/^\d{8}_\d{6}_[a-f0-9]{16}\.svg$/', $neu[0]['stored_name'])
    && file_get_contents(karten_symbol_dir() . '/' . $neu[0]['stored_name']) === $svgGut);
$check('Skript und leere Datei abgelehnt, mit Grund', array_sum($e['abgelehnt']) === 2 && isset($e['abgelehnt']['sie enthält Skript']) && isset($e['abgelehnt']['die Datei ist leer'])
    && count($e['beispiele']) === 2 && str_starts_with($e['beispiele'][0], 'Schlecht.svg: '));
$check('nicht gewählte Ordner bleiben draußen', !in_array('Helfer', array_column($neu, 'name'), true));
$check('nach schon Vorhandenem wurde mit der Quelle gefragt', $GLOBALS['quelleGefragt'] === 'github:jonas-koeritz/Taktische-Zeichen@v2.0.0');

$GLOBALS['vorhanden'] = [['gruppe' => 'TZ THW Fahrzeuge', 'name' => 'gkw'], ['gruppe' => 'tz thw fahrzeuge', 'name' => 'MTW OV']];
$GLOBALS['inserts'] = [];
$e = karten_github_importieren((string)$pfad, ['svg/THW_Fahrzeuge'], 'svg', 'TZ', $quelle, $ich);
$check('zweiter Durchgang legt nichts doppelt an', $e['neu'] === 0 && $e['doppelt'] === 2 && array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'karten_symbole') === []);
$GLOBALS['vorhanden'] = [];

$GLOBALS['inserts'] = [];
$e = karten_github_importieren((string)$pfad, ['png/256/THW_Fahrzeuge', 'svg/Personen'], 'beides', '', $quelle, $ich);
$neu = array_column(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'karten_symbole'), 1);
$check('Bilder und SVG aus zwei Ordnern', $e['neu'] === 2 && array_column($neu, 'gruppe') === ['Personen', 'THW Fahrzeuge'] && str_starts_with($neu[1]['mime'], 'image/') && $neu[1]['mime'] !== 'image/svg+xml');
$check('falsches Bild abgelehnt', ($e['abgelehnt']['die Datei trägt eine Bild-Endung, ist aber kein solches Bild'] ?? 0) === 1);
$check('Bild liegt in der Ablage, keine Rohdatei bleibt', is_file(karten_symbol_dir() . '/' . $neu[1]['stored_name']) && (glob(karten_symbol_dir() . '/*.roh') ?: []) === []);
$GLOBALS['inserts'] = [];
$e = karten_github_importieren((string)$pfad, ['png/256/THW_Fahrzeuge', 'svg/Personen'], 'bild', '', $quelle, $ich);
$check('nur Bilder', $e['neu'] === 1 && count(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'karten_symbole')) === 1);
$GLOBALS['inserts'] = [];
$e = karten_github_importieren((string)$pfad, ['gibt/es/nicht', '../..'], 'beides', '', $quelle, $ich);
$check('unbekannter Ordner: nichts', $e['neu'] === 0 && $e['abgelehnt'] === []);

/* ---------- Aufräumen ---------- */
touch((string)$pfad, time() - 90000);
$frisch = karten_github_laden($info['quellen'][0]);
$check('liegengebliebene Pakete verschwinden, frische bleiben', karten_github_aufraeumen() === 1 && karten_github_zip_pfad($zip) === null && karten_github_zip_pfad($frisch) !== null);
karten_github_zip_weg($frisch);
$check('Paket verworfen', karten_github_zip_pfad($frisch) === null && (glob($importDir . '/*.zip') ?: []) === []);
unset($GLOBALS['karten_github_attrappe']);

/* ---------- Taktisches Zeichen je Fahrzeug ---------- */
$symbole = [['id' => 5, 'name' => 'GKW <7t>', 'gruppe' => 'THW Fahrzeuge'], ['id' => 6, 'name' => 'MTW', 'gruppe' => 'THW Fahrzeuge'], ['id' => 7, 'name' => 'Lose', 'gruppe' => '']];
$opt = karten_symbol_optionen($symbole, 6);
$check('Auswahl nach Gruppen, gewähltes markiert', str_contains($opt, '<optgroup label="THW Fahrzeuge">') && str_contains($opt, '<optgroup label="Ohne Gruppe">')
    && (bool)preg_match('/<option value="6"[^>]* selected>MTW</', $opt) && substr_count($opt, ' selected') === 1 && str_starts_with($opt, '<option value="">– kein Zeichen –</option>'));
$check('Auswahl: Bildadresse je Option, Name maskiert', str_contains($opt, 'p=karten_symbol&amp;id=5') && str_contains($opt, 'GKW &lt;7t&gt;') && !str_contains($opt, '<7t>'));
$fz = karten_fahrzeug_punkte([
    ['id' => 7, 'bezeichnung' => 'GKW', 'funkrufname' => '', 'geo_lat' => '53.0793', 'geo_lng' => '8.8017', 'geo_at' => null, 'geo_quelle' => '', 'symbol_id' => 5],
    ['id' => 8, 'bezeichnung' => 'MTW', 'funkrufname' => '', 'geo_lat' => '53.1', 'geo_lng' => '8.9', 'geo_at' => null, 'geo_quelle' => '', 'symbol_id' => null],
]);
$check('Karte: Zeichen am Fahrzeug, sonst Punkt', str_contains($fz[0]['src'], 'p=karten_symbol&id=5') && $fz[1]['src'] === '');
$GLOBALS['fahrzeuge'] = [5 => 2];
$grund = karten_symbol_delete(['id' => 5, 'name' => 'GKW', 'stored_name' => 'x.svg']);
$check('zugeordnetes Symbol lässt sich nicht löschen', $grund !== null && str_contains($grund, '2 Fahrzeug(en)') && $GLOBALS['execs'] === []);
file_put_contents(karten_symbol_dir() . '/frei.svg', $svgGut);
$GLOBALS['gruppenSymbole'] = [['id' => 5, 'name' => 'GKW', 'stored_name' => 'x.svg'], ['id' => 9, 'name' => 'Frei', 'stored_name' => 'frei.svg'], ['id' => 10, 'name' => 'Gesetzt', 'stored_name' => 'y.svg']];
$GLOBALS['marker'] = [10 => 1];
$GLOBALS['execs'] = [];
$check('Gruppe löschen: Benutztes bleibt', karten_gruppe_loeschen('TZ THW Fahrzeuge') === [1, 2] && count($GLOBALS['execs']) === 1 && $GLOBALS['execs'][0][1] === [9]
    && !is_file(karten_symbol_dir() . '/frei.svg'));
$GLOBALS['fahrzeuge'] = [];
$GLOBALS['marker'] = [];

/* ---------- Ansichten ---------- */
$html = render_partial('karten_github', ['stand' => null, 'maxMb' => 300]);
$check('Schritt 1: Hinweis, Eingabe, noch kein Abruf', str_contains($html, 'bringt keine Symbole mit und holt nichts von selbst') && str_contains($html, 'name="repo"')
    && str_contains($html, 'value="pruefen"') && !str_contains($html, 'value="laden"'));
$html = render_partial('karten_github', ['stand' => ['schritt' => 'quelle', 'info' => $info], 'maxMb' => 60]);
$check('Schritt 2: Lizenz, Pakete, Bestätigung ist Pflicht', str_contains($html, 'CC-BY-4.0') && str_contains($html, 'release.zip') && str_contains($html, 'Quelltext des Zweigs master')
    && (bool)preg_match('/<input type="checkbox" id="lizenz_ok" name="lizenz_ok" value="1" required>/', $html));
$check('Schritt 2: Beschreibung maskiert, zu großes Paket gesperrt', str_contains($html, '&lt;b&gt;als&lt;/b&gt;') && !str_contains($html, '<b>als</b>')
    && (bool)preg_match('/id="quelle0"[^>]* disabled/', $html) && !preg_match('/id="quelle1"[^>]* disabled/', $html));
$html = render_partial('karten_github', ['stand' => ['schritt' => 'quelle', 'info' => $ohne], 'maxMb' => 300]);
$check('Schritt 2 ohne Lizenz: Warnung', str_contains($html, 'keine Lizenz erkannt'));
$html = render_partial('karten_github', ['stand' => ['schritt' => 'auswahl', 'info' => $info, 'zip' => 'x', 'quelle' => $info['quellen'][0], 'inhalt' => $inhalt, 'lizenz' => 'CC0-1.0'], 'maxMb' => 300]);
$check('Schritt 3: Ordner mit Nummern, nicht mit Pfaden als Wert', substr_count($html, 'name="ordner[]"') === 3 && str_contains($html, 'name="ordner[]" value="2"') && str_contains($html, 'svg/THW_Fahrzeuge')
    && !str_contains($html, 'value="svg/THW_Fahrzeuge"'));
$check('Schritt 3: Gruppe, Zählung, Lizenz, Verwerfen', str_contains($html, 'THW Fahrzeuge') && str_contains($html, 'CC0-1.0') && str_contains($html, 'value="importieren"') && str_contains($html, 'value="verwerfen"'));

$zeile = static fn(array $x): array => $x + ['id' => 5, 'name' => 'GKW', 'gruppe' => 'TZ THW Fahrzeuge', 'orig_name' => 'GKW.svg', 'size_bytes' => 4000, 'verwendet' => 0, 'fahrzeuge' => 0,
    'quelle' => 'github:jonas-koeritz/Taktische-Zeichen@v2.0.0', 'lizenz' => 'CC0-1.0'];
$liste = [$zeile([]), $zeile(['id' => 6, 'name' => 'MTW', 'fahrzeuge' => 2])];
$daten = ['gruppiert' => karten_symbole_gruppiert($liste), 'anzahl' => 2, 'gruppen' => ['TZ THW Fahrzeuge'], 'verwalten' => true, 'maxMb' => 10];
$html = render_partial('karten_symbole', $daten);
$check('Bibliothek: Knopf zu GitHub, Quelle, Lizenz, Fahrzeuge', str_contains($html, 'p=karten_github') && str_contains($html, 'Quelle GitHub jonas-koeritz/Taktische-Zeichen@v2.0.0')
    && str_contains($html, 'CC0-1.0') && str_contains($html, '2 Fahrzeug(e)'));
$check('Bibliothek: Gruppe löschen, bei wenigen offen', str_contains($html, 'value="gruppe_loeschen"') && str_contains($html, '<details open>'));
$html = render_partial('karten_symbole', ['verwalten' => false, 'anzahl' => 400] + $daten);
$check('Bibliothek ohne Pflegerecht: kein Abruf, kein Löschen; große Bibliothek zugeklappt', !str_contains($html, 'p=karten_github') && !str_contains($html, 'gruppe_loeschen') && !str_contains($html, '<details open>'));

/* ---------- Verdrahtung ---------- */
$lies = static fn(string $rel): string => (string)file_get_contents($app . '/' . $rel);
$check('Route, Bibliothek, Menü', str_contains($lies('public/index.php'), "'karten_github'      => 'karten_github'") && str_contains($lies('src/bootstrap.php'), "/lib/karten_github.php'")
    && str_contains($lies('src/lib/nav.php'), "'karten_symbole', 'karten_github']"));
$seite = $lies('src/pages/karten_github.php');
$check('Seite: nur die Leitung, Lizenz vor dem Holen, Zeit für große Pakete', str_contains($seite, "can('manage_karten')") && str_contains($seite, "post_bool('lizenz_ok')")
    && strpos($seite, "post_bool('lizenz_ok')") < strpos($seite, 'karten_github_laden(') && str_contains($seite, 'set_time_limit(900)'));
$check('Seite: Paket kommt aus der Sitzung, Ordner über Nummern', str_contains($seite, "\$stand['info']['quellen'][(int)post_int('quelle', -1)]") && str_contains($seite, '$alle[(int)$nr]')
    && !str_contains($seite, "post_str('url')"));
$check('Seite: Paket wird nach dem Übernehmen und Verwerfen gelöscht', substr_count($seite, 'karten_github_zip_weg(') >= 3);
$check('nichts von selbst: kein Abruf im Cron, im Start oder in der Einrichtung', !str_contains($lies('public/cron.php'), 'karten_github') && !str_contains($lies('src/cli/migrate.php'), 'karten_github')
    && !str_contains($lies('sql/seed.sql'), 'github.com') && !str_contains($lies('docker/rootfs/run.sh'), 'github'));
$lib = $lies('src/lib/karten_github.php');
$check('Abruf: nur https, keine automatische Weiterleitung, neutraler Name', str_contains($lib, 'CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS') && str_contains($lib, 'CURLOPT_FOLLOWLOCATION => false')
    && str_contains($lib, "CURLOPT_USERAGENT      => 'OV-Multitool'") && !str_contains($lib, 'extractTo'));
$abruf = $lies('src/pages/karten_symbol.php');
$check('Symbol-Abruf: Schrift aus der Datei erlaubt, sonst nichts', str_contains($abruf, "img-src data:; font-src data:; sandbox") && str_contains($abruf, "default-src \\'none\\'"));
$check('Symbol-Abruf: Zeichen eines Fahrzeugs auch mit Fahrzeugrecht', str_contains($abruf, "can('view_karten')") && str_contains($abruf, "can('view_vehicles')") && str_contains($abruf, 'FROM vehicles WHERE symbol_id = ?'));
$schema = $lies('sql/schema.sql');
$migrate = $lies('src/cli/migrate.php');
$check('Schema: Quelle, Lizenz, Index', str_contains($schema, "quelle       VARCHAR(200) NOT NULL DEFAULT ''") && str_contains($schema, "lizenz       VARCHAR(80)  NOT NULL DEFAULT ''") && str_contains($schema, 'KEY idx_ksym_quelle (quelle)'));
$check('Schema: Zeichen am Fahrzeug mit Index, der Fremdschlüssel kommt in der Wanderung', str_contains($schema, 'symbol_id         INT UNSIGNED NULL') && str_contains($schema, 'KEY idx_fz_symbol (symbol_id)')
    && !str_contains($schema, 'fk_fz_symbol'));
$check('Migration', str_contains($migrate, "\$merken('105_symbol_quelle_fahrzeugzeichen');") && str_contains($migrate, 'ADD COLUMN quelle VARCHAR(200)') && str_contains($migrate, 'ADD KEY idx_ksym_quelle (quelle)')
    && str_contains($migrate, 'ADD COLUMN symbol_id INT UNSIGNED NULL') && str_contains($migrate, 'ADD CONSTRAINT fk_fz_symbol'));
$fzLib = $lies('src/lib/vehicles.php');
$check('Fahrzeug speichert nur ein Symbol, das es gibt', str_contains($fzLib, "'symbol_id'         => vehicle_symbol_pruefen(post_int('symbol_id'))") && str_contains($fzLib, 'return karten_symbol_find($id) ? $id : null;'));
$check('Fahrzeugformular, Akte und Liste zeigen das Zeichen', str_contains($lies('views/vehicle_edit.php'), 'name="symbol_id" data-symbol-wahl="symbol_vorschau"')
    && str_contains($lies('views/vehicle.php'), 'class="fzzeichen"') && str_contains($lies('views/vehicles.php'), 'fzzeichen--klein'));
$check('Karte zeichnet das Zeichen, Vorschau im Formular', str_contains($lies('public/assets/js/karte.js'), 'lagekarte__fahrzeug-zeichen') && str_contains($lies('public/assets/js/app.js'), 'select[data-symbol-wahl]')
    && str_contains($lies('public/assets/css/app.css'), '.lagekarte__fahrzeug-zeichen'));
$check('Doku', str_contains($lies('DOCS.md'), '**Symbole von GitHub holen**') && str_contains($lies('DOCS.md'), '**Taktisches Zeichen je Fahrzeug**') && str_contains($lies('CHANGELOG.md'), 'Symbole von GitHub holen'));

/* ---------- aufräumen ---------- */
$weg = static function (string $dir) use (&$weg): void {
    foreach (glob($dir . '/*') ?: [] as $f) { is_dir($f) ? $weg($f) : @unlink($f); }
    @rmdir($dir);
};
$weg($tmp);

echo "$ok bestanden, $fail fehlgeschlagen\n";
