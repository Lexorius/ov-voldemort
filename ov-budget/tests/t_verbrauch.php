<?php
declare(strict_types=1);
/*
 * Verbrauch: Rechnung aus der Kette der Stände, Tarife und Kosten,
 * Speichern von Zähler und Tarif, Stand eintragen, QR-Meldung.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'verbrauch_modul_name' => 'Verbrauch', 'verbrauch_intro' => '',
    'verbrauch_ha_intervall_minuten' => '60'];
$GLOBALS['inserts'] = [];
$GLOBALS['vorher'] = null;
$GLOBALS['state'] = [];
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return $GLOBALS['rows'] ?? []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM meters m')) { return $GLOBALS['parent'] ?? null; }
    if (str_contains($sql, 'FROM meter_readings')) { return $GLOBALS['vorher']; }
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
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'verbrauch'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$nah = static fn(?float $a, float $b): bool => $a !== null && abs($a - $b) < 0.01;

/* ---------- Stand zu einem Zeitpunkt ---------- */
$st = [
    ['stand' => 1000, 'gelesen_am' => '2026-01-01 00:00:00'],
    ['stand' => 1310, 'gelesen_am' => '2026-02-01 00:00:00'],   // 31 Tage, 10/Tag
    ['stand' => 1590, 'gelesen_am' => '2026-03-01 00:00:00'],   // 28 Tage, 10/Tag
];
$check('vor der ersten Ablesung nichts', verbrauch_stand_am($st, strtotime('2025-12-31')) === null);
$check('genau auf einer Ablesung', verbrauch_stand_am($st, strtotime('2026-02-01')) === 1310.0);
$check('dazwischen linear', $nah(verbrauch_stand_am($st, strtotime('2026-01-11')), 1100.0));
$check('nach der letzten gilt die letzte', verbrauch_stand_am($st, strtotime('2026-06-01')) === 1590.0);

/* ---------- Verbrauch im Zeitraum, je Monat ---------- */
$check('Januar = 310', $nah(verbrauch_zwischen($st, strtotime('2026-01-01'), strtotime('2026-02-01')), 310.0));
$check('halber Januar', $nah(verbrauch_zwischen($st, strtotime('2026-01-01'), strtotime('2026-01-16')), 150.0));
$check('Zeitraum vor der ersten Ablesung: ab der ersten', $nah(verbrauch_zwischen($st, strtotime('2025-12-01'), strtotime('2026-02-01')), 310.0));
$check('ganz vor der ersten: nichts', verbrauch_zwischen($st, strtotime('2025-11-01'), strtotime('2025-12-01')) === null);
$m = verbrauch_monate($st, 2026);
$check('zwölf Monate', count($m) === 12 && $nah($m[1], 310.0) && $nah($m[2], 280.0));
$check('nach der letzten Ablesung null Verbrauch', $m[4] === 0.0 || $m[4] === null);

// Zählerwechsel: Rücklauf zählt nicht als Verbrauch
$wechsel = [
    ['stand' => 9000, 'gelesen_am' => '2026-01-01 00:00:00'],
    ['stand' => 9100, 'gelesen_am' => '2026-01-11 00:00:00'],
    ['stand' => 5,    'gelesen_am' => '2026-01-11 00:00:01'],   // neuer Zähler
    ['stand' => 105,  'gelesen_am' => '2026-01-21 00:00:00'],
];
$check('Rücklauf herausgerechnet', $nah(verbrauch_zwischen($wechsel, strtotime('2026-01-01'), strtotime('2026-01-21')), 200.0));
$ab = verbrauch_abschnitte($wechsel);
$check('Abschnitt mit Wechsel als solcher, je Tag zusammengefasst', count($ab) === 2 && $ab[0]['menge'] === null && $nah($ab[1]['je_tag'], 10.0));

// Stündliche Stände aus Home Assistant: ein Abschnitt je Tag
$stuendlich = [];
for ($h = 0; $h < 48; $h++) {
    $stuendlich[] = ['stand' => 100 + $h * 0.5, 'gelesen_am' => date('Y-m-d H:i:s', strtotime('2026-09-25 00:00:00') + $h * 3600)];
}
$ab = verbrauch_abschnitte($stuendlich);
$check('stündlich: je Tag ein Abschnitt', count($ab) === 1 && $nah($ab[0]['menge'], 12.0) && $nah($ab[0]['tage'], 1.0));
$check('Tagesstände nehmen den letzten je Tag', count(verbrauch_tagesstaende($stuendlich)) === 2
    && verbrauch_tagesstaende($stuendlich)[0]['stand'] === 100 + 23 * 0.5);

/* ---------- Tarife ---------- */
$tarife = [
    ['id' => 1, 'art' => 'strom', 'name' => 'Alt', 'gueltig_von' => '2025-01-01', 'gueltig_bis' => '2025-12-31', 'arbeitspreis' => 0.30, 'grundpreis_monat' => 10, 'einheit' => 'kWh'],
    ['id' => 2, 'art' => 'strom', 'name' => 'Neu', 'gueltig_von' => '2026-01-01', 'gueltig_bis' => null, 'arbeitspreis' => 0.40, 'grundpreis_monat' => 12, 'einheit' => 'kWh'],
    ['id' => 3, 'art' => 'gas', 'name' => 'Gas', 'gueltig_von' => '2026-01-01', 'gueltig_bis' => null, 'arbeitspreis' => 0.10, 'grundpreis_monat' => 0, 'einheit' => 'kWh'],
];
$check('Tarif am Tag', tarif_am($tarife, 'strom', '2025-06-15')['name'] === 'Alt' && tarif_am($tarife, 'strom', '2026-06-15')['name'] === 'Neu');
$check('ohne passenden Tarif null', tarif_am($tarife, 'wasser', '2026-06-15') === null && tarif_am($tarife, 'strom', '2024-01-01') === null);
$k = verbrauch_kosten(310.0, 1.0, $tarife[1], 31.0);
$check('Kosten = Arbeit + Grund anteilig', $nah($k['arbeit'], 124.0) && $nah($k['grund'], 12.22) && $nah($k['gesamt'], 136.22));
$check('Gas mit Umrechnung', $nah(verbrauch_kosten(100.0, 10.0, $tarife[2], 30.0)['arbeit'], 100.0));
$check('ohne Tarif keine Kosten', verbrauch_kosten(100.0, 1.0, null, 30.0)['gesamt'] === null);
$meter = ['id' => 1, 'art' => 'strom', 'einheit' => 'kWh', 'umrechnung' => 1, 'name' => 'Strom'];
$kj = verbrauch_kosten_jahr($st, $tarife, $meter, 2026);
$check('Jahreskosten aus Monaten', $nah($kj['monate'][1], 136.22) && $kj['ohne_tarif'] === 0 && $kj['gesamt'] > 240.0);
$kj = verbrauch_kosten_jahr($st, [], $meter, 2026);
$check('Monate ohne Tarif gezählt', $kj['ohne_tarif'] > 0 && $kj['gesamt'] === 0.0);

