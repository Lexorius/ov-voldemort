<?php
declare(strict_types=1);
/*
 * SIM-Karten: Zuordnung, Vertragsfristen, verdeckte PIN – und die Liste.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'telefon_landesvorwahl' => '+49',
    'sim_modul_name' => 'SIM-Karten', 'sim_intro' => '', 'sim_vertrag_warnung_tage' => '60'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['nummern'] = [];   // schon vergebene Rufnummern

function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) {
        $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text',
                'label' => '', 'hint' => '', 'sort_order' => 0];
    }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, 'FROM sims WHERE rufnummer')) {
        return in_array($p[0] ?? '', $GLOBALS['nummern'], true) ? 7 : null;
    }
    return $d;
}
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 1; }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'] ?? true; }
function current_user(): ?array { return ['id' => 1]; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'sims'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ================= Zuordnung ================= */
$check('bekannte Ziele', array_keys(SIM_ZIELE) === ['ov', 'fahrzeug', 'fachgruppe', 'person']);
$check('unbekanntes Ziel wird zum Ortsverband',
    sim_ziel_typ('erfunden') === 'ov' && sim_ziel_typ('') === 'ov' && sim_ziel_typ('fahrzeug') === 'fahrzeug');

$check('Fahrzeug mit Kennzeichen', sim_ziel_text([
    'ziel_typ' => 'fahrzeug', 'fahrzeug_label' => 'GKW 1', 'fahrzeug_kennzeichen' => 'THW 99020',
]) === 'GKW 1 · THW 99020');
$check('Fahrzeug ohne Kennzeichen', sim_ziel_text([
    'ziel_typ' => 'fahrzeug', 'fahrzeug_label' => 'MTW', 'fahrzeug_kennzeichen' => '',
]) === 'MTW');
$check('Fachgruppe', sim_ziel_text(['ziel_typ' => 'fachgruppe', 'fachgruppe_label' => 'FGr N']) === 'FGr N');
$check('Person', sim_ziel_text(['ziel_typ' => 'person', 'person_label' => 'Anna Beispiel']) === 'Anna Beispiel');
$check('ohne Zuordnung der Ortsverband', sim_ziel_text(['ziel_typ' => 'ov']) === 'Ortsverband');
$check('gelöschtes Ziel fällt auf', sim_ziel_text(['ziel_typ' => 'fahrzeug', 'fahrzeug_label' => null])
    === 'Fahrzeug (gelöscht)');

/* ================= PIN verdecken ================= */
$check('PIN wird verdeckt', sim_verdeckt('1234') === '••••');
$check('lange PUK auch', mb_strlen(sim_verdeckt('12345678')) === 8);
$check('kurze Zeichen bekommen vier Punkte', sim_verdeckt('12') === '••••');
$check('leer bleibt leer', sim_verdeckt('') === '' && sim_verdeckt('  ') === '');
$check('die Zahl selbst steht nicht drin', !str_contains(sim_verdeckt('1234'), '1'));

/* ================= Vertragsfristen ================= */
$mitV = static fn(string $bis) => ['hat_vertrag' => 1, 'vertrag_bis' => $bis];
$check('unbefristet', sim_vertrag($mitV(''), 60, '2026-01-01')['stufe'] === 'offen');
$check('weit weg ist ok', sim_vertrag($mitV('2026-12-31'), 60, '2026-01-01')['stufe'] === 'ok');
$check('bald fällig', sim_vertrag($mitV('2026-02-15'), 60, '2026-01-01')['stufe'] === 'bald');
$check('abgelaufen', sim_vertrag($mitV('2025-12-31'), 60, '2026-01-01')['stufe'] === 'faellig');
$check('Tage gezählt', sim_vertrag($mitV('2026-01-31'), 60, '2026-01-01')['tage'] === 30);
$check('ohne Vertrag keine Frist',
    sim_vertrag(['hat_vertrag' => 0, 'vertrag_bis' => '2026-01-02'], 60, '2026-01-01')['stufe'] === 'offen');

/* ================= Zahlen für den Kopf ================= */
$liste = [
    ['ziel_typ' => 'fahrzeug', 'kosten_monat' => '9.99', 'vertrag_bis' => '2026-12-31', 'hat_vertrag' => 1],
    ['ziel_typ' => 'ov', 'kosten_monat' => '5.00', 'vertrag_bis' => '2026-02-01', 'hat_vertrag' => 1],
    ['ziel_typ' => 'ov', 'kosten_monat' => null, 'vertrag_bis' => '', 'hat_vertrag' => 0],
];
$s = sim_stats($liste, 60, '2026-01-01');
$check('Karten gezählt', $s['anzahl'] === 3);
$check('Kosten summiert', abs($s['kosten'] - 14.99) < 0.001);
$check('ohne Zuordnung gezählt', $s['ohne_zuordnung'] === 2);
$check('auslaufende Verträge gezählt', $s['vertrag_bald'] === 1);

