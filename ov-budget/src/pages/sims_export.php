<?php
declare(strict_types=1);

/** SIM-Karten als CSV – Bestandsliste fürs Archiv oder die Verwaltung */

// PIN und PUK stehen in der Datei; die bleibt bei der Leitung
if (!can('manage_sims')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Bestandsliste gibt es nur für die Leitung.']);
    return;
}

$sims = sim_query([
    'q'         => get_str('q'),
    'karte_art' => get_str('karte_art'),
    'typ_id'    => get_int('typ_id'),
    'status_id' => get_int('status_id'),
    'ziel_typ'  => get_str('ziel_typ'),
    'aktiv'     => get_str('aktiv') === 'alle' ? 'alle' : 'aktiv',
    'sort'      => get_str('sort'),
]);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="simkarten_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");   // BOM, damit Excel die Umlaute richtig anzeigt

fputcsv($out, [
    'Kartenwelt', 'Rufnummer', 'ISSI', 'OPTA', 'ICCID', 'Art', 'Status', 'Gehoert zu', 'Zuordnung', 'Steckt in',
    'Anbieter', 'Tarif', 'Datenvolumen', 'Kosten je Monat', 'Vertrag bis',
    'Ausgegeben am', 'PIN', 'PUK', 'Im Bestand', 'Notiz',
], ';');

foreach ($sims as $s) {
    fputcsv($out, [
        SIM_ARTEN[(string)$s['karte_art']] ?? '',
        (string)$s['rufnummer'],
        (string)$s['issi'],
        (string)$s['opta'],
        (string)$s['iccid'],
        (string)($s['typ_label'] ?? ''),
        (string)($s['status_label'] ?? ''),
        SIM_ZIELE[(string)$s['ziel_typ']] ?? '',
        sim_ziel_text($s),
        (string)$s['geraet'],
        (string)$s['anbieter'],
        (string)$s['tarif'],
        (string)$s['datenvolumen'],
        sim_hat_vertrag($s) && $s['kosten_monat'] !== null
            ? number_format((float)$s['kosten_monat'], 2, ',', '') : '',
        $s['vertrag_bis'] ? de_date((string)$s['vertrag_bis']) : '',
        $s['ausgegeben_am'] ? de_date((string)$s['ausgegeben_am']) : '',
        (string)$s['pin'],
        (string)$s['puk'],
        (int)$s['is_active'] === 1 ? 'ja' : 'nein',
        preg_replace('/\s+/', ' ', (string)($s['notiz'] ?? '')),
    ], ';');
}

fclose($out);
audit('sim.export', 'sim', null, count($sims) . ' Zeilen');
exit;
