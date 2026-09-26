<?php
declare(strict_types=1);

/**
 * Verbrauch: Zähler für Strom, Gas und Wasser, ihre Stände und die Tarife.
 *
 * Ein Zähler hat eine Quelle: Entweder liest Home Assistant ihn (eine
 * Entität, die der Abruf regelmäßig abfragt), oder jemand trägt den Stand
 * von Hand ein – am Bildschirm oder per QR-Code am Zähler über den
 * Connector. Die Stände sind eine Kette von Ablesungen; alles Weitere
 * (Verbrauch je Monat, je Jahr, Kosten) wird daraus gerechnet.
 *
 * Tarife gelten je Art mit einem Zeitraum von–bis. Kosten = Arbeitspreis ×
 * Menge (bei Gas nach Umrechnung in kWh) + Grundpreis anteilig je Tag.
 */

const METER_ARTEN = [
    'strom'  => ['label' => 'Strom',  'einheit' => 'kWh', 'color' => '#b45309', 'icon' => 'strom'],
    'gas'    => ['label' => 'Gas',    'einheit' => 'm³',  'color' => '#0369a1', 'icon' => 'gas'],
    'wasser' => ['label' => 'Wasser', 'einheit' => 'm³',  'color' => '#0e7490', 'icon' => 'wasser'],
];
const METER_QUELLEN = ['manuell' => 'von Hand', 'ha' => 'Home Assistant'];
const READING_QUELLEN = ['manuell' => 'von Hand', 'ha' => 'Home Assistant', 'qr' => 'QR-Code'];
/** Tage ohne Stand, ab denen ein Zähler als vernachlässigt gilt */
const METER_WARN_TAGE = 35;

/** Nur bekannte Arten zulassen. Reine Funktion. */
function meter_art(string $art): string
{
    return array_key_exists($art, METER_ARTEN) ? $art : 'strom';
}

/** Menge lesbar: 1.234,5 kWh. Reine Funktion. */
function menge(float|int|string|null $v, string $einheit = '', int $dezimal = 1): string
{
    if ($v === null || $v === '') {
        return '–';
    }
    $text = number_format((float)$v, $dezimal, ',', '.');
    return $einheit !== '' ? $text . ' ' . $einheit : $text;
}

/* ==================================================================== */
/* Zähler                                                                */
/* ==================================================================== */

function meter_select(): string
{
    return 'SELECT m.*, c.name AS connector_name,
                   (SELECT r.stand FROM meter_readings r WHERE r.meter_id = m.id
                     ORDER BY r.gelesen_am DESC, r.id DESC LIMIT 1) AS letzter_stand,
                   (SELECT r.gelesen_am FROM meter_readings r WHERE r.meter_id = m.id
                     ORDER BY r.gelesen_am DESC, r.id DESC LIMIT 1) AS letzte_ablesung,
                   (SELECT r.quelle FROM meter_readings r WHERE r.meter_id = m.id
                     ORDER BY r.gelesen_am DESC, r.id DESC LIMIT 1) AS letzte_quelle,
                   (SELECT COUNT(*) FROM meter_readings r WHERE r.meter_id = m.id) AS ablesungen
            FROM meters m
            LEFT JOIN connectors c ON c.id = m.qr_connector_id';
}