/* ================= Speichern ================= */
$_POST = ['rufnummer' => '0151 1234567', 'iccid' => '8949 0000 1111 2222 333',
          'ziel_typ' => 'fahrzeug', 'ziel_id_fahrzeug' => '4', 'pin' => '1234', 'puk' => '12345678',
          'anbieter' => 'Telekom', 'kosten_monat' => '9,99', 'vertrag_bis' => '2027-03-31',
          'is_active' => '1', 'hat_vertrag' => '1', 'hat_pin' => '1'];
$GLOBALS['inserts'] = [];
[$id, $fehler] = sim_save_from_post(null, ['id' => 1]);
$daten = $GLOBALS['inserts'][0][1] ?? [];
$check('angelegt', $fehler === [] && ($GLOBALS['inserts'][0][0] ?? '') === 'sims');
$check('Rufnummer international', $daten['rufnummer'] === '+49 151 1234567');
$check('ICCID ohne Leerzeichen', $daten['iccid'] === '89490000111122223 33' || $daten['iccid'] === '8949000011112222333');
$check('Zuordnung übernommen', $daten['ziel_typ'] === 'fahrzeug' && $daten['ziel_id'] === 4);
$check('deutscher Betrag gelesen', abs($daten['kosten_monat'] - 9.99) < 0.001);
$check('PIN und PUK gespeichert', $daten['pin'] === '1234' && $daten['puk'] === '12345678');

// Zum Ortsverband gehört keine Nummer
$_POST['ziel_typ'] = 'ov';
$GLOBALS['inserts'] = [];
sim_save_from_post(null, ['id' => 1]);
$daten = $GLOBALS['inserts'][0][1];
$check('Ortsverband ohne Zielnummer', array_key_exists('ziel_id', $daten) && $daten['ziel_id'] === null);

// Ziel ohne Auswahl
$_POST['ziel_typ'] = 'fachgruppe';
[$id, $fehler] = sim_save_from_post(null, ['id' => 1]);
$check('Zuordnung ohne Auswahl wird abgewiesen', $id === null && count($fehler) === 1);

// Weder Nummer noch ICCID
$_POST = ['rufnummer' => '', 'iccid' => '', 'ziel_typ' => 'ov'];
[$id, $fehler] = sim_save_from_post(null, ['id' => 1]);
$check('ganz ohne Kennung geht nicht', $id === null && count($fehler) === 1);

// Zu kurze ICCID
$_POST = ['rufnummer' => '', 'iccid' => '123', 'ziel_typ' => 'ov'];
[$id, $fehler] = sim_save_from_post(null, ['id' => 1]);
$check('zu kurze ICCID fällt auf', $id === null && count($fehler) === 1);

// Doppelte Rufnummer
$GLOBALS['nummern'] = ['+49 151 1234567'];
$_POST = ['rufnummer' => '0151 1234567', 'iccid' => '', 'ziel_typ' => 'ov'];
[$id, $fehler] = sim_save_from_post(null, ['id' => 1]);
$check('dieselbe Nummer nur einmal', $id === null
    && str_contains($fehler[0] ?? '', 'schon auf einer anderen Karte'));
$GLOBALS['nummern'] = [];

/* ================= Vertrag und PIN zuschaltbar ================= */
$_POST = ['rufnummer' => '0171 7654321', 'iccid' => '', 'ziel_typ' => 'ov',
          'anbieter' => 'Vodafone', 'tarif' => 'Data S', 'datenvolumen' => '5 GB',
          'kosten_monat' => '7,50', 'vertrag_bis' => '2028-01-31',
          'pin' => '0000', 'puk' => '11112222'];
$GLOBALS['inserts'] = [];
sim_save_from_post(null, ['id' => 1]);
$daten = $GLOBALS['inserts'][0][1];
$check('ohne Haken kein Vertrag', $daten['hat_vertrag'] === 0
    && $daten['anbieter'] === '' && $daten['tarif'] === '' && $daten['datenvolumen'] === ''
    && $daten['kosten_monat'] === null && $daten['vertrag_bis'] === null);
$check('ohne Haken keine PIN', $daten['hat_pin'] === 0
    && $daten['pin'] === '' && $daten['puk'] === '');

$_POST['hat_vertrag'] = '1';
$_POST['hat_pin'] = '1';
$GLOBALS['inserts'] = [];
sim_save_from_post(null, ['id' => 1]);
$daten = $GLOBALS['inserts'][0][1];
$check('mit Haken kommt der Vertrag', $daten['hat_vertrag'] === 1
    && $daten['anbieter'] === 'Vodafone' && abs($daten['kosten_monat'] - 7.5) < 0.001
    && $daten['vertrag_bis'] === '2028-01-31');
