<?php
declare(strict_types=1);

/*
 * Kalender: eigene Termine (für mich, meine Funktion, meine Fachgruppe oder
 * den ganzen OV), dazu alles, was in den Modulen schon ein Datum hat –
 * Besprechungen, Veranstaltungen, Fristen, Aufgaben, Stichtage –, die
 * gesetzlichen Feiertage des Bundeslands und Kalender aus Home Assistant.
 *
 * Fremde Quellen werden nicht kopiert, sondern beim Anzeigen eingesammelt;
 * nur die Kalender aus Home Assistant liegen als Zwischenspeicher in der
 * Datenbank, damit die Seite ohne Home Assistant schnell bleibt.
 */

/** Für wen ein eigener Termin gilt */
const KALENDER_ZIELE = [
    'ov'         => 'Ortsverband (alle)',
    'fachgruppe' => 'Fachgruppe',
    'funktion'   => 'Funktion',
    'user'       => 'nur für mich',
];

/** Quellen im Kalender mit Farbe und Beschriftung – die Reihenfolge ist die der Legende */
const KALENDER_QUELLEN = [
    'termin'        => ['label' => 'Termine', 'color' => '#0369a1'],
    'besprechung'   => ['label' => 'Besprechungen', 'color' => '#7c3aed'],
    'veranstaltung' => ['label' => 'Veranstaltungen', 'color' => '#c2410c'],
    'aufgabe'       => ['label' => 'Aufgaben fällig', 'color' => '#0e7490'],
    'frist'         => ['label' => 'Fristen (HU, SP, UVV, Prüfung, Vertrag)', 'color' => '#b91c1c'],
    'budget'        => ['label' => 'Budget-Stichtag', 'color' => '#15803d'],
    'ha'            => ['label' => 'Home Assistant', 'color' => '#ca8a04'],
    'feiertag'      => ['label' => 'Feiertage', 'color' => '#64748b'],
];

const KALENDER_BUNDESLAENDER = [
    'BW' => 'Baden-Württemberg', 'BY' => 'Bayern', 'BE' => 'Berlin', 'BB' => 'Brandenburg', 'HB' => 'Bremen',
    'HH' => 'Hamburg', 'HE' => 'Hessen', 'MV' => 'Mecklenburg-Vorpommern', 'NI' => 'Niedersachsen',
    'NW' => 'Nordrhein-Westfalen', 'RP' => 'Rheinland-Pfalz', 'SL' => 'Saarland', 'SN' => 'Sachsen',
    'ST' => 'Sachsen-Anhalt', 'SH' => 'Schleswig-Holstein', 'TH' => 'Thüringen', '' => 'nur bundesweite',
];

/* ==================================================================== */
/* Feiertage – gerechnet, nicht geladen                                  */
/* ==================================================================== */

/** Ostersonntag nach Gauß. Reine Funktion. */
function kalender_ostersonntag(int $jahr): int
{
    $a = $jahr % 19;
    $b = intdiv($jahr, 100);
    $c = $jahr % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $monat = intdiv($h + $l - 7 * $m + 114, 31);
    $tag = (($h + $l - 7 * $m + 114) % 31) + 1;
    return mktime(0, 0, 0, $monat, $tag, $jahr);
}

/** Gesetzliche Feiertage eines Jahres: [Datum => Name]. Reine Funktion. */
function kalender_feiertage(int $jahr, string $land = ''): array
{
    $land = strtoupper($land);
    $ostern = kalender_ostersonntag($jahr);
    $tag = static fn(int $ts, int $plus = 0): string => date('Y-m-d', $ts + $plus * 86400);
    $f = [
        $tag(mktime(0, 0, 0, 1, 1, $jahr))   => 'Neujahr',
        $tag($ostern, -2)                     => 'Karfreitag',
        $tag($ostern, 1)                      => 'Ostermontag',
        $tag(mktime(0, 0, 0, 5, 1, $jahr))   => 'Tag der Arbeit',
        $tag($ostern, 39)                     => 'Christi Himmelfahrt',
        $tag($ostern, 50)                     => 'Pfingstmontag',
        $tag(mktime(0, 0, 0, 10, 3, $jahr))  => 'Tag der Deutschen Einheit',
        $tag(mktime(0, 0, 0, 12, 25, $jahr)) => '1. Weihnachtstag',
        $tag(mktime(0, 0, 0, 12, 26, $jahr)) => '2. Weihnachtstag',
    ];
    if (in_array($land, ['BW', 'BY', 'ST'], true)) {
        $f[$tag(mktime(0, 0, 0, 1, 6, $jahr))] = 'Heilige Drei Könige';
    }
    if (in_array($land, ['BE', 'MV'], true)) {
        $f[$tag(mktime(0, 0, 0, 3, 8, $jahr))] = 'Internationaler Frauentag';
    }
    if (in_array($land, ['BW', 'BY', 'HE', 'NW', 'RP', 'SL'], true)) {
        $f[$tag($ostern, 60)] = 'Fronleichnam';
    }
    if ($land === 'SL') {
        $f[$tag(mktime(0, 0, 0, 8, 15, $jahr))] = 'Mariä Himmelfahrt';
    }
    if ($land === 'TH') {
        $f[$tag(mktime(0, 0, 0, 9, 20, $jahr))] = 'Weltkindertag';
    }
    if (in_array($land, ['BB', 'HB', 'HH', 'MV', 'NI', 'SN', 'ST', 'SH', 'TH'], true)) {
        $f[$tag(mktime(0, 0, 0, 10, 31, $jahr))] = 'Reformationstag';
    }
    if (in_array($land, ['BW', 'BY', 'NW', 'RP', 'SL'], true)) {
        $f[$tag(mktime(0, 0, 0, 11, 1, $jahr))] = 'Allerheiligen';
    }
    if ($land === 'SN') {
        // Mittwoch vor dem 23. November
        $t = mktime(0, 0, 0, 11, 22, $jahr);
        while ((int)date('N', $t) !== 3) {
            $t -= 86400;
        }
        $f[$tag($t)] = 'Buß- und Bettag';
    }
    ksort($f);
    return $f;
}

/* ==================================================================== */
/* Eigene Termine                                                        */
/* ==================================================================== */

function kalender_ziel(string $ziel): string
{
    return array_key_exists($ziel, KALENDER_ZIELE) ? $ziel : 'user';
}

/** Darf diese Person den Termin sehen? Leitung sieht alles. Reine Funktion. */
function kalender_sichtbar(array $t, array $u): bool
{
    if (in_array((string)($u['role'] ?? ''), ['admin', 'leitung'], true) || (int)($t['created_by'] ?? 0) === (int)$u['id']) {
        return true;
    }
    // Weitere Fachgruppen am Termin: wer in einer davon ist, sieht ihn
    if ((int)($u['fachgruppe_id'] ?? 0) > 0 && in_array((int)$u['fachgruppe_id'], kalender_fachgruppen_ids($t), true)) {
        return true;
    }
    return match ((string)$t['ziel']) {
        'ov'         => true,
        'fachgruppe' => (int)$t['ziel_id'] === (int)($u['fachgruppe_id'] ?? 0),
        'funktion'   => in_array((int)$t['ziel_id'], $u['functions'] ?? [], true),
        'user'       => (int)$t['ziel_id'] === (int)$u['id'],
        default      => false,
    };
}

/** Die weiteren Fachgruppen eines Termins als ids – aus „5,7" (Abfrage) oder einer Liste. Reine Funktion. */
function kalender_fachgruppen_ids(array $t): array
{
    $roh = $t['fachgruppen_ids'] ?? [];
    if (is_string($roh)) {
        $roh = $roh === '' ? [] : explode(',', $roh);
    }
    return array_values(array_filter(array_map('intval', (array)$roh), static fn($i) => $i > 0));
}

/** Welche Fachgruppen diese Person an einen Termin hängen darf: Leitung alle, sonst nur die eigene. Reine Funktion. */
function kalender_fachgruppen_erlaubt(array $ids, array $u): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
    return array_values(array_filter($ids, static fn($id) => kalender_darf_ziel('fachgruppe', $id, $u)));
}

/** Genau diese weiteren Fachgruppen gehören zum Termin */
function kalender_fachgruppen_setzen(int $entryId, array $ids, array $u): void
{
    db_exec('DELETE FROM calendar_fachgruppen WHERE entry_id = ?', [$entryId]);
    foreach (kalender_fachgruppen_erlaubt($ids, $u) as $fid) {
        db_insert('calendar_fachgruppen', ['entry_id' => $entryId, 'fachgruppe_id' => $fid]);
    }
}