/* ---------- Speichern ---------- */
$_POST = ['name' => 'Strom Unterkunft', 'art' => 'strom', 'quelle' => 'ha', 'ha_entity' => 'sensor.strom_gesamt',
          'einheit' => '', 'umrechnung' => '', 'ha_faktor' => '0,001', 'is_active' => '1', 'zaehlernummer' => 'Z-1'];
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$d = $GLOBALS['inserts'][0][1];
$check('Zähler angelegt', $fehler === [] && $GLOBALS['inserts'][0][0] === 'meters');
$check('Vorgaben: Einheit der Art, Umrechnung 1, Faktor übernommen', $d['einheit'] === 'kWh' && (float)$d['umrechnung'] === 1.0 && (float)$d['ha_faktor'] === 0.001);
$_POST['ha_entity'] = 'kein entity';
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$check('falsche Entität abgewiesen', $id === null && count($fehler) === 1);
$_POST['quelle'] = 'manuell';
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$check('von Hand: Entität wird geleert', $fehler === [] && end($GLOBALS['inserts'])[1]['ha_entity'] === '');

$_POST = ['art' => 'gas', 'name' => 'Gas 2026', 'gueltig_von' => '2026-01-01', 'gueltig_bis' => '', 'arbeitspreis' => '0,1099', 'grundpreis_monat' => '9,90', 'einheit' => ''];
[$id, $fehler] = tarif_save_from_post(null);
$t = end($GLOBALS['inserts'])[1];
$check('Tarif angelegt: Gas rechnet je kWh', $fehler === [] && $t['einheit'] === 'kWh' && (float)$t['arbeitspreis'] === 0.1099 && $t['gueltig_bis'] === null);
$_POST['gueltig_bis'] = '2025-06-01';
[$id, $fehler] = tarif_save_from_post(null);
$check('Ende vor Anfang abgewiesen', $id === null);

/* ---------- Stand eintragen ---------- */
$meter = ['id' => 7, 'name' => 'Strom', 'einheit' => 'kWh'];
$GLOBALS['vorher'] = ['stand' => 1500.0, 'gelesen_am' => '2026-09-01 08:00:00'];
[$id, $fehler] = reading_add($meter, 1520.5, '2026-09-25 09:30', 'manuell', 'Anna', '', ['id' => 1]);
$r = end($GLOBALS['inserts'])[1];
$check('Stand eingetragen', $fehler === null && $r['stand'] === 1520.5 && $r['gelesen_am'] === '2026-09-25 09:30:00' && $r['melder'] === 'Anna');
[$id, $fehler] = reading_add($meter, 1400.0, '2026-09-25 09:30');
$check('Stand unter dem vorherigen abgewiesen', $id === null && str_contains((string)$fehler, 'Rücklauf'));
[$id, $fehler] = reading_add($meter, 3.0, '2026-09-25 09:30', 'manuell', '', 'Zähler getauscht', null, true);
$check('mit Rücklauf erlaubt', $fehler === null);
[$id, $fehler] = reading_add($meter, 1600.0, '2030-01-01 00:00');
$check('Zukunft abgewiesen', $id === null);
[$id, $fehler] = reading_add($meter, -1.0, '2026-09-25 09:30');
$check('negativ abgewiesen', $id === null);

/* ---------- QR-Meldung ---------- */
$GLOBALS['vorher'] = ['stand' => 1500.0, 'gelesen_am' => '2026-09-01 08:00:00'];
$ts = strtotime('2026-09-25 10:00:00');
$check('QR-Stand übernommen', meter_qr_anwenden($meter, ['stand' => 1530.25, 'zeit' => $ts - 60, 'melder' => 'Bernd'], $ts) === null);
$r = end($GLOBALS['inserts'])[1];
$check('Zeit des Melders genommen, Quelle qr', $r['quelle'] === 'qr' && $r['gelesen_am'] === date('Y-m-d H:i:s', $ts - 60) && $r['melder'] === 'Bernd');
$check('QR-Stand unter vorherigem abgewiesen', meter_qr_anwenden($meter, ['stand' => 10, 'zeit' => $ts], $ts) !== null);
$check('QR-Name ohne Nummer', meter_qr_name(['name' => 'Strom', 'zaehlernummer' => '']) === 'Strom'
    && meter_qr_name(['name' => 'Strom', 'zaehlernummer' => 'Z-1']) === 'Strom · Nr. Z-1');

