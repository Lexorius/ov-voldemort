<?php
declare(strict_types=1);
/*
 * Warnmeldungen: auffälliger Verbrauch (Sprung, nachts laufendes Wasser),
 * Erinnerung „Zähler bitte ablesen", Connector-Störungen mit Karenz.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'ha_benachrichtigung_aktiv' => '1', 'verbrauch_anomalie_faktor' => '1.5',
    'connector_warn_minuten' => '15', 'notify_verbrauch_anomalie' => '1', 'notify_zaehler_ablesen' => '1', 'notify_connector_offline' => '1'];
$GLOBALS['state'] = [];
$GLOBALS['queue'] = [];
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    return str_contains($sql, 'FROM settings') ? ($GLOBALS['state'][$p[0] ?? ''] ?? $d) : $d;
}
function db_exec(string $sql, array $p = []): int {
    if (str_contains($sql, 'INSERT INTO settings')) { $GLOBALS['state'][$p[0]] = (string)$p[1]; }
    return 1;
}
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
function notify_ereignis_aktiv(string $key): bool { return ($GLOBALS['settings']['notify_' . $key] ?? '1') === '1'; }
function notify_leitung(): array { return [1, 2]; }
function notify_queue(array $ids, string $ereignis, string $titel, string $text, string $url = ''): int {
    $GLOBALS['queue'][] = ['ids' => $ids, 'ereignis' => $ereignis, 'titel' => $titel, 'text' => $text, 'url' => $url];
    return count($ids);
}
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'verbrauch', 'connector'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$nah = static fn(?float $a, float $b): bool => $a !== null && abs($a - $b) < 0.01;

/** Tagesstände: $proTag je Tag über $tage Tage bis $ende, letzte $sprungTage mit $sprungProTag */
$kette = static function (int $ende, int $tage, float $proTag, int $sprungTage = 0, float $sprungProTag = 0.0, int $schrittStunden = 24): array {
    $st = [];
    $stand = 1000.0;
    $start = $ende - $tage * 86400;
    for ($t = $start; $t <= $ende; $t += $schrittStunden * 3600) {
        $rate = ($ende - $t) < $sprungTage * 86400 ? $sprungProTag : $proTag;
        if ($t > $start) {
            $stand += $rate * $schrittStunden / 24;
        }
        $st[] = ['stand' => round($stand, 3), 'gelesen_am' => date('Y-m-d H:i:s', $t)];
    }
    return $st;
};
$jetzt = strtotime('2026-10-01 08:00:00');

/* ---------- Sprung ---------- */
$st = $kette($jetzt, 63, 10.0, 7, 20.0);
$s = verbrauch_anomalie_sprung($st, $jetzt, 1.5);
$check('doppelter Verbrauch fällt auf', $s !== null && $nah($s['woche_pro_tag'], 20.0) && $nah($s['basis_pro_tag'], 10.0) && $nah((float)$s['faktor'], 2.0) && $s['basis_tage'] === 56);
$check('mit Faktor 2,5 nicht', verbrauch_anomalie_sprung($st, $jetzt, 2.5) === null);
$check('gleichmäßig nicht', verbrauch_anomalie_sprung($kette($jetzt, 63, 10.0), $jetzt, 1.5) === null);
$check('leicht mehr (1,3-fach) nicht', verbrauch_anomalie_sprung($kette($jetzt, 63, 10.0, 7, 13.0), $jetzt, 1.5) === null);
$check('neuer Zähler: 14 Tage Vergleich reichen', verbrauch_anomalie_sprung($kette($jetzt, 21, 10.0, 7, 20.0), $jetzt, 1.5) !== null);
$check('unter 14 Tagen Vergleich keine Aussage', verbrauch_anomalie_sprung($kette($jetzt, 18, 10.0, 7, 20.0), $jetzt, 1.5) === null);
$check('ohne frischen Stand keine Aussage', verbrauch_anomalie_sprung($kette($jetzt - 10 * 86400, 63, 10.0, 7, 20.0), $jetzt, 1.5) === null);
$s = verbrauch_anomalie_sprung($kette($jetzt, 63, 0.0, 7, 3.0), $jetzt, 1.5);
$check('bisher nichts, jetzt Verbrauch', $s !== null && $s['faktor'] === null && $nah($s['woche_pro_tag'], 3.0));
$check('bisher nichts, weiter nichts', verbrauch_anomalie_sprung($kette($jetzt, 63, 0.0), $jetzt, 1.5) === null);

