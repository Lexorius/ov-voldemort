<?php
declare(strict_types=1);

$user = require_role('admin', 'leitung');

$id = get_int('id');
$expense = $id ? expense_find($id) : null;

// Beim Bearbeiten entscheidet der Datensatz, beim Anlegen die Adresszeile
$art = buchungsart($expense['art'] ?? get_str('art', 'ausgabe'));

if ($id && !$expense) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Buchung gibt es nicht (mehr).']);
    return;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $expense) {
        db_exec('DELETE FROM expenses WHERE id = ?', [$expense['id']]);
        audit($art . '.geloescht', 'expense', (int)$expense['id'], $expense['bezeichnung']);
        flash('success', 'Buchung gelöscht.');
        redirect_route('expenses', ['jahr' => $expense['jahr'], 'art' => $art]);
    }

    [$newId, $errors] = expense_save_from_post($expense, $user);
    if ($newId) {
        $wort = $art === 'einnahme' ? 'Einnahme' : 'Ausgabe';
        flash('success', $wort . ($expense ? ' gespeichert.' : ' erfasst.'));
        $ziel = post_int('jahr') ?: (int)date('Y');
        if (post_str('weiter') === '1') {
            flash('info', 'Nächste ' . $wort . ' eintragen.');
            redirect_route('expense_edit', ['jahr' => $ziel, 'art' => $art]);
        }
        redirect_route('expenses', ['jahr' => $ziel, 'art' => $art]);
    }
    $expense = array_merge($expense ?? [], $_POST, ['id' => $expense['id'] ?? null]);
}

if (!$expense) {
    $jahr = get_int('jahr') ?: haushaltsjahr();
    $expense = [
        'id' => null,
        'art' => $art,
        'jahr' => $jahr,
        // Bei einem vergangenen Haushaltsjahr nicht das heutige Datum vorschlagen
        'datum' => $jahr === (int)date('Y') ? date('Y-m-d') : $jahr . '-01-01',
        'bezeichnung' => '', 'beschreibung' => '',
        'kategorie_id' => null, 'fachgruppe_id' => null,
        'budget_id' => get_int('budget_id'), 'wish_id' => get_int('wish_id'),
        'event_id' => get_int('event_id'), 'bestellung_id' => get_int('bestellung_id'),
        'betrag_brutto' => '', 'betrag_netto' => '',
        'lieferant' => '', 'beleg_nr' => '', 'referenz' => '',
        'bezahlt_am' => null, 'notiz' => '',
        'status' => buchung_status(get_str('status', 'bezahlt')),
        'abgerechnet_am' => null, 'abgerechnet_betrag' => null, 'gestellt_am' => null, 'gestellt_betrag' => null,
        'gestellt_nr' => '', 'zugesagt_am' => null, 'zugesagt_betrag' => null,
    ];
    // Vorbelegung aus der Adresse, etwa „Verpflegung als geplante Ausgabe" von der Veranstaltung
    if (get_str('bezeichnung') !== '') {
        $expense['bezeichnung'] = mb_substr(get_str('bezeichnung'), 0, 200);
    }
    if (get_str('betrag') !== '') {
        $expense['betrag_brutto'] = round((float)str_replace(',', '.', get_str('betrag')), 2);
    }
    if (get_str('datum') !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', get_str('datum'))) {
        $expense['datum'] = get_str('datum');
        $expense['jahr'] = (int)substr(get_str('datum'), 0, 4);
    }
}

// Bezüge: gespeicherte Verknüpfungen, beim Anlegen aus Adresse oder Bestellung
$links = !empty($expense['id']) ? expense_links((int)$expense['id']) : ['wish' => [], 'vehicle' => []];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $links = ['wish' => array_map('intval', (array)($_POST['wishes'] ?? [])), 'vehicle' => array_map('intval', (array)($_POST['vehicles'] ?? []))];
} elseif (empty($expense['id'])) {
    if (get_int('wish_id')) {
        $links['wish'][] = (int)get_int('wish_id');
    }
    if (get_int('vehicle_id')) {
        $links['vehicle'][] = (int)get_int('vehicle_id');
    }
    $bestellung = get_int('bestellung_id') ? bestellung_find((int)get_int('bestellung_id')) : null;
    if ($bestellung) {
        $expense['bestellung_id'] = (int)$bestellung['id'];
        $expense['lieferant'] = (string)$bestellung['lieferant'];
        $expense['bezeichnung'] = 'Rechnung Bestellung ' . $bestellung['nummer'] . ' · ' . $bestellung['lieferant'];
        $expense['betrag_brutto'] = (float)$bestellung['summe'];
        $expense['referenz'] = (string)$bestellung['bestell_nr'];
        foreach (bestellung_wuensche((int)$bestellung['id']) as $w) {
            $links['wish'][] = (int)$w['id'];
            if ($w['vehicle_id']) {
                $links['vehicle'][] = (int)$w['vehicle_id'];
            }
            if (empty($expense['budget_id']) && $w['budget_id']) {
                $expense['budget_id'] = (int)$w['budget_id'];
            }
        }
    }
}
$links['wish'] = array_values(array_unique($links['wish']));
$links['vehicle'] = array_values(array_unique($links['vehicle']));
// Wünsche zur Auswahl: freigegeben, bestellt, beschafft – und alle schon verknüpften
$wunschIn = $links['wish'] ? ' OR w.id IN (' . implode(',', array_map('intval', $links['wish'])) . ')' : '';
$wort = $art === 'einnahme' ? 'Einnahme' : 'Ausgabe';
$stichtag = $art === 'ausgabe' ? budget_stichtag_info(budget_year((int)$expense['jahr'])['stichtag'] ?? null) : ['stichtag' => null, 'tage' => null, 'gesperrt' => false];

render('expense_edit', [
    'stichtag' => $stichtag,
    'title'   => $wort . ($expense['id'] ? ' bearbeiten' : ' erfassen'),
    'art'     => $art,
    'expense' => $expense,
    'errors'  => $errors,
    'budgets' => db_all('SELECT id, jahr, name FROM budgets WHERE is_active = 1 ORDER BY jahr DESC, name'),
    'wishes'  => db_all(
        "SELECT w.id, w.bezeichnung, w.netto_gesamt, st.slug AS status_slug, st.label AS status_label
         FROM wishes w LEFT JOIN list_items st ON st.id = w.status_id
         WHERE st.slug IN ('freigegeben','bestellt','beschafft')" . $wunschIn . "
         ORDER BY FIELD(st.slug,'bestellt','freigegeben','beschafft'), w.updated_at DESC LIMIT 300"
    ),
    'vehicles' => can('view_vehicles') ? db_all('SELECT id, bezeichnung, kennzeichen FROM vehicles WHERE is_active = 1 ORDER BY bezeichnung') : [],
    'bestellungen' => db_all(bestellung_select() . " WHERE b.status IN ('bestellt','geliefert')" . (!empty($expense['bestellung_id']) ? ' OR b.id = ' . (int)$expense['bestellung_id'] : '') . ' ORDER BY b.bestellt_am DESC'),
    'links'   => $links,
    'events'  => db_all('SELECT id, titel, beginn FROM events ORDER BY beginn DESC LIMIT 200'),
]);
