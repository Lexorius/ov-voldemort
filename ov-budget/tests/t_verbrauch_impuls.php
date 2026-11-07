<?php
declare(strict_types=1);
/*
 * Verbrauch – Impulszähler aus Home Assistant an einem Handzähler:
 * Eichpunkt beim Ablesen, Rechnung vom Eichpunkt aus, Neustart des Sensors,
 * Nachtrag aus der Geschichte, Abruf und Speichern des Zählers.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'verbrauch_modul_name' => 'Verbrauch', 'verbrauch_intro' => '',
    'verbrauch_ha_intervall_minuten' => '60'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['state'] = [];
$GLOBALS['vorher'] = null;      // reading_add: der Stand vor dem Zeitpunkt
$GLOBALS['letzter'] = null;     // jüngster Stand überhaupt
$GLOBALS['mit_wert'] = null;    // jüngster Stand mit Sensorwert
$GLOBALS['eich'] = null;        // jüngster Eichpunkt (von Hand / QR mit Sensorwert)
$GLOBALS['ha'] = [];            // Pfad => Antwort; fehlt er, wirft ha_api
$GLOBALS['ha_pfade'] = [];
$GLOBALS['sqls'] = [];
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { $GLOBALS['sqls'][] = $sql; return $GLOBALS['rows'] ?? []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM meters m')) { return $GLOBALS['parent'] ?? null; }
    if (str_contains($sql, 'FROM meter_readings')) {
        if (str_contains($sql, 'gelesen_am <= ?')) { return $GLOBALS['vorher']; }
        if (str_contains($sql, "quelle IN ('manuell','qr')")) { return $GLOBALS['eich']; }
        if (str_contains($sql, 'impuls_wert IS NOT NULL')) { return $GLOBALS['mit_wert']; }
        return $GLOBALS['letzter'];
    }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, 'FROM settings')) { return $GLOBALS['state'][$p[0] ?? ''] ?? $d; }
    return $d;
}
function db_exec(string $sql, array $p = []): int {
    if (str_contains($sql, 'INSERT INTO settings')) { $GLOBALS['state'][$p[0]] = (string)$p[1]; }
    return 1;
}
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
function ha_api(string $pfad, ?array $body = null, int $timeout = 10): array {
    $GLOBALS['ha_pfade'][] = $pfad;
    $key = explode('?', $pfad)[0];
    if (!array_key_exists($key, $GLOBALS['ha'])) { throw new RuntimeException('Home Assistant nicht erreichbar'); }
    return $GLOBALS['ha'][$key];
}
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'verbrauch'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$nah = static fn(?float $a, float $b): bool => $a !== null && abs($a - $b) < 0.0005;
$staende = static fn() => array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'meter_readings'));

$wasser = ['id' => 7, 'art' => 'wasser', 'rolle' => 'bezug', 'name' => 'Wasser Unterkunft', 'einheit' => 'm³', 'umrechnung' => 1,
           'quelle' => 'manuell', 'ha_entity' => '', 'ha_faktor' => 1, 'impuls_entity' => 'sensor.wasser_impulse', 'impuls_faktor' => 0.001];
$ohne = array_merge($wasser, ['impuls_entity' => '']);
$haZaehler = array_merge($wasser, ['quelle' => 'ha', 'ha_entity' => 'sensor.wasser_stand', 'impuls_entity' => 'sensor.egal']);

/* ---------- Reine Funktionen ---------- */
$check('Impulszähler aktiv nur bei Handzähler mit Entität', meter_impuls_aktiv($wasser) && !meter_impuls_aktiv($ohne) && !meter_impuls_aktiv($haZaehler));
$check('mit Home Assistant: Quelle ha oder Impulszähler', meter_mit_ha($wasser) && meter_mit_ha($haZaehler) && !meter_mit_ha($ohne));
$check('Sensorwert als Zahl', impuls_zahl('12345') === 12345.0 && impuls_zahl(' 12.5 ') === 12.5 && impuls_zahl('unknown') === null
    && impuls_zahl('unavailable') === null && impuls_zahl('') === null && impuls_zahl(null) === null);
$check('Quelle „Impulszähler" bekannt', READING_QUELLEN['impuls'] === 'Impulszähler');

