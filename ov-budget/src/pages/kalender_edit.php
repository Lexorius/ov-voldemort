<?php
declare(strict_types=1);

$user = current_user();
$id = get_int('id');
$termin = $id ? kalender_find($id) : null;

if ($id && !$termin) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Termin gibt es nicht (mehr).']);
    return;
}
if ($termin && !kalender_darf_bearbeiten($termin, $user)) {
    if (!kalender_sichtbar($termin, $user)) {
        http_response_code(403);
        render('error', ['title' => 'Kein Zugriff', 'message' => 'Dieser Termin ist nicht für dich.']);
        return;
    }
    $nurLesen = true;
} else {
    $nurLesen = false;
}
if (!$termin && !can('create_kalender')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Termine dürfen derzeit nur von der Leitung angelegt werden.']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$nurLesen) {
    if (post_str('action') === 'delete' && $termin) {
        kalender_delete($termin);
        flash('success', 'Termin gelöscht.');
        redirect_route('kalender', ['monat' => substr((string)$termin['beginn'], 0, 7)]);
    }
    // Mülleimer an einer Datei: nur die Datei weg, die übrigen Eingaben bleiben unangetastet
    if (post_int('datei_weg') && $termin) {
        $datei = kfile_find((int)post_int('datei_weg', 0));
        if ($datei && (int)$datei['entry_id'] === (int)$termin['id']) {
            kfile_delete($datei);
            audit('termin.datei_weg', 'calendar', (int)$termin['id'], (string)$datei['orig_name']);
            flash('success', 'Datei entfernt.');
        }
        redirect(url('kalender_edit', ['id' => $termin['id']]) . '#dateien');
    }
    [$neu, $errors] = kalender_save_from_post($termin, $user);
    if ($neu) {
        flash('success', $termin ? 'Termin gespeichert.' : 'Termin eingetragen.');
        // Mitgeschickte Dateien gleich anhängen – auch beim Anlegen
        if (kalender_dateien_mitgeschickt('dateien')) {
            [$anzahl, $dateiFehler] = kfile_store_uploads($neu, 'dateien', post_str('datei_titel'), $user);
            if ($anzahl > 0) {
                flash('success', sprintf('%d Datei(en) angehängt.', $anzahl));
                audit('termin.datei', 'calendar', $neu, sprintf('%d Datei(en)', $anzahl));
            }
            foreach ($dateiFehler as $f) {
                flash('error', e($f));
            }
        }
        // Doppelbuchung? Dann warnen, aber speichern
        $gespeichert = kalender_find($neu);
        $gewaehlteFahrzeuge = can('view_vehicles') ? wahl_ids('vehicles') : [];
        if ($gespeichert && $gewaehlteFahrzeuge) {
            $konflikte = kalender_fahrzeug_konflikte($gewaehlteFahrzeuge, (string)$gespeichert['beginn'], (string)($gespeichert['ende'] ?: $gespeichert['beginn']), $neu);
            if ($konflikte) {
                flash('warn', e(kalender_konflikt_text($konflikte, db_all('SELECT id, bezeichnung FROM vehicles WHERE is_active = 1'))));
            }
        }
        redirect_route('kalender', ['monat' => substr((string)post_date('datum'), 0, 7)]);
    }
    $termin = array_merge($termin ?? [], $_POST, ['id' => $termin['id'] ?? null]);
}

// Aus einem Auftrag heraus: Werkstatttermin mit Fahrzeug und Auftrag vorbelegen
$auftragVorlage = !$termin && get_int('auftrag_id') && can('view_vehicles') && function_exists('order_find') ? order_find((int)get_int('auftrag_id')) : null;