/** $f: art, aktiv ('alle'), q */
function meter_query(array $f = []): array
{
    $w = [];
    $p = [];
    if (!empty($f['art'])) {
        $w[] = 'm.art = ?';
        $p[] = meter_art((string)$f['art']);
    }
    if (($f['aktiv'] ?? '') !== 'alle') {
        $w[] = 'm.is_active = 1';
    }
    if (!empty($f['q'])) {
        $w[] = '(m.name LIKE ? OR m.zaehlernummer LIKE ? OR m.standort LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like);
    }
    return db_all(meter_select() . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY m.is_active DESC, m.art, m.name', $p);
}

function meter_find(int $id): ?array
{
    return $id > 0 ? db_row(meter_select() . ' WHERE m.id = ?', [$id]) : null;
}

/** Zähler aus dem Formular speichern. Rückgabe: [id, fehler[]] */
function meter_save_from_post(?array $existing, array $user): array
{
    $errors = [];
    $name = post_str('name');
    if ($name === '') {
        $errors[] = 'Bitte einen Namen angeben, z. B. „Strom Unterkunft".';
    }
    $art = meter_art(post_str('art', (string)($existing['art'] ?? 'strom')));
    $quelle = array_key_exists(post_str('quelle'), METER_QUELLEN) ? post_str('quelle') : 'manuell';
    $entity = trim(post_str('ha_entity'));
    if ($quelle === 'ha') {
        if ($entity === '') {
            $errors[] = 'Für die Quelle Home Assistant braucht es eine Entität (z. B. sensor.strom_gesamt).';
        } elseif (!preg_match('/^[a-z0-9_]+\.[a-z0-9_]+$/', $entity)) {
            $errors[] = 'Die Entität sieht nicht richtig aus – erwartet wird etwa sensor.strom_gesamt.';
        }
    }
    $einheit = mb_substr(post_str('einheit') ?: METER_ARTEN[$art]['einheit'], 0, 10);
    $umrechnung = post_dec('umrechnung', 1.0);
    if ($umrechnung <= 0) {
        $umrechnung = 1.0;
    }
    $haFaktor = post_dec('ha_faktor', 1.0);
    if ($haFaktor <= 0) {
        $haFaktor = 1.0;
    }
    if ($errors) {
        return [null, $errors];
    }

    $data = [
        'art'           => $art,
        'name'          => mb_substr($name, 0, 150),
        'zaehlernummer' => mb_substr(post_str('zaehlernummer'), 0, 80),
        'standort'      => mb_substr(post_str('standort'), 0, 150),
        'einheit'       => $einheit,
        'umrechnung'    => round($umrechnung, 4),
        'quelle'        => $quelle,
        'ha_entity'     => $quelle === 'ha' ? mb_substr($entity, 0, 200) : '',
        'ha_faktor'     => round($haFaktor, 6),
        'notiz'         => post_str('notiz'),
        'is_active'     => post_bool('is_active'),
    ];
    if ($existing) {
        db_update('meters', $data, 'id = ?', [(int)$existing['id']]);
        $id = (int)$existing['id'];
        audit('zaehler.bearbeitet', 'meter', $id, $data['name']);
    } else {
        $data['created_by'] = (int)$user['id'];
        $id = db_insert('meters', $data);
        audit('zaehler.angelegt', 'meter', $id, $data['name']);
    }
    return [$id, []];
}

function meter_delete(array $meter): void
{
    db_exec('DELETE FROM meters WHERE id = ?', [(int)$meter['id']]);
    audit('zaehler.geloescht', 'meter', (int)$meter['id'], (string)$meter['name']);
}

/** Wie lange ist der letzte Stand her? Reine Funktion. */
function meter_stand_alter(array $meter, ?string $jetzt = null): array
{
    $letzte = (string)($meter['letzte_ablesung'] ?? '');
    if ($letzte === '') {
        return ['stufe' => 'nie', 'tage' => null];
    }
    $tage = (int)floor((strtotime($jetzt ?? date('Y-m-d H:i:s')) - strtotime($letzte)) / 86400);
    return ['stufe' => $tage > METER_WARN_TAGE ? 'alt' : 'frisch', 'tage' => $tage];
}

/* ==================================================================== */
/* Zählerstände                                                          */
/* ==================================================================== */

function readings_query(int $meterId, ?int $jahr = null, int $limit = 0): array
{
    $w = ['meter_id = ?'];
    $p = [$meterId];
    if ($jahr !== null) {
        $w[] = 'YEAR(gelesen_am) = ?';
        $p[] = $jahr;
    }
    return db_all('SELECT r.*, u.display_name AS erfasser FROM meter_readings r
                   LEFT JOIN users u ON u.id = r.created_by
                   WHERE ' . implode(' AND ', $w) . ' ORDER BY gelesen_am DESC, id DESC'
        . ($limit > 0 ? ' LIMIT ' . (int)$limit : ''), $p);
}

/** Alle Stände eines Zählers, älteste zuerst – die Reihenfolge für die Rechnung */
function readings_alle(int $meterId): array
{
    return db_all('SELECT stand, gelesen_am, quelle FROM meter_readings WHERE meter_id = ?
                   ORDER BY gelesen_am ASC, id ASC', [$meterId]);
}

/**
 * Einen Stand eintragen. Prüft, dass er zur Kette passt: nicht kleiner als
 * der letzte davor (außer ausdrücklich erlaubt, etwa nach Zählerwechsel).
 * Rückgabe: [id, fehler]
 */
function reading_add(array $meter, float $stand, string $gelesenAm, string $quelle = 'manuell',
                     string $melder = '', string $notiz = '', ?array $user = null, bool $ruecklauf = false): array
{
    if ($stand < 0) {
        return [null, 'Ein Zählerstand kann nicht negativ sein.'];
    }
    $t = strtotime($gelesenAm);
    if ($t === false) {
        return [null, 'Der Zeitpunkt ist nicht lesbar.'];
    }
    if ($t > time() + 3600) {
        return [null, 'Der Zeitpunkt liegt in der Zukunft.'];
    }
    $gelesenAm = date('Y-m-d H:i:s', $t);
    $vorher = db_row('SELECT stand, gelesen_am FROM meter_readings WHERE meter_id = ? AND gelesen_am <= ?
                      ORDER BY gelesen_am DESC, id DESC LIMIT 1', [(int)$meter['id'], $gelesenAm]);
    if ($vorher && (float)$vorher['stand'] > $stand && !$ruecklauf) {
        return [null, sprintf('Der Stand %s liegt unter dem vorherigen (%s vom %s). Falls der Zähler getauscht wurde, „Rücklauf zulassen" ankreuzen.',
            menge($stand, (string)$meter['einheit'], 3), menge((float)$vorher['stand'], (string)$meter['einheit'], 3),
            de_datetime((string)$vorher['gelesen_am']))];
    }
    $id = db_insert('meter_readings', [
        'meter_id'   => (int)$meter['id'],
        'stand'      => round($stand, 3),
        'gelesen_am' => $gelesenAm,
        'quelle'     => array_key_exists($quelle, READING_QUELLEN) ? $quelle : 'manuell',
        'melder'     => mb_substr(trim($melder), 0, 60),
        'notiz'      => mb_substr(trim($notiz), 0, 255),
        'created_by' => $user ? (int)$user['id'] : null,
    ]);
    audit('zaehler.stand', 'meter', (int)$meter['id'],
        sprintf('%s: %s (%s)', $meter['name'], menge($stand, (string)$meter['einheit'], 3), READING_QUELLEN[$quelle] ?? $quelle));
    return [$id, null];
}

function reading_delete(array $meter, int $readingId): bool
{
    $n = db_exec('DELETE FROM meter_readings WHERE id = ? AND meter_id = ?', [$readingId, (int)$meter['id']]);
    if ($n > 0) {
        audit('zaehler.stand.geloescht', 'meter', (int)$meter['id'], (string)$readingId);
    }
    return $n > 0;
}

/* ==================================================================== */
/* Rechnen: Verbrauch aus der Kette der Stände                           */
/* ==================================================================== */

/**
 * Zählerstand zu einem Zeitpunkt, zwischen zwei Ablesungen linear
 * geschätzt. Vor der ersten Ablesung gibt es nichts (null); nach der
 * letzten gilt die letzte. Bei einem Rücklauf (Zählerwechsel) zählt der
 * Abschnitt davor nicht mit. Reine Funktion; $staende älteste zuerst.
 */
function verbrauch_stand_am(array $staende, int $ts): ?float
{
    if (!$staende) {
        return null;
    }
    $erste = strtotime((string)$staende[0]['gelesen_am']);
    if ($ts < $erste) {
        return null;
    }
    $vor = null;
    foreach ($staende as $s) {
        $t = strtotime((string)$s['gelesen_am']);
        if ($t === $ts) {
            return (float)$s['stand'];
        }
        if ($t > $ts) {
            if ($vor === null) {
                return null;
            }
            $tv = strtotime((string)$vor['gelesen_am']);
            $anteil = $t > $tv ? ($ts - $tv) / ($t - $tv) : 0;
            $a = (float)$vor['stand'];
            $b = (float)$s['stand'];
            if ($b < $a) {
                return $a;   // Rücklauf: bis zum Wechsel gilt der alte Stand
            }
            return $a + ($b - $a) * $anteil;
        }
        $vor = $s;
    }
    return (float)$vor['stand'];
}

/**
 * Verbrauch in einem Zeitraum – als Summe der Zuwächse, Rückläufe zählen
 * nicht. Reine Funktion; $staende älteste zuerst.
 */
function verbrauch_zwischen(array $staende, int $von, int $bis): ?float
{
    if ($bis <= $von) {
        return 0.0;
    }
    $startStand = verbrauch_stand_am($staende, $von);
    $endStand = verbrauch_stand_am($staende, $bis);
    if ($startStand === null || $endStand === null) {
        // Zeitraum beginnt vor der ersten Ablesung: ab der ersten zählen
        if ($endStand === null) {
            return null;
        }
        $startStand = (float)$staende[0]['stand'];
        $von = strtotime((string)$staende[0]['gelesen_am']);
        if ($von >= $bis) {
            return 0.0;
        }
    }
    // Rückläufe innerhalb des Zeitraums herausrechnen
    $summe = $endStand - $startStand;
    $vor = null;
    foreach ($staende as $s) {
        $t = strtotime((string)$s['gelesen_am']);
        if ($t <= $von || $t > $bis) {
            if ($t <= $von) {
                $vor = $s;
            }
            continue;
        }
        if ($vor !== null && (float)$s['stand'] < (float)$vor['stand']) {
            $summe += (float)$vor['stand'] - (float)$s['stand'];
        }
        $vor = $s;
    }
    return max(0.0, $summe);
}

/** Verbrauch je Monat eines Jahres, immer zwölf Werte (null = keine Daten). Reine Funktion. */
function verbrauch_monate(array $staende, int $jahr): array
{
    $out = [];
    for ($m = 1; $m <= 12; $m++) {
        $von = mktime(0, 0, 0, $m, 1, $jahr);
        $bis = mktime(0, 0, 0, $m + 1, 1, $jahr);
        $out[$m] = $bis > time() + 86400 && $von > time() ? null : verbrauch_zwischen($staende, $von, $bis);
    }
    return $out;
}

/**
 * Die Abschnitte zwischen zwei Ablesungen mit Tagesdurchschnitt – für die
 * Tabelle am Zähler. Reine Funktion; $staende älteste zuerst.
 */
function verbrauch_abschnitte(array $staende): array
{
    $out = [];
    $vor = null;
    foreach ($staende as $s) {
        if ($vor !== null) {
            $tage = (strtotime((string)$s['gelesen_am']) - strtotime((string)$vor['gelesen_am'])) / 86400;
            $menge = (float)$s['stand'] - (float)$vor['stand'];
            $out[] = [
                'von'     => (string)$vor['gelesen_am'],
                'bis'     => (string)$s['gelesen_am'],
                'tage'    => $tage,
                'menge'   => $menge < 0 ? null : $menge,   // null = Rücklauf / Wechsel
                'je_tag'  => $menge < 0 || $tage <= 0 ? null : $menge / $tage,
            ];
        }
        $vor = $s;
    }
    return $out;
}

/* ==================================================================== */
/* Tarife                                                                */
/* ==================================================================== */

function tarif_query(?string $art = null): array
{
    return db_all('SELECT * FROM tariffs' . ($art ? ' WHERE art = ?' : '')
        . ' ORDER BY art, gueltig_von DESC, id DESC', $art ? [meter_art($art)] : []);
}

function tarif_find(int $id): ?array
{
    return $id > 0 ? db_row('SELECT * FROM tariffs WHERE id = ?', [$id]) : null;
}

/** Tarif, der an einem Tag gilt – der jüngste passende. Reine Funktion. */
function tarif_am(array $tarife, string $art, string $datum): ?array
{
    $treffer = null;
    foreach ($tarife as $t) {
        if ((string)$t['art'] !== $art || (string)$t['gueltig_von'] > $datum) {
            continue;
        }
        $bis = (string)($t['gueltig_bis'] ?? '');
        if ($bis !== '' && $bis < $datum) {
            continue;
        }
        if ($treffer === null || (string)$t['gueltig_von'] > (string)$treffer['gueltig_von']) {
            $treffer = $t;
        }
    }
    return $treffer;
}

/** Tarif aus dem Formular speichern. Rückgabe: [id, fehler[]] */
function tarif_save_from_post(?array $existing): array
{
    $errors = [];
    $art = meter_art(post_str('art', (string)($existing['art'] ?? 'strom')));
    $name = post_str('name');
    if ($name === '') {
        $errors[] = 'Bitte einen Namen angeben, z. B. „Stadtwerke Grundversorgung 2026".';
    }
    $von = post_date('gueltig_von');
    if (!$von) {
        $errors[] = 'Bitte angeben, ab wann der Tarif gilt.';
    }
    $bis = post_date('gueltig_bis');
    if ($von && $bis && $bis < $von) {
        $errors[] = 'Das Ende liegt vor dem Anfang.';
    }
    $arbeitspreis = post_dec('arbeitspreis');
    if ($arbeitspreis < 0) {
        $errors[] = 'Der Arbeitspreis kann nicht negativ sein.';
    }
    if ($errors) {
        return [null, $errors];
    }
    $data = [
        'art'              => $art,
        'name'             => mb_substr($name, 0, 150),
        'anbieter'         => mb_substr(post_str('anbieter'), 0, 150),
        'gueltig_von'      => $von,
        'gueltig_bis'      => $bis,
        'arbeitspreis'     => round($arbeitspreis, 4),
        'grundpreis_monat' => round(max(0.0, post_dec('grundpreis_monat')), 2),
        'einheit'          => mb_substr(post_str('einheit') ?: ($art === 'gas' ? 'kWh' : METER_ARTEN[$art]['einheit']), 0, 10),
        'notiz'            => post_str('notiz'),
    ];
    if ($existing) {
        db_update('tariffs', $data, 'id = ?', [(int)$existing['id']]);
        $id = (int)$existing['id'];
        audit('tarif.bearbeitet', 'tariff', $id, $data['name']);
    } else {
        $id = db_insert('tariffs', $data);
        audit('tarif.angelegt', 'tariff', $id, $data['name']);
    }
    return [$id, []];
}

function tarif_delete(array $tarif): void
{
    db_exec('DELETE FROM tariffs WHERE id = ?', [(int)$tarif['id']]);
    audit('tarif.geloescht', 'tariff', (int)$tarif['id'], (string)$tarif['name']);
}

/**
 * Kosten eines Zeitraums: Arbeitspreis × Menge (nach Umrechnung, etwa
 * m³ → kWh bei Gas) + Grundpreis anteilig nach Tagen. Reine Funktion.
 * Rückgabe: ['arbeit' => €, 'grund' => €, 'gesamt' => €, 'tarif' => ?array]
 */
function verbrauch_kosten(?float $menge, float $umrechnung, ?array $tarif, float $tage): array
{
    if ($tarif === null || $menge === null) {
        return ['arbeit' => null, 'grund' => null, 'gesamt' => null, 'tarif' => $tarif];
    }
    $arbeit = $menge * ($umrechnung > 0 ? $umrechnung : 1.0) * (float)$tarif['arbeitspreis'];
    $grund = (float)$tarif['grundpreis_monat'] * max(0.0, $tage) / 30.4375;
    return ['arbeit' => round($arbeit, 2), 'grund' => round($grund, 2),
            'gesamt' => round($arbeit + $grund, 2), 'tarif' => $tarif];
}

/**
 * Kosten eines Jahres, Monat für Monat mit dem jeweils gültigen Tarif.
 * Reine Funktion. Rückgabe: ['monate' => [1..12 => €|null], 'gesamt' => €]
 */
function verbrauch_kosten_jahr(array $staende, array $tarife, array $meter, int $jahr): array
{
    $monate = verbrauch_monate($staende, $jahr);
    $out = ['monate' => [], 'gesamt' => 0.0, 'ohne_tarif' => 0];
    foreach ($monate as $m => $mengeMonat) {
        if ($mengeMonat === null) {
            $out['monate'][$m] = null;
            continue;
        }
        $tag = sprintf('%04d-%02d-15', $jahr, $m);
        $tarif = tarif_am($tarife, (string)$meter['art'], $tag);
        if ($tarif === null) {
            $out['monate'][$m] = null;
            $out['ohne_tarif']++;
            continue;
        }
        $tage = (int)date('t', mktime(0, 0, 0, $m, 1, $jahr));
        $k = verbrauch_kosten($mengeMonat, (float)$meter['umrechnung'], $tarif, (float)$tage);
        $out['monate'][$m] = $k['gesamt'];
        $out['gesamt'] += (float)$k['gesamt'];
    }
    $out['gesamt'] = round($out['gesamt'], 2);
    return $out;
}

/* ==================================================================== */
/* Kennzahlen                                                            */
/* ==================================================================== */

/**
 * Die Zahlen für Übersicht und MQTT: je Art Verbrauch im Jahr und in den
 * letzten 30 Tagen, Kosten im Jahr, vernachlässigte Zähler.
 */
function verbrauch_stats(array $meters, array $tarife, int $jahr, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $out = ['zaehler' => count($meters), 'alt' => 0, 'kosten_jahr' => 0.0, 'ohne_tarif' => 0, 'je_art' => []];
    foreach (METER_ARTEN as $key => $a) {
        $out['je_art'][$key] = ['jahr' => 0.0, 'tage30' => 0.0, 'einheit' => $a['einheit'], 'zaehler' => 0, 'kosten' => 0.0];
    }
    $jahresanfang = mktime(0, 0, 0, 1, 1, $jahr);
    $jahresende = min($jetzt, mktime(0, 0, 0, 1, 1, $jahr + 1));
    foreach ($meters as $m) {
        $art = (string)$m['art'];
        $staende = readings_alle((int)$m['id']);
        $out['je_art'][$art]['zaehler']++;
        $out['je_art'][$art]['einheit'] = (string)$m['einheit'];
        $out['je_art'][$art]['jahr'] += (float)(verbrauch_zwischen($staende, $jahresanfang, $jahresende) ?? 0);
        $out['je_art'][$art]['tage30'] += (float)(verbrauch_zwischen($staende, $jetzt - 30 * 86400, $jetzt) ?? 0);
        $k = verbrauch_kosten_jahr($staende, $tarife, $m, $jahr);
        $out['je_art'][$art]['kosten'] += $k['gesamt'];
        $out['kosten_jahr'] += $k['gesamt'];
        $out['ohne_tarif'] += $k['ohne_tarif'] > 0 ? 1 : 0;
        if (meter_stand_alter($m, date('Y-m-d H:i:s', $jetzt))['stufe'] !== 'frisch') {
            $out['alt']++;
        }
    }
    $out['kosten_jahr'] = round($out['kosten_jahr'], 2);
    return $out;
}

/* ==================================================================== */
/* Home Assistant als Quelle                                             */
/* ==================================================================== */

/**
 * Entitäten, die als Zähler taugen: Sensoren mit Energie-, Gas- oder
 * Wassereinheit oder passender device_class. Zehn Minuten zwischengespeichert.
 * Rückgabe: [entity_id => ['name' => …, 'einheit' => …, 'wert' => …]]
 */
function ha_zaehler_entitaeten(bool $frisch = false): array
{
    if (!$frisch) {
        $zwischen = json_decode((string)state_get('verbrauch_ha_entitaeten', ''), true);
        if (is_array($zwischen) && (int)state_get('verbrauch_ha_entitaeten_stand', '0') > time() - 600) {
            return $zwischen;
        }
    }
    $out = [];
    foreach (ha_api('states') as $s) {
        $id = (string)($s['entity_id'] ?? '');
        $attr = (array)($s['attributes'] ?? []);
        $einheit = (string)($attr['unit_of_measurement'] ?? '');
        $klasse = (string)($attr['device_class'] ?? '');
        if (!str_starts_with($id, 'sensor.')) {
            continue;
        }
        if (!in_array($klasse, ['energy', 'gas', 'water'], true)
            && !in_array($einheit, ['kWh', 'Wh', 'MWh', 'm³', 'm3', 'L', 'ft³'], true)) {
            continue;
        }
        $out[$id] = [
            'name'    => (string)($attr['friendly_name'] ?? $id),
            'einheit' => $einheit,
            'klasse'  => $klasse,
            'wert'    => (string)($s['state'] ?? ''),
        ];
    }
    ksort($out);
    state_save('verbrauch_ha_entitaeten', (string)json_encode($out, JSON_UNESCAPED_UNICODE));
    state_save('verbrauch_ha_entitaeten_stand', (string)time());
    return $out;
}

/**
 * Einen Zähler aus Home Assistant lesen und den Stand eintragen, wenn er
 * neu ist. Rückgabe: ['ok' => bool, 'text' => …, 'stand' => ?float]
 */
function meter_ha_lesen(array $meter, ?array $user = null): array
{
    if ((string)$meter['quelle'] !== 'ha' || trim((string)$meter['ha_entity']) === '') {
        return ['ok' => false, 'text' => 'Dieser Zähler wird nicht aus Home Assistant gelesen.', 'stand' => null];
    }
    try {
        $s = ha_api('states/' . rawurlencode((string)$meter['ha_entity']));
    } catch (Throwable $ex) {
        state_save('verbrauch_ha_fehler_' . (int)$meter['id'], mb_substr($ex->getMessage(), 0, 200));
        return ['ok' => false, 'text' => $ex->getMessage(), 'stand' => null];
    }
    $roh = (string)($s['state'] ?? '');
    if ($roh === '' || !is_numeric($roh)) {
        $text = 'Die Entität liefert keinen Zahlenwert (' . ($roh === '' ? 'leer' : $roh) . ').';
        state_save('verbrauch_ha_fehler_' . (int)$meter['id'], $text);
        return ['ok' => false, 'text' => $text, 'stand' => null];
    }
    $stand = round((float)$roh * (float)$meter['ha_faktor'], 3);
    state_save('verbrauch_ha_fehler_' . (int)$meter['id'], '');

    $letzter = db_row('SELECT stand, gelesen_am FROM meter_readings WHERE meter_id = ?
                       ORDER BY gelesen_am DESC, id DESC LIMIT 1', [(int)$meter['id']]);
    if ($letzter && abs((float)$letzter['stand'] - $stand) < 0.0005) {
        return ['ok' => true, 'text' => 'Unverändert: ' . menge($stand, (string)$meter['einheit'], 3), 'stand' => $stand];
    }
    [$id, $fehler] = reading_add($meter, $stand, date('Y-m-d H:i:s'), 'ha', 'Home Assistant', '', $user,
        (float)($letzter['stand'] ?? 0) > $stand);
    if ($fehler !== null) {
        return ['ok' => false, 'text' => $fehler, 'stand' => $stand];
    }
    return ['ok' => true, 'text' => 'Stand übernommen: ' . menge($stand, (string)$meter['einheit'], 3), 'stand' => $stand];
}

/** Für den Abruf: alle Zähler mit Quelle Home Assistant lesen, wenn fällig */
function verbrauch_ha_sync(): array
{
    $res = ['gelesen' => 0, 'neu' => 0, 'fehler' => 0];
    $minuten = max(5, setting_int('verbrauch_ha_intervall_minuten', 60));
    if (time() - (int)state_get('verbrauch_ha_letzter_abruf', '0') < $minuten * 60) {
        return $res;
    }
    state_save('verbrauch_ha_letzter_abruf', (string)time());
    foreach (db_all("SELECT * FROM meters WHERE quelle = 'ha' AND is_active = 1 AND ha_entity <> ''") as $m) {
        $r = meter_ha_lesen($m);
        $res['gelesen']++;
        if (!$r['ok']) {
            $res['fehler']++;
        } elseif (!str_starts_with($r['text'], 'Unverändert')) {
            $res['neu']++;
        }
    }
    return $res;
}

/* ==================================================================== */
/* QR-Code am Zähler (über den Connector)                                */
/* ==================================================================== */

/** Bezeichnung für den Anker der QR-Adresse – bleibt im Browser. Reine Funktion. */
function meter_qr_name(array $meter): string
{
    $teile = [(string)$meter['name']];
    if (trim((string)($meter['zaehlernummer'] ?? '')) !== '') {
        $teile[] = 'Nr. ' . $meter['zaehlernummer'];
    }
    return implode(' · ', $teile);
}

/** Meldung vom QR-Code eintragen. Rückgabe: Klartext fürs Protokoll oder Fehler. */
function meter_qr_anwenden(array $meter, array $daten, int $ts): ?string
{
    $stand = (float)($daten['stand'] ?? -1);
    $zeit = (int)($daten['zeit'] ?? 0);
    $wann = $zeit > 0 && abs($ts - $zeit) < 86400 ? $zeit : $ts;
    [$id, $fehler] = reading_add($meter, $stand, date('Y-m-d H:i:s', $wann), 'qr',
        (string)($daten['melder'] ?? ''), (string)($daten['notiz'] ?? ''));
    return $fehler;
}
