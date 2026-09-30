<?php
declare(strict_types=1);
/*
 * Funkgeräte: Gruppen, Karten buchen und die Bestandsmeldung per QR-Code.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'telefon_landesvorwahl' => '+49',
    'funk_modul_name' => 'Funkgeräte', 'funk_intro' => '', 'funk_pruefung_warnung_tage' => '30'];
$GLOBALS['updates'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['audit'] = [];

function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return $GLOBALS['rows'] ?? []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) {
        $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text',
                'label' => '', 'hint' => '', 'sort_order' => 0];
    }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $GLOBALS['val'] ?? $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 1; }
function db_update(string $t, array $d, string $w, array $p): int {
    $GLOBALS['updates'][] = [$t, $d, $w, $p]; return 1;
}
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'webpush', 'sims', 'radios'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ================= Zuordnung und Kennzahlen ================= */
$check('Ziele wie bei den Karten', array_keys(RADIO_ZIELE) === ['ov', 'fahrzeug', 'fachgruppe', 'person']);
$check('Fahrzeug als Ziel', radio_ziel_text([
    'ziel_typ' => 'fahrzeug', 'fahrzeug_label' => 'GKW 1', 'fahrzeug_kennzeichen' => 'THW 99020',
]) === 'GKW 1 · THW 99020');

$check('Prüfung offen', radio_pruefung(['pruefung_bis' => ''], 30, '2026-01-01')['stufe'] === 'offen');
$check('Prüfung bald', radio_pruefung(['pruefung_bis' => '2026-01-20'], 30, '2026-01-01')['stufe'] === 'bald');
$check('Prüfung überfällig', radio_pruefung(['pruefung_bis' => '2025-12-01'], 30, '2026-01-01')['stufe'] === 'faellig');

$check('noch nie gesehen', radio_gesehen([])['stufe'] === 'nie');
$check('frisch gesehen',
    radio_gesehen(['zuletzt_gesehen' => '2026-01-01 10:00:00'], 30, '2026-01-10 10:00:00')['stufe'] === 'frisch');
$check('lange her',
    radio_gesehen(['zuletzt_gesehen' => '2025-11-01 10:00:00'], 30, '2026-01-10 10:00:00')['stufe'] === 'alt');

$geraete = [
    ['ziel_typ' => 'fahrzeug', 'karten' => 1, 'pruefung_bis' => '2026-01-20',
     'zuletzt_gesehen' => date('Y-m-d H:i:s')],
    ['ziel_typ' => 'ov', 'karten' => 0, 'pruefung_bis' => '', 'zuletzt_gesehen' => null],
];
$s = radio_stats($geraete, 30, '2026-01-01');
$check('Geräte gezählt', $s['anzahl'] === 2 && $s['mit_karte'] === 1);
$check('ohne Zuordnung gezählt', $s['ohne_zuordnung'] === 1);
$check('fällige Prüfung gezählt', $s['pruefung_bald'] === 1);
$check('lange nicht gesehen gezählt', $s['lange_nicht_gesehen'] === 1);

/* ================= Speichern ================= */
$_POST = ['bezeichnung' => 'MRT GKW 1', 'typ_id' => '3', 'status_id' => '5',
          'hersteller' => 'Motorola', 'seriennummer' => 'SN-4711', 'funkrufname' => 'Heros 24/51',
          'ziel_typ' => 'fahrzeug', 'ziel_id_fahrzeug' => '4', 'group_id' => '2',
          'standort' => 'Ladeschale', 'pruefung_bis' => '2027-01-31', 'is_active' => '1'];
$GLOBALS['inserts'] = [];
[$id, $fehler] = radio_save_from_post(null, ['id' => 1]);
$daten = $GLOBALS['inserts'][0][1] ?? [];
$check('Gerät angelegt', $fehler === [] && ($GLOBALS['inserts'][0][0] ?? '') === 'radios');
$check('Zuordnung und Gruppe übernommen',
    $daten['ziel_typ'] === 'fahrzeug' && $daten['ziel_id'] === 4 && $daten['group_id'] === 2);
$check('Stammdaten übernommen', $daten['seriennummer'] === 'SN-4711'
    && $daten['funkrufname'] === 'Heros 24/51' && $daten['pruefung_bis'] === '2027-01-31');

$_POST['bezeichnung'] = '';
[$id, $fehler] = radio_save_from_post(null, ['id' => 1]);
$check('ohne Bezeichnung geht nicht', $id === null && count($fehler) === 1);

