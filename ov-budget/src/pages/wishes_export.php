<?php
declare(strict_types=1);

$user = current_user();

$rows = wish_query([
    'q'                => get_str('q'),
    'status_id'        => get_int('status_id'),
    'fachgruppe_id'    => get_int('fachgruppe_id'),
    'kategorie_id'     => get_int('kategorie_id'),
    'dringlichkeit_id' => get_int('dringlichkeit_id'),
    'budget_id'        => get_int('budget_id'),
    'sort'             => get_str('sort', 'prio'),
    'offen'            => get_str('alle') === '1' ? 0 : 1,
    'mine'             => get_str('mine') === '1' ? (int)$user['id'] : null,
]);

$file = 'wuensche_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $file . '"');

$out = fopen('php://output', 'wb');
// BOM, damit Excel die Umlaute richtig anzeigt
fwrite($out, "\xEF\xBB\xBF");

$head = [
    'ID', 'Bezeichnung', 'Fachgruppe', 'Kategorie', 'Dringlichkeit', 'Status',
    'Anzahl', 'Einheit', 'Netto Einzel', 'Netto Gesamt', 'MwSt %', 'Nice to have',
    'Priorität', 'Stimmen', 'Benötigt bis', 'Budget', 'Lieferant', 'Artikelnummer',
    'Antragsteller', 'Quelle', 'Anlagen', 'Angelegt am', 'Begründung',
];
fputcsv($out, $head, ';');

foreach ($rows as $w) {
    fputcsv($out, [
        $w['id'],
        $w['bezeichnung'],
        $w['fachgruppe_label'],
        $w['kategorie_label'],
        $w['dring_label'],
        $w['status_label'],
        number_format((float)$w['anzahl'], 2, ',', ''),
        $w['einheit_label'],
        number_format((float)$w['netto_einzel'], 2, ',', ''),
        number_format((float)$w['netto_gesamt'], 2, ',', ''),
        number_format((float)$w['mwst_satz'], 2, ',', ''),
        (int)$w['nice_to_have'] ? 'ja' : 'nein',
        $w['prioritaet'],
        $w['votes'],
        de_date($w['benoetigt_bis']),
        $w['budget_name'] ? $w['budget_jahr'] . ' ' . $w['budget_name'] : '',
        $w['lieferant'],
        $w['artikelnummer'],
        $w['antragsteller'],
        $w['source'],
        $w['anlagen'],
        de_datetime($w['created_at']),
        preg_replace('/\s+/', ' ', (string)$w['begruendung']),
    ], ';');
}
fclose($out);
audit('wunsch.export', 'wish', null, count($rows) . ' Zeilen');
exit;
