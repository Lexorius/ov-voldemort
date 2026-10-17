<?php
declare(strict_types=1);
/*
 * STAN: ZIP-Einträge prüfen, Paket sichten, als neue Version auspacken
 * (Nummer zählt hoch), Dateien nachreichen, Version löschen, Ansicht.
 */
session_start();
define('OVB_TEST_UPLOADS', true);
$GLOBALS['settings'] = ['upload_max_mb' => '10', 'stan_max_mb' => '1', 'stan_modul_name' => 'STAN'];
$GLOBALS['inserts'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['max'] = 2;
$GLOBALS['dateien_rows'] = [];
$GLOBALS['upload_dir'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ovb_stan_' . bin2hex(random_bytes(4));
@mkdir($GLOBALS['upload_dir']);
putenv('OVB_UPLOAD_DIR=' . $GLOBALS['upload_dir']);   // app_config() liest Umgebungsvariablen zuerst
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    if (str_contains($sql, 'FROM stan_dateien WHERE version_id')) { return $GLOBALS['dateien_rows']; }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'MAX(nummer)') ? $GLOBALS['max'] : $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 100 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function db_lock(string $name, int $warten = 0): bool { return true; }
function db_unlock(string $name): void {}
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung']; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'uploads', 'stan'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$u = ['id' => 1, 'role' => 'leitung'];

/* ---------- reine Helfer ---------- */
$check('Einträge: PDF im Ordner geht, Ordner, Pfad nach oben, absolut, Systemdateien, fremde Art, leer nicht',
    stan_eintrag_pruefen('STAN/Fachgruppe N/FGr N.pdf', 10) === null && stan_eintrag_pruefen('STAN/leer/', 0) === 'Ordner' && stan_eintrag_pruefen('../evil.pdf', 5) === 'Pfad nach oben'
    && stan_eintrag_pruefen('/etc/x.pdf', 5) === 'absoluter Pfad' && stan_eintrag_pruefen('C:\\x.pdf', 5) === 'absoluter Pfad' && stan_eintrag_pruefen('__MACOSX/._a.pdf', 5) === 'Systemdatei'
    && stan_eintrag_pruefen('STAN/.DS_Store', 5) === 'Systemdatei' && stan_eintrag_pruefen('STAN/bad.exe', 5) === 'Dateiart .exe nicht erlaubt' && stan_eintrag_pruefen('a.pdf', 0) === 'leer');
$baum = stan_baum([['pfad' => 'STAN/Zug/b.pdf', 'name' => 'b.pdf'], ['pfad' => 'a.pdf', 'name' => 'a.pdf'], ['pfad' => 'STAN/Zug/A.pdf', 'name' => 'A.pdf'], ['pfad' => 'STAN/x.pdf', 'name' => 'x.pdf']]);
$check('Baum nach Ordnern, Dateien natürlich sortiert', array_keys($baum) === ['', 'STAN', 'STAN/Zug'] && array_column($baum['STAN/Zug'], 'name') === ['A.pdf', 'b.pdf'] && $baum['']['0']['name'] === 'a.pdf');
$check('Versionstext', stan_version_text(['nummer' => 3, 'bemerkung' => ' Stand 10/2026 ']) === 'Version 3 – Stand 10/2026' && stan_version_text(['nummer' => 1]) === 'Version 1');

/* ---------- Paket sichten und einspielen ---------- */
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ovb_stan_test_' . bin2hex(random_bytes(4));
@mkdir($tmp);
$zipPfad = $tmp . '/stan.zip';
$z = new ZipArchive();
$z->open($zipPfad, ZipArchive::CREATE);
$z->addFromString('STAN/Fachgruppe N/FGr N.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n");
$z->addFromString('STAN/Lies mich.txt', "Stand 10/2026\n");
$z->addFromString('STAN/gefaelscht.pdf', "<html><body>kein pdf</body></html>");
$z->addFromString('__MACOSX/._FGr N.pdf', 'x');
$z->addFromString('STAN/.DS_Store', 'x');
$z->addFromString('STAN/bad.exe', 'MZ');
$z->addEmptyDir('STAN/leer');
$z->close();
$inhalt = stan_zip_lesen($zipPfad);
$check('Paket gesichtet: drei Dateien, drei übergangen, Ordner still übergangen', count($inhalt['dateien']) === 3 && count($inhalt['uebergangen']) === 3 && $inhalt['dateien'][0]['name'] === 'STAN/Fachgruppe N/FGr N.pdf'
    && in_array('Systemdatei', array_column($inhalt['uebergangen'], 'grund'), true) && in_array('Dateiart .exe nicht erlaubt', array_column($inhalt['uebergangen'], 'grund'), true));
file_put_contents($tmp . '/kein.zip', 'kein zip');
try { stan_zip_lesen($tmp . '/kein.zip'); $check('kein ZIP wirft', false); } catch (RuntimeException $e) { $check('kein ZIP wirft', str_contains($e->getMessage(), 'kein lesbares ZIP')); }

copy($zipPfad, $tmp . '/upload.zip');
$_FILES = ['paket' => ['name' => 'STAN_2026.zip', 'type' => 'application/zip', 'tmp_name' => $tmp . '/upload.zip', 'error' => UPLOAD_ERR_OK, 'size' => filesize($zipPfad)]];
$GLOBALS['inserts'] = [];
[$id, $fehler, $hinweise] = stan_version_aus_zip('paket', ' Stand 10/2026 ', $u);
$v = $GLOBALS['inserts'][0] ?? null;
$dateien = array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'stan_dateien'));
$check('Version angelegt: Nummer hochgezählt (2 → 3), Bemerkung, ZIP aufgehoben', $fehler === [] && $id === 101 && $v[0] === 'stan_versionen' && $v[1]['nummer'] === 3 && $v[1]['bemerkung'] === 'Stand 10/2026' && $v[1]['orig_name'] === 'STAN_2026.zip'
    && str_starts_with($v[1]['zip_name'], 'v003_') && is_file($GLOBALS['upload_dir'] . '/stan/' . $v[1]['zip_name']));