$check('mit Haken kommen PIN und PUK', $daten['hat_pin'] === 1
    && $daten['pin'] === '0000' && $daten['puk'] === '11112222');

$check('Haken werden gelesen', sim_hat_vertrag(['hat_vertrag' => 1]) && !sim_hat_vertrag([])
    && sim_hat_pin(['hat_pin' => 1]) && !sim_hat_pin(['hat_pin' => 0]));

/* ================= TETRA-Sicherheitskarten ================= */
$check('bekannte Kartenwelten', array_keys(SIM_ARTEN) === ['mobilfunk', 'tetra']);
$check('unbekannte Welt wird Mobilfunk',
    sim_karte_art('erfunden') === 'mobilfunk' && sim_karte_art('tetra') === 'tetra');
$check('TETRA erkannt', sim_ist_tetra(['karte_art' => 'tetra']) && !sim_ist_tetra([]));

$_POST = ['karte_art' => 'tetra', 'issi' => '2621234', 'opta' => 'NW THW OV MUST 01',
          'iccid' => '', 'rufnummer' => '0151 999888', 'ziel_typ' => 'fahrzeug',
          'ziel_id_fahrzeug' => '4', 'hat_pin' => '1', 'pin' => '1234', 'puk' => '87654321'];
$GLOBALS['inserts'] = [];
[$id, $fehler] = sim_save_from_post(null, ['id' => 1]);
$daten = $GLOBALS['inserts'][0][1] ?? [];
$check('TETRA angelegt', $fehler === [] && $daten['karte_art'] === 'tetra');
$check('ISSI und OPTA gespeichert', $daten['issi'] === '2621234' && $daten['opta'] === 'NW THW OV MUST 01');
$check('TETRA hat keine Rufnummer', $daten['rufnummer'] === '');
$check('PIN und PUK gibt es auch bei TETRA', $daten['pin'] === '1234' && $daten['puk'] === '87654321');

$_POST['issi'] = '12 34 567';
$GLOBALS['inserts'] = [];
sim_save_from_post(null, ['id' => 1]);
$check('ISSI wird auf Ziffern gekürzt', $GLOBALS['inserts'][0][1]['issi'] === '1234567');

$_POST['issi'] = '123';
[$id, $fehler] = sim_save_from_post(null, ['id' => 1]);
$check('zu kurze ISSI fällt auf', $id === null && count($fehler) === 1);

$_POST = ['karte_art' => 'tetra', 'issi' => '', 'opta' => '', 'iccid' => '', 'ziel_typ' => 'ov'];
[$id, $fehler] = sim_save_from_post(null, ['id' => 1]);
$check('TETRA ganz ohne Kennung geht nicht', $id === null && count($fehler) === 1);

$check('Kennung: TETRA zeigt die ISSI',
    sim_kennung(['karte_art' => 'tetra', 'issi' => '2621234', 'opta' => 'X', 'iccid' => '']) === '2621234');
$check('Kennung: ohne ISSI die OPTA',
    sim_kennung(['karte_art' => 'tetra', 'issi' => '', 'opta' => 'NW THW OV MUST 01', 'iccid' => ''])
    === 'NW THW OV MUST 01');
$check('Kennung: Mobilfunk zeigt die Nummer',
    sim_kennung(['karte_art' => 'mobilfunk', 'rufnummer' => '+49 151 1234567', 'iccid' => 'x'])
    === '+49 151 1234567');
$check('Bezeichnung nennt die ISSI',
    sim_bezeichnung(['karte_art' => 'tetra', 'issi' => '2621234']) === 'ISSI 2621234');

