<?php
declare(strict_types=1);
/* Karten: eingeblendete Fahrzeuge erneuern sich von selbst – Takt, Paket für den Browser, Verdrahtung */
session_start();
date_default_timezone_set('Europe/Berlin');
$GLOBALS['settings'] = ['waehrung' => 'EUR'];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung', 'display_name' => 'Tester']; }
function geo_heim(): ?array { return null; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'uploads', 'vehicle_files', 'standorte', 'karten'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Takt ---------- */
$check('Vorgabe 30 Sekunden', karten_fahrzeug_sekunden() === 30 && KARTEN_FAHRZEUG_SEKUNDEN === 30);
$check('in den Grenzen', karten_fahrzeug_sekunden(5) === 10 && karten_fahrzeug_sekunden(0) === 10 && karten_fahrzeug_sekunden(-3) === 10
    && karten_fahrzeug_sekunden(45) === 45 && karten_fahrzeug_sekunden('120') === 120 && karten_fahrzeug_sekunden(99999) === 600);
$check('Unsinn fällt auf die Vorgabe', karten_fahrzeug_sekunden('schnell') === 30 && karten_fahrzeug_sekunden('') === 30 && karten_fahrzeug_sekunden('14.6') === 15);
$GLOBALS['settings']['karten_fahrzeug_sekunden'] = '15';
settings_reset_cache();
$check('aus den Einstellungen', karten_fahrzeug_sekunden() === 15);
$karte = ['id' => 3, 'lat' => 53.0793, 'lng' => 8.8017, 'zoom' => 14, 'fahrzeuge' => 1];
$k = karten_konfig($karte, [], [], ['setzen' => false, 'verwalten' => false, 'fahrzeuge' => true]);
$check('der Browser bekommt den Takt', $k['fahrzeugSekunden'] === 15 && $k['fahrzeugeAn'] === true);
$GLOBALS['settings']['karten_fahrzeug_sekunden'] = '2';
settings_reset_cache();
$check('zu kurzer Takt wird angehoben', karten_konfig($karte, [], [], ['fahrzeuge' => true])['fahrzeugSekunden'] === 10);

/* ---------- Verdrahtung ---------- */
$lies = static fn(string $rel): string => (string)file_get_contents($app . '/' . $rel);
$js = $lies('public/assets/js/karte.js');
$holen = substr($js, (int)strpos($js, 'function fahrzeugeHolen()'), (int)strpos($js, 'function fahrzeugeSchalten()') - (int)strpos($js, 'function fahrzeugeHolen()'));
$check('Abruf im eingestellten Takt, nicht fest', str_contains($js, 'parseInt(K.fahrzeugSekunden, 10)') && str_contains($holen, 'window.setTimeout(fahrzeugeHolen, fzSekunden * 1000)')
    && !str_contains($js, '60000') && !str_contains($js, 'fzUhr'));
$check('Abruf baut die Fahrzeuge nicht neu auf', str_contains($holen, 'fahrzeugeZeigen(d.fahrzeuge)') && !str_contains($holen, 'fahrzeugeWeg()'));
$check('kein zweiter Abruf, solange einer läuft; im Hintergrund Ruhe', str_contains($holen, 'document.hidden || fzLaeuft') && str_contains($holen, 'fzLaeuft = false'));
$check('kein Zwischenspeicher des Browsers', str_contains($holen, "cache: 'no-store'"));
$check('Fehler lässt die Fahrzeuge stehen und versucht es weiter', str_contains($holen, 'nächster Versuch') && (int)strpos($holen, '.catch(') < (int)strrpos($holen, 'window.setTimeout(fahrzeugeHolen'));
$zeigen = substr($js, (int)strpos($js, 'function fahrzeugeZeigen('), (int)strpos($js, 'function fahrzeugeHolen()') - (int)strpos($js, 'function fahrzeugeZeigen('));
$check('bekannte Fahrzeuge werden angepasst, verschwundene entfernt', str_contains($zeigen, 'nachId[fz.id]') && str_contains($zeigen, 'markerEbene.removeChild(nachId[id].el)')
    && str_contains($zeigen, "classList.add('is-gleitend')") && str_contains($zeigen, "classList.remove('is-gleitend')"));
$check('geändertes Zeichen wird getauscht', str_contains($zeigen, 'f.d.src !== fz.src') && str_contains($js, 'function fahrzeugBild('));
$check('zurück auf der Seite und wieder im Netz wird sofort geholt', str_contains($js, "document.addEventListener('visibilitychange'") && str_contains($js, "window.addEventListener('online'"));
$css = $lies('public/assets/css/app.css');
$check('Gleiten nur nach einem Abruf, mit Rücksicht auf reduzierte Bewegung', str_contains($css, '.lagekarte__fahrzeug.is-gleitend { transition: left') && str_contains($css, 'prefers-reduced-motion: reduce')
    && !preg_match('/\.lagekarte__fahrzeug \{[^}]*transition/', $css));
$check('Stand mit Sekunden', str_contains($lies('src/pages/karten_api.php'), "'stand' => date('H:i:s')"));
$check('Einstellung mitgeliefert', str_contains($lies('sql/seed.sql'), "('karten_fahrzeug_sekunden','30',"));
$check('Doku', str_contains($lies('DOCS.md'), 'erneuert die') && str_contains($lies('CHANGELOG.md'), 'erneuern sich von selbst'));

/* ---------- Einheiten und Abschnitte im Takt ---------- */
$check('Lage: Vorgabe 15 Sekunden, Grenzen 5 bis 600', karten_lage_sekunden() === 15 && karten_lage_sekunden(1) === 5 && karten_lage_sekunden('45') === 45 && karten_lage_sekunden(99999) === 600
    && karten_lage_sekunden('x') === 15);
$GLOBALS['settings']['karten_lage_sekunden'] = '8';
settings_reset_cache();
$check('Lage: Takt aus den Einstellungen in das Paket', karten_konfig($karte, [], [], ['fahrzeuge' => false])['lageSekunden'] === 8);
unset($GLOBALS['settings']['karten_lage_sekunden']);
settings_reset_cache();
$lage = karten_lage(3);
$check('Lage: Einheiten, Abschnitte und Gesamtstärke in einem Paket', $lage['marker'] === [] && $lage['abschnitte'] === [] && $lage['staerke']['text'] === '0/0/0/0');
$api = $lies('src/pages/karten_api.php');
$check('Lage: Abruf per GET hinter dem Recht, die Karten zu sehen', str_contains($api, "get_str('action') === 'lage'") && strpos($api, "can('view_karten')") < strpos($api, "get_str('action') === 'lage'")
    && str_contains($api, 'karten_lage((int)$karte[\'id\'])'));
$lageJs = substr($js, (int)strpos($js, 'function lageUebernehmen('), (int)strpos($js, '// Zurück auf der Seite') - (int)strpos($js, 'function lageUebernehmen('));
$check('Skript: Lage im eingestellten Takt, ohne Zwischenspeicher, im Hintergrund Ruhe', str_contains($js, 'parseInt(K.lageSekunden, 10)') && str_contains($lageJs, "action=lage&karte_id=")
    && str_contains($lageJs, "cache: 'no-store'") && str_contains($lageJs, 'document.hidden || lageLaeuft') && str_contains($lageJs, 'window.setTimeout(lageHolen, lageSekunden * 1000)'));
$check('Skript: die eigene Hand gewinnt – gezogen, in der Tafel, ungespeichert, eben gesetzt oder gelöscht', str_contains($lageJs, 'm === gewaehlt || m.zieht || (offen && offen.m === m)')
    && str_contains($lageJs, 'm.seit > angefragt') && str_contains($lageJs, 'lageGeloescht[fremd.id]') && str_contains($js, 'lageGeloescht[m.d.id] = Date.now()') && str_contains($js, 'm.zieht = true;'));
$check('Skript: Abschnitte werden mitgeführt, Geändertes neu gezeichnet', str_contains($lageJs, 'abschnitte.length = 0') && str_contains($lageJs, 'if (geaendert) {'));
$check('Skript: zurück auf der Seite wird die Lage sofort geholt', preg_match("/visibilitychange', function \(\) \{\s*if \(document\.hidden\) \{ return; \}\s*lageHolen\(\);/", $js) === 1);
$check('Lage: Einstellung mitgeliefert, Doku', str_contains($lies('sql/seed.sql'), "('karten_lage_sekunden','15',") && str_contains($lies('DOCS.md'), 'Änderungen kommen überall an'));

/* ==================================================================== */
/* Fahrzeuge von Hand setzen                                             */
/* ==================================================================== */
$GLOBALS['gesetzt'] = [];
function vehicle_position_set(array $vehicle, float $lat, float $lng, ?float $genauigkeit, array $user, string $titel = 'Standort gesetzt'): ?string {
    $GLOBALS['gesetzt'][] = [(int)$vehicle['id'], $lat, $lng, $genauigkeit, (int)$user['id'], $titel];
    return $GLOBALS['setzFehler'] ?? null;
}
require $app . '/src/lib/divera_vehicles.php';
unset($GLOBALS['settings']['karten_fahrzeug_sekunden']);
settings_reset_cache();

/* ---------- Wartezeit ---------- */
$check('Vorgabe 5 Minuten', karten_fahrzeug_hand_minuten() === 5 && KARTEN_FAHRZEUG_HAND_MINUTEN === 5);
$check('Wartezeit in den Grenzen', karten_fahrzeug_hand_minuten(0) === 0 && karten_fahrzeug_hand_minuten(-4) === 0 && karten_fahrzeug_hand_minuten('15') === 15
    && karten_fahrzeug_hand_minuten(99999) === 1440 && karten_fahrzeug_hand_minuten('x') === 5);
$check('GPS sind Divera und der QR-Code', KARTEN_GPS_QUELLEN === ['divera', 'qr']);

/* ---------- Wann lässt sich ein Fahrzeug setzen ---------- */
$jetzt = strtotime('2026-11-13 12:00:00');
$fz = static fn(string $quelle, ?string $zeit, array $mehr = []): array => $mehr + ['id' => 7, 'bezeichnung' => 'GKW', 'funkrufname' => 'Heros 22/52', 'is_active' => 1,
    'geo_lat' => '53.079300', 'geo_lng' => '8.801700', 'geo_at' => $zeit, 'geo_quelle' => $quelle, 'symbol_id' => null];
$check('Divera vor 3 Minuten: meldet sich selbst', !karten_fahrzeug_setzbar($fz('divera', '2026-11-13 11:57:00'), $jetzt, 5));
$check('Divera vor genau 5 Minuten: noch fest', !karten_fahrzeug_setzbar($fz('divera', '2026-11-13 11:55:00'), $jetzt, 5));
$check('Divera vor 6 Minuten: setzbar', karten_fahrzeug_setzbar($fz('divera', '2026-11-13 11:54:00'), $jetzt, 5));
$check('QR-Code zählt als GPS', !karten_fahrzeug_setzbar($fz('qr', '2026-11-13 11:58:00'), $jetzt, 5) && karten_fahrzeug_setzbar($fz('qr', '2026-11-13 11:40:00'), $jetzt, 5));
$check('von Hand gesetzt: jederzeit wieder setzbar', karten_fahrzeug_setzbar($fz('mensch', '2026-11-13 11:59:30'), $jetzt, 5));
$check('ohne Position oder ohne Zeitpunkt: setzbar', karten_fahrzeug_setzbar($fz('divera', '2026-11-13 11:59:00', ['geo_lat' => null, 'geo_lng' => null]), $jetzt, 5)
    && karten_fahrzeug_setzbar($fz('divera', null), $jetzt, 5) && karten_fahrzeug_setzbar($fz('', '2026-11-13 11:59:00'), $jetzt, 5));
$check('Wartezeit 0: jederzeit', karten_fahrzeug_setzbar($fz('divera', '2026-11-13 11:59:59'), $jetzt, 0));

$p = karten_fahrzeug_punkte([$fz('divera', '2026-11-13 11:57:00'), $fz('qr', '2026-11-13 11:00:00', ['id' => 8])], $jetzt, 5);
$check('Karte: frisches fest, altes setzbar', $p[0]['setzbar'] === false && $p[1]['setzbar'] === true);
$p = karten_fahrzeug_punkte([$fz('qr', '2026-11-13 11:00:00')], $jetzt);
$check('wer nicht setzen darf, sieht nichts Setzbares', $p[0]['setzbar'] === false);

/* ---------- Setzen ---------- */
$ich = ['id' => 4, 'display_name' => 'Tester'];
$check('außer Dienst: nein', is_string(karten_fahrzeug_setzen($fz('mensch', null, ['is_active' => 0]), 53.1, 8.9, $ich, $jetzt)) && $GLOBALS['gesetzt'] === []);
$check('Stelle außerhalb der Karte: nein', is_string(karten_fahrzeug_setzen($fz('mensch', null), 95, 8.9, $ich, $jetzt)) && is_string(karten_fahrzeug_setzen($fz('mensch', null), 'x', '', $ich, $jetzt)) && $GLOBALS['gesetzt'] === []);
$grund = karten_fahrzeug_setzen($fz('divera', '2026-11-13 11:58:00'), 53.1, 8.9, $ich, $jetzt);
$check('meldet sich selbst: nein, mit Grund', is_string($grund) && str_contains($grund, 'Divera') && str_contains($grund, '5 Minuten') && $GLOBALS['gesetzt'] === []);
$grund = karten_fahrzeug_setzen($fz('qr', '2026-11-13 11:58:00'), 53.1, 8.9, $ich, $jetzt);
$check('QR-Code gerade gescannt: nein', is_string($grund) && str_contains($grund, 'QR-Code'));
$punkt = karten_fahrzeug_setzen($fz('divera', '2026-11-13 11:40:00'), '53.1234567', '8.9', $ich, $jetzt);
$check('nach 20 Minuten ohne Meldung: gesetzt, am Fahrzeug gespeichert', is_array($punkt) && $GLOBALS['gesetzt'] === [[7, 53.1234567, 8.9, null, 4, 'Standort auf der Karte gesetzt']]);
$check('Punkt für die Karte: neue Stelle, von Hand, weiter setzbar', $punkt['lat'] === 53.123457 && $punkt['lng'] === 8.9 && $punkt['stand'] === 'gerade eben · von Hand'
    && $punkt['setzbar'] === true && $punkt['alt'] === false && $punkt['id'] === 7);
$GLOBALS['gesetzt'] = [];
$check('ohne Position: gesetzt', is_array(karten_fahrzeug_setzen($fz('', null, ['geo_lat' => null, 'geo_lng' => null]), 53.1, 8.9, $ich, $jetzt)) && count($GLOBALS['gesetzt']) === 1);
$GLOBALS['setzFehler'] = 'Die übermittelte Position ist unbrauchbar.';
$check('Fehler beim Speichern kommt an', karten_fahrzeug_setzen($fz('mensch', null), 53.1, 8.9, $ich, $jetzt) === 'Die übermittelte Position ist unbrauchbar.');
unset($GLOBALS['setzFehler']);
$k = karten_konfig($karte, [], [], ['fahrzeuge' => true, 'fahrzeugSetzen' => true]);
$check('der Browser weiß, ob gesetzt werden darf', $k['fahrzeugSetzen'] === true && karten_konfig($karte, [], [], ['fahrzeuge' => true])['fahrzeugSetzen'] === false
    && karten_konfig($karte, [], [], ['fahrzeuge' => false, 'fahrzeugSetzen' => true])['fahrzeugSetzen'] === false);

/* ---------- Divera: Hand-Position bleibt, bis Divera etwas Neues meldet ---------- */
$st = ['id' => 55, 'fmsstatus' => 2, 'fmsstatus_ts' => $jetzt - 3600, 'lat' => 53.0793, 'lng' => 8.8017, 'crew' => []];
$unser = static fn(array $mehr): array => $mehr + ['id' => 7, 'fms_status' => 2, 'fms_at' => date('Y-m-d H:i:s', $jetzt - 3600), 'geo_lat' => '53.200000', 'geo_lng' => '8.700000',
    'geo_at' => date('Y-m-d H:i:s', $jetzt - 600), 'geo_quelle' => 'mensch', 'divera_lat' => '53.079300', 'divera_lng' => '8.801700'];
$d = dv_status_update($st, $unser([]), true, $jetzt)['daten'];
$check('Divera nennt denselben alten Stand: Hand-Position bleibt', !isset($d['geo_lat']) && !isset($d['geo_quelle']) && $d['divera_lat'] === 53.0793 && $d['divera_lng'] === 8.8017);
$d = dv_status_update(['lat' => 53.0801, 'lng' => 8.8025] + $st, $unser([]), true, $jetzt)['daten'];
$check('Divera nennt eine neue Position: sie gilt sofort', ($d['geo_lat'] ?? null) === 53.0801 && $d['geo_quelle'] === 'divera' && $d['divera_lat'] === 53.0801);
$d = dv_status_update(['lat' => 53.0801, 'lng' => 8.8025] + $st, $unser(['geo_quelle' => 'qr']), true, $jetzt)['daten'];
$check('QR-Meldung bleibt für ihre Schutzzeit, auch wenn Divera Neues nennt', !isset($d['geo_lat']) && $d['divera_lat'] === 53.0801);
$d = dv_status_update(['lat' => 53.0801, 'lng' => 8.8025] + $st, $unser(['divera_lat' => null, 'divera_lng' => null]), true, $jetzt)['daten'];
$check('Diveras alter Stand unbekannt: Hand-Position bleibt, Stand wird gemerkt', !isset($d['geo_lat']) && $d['divera_lat'] === 53.0801);
$d = dv_status_update($st, $unser(['geo_at' => date('Y-m-d H:i:s', $jetzt - 13 * 3600)]), true, $jetzt)['daten'];
$check('nach der Schutzzeit gilt Divera wieder', ($d['geo_quelle'] ?? '') === 'divera');
$d = dv_status_update($st, $unser(['geo_quelle' => 'divera']), true, $jetzt)['daten'];
$check('Divera-Position wird wie bisher bei jedem Abruf erneuert', ($d['geo_quelle'] ?? '') === 'divera' && $d['geo_at'] === date('Y-m-d H:i:s', $jetzt));
$d = dv_status_update($st, $unser([]), false, $jetzt)['daten'];
$check('Position abgeschaltet: nichts wird gemerkt', !isset($d['geo_lat']) && !array_key_exists('divera_lat', $d));
$d = dv_status_update(['lat' => 0, 'lng' => 0] + $st, $unser(['geo_quelle' => 'divera']), true, $jetzt)['daten'];
$check('Divera ohne Position: nichts ändert sich', !isset($d['geo_lat']) && !array_key_exists('divera_lat', $d));

/* ---------- Verdrahtung ---------- */
$dv = $lies('src/lib/divera_vehicles.php');
$check('der Abgleich liest die Quelle der Position mit – sonst greift kein Schutz', (bool)preg_match('/SELECT id, bezeichnung,[^;]*geo_quelle, divera_lat, divera_lng,[^;]*FROM vehicles WHERE is_active = 1/s', $dv));
$api = $lies('src/pages/karten_api.php');
$check('Schnittstelle: Setzen braucht Fahrzeug- und Melderecht, nicht das Symbolrecht', str_contains($api, "\$action === 'fahrzeug_setzen'") && str_contains($api, "!can('view_vehicles') || !can('report_vehicle')")
    && strpos($api, "\$action === 'fahrzeug_setzen'") < strpos($api, "if (!can('mark_karten'))"));
$check('Schnittstelle: Liste ohne Position nur für die, die setzen dürfen', str_contains($api, "'ohne' => \$setzen ? karten_fahrzeuge_ohne() : []") && str_contains($api, "karten_fahrzeuge(null, \$setzen)"));
$check('Schnittstelle: Setzen nur per POST', strpos($api, "\$action === 'fahrzeug_setzen'") > strpos($api, "\$_SERVER['REQUEST_METHOD'] !== 'POST'"));
$check('Seite und Ansicht', str_contains($lies('src/pages/karte.php'), "'fahrzeugSetzen' => can('view_vehicles') && can('report_vehicle')") && str_contains($lies('views/karte.php'), 'data-karte-fahrzeug-setzen'));
$check('Karte: ziehen nur, was der Server freigibt; Klick öffnet weiter das Fahrzeug', str_contains($js, '!K.fahrzeugSetzen || !f.d.setzbar') && str_contains($js, "action: 'fahrzeug_setzen', id: f.d.id")
    && str_contains($js, 'if (f.gezogen) { ev.preventDefault();') && str_contains($js, 'if (f.zieht) { fz.lat = f.d.lat;'));
$check('Karte: nicht gespeichert heißt zurück an die alte Stelle', str_contains($js, 'f.d.lat = s.lat;') && str_contains($js, 'function fahrzeugMenue('));
$check('Aussehen', str_contains($css, '.lagekarte__fahrzeug.is-setzbar') && str_contains($css, '.lagekarte__fahrzeug.is-gezogen'));
$schema = $lies('sql/schema.sql');
$wanderung = $lies('src/cli/migrate.php');
$check('Schema und Migration', str_contains($schema, 'divera_lat        DECIMAL(9,6) NULL') && str_contains($schema, 'divera_lng        DECIMAL(9,6) NULL')
    && str_contains($wanderung, "\$merken('106_divera_position');") && str_contains($wanderung, 'ADD COLUMN divera_lat DECIMAL(9,6) NULL'));
$check('Einstellung und Doku', str_contains($lies('sql/seed.sql'), "('karten_fahrzeug_hand_minuten','5',") && str_contains($lies('DOCS.md'), '**Fahrzeug von Hand setzen**')
    && str_contains($lies('CHANGELOG.md'), 'Fahrzeuge von Hand setzen'));
$check('Fahrzeug: Titel im Journal wählbar', str_contains($lies('src/lib/vehicles.php'), "array \$user, string \$titel = 'Standort gesetzt'): ?string") && str_contains($lies('src/lib/vehicles.php'), "'titel'    => \$titel,"));

echo "$ok bestanden, $fail fehlgeschlagen\n";
