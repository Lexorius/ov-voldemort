<?php
declare(strict_types=1);
/*
 * Entfernungen (!! TEST !!): Luftlinie, Adress-Schlüssel, Texte, Antworten von
 * Nominatim und OSRM, Zwischenspeicher mit Vormerken, Warteschlange mit
 * genau einer Anfrage je Abruf und Pause, Verwaltungsseite.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR'];
$GLOBALS['state'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['adressen'] = [];      // key => Zeile
$GLOBALS['routen'] = [];        // "lat,lng>lat,lng" => Zeile
$GLOBALS['heim'] = null;
$GLOBALS['dienststellen'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM geo_adressen WHERE adresse')) { return $GLOBALS['adressen'][$p[0]] ?? null; }
    if (str_contains($sql, "FROM geo_adressen WHERE status = 'offen'")) { foreach ($GLOBALS['adressen'] as $a) { if ($a['status'] === 'offen' && ($a['versuche'] ?? 0) < 3) { return $a; } } return null; }
    if (str_contains($sql, 'FROM geo_routen WHERE von_lat')) { return $GLOBALS['routen'][implode(',', $p)] ?? null; }
    if (str_contains($sql, "FROM geo_routen WHERE status = 'offen'")) { foreach ($GLOBALS['routen'] as $r) { if ($r['status'] === 'offen' && ($r['versuche'] ?? 0) < 3) { return $r; } } return null; }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'FROM settings WHERE skey') ? ($GLOBALS['state'][$p[0]] ?? $d) : $d; }
function db_exec(string $sql, array $p = []): int {
    $GLOBALS['execs'][] = [$sql, $p];
    if (str_contains($sql, 'INSERT INTO settings')) { $GLOBALS['state'][$p[0]] = $p[1]; }
    if (str_contains($sql, 'INSERT IGNORE INTO geo_adressen')) { $GLOBALS['adressen'][$p[0]] ??= ['id' => count($GLOBALS['adressen']) + 1, 'adresse' => $p[0], 'text' => $p[1], 'lat' => null, 'lng' => null, 'status' => 'offen', 'versuche' => 0]; }
    if (str_contains($sql, 'INSERT IGNORE INTO geo_routen')) { $GLOBALS['routen'][implode(',', $p)] ??= ['id' => count($GLOBALS['routen']) + 1, 'von_lat' => $p[0], 'von_lng' => $p[1], 'nach_lat' => $p[2], 'nach_lng' => $p[3], 'km' => null, 'minuten' => null, 'status' => 'offen', 'versuche' => 0]; }
    return 1;
}
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function db_lock(string $name, int $warten = 0): bool { return true; }
function db_unlock(string $name): void {}
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'admin']; }
function standort_find(?int $id): ?array { return $GLOBALS['heim'] && (int)$GLOBALS['heim']['id'] === $id ? $GLOBALS['heim'] : null; }
function thw_all(bool $nurAktive = false): array { return $GLOBALS['dienststellen']; }
function thw_anschrift(array $d): string { return implode(', ', array_filter([trim((string)$d['strasse']), trim(trim((string)$d['plz']) . ' ' . trim((string)$d['ort']))], 'strlen')); }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'geo'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$einstellen = static function (array $w): void { $GLOBALS['settings'] = array_merge($GLOBALS['settings'], $w); settings_reset_cache(); };
$HN = ['lat' => 49.1427, 'lng' => 9.2109];
$S = ['lat' => 48.7758, 'lng' => 9.1829];

/* ---------- reine Rechnung ---------- */
$km = geo_luftlinie_km($HN['lat'], $HN['lng'], $S['lat'], $S['lng']);
$check('Luftlinie Heilbronn–Stuttgart rund 41 km', $km > 40 && $km < 42 && geo_luftlinie_km(1, 1, 1, 1) === 0.0);
$check('Adress-Schlüssel', geo_adresse_normal("  Musterstraße 1,\n74072   Heilbronn ") === 'musterstraße 1, 74072 heilbronn' && geo_adresse_normal('') === '');
$check('Koordinaten aus Text', geo_koordinaten_aus_text('49.142345,9.218765') === ['lat' => 49.142345, 'lng' => 9.218765] && geo_koordinaten_aus_text('Neckarwiese') === null && geo_koordinaten_aus_text('99,9') === null);
$check('Minuten lesbar', geo_minuten_text(45) === '45 min' && geo_minuten_text(95) === '1 h 35 min' && geo_minuten_text(120) === '2 h');
$check('Text: fertig', geo_entfernung_text(['luftlinie_km' => 29.4, 'km' => 38.2, 'minuten' => 45, 'stand' => 'fertig']) === '38 km · etwa 45 min · (Luftlinie 29 km)');
$check('Text: wartet mit und ohne Luftlinie', geo_entfernung_text(['luftlinie_km' => 29.4, 'km' => null, 'minuten' => null, 'stand' => 'wartet']) === 'Luftlinie 29 km · Fahrstrecke wird ermittelt …'
    && geo_entfernung_text(['luftlinie_km' => null, 'km' => null, 'minuten' => null, 'stand' => 'wartet']) === 'Adresse wird gesucht …'
    && geo_entfernung_text(['luftlinie_km' => null, 'km' => null, 'minuten' => null, 'stand' => 'fehler']) === 'Adresse nicht gefunden'
    && geo_entfernung_text(['luftlinie_km' => null, 'km' => null, 'minuten' => null, 'stand' => 'aus']) === 'Entfernungen sind ausgeschaltet' && geo_entfernung_text(null) === '');