/* ---------- Alter und Kennzahlen ---------- */
$check('Stand frisch', meter_stand_alter(['letzte_ablesung' => '2026-09-20 00:00:00'], '2026-09-25 00:00:00')['stufe'] === 'frisch');
$check('Stand alt', meter_stand_alter(['letzte_ablesung' => '2026-07-01 00:00:00'], '2026-09-25 00:00:00')['stufe'] === 'alt');
$check('nie', meter_stand_alter(['letzte_ablesung' => null])['stufe'] === 'nie');
$check('Mengen lesbar', menge(1234.5, 'kWh') === '1.234,5 kWh' && menge(null) === '–' && menge(2, 'm³', 0) === '2 m³');

/* ---------- Zeitraum, Jahre, Zwischenspeicher ---------- */
[$von, $bis] = verbrauch_zeitraum(2026, strtotime('2026-09-26 12:00:00'));
$check('Zeitraum deckt Jahr und letzte 31 Tage', $von === mktime(0, 0, 0, 1, 1, 2026) && $bis === mktime(0, 0, 0, 1, 1, 2027));
[$von, $bis] = verbrauch_zeitraum(2024, strtotime('2026-09-26 12:00:00'));
$check('altes Jahr: bis heute geladen, wegen der 30 Tage', $von === mktime(0, 0, 0, 1, 1, 2024) && $bis === strtotime('2026-09-26 12:00:00'));

$GLOBALS['state'] = [];
$GLOBALS['rows'] = [];
$GLOBALS['stats_laeufe'] = 0;
$stats1 = verbrauch_stats_cached(2026);
$stats2 = verbrauch_stats_cached(2026);
$check('zweiter Aufruf kommt aus dem Zwischenspeicher', $stats1 === $stats2 && ($GLOBALS['state']['verbrauch_stats_marke'] ?? '') !== '');
$marke = $GLOBALS['state']['verbrauch_stats_marke'];
verbrauch_geaendert();
verbrauch_stats_cached(2026);
$check('Änderung erzwingt Neuberechnung', $GLOBALS['state']['verbrauch_stats_marke'] !== $marke
    && str_starts_with($GLOBALS['state']['verbrauch_stats_marke'], '1|2026|'));

/* ---------- Berichte ---------- */
// 2025: 10/Tag, 2026: 12/Tag – vom 1.1.2025 bis 1.10.2026
$kette = [];
$stand = 0.0;
for ($t = strtotime('2025-01-01'); $t <= strtotime('2026-10-01'); $t += 86400) {
    $kette[] = ['stand' => $stand, 'gelesen_am' => date('Y-m-d 00:00:00', $t), 'quelle' => 'ha'];
    $stand += date('Y', $t) === '2025' ? 10 : 12;
}
$jetzt = strtotime('2026-10-01 00:00:00');
$jb = verbrauch_jahr_mit_vorjahr($kette, 2026, $jetzt);
$check('Jahr bis heute: 273 Tage × 12', $jb['bis_heute'] && $nah($jb['summe'], 273 * 12.0));
$check('Vorjahr bis zum selben Tag: 273 × 10', $nah($jb['summe_vorjahr'], 2730.0));
$check('Veränderung +20 %', $jb['delta_prozent'] === 20.0);
$check('je Tag 12', $nah($jb['je_tag'], 12.0) && $jb['tage'] === 273);
$check('stärkster Monat: ein 31-Tage-Monat mit 372', $jb['spitze'] !== null && $nah($jb['spitze']['wert'], 372.0));
$jb = verbrauch_jahr_mit_vorjahr($kette, 2025, $jetzt);
$check('abgeschlossenes Jahr: 3650, kein Vorjahr', !$jb['bis_heute'] && $nah($jb['summe'], 3650.0) && $jb['summe_vorjahr'] === null && $jb['delta_prozent'] === null);

$GLOBALS['rows'] = $kette;
$meterB = ['id' => 1, 'art' => 'strom', 'name' => 'Strom', 'einheit' => 'kWh', 'umrechnung' => 1, 'zaehlernummer' => '', 'standort' => '', 'is_active' => 1, 'quelle' => 'ha'];
$zb = verbrauch_zaehlerbericht($meterB, $tarife, 2026, $jetzt);
$check('Zählerbericht: Kosten und Tarif dabei', $zb['kosten']['gesamt'] > 0 && $zb['tarif']['name'] === 'Neu' && $zb['ablesungen'] === 274 && $zb['letzter_stand'] !== null);
$jr = verbrauch_jahresbericht([$meterB, $meterB + ['id' => 2, 'name' => 'Strom 2']], $tarife, 2026, $jetzt);
$check('Jahresbericht: zwei Zähler zusammengefasst', $jr['je_art']['strom']['zaehler'] === 2 && $nah($jr['je_art']['strom']['summe'], 2 * 273 * 12.0)
    && $jr['je_art']['strom']['delta_prozent'] === 20.0 && count($jr['zaehler']) === 2 && $jr['je_art']['gas']['zaehler'] === 0);
$check('Jahresbericht: Monate je Art summiert', $nah($jr['je_art']['strom']['monate'][1], 2 * 31 * 12.0) && $nah($jr['je_art']['strom']['monate_vorjahr'][1], 2 * 310.0));
$GLOBALS['rows'] = [];

$html = render_partial('meter_bericht', ['meter' => $meterB, 'jahr' => 2026, 'bericht' => $zb]);
$check('Zählerbericht rendert', str_contains($html, 'Verbrauchsbericht') && str_contains($html, '+20,0 %') && str_contains($html, 'Drucken / PDF'));
$html = render_partial('verbrauch_bericht', ['jahr' => 2026, 'bericht' => $jr]);
$check('Jahresbericht rendert', str_contains($html, 'Verbrauch 2026') && str_contains($html, 'Strom 2') && str_contains($html, 'landscape'));

