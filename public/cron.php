<?php
/**
 * Automatischer Divera-Abruf.
 * Aufruf: /cron.php?token=...   (Token in den Einstellungen hinterlegen)
 * Alternativ per CLI: php public/cron.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    header('Content-Type: text/plain; charset=UTF-8');
}

$token = (string)setting('divera_cron_token', '');

if (!$cli) {
    if ($token === '') {
        http_response_code(403);
        exit("Der automatische Abruf ist nicht aktiviert (kein Token hinterlegt).\n");
    }
    if (!hash_equals($token, (string)($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit("Ungültiges Token.\n");
    }
}

if (!divera_enabled()) {
    exit("Divera-Anbindung ist deaktiviert oder unvollständig konfiguriert.\n");
}

$forms = db_all('SELECT * FROM divera_forms WHERE auto_import = 1');
if (!$forms) {
    exit("Kein Formular für den automatischen Import vorgemerkt.\n");
}

$gesamt = ['created' => 0, 'skipped' => 0, 'failed' => 0];

foreach ($forms as $form) {
    try {
        $res = divera_import_form($form, null);
        $gesamt['created'] += $res['created'];
        $gesamt['skipped'] += $res['skipped'];
        $gesamt['failed'] += $res['failed'];
        printf(
            "%s: %d geprüft, %d neu, %d bekannt, %d Fehler\n",
            $form['name'],
            $res['total'],
            $res['created'],
            $res['skipped'],
            $res['failed']
        );
    } catch (Throwable $ex) {
        db_insert('divera_log', [
            'form_id' => (string)$form['form_id'],
            'status'  => 'fehler',
            'message' => mb_substr($ex->getMessage(), 0, 500),
        ]);
        printf("%s: FEHLER – %s\n", $form['name'], $ex->getMessage());
    }
}

audit('divera.cron', 'divera_form', null,
    sprintf('%d neu, %d bekannt, %d Fehler', $gesamt['created'], $gesamt['skipped'], $gesamt['failed']));

printf("Fertig: %d neue Wünsche.\n", $gesamt['created']);