$t = geo_nominatim_parsen([['lat' => '49.1423450', 'lon' => '9.2187650', 'display_name' => 'Musterstraße 1, Heilbronn']]);
$check('Nominatim-Antwort', $t === ['lat' => 49.142345, 'lng' => 9.218765, 'treffer' => 'Musterstraße 1, Heilbronn'] && geo_nominatim_parsen([]) === null && geo_nominatim_parsen('x') === null);
$check('OSRM-Antwort', geo_osrm_parsen(['code' => 'Ok', 'routes' => [['distance' => 38240.6, 'duration' => 2711.2]]]) === ['km' => 38.2, 'minuten' => 45]);
try { geo_osrm_parsen(['code' => 'NoRoute', 'message' => 'Impossible route.']); $check('OSRM NoRoute wirft', false); } catch (GeoException $e) { $check('OSRM NoRoute wirft', str_starts_with($e->getMessage(), 'Keine Route')); }
try { geo_osrm_parsen(['code' => 'InvalidQuery']); $check('OSRM Unfug wirft', false); } catch (GeoException $e) { $check('OSRM Unfug wirft', str_contains($e->getMessage(), 'InvalidQuery')); }

/* ---------- Zwischenspeicher und Vormerken ---------- */
$check('aus: nichts vorgemerkt, Koordinaten im Text gehen trotzdem', geo_koordinaten('Musterstraße 1, 74072 Heilbronn')['stand'] === 'aus' && $GLOBALS['adressen'] === [] && geo_koordinaten('49.1,9.2')['stand'] === 'fertig' && geo_route($HN, $S)['stand'] === 'aus' && $GLOBALS['routen'] === []);
$check('ohne Unterkunft keine Entfernung', geo_heim() === null && geo_entfernung(['ziel' => 'x', 'koordinaten' => false]) === null && geo_dienststellen_entfernungen([['id' => 1, 'strasse' => 'A 1', 'plz' => '1', 'ort' => 'B']]) === []);
$einstellen(['geo_aktiv' => '1', 'geo_pause_sek' => '120']);
$k = geo_koordinaten('Musterstraße 1, 74072 Heilbronn');
$check('an: unbekannte Adresse vorgemerkt', $k['stand'] === 'wartet' && isset($GLOBALS['adressen']['musterstraße 1, 74072 heilbronn']) && $GLOBALS['adressen']['musterstraße 1, 74072 heilbronn']['text'] === 'Musterstraße 1, 74072 Heilbronn');
$check('zweimal fragen = einmal merken, wartet', geo_koordinaten('Musterstraße 1,  74072 Heilbronn')['stand'] === 'wartet' && count($GLOBALS['adressen']) === 1 && geo_koordinaten('  ')['stand'] === 'fehler');
$GLOBALS['state']['geo_heim_standort_id'] = '7';
$GLOBALS['heim'] = ['id' => 7, 'name' => 'Unterkunft', 'geo_lat' => '49.142700', 'geo_lng' => '9.210900'];
$e = geo_entfernung(['ziel' => 'Musterstraße 1, 74072 Heilbronn', 'koordinaten' => false]);
$check('Entfernung: Adresse wartet noch', $e['stand'] === 'wartet' && $e['luftlinie_km'] === null && $e['heim'] === 'Unterkunft');
$GLOBALS['adressen']['musterstraße 1, 74072 heilbronn'] = ['id' => 1, 'adresse' => 'musterstraße 1, 74072 heilbronn', 'text' => 'Musterstraße 1, 74072 Heilbronn', 'lat' => '48.775800', 'lng' => '9.182900', 'status' => 'fertig', 'versuche' => 1];
$e = geo_entfernung(['ziel' => 'Musterstraße 1, 74072 Heilbronn', 'koordinaten' => false]);
$check('Entfernung: Adresse da, Luftlinie sofort, Strecke vorgemerkt', $e['stand'] === 'wartet' && $e['luftlinie_km'] > 40 && $e['km'] === null && isset($GLOBALS['routen']['49.1427,9.2109,48.7758,9.1829']));
$GLOBALS['routen']['49.1427,9.2109,48.7758,9.1829'] = array_merge($GLOBALS['routen']['49.1427,9.2109,48.7758,9.1829'], ['status' => 'fertig', 'km' => '52.3', 'minuten' => 48]);
$e = geo_entfernung(['ziel' => '48.7758,9.1829', 'koordinaten' => true]);
$check('Entfernung: alles da', $e['stand'] === 'fertig' && $e['km'] === 52.3 && $e['minuten'] === 48 && geo_entfernung_text($e) === '52 km · etwa 48 min · (Luftlinie 41 km)');
$GLOBALS['dienststellen'] = [['id' => 1, 'strasse' => 'Musterstraße 1', 'plz' => '74072', 'ort' => 'Heilbronn'], ['id' => 2, 'strasse' => '', 'plz' => '', 'ort' => 'Stuttgart'], ['id' => 3, 'strasse' => 'Neue Str. 5', 'plz' => '70173', 'ort' => 'Stuttgart']];
$d = geo_dienststellen_entfernungen($GLOBALS['dienststellen']);
$check('Dienststellen: Entfernung je Anschrift, nur Ortsname übergangen, Neues vorgemerkt', array_keys($d) === [1, 3] && $d[1]['stand'] === 'fertig' && $d[3]['stand'] === 'wartet' && isset($GLOBALS['adressen']['neue str. 5, 70173 stuttgart']));
$check('Dienststellen vormerken zählt nur Neue', geo_dienststellen_vormerken() === 0);