/* ---------- Profil ---------- */
// Stündliche Stände über vier Wochen: 1 je Stunde, um 18 Uhr 5, dienstags alles doppelt
$prof = [];
$stand = 0.0;
$start = strtotime('2026-03-02 00:00:00');   // ein Montag
for ($h = 0; $h <= 28 * 24; $h++) {
    $t = $start + $h * 3600;
    $prof[] = ['stand' => $stand, 'gelesen_am' => date('Y-m-d H:i:s', $t), 'quelle' => 'ha'];
    $zuwachs = (int)date('G', $t) === 18 ? 5.0 : 1.0;
    if ((int)date('N', $t) === 2) { $zuwachs *= 2; }
    $stand += $zuwachs;
}
$p = verbrauch_profil($prof, $start, $start + 28 * 86400);
$check('Profil: Abschnitte und Tage', $p['abschnitte'] === 28 * 24 && $nah($p['tage'], 28.0));
$check('Profil: stündlich ist aussagekräftig', $p['stunden_aussagekraeftig'] && $p['wochentage_aussagekraeftig'] && $p['abstand_stunden'] === 1.0);
$check('stärkste Stunde 18 Uhr', $p['spitzen_stunden'][0]['stunde'] === 18 && $nah($p['spitzen_stunden'][0]['wert'], (5 * 6 + 10) / 7));
$check('stärkster Wochentag Dienstag', $p['spitze_tag'] === 1 && $nah($p['wochentage'][1], 2 * (23 + 5)) && $nah($p['wochentage'][0], 28.0));
$check('stärkste Zeit der Woche: Di 18 Uhr', $p['spitzen_woche'][0]['tag'] === 1 && $p['spitzen_woche'][0]['stunde'] === 18 && $nah($p['spitzen_woche'][0]['wert'], 10.0));
$check('Nachtanteil 8 von 30 Stunden-Einheiten', $p['nacht_anteil'] === (int)round(8 / (23 + 5) * 100));

// Wöchentliche Ablesungen: kein Tages-, kein Wochenprofil
$woe = [];
for ($w = 0; $w <= 8; $w++) { $woe[] = ['stand' => $w * 70.0, 'gelesen_am' => date('Y-m-d 08:00:00', $start + $w * 7 * 86400)]; }
$p = verbrauch_profil($woe, $start, $start + 56 * 86400);
$check('wöchentlich: nur Mittelwert, alle Wochentage gleich', !$p['stunden_aussagekraeftig'] && !$p['wochentage_aussagekraeftig']
    && $nah($p['wochentage'][0], 10.0) && $nah($p['wochentage'][6], 10.0) && $p['abstand_stunden'] === 168.0);
$check('ohne Stände leeres Profil', verbrauch_profil([], $start, $start + 86400)['abschnitte'] === 0);

// Junger Unterzähler: drei Tage stündlich (Mi–Fr) – Tageskurve ja, Wochenprofil noch nicht
$jung = [];
$startMi = strtotime('2026-10-07 12:00:00');   // ein Mittwoch
for ($h = 0; $h <= 69; $h++) { $jung[] = ['stand' => $h * 0.2, 'gelesen_am' => date('Y-m-d H:i:s', $startMi + $h * 3600), 'quelle' => 'ha']; }
$pj = verbrauch_profil($jung, $startMi, $startMi + 70 * 3600);
$check('drei Tage: Stunden aussagekräftig, Wochentage nicht; Mo, Di, So fehlen', $pj['stunden_aussagekraeftig'] && !$pj['wochentage_aussagekraeftig']
    && $pj['wochentage_fehlend'] === [0, 1, 6] && $pj['wochentage_tage'][0] === 0.0 && $pj['wochentage_tage'][3] > 0.9 && $pj['abschnitte'] === 69);
$check('schwächster Tag nur unter den belegten', in_array($pj['schwach_tag'], [2, 3, 4, 5], true));
$html = render_partial('partials/verbrauch_profil', ['profil' => $pj, 'einheit' => 'kWh', 'farbe' => '#b45309', 'bericht' => false]);
$check('Profil: fehlende Wochentage als –, Hinweis mit den fehlenden Tagen, kein stärkster Wochentag', str_contains($html, 'Erst nach einer vollen Woche')
    && str_contains($html, 'es fehlen Mo, Di, So') && str_contains($html, 'noch kein Stand an diesem Wochentag') && !str_contains($html, 'Stärkster Wochentag')
    && str_contains($html, 'Stärkste Stunden am Tag') && str_contains($html, 'Grundlage: 70 Stände über 2,9 Tage'));
$html = render_partial('partials/verbrauch_profil', ['profil' => verbrauch_profil($prof, $start, $start + 28 * 86400), 'einheit' => 'kWh', 'farbe' => '#b45309', 'bericht' => false]);
$check('Profil rendert mit Spitzen', str_contains($html, 'Dienstag') && str_contains($html, '18–19 Uhr') && str_contains($html, 'Di 18–19 Uhr'));
$html = render_partial('partials/verbrauch_profil', ['profil' => $p, 'einheit' => 'kWh', 'farbe' => '#b45309', 'bericht' => true]);
$check('Profil sagt, wenn es nur mittelt', str_contains($html, 'gleichmäßig verteilt') && !str_contains($html, 'Stärkste Stunden'));

/* ---------- Rollen: Unterzähler und Solar ---------- */
$check('Tarifart je Rolle', meter_tarif_art(['rolle' => 'bezug', 'art' => 'gas']) === 'gas' && meter_tarif_art(['rolle' => 'unter', 'art' => 'strom']) === null
    && meter_tarif_art(['rolle' => 'erzeugung']) === null && meter_tarif_art(['rolle' => 'einspeisung']) === 'einspeisung');
$check('nur Hauptzähler zählen', meter_zaehlt(['rolle' => 'bezug']) && !meter_zaehlt(['rolle' => 'unter']) && !meter_zaehlt(['rolle' => 'erzeugung']));
$check('Einspeisung ist eine Tarifart', tarif_art('einspeisung') === 'einspeisung' && tarif_art('unsinn') === 'strom' && meter_art('einspeisung') === 'strom');