$eich = ['stand' => 1234.567, 'impuls_wert' => 50000];
$check('Normalfall: Eichstand + Zuwachs × Faktor', $nah(impuls_stand($eich, null, 51234.0, 0.001), 1235.801));
$check('Normalfall rechnet vom Eichpunkt, nicht vom letzten Zwischenstand',
    $nah(impuls_stand($eich, ['stand' => 1235.0, 'impuls_wert' => 50433], 51234.0, 0.001), 1235.801));
$check('Sensor unter dem letzten Wert: neu angefangen, Wert ist der Zuwachs seit dem letzten Stand',
    $nah(impuls_stand($eich, ['stand' => 1235.801, 'impuls_wert' => 51234], 120.0, 0.001), 1235.921));
$check('nach dem Neustart geht es vom letzten Stand weiter, nicht vom Eichpunkt',
    $nah(impuls_stand($eich, ['stand' => 1235.921, 'impuls_wert' => 120], 620.0, 0.001), 1236.421));
$check('Faktor 1: Sensor zählt schon in der Zählereinheit', $nah(impuls_stand(['stand' => 100, 'impuls_wert' => 10], null, 15.0, 1.0), 105.0));
$check('ohne Zuwachs bleibt der Eichstand', $nah(impuls_stand($eich, null, 50000.0, 0.001), 1234.567));

/* ---------- Zähler speichern ---------- */
$_POST = ['name' => 'Wasser Unterkunft', 'art' => 'wasser', 'quelle' => 'manuell', 'einheit' => '', 'umrechnung' => '', 'ha_faktor' => '',
          'impuls_entity' => 'sensor.wasser_impulse', 'impuls_faktor' => '0,001', 'is_active' => '1'];
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$d = end($GLOBALS['inserts'])[1];
$check('Handzähler mit Impulszähler gespeichert', $fehler === [] && $d['impuls_entity'] === 'sensor.wasser_impulse' && (float)$d['impuls_faktor'] === 0.001 && $d['quelle'] === 'manuell');
$_POST['impuls_entity'] = 'kein sensor';
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$check('falsche Entität des Impulszählers abgewiesen', $id === null && count($fehler) === 1 && str_contains($fehler[0], 'Impulszähler'));
$_POST['impuls_entity'] = '';
$_POST['impuls_faktor'] = '';
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$d = end($GLOBALS['inserts'])[1];
$check('ohne Entität: kein Impulszähler, Faktor 1', $fehler === [] && $d['impuls_entity'] === '' && (float)$d['impuls_faktor'] === 1.0);
$_POST['impuls_entity'] = 'sensor.wasser_impulse';
$_POST['impuls_faktor'] = '-3';
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$check('Faktor kleiner null wird 1', $fehler === [] && (float)end($GLOBALS['inserts'])[1]['impuls_faktor'] === 1.0);
$_POST = ['name' => 'Wasser HA', 'art' => 'wasser', 'quelle' => 'ha', 'ha_entity' => 'sensor.wasser_stand', 'einheit' => '', 'umrechnung' => '',
          'ha_faktor' => '', 'impuls_entity' => 'sensor.wasser_impulse', 'impuls_faktor' => '0,001', 'is_active' => '1'];
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$check('Quelle Home Assistant: Impulszähler wird geleert', $fehler === [] && end($GLOBALS['inserts'])[1]['impuls_entity'] === '');

/* ---------- Ablesen von Hand: Eichpunkt ---------- */
$GLOBALS['inserts'] = [];
$GLOBALS['ha'] = ['states/sensor.wasser_impulse' => ['entity_id' => 'sensor.wasser_impulse', 'state' => '50000']];
[$id, $fehler] = reading_add($wasser, 1234.567, date('Y-m-d H:i:s'), 'manuell', 'Thomas');
$r = $staende()[0][1];
$check('Ablesung von Hand jetzt: Sensorwert kommt dazu', $fehler === null && $r['quelle'] === 'manuell' && $nah((float)$r['impuls_wert'], 50000.0));
$GLOBALS['inserts'] = [];
[$id, $fehler] = reading_add($wasser, 1234.600, date('Y-m-d H:i:s'), 'qr', 'Scanner');
$check('QR-Meldung ist ebenfalls Eichpunkt', $fehler === null && $nah((float)$staende()[0][1]['impuls_wert'], 50000.0));
$GLOBALS['inserts'] = [];
[$id, $fehler] = reading_add($ohne, 1234.600, date('Y-m-d H:i:s'), 'manuell', 'Thomas');
$check('ohne Impulszähler kein Sensorwert', $fehler === null && $staende()[0][1]['impuls_wert'] === null);

