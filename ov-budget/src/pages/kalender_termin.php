<?php
declare(strict_types=1);

/** Ein Termin auf einen Blick: nur das, was dran hängt – mit Druckansicht und Knopf zum Bearbeiten */
$user = current_user();
$termin = kalender_find((int)get_int('id', 0));
if (!$termin) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Termin gibt es nicht (mehr).']);
    return;
}
if (!kalender_sichtbar($termin, $user)) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Dieser Termin ist nicht für dich.']);
    return;
}

$daten = [
    'title'      => (string)$termin['titel'],
    'termin'     => $termin,
    'wann'       => kalender_wann_lang($termin),
    'fuerWen'    => kalender_ziel_name($termin),
    'darfBearbeiten' => kalender_darf_bearbeiten($termin, $user),
    'besprochen' => can('view_meetings') && function_exists('tps_fuer') ? tps_fuer('termin', (int)$termin['id']) : [],
] + kalender_termin_anhang($termin, $user);

$daten['navi'] = kalender_navi_ziel($termin, $daten['standorte'], $daten['dienststellen']);
$daten['naviLinks'] = $daten['navi'] ? kalender_navi_links($daten['navi']) : [];

if (get_str('druck') === '1') {
    echo render_partial('kalender_termin_druck', $daten);
    return;
}
render('kalender_termin', $daten);