$so = verbrauch_solar_bilanz(['erzeugung' => 5000.0, 'einspeisung' => 3000.0, 'erloes' => 240.0], 4000.0);
$check('Solarbilanz: Eigenverbrauch 2000, gesamt 6000, Autarkie 33 %', $so['eigenverbrauch'] === 2000.0 && $so['gesamt'] === 6000.0 && $so['autarkie'] === 33 && $so['erloes'] === 240.0);
$check('Solarbilanz ohne Verbrauch', verbrauch_solar_bilanz(['erzeugung' => 0.0, 'einspeisung' => 0.0, 'erloes' => 0], 0.0)['autarkie'] === 0);

$anteile = verbrauch_anteile([
    ['id' => 1, 'rolle' => 'bezug', 'parent_id' => null], ['id' => 2, 'rolle' => 'unter', 'parent_id' => 1],
    ['id' => 3, 'rolle' => 'unter', 'parent_id' => 1], ['id' => 4, 'rolle' => 'unter', 'parent_id' => 9],
], [1 => 1000.0, 2 => 250.0, 3 => 400.0, 4 => 10.0]);
$check('Anteile am Hauptzähler', $anteile === [2 => 25.0, 3 => 40.0, 4 => null]);

// Zeiträume: was die Stände wirklich abdecken, und ob Unterzähler zum Hauptzähler passen
$j1 = strtotime('2026-01-01'); $j2 = strtotime('2027-01-01');
$abd = verbrauch_abdeckung($st, $j1, $j2);
$check('Abdeckung: erste bis letzte Ablesung', $abd['von'] === $j1 && $abd['bis'] === strtotime('2026-03-01') && $nah($abd['tage'], 59.0));
$check('Abdeckung beschnitten auf den Zeitraum', verbrauch_abdeckung($st, strtotime('2026-02-15'), $j2)['von'] === strtotime('2026-02-15')
    && verbrauch_abdeckung($st, strtotime('2026-04-01'), $j2) === null && verbrauch_abdeckung([$st[0]], $j1, $j2) === null);
$haupt = ['von' => $j1, 'bis' => strtotime('2026-10-08'), 'tage' => 280.0];
$check('Unterzähler ab September weicht ab', verbrauch_zeitraum_abweichend(['von' => strtotime('2026-09-12'), 'bis' => strtotime('2026-10-08'), 'tage' => 26.0], $haupt));
$check('wenige Tage Versatz sind in Ordnung', !verbrauch_zeitraum_abweichend(['von' => strtotime('2026-01-05'), 'bis' => strtotime('2026-10-04'), 'tage' => 272.0], $haupt));
$check('früheres Ende weicht ab, ohne Stände auch; ohne Hauptzeitraum nie', verbrauch_zeitraum_abweichend(['von' => $j1, 'bis' => strtotime('2026-06-01'), 'tage' => 151.0], $haupt)
    && verbrauch_zeitraum_abweichend(null, $haupt) && !verbrauch_zeitraum_abweichend(null, null));
$check('Zeitraumtext', verbrauch_zeitraum_text(['von' => strtotime('2026-09-12'), 'bis' => strtotime('2026-10-08'), 'tage' => 26.0]) === '12.09.–08.10.2026' && verbrauch_zeitraum_text(null) === 'keine Stände');

// Kosten je Bereich
$ohneGrund = array_map(static fn($t) => ['grundpreis_monat' => 0] + $t, $tarife);
$check('Unterzähler kostet den Arbeitspreis ohne Grundpreis', $nah(verbrauch_kosten_jahr_unter($st, $tarife, ['art' => 'strom', 'rolle' => 'unter', 'umrechnung' => 1], 2026),
    verbrauch_kosten_jahr($st, $ohneGrund, ['art' => 'strom', 'rolle' => 'bezug', 'umrechnung' => 1], 2026)['gesamt']) && verbrauch_kosten_jahr_unter($st, $tarife, ['art' => 'strom', 'rolle' => 'unter', 'umrechnung' => 1], 2026) > 0);
require_once $app . '/src/lib/standorte.php';
$plaetze = [
    ['id' => 1, 'parent_id' => null, 'typ' => 'gebaeude', 'name' => 'Haupthaus', 'sort_order' => 10, 'is_active' => 1, 'kurz' => ''],
    ['id' => 2, 'parent_id' => 1, 'typ' => 'stockwerk', 'name' => '1. Stock', 'sort_order' => 10, 'is_active' => 1, 'kurz' => ''],
    ['id' => 3, 'parent_id' => 2, 'typ' => 'raum', 'name' => 'Raum 12', 'sort_order' => 10, 'is_active' => 1, 'kurz' => ''],
    ['id' => 4, 'parent_id' => null, 'typ' => 'halle', 'name' => 'Halle 1', 'sort_order' => 20, 'is_active' => 1, 'kurz' => ''],
    ['id' => 5, 'parent_id' => null, 'typ' => 'hof', 'name' => 'Hof', 'sort_order' => 30, 'is_active' => 1, 'kurz' => ''],
];
$bm = [
    ['id' => 10, 'name' => 'Hauszähler', 'art' => 'strom', 'rolle' => 'bezug', 'parent_id' => null, 'bereich_id' => 1, 'einheit' => 'kWh'],
    ['id' => 11, 'name' => 'Strom 1. OG', 'art' => 'strom', 'rolle' => 'unter', 'parent_id' => 10, 'bereich_id' => 2, 'einheit' => 'kWh'],
    ['id' => 12, 'name' => 'Strom Halle', 'art' => 'strom', 'rolle' => 'unter', 'parent_id' => 10, 'bereich_id' => 4, 'einheit' => 'kWh'],
    ['id' => 13, 'name' => 'Gas Haus', 'art' => 'gas', 'rolle' => 'bezug', 'parent_id' => null, 'bereich_id' => 1, 'einheit' => 'm³'],
    ['id' => 14, 'name' => 'Solar', 'art' => 'strom', 'rolle' => 'erzeugung', 'parent_id' => null, 'bereich_id' => 1, 'einheit' => 'kWh'],
    ['id' => 15, 'name' => 'Wasser ohne', 'art' => 'wasser', 'rolle' => 'bezug', 'parent_id' => null, 'bereich_id' => null, 'einheit' => 'm³'],
];
$bz = [10 => ['menge' => 1000.0, 'kosten' => 300.0], 11 => ['menge' => 400.0, 'kosten' => 120.0], 12 => ['menge' => 100.0, 'kosten' => 30.0],
       13 => ['menge' => 800.0, 'kosten' => 80.0], 14 => ['menge' => 5000.0, 'kosten' => 0.0], 15 => ['menge' => 50.0, 'kosten' => 100.0]];