$check('Dateien ausgepackt: PDF und Text mit Pfad, Name, Typ; falsches PDF (HTML) abgelehnt', count($dateien) === 2 && $dateien[0][1]['pfad'] === 'STAN/Fachgruppe N/FGr N.pdf' && $dateien[0][1]['name'] === 'FGr N.pdf' && $dateien[0][1]['mime'] === 'application/pdf'
    && $dateien[0][1]['version_id'] === 101 && is_file($GLOBALS['upload_dir'] . '/stan/' . $dateien[0][1]['stored_name']) && $dateien[1][1]['mime'] === 'text/plain'
    && count($hinweise) === 2 && str_contains($hinweise[0], '3 Eintrag/Einträge übergangen') && str_contains($hinweise[1], 'gefaelscht.pdf'));
$_FILES = ['paket' => ['name' => 'x.pdf', 'tmp_name' => $tmp . '/upload.zip', 'error' => UPLOAD_ERR_OK, 'size' => 100]];
[$id2, $fehler] = stan_version_aus_zip('paket', '', $u);
$check('kein ZIP als Paket: abgelehnt', $id2 === null && str_contains($fehler[0], 'muss ein ZIP sein'));
$_FILES = ['paket' => ['name' => 'gross.zip', 'tmp_name' => $tmp . '/upload.zip', 'error' => UPLOAD_ERR_OK, 'size' => 1024 * 1024 + 1]];
[$id2, $fehler] = stan_version_aus_zip('paket', '', $u);
$check('über der STAN-Grenze (eigene Einstellung, nicht die der Anlagen): abgelehnt', $id2 === null && str_contains($fehler[0], 'zu groß') && stan_max_bytes() === 1024 * 1024 && upload_max_bytes() === 10 * 1024 * 1024);
$_FILES = [];
[$id2, $fehler] = stan_version_aus_zip('paket', '', $u);
$check('nichts angekommen: Hinweis', $id2 === null && str_contains($fehler[0], 'keine Datei an'));

/* ---------- nachreichen ---------- */
file_put_contents($tmp . '/nach.pdf', "%PDF-1.4\n%%EOF\n");
file_put_contents($tmp . '/boese.pdf', "<html>x</html>");
$_FILES = ['dateien' => ['name' => ['FGr N Anlage.pdf', 'boese.pdf', 'skript.php'], 'tmp_name' => [$tmp . '/nach.pdf', $tmp . '/boese.pdf', $tmp . '/nach.pdf'], 'error' => [0, 0, 0], 'size' => [20, 15, 20]]];
$GLOBALS['inserts'] = [];
[$n, $fehler] = stan_dateien_nachreichen(101, 'dateien', ' Fachgruppe N/ ', $u);
$check('nachreichen: eine abgelegt im Ordner, HTML-als-PDF und PHP abgelehnt', $n === 1 && count($fehler) === 2 && $GLOBALS['inserts'][0][1]['pfad'] === 'Fachgruppe N/FGr N Anlage.pdf' && $GLOBALS['inserts'][0][1]['version_id'] === 101
    && str_contains($fehler[0], 'nicht erlaubt') && str_contains($fehler[1], 'nicht erlaubt'));