/* ---------- Nacht ---------- */
$nachtKette = static function (int $jetzt, array $nullStunden = [], int $dichte = 1): array {
    $tag = strtotime(date('Y-m-d', $jetzt));
    $st = [];
    $stand = 500.0;
    for ($h = 0; $h <= 8; $h += $dichte) {
        $t = $tag + $h * 3600;
        if ($h > 0) {
            $stand += in_array($h, $nullStunden, true) ? 0.0 : 0.02 * $dichte;
        }
        $st[] = ['stand' => round($stand, 3), 'gelesen_am' => date('Y-m-d H:i:s', $t)];
    }
    return $st;
};
$n = verbrauch_anomalie_nacht($nachtKette($jetzt), $jetzt);
$check('jede Nachtstunde Wasser: Auffälligkeit', $n !== null && $nah($n['menge'], 0.08) && date('H', $n['von']) === '01' && date('H', $n['bis']) === '05');
$check('eine Stunde ohne: nichts', verbrauch_anomalie_nacht($nachtKette($jetzt, [3]), $jetzt) === null);
$check('zu wenige Stände in der Nacht: nichts', verbrauch_anomalie_nacht($nachtKette($jetzt, [], 4), $jetzt) === null);
$frueh = strtotime('2026-10-01 03:00:00');
$n = verbrauch_anomalie_nacht($nachtKette($frueh - 86400), $frueh);
$check('vor 5 Uhr zählt die vorige Nacht', $n !== null && date('Y-m-d', $n['von']) === '2026-09-30');

/* ---------- Alle Zähler ---------- */
$meters = [
    ['id' => 1, 'name' => 'Strom Halle', 'art' => 'strom', 'einheit' => 'kWh', 'rolle' => 'bezug', 'is_active' => 1, 'quelle' => 'ha'],
    ['id' => 2, 'name' => 'Wasser', 'art' => 'wasser', 'einheit' => 'm³', 'rolle' => 'bezug', 'is_active' => 1, 'quelle' => 'ha'],
    ['id' => 3, 'name' => 'Solar', 'art' => 'strom', 'einheit' => 'kWh', 'rolle' => 'erzeugung', 'is_active' => 1, 'quelle' => 'ha'],
    ['id' => 4, 'name' => 'Alt', 'art' => 'gas', 'einheit' => 'kWh', 'rolle' => 'bezug', 'is_active' => 0, 'quelle' => 'manuell'],
];
$laden = static fn(int $id, int $von, int $bis): array => match ($id) {
    1 => $kette($jetzt, 63, 10.0, 7, 20.0),
    2 => array_merge($kette($jetzt - 86400, 62, 0.5), $nachtKette($jetzt)),
    default => $kette($jetzt, 63, 10.0, 7, 50.0),
};
$a = verbrauch_anomalien($meters, $jetzt, $laden);
$check('Strom-Sprung und Wasser-Nacht, Solar und stillgelegt nicht', count($a) === 2
    && $a[0]['meter']['id'] === 1 && $a[0]['art'] === 'sprung' && $a[1]['meter']['id'] === 2 && $a[1]['art'] === 'nacht');