// Nachgetragene Ablesung von gestern: Sensorwert aus der Geschichte
$GLOBALS['inserts'] = [];
$GLOBALS['ha_pfade'] = [];
$gestern = time() - 86400;
$GLOBALS['ha']['history/period/' . rawurlencode(date('c', $gestern))] = [[
    ['entity_id' => 'sensor.wasser_impulse', 'state' => 'unavailable', 'last_changed' => date('c', $gestern)],
    ['state' => '48120', 'last_changed' => date('c', $gestern + 60)],
]];
[$id, $fehler] = reading_add($wasser, 1232.000, date('Y-m-d H:i:s', $gestern), 'manuell', 'Thomas');
$r = $staende()[0][1];
$check('nachgetragene Ablesung: Sensorwert aus der Geschichte, unavailable übersprungen', $fehler === null && $nah((float)$r['impuls_wert'], 48120.0));
$check('Geschichte mit Entität und Ende abgefragt', count($GLOBALS['ha_pfade']) === 1
    && str_starts_with($GLOBALS['ha_pfade'][0], 'history/period/') && str_contains($GLOBALS['ha_pfade'][0], 'filter_entity_id=sensor.wasser_impulse')
    && str_contains($GLOBALS['ha_pfade'][0], 'end_time=') && str_contains($GLOBALS['ha_pfade'][0], 'minimal_response'));

// Home Assistant nicht erreichbar: Ablesung trotzdem gespeichert, ohne Sensorwert
$GLOBALS['inserts'] = [];
$GLOBALS['ha'] = [];
$GLOBALS['state'] = [];
[$id, $fehler] = reading_add($wasser, 1234.700, date('Y-m-d H:i:s'), 'manuell', 'Thomas');
$check('HA weg: Ablesung ohne Sensorwert, kein Fehler für den Ableser', $fehler === null && $staende()[0][1]['impuls_wert'] === null);
$check('… aber die Störung ist vermerkt', str_contains((string)($GLOBALS['state']['verbrauch_ha_fehler_7'] ?? ''), 'nicht erreichbar'));

/* ---------- Abruf: Zwischenstand aus dem Impulszähler ---------- */
$GLOBALS['inserts'] = [];
$GLOBALS['state'] = [];
$GLOBALS['ha'] = ['states/sensor.wasser_impulse' => ['state' => '51234']];
$GLOBALS['letzter'] = null; $GLOBALS['mit_wert'] = null; $GLOBALS['eich'] = null;
$r = meter_impuls_lesen($wasser);
$check('ohne Eichpunkt: Hinweis, einmal von Hand ablesen', !$r['ok'] && str_contains($r['text'], 'Eichpunkt') && $staende() === []);
$check('Hinweis steht als Fehler am Zähler', str_contains((string)$GLOBALS['state']['verbrauch_ha_fehler_7'], 'Eichpunkt'));

$eichZeile = ['id' => 1, 'stand' => 1234.567, 'gelesen_am' => date('Y-m-d H:i:s', time() - 7200), 'quelle' => 'manuell', 'impuls_wert' => 50000];
$GLOBALS['letzter'] = $eichZeile; $GLOBALS['mit_wert'] = $eichZeile; $GLOBALS['eich'] = $eichZeile; $GLOBALS['vorher'] = $eichZeile;
$r = meter_impuls_lesen($wasser);
$z = $staende();
$check('Zwischenstand eingetragen: 1234,567 + 1234 × 0,001', $r['ok'] && $nah($r['stand'], 1235.801) && count($z) === 1
    && $z[0][1]['quelle'] === 'impuls' && $nah((float)$z[0][1]['stand'], 1235.801) && $nah((float)$z[0][1]['impuls_wert'], 51234.0)
    && $z[0][1]['melder'] === 'Home Assistant');
