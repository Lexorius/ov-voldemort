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
/** Was ein Zähler misst: den Bezug (Hauptzähler), einen Teil davon, die Solarerzeugung, die Einspeisung */
const METER_ROLLEN = [
    'bezug'       => 'Hauptzähler – Bezug vom Versorger',
    'unter'       => 'Unterzähler – misst einen Teil eines Hauptzählers (Stockwerk, Halle …)',
    'erzeugung'   => 'Erzeugung – was die Solaranlage liefert (Wechselrichter)',
    'einspeisung' => 'Einspeisung – was ins Netz zurückgeht (Zählwerk 2.8.0)',
];
/** Tarife gibt es je Art, dazu die Einspeisevergütung */
const TARIF_ARTEN = [
    'strom'       => ['label' => 'Strom', 'einheit' => 'kWh', 'color' => '#b45309'],
    'gas'         => ['label' => 'Gas', 'einheit' => 'kWh', 'color' => '#0369a1'],
    'wasser'      => ['label' => 'Wasser', 'einheit' => 'm³', 'color' => '#0e7490'],
    'einspeisung' => ['label' => 'Einspeisevergütung', 'einheit' => 'kWh', 'color' => '#ca8a04'],
];
const READING_QUELLEN = ['manuell' => 'von Hand', 'ha' => 'Home Assistant', 'qr' => 'QR-Code'];
/** Tage ohne Stand, ab denen ein Zähler als vernachlässigt gilt */
const METER_WARN_TAGE = 35;

/** Nur bekannte Arten zulassen. Reine Funktion. */
function meter_art(string $art): string
{
    return array_key_exists($art, METER_ARTEN) ? $art : 'strom';
}

function meter_rolle(string $rolle): string
{
    return array_key_exists($rolle, METER_ROLLEN) ? $rolle : 'bezug';
}

function tarif_art(string $art): string
{
    return array_key_exists($art, TARIF_ARTEN) ? $art : 'strom';
}

/** Welcher Tarif für einen Zähler gilt – oder null, wenn er keine Kosten trägt. Reine Funktion. */
function meter_tarif_art(array $meter): ?string
{
    return match ((string)($meter['rolle'] ?? 'bezug')) {
        'unter', 'erzeugung' => null,
        'einspeisung'        => 'einspeisung',
        default              => meter_art((string)($meter['art'] ?? 'strom')),
    };
}