/* ---------- löschen ---------- */
$GLOBALS['dateien_rows'] = array_map(static fn($d) => $d[1] + ['id' => 7], $dateien);
$GLOBALS['execs'] = [];
stan_version_loeschen(['id' => 101, 'nummer' => 3, 'bemerkung' => '', 'zip_name' => $v[1]['zip_name']]);
$sql = implode("\n", array_column($GLOBALS['execs'], 0));
$check('Version löschen: Dateien und ZIP weg, Zeilen gelöscht', !is_file($GLOBALS['upload_dir'] . '/stan/' . $dateien[0][1]['stored_name']) && !is_file($GLOBALS['upload_dir'] . '/stan/' . $v[1]['zip_name'])
    && substr_count($sql, 'DELETE FROM stan_dateien') === 2 && str_contains($sql, 'DELETE FROM stan_versionen'));

/* ---------- stückweiser Upload ---------- */
$check('Upload-Kennung: 32 Hex-Zeichen', stan_upload_id_ok(str_repeat('ab', 16)) && !stan_upload_id_ok('../x') && !stan_upload_id_ok(str_repeat('ab', 15)) && !stan_upload_id_ok(strtoupper(str_repeat('ab', 16))));
$kid = str_repeat('cd', 16);
$teile = ["%PDF-1.4\n", "1 0 obj\n", "%%EOF\n"];
$r = [];
foreach ($teile as $i => $t) {
    file_put_contents($tmp . '/teil', $t);
    $r[] = stan_teil_anfuegen($kid, $i, 3, 'Ordner\\STAN Anlage.pdf', $tmp . '/teil');
}
$check('Stücke der Reihe nach angehängt', $r[0]['ok'] && $r[0]['next'] === 1 && $r[2]['ok'] && $r[2]['next'] === 3 && stan_teile_fertig($kid)['size'] === strlen(implode('', $teile)));
file_put_contents($tmp . '/teil', 'x');
$check('Stück außer der Reihe: abgelehnt', !stan_teil_anfuegen(str_repeat('ef', 16), 1, 3, 'a.pdf', $tmp . '/teil')['ok'] && !stan_teil_anfuegen('kaputt', 0, 1, 'a.pdf', $tmp . '/teil')['ok'] && !stan_teil_anfuegen(str_repeat('ef', 16), 0, 0, 'a.pdf', $tmp . '/teil')['ok']);
file_put_contents($tmp . '/teil', 'x');
stan_teil_anfuegen(str_repeat('ef', 16), 0, 2, 'halb.pdf', $tmp . '/teil');
$check('unvollständig: nicht fertig', stan_teile_fertig(str_repeat('ef', 16)) === null && stan_teile_fertig('kaputt') === null);
file_put_contents($tmp . '/teil', "%PDF-1.4\n%%EOF\n");
$fertig = stan_teil_anfuegen(str_repeat('ef', 16), 1, 2, 'halb.pdf', $tmp . '/teil') ? stan_teile_fertig(str_repeat('ef', 16)) : null;
$check('vollständig: Datei mit Namen (nur Basisname) und Größe', $fertig !== null && $fertig['name'] === 'halb.pdf' && is_file($fertig['pfad']) && $fertig['size'] > 0);
$GLOBALS['inserts'] = [];
$check('zusammengesetzte Datei nachreichen', stan_datei_nachreichen_pfad(101, $fertig['pfad'], $fertig['name'], $fertig['size'], 'Zug') === null && $GLOBALS['inserts'][0][1]['pfad'] === 'Zug/halb.pdf' && !is_file($fertig['pfad']));
$alt = stan_tmp_dir() . '/' . str_repeat('ab', 16) . '.part';
file_put_contents($alt, 'alt');
touch($alt, time() - 90000);
$check('Liegengebliebenes wird weggeräumt', stan_teile_aufraeumen() >= 1 && !is_file($alt));
foreach (glob(stan_tmp_dir() . '/*') ?: [] as $f) { @unlink($f); }
@rmdir(stan_tmp_dir());