$check('Störung gelöscht', ($GLOBALS['state']['verbrauch_ha_fehler_7'] ?? '') === '');
$check('Text nennt den Zwischenstand', str_starts_with($r['text'], 'Zwischenstand übernommen'));

// Unverändert: Sensor steht noch auf demselben Wert wie beim letzten Zwischenstand
$GLOBALS['inserts'] = [];
$zwischen = ['id' => 2, 'stand' => 1235.801, 'gelesen_am' => date('Y-m-d H:i:s', time() - 3600), 'quelle' => 'impuls', 'impuls_wert' => 51234];
$GLOBALS['letzter'] = $zwischen; $GLOBALS['mit_wert'] = $zwischen; $GLOBALS['vorher'] = $zwischen;
$r = meter_impuls_lesen($wasser);
$check('unverändert: nichts eingetragen', $r['ok'] && str_starts_with($r['text'], 'Unverändert') && $staende() === []);

// Sensor neu gestartet (steht auf 120): vom letzten Zwischenstand weiter
$GLOBALS['ha'] = ['states/sensor.wasser_impulse' => ['state' => '120']];
$r = meter_impuls_lesen($wasser);
$z = $staende();
$check('Neustart des Sensors: 1235,801 + 120 × 0,001', $r['ok'] && count($z) === 1 && $nah((float)$z[0][1]['stand'], 1235.921));

// Sensor liefert unknown
$GLOBALS['inserts'] = [];
$GLOBALS['ha'] = ['states/sensor.wasser_impulse' => ['state' => 'unknown']];
$r = meter_impuls_lesen($wasser);
$check('unknown: Fehler, nichts eingetragen', !$r['ok'] && str_contains($r['text'], 'keinen Zahlenwert') && $staende() === []
    && str_contains((string)$GLOBALS['state']['verbrauch_ha_fehler_7'], 'Zahlenwert'));

// Home Assistant weg
$GLOBALS['ha'] = [];
$r = meter_impuls_lesen($wasser);
$check('HA weg: Fehler vermerkt', !$r['ok'] && str_contains($r['text'], 'nicht erreichbar') && str_contains((string)$GLOBALS['state']['verbrauch_ha_fehler_7'], 'nicht erreichbar'));

// Nachtrag: jüngste Ablesung von Hand ohne Sensorwert (HA war beim Eintragen weg) wird jetzt geeicht
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = [];
$GLOBALS['ha'] = ['states/sensor.wasser_impulse' => ['state' => '52000']];
$handOhne = ['id' => 3, 'stand' => 1236.000, 'gelesen_am' => date('Y-m-d H:i:s', time() - 600), 'quelle' => 'manuell', 'impuls_wert' => null];
$GLOBALS['letzter'] = $handOhne; $GLOBALS['vorher'] = $handOhne;
// Nach dem Nachtrag liefert die Datenbank die Ablesung als Eichpunkt: das bildet der Stub über einen Zähler nach
$GLOBALS['eich'] = ['id' => 3, 'stand' => 1236.000, 'gelesen_am' => $handOhne['gelesen_am'], 'quelle' => 'manuell', 'impuls_wert' => 52000];
$GLOBALS['mit_wert'] = $GLOBALS['eich'];
$r = meter_impuls_lesen($wasser);
$check('Nachtrag: Sensorwert an die Ablesung geschrieben', count($GLOBALS['updates']) === 1 && $GLOBALS['updates'][0][0] === 'meter_readings'
    && $nah((float)$GLOBALS['updates'][0][1]['impuls_wert'], 52000.0) && $GLOBALS['updates'][0][2] === [3]);
$check('Nachtrag: Stand unverändert, weil der Sensor seit der Ablesung nicht weiterzählte', $r['ok'] && str_starts_with($r['text'], 'Unverändert'));