$_POST['bezeichnung'] = 'HRT 03';
$_POST['ziel_typ'] = 'fachgruppe';
unset($_POST['ziel_id_fachgruppe']);
[$id, $fehler] = radio_save_from_post(null, ['id' => 1]);
$check('Zuordnung ohne Auswahl wird abgewiesen', $id === null && count($fehler) === 1);

$GLOBALS['val'] = 9;   // Seriennummer schon vergeben
$_POST['ziel_typ'] = 'ov';
[$id, $fehler] = radio_save_from_post(null, ['id' => 1]);
$check('doppelte Seriennummer fällt auf', $id === null
    && str_contains($fehler[0] ?? '', 'schon an einem anderen Gerät'));
$GLOBALS['val'] = null;

/* ================= Gruppen ================= */
$_POST = ['name' => 'HRT-Koffer Zugtrupp', 'lagerort' => 'Funkraum', 'ziel_typ' => 'ov', 'is_active' => '1'];
$GLOBALS['inserts'] = [];
[$id, $fehler] = radio_group_save_from_post(null, ['id' => 1]);
$check('Gruppe angelegt', $fehler === [] && $GLOBALS['inserts'][0][0] === 'radio_groups'
    && $GLOBALS['inserts'][0][1]['name'] === 'HRT-Koffer Zugtrupp');

$_POST['name'] = '';
[$id, $fehler] = radio_group_save_from_post(null, ['id' => 1]);
$check('Gruppe ohne Namen geht nicht', $id === null && count($fehler) === 1);

/* ================= Karte ins Gerät buchen ================= */
$radio = ['id' => 7, 'bezeichnung' => 'MRT GKW 1', 'ziel_typ' => 'fahrzeug', 'ziel_id' => 4];
$sim = ['id' => 3, 'karte_art' => 'tetra', 'issi' => '2621234', 'rufnummer' => ''];
$GLOBALS['updates'] = [];
radio_card_add($radio, $sim, ['id' => 1]);
[$tabelle, $daten, , $wo] = $GLOBALS['updates'][0];
$check('Karte bekommt das Gerät', $tabelle === 'sims' && $daten['radio_id'] === 7 && $wo[0] === 3);
$check('Karte übernimmt die Zuordnung',
    $daten['ziel_typ'] === 'fahrzeug' && $daten['ziel_id'] === 4);

$GLOBALS['updates'] = [];
radio_card_add($radio, $sim, ['id' => 1], false);
$check('auf Wunsch ohne Zuordnung', !array_key_exists('ziel_typ', $GLOBALS['updates'][0][1]));

$GLOBALS['updates'] = [];
radio_card_remove($sim, ['id' => 1]);
$check('Karte wieder frei', $GLOBALS['updates'][0][1]['radio_id'] === null);

/* ================= Bestandsmeldung ================= */
$GLOBALS['updates'] = [];
$GLOBALS['inserts'] = [];
$text = radio_bestand_anwenden('geraet', ['id' => 7, 'bezeichnung' => 'MRT GKW 1'],
    ['da' => true, 'melder' => 'Anna'], strtotime('2026-01-10 09:00:00'));
$check('Gerät als gesehen eingetragen', $GLOBALS['updates'][0][0] === 'radios'
    && $GLOBALS['updates'][0][1]['zuletzt_gesehen'] === '2026-01-10 09:00:00'
    && $GLOBALS['updates'][0][1]['zuletzt_melder'] === 'Anna');
$check('Klartext fürs Protokoll', str_contains($text, 'MRT GKW 1 am Lagerort'));
$check('Melder wird gekürzt gespeichert',
    mb_strlen((string)$GLOBALS['updates'][0][1]['zuletzt_melder']) <= 60);

// Gruppe vollzählig: alle meldbaren Geräte gelten als gesehen – die fest verbauten nicht
$GLOBALS['updates'] = [];
$text = radio_bestand_anwenden('gruppe', ['id' => 2, 'name' => 'HRT-Koffer', 'geraete' => 8, 'meldbar' => 8],
    ['da' => true, 'anzahl' => 8, 'melder' => ''], strtotime('2026-01-10 09:00:00'));
$check('Gruppe eingetragen', $GLOBALS['updates'][0][0] === 'radio_groups'
    && $GLOBALS['updates'][0][1]['zuletzt_anzahl'] === 8);
$check('vollzählig: auch die Geräte, aber nur die meldbaren', count($GLOBALS['updates']) === 2
    && $GLOBALS['updates'][1][0] === 'radios'
    && str_contains($GLOBALS['updates'][1][2], 'group_id') && str_contains($GLOBALS['updates'][1][2], 'fest_verbaut = 0'));
$check('Klartext nennt die Zahlen', str_contains($text, '8 von 8'));

