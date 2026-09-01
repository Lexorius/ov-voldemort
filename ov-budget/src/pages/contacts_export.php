<?php
declare(strict_types=1);

if (!can('view_contacts')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Kontakte sind nur für die Leitung sichtbar.']);
    return;
}

$groupId = get_int('group_id');
$gruppe = $groupId ? contact_group_find($groupId) : null;

if ($gruppe) {
    $rows = contact_group_members((int)$gruppe['id']);
    $name = 'verteiler_' . slugify((string)$gruppe['name']);
} else {
    $rows = contact_query([
        'q'            => get_str('q'),
        'kategorie_id' => get_int('kategorie_id'),
        'aktiv'        => get_str('aktiv') === 'alle' ? 'alle' : 'aktiv',
        'nur_mit_email' => get_str('mit_email') === '1' ? 1 : 0,
        'sort'         => get_str('sort', 'name'),
    ]);
    $name = 'kontakte';
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $name . '_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");   // BOM, damit Excel die Umlaute richtig anzeigt

// Spaltennamen passend für einen Serienbrief
$kopf = [
    'Anrede', 'Titel', 'Vorname', 'Nachname', 'Organisation', 'Position',
    'Strasse', 'PLZ', 'Ort', 'Land', 'Briefanrede',
    'E-Mail', 'Telefon', 'Mobil', 'Kategorie', 'Notiz',
];
if ($gruppe) {
    array_push($kopf, 'Status', 'Personen', 'Bemerkung');
}

// Frei definierte Felder hinten anhaengen, damit der Serienbrief sie kennt
$extraFelder = contact_extra_fields();
foreach ($extraFelder as $def) {
    $kopf[] = $def['label'];
}
fputcsv($out, $kopf, ';');

foreach ($rows as $c) {
    $zeile = [
        $c['anrede'], $c['titel'], $c['vorname'], $c['nachname'],
        $c['organisation'], $c['position'],
        $c['strasse'], $c['plz'], $c['ort'], $c['land'],
        contact_salutation($c),
        $c['email'], $c['telefon'], $c['mobil'],
        $c['kategorie_label'] ?? '',
        preg_replace('/\s+/', ' ', (string)$c['notiz']),
    ];
    if ($gruppe) {
        array_push($zeile, $c['status_label'] ?? '', $c['personen'] ?? '', $c['teilnahme_notiz'] ?? '');
    }
    $werte = extra_values($c);
    foreach ($extraFelder as $key => $def) {
        $wert = (string)($werte[$key] ?? '');
        $zeile[] = $def['type'] === 'bool' ? ($wert === '1' ? 'ja' : 'nein') : $wert;
    }
    fputcsv($out, $zeile, ';');
}

fclose($out);
audit('kontakt.export', 'contact', $gruppe['id'] ?? null, count($rows) . ' Zeilen');
exit;