/** Darf diese Person einen Termin mit diesem Ziel anlegen? Reine Funktion. */
function kalender_darf_ziel(string $ziel, int $zielId, array $u): bool
{
    if (in_array((string)($u['role'] ?? ''), ['admin', 'leitung'], true)) {
        return true;
    }
    return match ($ziel) {
        'user'       => $zielId === (int)$u['id'],
        'fachgruppe' => $zielId > 0 && $zielId === (int)($u['fachgruppe_id'] ?? 0),
        'funktion'   => $zielId > 0 && in_array($zielId, $u['functions'] ?? [], true),
        default      => false,
    };
}

function kalender_darf_bearbeiten(array $t, array $u): bool
{
    return in_array((string)($u['role'] ?? ''), ['admin', 'leitung'], true) || (int)($t['created_by'] ?? 0) === (int)$u['id'];
}

/** Was ein Termin „für" wen ist – zum Anzeigen, samt weiterer Fachgruppen. */
function kalender_ziel_name(array $t): string
{
    $name = match ((string)$t['ziel']) {
        'ov'         => 'Ortsverband',
        'fachgruppe' => 'Fachgruppe ' . list_label((int)$t['ziel_id'], '?'),
        'funktion'   => list_label((int)$t['ziel_id'], '?'),
        'user'       => (string)($t['ziel_name'] ?? 'persönlich'),
        default      => '',
    };
    $weitere = trim((string)($t['fachgruppen_namen'] ?? ''));
    return $weitere !== '' ? ($name !== '' ? $name . ' + ' : '') . $weitere : $name;
}

/** Unterabfrage: die Instandsetzungsaufträge eines Termins als „2026-0005 Bremse · …" */
function kalender_auftraege_sub(): string
{
    return "(SELECT GROUP_CONCAT(CONCAT(o.nummer, ' ', o.titel) ORDER BY o.nummer SEPARATOR ' · ') FROM calendar_auftraege ca JOIN vehicle_orders o ON o.id = ca.order_id
              WHERE ca.entry_id = k.id) AS auftraege_namen";
}

/** Die Aufträge eines Termins */
function kalender_auftraege(int $entryId): array
{
    return db_all('SELECT o.id, o.nummer, o.titel, o.vehicle_id, v.bezeichnung AS fahrzeug FROM calendar_auftraege ca
                   JOIN vehicle_orders o ON o.id = ca.order_id JOIN vehicles v ON v.id = o.vehicle_id WHERE ca.entry_id = ? ORDER BY o.nummer', [$entryId]);
}

/** Genau diese Aufträge gehören zum Termin */
function kalender_auftraege_setzen(int $entryId, array $ids): void
{
    db_exec('DELETE FROM calendar_auftraege WHERE entry_id = ?', [$entryId]);
    foreach (array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0))) as $oid) {
        db_insert('calendar_auftraege', ['entry_id' => $entryId, 'order_id' => $oid]);
    }
}

/** Alle Termine eines Auftrags (Abgabe, Abholung, Nachbesserung), die diese Person sehen darf – älteste zuerst */
function kalender_termine_auftrag(int $orderId, array $u): array
{
    $rows = db_all(
        'SELECT k.*, u.display_name AS ziel_name, ' . kalender_select_zusatz() . '
         FROM calendar_auftraege ca
         JOIN calendar_entries k ON k.id = ca.entry_id
         LEFT JOIN users u ON u.id = k.ziel_id AND k.ziel = \'user\'
         WHERE ca.order_id = ?
         ORDER BY k.beginn, k.id',
        [$orderId]
    );
    return array_values(array_filter($rows, static fn($t) => kalender_sichtbar($t, $u)));
}

/** Untertitel eines Termins: für wen – dazu Dienststellen (⛑), Aufträge (🔧) und Kontakte (👤, nur wer Kontakte sehen darf). */
function kalender_untertitel(array $t): string
{
    $d = trim((string)($t['dienststellen_namen'] ?? ''));
    $a = trim((string)($t['auftraege_namen'] ?? ''));
    $k = can('view_contacts') ? trim((string)($t['kontakte_namen'] ?? '')) : '';
    return kalender_ziel_name($t) . ($d !== '' ? ' · ⛑ ' . $d : '') . ($a !== '' ? ' · 🔧 ' . $a : '') . ($k !== '' ? ' · 👤 ' . $k : '');
}

/** Unterabfrage: die beteiligten THW-Dienststellen eines Termins als „OV Neckarsulm, RSt Heilbronn" */
function kalender_dienststellen_sub(): string
{
    return "(SELECT GROUP_CONCAT(COALESCE(NULLIF(d.kurz, ''), d.name) ORDER BY d.sort_order, d.name SEPARATOR ', ')
              FROM calendar_dienststellen cd JOIN thw_dienststellen d ON d.id = cd.dienststelle_id WHERE cd.entry_id = k.id) AS dienststellen_namen";
}

/** Die beteiligten Dienststellen eines Termins */
function kalender_dienststellen(int $entryId): array
{
    return db_all('SELECT d.* FROM calendar_dienststellen cd JOIN thw_dienststellen d ON d.id = cd.dienststelle_id WHERE cd.entry_id = ? ORDER BY d.sort_order, d.name', [$entryId]);
}

/** Genau diese Dienststellen gehören zum Termin */
function kalender_dienststellen_setzen(int $entryId, array $ids): void
{
    db_exec('DELETE FROM calendar_dienststellen WHERE entry_id = ?', [$entryId]);
    foreach (array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0))) as $did) {
        db_insert('calendar_dienststellen', ['entry_id' => $entryId, 'dienststelle_id' => $did]);
    }
}

/** Unterabfrage: die Kontakte eines Termins als „Max Muster, Stadt Heilbronn" */
function kalender_kontakte_sub(): string
{
    return "(SELECT GROUP_CONCAT(COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.vorname, c.nachname)), ''), c.organisation) ORDER BY c.nachname, c.vorname SEPARATOR ', ')
              FROM calendar_kontakte ck JOIN contacts c ON c.id = ck.contact_id WHERE ck.entry_id = k.id) AS kontakte_namen";
}

/** Die Kontakte eines Termins */
function kalender_kontakte(int $entryId): array
{
    return db_all('SELECT c.id, c.titel, c.vorname, c.nachname, c.organisation, c.position, c.email, c.telefon, c.mobil, c.is_active
                   FROM calendar_kontakte ck JOIN contacts c ON c.id = ck.contact_id WHERE ck.entry_id = ? ORDER BY c.nachname, c.vorname, c.organisation', [$entryId]);
}

/** Genau diese Kontakte gehören zum Termin */
function kalender_kontakte_setzen(int $entryId, array $ids): void
{
    db_exec('DELETE FROM calendar_kontakte WHERE entry_id = ?', [$entryId]);
    foreach (array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0))) as $cid) {
        db_insert('calendar_kontakte', ['entry_id' => $entryId, 'contact_id' => $cid]);
    }
}

/** Unterabfragen für die weiteren Fachgruppen eines Termins: ids und Namen */
function kalender_fachgruppen_sub(): string
{
    return "(SELECT GROUP_CONCAT(cf.fachgruppe_id) FROM calendar_fachgruppen cf WHERE cf.entry_id = k.id) AS fachgruppen_ids,
            (SELECT GROUP_CONCAT(li.label ORDER BY li.sort_order, li.label SEPARATOR ', ') FROM calendar_fachgruppen cf JOIN list_items li ON li.id = cf.fachgruppe_id
              WHERE cf.entry_id = k.id) AS fachgruppen_namen";
}

/** Unterabfrage für die Platznamen – leer, wenn die Plätze (noch) nicht geladen sind */
function kalender_standorte_sub(string $tabelle, string $spalte, string $alias): string
{
    return function_exists('standort_namen_subquery') ? standort_namen_subquery($tabelle, $spalte, $alias) : "''";
}

/** Spalten, die jeder Termin mitbringt: Platzname und die Namen seiner Fahrzeuge */
function kalender_select_zusatz(): string
{
    return kalender_standorte_sub('calendar_standorte', 'entry_id', 'k') . " AS standort_name,
            (SELECT GROUP_CONCAT(v.bezeichnung ORDER BY v.bezeichnung SEPARATOR ' · ') FROM calendar_vehicles cv JOIN vehicles v ON v.id = cv.vehicle_id
              WHERE cv.entry_id = k.id) AS fahrzeuge_namen, " . kalender_fachgruppen_sub() . ", " . kalender_auftraege_sub() . ", " . kalender_kontakte_sub() . ", " . kalender_dienststellen_sub();
}

