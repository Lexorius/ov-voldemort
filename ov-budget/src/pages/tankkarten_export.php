<?php
declare(strict_types=1);

/** Tankkarten als CSV – Bestandsliste fürs Archiv oder die Verwaltung */

// Die PIN steht in der Datei; die bleibt bei der Leitung
if (!can('manage_tankkarten')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Bestandsliste gibt es nur für die Leitung.']);
    return;
}

$karten = tankkarte_query([
    'q'           => get_str('q'),
    'anbieter_id' => get_int('anbieter_id'),
    'status_id'   => get_int('status_id'),
    'art'         => get_str('art'),
    'aktiv'       => get_str('aktiv') === 'alle' ? 'alle' : 'aktiv',
    'sort'        => get_str('sort'),
]);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="tankkarten_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");   // BOM, damit Excel die Umlaute richtig anzeigt

fputcsv($out, csv_sicher([
    'Anbieter', 'Kartennummer', 'Art', 'Status', 'Fahrzeuge', 'Bei Person', 'Aufbewahrung',
    'Kundennummer', 'Gueltig bis', 'Ausgegeben am', 'PIN', 'Im Bestand', 'Notiz',
]), ';');

foreach ($karten as $k) {
    fputcsv($out, csv_sicher([
        (string)($k['anbieter_label'] ?? ''),
        (string)$k['nummer'],
        TANKKARTE_ARTEN[(string)$k['art']] ?? '',
        (string)($k['status_label'] ?? ''),
        tankkarte_fahrzeuge_text($k),
        (string)($k['person_label'] ?? ''),
        (string)$k['aufbewahrung'],
        (string)$k['kundennummer'],
        $k['gueltig_bis'] ? de_date((string)$k['gueltig_bis']) : '',
        $k['ausgegeben_am'] ? de_date((string)$k['ausgegeben_am']) : '',
        (string)$k['pin'],
        (int)$k['is_active'] === 1 ? 'ja' : 'nein',
        preg_replace('/\s+/', ' ', (string)($k['notiz'] ?? '')),
    ]), ';');
}

fclose($out);
audit('tankkarte.export', 'tankkarte', null, count($karten) . ' Zeilen');
exit;
