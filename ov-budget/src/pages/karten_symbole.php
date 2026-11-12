<?php
declare(strict_types=1);

/* Symbolbibliothek der Karten: SVG und Bilder hochladen, benennen, gruppieren, löschen */
$user = require_login();
if (!can('view_karten')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Karten sind nur für die Leitung sichtbar.']);
    return;
}
$verwalten = can('manage_karten');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$verwalten) {
        flash('error', 'Die Symbolbibliothek pflegt die Leitung.');
        redirect_route('karten_symbole');
    }
    $symbol = karten_symbol_find(post_int('symbol_id'));
    switch (post_str('action')) {
        case 'hochladen':
            [$anzahl, $fehler] = karten_symbole_speichern('symbole', post_str('name'), post_str('gruppe'), $user);
            foreach ($fehler as $f) {
                flash('error', $f);
            }
            if ($anzahl > 0) {
                flash('success', ($anzahl === 1 ? 'Ein Symbol' : $anzahl . ' Symbole') . ' in die Bibliothek gelegt.');
            }
            break;
        case 'aendern':
            if ($symbol) {
                karten_symbol_aendern($symbol, post_str('name'), post_str('gruppe'));
                flash('success', 'Gespeichert.');
            }
            break;
        case 'loeschen':
            if ($symbol) {
                $grund = karten_symbol_delete($symbol);
                flash($grund === null ? 'success' : 'error', $grund ?? 'Symbol „' . (string)$symbol['name'] . '" gelöscht.');
            }
            break;
    }
    redirect_route('karten_symbole');
}

$symbole = karten_symbole();
render('karten_symbole', [
    'title'     => 'Symbole',
    'gruppiert' => karten_symbole_gruppiert($symbole),
    'anzahl'    => count($symbole),
    'gruppen'   => karten_gruppen(),
    'verwalten' => $verwalten,
    'maxMb'     => (int)round(upload_max_bytes() / 1048576),
]);