function kalender_find(int $id): ?array
{
    return $id > 0 ? db_row(
        'SELECT k.*, u.display_name AS ziel_name, e.display_name AS erfasser, ' . kalender_select_zusatz() . '
         FROM calendar_entries k
         LEFT JOIN users u ON u.id = k.ziel_id AND k.ziel = \'user\'
         LEFT JOIN users e ON e.id = k.created_by
         WHERE k.id = ?',
        [$id]
    ) : null;
}

/** Eigene Termine im Zeitraum, die diese Person sehen darf */
function kalender_termine(string $von, string $bis, array $u): array
{
    $rows = db_all(
        'SELECT k.*, u.display_name AS ziel_name, ' . kalender_select_zusatz() . '
         FROM calendar_entries k
         LEFT JOIN users u ON u.id = k.ziel_id AND k.ziel = \'user\'
         WHERE k.beginn < ? AND COALESCE(k.ende, k.beginn) >= ?
         ORDER BY k.beginn, k.id',
        [$bis . ' 23:59:59', $von . ' 00:00:00']
    );
    return array_values(array_filter($rows, static fn($t) => kalender_sichtbar($t, $u)));
}

/** Platz und freier Ort als ein Text: „Halle 1 · Tor 3". Reine Funktion. */
function kalender_ort_text(array $t): string
{
    return implode(' · ', array_filter([trim((string)($t['standort_name'] ?? '')), trim((string)($t['ort'] ?? ''))], 'strlen'));
}

/* ---------- Fahrzeuge am Termin ---------- */

/** Die Fahrzeuge eines Termins */
function kalender_fahrzeuge(int $entryId): array
{
    return db_all('SELECT v.id, v.bezeichnung, v.funkrufname, v.kennzeichen, v.is_active FROM calendar_vehicles cv
                   JOIN vehicles v ON v.id = cv.vehicle_id WHERE cv.entry_id = ? ORDER BY v.bezeichnung', [$entryId]);
}

/** Genau diese Fahrzeuge gehören zum Termin */
function kalender_fahrzeuge_setzen(int $entryId, array $ids): void
{
    db_exec('DELETE FROM calendar_vehicles WHERE entry_id = ?', [$entryId]);
    foreach (array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0))) as $vid) {
        db_insert('calendar_vehicles', ['entry_id' => $entryId, 'vehicle_id' => $vid]);
    }
}

/** Termine mit einem Fahrzeug im Zeitraum, die diese Person sehen darf */
function kalender_termine_fahrzeug(int $vehicleId, string $von, string $bis, array $u): array
{
    $rows = db_all(
        'SELECT k.*, u.display_name AS ziel_name, ' . kalender_select_zusatz() . '
         FROM calendar_vehicles cv
         JOIN calendar_entries k ON k.id = cv.entry_id
         LEFT JOIN users u ON u.id = k.ziel_id AND k.ziel = \'user\'
         WHERE cv.vehicle_id = ? AND k.beginn < ? AND COALESCE(k.ende, k.beginn) >= ?
         ORDER BY k.beginn, k.id',
        [$vehicleId, $bis . ' 23:59:59', $von . ' 00:00:00']
    );
    return array_values(array_filter($rows, static fn($t) => kalender_sichtbar($t, $u)));
}

/** Alle Fahrzeuge, die im Zeitraum in Terminen verplant sind – je Fahrzeug seine Termine */
function kalender_fahrzeuge_zeitraum(string $von, string $bis, array $u): array
{
    $rows = db_all(
        'SELECT k.*, u.display_name AS ziel_name, ' . kalender_standorte_sub('calendar_standorte', 'entry_id', 'k') . ' AS standort_name, ' . kalender_fachgruppen_sub() . ',
                v.id AS vehicle_id, v.bezeichnung AS vehicle_bezeichnung, v.funkrufname AS vehicle_funkrufname, v.kennzeichen AS vehicle_kennzeichen
         FROM calendar_vehicles cv
         JOIN calendar_entries k ON k.id = cv.entry_id
         JOIN vehicles v ON v.id = cv.vehicle_id
         LEFT JOIN users u ON u.id = k.ziel_id AND k.ziel = \'user\'
         WHERE k.beginn < ? AND COALESCE(k.ende, k.beginn) >= ?
         ORDER BY v.bezeichnung, k.beginn, k.id',
        [$bis . ' 23:59:59', $von . ' 00:00:00']
    );
    return kalender_fahrzeuge_gruppieren(array_values(array_filter($rows, static fn($t) => kalender_sichtbar($t, $u))));
}

/** Zeilen (Termin + Fahrzeugspalten) je Fahrzeug bündeln, Fahrzeuge nach dem frühesten Termin. Reine Funktion. */
function kalender_fahrzeuge_gruppieren(array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        $vid = (int)$r['vehicle_id'];
        if (!isset($out[$vid])) {
            $out[$vid] = ['fahrzeug' => ['id' => $vid, 'bezeichnung' => (string)$r['vehicle_bezeichnung'], 'funkrufname' => (string)($r['vehicle_funkrufname'] ?? ''),
                                         'kennzeichen' => (string)($r['vehicle_kennzeichen'] ?? '')], 'termine' => []];
        }
        $out[$vid]['termine'][] = ['id' => (int)$r['id'], 'titel' => (string)$r['titel'], 'beginn' => (string)$r['beginn'], 'ende' => $r['ende'] ?? null,
                                   'ganztag' => (int)($r['ganztag'] ?? 0) === 1, 'ort' => kalender_ort_text($r)];
    }
    foreach ($out as &$g) {
        usort($g['termine'], static fn($a, $b) => $a['beginn'] <=> $b['beginn']);
    }
    unset($g);
    uasort($out, static fn($a, $b) => [$a['termine'][0]['beginn'], $a['fahrzeug']['bezeichnung']] <=> [$b['termine'][0]['beginn'], $b['fahrzeug']['bezeichnung']]);
    return array_values($out);
}

/** Überschneiden sich zwei Zeiträume? Gleicher Beginn zählt mit. Reine Funktion. */
function kalender_ueberschneidung(string $b1, ?string $e1, string $b2, ?string $e2): bool
{
    $e1 = $e1 ?: $b1;
    $e2 = $e2 ?: $b2;
    return $b1 === $b2 || ($b1 < $e2 && $b2 < $e1);
}

/** Beginn und Ende aus den Formularfeldern (datum, zeit, ganztag, ende_datum, ende_zeit) – null, wenn unvollständig. Reine Funktion. */
function kalender_zeitraum_aus_feldern(array $t): ?array
{
    $datum = (string)($t['datum'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
        return null;
    }
    $ganztag = (int)($t['ganztag'] ?? 0) === 1;
    $zeit = preg_match('/^\d{2}:\d{2}$/', (string)($t['zeit'] ?? '')) ? (string)$t['zeit'] : '';
    if (!$ganztag && $zeit === '') {
        return null;
    }
    $endeDatum = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($t['ende_datum'] ?? '')) ? (string)$t['ende_datum'] : $datum;
    $endeZeit = preg_match('/^\d{2}:\d{2}$/', (string)($t['ende_zeit'] ?? '')) ? (string)$t['ende_zeit'] : '';
    $beginn = $datum . ' ' . ($ganztag ? '00:00:00' : $zeit . ':00');
    $ende = $ganztag ? $endeDatum . ' 23:59:59' : $endeDatum . ' ' . ($endeZeit !== '' ? $endeZeit . ':00' : $zeit . ':00');
    return $ende < $beginn ? null : [$beginn, $ende];
}

/**
 * Welche dieser Fahrzeuge stecken im Zeitraum schon in einem anderen Termin?
 * Rückgabe: [vehicle_id => [[id, titel, beginn, ende, ganztag]]]. $ausser: der eigene Termin.
 */
function kalender_fahrzeug_konflikte(array $vehicleIds, string $beginn, string $ende, int $ausser = 0): array
{
    $ids = array_values(array_filter(array_map('intval', $vehicleIds), static fn($i) => $i > 0));
    if (!$ids) {
        return [];
    }
    $platz = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_all(
        "SELECT cv.vehicle_id, k.id, k.titel, k.beginn, k.ende, k.ganztag
         FROM calendar_vehicles cv JOIN calendar_entries k ON k.id = cv.entry_id
         WHERE cv.vehicle_id IN ($platz) AND k.id <> ? AND k.beginn <= ? AND COALESCE(k.ende, k.beginn) >= ?
         ORDER BY k.beginn, k.id",
        array_merge($ids, [$ausser, $ende, $beginn])
    );
    return kalender_konflikte_gruppieren($rows, $beginn, $ende);
}