$check('kein GLOB_BRACE – gibt es auf Alpine nicht', !str_contains((string)file_get_contents($app . '/src/lib/stan.php'), 'GLOB_BRACE') && !str_contains((string)file_get_contents($app . '/src/pages/stan_upload.php'), 'GLOB_BRACE'));
$check('Stück-Schnittstelle fängt Fehler als JSON ab', substr_count((string)file_get_contents($app . '/src/pages/stan_upload.php'), 'catch (Throwable') === 2);

$check('Fehlerfänger lässt unterdrückte Warnungen durch', str_contains((string)file_get_contents($app . '/src/pages/stan_upload.php'), 'error_reporting() & $nr'));

/* ---------- Ansicht ---------- */
$versionen = [
    ['id' => 5, 'nummer' => 3, 'bemerkung' => 'Stand 10/2026', 'zip_name' => 'v003_x.zip', 'orig_name' => 'STAN_2026.zip', 'zip_bytes' => 1000, 'uploaded_by' => 1, 'created_at' => '2026-10-17 10:00:00', 'hochgeladen_von' => 'Thomas', 'anzahl' => 2, 'bytes' => 500],
    ['id' => 4, 'nummer' => 2, 'bemerkung' => '', 'zip_name' => '', 'orig_name' => 'alt.zip', 'zip_bytes' => 900, 'uploaded_by' => 1, 'created_at' => '2026-09-01 10:00:00', 'hochgeladen_von' => 'Thomas', 'anzahl' => 1, 'bytes' => 100],
];
$liste = [['id' => 21, 'version_id' => 5, 'pfad' => 'STAN/Fachgruppe N/FGr N.pdf', 'name' => 'FGr N.pdf', 'stored_name' => 'a.pdf', 'mime' => 'application/pdf', 'size_bytes' => 400], ['id' => 22, 'version_id' => 5, 'pfad' => 'Lies mich.txt', 'name' => 'Lies mich.txt', 'stored_name' => 'b.txt', 'mime' => 'text/plain', 'size_bytes' => 100]];
$html = render_partial('stan', ['title' => 'STAN', 'versionen' => $versionen, 'aktuell' => $versionen[0], 'gezeigt' => $versionen[0], 'dateien' => $liste, 'darf' => true, 'maxMb' => 10]);
$check('Ansicht: stückweiser Upload an beiden Formularen', substr_count($html, 'data-stueckweise=') === 2 && str_contains($html, 'data-ziel="paket"') && str_contains($html, 'data-ziel="nachreichen"') && str_contains($html, '16 MB je Anfrage'));
$check('Ansicht: Versionen als Chips mit „aktuell", Dateien nach Ordner, Links, Upload als Version 4', str_contains($html, 'Version 3 – Stand 10/2026') && str_contains($html, '>aktuell</span>') && str_contains($html, 'p=stan&amp;version=4')
    && str_contains($html, '📁 STAN/Fachgruppe N') && str_contains($html, 'p=stan_file&amp;id=21') && str_contains($html, 'Neues Paket als Version 4 hochladen') && str_contains($html, 'value="nachreichen"') && str_contains($html, 'value="version_weg"'));
$html = render_partial('stan', ['title' => 'STAN', 'versionen' => $versionen, 'aktuell' => $versionen[0], 'gezeigt' => $versionen[1], 'dateien' => [], 'darf' => false, 'maxMb' => 10]);
$check('Ansicht ohne Recht und ältere Version: keine Formulare, Hinweis „ältere Version"', !str_contains($html, '<form') && str_contains($html, 'ältere Version') && !str_contains($html, 'Neues Paket'));
$html = render_partial('stan', ['title' => 'STAN', 'versionen' => [], 'aktuell' => null, 'gezeigt' => null, 'dateien' => [], 'darf' => true, 'maxMb' => 10]);
$check('Ansicht leer: Aufforderung, Version 1', str_contains($html, 'es wird Version 1') && str_contains($html, 'Neues Paket als Version 1 hochladen'));

// aufräumen
foreach ([$tmp, $GLOBALS['upload_dir'] . '/stan', $GLOBALS['upload_dir']] as $d) {
    foreach (glob($d . '/*') ?: [] as $f) { if (is_file($f)) { @unlink($f); } }
    @rmdir($d);
}

echo "$ok bestanden, $fail fehlgeschlagen\n";
