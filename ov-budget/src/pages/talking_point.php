<?php
declare(strict_types=1);

/**
 * Ein Talking Point mit seiner Diskussion.
 * Anmerkungen gehen, solange der Punkt nicht abgeschlossen ist.
 */
if (!can('view_meetings')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Besprechungen sind nur für die Leitung sichtbar.']);
    return;
}

$user = current_user();
$tp = tp_find_full((int)get_int('id', 0));
if (!$tp) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Punkt gibt es nicht (mehr).']);
    return;
}
$zurueck = url('talking_point', ['id' => $tp['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post_str('action')) {
        case 'anmerkung':
            $fehler = tp_comment_add($tp, $user, post_str('body'));
            if ($fehler !== null) {
                flash('error', e($fehler));
            } else {
                flash('success', 'Anmerkung gespeichert.');
            }
            redirect($zurueck . '#anmerkungen');
            // no break

        case 'anmerkung_loeschen':
            $k = db_row('SELECT * FROM talking_point_comments WHERE id = ? AND tp_id = ?',
                [post_int('comment_id', 0) ?? 0, (int)$tp['id']]);
            if ($k && tp_comment_deletable($k, $tp, $user)) {
                db_exec('DELETE FROM talking_point_comments WHERE id = ?', [(int)$k['id']]);
                audit('tp.anmerkung.geloescht', 'talking_point', (int)$tp['id'], mb_substr((string)$k['body'], 0, 80));
                flash('success', 'Anmerkung entfernt.');
            } else {
                flash('error', 'Diese Anmerkung lässt sich nicht (mehr) entfernen.');
            }
            redirect($zurueck . '#anmerkungen');
    }
}

render('talking_point', [
    'title'      => $tp['titel'],
    'tp'         => $tp,
    'kommentare' => tp_comments((int)$tp['id']),
    'offen'      => tp_discussion_open($tp),
    'user'       => $user,
]);