// Ablesung von Hand älter als eine Stunde ohne Sensorwert und ohne Geschichte: kein Nachtrag, älterer Eichpunkt zählt
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = [];
$alt = ['id' => 4, 'stand' => 1236.000, 'gelesen_am' => date('Y-m-d H:i:s', time() - 2 * 86400), 'quelle' => 'manuell', 'impuls_wert' => null];
$GLOBALS['letzter'] = $alt; $GLOBALS['vorher'] = $alt;
$GLOBALS['eich'] = $eichZeile; $GLOBALS['mit_wert'] = $eichZeile;
$GLOBALS['ha'] = ['states/sensor.wasser_impulse' => ['state' => '52000']];
$r = meter_impuls_lesen($wasser);
$check('ohne Geschichte kein Nachtrag', $GLOBALS['updates'] === []);
$check('älterer Eichpunkt: 1234,567 + 2000 × 0,001 = 1236,567 über der Ablesung – eingetragen', $r['ok'] && $nah($r['stand'], 1236.567) && count($staende()) === 1);

/* ---------- Weiche in meter_ha_lesen und der Abruf ---------- */
$GLOBALS['inserts'] = [];
$GLOBALS['letzter'] = $eichZeile; $GLOBALS['mit_wert'] = $eichZeile; $GLOBALS['eich'] = $eichZeile; $GLOBALS['vorher'] = $eichZeile;
$GLOBALS['ha'] = ['states/sensor.wasser_impulse' => ['state' => '51000']];
$r = meter_ha_lesen($wasser);
$check('meter_ha_lesen nimmt den Impulszähler', $r['ok'] && $nah($r['stand'], 1235.567) && $staende()[0][1]['quelle'] === 'impuls');
$r = meter_ha_lesen($ohne);
$check('Handzähler ohne Impulszähler: nicht aus Home Assistant', !$r['ok'] && str_contains($r['text'], 'nicht aus Home Assistant'));

$GLOBALS['sqls'] = [];
$GLOBALS['rows'] = [];
$GLOBALS['state'] = [];
verbrauch_ha_sync();
$sql = implode(' ', array_filter($GLOBALS['sqls'], static fn($s) => str_contains($s, 'FROM meters')));
$check('Abruf holt auch Handzähler mit Impulszähler', str_contains($sql, "impuls_entity <> ''") && str_contains($sql, "quelle = 'ha'"));

/* ---------- Entitätenliste ---------- */
$GLOBALS['state'] = [];
$GLOBALS['ha'] = ['states' => [
    ['entity_id' => 'sensor.wasser_impulse', 'state' => '51000', 'attributes' => ['friendly_name' => 'Wasser Impulse', 'state_class' => 'total_increasing']],
    ['entity_id' => 'sensor.temperatur', 'state' => '21', 'attributes' => ['unit_of_measurement' => '°C', 'state_class' => 'measurement']],
    ['entity_id' => 'sensor.strom', 'state' => '5', 'attributes' => ['unit_of_measurement' => 'kWh', 'device_class' => 'energy']],
]];
$e = ha_zaehler_entitaeten(true);
$check('Entitätenliste nimmt total_increasing ohne Einheit, keine Momentanwerte', isset($e['sensor.wasser_impulse'], $e['sensor.strom']) && !isset($e['sensor.temperatur']));

/* ---------- Faktor im Formular: kleine Zahlen überleben das erneute Öffnen ---------- */
$check('Faktor 0,001 bleibt im Eingabefeld erhalten', num_input(0.001, true, 6) === '0,001' && num_input('0.001000', true, 6) === '0,001');
$check('Faktor 1 ohne Nachkommastellen, Beträge weiter mit zwei', num_input(1.0, true, 6) === '1' && num_input(12.5) === '12,50');
$check('Arbeitspreis mit vier Stellen', num_input(0.3245, true, 4) === '0,3245' && num_input(0.00045, true, 6) === '0,00045');
$_POST = ['name' => 'Wasser', 'art' => 'wasser', 'quelle' => 'manuell', 'einheit' => 'm³', 'umrechnung' => '', 'ha_faktor' => '',
          'impuls_entity' => 'sensor.wasser_impulse', 'impuls_faktor' => num_input(0.001, true, 6), 'is_active' => '1'];
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$check('Rundlauf: angezeigter Faktor gespeichert ergibt wieder 0,001', $fehler === [] && (float)end($GLOBALS['inserts'])[1]['impuls_faktor'] === 0.001);

echo "$ok bestanden, $fail fehlgeschlagen\n";
exit($fail === 0 ? 0 : 1);