if (!$termin) {
    $datum = get_str('datum');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
        $datum = date('Y-m-d');
    }
    $termin = [
        'id' => null, 'titel' => '', 'beschreibung' => '', 'ort' => '', 'datum' => $datum, 'zeit' => '',
        'ende_datum' => '', 'ende_zeit' => '', 'ganztag' => 0, 'ziel' => 'user', 'ziel_id' => (int)$user['id'], 'farbe' => '',
    ];
    if ($auftragVorlage) {
        $termin['titel'] = mb_substr('Werkstatt: ' . $auftragVorlage['titel'] . ' (' . $auftragVorlage['fahrzeug'] . ')', 0, 200);
        $termin['ort'] = (string)($auftragVorlage['werkstatt'] ?? '');
    }
} elseif (!isset($termin['datum'])) {
    // aus der Datenbank: Beginn und Ende in Formularfelder zerlegen
    $termin['datum'] = substr((string)$termin['beginn'], 0, 10);
    $termin['zeit'] = (int)$termin['ganztag'] === 1 ? '' : substr((string)$termin['beginn'], 11, 5);
    $termin['ende_datum'] = $termin['ende'] ? substr((string)$termin['ende'], 0, 10) : '';
    $termin['ende_zeit'] = $termin['ende'] && (int)$termin['ganztag'] !== 1 ? substr((string)$termin['ende'], 11, 5) : '';
}

$gewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$nurLesen) {
    $gewaehlt = wahl_ids('vehicles');
} elseif (!empty($termin['id'])) {
    $gewaehlt = array_map('intval', array_column(kalender_fahrzeuge((int)$termin['id']), 'id'));
} elseif (get_int('vehicle_id')) {
    $gewaehlt = [(int)get_int('vehicle_id')];
} elseif ($auftragVorlage) {
    $gewaehlt = [(int)$auftragVorlage['vehicle_id']];
}
$auftraegeGewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$nurLesen) {
    $auftraegeGewaehlt = wahl_ids('auftraege');
} elseif (!empty($termin['id']) && can('view_vehicles')) {
    $auftraegeGewaehlt = array_map('intval', array_column(kalender_auftraege((int)$termin['id']), 'id'));
} elseif ($auftragVorlage) {
    $auftraegeGewaehlt = [(int)$auftragVorlage['id']];
}
// Zur Wahl: offene Aufträge aller Fahrzeuge, dazu die schon verknüpften (auch wenn erledigt)
$auftragOptionen = [];
if (can('view_vehicles') && function_exists('order_query')) {
    $liste = order_query(['offen' => 1]);
    $da = array_map('intval', array_column($liste, 'id'));
    foreach ($auftraegeGewaehlt as $oid) {
        if (!in_array($oid, $da, true) && ($o = order_find($oid))) {
            $liste[] = $o;
        }
    }
    foreach ($liste as $o) {
        $auftragOptionen[] = ['id' => (int)$o['id'], 'text' => $o['nummer'] . ' ' . $o['titel'], 'sub' => (string)($o['status_label'] ?? ''), 'gruppe' => (string)$o['fahrzeug']];
    }
}

$standorteGewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$nurLesen) {
    $standorteGewaehlt = wahl_ids('standorte');
} elseif (!empty($termin['id'])) {
    $standorteGewaehlt = array_map('intval', array_column(standort_links('calendar_standorte', 'entry_id', (int)$termin['id']), 'id'));
} elseif (get_int('standort_id')) {
    $standorteGewaehlt = [(int)get_int('standort_id')];
}

$fahrzeuge = can('view_vehicles')
    ? db_all('SELECT v.id, v.bezeichnung, v.funkrufname, v.kennzeichen, v.sitzplaetze, v.zuladung_kg, t.slug AS typ_slug, t.label AS typ_label
              FROM vehicles v LEFT JOIN list_items t ON t.id = v.typ_id WHERE v.is_active = 1 ORDER BY v.bezeichnung')
    : [];
// Welche Fahrzeuge zur gewählten Zeit schon in einem anderen Termin stecken
$zeitraum = kalender_zeitraum_aus_feldern($termin);
$belegt = $fahrzeuge && $zeitraum ? kalender_fahrzeug_konflikte(array_column($fahrzeuge, 'id'), $zeitraum[0], $zeitraum[1], (int)($termin['id'] ?? 0)) : [];

$fachgruppenGewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$nurLesen) {
    $fachgruppenGewaehlt = wahl_ids('fachgruppen');
} elseif (!empty($termin['id'])) {
    $fachgruppenGewaehlt = array_map('intval', array_column(db_all('SELECT fachgruppe_id FROM calendar_fachgruppen WHERE entry_id = ? ORDER BY fachgruppe_id', [(int)$termin['id']]), 'fachgruppe_id'));
}
// Zur Wahl: Leitung alle Fachgruppen, Mitglieder nur die eigene
$fachgruppenOptionen = [];
foreach (list_items('fachgruppe') as $fg) {
    if (kalender_darf_ziel('fachgruppe', (int)$fg['id'], $user) || in_array((int)$fg['id'], $fachgruppenGewaehlt, true)) {
        $fachgruppenOptionen[] = ['id' => (int)$fg['id'], 'text' => (string)$fg['label'], 'sub' => '', 'gruppe' => ''];
    }
}