/** Zählt der Zähler zum Verbrauch der Art? Unterzähler und Solar nicht – sonst wäre es doppelt. Reine Funktion. */
function meter_zaehlt(array $meter): bool
{
    return (string)($meter['rolle'] ?? 'bezug') === 'bezug';
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
    return 'SELECT m.*, c.name AS connector_name, p.name AS parent_name,
                   (SELECT COUNT(*) FROM meters u WHERE u.parent_id = m.id AND u.is_active = 1) AS unterzaehler,
                   (SELECT r.stand FROM meter_readings r WHERE r.meter_id = m.id
                     ORDER BY r.gelesen_am DESC, r.id DESC LIMIT 1) AS letzter_stand,
                   (SELECT r.gelesen_am FROM meter_readings r WHERE r.meter_id = m.id
                     ORDER BY r.gelesen_am DESC, r.id DESC LIMIT 1) AS letzte_ablesung,
                   (SELECT r.quelle FROM meter_readings r WHERE r.meter_id = m.id
                     ORDER BY r.gelesen_am DESC, r.id DESC LIMIT 1) AS letzte_quelle,
                   (SELECT COUNT(*) FROM meter_readings r WHERE r.meter_id = m.id) AS ablesungen
            FROM meters m
            LEFT JOIN connectors c ON c.id = m.qr_connector_id
            LEFT JOIN meters p ON p.id = m.parent_id';
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
        . ' ORDER BY m.is_active DESC, m.art, COALESCE(m.parent_id, m.id), m.parent_id IS NOT NULL, m.name', $p);
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
    $rolle = meter_rolle(post_str('rolle', (string)($existing['rolle'] ?? 'bezug')));
    if (in_array($rolle, ['erzeugung', 'einspeisung'], true)) {
        $art = 'strom';   // Solar gibt es nur bei Strom
    }
    $parentId = null;
    if ($rolle === 'unter') {
        $parent = meter_find(post_int('parent_id', 0) ?? 0);
        if (!$parent) {
            $errors[] = 'Ein Unterzähler braucht einen Hauptzähler, dessen Teil er misst.';
        } elseif ($existing && (int)$parent['id'] === (int)$existing['id']) {
            $errors[] = 'Ein Zähler kann nicht sein eigener Hauptzähler sein.';
        } elseif ((string)$parent['rolle'] !== 'bezug') {
            $errors[] = 'Der Hauptzähler muss selbst ein Hauptzähler sein – kein Unterzähler, keine Solarzählung.';
        } elseif ((string)$parent['art'] !== $art) {
            $errors[] = 'Haupt- und Unterzähler müssen dieselbe Art haben.';
        } else {
            $parentId = (int)$parent['id'];
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
        'rolle'         => $rolle,
        'parent_id'     => $parentId,
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
    verbrauch_geaendert();
    return [$id, []];
}

function meter_delete(array $meter): void
{
    db_exec('DELETE FROM meters WHERE id = ?', [(int)$meter['id']]);
    audit('zaehler.geloescht', 'meter', (int)$meter['id'], (string)$meter['name']);
    verbrauch_geaendert();
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

/** Unterzähler eines Hauptzählers mit ihrem Anteil im Jahr */
function meter_unterzaehler_mit_anteil(array $parent, int $jahr): array
{
    $kinder = db_all(meter_select() . ' WHERE m.parent_id = ? ORDER BY m.is_active DESC, m.name', [(int)$parent['id']]);
    if (!$kinder) {
        return [];
    }
    $jetzt = time();
    $anfang = mktime(0, 0, 0, 1, 1, $jahr);
    $ende = min($jetzt, mktime(0, 0, 0, 1, 1, $jahr + 1));
    [$von, $bis] = verbrauch_zeitraum($jahr, $jetzt);
    $mengen = [(int)$parent['id'] => (float)(verbrauch_zwischen(readings_bereich((int)$parent['id'], $von, $bis), $anfang, $ende) ?? 0)];
    foreach ($kinder as $k) {
        $mengen[(int)$k['id']] = (float)(verbrauch_zwischen(readings_bereich((int)$k['id'], $von, $bis), $anfang, $ende) ?? 0);
    }
    $anteile = verbrauch_anteile($kinder, $mengen);
    $rest = $mengen[(int)$parent['id']];
    foreach ($kinder as &$k) {
        $k['jahr'] = $mengen[(int)$k['id']];
        $k['anteil'] = $anteile[(int)$k['id']] ?? null;
        $rest -= $k['jahr'];
    }
    unset($k);
    return ['liste' => $kinder, 'hauptzaehler' => $mengen[(int)$parent['id']], 'rest' => max(0.0, $rest)];
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
 * Nur die Stände, die ein Zeitraum braucht: der letzte davor, alle darin,
 * der erste danach. So bleibt die Rechnung gleich schnell, ob ein Zähler
 * ein Jahr oder zehn Jahre Geschichte hat. Älteste zuerst.
 */
function readings_bereich(int $meterId, int $von, int $bis): array
{
    $a = date('Y-m-d H:i:s', $von);
    $b = date('Y-m-d H:i:s', $bis);
    $rows = db_all(
        '(SELECT stand, gelesen_am, quelle FROM meter_readings WHERE meter_id = ? AND gelesen_am < ?
          ORDER BY gelesen_am DESC, id DESC LIMIT 1)
         UNION ALL
         (SELECT stand, gelesen_am, quelle FROM meter_readings WHERE meter_id = ? AND gelesen_am >= ? AND gelesen_am <= ?
          ORDER BY gelesen_am ASC, id ASC)
         UNION ALL
         (SELECT stand, gelesen_am, quelle FROM meter_readings WHERE meter_id = ? AND gelesen_am > ?
          ORDER BY gelesen_am ASC, id ASC LIMIT 1)',
        [$meterId, $a, $meterId, $a, $b, $meterId, $b]
    );
    usort($rows, static fn($x, $y) => strcmp((string)$x['gelesen_am'], (string)$y['gelesen_am']));
    return $rows;
}

/** Zeitraum, den Übersicht und Kennzahlen eines Jahres brauchen: [von, bis] */
function verbrauch_zeitraum(int $jahr, ?int $jetzt = null): array
{
    $jetzt ??= time();
    return [min(mktime(0, 0, 0, 1, 1, $jahr), $jetzt - 31 * 86400), max($jetzt, mktime(0, 0, 0, 1, 1, $jahr + 1))];
}

/** Jahre, zu denen es Stände gibt – aus dem ersten und letzten, nicht aus einem Volltext über die Tabelle */
function verbrauch_jahre(?int $meterId = null): array
{
    $r = db_row('SELECT MIN(gelesen_am) AS a, MAX(gelesen_am) AS b FROM meter_readings'
        . ($meterId !== null ? ' WHERE meter_id = ?' : ''), $meterId !== null ? [$meterId] : []);
    $jahre = [];
    if ($r && $r['a'] !== null && $r['b'] !== null) {
        for ($j = (int)substr((string)$r['b'], 0, 4); $j >= (int)substr((string)$r['a'], 0, 4); $j--) {
            $jahre[] = $j;
        }
    }
    return $jahre;
}

/**
 * Jede Änderung an Ständen, Zählern oder Tarifen erhöht diese Marke.
 * Kennzahlen im Hintergrund rechnen nur neu, wenn sie sich bewegt hat.
 */
function verbrauch_geaendert(): void
{
    state_save('verbrauch_marke', (string)((int)state_get('verbrauch_marke', '0') + 1));
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
    verbrauch_geaendert();
    return [$id, null];
}

function reading_delete(array $meter, int $readingId): bool
{
    $n = db_exec('DELETE FROM meter_readings WHERE id = ? AND meter_id = ?', [$readingId, (int)$meter['id']]);
    if ($n > 0) {
        audit('zaehler.stand.geloescht', 'meter', (int)$meter['id'], (string)$readingId);
        verbrauch_geaendert();
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
            return null;   // der Zeitraum liegt ganz vor der ersten Ablesung
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
 * Je Kalendertag der letzte Stand – aus stündlichen Ständen (Home
 * Assistant) wird so ein Tageswert. Reine Funktion; $staende älteste zuerst.
 */
function verbrauch_tagesstaende(array $staende): array
{
    $tage = [];
    foreach ($staende as $s) {
        $tage[substr((string)$s['gelesen_am'], 0, 10)] = $s;
    }
    return array_values($tage);
}

/**
 * Die Abschnitte zwischen zwei Ablesetagen mit Tagesdurchschnitt – für die
 * Tabelle am Zähler. Mehrere Stände an einem Tag zählen als einer, sonst
 * stünden dort Zeilen mit null Tagen. Reine Funktion; $staende älteste zuerst.
 */
function verbrauch_abschnitte(array $staende): array
{
    $out = [];
    $vor = null;
    foreach (verbrauch_tagesstaende($staende) as $s) {
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
        . ' ORDER BY art, gueltig_von DESC, id DESC', $art ? [tarif_art($art)] : []);
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
    $art = tarif_art(post_str('art', (string)($existing['art'] ?? 'strom')));
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
        'einheit'          => mb_substr(post_str('einheit') ?: TARIF_ARTEN[$art]['einheit'], 0, 10),
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
    verbrauch_geaendert();
    return [$id, []];
}

function tarif_delete(array $tarif): void
{
    db_exec('DELETE FROM tariffs WHERE id = ?', [(int)$tarif['id']]);
    audit('tarif.geloescht', 'tariff', (int)$tarif['id'], (string)$tarif['name']);
    verbrauch_geaendert();
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
    $tarifArt = meter_tarif_art($meter);
    $out = ['monate' => [], 'gesamt' => 0.0, 'ohne_tarif' => 0, 'erloes' => $tarifArt === 'einspeisung'];
    foreach ($monate as $m => $mengeMonat) {
        if ($mengeMonat === null || $tarifArt === null) {
            $out['monate'][$m] = null;
            continue;
        }
        $tag = sprintf('%04d-%02d-15', $jahr, $m);
        $tarif = tarif_am($tarife, $tarifArt, $tag);
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
    $out = ['zaehler' => count($meters), 'alt' => 0, 'kosten_jahr' => 0.0, 'erloes_jahr' => 0.0, 'ohne_tarif' => 0, 'je_art' => [],
            'solar' => ['vorhanden' => false, 'erzeugung' => 0.0, 'einspeisung' => 0.0, 'eigenverbrauch' => 0.0,
                        'bezug' => 0.0, 'gesamt' => 0.0, 'autarkie' => 0, 'erloes' => 0.0]];
    foreach (METER_ARTEN as $key => $a) {
        $out['je_art'][$key] = ['jahr' => 0.0, 'tage30' => 0.0, 'einheit' => $a['einheit'], 'zaehler' => 0, 'kosten' => 0.0];
    }
    $jahresanfang = mktime(0, 0, 0, 1, 1, $jahr);
    $jahresende = min($jetzt, mktime(0, 0, 0, 1, 1, $jahr + 1));
    [$von, $bis] = verbrauch_zeitraum($jahr, $jetzt);
    foreach ($meters as $m) {
        $art = (string)$m['art'];
        $rolle = (string)($m['rolle'] ?? 'bezug');
        $staende = readings_bereich((int)$m['id'], $von, $bis);
        $jahrMenge = (float)(verbrauch_zwischen($staende, $jahresanfang, $jahresende) ?? 0);
        if (meter_stand_alter($m, date('Y-m-d H:i:s', $jetzt))['stufe'] !== 'frisch') {
            $out['alt']++;
        }
        if ($rolle === 'erzeugung' || $rolle === 'einspeisung') {
            $out['solar']['vorhanden'] = true;
            $out['solar'][$rolle] += $jahrMenge;
            if ($rolle === 'einspeisung') {
                $out['solar']['erloes'] += verbrauch_kosten_jahr($staende, $tarife, $m, $jahr)['gesamt'];
            }
            continue;
        }
        if ($rolle === 'unter') {
            continue;   // steckt schon im Hauptzähler
        }
        $out['je_art'][$art]['zaehler']++;
        $out['je_art'][$art]['einheit'] = (string)$m['einheit'];
        $out['je_art'][$art]['jahr'] += $jahrMenge;
        $out['je_art'][$art]['tage30'] += (float)(verbrauch_zwischen($staende, $jetzt - 30 * 86400, $jetzt) ?? 0);
        $k = verbrauch_kosten_jahr($staende, $tarife, $m, $jahr);
        $out['je_art'][$art]['kosten'] += $k['gesamt'];
        $out['kosten_jahr'] += $k['gesamt'];
        $out['ohne_tarif'] += $k['ohne_tarif'] > 0 ? 1 : 0;
    }
    $out['kosten_jahr'] = round($out['kosten_jahr'], 2);
    $out['erloes_jahr'] = round($out['solar']['erloes'], 2);
    $out['solar'] = verbrauch_solar_bilanz($out['solar'], $out['je_art']['strom']['jahr']);
    return $out;
}

/**
 * Solarbilanz: Eigenverbrauch = Erzeugung − Einspeisung, Gesamtverbrauch =
 * Bezug + Eigenverbrauch, Autarkie = Eigenverbrauch / Gesamtverbrauch.
 * Reine Funktion.
 */
function verbrauch_solar_bilanz(array $solar, float $bezug): array
{
    $solar['bezug'] = $bezug;
    $solar['eigenverbrauch'] = max(0.0, (float)$solar['erzeugung'] - (float)$solar['einspeisung']);
    $solar['gesamt'] = $bezug + $solar['eigenverbrauch'];
    $solar['autarkie'] = $solar['gesamt'] > 0 ? (int)round($solar['eigenverbrauch'] / $solar['gesamt'] * 100) : 0;
    $solar['erloes'] = round((float)$solar['erloes'], 2);
    return $solar;
}

/**
 * Anteile der Unterzähler an ihrem Hauptzähler: [id => Prozent|null].
 * $mengen: [id => Jahresmenge]. Reine Funktion.
 */
function verbrauch_anteile(array $meters, array $mengen): array
{
    $out = [];
    foreach ($meters as $m) {
        $parent = (int)($m['parent_id'] ?? 0);
        if ((string)($m['rolle'] ?? 'bezug') !== 'unter' || $parent === 0) {
            continue;
        }
        $basis = (float)($mengen[$parent] ?? 0);
        $out[(int)$m['id']] = $basis > 0 ? round((float)($mengen[(int)$m['id']] ?? 0) / $basis * 100, 1) : null;
    }
    return $out;
}

/**
 * Kennzahlen für den Hintergrund (MQTT): neu gerechnet nur, wenn sich seit
 * dem letzten Mal ein Stand, ein Zähler oder ein Tarif geändert hat – oder
 * der Tag gewechselt hat, weil „letzte 30 Tage" und „ohne Stand" wandern.
 */
function verbrauch_stats_cached(int $jahr): array
{
    $marke = state_get('verbrauch_marke', '0') . '|' . $jahr . '|' . date('Y-m-d');
    if (state_get('verbrauch_stats_marke', '') === $marke) {
        $alt = json_decode(state_get('verbrauch_stats_wert', ''), true);
        if (is_array($alt) && isset($alt['je_art'])) {
            return $alt;
        }
    }
    $stats = verbrauch_stats(meter_query([]), tarif_query(), $jahr);
    state_save('verbrauch_stats_wert', (string)json_encode($stats, JSON_PRESERVE_ZERO_FRACTION));
    state_save('verbrauch_stats_marke', $marke);
    return $stats;
}

/* ==================================================================== */
/* Profil: wann wird verbraucht?                                         */
/* ==================================================================== */

/**
 * Verbrauch je Wochentag und je Tagesstunde im Durchschnitt, aus der Kette
 * der Stände zwischen $von und $bis. Jeder Abschnitt zwischen zwei
 * Ablesungen wird gleichmäßig auf seine Stunden verteilt – bei stündlichen
 * Ständen entsteht so eine echte Tageskurve, bei täglichen ein
 * Wochenprofil, bei seltenen Ablesungen nur ein Mittelwert. Was davon
 * aussagekräftig ist, sagt das Ergebnis mit. Reine Funktion.
 */
function verbrauch_profil(array $staende, int $von, int $bis): array
{
    $wochentage = array_fill(0, 7, 0.0);      // Mo = 0 … So = 6
    $wtSekunden = array_fill(0, 7, 0.0);
    $stunden = array_fill(0, 24, 0.0);
    $stSekunden = array_fill(0, 24, 0.0);
    $woche = [];                               // "tag-stunde" => Menge
    $wocheSekunden = [];
    $abschnitte = 0;
    $abstaende = [];
    $gesamtSekunden = 0.0;

    $vor = null;
    foreach ($staende as $s) {
        $t = strtotime((string)$s['gelesen_am']);
        if ($vor !== null) {
            $t1 = max($vor['t'], $von);
            $t2 = min($t, $bis);
            $menge = (float)$s['stand'] - (float)$vor['stand'];
            if ($t2 > $t1 && $t > $vor['t'] && $menge >= 0) {
                $rate = $menge / ($t - $vor['t']);    // Menge je Sekunde, gleichmäßig
                $abschnitte++;
                $abstaende[] = (float)(($t - $vor['t']) / 3600);
                // Stunde für Stunde durch den Abschnitt gehen (höchstens ein Jahr je Abschnitt)
                $cursor = $t1;
                $schritte = 0;
                while ($cursor < $t2 && $schritte < 9000) {
                    $stundenEnde = min($t2, (intdiv($cursor, 3600) + 1) * 3600);
                    $dauer = $stundenEnde - $cursor;
                    $h = (int)date('G', $cursor);
                    $wt = ((int)date('N', $cursor)) - 1;
                    $anteil = $rate * $dauer;
                    $stunden[$h] += $anteil;
                    $stSekunden[$h] += $dauer;
                    $wochentage[$wt] += $anteil;
                    $wtSekunden[$wt] += $dauer;
                    $key = $wt . '-' . $h;
                    $woche[$key] = ($woche[$key] ?? 0.0) + $anteil;
                    $wocheSekunden[$key] = ($wocheSekunden[$key] ?? 0.0) + $dauer;
                    $gesamtSekunden += $dauer;
                    $cursor = $stundenEnde;
                    $schritte++;
                }
            }
        }
        $vor = ['t' => $t, 'stand' => $s['stand']];
    }
    if ($abschnitte === 0) {
        return ['abschnitte' => 0, 'tage' => 0.0, 'abstand_stunden' => 0.0, 'wochentage' => $wochentage, 'stunden' => $stunden,
                'wochentage_aussagekraeftig' => false, 'stunden_aussagekraeftig' => false, 'spitze_tag' => 0, 'schwach_tag' => 0,
                'spitzen_stunden' => [], 'spitzen_woche' => [], 'nacht_anteil' => 0];
    }
    // Durchschnitt: je Wochentag pro Tag, je Stunde pro Stunde
    foreach ($wochentage as $i => $v) {
        $wochentage[$i] = $wtSekunden[$i] > 0 ? $v / ($wtSekunden[$i] / 86400) : 0.0;
    }
    foreach ($stunden as $i => $v) {
        $stunden[$i] = $stSekunden[$i] > 0 ? $v / ($stSekunden[$i] / 3600) : 0.0;
    }
    $wocheMittel = [];
    foreach ($woche as $key => $v) {
        [$wt, $h] = array_map('intval', explode('-', $key));
        $wocheMittel[] = ['tag' => $wt, 'stunde' => $h, 'wert' => $wocheSekunden[$key] > 0 ? $v / ($wocheSekunden[$key] / 3600) : 0.0];
    }
    usort($wocheMittel, static fn($a, $b) => $b['wert'] <=> $a['wert']);
    sort($abstaende);
    $median = (float)$abstaende[intdiv(count($abstaende), 2)];

    $stundenSortiert = [];
    foreach ($stunden as $i => $v) {
        $stundenSortiert[] = ['stunde' => $i, 'wert' => $v];
    }
    usort($stundenSortiert, static fn($a, $b) => $b['wert'] <=> $a['wert']);
    $tagesSumme = array_sum($stunden);
    $nacht = 0.0;
    foreach ([22, 23, 0, 1, 2, 3, 4, 5] as $h) {
        $nacht += $stunden[$h];
    }
    $spitzeTag = (int)array_search(max($wochentage), $wochentage, true);
    $schwachTag = (int)array_search(min($wochentage), $wochentage, true);

    return [
        'abschnitte'      => $abschnitte,
        'tage'            => $gesamtSekunden / 86400,
        'abstand_stunden' => $median,
        'wochentage'      => $wochentage,
        'stunden'         => $stunden,
        'wochentage_aussagekraeftig' => $median <= 26,
        'stunden_aussagekraeftig'    => $median <= 2,
        'spitze_tag'      => $spitzeTag,
        'schwach_tag'     => $schwachTag,
        'spitzen_stunden' => array_slice($stundenSortiert, 0, 3),
        'spitzen_woche'   => array_slice($wocheMittel, 0, 3),
        'nacht_anteil'    => $tagesSumme > 0 ? (int)round($nacht / $tagesSumme * 100) : 0,
    ];
}

/* ==================================================================== */
/* Berichte: je Zähler und für das ganze Jahr                            */
/* ==================================================================== */

/** Ein Jahr mit Vorjahr aus einer Kette von Ständen. Reine Funktion. */
function verbrauch_jahr_mit_vorjahr(array $staende, int $jahr, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $anfang = mktime(0, 0, 0, 1, 1, $jahr);
    $ende = mktime(0, 0, 0, 1, 1, $jahr + 1);
    $bisHeute = $jetzt < $ende;
    $stichtag = $bisHeute ? $jetzt : $ende;
    // Im laufenden Jahr wird das Vorjahr nur bis zum selben Tag gezählt
    $vorjahrEnde = $bisHeute ? mktime((int)date('G', $jetzt), (int)date('i', $jetzt), 0, (int)date('n', $jetzt), (int)date('j', $jetzt), $jahr - 1) : $anfang;
    $summe = verbrauch_zwischen($staende, $anfang, $stichtag);
    $vorjahr = verbrauch_zwischen($staende, mktime(0, 0, 0, 1, 1, $jahr - 1), $vorjahrEnde);
    $monate = verbrauch_monate($staende, $jahr);
    $vorMonate = verbrauch_monate($staende, $jahr - 1);
    $tage = max(1, (int)floor(($stichtag - $anfang) / 86400));
    $spitze = null;
    $namen = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    foreach ($monate as $m => $v) {
        if ($v !== null && $v > 0 && ($spitze === null || $v > $spitze['wert'])) {
            $spitze = ['monat' => $m, 'name' => $namen[$m], 'wert' => $v];
        }
    }
    $s = (float)($summe ?? 0);
    return [
        'summe'          => $s,
        'summe_vorjahr'  => $vorjahr,
        'delta_prozent'  => $vorjahr !== null && $vorjahr > 0 ? round(($s - $vorjahr) / $vorjahr * 100, 1) : null,
        'monate'         => $monate,
        'monate_vorjahr' => $vorMonate,
        'bis_heute'      => $bisHeute,
        'tage'           => $tage,
        'je_tag'         => $s / $tage,
        'spitze'         => $spitze,
    ];
}

/** Bericht zu einem Zähler: Jahr, Vorjahr, Monate, Kosten, Kennzahlen */
function verbrauch_zaehlerbericht(array $meter, array $tarife, int $jahr, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $staende = readings_bereich((int)$meter['id'], mktime(0, 0, 0, 1, 1, $jahr - 1), max($jetzt, mktime(0, 0, 0, 1, 1, $jahr + 1)));
    $b = verbrauch_jahr_mit_vorjahr($staende, $jahr, $jetzt);
    $b['kosten'] = verbrauch_kosten_jahr($staende, $tarife, $meter, $jahr);
    $b['kosten_vorjahr'] = verbrauch_kosten_jahr($staende, $tarife, $meter, $jahr - 1)['gesamt'];
    $tarifArt = meter_tarif_art($meter);
    $b['tarif'] = $tarifArt ? tarif_am($tarife, $tarifArt, sprintf('%04d-06-15', $jahr)) : null;
    $b['ablesungen'] = count(array_filter($staende, static fn($s) => substr((string)$s['gelesen_am'], 0, 4) === (string)$jahr));
    $letzter = $staende ? end($staende) : null;
    $b['letzter_stand'] = $letzter ? (float)$letzter['stand'] : null;
    $b['profil'] = verbrauch_profil($staende, mktime(0, 0, 0, 1, 1, $jahr), min($jetzt, mktime(0, 0, 0, 1, 1, $jahr + 1)));
    return $b;
}

/** Jahresbericht über alle Zähler: je Art zusammengefasst, dazu jeder Zähler */
function verbrauch_jahresbericht(array $meters, array $tarife, int $jahr, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $out = ['zaehler' => [], 'je_art' => [], 'kosten' => 0.0, 'kosten_vorjahr' => 0.0, 'erloes' => 0.0, 'ohne_tarif' => 0,
            'bis_heute' => $jetzt < mktime(0, 0, 0, 1, 1, $jahr + 1),
            'solar' => ['vorhanden' => false, 'erzeugung' => 0.0, 'einspeisung' => 0.0, 'erzeugung_vorjahr' => null, 'einspeisung_vorjahr' => null, 'erloes' => 0.0]];
    foreach (METER_ARTEN as $key => $a) {
        $out['je_art'][$key] = ['zaehler' => 0, 'einheit' => $a['einheit'], 'summe' => 0.0, 'summe_vorjahr' => null,
            'delta_prozent' => null, 'kosten' => 0.0, 'monate' => array_fill(1, 12, null), 'monate_vorjahr' => array_fill(1, 12, null)];
    }
    $mengen = [];
    foreach ($meters as $m) {
        $b = verbrauch_zaehlerbericht($m, $tarife, $jahr, $jetzt);
        $art = (string)$m['art'];
        $rolle = (string)($m['rolle'] ?? 'bezug');
        $mengen[(int)$m['id']] = $b['summe'];
        $out['zaehler'][] = $m + ['summe' => $b['summe'], 'summe_vorjahr' => $b['summe_vorjahr'], 'delta_prozent' => $b['delta_prozent'],
            'kosten' => $b['kosten']['gesamt'], 'erloes' => $b['kosten']['erloes'], 'ohne_tarif' => $b['kosten']['ohne_tarif'],
            'ablesungen' => $b['ablesungen'], 'anteil' => null];
        if ($rolle === 'erzeugung' || $rolle === 'einspeisung') {
            $out['solar']['vorhanden'] = true;
            $out['solar'][$rolle] += $b['summe'];
            if ($b['summe_vorjahr'] !== null) {
                $out['solar'][$rolle . '_vorjahr'] = (float)($out['solar'][$rolle . '_vorjahr'] ?? 0) + $b['summe_vorjahr'];
            }
            if ($rolle === 'einspeisung') {
                $out['solar']['erloes'] += $b['kosten']['gesamt'];
                $out['erloes'] += $b['kosten']['gesamt'];
            }
            continue;
        }
        if ($rolle === 'unter') {
            continue;
        }
        $s = &$out['je_art'][$art];
        $s['zaehler']++;
        $s['einheit'] = (string)$m['einheit'];
        $s['summe'] += $b['summe'];
        if ($b['summe_vorjahr'] !== null) {
            $s['summe_vorjahr'] = (float)($s['summe_vorjahr'] ?? 0) + $b['summe_vorjahr'];
        }
        $s['kosten'] += $b['kosten']['gesamt'];
        for ($i = 1; $i <= 12; $i++) {
            if ($b['monate'][$i] !== null) {
                $s['monate'][$i] = (float)($s['monate'][$i] ?? 0) + $b['monate'][$i];
            }
            if ($b['monate_vorjahr'][$i] !== null) {
                $s['monate_vorjahr'][$i] = (float)($s['monate_vorjahr'][$i] ?? 0) + $b['monate_vorjahr'][$i];
            }
        }
        unset($s);
        $out['kosten'] += $b['kosten']['gesamt'];
        $out['kosten_vorjahr'] += (float)$b['kosten_vorjahr'];
        $out['ohne_tarif'] += $b['kosten']['ohne_tarif'] > 0 ? 1 : 0;
    }
    $anteile = verbrauch_anteile($meters, $mengen);
    foreach ($out['zaehler'] as &$z) {
        $z['anteil'] = $anteile[(int)$z['id']] ?? null;
    }
    unset($z);
    $out['solar'] = verbrauch_solar_bilanz($out['solar'], $out['je_art']['strom']['summe']);
    $out['erloes'] = round($out['erloes'], 2);
    foreach ($out['je_art'] as &$s) {
        $s['delta_prozent'] = $s['summe_vorjahr'] !== null && $s['summe_vorjahr'] > 0
            ? round(($s['summe'] - $s['summe_vorjahr']) / $s['summe_vorjahr'] * 100, 1) : null;
        $s['kosten'] = round($s['kosten'], 2);
    }
    unset($s);
    $out['kosten'] = round($out['kosten'], 2);
    $out['kosten_vorjahr'] = $out['kosten_vorjahr'] > 0 ? round($out['kosten_vorjahr'], 2) : null;
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