/** Zeilen (vehicle_id + Termin) zu [vehicle_id => Termine], nur echte Überschneidungen. Reine Funktion. */
function kalender_konflikte_gruppieren(array $rows, string $beginn, string $ende): array
{
    $out = [];
    foreach ($rows as $r) {
        if (kalender_ueberschneidung($beginn, $ende, (string)$r['beginn'], $r['ende'] ?? null)) {
            $out[(int)$r['vehicle_id']][] = ['id' => (int)$r['id'], 'titel' => (string)$r['titel'], 'beginn' => (string)$r['beginn'],
                                             'ende' => $r['ende'] ?? null, 'ganztag' => (int)($r['ganztag'] ?? 0) === 1];
        }
    }
    return $out;
}

/** „Sa 17.10., 08:00 Uhr" für einen Termin. Reine Funktion. */
function kalender_wann(array $t): string
{
    $tag = substr((string)$t['beginn'], 0, 10);
    $zeit = kalender_zeit(['ganztag' => !empty($t['ganztag']), 'beginn' => (string)$t['beginn'], 'ende' => $t['ende'] ?? null]);
    return ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'][(int)date('N', strtotime($tag)) - 1] . ' ' . date('d.m.', strtotime($tag)) . ($zeit !== '' ? ', ' . $zeit . ' Uhr' : '');
}

/** Warnung für die Leitung: welche Fahrzeuge doppelt gebucht sind. Reine Funktion. */
function kalender_konflikt_text(array $konflikte, array $fahrzeuge): string
{
    $namen = [];
    foreach ($fahrzeuge as $v) {
        $namen[(int)$v['id']] = (string)$v['bezeichnung'];
    }
    $teile = [];
    foreach ($konflikte as $vid => $termine) {
        $teile[] = ($namen[(int)$vid] ?? ('Fahrzeug #' . $vid)) . ' ist auch im Termin ' . implode(' und ', array_map(static fn($t) => '„' . $t['titel'] . '" (' . kalender_wann($t) . ')', $termine));
    }
    return $teile ? 'Doppelbuchung: ' . implode('; ', $teile) . '.' : '';
}

/** Fahrzeuge in drei Blöcke: Autos und LKW, Anhänger, Geräte – nach Art (slug/label). Reine Funktion. */
function kalender_fahrzeuge_bloecke(array $fahrzeuge): array
{
    $out = ['fahrzeuge' => [], 'anhaenger' => [], 'geraete' => []];
    foreach ($fahrzeuge as $v) {
        $slug = strtolower((string)($v['typ_slug'] ?? ''));
        $label = strtolower((string)($v['typ_label'] ?? ''));
        if (str_contains($slug, 'anh') || str_contains($label, 'anhänger') || str_contains($label, 'anhaenger') || str_contains($label, 'fwa')) {
            $out['anhaenger'][] = $v;
        } elseif (str_starts_with($slug, 'geraet') || preg_match('/aggregat|pumpe|boot|notstrom|material/u', $label)
            || (preg_match('/gerät|geraet/u', $label) && !preg_match('/wagen|fahrzeug|lkw|kraft/u', $label))) {
            // „Gerätekraftwagen" bleibt ein Fahrzeug, „Gerät / Aggregat" ein Gerät
            $out['geraete'][] = $v;
        } else {
            $out['fahrzeuge'][] = $v;
        }
    }
    return $out;
}

/** In den Wochengruppen die Termine markieren, die sich je Fahrzeug überschneiden ('konflikt'). Reine Funktion. */
function kalender_konflikte_markieren(array $gruppen): array
{
    foreach ($gruppen as &$g) {
        $g['konflikte'] = 0;
        $n = count($g['termine']);
        for ($i = 0; $i < $n; $i++) {
            $g['termine'][$i]['konflikt'] = false;
            for ($k = 0; $k < $n; $k++) {
                if ($k !== $i && kalender_ueberschneidung((string)$g['termine'][$i]['beginn'], $g['termine'][$i]['ende'] ?? null, (string)$g['termine'][$k]['beginn'], $g['termine'][$k]['ende'] ?? null)) {
                    $g['termine'][$i]['konflikt'] = true;
                    break;
                }
            }
            if ($g['termine'][$i]['konflikt']) {
                $g['konflikte']++;
            }
        }
    }
    unset($g);
    return $gruppen;
}

/** Montag und Sonntag der Woche, in der ein Tag liegt. Reine Funktion. */
function kalender_woche(string $tag): array
{
    $ts = strtotime($tag);
    $montag = $ts - (((int)date('N', $ts)) - 1) * 86400;
    return [date('Y-m-d', $montag), date('Y-m-d', $montag + 6 * 86400)];
}

/** Termin aus dem Formular speichern. Gibt [id, fehler[]] zurück. */
function kalender_save_from_post(?array $existing, array $user): array
{
    $errors = [];
    $titel = trim(post_str('titel'));
    if ($titel === '') {
        $errors[] = 'Bitte einen Titel angeben.';
    }
    $datum = post_date('datum');
    if (!$datum) {
        $errors[] = 'Bitte ein gültiges Datum angeben.';
    }
    $ganztag = post_bool('ganztag') === 1;
    $zeit = preg_match('/^\d{2}:\d{2}$/', post_str('zeit')) ? post_str('zeit') : '';
    $endeDatum = post_date('ende_datum') ?: $datum;
    $endeZeit = preg_match('/^\d{2}:\d{2}$/', post_str('ende_zeit')) ? post_str('ende_zeit') : '';
    if (!$ganztag && $zeit === '') {
        $errors[] = 'Bitte eine Uhrzeit angeben – oder „ganztägig" anhaken.';
    }
    $ziel = kalender_ziel(post_str('ziel', 'user'));
    $zielId = match ($ziel) {
        'fachgruppe' => (int)post_int('ziel_fachgruppe'),
        'funktion'   => (int)post_int('ziel_funktion'),
        'user'       => (int)(post_int('ziel_user') ?: $user['id']),
        default      => 0,
    };
    if ($ziel !== 'ov' && $zielId <= 0) {
        $errors[] = 'Bitte auswählen, für wen der Termin gilt.';
    } elseif (!kalender_darf_ziel($ziel, $zielId, $user)) {
        $errors[] = 'Für dieses Ziel darfst du keine Termine anlegen – nur für dich, deine Fachgruppe und deine Funktionen.';
    }
    if ($errors) {
        return [null, $errors];
    }
    $beginn = $datum . ' ' . ($ganztag ? '00:00:00' : $zeit . ':00');
    $ende = $ganztag
        ? $endeDatum . ' 23:59:59'
        : $endeDatum . ' ' . ($endeZeit !== '' ? $endeZeit . ':00' : $zeit . ':00');
    if ($ende < $beginn) {
        return [null, ['Das Ende liegt vor dem Beginn.']];
    }
    $farbe = preg_match('/^#[0-9a-fA-F]{6}$/', post_str('farbe')) ? strtolower(post_str('farbe')) : '';
    $data = [
        'titel'        => mb_substr($titel, 0, 200),
        'beschreibung' => post_str('beschreibung'),
        'ort'          => mb_substr(post_str('ort'), 0, 150),
        'beginn'       => $beginn,
        'ende'         => $ende,
        'ganztag'      => $ganztag ? 1 : 0,
        'ziel'         => $ziel,
        'ziel_id'      => $ziel === 'ov' ? null : $zielId,
        'farbe'        => $farbe,
        'updated_by'   => (int)$user['id'],
    ];
    if ($existing) {
        db_update('calendar_entries', $data, 'id = ?', [(int)$existing['id']]);
        $id = (int)$existing['id'];
        audit('termin.bearbeitet', 'calendar', $id, $data['titel']);
    } else {
        $data['created_by'] = (int)$user['id'];
        $id = db_insert('calendar_entries', $data);
        audit('termin.angelegt', 'calendar', $id, $data['titel']);
    }
    // Fahrzeuge nur anfassen, wenn das Feld im Formular war (die Person sie also wählen konnte)
    if (can('view_vehicles') && (post_str('fahrzeuge_feld') === '1' || isset($_POST['vehicles']))) {
        kalender_fahrzeuge_setzen($id, wahl_ids('vehicles'));
    }
    // Plätze: nur, wenn das Feld im Formular war
    if (post_str('standorte_feld') === '1' && function_exists('standort_links_setzen')) {
        standort_links_setzen('calendar_standorte', 'entry_id', $id, wahl_ids('standorte'));
    }
    // Weitere Fachgruppen: nur, wenn das Feld im Formular war; Mitglieder nur die eigene
    if (post_str('fachgruppen_feld') === '1') {
        kalender_fachgruppen_setzen($id, wahl_ids('fachgruppen'), $user);
    }
    // Instandsetzungsaufträge (Werkstatttermin): nur, wenn das Feld im Formular war
    if (can('view_vehicles') && post_str('auftraege_feld') === '1') {
        kalender_auftraege_setzen($id, wahl_ids('auftraege'));
    }
    // Kontakte: nur, wer sie sehen darf und wenn das Feld im Formular war
    if (can('view_contacts') && post_str('kontakte_feld') === '1') {
        kalender_kontakte_setzen($id, wahl_ids('kontakte'));
    }
    // Beteiligte THW-Dienststellen (in oder mit OV y): nur, wenn das Feld im Formular war
    if (post_str('dienststellen_feld') === '1') {
        kalender_dienststellen_setzen($id, wahl_ids('dienststellen'));
    }
    return [$id, []];
}

