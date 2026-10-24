<?php
declare(strict_types=1);

/** Antworten einer Abstimmung als CSV */
$user = require_login();
if (!can('view_umfragen')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Abstimmungen sind nur für die Leitung sichtbar.']);
    return;
}
$umfrage = umfrage_find(get_int('id'));
if ($umfrage === null) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Abstimmung gibt es nicht (mehr).']);
    return;
}
$csv = umfrage_csv(umfrage_seiten($umfrage), umfrage_antworten($umfrage));
$name = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$umfrage['titel']) ?: 'abstimmung';
audit('umfrage.export', 'umfrage', (int)$umfrage['id'], 'csv');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="abstimmung_' . trim($name, '_') . '_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');
echo $csv;
