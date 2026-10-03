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
    return match ((string)$t['ziel']) {
        'ov'         => true,
        'fachgruppe' => (int)$t['ziel_id'] === (int)($u['fachgruppe_id'] ?? 0),
        'funktion'   => in_array((int)$t['ziel_id'], $u['functions'] ?? [], true),
        'user'       => (int)$t['ziel_id'] === (int)$u['id'],
        default      => false,
    };
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

/** Was ein Termin „für" wen ist – zum Anzeigen. */
function kalender_ziel_name(array $t): string
{
    return match ((string)$t['ziel']) {
        'ov'         => 'Ortsverband',
        'fachgruppe' => 'Fachgruppe ' . list_label((int)$t['ziel_id'], '?'),
        'funktion'   => list_label((int)$t['ziel_id'], '?'),
        'user'       => (string)($t['ziel_name'] ?? 'persönlich'),
        default      => '',
    };
}

function kalender_find(int $id): ?array
{
    return $id > 0 ? db_row(
        'SELECT k.*, u.display_name AS ziel_name, e.display_name AS erfasser
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
        'SELECT k.*, u.display_name AS ziel_name
         FROM calendar_entries k
         LEFT JOIN users u ON u.id = k.ziel_id AND k.ziel = \'user\'
         WHERE k.beginn < ? AND COALESCE(k.ende, k.beginn) >= ?
         ORDER BY k.beginn, k.id',
        [$bis . ' 23:59:59', $von . ' 00:00:00']
    );
    return array_values(array_filter($rows, static fn($t) => kalender_sichtbar($t, $u)));
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
    return [$id, []];
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

    if (in_array('termin', $quellen, true)) {
        foreach (kalender_termine($von, $bis, $u) as $t) {
            $out[] = kalender_eintrag('termin', (string)$t['titel'], (string)$t['beginn'], $t['ende'], [
                'ganztag' => (int)$t['ganztag'] === 1, 'ort' => $t['ort'], 'id' => $t['id'],
                'url' => url('kalender_edit', ['id' => $t['id']]), 'farbe' => $t['farbe'] ?: KALENDER_QUELLEN['termin']['color'],
                'untertitel' => kalender_ziel_name($t),
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
        foreach (db_all("SELECT e.id, e.titel, e.beginn, e.ende, e.ort, t.color AS typ_color, t.label AS typ_label
                         FROM events e LEFT JOIN list_items t ON t.id = e.typ_id
                         WHERE e.beginn <= ? AND COALESCE(e.ende, e.beginn) >= ? AND e.status <> 'abgesagt'", [$bis . ' 23:59:59', $von . ' 00:00:00']) as $e) {
            $out[] = kalender_eintrag('veranstaltung', (string)$e['titel'], (string)$e['beginn'], $e['ende'], ['ort' => $e['ort'], 'id' => $e['id'],
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
            $out[] = kalender_eintrag('aufgabe', 'Fällig: ' . $t['titel'], (string)$t['faellig_am'], null, ['id' => $t['id'],
                'url' => url('todo', ['id' => $t['id']]), 'untertitel' => function_exists('todo_target_name') ? todo_target_name($t) : '']);
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
            foreach (db_all('SELECT id, bezeichnung, vertrag_bis FROM sims WHERE is_active = 1 AND vertrag_bis BETWEEN ? AND ?', [$von, $bis]) as $s) {
                $out[] = kalender_eintrag('frist', 'Vertrag endet: ' . $s['bezeichnung'], (string)$s['vertrag_bis'], null,
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
    usort($out, static fn($a, $b) => [$a['beginn'], $a['quelle'], $a['titel']] <=> [$b['beginn'], $b['quelle'], $b['titel']]);
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
    return $id;
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