// Kontakte: nur, wer sie sehen darf – zur Wahl alle aktiven, dazu die schon verknüpften
$kontakte = [];
$kontakteGewaehlt = [];
$kontaktOptionen = [];
if (can('view_contacts') && function_exists('contact_query')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$nurLesen) {
        $kontakteGewaehlt = wahl_ids('kontakte');
    } elseif (!empty($termin['id'])) {
        $kontakte = kalender_kontakte((int)$termin['id']);
        $kontakteGewaehlt = array_map('intval', array_column($kontakte, 'id'));
    }
    $liste = contact_query(['limit' => 1000]);
    $da = array_map('intval', array_column($liste, 'id'));
    foreach ($kontakteGewaehlt as $cid) {
        if (!in_array($cid, $da, true) && ($c = contact_find($cid))) {
            $liste[] = $c;
        }
    }
    foreach ($liste as $c) {
        $sub = implode(' · ', array_filter([trim((string)($c['organisation'] ?? '')) !== '' && trim(($c['vorname'] ?? '') . ($c['nachname'] ?? '')) !== '' ? (string)$c['organisation'] : '', (string)($c['position'] ?? '')]));
        $kontaktOptionen[] = ['id' => (int)$c['id'], 'text' => contact_name($c), 'sub' => $sub, 'gruppe' => ''];
    }
}

// THW-Dienststellen: in oder mit welchem OV der Termin stattfindet
$dienststellenGewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$nurLesen) {
    $dienststellenGewaehlt = wahl_ids('dienststellen');
} elseif (!empty($termin['id'])) {
    $dienststellenGewaehlt = array_map('intval', array_column(kalender_dienststellen((int)$termin['id']), 'id'));
}
$dienststellenOptionen = function_exists('thw_optionen') ? thw_optionen(thw_all(), $dienststellenGewaehlt) : [];

render('kalender_edit', [
    'dienststellenGewaehlt' => $dienststellenGewaehlt,
    'dienststellenOptionen' => $dienststellenOptionen,
    'kontakte'         => $kontakte,
    'kontakteGewaehlt' => $kontakteGewaehlt,
    'kontaktOptionen'  => $kontaktOptionen,
    'auftraegeGewaehlt' => $auftraegeGewaehlt,
    'auftragOptionen'   => $auftragOptionen,
    'dateien' => !empty($termin['id']) ? kfile_list((int)$termin['id']) : [],
    'fachgruppenGewaehlt' => $fachgruppenGewaehlt,
    'fachgruppenOptionen' => $fachgruppenOptionen,
    'standorteGewaehlt' => $standorteGewaehlt,
    'fahrzeuge' => $fahrzeuge,
    'fahrzeugBloecke' => kalender_fahrzeuge_bloecke($fahrzeuge),
    'belegt'    => $belegt,
    'gewaehlt'  => $gewaehlt,
    'standorte' => function_exists('standort_all') ? standort_all() : [],
    'title'   => $termin['id'] ? ($nurLesen ? 'Termin' : 'Termin bearbeiten') : 'Termin eintragen',
    'termin'  => $termin,
    'errors'  => $errors,
    'besprochen' => !empty($termin['id']) && can('view_meetings') && function_exists('tps_fuer') ? tps_fuer('termin', (int)$termin['id']) : [],
    'nurLesen' => $nurLesen,
    'user'    => $user,
    'leitung' => in_array((string)$user['role'], ['admin', 'leitung'], true),
    'users'   => db_all('SELECT id, display_name, username FROM users WHERE is_active = 1 ORDER BY display_name'),
]);
