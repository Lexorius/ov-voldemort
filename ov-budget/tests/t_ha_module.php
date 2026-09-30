<?php
declare(strict_types=1);
/*
 * MQTT-Export aller Module: Veranstaltungswerte, Sperrliste für alles,
 * was einem Mitleser des Brokers nützen würde.
 */
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'ov_name' => 'OV', 'ha_mqtt_basis' => 'ovbudget'];
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'webpush', 'events', 'mqtt', 'ha_export'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Veranstaltungen ---------- */
$jetzt = strtotime('2026-09-26 12:00:00');
$kommende = [
    ['id' => 1, 'titel' => 'Abgesagte Übung', 'beginn' => '2026-09-27 09:00:00', 'status' => 'abgesagt', 'gaesteliste' => 1],
    ['id' => 2, 'titel' => 'Helferversammlung', 'typ_label' => 'Versammlung', 'ort' => 'Unterkunft',
     'beginn' => '2026-10-03 18:00:00', 'status' => 'geplant', 'gaesteliste' => 1],
    ['id' => 3, 'titel' => 'Grillabend', 'beginn' => '2026-10-20 17:00:00', 'status' => 'geplant', 'gaesteliste' => 0,
     'teilnehmer_geplant' => 40, 'teilnehmer_ist' => null],
    ['id' => 4, 'titel' => 'Weit weg', 'beginn' => '2026-12-24 17:00:00', 'status' => 'geplant', 'gaesteliste' => 1],
];
$stats = static fn(array $e): array => ['zusagen' => 12, 'absagen' => 3, 'offen' => 5, 'personen' => 17, 'eingeladen' => 20];
$w = ha_veranstaltung_werte($kommende, $stats, $jetzt);
$check('abgesagte übersprungen', str_starts_with((string)$w['naechste'], '2026-10-03'));
$check('Titel, Art, Ort und Datum als Attribute', $w['info']['titel'] === 'Helferversammlung' && $w['info']['art'] === 'Versammlung'
    && $w['info']['ort'] === 'Unterkunft' && $w['info']['datum'] === '03.10.2026');
$check('Zahlen aus der Gästeliste', $w['zusagen'] === 12 && $w['offen'] === 5 && $w['personen'] === 17);
$check('zwei in 30 Tagen (nicht die abgesagte, nicht Dezember)', $w['in_30_tagen'] === 2);
$check('keine Gästenamen im Ergebnis', !isset($w['info']['gaeste']) && !array_key_exists('gaeste', $w));

$w = ha_veranstaltung_werte([$kommende[2]], $stats, $jetzt);
$check('ohne Gästeliste: geplante Zahl', $w['personen'] === 40 && $w['zusagen'] === null && $w['offen'] === null);
$check('Attribut sagt: ohne Gästeliste', $w['info']['gaesteliste'] === 'nein');

$w = ha_veranstaltung_werte([], $stats, $jetzt);
$check('nichts Kommendes: leer', $w['naechste'] === null && $w['in_30_tagen'] === 0 && $w['info'] === []);

/* ---------- Sperrliste ---------- */
$verboten = ['issi', 'opta', 'pin', 'puk', 'iccid', 'rufnummer', 'telefon', 'email', 'token', 'code', 'passw',
             'secret', 'schluessel', 'pem', 'name_', 'vorname', 'nachname', 'strasse', 'adresse', 'gast', 'lagerort'];
$treffer = [];
foreach (array_keys(ha_sensoren()) as $key) {
    foreach ($verboten as $v) { if (str_contains($key, $v)) { $treffer[] = $key; } }
}
$check('kein Sensor mit heiklem Schlüssel', $treffer === []);

$fz = ha_fahrzeug_werte(['id' => 3, 'bezeichnung' => 'GKW 1', 'status_label' => 'einsatzbereit', 'fms_status' => 2,
    'hu_bis' => '2027-01-01', 'sp_bis' => null, 'uvv_bis' => '2026-11-11', 'km_stand' => 12345, 'offene_auftraege' => 1,
    'kennzeichen' => 'THW 99020', 'funkrufname' => 'Heros 24/51', 'issi' => '2621234', 'opta' => 'NW HEROS',
    'fachgruppe_label' => 'B1', 'geo_lat' => null, 'geo_lng' => null]);
$check('UVV je Fahrzeug', $fz['uvv_bis'] === '2026-11-11');
$check('ISSI und OPTA nicht mehr im Export', !isset($fz['info']['issi']) && !isset($fz['info']['opta'])
    && !str_contains(json_encode($fz), '2621234') && !str_contains(json_encode($fz), 'NW HEROS'));
$check('Kennzeichen und Funkrufname bleiben', $fz['info']['kennzeichen'] === 'THW 99020');

/* ---------- Anmeldung ---------- */
$d = ha_discovery_messages('ovbudget', 'OV', '1.45.0', [], false);
$ids = array_map(static fn($m) => json_decode($m[1], true)['unique_id'], $d);
foreach (['funk_gesamt', 'funk_nicht_gemeldet', 'sim_kosten_monat', 'veranstaltung_naechste', 'veranstaltungen_30_tage',
          'ausgaben_jahr', 'saldo_jahr', 'sicherung_letzte', 'connectoren_gekoppelt'] as $k) {
    $check("angemeldet: $k", in_array('ovbudget_' . $k, $ids, true));
}
$cfg = json_decode(array_values(array_filter($d, static fn($m) => str_contains($m[0], '/veranstaltung_naechste/')))[0][1], true);
$check('Veranstaltung mit Attributen', str_contains($cfg['json_attributes_template'] ?? '', 'veranstaltung_info'));
$cfg = json_decode(array_values(array_filter($d, static fn($m) => str_contains($m[0], '/sicherung_letzte/')))[0][1], true);
$check('Sicherung als Diagnose und Zeitstempel', ($cfg['entity_category'] ?? '') === 'diagnostic' && $cfg['device_class'] === 'timestamp');
$cfg = json_decode(array_values(array_filter($d, static fn($m) => str_contains($m[0], '/sim_kosten_monat/')))[0][1], true);
$check('Kosten in Euro', $cfg['unit_of_measurement'] === 'EUR' && $cfg['device_class'] === 'monetary');
$check('Fahrzeugsensor UVV vorhanden', array_key_exists('uvv_bis', ha_fahrzeug_sensoren()));

echo "$ok bestanden, $fail fehlgeschlagen\n";