$check('Faktor aus der Einstellung', count(verbrauch_anomalien($meters, $jetzt, $laden, 2.5)) === 1);
$check('Text nennt Zähler und Zahlen', str_starts_with(verbrauch_anomalie_text($a[0]), 'Strom Halle: in den letzten 7 Tagen 20,0 kWh je Tag')
    && str_contains(verbrauch_anomalie_text($a[0]), '2,0-Fache') && str_contains(verbrauch_anomalie_text($a[1]), 'zwischen 1 und 5 Uhr'));

/* ---------- Tägliche Meldung ---------- */
// Ketten, die bis zum jeweiligen Abfragezeitpunkt reichen
$ladenBis = static fn(int $id, int $von, int $bis): array => match ($id) {
    1 => $kette($bis, 63, 10.0, 7, 20.0),
    2 => array_merge($kette($bis - 86400, 62, 0.5), $nachtKette($bis)),
    default => $kette($bis, 63, 10.0, 7, 50.0),
};
$GLOBALS['queue'] = [];
$n = verbrauch_anomalien_taeglich($jetzt, $meters, $ladenBis);
$check('Meldung an die Leitung mit beiden Zeilen', $n === 2 && count($GLOBALS['queue']) === 1 && $GLOBALS['queue'][0]['ereignis'] === 'verbrauch_anomalie'
    && substr_count($GLOBALS['queue'][0]['text'], "\n") === 1 && $GLOBALS['queue'][0]['url'] === '?p=verbrauch');
$check('am nächsten Tag nicht noch einmal', verbrauch_anomalien_taeglich($jetzt + 86400, $meters, $ladenBis) === 0);
$check('nach drei Tagen wieder', verbrauch_anomalien_taeglich($jetzt + 3 * 86400, $meters, $ladenBis) === 2);
$GLOBALS['settings']['notify_verbrauch_anomalie'] = '0';
$check('abgeschaltet nichts', verbrauch_anomalien_taeglich($jetzt + 10 * 86400, $meters, $ladenBis) === 0);
$GLOBALS['settings']['notify_verbrauch_anomalie'] = '1';

/* ---------- Zähler bitte ablesen ---------- */
$GLOBALS['queue'] = [];
$GLOBALS['state'] = ['verbrauch_ha_fehler_2' => 'Entität unbekannt'];
$zaehler = [
    ['id' => 1, 'name' => 'Strom', 'quelle' => 'manuell', 'is_active' => 1, 'letzte_ablesung' => date('Y-m-d H:i:s', $jetzt - 60 * 86400)],
    ['id' => 2, 'name' => 'Wasser HA', 'quelle' => 'ha', 'is_active' => 1, 'letzte_ablesung' => date('Y-m-d H:i:s', $jetzt - 3600)],
    ['id' => 3, 'name' => 'Gas', 'quelle' => 'manuell', 'is_active' => 1, 'letzte_ablesung' => date('Y-m-d H:i:s', $jetzt - 2 * 86400)],
    ['id' => 4, 'name' => 'Neu', 'quelle' => 'manuell', 'is_active' => 1, 'letzte_ablesung' => null],
    ['id' => 5, 'name' => 'Weg', 'quelle' => 'manuell', 'is_active' => 0, 'letzte_ablesung' => null],
];
$n = verbrauch_erinnerungen_taeglich($jetzt, $zaehler);
$t = $GLOBALS['queue'][0]['text'] ?? '';
$check('alt, HA-Fehler und nie – frisch und stillgelegt nicht', $n === 2 && str_contains($t, 'Strom: letzter Stand vor 60 Tagen')
    && str_contains($t, 'Wasser HA: Home Assistant liefert keinen Stand (Entität unbekannt)') && str_contains($t, 'Neu: noch nie abgelesen')
    && !str_contains($t, 'Gas') && !str_contains($t, 'Weg') && $GLOBALS['queue'][0]['titel'] === '3 Zähler bitte ablesen');
