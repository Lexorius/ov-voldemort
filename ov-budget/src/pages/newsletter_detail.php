<?php
declare(strict_types=1);

/** Ein Newsletter: PDF, KI-Zusammenfassung, Empfänger, Mailtext, Planung, Versandstand */
$user = require_login();
if (!can('view_newsletter')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Der Newsletter ist nur für die Leitung sichtbar.']);
    return;
}
$n = newsletter_find(get_int('id'));
if ($n === null) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Newsletter gibt es nicht (mehr).']);
    return;
}
$connector = connector_find((int)($n['connector_id'] ?? 0));
$empfaenger = newsletter_empfaenger_aufloesen($n);
render('newsletter_detail', [
    'title'      => (string)$n['titel'],
    'n'          => $n,
    'connector'  => $connector,
    'vorlage'    => newsletter_vorlage($n, (string)setting('ov_name', '')),
    'fuss'       => $connector !== null ? newsletter_fuss((string)setting('newsletter_fusstext', ''), newsletter_abmeldelink($connector, str_repeat('x', 64)), (string)setting('ov_name', '')) : '',
    'empfaenger' => $empfaenger,
    'stand'      => newsletter_versandstand((int)$n['id']),
    'versand'    => db_all('SELECT * FROM newsletter_versand WHERE newsletter_id = ? ORDER BY fehler <> \'\' DESC, gesendet_am IS NULL DESC, id LIMIT 300', [(int)$n['id']]),
    'kiDaten'    => function_exists('ki_fuer_dateien') ? ki_fuer_dateien('newsletter', [(int)$n['id']]) : ['ergebnisse' => [], 'auftraege' => []],
    'darf'       => can('manage_newsletter'),
    'mailAktiv'  => mail_eingerichtet(),
    'meineMail'  => (string)($user['email'] ?? ''),
]);