/* ---------- Warteschlange: eine Anfrage, Pause ---------- */
$GLOBALS['anfragen'] = [];
$GLOBALS['geo_http_attrappe'] = static function (string $url): mixed {
    $GLOBALS['anfragen'][] = $url;
    if (str_contains($url, '/search?')) { return stripos($url, 'gibtsnicht') !== false ? [] : [['lat' => '48.7758', 'lon' => '9.1829', 'display_name' => 'Neue Straße 5, Stuttgart']]; }
    return ['code' => 'Ok', 'routes' => [['distance' => 52300, 'duration' => 2880]]];
};
$einstellen(['geo_aktiv' => '0']);
$jetzt = 1_800_000_000;
$check('aus: keine Anfrage', geo_warteschlange_abarbeiten($jetzt)['art'] === null && $GLOBALS['anfragen'] === []);
$einstellen(['geo_aktiv' => '1']);
$GLOBALS['state']['geo_letzte_anfrage'] = (string)($jetzt - 60);
$check('Pause noch nicht vorbei: keine Anfrage', !geo_pause_vorbei($jetzt) && geo_warteschlange_abarbeiten($jetzt)['art'] === null && $GLOBALS['anfragen'] === []);
$GLOBALS['state']['geo_letzte_anfrage'] = (string)($jetzt - 120);
$GLOBALS['execs'] = [];
$r = geo_warteschlange_abarbeiten($jetzt);
$sql = implode("\n", array_column($GLOBALS['execs'], 0));
$check('eine Adresse: Nominatim gefragt, Treffer gespeichert, Zeitpunkt gemerkt', $r['art'] === 'adresse' && $r['ok'] && count($GLOBALS['anfragen']) === 1 && str_starts_with($GLOBALS['anfragen'][0], 'https://nominatim.openstreetmap.org/search?q=Neue+Str.+5%2C+70173+Stuttgart&format=jsonv2&limit=1&countrycodes=de%2Cat%2Cch')
    && str_contains($sql, "SET status = 'fertig', lat = ?") && $GLOBALS['state']['geo_letzte_anfrage'] === (string)$jetzt && str_contains($r['text'], 'gefunden'));