/** Belegung eines Platzes: Termine und Veranstaltungen dort im Zeitraum, als Kalendereinträge */
function standort_belegung(int $standortId, string $von, string $bis, array $u): array
{
    $out = [];
    $rows = db_all(
        'SELECT k.*, u.display_name AS ziel_name, ' . kalender_select_zusatz() . '
         FROM calendar_standorte cs
         JOIN calendar_entries k ON k.id = cs.entry_id
         LEFT JOIN users u ON u.id = k.ziel_id AND k.ziel = \'user\'
         WHERE cs.standort_id = ? AND k.beginn < ? AND COALESCE(k.ende, k.beginn) >= ?
         ORDER BY k.beginn, k.id',
        [$standortId, $bis . ' 23:59:59', $von . ' 00:00:00']
    );
    foreach ($rows as $t) {
        if (!kalender_sichtbar($t, $u)) {
            continue;
        }
        $out[] = kalender_eintrag('termin', (string)$t['titel'], (string)$t['beginn'], $t['ende'], [
            'ganztag' => (int)$t['ganztag'] === 1, 'ort' => kalender_ort_text($t), 'id' => $t['id'], 'fahrzeuge' => (string)($t['fahrzeuge_namen'] ?? ''),
            'url' => url('kalender_termin', ['id' => $t['id']]), 'farbe' => $t['farbe'] ?: KALENDER_QUELLEN['termin']['color'], 'untertitel' => kalender_untertitel($t),
        ]);
    }
    if (can('view_events')) {
        foreach (db_all("SELECT e.id, e.titel, e.beginn, e.ende, e.ort, t.color AS typ_color, t.label AS typ_label
                         FROM event_standorte es JOIN events e ON e.id = es.event_id LEFT JOIN list_items t ON t.id = e.typ_id
                         WHERE es.standort_id = ? AND e.beginn <= ? AND COALESCE(e.ende, e.beginn) >= ? AND e.status <> 'abgesagt'
                         ORDER BY e.beginn", [$standortId, $bis . ' 23:59:59', $von . ' 00:00:00']) as $e) {
            $out[] = kalender_eintrag('veranstaltung', (string)$e['titel'], (string)$e['beginn'], $e['ende'], ['ort' => (string)$e['ort'], 'id' => $e['id'],
                'url' => url('event', ['id' => $e['id']]), 'farbe' => $e['typ_color'] ?: KALENDER_QUELLEN['veranstaltung']['color'],
                'untertitel' => (string)($e['typ_label'] ?? ''), 'ganztag' => substr((string)$e['beginn'], 11, 5) === '00:00']);
        }
    }
    usort($out, static fn($a, $b) => [$a['beginn'], $a['titel']] <=> [$b['beginn'], $b['titel']]);
    return $out;
}

function kalender_delete(array $t): void
{
    db_exec('DELETE FROM calendar_entries WHERE id = ?', [(int)$t['id']]);
    audit('termin.geloescht', 'calendar', (int)$t['id'], (string)$t['titel']);
}

/* ==================================================================== */
/* Einsammeln aus allen Quellen                                          */
/* ==================================================================== */

/** Ein Eintrag in einheitlicher Form. Reine Funktion. */
function kalender_eintrag(string $quelle, string $titel, string $beginn, ?string $ende = null, array $mehr = []): array
{
    $ganztag = $mehr['ganztag'] ?? (strlen($beginn) === 10);
    return [
        'quelle'   => $quelle,
        'titel'    => $titel,
        'beginn'   => strlen($beginn) === 10 ? $beginn . ' 00:00:00' : $beginn,
        'ende'     => $ende === null ? null : (strlen($ende) === 10 ? $ende . ' 23:59:59' : $ende),
        'ganztag'  => (bool)$ganztag,
        'ort'      => (string)($mehr['ort'] ?? ''),
        'url'      => (string)($mehr['url'] ?? ''),
        'farbe'    => (string)($mehr['farbe'] ?? KALENDER_QUELLEN[$quelle]['color']),
        'untertitel' => (string)($mehr['untertitel'] ?? ''),
        'fahrzeuge' => (string)($mehr['fahrzeuge'] ?? ''),
        'id'       => (int)($mehr['id'] ?? 0),
        'tag'      => substr($beginn, 0, 10),
    ];
}

/** Welche Quellen diese Person sehen darf */
function kalender_quellen_fuer(array $u): array
{
    $out = ['termin', 'aufgabe', 'feiertag', 'ha'];
    if (can('view_meetings')) {
        $out[] = 'besprechung';
    }
    if (can('view_events')) {
        $out[] = 'veranstaltung';
    }
    if (can('view_vehicles') || can('view_radios') || can('view_sims')) {
        $out[] = 'frist';
    }
    if (can('view_expenses')) {
        $out[] = 'budget';
    }
    return $out;
}

/**
 * Alle Einträge zwischen $von und $bis (je Y-m-d), nach Beginn sortiert.
 * $quellen schränkt ein; $u ist die angemeldete Person.
 */
function kalender_sammeln(string $von, string $bis, array $u, ?array $quellen = null): array
{
    $quellen ??= kalender_quellen_fuer($u);
    $out = [];
    foreach ($quellen as $q) {
        try {
            $out = array_merge($out, kalender_quelle($q, $von, $bis, $u));
        } catch (Throwable $ex) {
            // Eine Quelle (etwa eine fehlende Tabelle vor der Wanderung) hält den Kalender nicht auf
            error_log('OV-Multitool Kalender, Quelle ' . $q . ': ' . $ex->getMessage());
        }
    }
    usort($out, static fn($a, $b) => [$a['beginn'], $a['quelle'], $a['titel']] <=> [$b['beginn'], $b['quelle'], $b['titel']]);
    return $out;
}

/** Einträge einer einzelnen Quelle */
function kalender_quelle(string $quelle, string $von, string $bis, array $u): array
{
    $quellen = [$quelle];
    $out = [];

    if (in_array('termin', $quellen, true)) {
        foreach (kalender_termine($von, $bis, $u) as $t) {
            $out[] = kalender_eintrag('termin', (string)$t['titel'], (string)$t['beginn'], $t['ende'], [
                'ganztag' => (int)$t['ganztag'] === 1, 'ort' => kalender_ort_text($t), 'id' => $t['id'], 'fahrzeuge' => (string)($t['fahrzeuge_namen'] ?? ''),
                'url' => url('kalender_termin', ['id' => $t['id']]), 'farbe' => $t['farbe'] ?: KALENDER_QUELLEN['termin']['color'],
                'untertitel' => kalender_untertitel($t),
            ]);
        }
    }
    if (in_array('besprechung', $quellen, true)) {
        foreach (db_all("SELECT id, titel, datum, beginn, ende, ort, status FROM meetings WHERE datum BETWEEN ? AND ? AND status <> 'abgesagt'", [$von, $bis]) as $m) {
            $b = $m['beginn'] ? $m['datum'] . ' ' . substr((string)$m['beginn'], 0, 8) : $m['datum'];
            $e = $m['ende'] ? $m['datum'] . ' ' . substr((string)$m['ende'], 0, 8) : null;
            $out[] = kalender_eintrag('besprechung', (string)$m['titel'], $b, $e, ['ort' => $m['ort'], 'id' => $m['id'],
                'url' => url('meeting', ['id' => $m['id']]), 'ganztag' => !$m['beginn']]);
        }
    }
    if (in_array('veranstaltung', $quellen, true)) {
        foreach (db_all("SELECT e.id, e.titel, e.beginn, e.ende, e.ort, t.color AS typ_color, t.label AS typ_label,
                                " . kalender_standorte_sub('event_standorte', 'event_id', 'e') . " AS standort_name
                         FROM events e LEFT JOIN list_items t ON t.id = e.typ_id
                         WHERE e.beginn <= ? AND COALESCE(e.ende, e.beginn) >= ? AND e.status <> 'abgesagt'", [$bis . ' 23:59:59', $von . ' 00:00:00']) as $e) {
            $out[] = kalender_eintrag('veranstaltung', (string)$e['titel'], (string)$e['beginn'], $e['ende'], ['ort' => kalender_ort_text($e), 'id' => $e['id'],
                'url' => url('event', ['id' => $e['id']]), 'farbe' => $e['typ_color'] ?: KALENDER_QUELLEN['veranstaltung']['color'],
                'untertitel' => (string)($e['typ_label'] ?? ''), 'ganztag' => substr((string)$e['beginn'], 11, 5) === '00:00']);
        }
    }
    if (in_array('aufgabe', $quellen, true)) {
        foreach (db_all("SELECT t.* FROM todos t LEFT JOIN list_items s ON s.id = t.status_id
                         WHERE COALESCE(s.is_final,0) = 0 AND t.faellig_am BETWEEN ? AND ?", [$von, $bis]) as $t) {
            if (!todo_is_mine($t, $u) && !can('manage_todos')) {
                continue;
            }
            // Mit Haken-Symbol, damit die Aufgabe im Kalender sofort als Aufgabe erkennbar ist
            $out[] = kalender_eintrag('aufgabe', '☑ Fällig: ' . $t['titel'], (string)$t['faellig_am'], null, ['id' => $t['id'],
                'url' => url('todo', ['id' => $t['id']]), 'untertitel' => 'Aufgabe' . (function_exists('todo_target_name') ? ' · ' . todo_target_name($t) : '')]);
        }
    }
    if (in_array('frist', $quellen, true)) {
        if (can('view_vehicles')) {
            foreach (db_all('SELECT id, bezeichnung, hu_bis, sp_bis, uvv_bis FROM vehicles WHERE is_active = 1') as $v) {
                foreach (['hu_bis' => 'HU', 'sp_bis' => 'SP', 'uvv_bis' => 'UVV'] as $feld => $label) {
                    if ($v[$feld] && $v[$feld] >= $von && $v[$feld] <= $bis) {
                        $out[] = kalender_eintrag('frist', $label . ' fällig: ' . $v['bezeichnung'], (string)$v[$feld], null,
                            ['id' => $v['id'], 'url' => url('vehicle', ['id' => $v['id']]), 'untertitel' => 'Fahrzeug']);
                    }
                }
            }
        }
        if (can('view_radios')) {
            foreach (db_all('SELECT id, bezeichnung, pruefung_bis FROM radios WHERE is_active = 1 AND pruefung_bis BETWEEN ? AND ?', [$von, $bis]) as $r) {
                $out[] = kalender_eintrag('frist', 'Prüfung fällig: ' . $r['bezeichnung'], (string)$r['pruefung_bis'], null,
                    ['id' => $r['id'], 'url' => url('radio', ['id' => $r['id']]), 'untertitel' => 'Funkgerät']);
            }
        }
        if (can('view_sims')) {
            foreach (db_all('SELECT * FROM sims WHERE is_active = 1 AND vertrag_bis BETWEEN ? AND ?', [$von, $bis]) as $s) {
                $name = function_exists('sim_bezeichnung') ? sim_bezeichnung($s) : (string)(($s['rufnummer'] ?? '') ?: (($s['issi'] ?? '') ?: 'SIM-Karte'));
                $out[] = kalender_eintrag('frist', 'Vertrag endet: ' . $name, (string)$s['vertrag_bis'], null,
                    ['id' => $s['id'], 'url' => url('sim_edit', ['id' => $s['id']]), 'untertitel' => 'SIM-Karte']);
            }
        }
    }
    if (in_array('budget', $quellen, true)) {
        foreach (db_all('SELECT jahr, stichtag FROM budget_years WHERE stichtag BETWEEN ? AND ?', [$von, $bis]) as $b) {
            $out[] = kalender_eintrag('budget', 'Stichtag Jahresbudget ' . $b['jahr'], (string)$b['stichtag'], null,
                ['url' => url('budget', ['jahr' => $b['jahr']]), 'untertitel' => 'letzter Tag für Ausgaben']);
        }
    }
    if (in_array('feiertag', $quellen, true)) {
        $land = (string)setting('kalender_bundesland', 'BW');
        for ($j = (int)substr($von, 0, 4); $j <= (int)substr($bis, 0, 4); $j++) {
            foreach (kalender_feiertage($j, $land) as $tag => $name) {
                if ($tag >= $von && $tag <= $bis) {
                    $out[] = kalender_eintrag('feiertag', $name, $tag);
                }
            }
        }
    }
    if (in_array('ha', $quellen, true)) {
        foreach (db_all('SELECT * FROM calendar_ha WHERE beginn <= ? AND COALESCE(ende, beginn) >= ? ORDER BY beginn', [$bis . ' 23:59:59', $von . ' 00:00:00']) as $h) {
            $out[] = kalender_eintrag('ha', (string)$h['titel'], (string)$h['beginn'], $h['ende'], ['ganztag' => (int)$h['ganztag'] === 1,
                'ort' => $h['ort'], 'untertitel' => (string)$h['kalender_name']]);
        }
    }
    return $out;
}

/** Einträge je Tag verteilen – mehrtägige auf jeden Tag. Reine Funktion. */
function kalender_je_tag(array $eintraege, string $von, string $bis): array
{
    $out = [];
    foreach ($eintraege as $e) {
        $start = max($von, substr($e['beginn'], 0, 10));
        $stop = min($bis, substr((string)($e['ende'] ?? $e['beginn']), 0, 10));
        for ($t = strtotime($start); $t <= strtotime($stop); $t += 86400) {
            $out[date('Y-m-d', $t)][] = $e;
        }
    }
    return $out;
}

/** Monatsraster: Wochen à sieben Tage ab Montag, mit Vor- und Nachlauf. Reine Funktion. */
function kalender_monatsraster(int $jahr, int $monat): array
{
    $erster = mktime(0, 0, 0, $monat, 1, $jahr);
    $start = $erster - (((int)date('N', $erster) - 1) * 86400);
    $letzter = mktime(0, 0, 0, $monat + 1, 0, $jahr);
    $ende = $letzter + ((7 - (int)date('N', $letzter)) * 86400);
    $wochen = [];
    for ($t = $start; $t <= $ende; $t += 7 * 86400) {
        $woche = [];
        for ($d = 0; $d < 7; $d++) {
            $woche[] = date('Y-m-d', $t + $d * 86400);
        }
        $wochen[] = $woche;
    }
    return ['von' => date('Y-m-d', $start), 'bis' => date('Y-m-d', $ende), 'wochen' => $wochen];
}

/** Uhrzeit oder „ganztägig" für die Anzeige. Reine Funktion. */
function kalender_zeit(array $e): string
{
    if ($e['ganztag']) {
        return '';
    }
    $z = substr($e['beginn'], 11, 5);
    if (!empty($e['ende']) && substr($e['ende'], 0, 10) === substr($e['beginn'], 0, 10) && substr($e['ende'], 11, 5) !== $z) {
        $z .= '–' . substr($e['ende'], 11, 5);
    }
    return $z;
}

/* ==================================================================== */
/* Besprechungen: Termine mitnehmen                                      */
/* ==================================================================== */

/** Termine im Blick der Besprechung: ab ihrem Tag so viele Wochen voraus wie eingestellt */
function kalender_fuer_besprechung(array $meeting, array $u): array
{
    $wochen = max(1, setting_int('kalender_besprechung_wochen', 6));
    $von = (string)$meeting['datum'];
    $bis = date('Y-m-d', strtotime($von) + $wochen * 7 * 86400);
    return array_values(array_filter(kalender_sammeln($von, $bis, $u),
        static fn($e) => !($e['quelle'] === 'besprechung' && (int)$e['id'] === (int)$meeting['id']) && $e['quelle'] !== 'feiertag'));
}

/** Einen Termin als Punkt auf die Tagesordnung setzen. Gibt die id des Punktes zurück. */
function kalender_in_besprechung(array $e, int $meetingId, array $user): int
{
    $wann = de_date(substr($e['beginn'], 0, 10)) . (kalender_zeit($e) !== '' ? ', ' . kalender_zeit($e) . ' Uhr' : '');
    $titel = mb_substr('Termin: ' . $e['titel'] . ' (' . $wann . ')', 0, 200);
    $text = implode("\n", array_filter([
        'Aus dem Kalender: ' . (KALENDER_QUELLEN[$e['quelle']]['label'] ?? $e['quelle']) . ($e['untertitel'] !== '' ? ' · ' . $e['untertitel'] : ''),
        $e['ort'] !== '' ? 'Ort: ' . $e['ort'] : '',
        !empty($e['ende']) && substr($e['ende'], 0, 10) !== substr($e['beginn'], 0, 10) ? 'Bis: ' . de_date(substr($e['ende'], 0, 10)) : '',
    ]));
    $id = db_insert('talking_points', [
        'meeting_id'      => $meetingId,
        'titel'           => $titel,
        'beschreibung'    => $text,
        'status_id'       => list_default_id('tp_status'),
        'sort_order'      => meeting_next_sort($meetingId),
        'eingebracht_von' => (int)$user['id'],
    ]);
    audit('tp.aus_kalender', 'talking_point', $id, $titel);
    if ($e['quelle'] === 'termin' && (int)$e['id'] > 0) {
        db_insert('tp_links', ['tp_id' => $id, 'typ' => 'termin', 'ziel_id' => (int)$e['id']]);
    }
    return $id;
}

/* ==================================================================== */
/* Dateien am Termin: Einladung, Programm, Anfahrt                       */
/* ==================================================================== */

function kfile_dir(): string
{
    $dir = upload_dir() . DIRECTORY_SEPARATOR . 'termine';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

function kfile_list(int $entryId): array
{
    return db_all('SELECT f.*, u.display_name AS hochgeladen_von FROM calendar_files f LEFT JOIN users u ON u.id = f.uploaded_by
                   WHERE f.entry_id = ? ORDER BY f.created_at DESC, f.id DESC', [$entryId]);
}

function kfile_find(?int $id): ?array
{
    return $id ? db_row('SELECT * FROM calendar_files WHERE id = ?', [$id]) : null;
}

/** Hochgeladene Dateien an einen Termin hängen – Bilder verkleinert und ohne Aufnahmeort. Gibt [anzahl, fehler[]] zurück. */
function kfile_store_uploads(int $entryId, string $feld, string $titel, array $user): array
{
    $fehler = [];
    $anzahl = 0;
    if (empty($_FILES[$feld]) || !is_array($_FILES[$feld]['name'])) {
        return [0, ['Es kam keine Datei an. Bei sehr großen Dateien bricht der Browser den Upload ab, bevor die Anwendung ihn sieht.']];
    }
    $erlaubt = array_values(array_unique(array_merge(vfile_document_types(), VFILE_BILD_TYPEN)));
    $max = upload_max_bytes();
    $dir = kfile_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        return [0, [sprintf('Der Ordner %s ist nicht beschreibbar.', $dir)]];
    }
    foreach ($_FILES[$feld]['name'] as $i => $name) {
        $name = (string)$name;
        $err = (int)$_FILES[$feld]['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE || $name === '') {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $fehler[] = sprintf('„%s" konnte nicht hochgeladen werden (Fehlercode %d).', $name, $err);
            continue;
        }
        $tmp = (string)$_FILES[$feld]['tmp_name'][$i];
        $groesse = (int)$_FILES[$feld]['size'][$i];
        if (!is_uploaded_file($tmp) && !defined('OVB_TEST_UPLOADS')) {
            $fehler[] = sprintf('„%s" wurde nicht über das Formular hochgeladen.', $name);
            continue;
        }
        $mime = class_exists('finfo') ? (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        $grund = vfile_check($name, $groesse, $mime, 'dokument', $erlaubt, $max);
        if ($grund !== null) {
            $fehler[] = $grund;
            continue;
        }
        $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $basis = date('Ymd_His') . '_' . bin2hex(random_bytes(8));
        $gespeichert = $basis . '.' . $endung;
        $ziel = $dir . DIRECTORY_SEPARATOR . $gespeichert;
        $vorschau = null;
        $istBild = in_array($endung, VFILE_BILD_TYPEN, true) && str_starts_with($mime, 'image/');
        $verarbeitet = $istBild && vfile_process_image($tmp, $ziel, $mime, VFILE_BILD_MAX) !== null;
        if ($verarbeitet) {
            @unlink($tmp);
            $groesse = (int)filesize($ziel);
            $vorschau = $basis . '_vorschau.' . $endung;
            if (vfile_process_image($ziel, $dir . DIRECTORY_SEPARATOR . $vorschau, $mime, VFILE_VORSCHAU_MAX) === null) {
                $vorschau = null;
            }
        } elseif (!(defined('OVB_TEST_UPLOADS') ? rename($tmp, $ziel) : move_uploaded_file($tmp, $ziel))) {
            $fehler[] = sprintf('„%s" konnte nicht gespeichert werden.', $name);
            continue;
        }
        $id = db_insert('calendar_files', [
            'entry_id'    => $entryId,
            'art'         => $istBild ? 'bild' : 'dokument',
            'titel'       => mb_substr(trim($titel) !== '' ? trim($titel) : pathinfo($name, PATHINFO_FILENAME), 0, 200),
            'orig_name'   => mb_substr($name, 0, 255),
            'stored_name' => $gespeichert,
            'thumb_name'  => $vorschau,
            'mime'        => $mime,
            'size_bytes'  => $groesse,
            'uploaded_by' => (int)$user['id'],
        ]);
        if (function_exists('ki_einreihen')) {
            ki_einreihen('termin', $id, $istBild ? 'bild' : 'dokument');
        }
        $anzahl++;
    }
    return [$anzahl, $fehler];
}

/** Wurde im Formular mindestens eine Datei ausgewählt? Reine Funktion über $_FILES. */
function kalender_dateien_mitgeschickt(string $feld): bool
{
    $namen = $_FILES[$feld]['name'] ?? null;
    if (!is_array($namen)) {
        return false;
    }
    foreach ($namen as $n) {
        if ((string)$n !== '') {
            return true;
        }
    }
    return false;
}

function kfile_delete(array $datei): void
{
    foreach ([$datei['stored_name'] ?? '', $datei['thumb_name'] ?? ''] as $name) {
        if ($name) {
            $pfad = kfile_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
            if (is_file($pfad)) {
                @unlink($pfad);
            }
        }
    }
    db_exec('DELETE FROM calendar_files WHERE id = ?', [(int)$datei['id']]);
    if (function_exists('ki_vergessen')) {
        ki_vergessen('termin', (int)$datei['id']);
    }
}

/** Pfad zur Datei oder zur Vorschau – oder null */
function kfile_path(array $datei, bool $vorschau = false): ?string
{
    $name = $vorschau && ($datei['thumb_name'] ?? null) ? $datei['thumb_name'] : $datei['stored_name'];
    $pfad = kfile_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
    return is_file($pfad) ? $pfad : null;
}

/** „Mi 14.10.2026, 19:00–21:00 Uhr" bzw. „Sa 17.10.2026 bis So 18.10.2026, ganztägig". Reine Funktion. */
function kalender_wann_lang(array $t): string
{
    $tage = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    $tag = substr((string)$t['beginn'], 0, 10);
    $ganztag = (int)($t['ganztag'] ?? 0) === 1;
    $text = $tage[(int)date('N', strtotime($tag)) - 1] . ', ' . de_date($tag);
    $endeTag = !empty($t['ende']) ? substr((string)$t['ende'], 0, 10) : $tag;
    if ($endeTag !== $tag) {
        $text .= ' bis ' . $tage[(int)date('N', strtotime($endeTag)) - 1] . ', ' . de_date($endeTag);
    }
    $zeit = kalender_zeit(['ganztag' => $ganztag, 'beginn' => (string)$t['beginn'], 'ende' => $t['ende'] ?? null]);
    if ($ganztag) {
        return $text . ', ganztägig';
    }
    if ($endeTag !== $tag && !empty($t['ende'])) {
        return $text . ', ' . substr((string)$t['beginn'], 11, 5) . ' bis ' . substr((string)$t['ende'], 11, 5) . ' Uhr';
    }
    return $text . ($zeit !== '' ? ', ' . $zeit . ' Uhr' : '');
}

/**
 * Alles, was an einem Termin hängt – nur das, was die Person sehen darf.
 * Rückgabe: ['standorte', 'fahrzeuge', 'auftraege', 'dienststellen', 'kontakte', 'fachgruppen', 'dateien']
 */
function kalender_termin_anhang(array $t, array $u): array
{
    $id = (int)$t['id'];
    $alleStandorte = function_exists('standort_all') ? standort_all() : [];
    $standorte = function_exists('standort_links') ? standort_links('calendar_standorte', 'entry_id', $id) : [];
    foreach ($standorte as &$s) {
        $s['pfad'] = $alleStandorte ? standort_pfad((int)$s['id'], $alleStandorte) : (string)$s['name'];
    }
    unset($s);
    return [
        'standorte'     => $standorte,
        'fahrzeuge'     => can('view_vehicles') ? kalender_fahrzeuge($id) : [],
        'auftraege'     => can('view_vehicles') ? kalender_auftraege($id) : [],
        'dienststellen' => function_exists('kalender_dienststellen') ? kalender_dienststellen($id) : [],
        'kontakte'      => can('view_contacts') ? kalender_kontakte($id) : [],
        'fachgruppen'   => db_all('SELECT li.id, li.label, li.color FROM calendar_fachgruppen cf JOIN list_items li ON li.id = cf.fachgruppe_id WHERE cf.entry_id = ? ORDER BY li.sort_order, li.label', [$id]),
        'dateien'       => kfile_list($id),
    ];
}

/**
 * Wohin das Navi soll: Koordinaten des ersten Platzes mit Lage, sonst der
 * freie Ort als Adresse, sonst die Anschrift der ersten THW-Dienststelle.
 * Rückgabe: ['text' => Beschriftung, 'ziel' => „49.1,9.2" oder Adresse, 'koordinaten' => bool] oder null. Reine Funktion.
 */
function kalender_navi_ziel(array $termin, array $standorte, array $dienststellen = []): ?array
{
    foreach ($standorte as $s) {
        if (($s['geo_lat'] ?? null) !== null && ($s['geo_lng'] ?? null) !== null) {
            return ['text' => (string)($s['pfad'] ?? $s['name']), 'ziel' => sprintf('%.6f,%.6f', (float)$s['geo_lat'], (float)$s['geo_lng']), 'koordinaten' => true];
        }
    }
    $ort = trim((string)($termin['ort'] ?? ''));
    if ($ort !== '') {
        return ['text' => $ort, 'ziel' => $ort, 'koordinaten' => false];
    }
    foreach ($dienststellen as $d) {
        $adresse = implode(', ', array_filter([trim((string)($d['strasse'] ?? '')), trim(trim((string)($d['plz'] ?? '')) . ' ' . trim((string)($d['ort'] ?? '')))], 'strlen'));
        if ($adresse !== '') {
            return ['text' => (string)$d['name'] . ' – ' . $adresse, 'ziel' => $adresse, 'koordinaten' => false];
        }
    }
    return null;
}

/** Adressen für die Karten-Apps: Google Maps (überall), Apple Karten, geo: (Android). Reine Funktion. */
function kalender_navi_links(array $ziel): array
{
    $z = rawurlencode($ziel['ziel']);
    $out = [
        'google' => 'https://www.google.com/maps/dir/?api=1&destination=' . $z,
        'apple'  => 'https://maps.apple.com/?daddr=' . $z,
    ];
    if (!empty($ziel['koordinaten'])) {
        $out['geo'] = 'geo:' . $ziel['ziel'] . '?q=' . $z;
    }
    return $out;
}

/** Schlüssel, mit dem ein Eintrag im Formular wiedergefunden wird. Reine Funktion. */
function kalender_schluessel(array $e): string
{
    return $e['quelle'] . '|' . $e['id'] . '|' . $e['beginn'] . '|' . $e['titel'];
}

/* ==================================================================== */
/* Home Assistant                                                        */
/* ==================================================================== */

/** Kalender, die Home Assistant kennt: [entity_id => name] */
function kalender_ha_liste(): array
{
    $out = [];
    foreach ((array)ha_api('calendars') as $c) {
        if (!empty($c['entity_id'])) {
            $out[(string)$c['entity_id']] = (string)($c['name'] ?? $c['entity_id']);
        }
    }
    ksort($out);
    return $out;
}

/** Ausgewählte Kalender aus der Einstellung */
function kalender_ha_ausgewaehlt(): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string)setting('kalender_ha_entitaeten', ''))),
        static fn($e) => preg_match('/^calendar\.[a-z0-9_]+$/', $e) === 1));
}