$bereiche = verbrauch_bereiche($plaetze, $bm, $bz);
$check('Bereiche: nur Plätze mit Zählern, in Baumreihenfolge', array_column($bereiche, 'name') === ['Haupthaus', '1. Stock', 'Halle 1']);
$hh = $bereiche[0];
$check('Haupthaus: Strom und Gas, Solar bleibt außen vor', $nah($hh['je_art']['strom']['menge'], 1000.0) && $nah($hh['je_art']['gas']['kosten'], 80.0) && $nah($hh['kosten'], 380.0) && count($hh['eigen']) === 2);
$check('Haupthaus: davon in Teilbereichen 500 kWh, nicht aufgeteilt 500 kWh', $nah($hh['unter']['strom']['menge'], 500.0) && $nah($hh['unter']['strom']['kosten'], 150.0)
    && $nah($hh['rest']['strom']['menge'], 500.0) && $nah($hh['rest']['gas']['menge'], 800.0) && $hh['anteil'] === null);
$check('1. Stock: 40 % vom Haupthaus, Halle 10 % – auch außerhalb des Platzbaums', $bereiche[1]['anteil'] === 40.0 && $bereiche[1]['anteil_von'] === 'Haupthaus'
    && $bereiche[1]['tiefe'] === 1 && $bereiche[1]['unter'] === null && $bereiche[2]['anteil'] === 10.0 && $bereiche[2]['tiefe'] === 0);
$html = render_partial('verbrauch_bereiche', ['jahr' => 2026, 'jahre' => [2026], 'zeilen' => $bereiche, 'ohneBereich' => [$bm[5] + $bz[15]], 'gesamt' => ['strom' => ['menge' => 1000.0, 'kosten' => 300.0, 'einheit' => 'kWh'], 'gas' => ['menge' => 800.0, 'kosten' => 80.0, 'einheit' => 'm³'], 'wasser' => ['menge' => 50.0, 'kosten' => 100.0, 'einheit' => 'm³']], 'bisHeute' => true, 'plaetzeDa' => true]);
$check('Kosten je Bereich rendert', str_contains($html, 'Kosten je Bereich 2026') && str_contains($html, 'Haupthaus') && str_contains($html, '380,00') && str_contains($html, '40,0 %') && str_contains($html, 'von Haupthaus')
    && str_contains($html, 'davon in Teilbereichen gemessen') && str_contains($html, 'Zähler ohne Bereich') && str_contains($html, 'Wasser ohne') && str_contains($html, 'p=standorte&amp;id=2') && str_contains($html, '480,00'));
$html = render_partial('verbrauch_bereiche', ['jahr' => 2026, 'jahre' => [2026], 'zeilen' => [], 'ohneBereich' => [], 'gesamt' => [], 'bisHeute' => false, 'plaetzeDa' => false]);
$check('ohne Plätze ein Hinweis', str_contains($html, 'Noch keine Stell- und Lagerplätze'));

// Kosten: Einspeisung mit Vergütung, Unterzähler ohne
$tarifeSolar = array_merge($tarife, [['id' => 9, 'art' => 'einspeisung', 'name' => 'EEG', 'gueltig_von' => '2026-01-01', 'gueltig_bis' => null, 'arbeitspreis' => 0.08, 'grundpreis_monat' => 0, 'einheit' => 'kWh']]);
$k = verbrauch_kosten_jahr($st, $tarifeSolar, ['art' => 'strom', 'rolle' => 'einspeisung', 'umrechnung' => 1], 2026);
$check('Einspeisung: Erlös 590 × 0,08', $k['erloes'] === true && $nah($k['gesamt'], 47.2));
$k = verbrauch_kosten_jahr($st, $tarifeSolar, ['art' => 'strom', 'rolle' => 'unter', 'umrechnung' => 1], 2026);
$check('Unterzähler: keine Kosten, kein fehlender Tarif', $k['gesamt'] === 0.0 && $k['ohne_tarif'] === 0);

// Kennzahlen: Unterzähler und Solar bleiben aus den Summen, Solar eigens
$GLOBALS['rows'] = $st;   // jeder Zähler bekommt dieselbe Kette (590 kWh im Jahr 2026 bis heute)
$meters = [
    ['id' => 1, 'art' => 'strom', 'rolle' => 'bezug', 'einheit' => 'kWh', 'umrechnung' => 1, 'letzte_ablesung' => date('Y-m-d H:i:s')],
    ['id' => 2, 'art' => 'strom', 'rolle' => 'unter', 'parent_id' => 1, 'einheit' => 'kWh', 'umrechnung' => 1, 'letzte_ablesung' => date('Y-m-d H:i:s')],
    ['id' => 3, 'art' => 'strom', 'rolle' => 'erzeugung', 'einheit' => 'kWh', 'umrechnung' => 1, 'letzte_ablesung' => date('Y-m-d H:i:s')],
    ['id' => 4, 'art' => 'strom', 'rolle' => 'einspeisung', 'einheit' => 'kWh', 'umrechnung' => 1, 'letzte_ablesung' => date('Y-m-d H:i:s')],
];
$stats = verbrauch_stats($meters, $tarifeSolar, 2026, strtotime('2026-12-31 12:00:00'));
$check('Strom zählt nur den Hauptzähler', $stats['je_art']['strom']['zaehler'] === 1 && $nah($stats['je_art']['strom']['jahr'], 590.0));
$check('Solar erkannt: Erzeugung, Einspeisung, Erlös', $stats['solar']['vorhanden'] && $nah($stats['solar']['erzeugung'], 590.0)
    && $nah($stats['solar']['einspeisung'], 590.0) && $nah($stats['erloes_jahr'], 47.2) && $stats['solar']['eigenverbrauch'] === 0.0);
