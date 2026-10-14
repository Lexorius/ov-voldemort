<?php
declare(strict_types=1);

/** THW-Kontakte: Dienststellen als Baum und Funktionspostfächer – für alle sichtbar, gepflegt von der Leitung */
$user = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)($user['role'] ?? ''), ['admin', 'leitung'], true)) {
    if (post_str('action') === 'aktiv' && ($d = thw_find((int)post_int('id', 0)))) {
        $neu = (int)$d['is_active'] === 1 ? 0 : 1;
        db_update('thw_dienststellen', ['is_active' => $neu], 'id = ?', [(int)$d['id']]);
        audit('thw.bearbeitet', 'thw_dienststelle', (int)$d['id'], $neu ? 'wieder aktiv' : 'stillgelegt');
        flash('success', $neu ? sprintf('„%s" ist wieder aktiv.', (string)$d['name']) : sprintf('„%s" ist stillgelegt.', (string)$d['name']));
    }
    if (post_str('action') === 'postfach_aktiv' && ($p = postfach_find((int)post_int('id', 0)))) {
        $neu = (int)$p['is_active'] === 1 ? 0 : 1;
        db_update('thw_postfaecher', ['is_active' => $neu], 'id = ?', [(int)$p['id']]);
        audit('postfach.bearbeitet', 'thw_postfach', (int)$p['id'], $neu ? 'wieder aktiv' : 'stillgelegt');
        flash('success', $neu ? sprintf('Postfach „%s" ist wieder aktiv.', (string)$p['name']) : sprintf('Postfach „%s" ist stillgelegt.', (string)$p['name']));
    }
    redirect_route('thw');
}

$q = get_str('q');
$alle = thw_all();
$postfaecher = postfach_all();

render('thw', [
    'title'       => (string)setting('thw_modul_name', 'THW-Kontakte'),
    'q'           => $q,
    'alle'        => $alle,
    'baum'        => standort_baum(thw_suchen($alle, $q)),
    'zaehlung'    => standort_zaehlung($alle),
    'postfaecher' => postfach_suchen($postfaecher, $q),
    'jeDienststelle' => postfach_je_dienststelle($postfaecher),
    'darfPflegen' => in_array((string)($user['role'] ?? ''), ['admin', 'leitung'], true),
]);