// Gruppe mit zwei verbauten MRTs: "alle" heißt 7, nicht 9
$GLOBALS['updates'] = [];
$text = radio_bestand_anwenden('gruppe', ['id' => 3, 'name' => 'FG Wasser', 'geraete' => 9, 'meldbar' => 7],
    ['da' => true], strtotime('2026-01-10 09:00:00'));
$check('fest verbaute zählen nicht: 7 von 7', str_contains($text, '7 von 7') && count($GLOBALS['updates']) === 2);
$check('QR-Name nennt die meldbaren', radio_group_qr_name(['name' => 'FG Wasser', 'geraete' => 9, 'meldbar' => 7]) === 'FG Wasser · 7 Geräte');

// Fest verbautes Gerät gilt über den Fahrzeugstandort als gesehen
$g = radio_gesehen(['fest_verbaut' => 1, 'ziel_typ' => 'fahrzeug', 'zuletzt_gesehen' => null,
    'fahrzeug_geo_at' => '2026-01-09 20:00:00'], 30, '2026-01-10 10:00:00');
$check('verbaut: Fahrzeugstandort zählt', $g['stufe'] === 'frisch' && $g['ueber'] === 'fahrzeug' && $g['wann'] === '2026-01-09 20:00:00');
$g = radio_gesehen(['fest_verbaut' => 1, 'ziel_typ' => 'fahrzeug', 'zuletzt_gesehen' => '2026-01-10 08:00:00',
    'fahrzeug_geo_at' => '2026-01-09 20:00:00'], 30, '2026-01-10 10:00:00');
$check('jüngere Meldung am Gerät schlägt den Fahrzeugstandort', $g['ueber'] === 'meldung');
$g = radio_gesehen(['fest_verbaut' => 0, 'ziel_typ' => 'fahrzeug', 'zuletzt_gesehen' => null,
    'fahrzeug_geo_at' => '2026-01-09 20:00:00'], 30, '2026-01-10 10:00:00');
$check('nicht verbaut: Fahrzeugstandort zählt nicht', $g['stufe'] === 'nie');
$check('MRT und FRT sind die Vorgabe', RADIO_FEST_VERBAUT === ['mrt', 'frt']);

$_POST = ['bezeichnung' => 'MRT GKW 1', 'ziel_typ' => 'ov', 'is_active' => '1', 'fest_verbaut' => '1'];
$GLOBALS['inserts'] = []; $GLOBALS['val'] = null;
radio_save_from_post(null, ['id' => 1]);
$check('Kennzeichen wird gespeichert', ($GLOBALS['inserts'][0][1]['fest_verbaut'] ?? null) === 1);

// Unvollständig: die Geräte bleiben unberührt
$GLOBALS['updates'] = [];
$text = radio_bestand_anwenden('gruppe', ['id' => 2, 'name' => 'HRT-Koffer', 'geraete' => 8],
    ['da' => true, 'anzahl' => 6], strtotime('2026-01-10 09:00:00'));
$check('unvollständig: nur die Gruppe', count($GLOBALS['updates']) === 1
    && $GLOBALS['updates'][0][1]['zuletzt_anzahl'] === 6);
$check('Klartext nennt die Lücke', str_contains($text, '6 von 8'));

// Ohne Angabe gilt die Gruppe als vollzählig
$GLOBALS['updates'] = [];
radio_bestand_anwenden('gruppe', ['id' => 2, 'name' => 'HRT-Koffer', 'geraete' => 5],
    ['da' => true], strtotime('2026-01-10 09:00:00'));
$check('ohne Anzahl gilt alles als da', $GLOBALS['updates'][0][1]['zuletzt_anzahl'] === 5);

// Unsinnige Anzahl wird begrenzt
$GLOBALS['updates'] = [];
radio_bestand_anwenden('gruppe', ['id' => 2, 'name' => 'X', 'geraete' => 5],
    ['da' => true, 'anzahl' => -3], time());
$check('negative Anzahl wird null', $GLOBALS['updates'][0][1]['zuletzt_anzahl'] === 0);

/* ================= Bezeichnung im QR-Anker ================= */
$check('Gerätename mit Funkrufname',
    radio_qr_name(['bezeichnung' => 'MRT GKW 1', 'funkrufname' => 'Heros 24/51'])
    === 'MRT GKW 1 · Heros 24/51');
$check('Gruppenname mit Anzahl',
    radio_group_qr_name(['name' => 'HRT-Koffer', 'geraete' => 8]) === 'HRT-Koffer · 8 Geräte');
$check('Gruppe ohne Geräte', radio_group_qr_name(['name' => 'Leer', 'geraete' => 0]) === 'Leer');

echo "$ok bestanden, $fail fehlgeschlagen\n";
