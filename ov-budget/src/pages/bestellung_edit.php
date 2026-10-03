<?php
declare(strict_types=1);

if (!can('order_wish')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Bestellungen darf nur anlegen, wer bestellen darf – siehe Verwaltung → Bestellberechtigungen.']);
    return;
}
$user = current_user();
$id = get_int('id');
$b = $id ? bestellung_find($id) : null;
if ($id && !$b) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Bestellung gibt es nicht (mehr).']);
    return;
}

$errors = [];
$gewaehlt = array_values(array_filter(array_map('intval', (array)($_GET['wishes'] ?? $_POST['wishes'] ?? [])), static fn($i) => $i > 0));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$neu, $errors] = bestellung_save_from_post($b, $user, $gewaehlt);
    if ($neu) {
        flash('success', $b ? 'Bestellung gespeichert.' : 'Bestellung angelegt – die Wünsche sind jetzt „bestellt".');
        redirect_route('bestellung', ['id' => $neu]);
    }
    $b = array_merge($b ?? [], $_POST, ['id' => $b['id'] ?? null]);
}

$frei = $b && !empty($b['id']) ? [] : wish_query(['status_slug' => 'freigegeben', 'sort' => 'prio']);
if (!$b) {
    $b = ['id' => null, 'lieferant' => '', 'bestellt_am' => date('Y-m-d'), 'bestell_nr' => '', 'notiz' => ''];
    // Lieferant aus dem ersten gewählten Wunsch vorschlagen
    foreach ($frei as $w) {
        if (in_array((int)$w['id'], $gewaehlt, true) && trim((string)$w['lieferant']) !== '') {
            $b['lieferant'] = (string)$w['lieferant'];
            break;
        }
    }
}

render('bestellung_edit', [
    'title'    => empty($b['id']) ? 'Bestellung anlegen' : 'Bestellung bearbeiten',
    'b'        => $b,
    'frei'     => $frei,
    'gewaehlt' => $gewaehlt,
    'errors'   => $errors,
    'wuensche' => !empty($b['id']) ? bestellung_wuensche((int)$b['id']) : [],
]);