$check('direkt danach: Pause, keine zweite Anfrage', geo_warteschlange_abarbeiten($jetzt + 30)['art'] === null && count($GLOBALS['anfragen']) === 1);
$GLOBALS['adressen']['neue str. 5, 70173 stuttgart']['status'] = 'fertig';
$GLOBALS['routen']['49.1427,9.2109,48.7758,9.1829']['status'] = 'offen';
$GLOBALS['execs'] = [];
$r = geo_warteschlange_abarbeiten($jetzt + 120);
$check('dann eine Strecke: OSRM-Adresse lng,lat;lng,lat, Ergebnis gespeichert', $r['art'] === 'route' && $r['ok'] && $GLOBALS['anfragen'][1] === 'https://router.project-osrm.org/route/v1/driving/9.210900,49.142700;9.182900,48.775800?overview=false'
    && str_contains(implode("\n", array_column($GLOBALS['execs'], 0)), "SET status = 'fertig', km = ?") && $GLOBALS['execs'][2][1][0] === 52.3 && $GLOBALS['execs'][2][1][1] === 48);
$GLOBALS['routen']['49.1427,9.2109,48.7758,9.1829']['status'] = 'fertig';
geo_koordinaten('Gibtsnicht 99, 00000 Nirgendwo');
$GLOBALS['execs'] = [];
$r = geo_warteschlange_abarbeiten($jetzt + 240);
$check('nicht gefunden: endgültig fehlgeschlagen, keine Wiederholung', $r['art'] === 'adresse' && !$r['ok'] && str_contains($r['text'], 'nicht gefunden') && str_contains(implode("\n", array_column($GLOBALS['execs'], 0)), "status = 'fehler', versuche = ?"));
$GLOBALS['adressen']['gibtsnicht 99, 00000 nirgendwo']['status'] = 'fehler';
$GLOBALS['geo_http_attrappe'] = static function (string $url): mixed { throw new GeoException('Der Dienst bremst (HTTP 429) – Pause verlängern.'); };
$GLOBALS['routen']['1,1,2,2'] = ['id' => 9, 'von_lat' => 1, 'von_lng' => 1, 'nach_lat' => 2, 'nach_lng' => 2, 'km' => null, 'minuten' => null, 'status' => 'offen', 'versuche' => 0];
$GLOBALS['execs'] = [];
$r = geo_warteschlange_abarbeiten($jetzt + 360);
$check('Dienst bremst: Strecke bleibt offen, Versuch gezählt', $r['art'] === 'route' && !$r['ok'] && str_contains($r['text'], '429') && str_contains(implode("\n", array_column($GLOBALS['execs'], 0)), "SET status = ?, versuche = ?") && $GLOBALS['execs'][2][1][0] === 'offen' && $GLOBALS['execs'][2][1][1] === 1);
$GLOBALS['routen']['1,1,2,2']['versuche'] = 2;
$GLOBALS['execs'] = [];
$r = geo_warteschlange_abarbeiten($jetzt + 480);
$check('dritter Fehlschlag: endgültig', !$r['ok'] && $GLOBALS['execs'][2][1][0] === 'fehler' && $GLOBALS['execs'][2][1][1] === 3);
$check('Pause eingegrenzt', ($einstellen(['geo_pause_sek' => '5']) ?? geo_pause_sek() === 30) && ($einstellen(['geo_pause_sek' => '99999']) ?? geo_pause_sek() === 3600));
$einstellen(['geo_pause_sek' => '120']);
$check('erneut versuchen setzt beide Tabellen zurück', geo_erneut_versuchen() === 2);