/** Eine Antwort von Home Assistant in Zeilen für den Zwischenspeicher. Reine Funktion. */
function kalender_ha_zeilen(string $entity, string $name, array $antwort): array
{
    $out = [];
    foreach ($antwort as $ev) {
        $start = $ev['start'] ?? [];
        $ende = $ev['end'] ?? [];
        $ganztag = isset($start['date']);
        $beginn = $ganztag ? (string)$start['date'] : kalender_ha_zeit((string)($start['dateTime'] ?? ''));
        if ($beginn === '') {
            continue;
        }
        if ($ganztag) {
            // Home Assistant nennt bei ganztägigen den Tag danach als Ende
            $bis = isset($ende['date']) ? date('Y-m-d', strtotime((string)$ende['date']) - 86400) : $beginn;
            $beginn .= ' 00:00:00';
            $bis = max($bis, substr($beginn, 0, 10)) . ' 23:59:59';
        } else {
            $bis = kalender_ha_zeit((string)($ende['dateTime'] ?? '')) ?: null;
        }
        $out[] = [
            'entity_id'     => $entity,
            'kalender_name' => mb_substr($name, 0, 100),
            'uid'           => mb_substr((string)($ev['uid'] ?? md5($entity . $beginn . (string)($ev['summary'] ?? ''))), 0, 190),
            'titel'         => mb_substr((string)($ev['summary'] ?? 'ohne Titel'), 0, 200),
            'beschreibung'  => (string)($ev['description'] ?? ''),
            'ort'           => mb_substr((string)($ev['location'] ?? ''), 0, 150),
            'beginn'        => $beginn,
            'ende'          => $bis,
            'ganztag'       => $ganztag ? 1 : 0,
        ];
    }
    return $out;
}

