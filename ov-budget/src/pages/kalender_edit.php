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
    [$neu, $errors] = kalender_save_from_post($termin, $user);
    if ($neu) {
        flash('success', $termin ? 'Termin gespeichert.' : 'Termin eingetragen.');
        // Doppelbuchung? Dann warnen, aber speichern
        $gespeichert = kalender_find($neu);
        $gewaehlteFahrzeuge = can('view_vehicles') ? array_map('intval', (array)($_POST['vehicles'] ?? [])) : [];
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

if (!$termin) {
    $datum = get_str('datum');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
        $datum = date('Y-m-d');
    }
    $termin = [
        'id' => null, 'titel' => '', 'beschreibung' => '', 'ort' => '', 'datum' => $datum, 'zeit' => '',
        'ende_datum' => '', 'ende_zeit' => '', 'ganztag' => 0, 'ziel' => 'user', 'ziel_id' => (int)$user['id'], 'farbe' => '',
    ];
} elseif (!isset($termin['datum'])) {
    // aus der Datenbank: Beginn und Ende in Formularfelder zerlegen
    $termin['datum'] = substr((string)$termin['beginn'], 0, 10);
    $termin['zeit'] = (int)$termin['ganztag'] === 1 ? '' : substr((string)$termin['beginn'], 11, 5);
    $termin['ende_datum'] = $termin['ende'] ? substr((string)$termin['ende'], 0, 10) : '';
    $termin['ende_zeit'] = $termin['ende'] && (int)$termin['ganztag'] !== 1 ? substr((string)$termin['ende'], 11, 5) : '';
}

$gewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$nurLesen) {
    $gewaehlt = array_map('intval', (array)($_POST['vehicles'] ?? []));
} elseif (!empty($termin['id'])) {
    $gewaehlt = array_map('intval', array_column(kalender_fahrzeuge((int)$termin['id']), 'id'));
} elseif (get_int('vehicle_id')) {
    $gewaehlt = [(int)get_int('vehicle_id')];
}

$standorteGewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$nurLesen) {
    $standorteGewaehlt = array_map('intval', (array)($_POST['standorte'] ?? []));
} elseif (!empty($termin['id'])) {
    $standorteGewaehlt = array_map('intval', array_column(standort_links('calendar_standorte', 'entry_id', (int)$termin['id']), 'id'));
} elseif (get_int('standort_id')) {
    $standorteGewaehlt = [(int)get_int('standort_id')];
}

$fahrzeuge = can('view_vehicles')
    ? db_all('SELECT v.id, v.bezeichnung, v.funkrufname, v.kennzeichen, t.slug AS typ_slug, t.label AS typ_label
              FROM vehicles v LEFT JOIN list_items t ON t.id = v.typ_id WHERE v.is_active = 1 ORDER BY v.bezeichnung')
    : [];
// Welche Fahrzeuge zur gewählten Zeit schon in einem anderen Termin stecken
$zeitraum = kalender_zeitraum_aus_feldern($termin);
$belegt = $fahrzeuge && $zeitraum ? kalender_fahrzeug_konflikte(array_column($fahrzeuge, 'id'), $zeitraum[0], $zeitraum[1], (int)($termin['id'] ?? 0)) : [];

render('kalender_edit', [
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