/* ================= Die Liste ================= */
$_SERVER['REQUEST_URI'] = '/?p=sims';
$_GET = ['p' => 'sims'];
$sims = [
    ['id' => 1, 'rufnummer' => '+49 151 1234567', 'iccid' => '8949000011112222333',
     'typ_label' => 'Fahrzeugrouter', 'typ_color' => '#b45309', 'status_label' => 'Im Einsatz',
     'status_color' => '#15803d', 'ziel_typ' => 'fahrzeug', 'ziel_id' => 4,
     'fahrzeug_label' => 'GKW 1', 'fahrzeug_kennzeichen' => 'THW 99020', 'fachgruppe_label' => null,
     'person_label' => null, 'geraet' => 'Router', 'anbieter' => 'Telekom', 'tarif' => '',
     'datenvolumen' => '10 GB', 'kosten_monat' => '9.99', 'vertrag_bis' => '2027-03-31',
     'ausgegeben_am' => null, 'pin' => '1234', 'puk' => '87654321', 'notiz' => '', 'is_active' => 1,
     'karte_art' => 'mobilfunk', 'issi' => '', 'opta' => '', 'hat_vertrag' => 1, 'hat_pin' => 1],
    ['id' => 2, 'rufnummer' => '', 'iccid' => '8949999988887777666',
     'typ_label' => 'Reservekarte', 'typ_color' => '#64748b', 'status_label' => 'Reserve',
     'status_color' => '#0369a1', 'ziel_typ' => 'ov', 'ziel_id' => null,
     'fahrzeug_label' => null, 'fachgruppe_label' => null, 'person_label' => null,
     'geraet' => '', 'anbieter' => '', 'tarif' => '', 'datenvolumen' => '',
     'kosten_monat' => null, 'vertrag_bis' => null, 'ausgegeben_am' => null,
     'pin' => '', 'puk' => '', 'notiz' => '', 'is_active' => 0,
     'karte_art' => 'mobilfunk', 'issi' => '', 'opta' => '', 'hat_vertrag' => 1, 'hat_pin' => 0],
];
$sims[] = ['id' => 3, 'rufnummer' => '', 'iccid' => '', 'karte_art' => 'tetra',
     'issi' => '2621234', 'opta' => 'NW THW OV MUST 01',
     'typ_label' => 'TETRA Fahrzeugfunk (MRT)', 'typ_color' => '#9f1239',
     'status_label' => 'Im Einsatz', 'status_color' => '#15803d',
     'ziel_typ' => 'fahrzeug', 'ziel_id' => 4, 'fahrzeug_label' => 'GKW 1',
     'fahrzeug_kennzeichen' => 'THW 99020', 'fachgruppe_label' => null, 'person_label' => null,
     'geraet' => 'MRT', 'anbieter' => '', 'tarif' => '', 'datenvolumen' => '',
     'kosten_monat' => null, 'vertrag_bis' => null, 'ausgegeben_am' => null,
     'pin' => '1234', 'puk' => '', 'notiz' => '', 'is_active' => 1,
     'hat_vertrag' => 0, 'hat_pin' => 1];

$html = render_partial('sims', [
    'sims' => $sims, 'stats' => sim_stats($sims, 60, '2026-01-01'), 'warn' => 60,
    'filter' => ['q' => '', 'karte_art' => '', 'typ_id' => null, 'status_id' => null,
                 'ziel_typ' => '', 'aktiv' => 'aktiv', 'sort' => ''],
]);
$check('TETRA-Karte mit ISSI und OPTA', str_contains($html, '2621234')
    && str_contains($html, 'NW THW OV MUST 01') && str_contains($html, '>TETRA</span>'));
$check('ohne Vertrag steht es da', str_contains($html, 'ohne Vertrag'));
$check('Filter nach Kartenwelt', str_contains($html, 'name="karte_art"'));
$check('TETRA-Karten gezählt', str_contains($html, '>TETRA</div>'));
$check('Rufnummer ist anklickbar', str_contains($html, 'href="tel:+491511234567"'));
$check('Zuordnung steht dabei', str_contains($html, 'GKW 1 · THW 99020'));
$check('Art und Status als Plakette', str_contains($html, 'Fahrzeugrouter') && str_contains($html, 'Im Einsatz'));
$check('PIN steht verdeckt da', str_contains($html, '••••') && str_contains($html, 'data-geheim="1234"'));
$check('PUK verdeckt', str_contains($html, 'data-geheim="87654321"'));
$check('ausgemusterte Karte gekennzeichnet', str_contains($html, 'ausgemustert'));
$check('unbefristet wird gesagt', str_contains($html, 'unbefristet'));
$check('Filter nach Zuordnung', str_contains($html, 'name="ziel_typ"'));

// Ohne Rechte keine Geheimnisse
$GLOBALS['rechte'] = false;
$html = render_partial('sims', [
    'sims' => $sims, 'stats' => sim_stats($sims, 60, '2026-01-01'), 'warn' => 60,
    'filter' => ['q' => '', 'karte_art' => '', 'typ_id' => null, 'status_id' => null,
                 'ziel_typ' => '', 'aktiv' => 'aktiv', 'sort' => ''],
]);
$check('ohne Rechte kein PIN', !str_contains($html, 'data-geheim')
    && !str_contains($html, '87654321') && !str_contains($html, 'PIN / PUK'));
$check('ohne Rechte kein Bearbeiten', !str_contains($html, 'p=sim_edit'));
$check('die Karten sieht man trotzdem', str_contains($html, 'GKW 1'));
$GLOBALS['rechte'] = true;

echo "$ok bestanden, $fail fehlgeschlagen\n";