/** ISO-Zeit mit Zone in Ortszeit Y-m-d H:i:s. Reine Funktion. */
function kalender_ha_zeit(string $iso): string
{
    if ($iso === '') {
        return '';
    }
    try {
        $d = new DateTimeImmutable($iso);
        return $d->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return '';
    }
}

/** Fällig? Abruf alle n Minuten (Einstellung). */
function kalender_ha_due(?int $jetzt = null): bool
{
    if (!kalender_ha_ausgewaehlt()) {
        return false;
    }
    $minuten = max(5, setting_int('kalender_ha_intervall_minuten', 60));
    return ($jetzt ?? time()) - (int)state_get('kalender_ha_letzter_abruf', '0') >= $minuten * 60;
}

/** Alle ausgewählten Kalender holen: 30 Tage zurück, ein Jahr voraus. */
function kalender_ha_sync(?int $jetzt = null): array
{
    $jetzt ??= time();
    $res = ['kalender' => 0, 'eintraege' => 0, 'fehler' => []];
    state_save('kalender_ha_letzter_abruf', (string)$jetzt);
    $von = date('Y-m-d\TH:i:sP', $jetzt - 30 * 86400);
    $bis = date('Y-m-d\TH:i:sP', $jetzt + 365 * 86400);
    $namen = [];
    try {
        $namen = kalender_ha_liste();
    } catch (Throwable $ex) {
        $res['fehler']['Liste'] = $ex->getMessage();
    }
    foreach (kalender_ha_ausgewaehlt() as $entity) {
        try {
            $antwort = ha_api('calendars/' . rawurlencode($entity) . '?start=' . rawurlencode($von) . '&end=' . rawurlencode($bis));
        } catch (Throwable $ex) {
            $res['fehler'][$entity] = mb_substr($ex->getMessage(), 0, 200);
            continue;
        }
        $zeilen = kalender_ha_zeilen($entity, $namen[$entity] ?? $entity, is_array($antwort) ? $antwort : []);
        db_exec('DELETE FROM calendar_ha WHERE entity_id = ?', [$entity]);
        foreach ($zeilen as $z) {
            db_insert('calendar_ha', $z);
        }
        $res['kalender']++;
        $res['eintraege'] += count($zeilen);
    }
    state_save('kalender_ha_fehler', (string)json_encode($res['fehler'], JSON_UNESCAPED_UNICODE));
    return $res;
}