$jr = verbrauch_jahresbericht($meters, $tarifeSolar, 2026, strtotime('2026-12-31 12:00:00'));
$check('Jahresbericht: Anteil des Unterzählers, Solar, Erlös', $jr['zaehler'][1]['anteil'] === 100.0 && $jr['solar']['vorhanden'] && $nah($jr['erloes'], 47.2)
    && $jr['je_art']['strom']['zaehler'] === 1);
$GLOBALS['rows'] = [];

// Speichern: Unterzähler braucht passenden Hauptzähler; Solar erzwingt Strom
$GLOBALS['parent'] = ['id' => 7, 'art' => 'strom', 'rolle' => 'bezug', 'name' => 'Haupt'];
$_POST = ['name' => 'EG', 'art' => 'strom', 'rolle' => 'unter', 'parent_id' => '7', 'quelle' => 'manuell', 'einheit' => '', 'umrechnung' => '', 'ha_faktor' => '', 'is_active' => '1'];
$GLOBALS['inserts'] = [];
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$check('Unterzähler angelegt', $fehler === [] && $GLOBALS['inserts'][0][1]['rolle'] === 'unter' && $GLOBALS['inserts'][0][1]['parent_id'] === 7);
$GLOBALS['parent'] = ['id' => 7, 'art' => 'gas', 'rolle' => 'bezug', 'name' => 'Gas'];
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$check('andere Art abgewiesen', $id === null && str_contains($fehler[0], 'dieselbe Art'));
$GLOBALS['parent'] = ['id' => 7, 'art' => 'strom', 'rolle' => 'unter', 'name' => 'Unter'];
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$check('Unterzähler als Hauptzähler abgewiesen', $id === null);
$GLOBALS['parent'] = null;
$_POST['rolle'] = 'erzeugung'; $_POST['art'] = 'gas';
[$id, $fehler] = meter_save_from_post(null, ['id' => 1]);
$check('Erzeugung erzwingt Strom', $fehler === [] && end($GLOBALS['inserts'])[1]['art'] === 'strom' && end($GLOBALS['inserts'])[1]['parent_id'] === null);

/* ---------- Ansichten rendern ---------- */
$GLOBALS['rows'] = [];
$meterVoll = ['id' => 1, 'art' => 'strom', 'rolle' => 'bezug', 'parent_id' => null, 'parent_name' => null, 'unterzaehler' => 0, 'name' => 'Strom Unterkunft', 'zaehlernummer' => 'Z-1', 'standort' => 'Keller',
    'einheit' => 'kWh', 'umrechnung' => 1, 'quelle' => 'manuell', 'ha_entity' => '', 'ha_faktor' => 1, 'qr_token' => '',
    'qr_connector_id' => null, 'notiz' => '', 'is_active' => 1, 'letzter_stand' => 1590, 'letzte_ablesung' => '2026-03-01 00:00:00',
    'letzte_quelle' => 'manuell', 'ablesungen' => 3, 'connector_name' => null];
$html = render_partial('verbrauch', ['meters' => [$meterVoll], 'karten' => [1 => ['tage30' => 12.0, 'jahr' => 590.0, 'kosten' => 250.0, 'ohne_tarif' => 0, 'alter' => ['stufe' => 'alt', 'tage' => 60], 'ha_fehler' => '', 'anteil' => null]],
    'stats' => verbrauch_stats([], $tarife, 2026), 'tarife' => $tarife, 'jahr' => 2026, 'jahre' => [2026], 'filter' => ['art' => '', 'aktiv' => '', 'q' => '']]);
$check('Übersicht rendert', str_contains($html, 'Strom Unterkunft') && str_contains($html, 'Stand eintragen'));
$check('Übersicht: je Tag zu den letzten 30 Tagen', str_contains($html, '0,40 kWh je Tag'));
$html = render_partial('meter', ['meter' => $meterVoll, 'jahr' => 2026, 'jahre' => [2026], 'staende' => [], 'monate' => verbrauch_monate($st, 2026),
    'kosten' => verbrauch_kosten_jahr($st, $tarife, $meterVoll, 2026), 'abschnitte' => verbrauch_abschnitte($st), 'tarifHeute' => $tarife[1],
    'alter' => ['stufe' => 'frisch', 'tage' => 1], 'haFehler' => '', 'connector' => null, 'zaehlerConnectoren' => [],
    'profil' => verbrauch_profil($st, strtotime('2026-01-01'), strtotime('2026-12-31')), 'unterzaehler' => []]);
