<?php
declare(strict_types=1);

/** Zählerstände als CSV – ein Zähler (?id=) oder alle */
if (!can('view_verbrauch')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Der Verbrauch ist nur für die Leitung sichtbar.']);
    return;
}

$meter = meter_find(get_int('id', 0) ?? 0);
$meters = $meter ? [$meter] : meter_query(['aktiv' => 'alle']);
$name = $meter ? 'zaehler_' . slugify((string)$meter['name']) : 'zaehlerstaende';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $name . '_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, csv_sicher(['Zähler', 'Art', 'Zählernummer', 'Datum', 'Uhrzeit', 'Stand', 'Einheit',
    'Verbrauch seit vorher', 'Tage', 'Quelle', 'Abgelesen von', 'Notiz']), ';');

$zeilen = 0;
foreach ($meters as $m) {
    $vor = null;
    foreach (array_reverse(readings_query((int)$m['id'])) as $r) {
        $t = strtotime((string)$r['gelesen_am']);
        $diff = $vor !== null ? (float)$r['stand'] - (float)$vor['stand'] : null;
        $tage = $vor !== null ? ($t - strtotime((string)$vor['gelesen_am'])) / 86400 : null;
        fputcsv($out, csv_sicher([
            (string)$m['name'], METER_ARTEN[(string)$m['art']]['label'], (string)$m['zaehlernummer'],
            date('d.m.Y', $t), date('H:i', $t),
            number_format((float)$r['stand'], 3, ',', ''), (string)$m['einheit'],
            $diff === null || $diff < 0 ? '' : number_format($diff, 3, ',', ''),
            $tage === null ? '' : number_format($tage, 1, ',', ''),
            READING_QUELLEN[(string)$r['quelle']] ?? (string)$r['quelle'],
            (string)($r['melder'] ?: ($r['erfasser'] ?? '')),
            (string)$r['notiz'],
        ]), ';');
        $vor = $r;
        $zeilen++;
    }
}
fclose($out);
audit('zaehler.export', 'meter', $meter ? (int)$meter['id'] : null, $zeilen . ' Zeilen');
exit;
