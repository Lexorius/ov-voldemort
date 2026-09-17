<?php
/**
 * Automatische Abrufe: Stein.APP und Divera 24/7.
 * Aufruf: /cron.php?token=...   (Token in den Einstellungen hinterlegen)
 * Alternativ per CLI: php public/cron.php
 *
 * Der Aufruf darf oft kommen (im Add-on jede Minute) – jeder Abruf prüft
 * selbst, ob er an der Reihe ist.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    header('Content-Type: text/plain; charset=UTF-8');
}

// Beide Anbindungen teilen sich den Token; der alte Divera-Token gilt weiter
$tokens = array_values(array_filter([
    (string)setting('cron_token', ''),
    (string)setting('divera_cron_token', ''),
]));

if (!$cli) {
    if (!$tokens) {
        http_response_code(403);
        exit("Der automatische Abruf ist nicht aktiviert (kein Token hinterlegt).\n");
    }
    $gegeben = (string)($_GET['token'] ?? '');
    $passt = false;
    foreach ($tokens as $t) {
        $passt = hash_equals($t, $gegeben) || $passt;
    }
    if (!$passt) {
        http_response_code(403);
        exit("Ungültiges Token.\n");
    }
}

/* ---- Stein.APP: Vollbild abziehen, Änderungen in die Fahrzeugakten ---- */
if (stein_enabled()) {
    $res = stein_sync();
    printf("Stein.APP: %s – %s\n", $res['status'], $res['message']);
} else {
    echo "Stein.APP: nicht eingerichtet.\n";
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