$check('in der Woche danach Ruhe', verbrauch_erinnerungen_taeglich($jetzt + 6 * 86400, $zaehler) === 0);
$check('nach sieben Tagen erneut', verbrauch_erinnerungen_taeglich($jetzt + 7 * 86400, $zaehler) === 2);

/* ---------- Connector-Störung ---------- */
$GLOBALS['queue'] = [];
$GLOBALS['state'] = [];
$c = ['id' => 7, 'name' => 'feedback', 'is_active' => 1];
$check('ohne Störung nichts', connector_stoerung($c) === null && connector_ausfall_melden($jetzt, [$c]) === 0);
connector_stoerung_merken($c, 'Verbindung abgelehnt', $jetzt);
connector_stoerung_merken($c, 'Zeitüberschreitung', $jetzt + 120);
$st = connector_stoerung($c);
$check('Störung mit erstem Zeitpunkt und letztem Grund', $st !== null && $st['seit'] === $jetzt && $st['text'] === 'Zeitüberschreitung' && !$st['gemeldet']);
$check('vor der Karenz keine Meldung', connector_ausfall_melden($jetzt + 14 * 60, [$c]) === 0 && $GLOBALS['queue'] === []);
$n = connector_ausfall_melden($jetzt + 16 * 60, [$c]);
$check('nach der Karenz Meldung an die Leitung', $n === 2 && count($GLOBALS['queue']) === 1 && $GLOBALS['queue'][0]['ereignis'] === 'connector_offline'
    && str_contains($GLOBALS['queue'][0]['text'], '„feedback" antwortet seit 16 Minuten nicht') && str_contains($GLOBALS['queue'][0]['text'], 'Zeitüberschreitung')
    && connector_stoerung($c)['gemeldet']);
$check('je Ausfall nur einmal', connector_ausfall_melden($jetzt + 3600, [$c]) === 0 && count($GLOBALS['queue']) === 1);
connector_stoerung_beendet($c, $jetzt + 2 * 3600);
$check('Entwarnung mit Dauer, Störung gelöscht', count($GLOBALS['queue']) === 2 && $GLOBALS['queue'][1]['titel'] === 'Connector wieder erreichbar'
    && str_contains($GLOBALS['queue'][1]['text'], 'nach 2 Stunden') && connector_stoerung($c) === null);
connector_stoerung_merken($c, 'kurz weg', $jetzt);
connector_stoerung_beendet($c, $jetzt + 60);
$check('kurze Störung ohne Meldung: auch keine Entwarnung', count($GLOBALS['queue']) === 2 && connector_stoerung($c) === null);
$check('Dauer lesbar', connector_dauer_text(90) === '2 Minuten' && connector_dauer_text(59) === '1 Minute' && connector_dauer_text(7200) === '2 Stunden'
    && connector_dauer_text(3 * 86400) === '3 Tagen');
$GLOBALS['settings']['connector_warn_minuten'] = '60';
settings_reset_cache();
connector_stoerung_merken($c, 'x', $jetzt);
$check('Karenz aus der Einstellung', connector_ausfall_melden($jetzt + 30 * 60, [$c]) === 0 && connector_ausfall_melden($jetzt + 61 * 60, [$c]) === 2);

/* ---------- Ansicht ---------- */
$html = render_partial('admin/connectors', ['liste' => [$c + ['url' => 'https://x', 'kurz_url' => '', 'fuer_fahrzeuge' => 1, 'fuer_veranstaltungen' => 0, 'fuer_bestand' => 0, 'fuer_verbrauch' => 0, 'pubkey' => 'a', 'server_pub' => 'b', 'pem' => 'c', 'letzter_abruf' => '2026-10-01 07:00:00', 'gekoppelt_am' => '2026-01-01 00:00:00', 'version' => '1.5.0']],
    'fahrzeuge' => [], 'aktiv' => true, 'hinweis' => '', 'fehler' => '']);
$check('Liste zeigt die Störung', str_contains($html, 'Störung') && str_contains($html, 'seit 01.10.2026'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