$check('Zählerseite rendert', str_contains($html, 'Verbrauch je Monat') && str_contains($html, 'Ablesen per QR-Code'));
$abH = render_partial('meter', ['meter' => $meterVoll, 'jahr' => 2026, 'jahre' => [2026], 'staende' => [], 'monate' => verbrauch_monate($st, 2026),
    'kosten' => verbrauch_kosten_jahr($st, $tarife, $meterVoll, 2026), 'tarifHeute' => $tarife[1], 'alter' => ['stufe' => 'frisch', 'tage' => 1], 'haFehler' => '', 'connector' => null, 'zaehlerConnectoren' => [],
    'profil' => verbrauch_profil($st, strtotime('2026-01-01'), strtotime('2026-12-31')), 'unterzaehler' => [],
    'abschnitte' => [['von' => '2026-10-07 00:00:00', 'bis' => '2026-10-08 00:00:00', 'tage' => 1.0, 'menge' => 64.67, 'je_tag' => 64.67],
                     ['von' => '2026-10-08 00:00:00', 'bis' => '2026-10-08 02:00:00', 'tage' => 0.08, 'menge' => 5.0, 'je_tag' => 60.0]]]);
$check('Abschnitte: je Tag auch bei einem Tag Abstand, nicht bei zwei Stunden', substr_count($abH, '64,67 kWh') === 2 && !str_contains($abH, '60,00 kWh'));
$unter = ['liste' => [
    ['id' => 2, 'name' => 'Strom Halle 1', 'standort' => '', 'is_active' => 1, 'jahr' => 36.1, 'anteil' => 4.8, 'abweichend' => true,
     'zeitraum' => ['von' => strtotime('2026-09-12'), 'bis' => strtotime('2026-10-08'), 'tage' => 26.0], 'haupt_gleich' => 120.0, 'anteil_eigen' => 30.1],
    ['id' => 3, 'name' => 'Strom Keller', 'standort' => 'HAR', 'is_active' => 1, 'jahr' => 200.0, 'anteil' => 26.5, 'abweichend' => false,
     'zeitraum' => ['von' => strtotime('2026-01-01'), 'bis' => strtotime('2026-10-08'), 'tage' => 280.0], 'haupt_gleich' => null, 'anteil_eigen' => null],
], 'hauptzaehler' => 755.6, 'rest' => 519.5, 'zeitraum' => ['von' => strtotime('2026-01-01'), 'bis' => strtotime('2026-10-08'), 'tage' => 280.0], 'abweichend' => 1];
$html = render_partial('meter', ['meter' => $meterVoll + ['unterzaehler' => 2], 'jahr' => 2026, 'jahre' => [2026], 'staende' => [], 'monate' => verbrauch_monate($st, 2026),
    'kosten' => verbrauch_kosten_jahr($st, $tarife, $meterVoll, 2026), 'abschnitte' => [], 'tarifHeute' => $tarife[1], 'alter' => ['stufe' => 'frisch', 'tage' => 1], 'haFehler' => '',
    'connector' => null, 'zaehlerConnectoren' => [], 'profil' => verbrauch_profil($st, strtotime('2026-01-01'), strtotime('2026-12-31')), 'unterzaehler' => $unter]);
$check('Unterzähler: Warnung, Zeitraum, Anteil im eigenen Zeitraum', str_contains($html, 'Die Zeiträume passen nicht zusammen') && str_contains($html, 'ein Unterzähler deckt')
    && str_contains($html, '12.09.–08.10.2026') && str_contains($html, '⚠ 26 Tage') && str_contains($html, '<strong>30,1 %</strong>') && str_contains($html, 'von 120,0 kWh')
    && str_contains($html, 'Zeiträume verschieden') && str_contains($html, 'im eigenen Zeitraum') && str_contains($html, 'Hauptzähler abgelesen 01.01.–08.10.2026'));
$unter['liste'][0]['abweichend'] = false; $unter['abweichend'] = 0;
$html = render_partial('meter', ['meter' => $meterVoll + ['unterzaehler' => 2], 'jahr' => 2026, 'jahre' => [2026], 'staende' => [], 'monate' => verbrauch_monate($st, 2026),
    'kosten' => verbrauch_kosten_jahr($st, $tarife, $meterVoll, 2026), 'abschnitte' => [], 'tarifHeute' => $tarife[1], 'alter' => ['stufe' => 'frisch', 'tage' => 1], 'haFehler' => '',
    'connector' => null, 'zaehlerConnectoren' => [], 'profil' => verbrauch_profil($st, strtotime('2026-01-01'), strtotime('2026-12-31')), 'unterzaehler' => $unter]);
$check('Unterzähler ohne Abweichung: keine Warnung, keine Zusatzspalte', !str_contains($html, 'passen nicht zusammen') && !str_contains($html, 'im eigenen Zeitraum') && !str_contains($html, 'Zeiträume verschieden') && str_contains($html, '01.01.–08.10.2026'));
$html = render_partial('meter_edit', ['meter' => $meterVoll, 'errors' => [], 'entitaeten' => ['sensor.strom' => ['name' => 'Strom', 'einheit' => 'kWh', 'klasse' => 'energy', 'wert' => '1']], 'haHinweis' => '', 'hauptzaehler' => [['id' => 5, 'name' => 'Haupt', 'art' => 'strom']]]);
$check('Zählerformular rendert mit Entitäten', str_contains($html, 'sensor.strom') && str_contains($html, 'data-quelle-block'));
$html = render_partial('tarife', ['jeArt' => ['strom' => ['aktuell' => $tarife[1], 'liste' => [$tarife[0], $tarife[1]]], 'gas' => ['aktuell' => null, 'liste' => []], 'wasser' => ['aktuell' => null, 'liste' => []], 'einspeisung' => ['aktuell' => null, 'liste' => []]], 'heute' => '2026-09-25']);
$check('Tarife rendern', str_contains($html, 'gilt') && str_contains($html, 'kein gültiger Tarif'));
$html = render_partial('tarif_edit', ['tarif' => ['id' => null, 'art' => 'gas', 'name' => '', 'anbieter' => '', 'gueltig_von' => '2026-01-01', 'gueltig_bis' => null, 'arbeitspreis' => '', 'grundpreis_monat' => '', 'einheit' => 'kWh', 'notiz' => ''], 'errors' => []]);
$check('Tarifformular rendert', str_contains($html, 'Arbeitspreis'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
