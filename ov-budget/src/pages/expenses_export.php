<?php
declare(strict_types=1);

if (!can('view_expenses')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Buchungen sind nur für die Leitung sichtbar.']);
    return;
}

$jahr = get_int('jahr') ?: setting_int('haushaltsjahr', (int)date('Y'));
$art = buchungsart(get_str('art', 'ausgabe'));

$rows = expense_query([
    'jahr'          => $jahr,
    'art'           => $art,
    'q'             => get_str('q'),
    'kategorie_id'  => get_int('kategorie_id'),
    'fachgruppe_id' => get_int('fachgruppe_id'),
    'budget_id'     => get_int('budget_id'),
    'von'           => get_str('von'),
    'bis'           => get_str('bis'),
    'sort'          => get_str('sort', 'alt'),
]);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'
    . ($art === 'einnahme' ? 'einnahmen' : 'ausgaben') . '_' . $jahr . '_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");   // BOM, damit Excel die Umlaute richtig anzeigt

$istEinnahme = $art === 'einnahme';

fputcsv($out, [
    'ID', 'Art', 'Datum', 'Jahr', 'Bezeichnung', 'Kategorie', 'Fachgruppe', 'Budgettopf',
    'Netto', 'MwSt %', 'Brutto',
    $istEinnahme ? 'Auftraggeber' : 'Lieferant',
    $istEinnahme ? 'Rechnungsnummer' : 'Belegnummer',
    $istEinnahme ? 'Einsatz-/Auftragsnummer' : 'Referenz',
    $istEinnahme ? 'Eingegangen am' : 'Bezahlt am',
    'Wunsch', 'Erfasst von', 'Erfasst am', 'Notiz',
], ';');

$summeNetto = 0.0;
$summeBrutto = 0.0;

foreach ($rows as $r) {
    $summeNetto += (float)$r['betrag_netto'];
    $summeBrutto += (float)$r['betrag_brutto'];
    fputcsv($out, [
        $r['id'],
        $r['art'],
        de_date($r['datum']),
        $r['jahr'],
        $r['bezeichnung'],
        $r['kategorie_label'],
        $r['fachgruppe_label'],
        $r['budget_name'],
        number_format((float)$r['betrag_netto'], 2, ',', ''),
        number_format((float)$r['mwst_satz'], 2, ',', ''),
        number_format((float)$r['betrag_brutto'], 2, ',', ''),
        $r['lieferant'],
        $r['beleg_nr'],
        $r['referenz'],
        de_date($r['bezahlt_am']),
        $r['wunsch_bezeichnung'],
        $r['erfasser'],
        de_datetime($r['created_at']),
        preg_replace('/\s+/', ' ', (string)$r['notiz']),
    ], ';');
}

fputcsv($out, [
    '', '', '', '', 'Summe (' . count($rows) . ' ' . BUCHUNGSARTEN[$art] . ')', '', '', '',
    number_format($summeNetto, 2, ',', ''), '',
    number_format($summeBrutto, 2, ',', ''),
], ';');

fclose($out);
audit($art . '.export', 'expense', null, count($rows) . ' Zeilen');
exit;
