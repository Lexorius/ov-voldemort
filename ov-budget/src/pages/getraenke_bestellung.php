<?php
declare(strict_types=1);

/** Eine Bestellung: Positionen, Mailtext, Lieferung, Abrechnung */
$user = require_login();
if (!can('view_getraenke')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Das Getränkemodul ist für dich nicht freigegeben.']);
    return;
}

$b = getraenke_bestellung_find(get_int('id'));
if ($b === null) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Bestellung gibt es nicht (mehr).']);
    return;
}
$positionen = getraenke_positionen((int)$b['id']);
$lieferant = getraenke_lieferant_find((int)($b['lieferant_id'] ?? 0));
$standort = (int)($b['standort_id'] ?? 0) > 0 ? standort_find((int)$b['standort_id']) : null;

// Vorschau des Mailtextes, solange nichts verschickt ist
$vorschau = (string)($b['mail_text'] ?? '');
if ($vorschau === '' && $lieferant !== null) {
    $vorschau = getraenke_bestellung_text($b, $positionen, $lieferant, $standort, [
        'ov_name'           => (string)setting('ov_name', ''),
        'besteller'         => (string)($user['display_name'] ?? $user['username'] ?? ''),
        'besteller_email'   => (string)($user['email'] ?? ''),
        'besteller_telefon' => (string)($user['phone'] ?? ''),
        'lieferadresse'     => (string)setting('getraenke_lieferadresse', ''),
        'hinweis'           => (string)setting('getraenke_bestell_hinweis', ''),
    ]);
}

render('getraenke_bestellung', [
    'title'       => 'Bestellung ' . (string)$b['nummer'],
    'b'           => $b,
    'positionen'  => $positionen,
    'lieferant'   => $lieferant,
    'standort'    => $standort,
    'summe'       => getraenke_bestellung_summe($positionen),
    'vorschau'    => $vorschau,
    'plaetze'     => standort_all(true),
    'lagerplaetze' => getraenke_lagerplaetze(),
    'kategorien'  => list_options(buchung_list_key('ausgabe'), null, '– Kategorie wählen –'),
    'darf'        => can('manage_getraenke'),
    'darfBudget'  => can('manage_budget'),
    'mailAktiv'   => mail_eingerichtet(),
    'expense'     => (int)($b['expense_id'] ?? 0) > 0 ? expense_find((int)$b['expense_id']) : null,
]);