/* ---------- Verwaltungsseite ---------- */
$htmlA = render_partial('admin/geo', ['fehler' => '', 'hinweis' => '', 'aktiv' => true, 'heim' => ['id' => 7, 'name' => 'Unterkunft', 'lat' => 49.1, 'lng' => 9.2], 'plaetze' => [['id' => 7, 'pfad' => 'Unterkunft'], ['id' => 8, 'pfad' => 'Unterkunft › Hof']],
    'status' => ['adressen' => ['offen' => 2, 'fertig' => 5, 'fehler' => 1], 'routen' => ['offen' => 0, 'fertig' => 4, 'fehler' => 0], 'letzte_anfrage' => $jetzt, 'naechste_in' => 75],
    'fehlerliste' => [['text' => 'Gibtsnicht 99', 'fehler' => 'Adresse nicht gefunden', 'versuche' => 3, 'erledigt_am' => '2026-10-17 10:00:00']], 'pause' => 120, 'osrmUrl' => 'https://router.project-osrm.org', 'nominatimUrl' => 'https://nominatim.openstreetmap.org']);
$check('Verwaltungsseite: TEST-Kennzeichnung, Unterkunft gewählt, Zähler, Fehlerliste, Knöpfe', substr_count($htmlA, '!! TEST !!') >= 2 && str_contains($htmlA, 'value="7" selected') && str_contains($htmlA, 'Nächste frühestens in 75 Sekunden')
    && str_contains($htmlA, 'Gibtsnicht 99') && str_contains($htmlA, 'value="anfrage"') && str_contains($htmlA, 'value="dienststellen"') && str_contains($htmlA, 'value="erneut"') && str_contains($htmlA, 'router.project-osrm.org'));
$htmlA = render_partial('admin/geo', ['fehler' => '', 'hinweis' => '', 'aktiv' => false, 'heim' => null, 'plaetze' => [], 'status' => ['adressen' => ['offen' => 0, 'fertig' => 0, 'fehler' => 0], 'routen' => ['offen' => 0, 'fertig' => 0, 'fehler' => 0], 'letzte_anfrage' => 0, 'naechste_in' => 0],
    'fehlerliste' => [], 'pause' => 120, 'osrmUrl' => 'https://router.project-osrm.org', 'nominatimUrl' => 'https://nominatim.openstreetmap.org']);
$check('Verwaltungsseite aus: Hinweis, kein Platz mit Koordinaten, Knöpfe gesperrt', str_contains($htmlA, 'ausgeschaltet') && str_contains($htmlA, 'Kein Platz hat Koordinaten') && str_contains($htmlA, 'value="anfrage" disabled') && !str_contains($htmlA, 'value="erneut"'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
