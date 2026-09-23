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

/*
 * Die Abrufe laufen unabhängig voneinander: Scheitert einer, laufen die
 * anderen trotzdem. Stein.APP und Divera schreiben in verschiedene Felder;
 * gemeinsame (Kennzeichen, ISSI, Funkrufname) füllt Divera nur, wenn sie leer sind.
 */
$abrufe = [
    'Stein.APP'         => static fn() => stein_enabled() ? [stein_sync()] : [],
    'Divera-Stammdaten' => static fn() => divera_vehicles_enabled() ? [divera_vehicles_sync_master()] : [],
    'Divera-Funkstatus' => static fn() => divera_vehicles_enabled() ? [divera_vehicles_sync_status()] : [],
    'QR-Standortmeldungen' => static function (): array {
        if (!connector_due()) {
            return [];
        }
        $res = connector_fetch_all();
        if ($res['geholt'] === 0 && $res['fehler'] === 0) {
            return [];
        }
        return [['status' => $res['fehler'] ? 'fehler' : 'ok', 'message' => sprintf(
            '%d Meldung(en) geholt, %d übernommen, %d Parkposition(en)%s',
            $res['geholt'], $res['uebernommen'], $res['parkpositionen'],
            $res['fehler'] ? ', ' . $res['fehler'] . ' unbrauchbar' : ''
        )]];
    },
    'Einladungen'       => static function (): array {
        if (!connector_events_due()) {
            return [];
        }
        $res = connector_events_sync();
        if ($res['gesendet'] === 0 && $res['geholt'] === 0 && $res['fehler'] === 0) {
            return [];
        }
        return [['status' => $res['fehler'] ? 'fehler' : 'ok', 'message' => sprintf(
            '%d Connector(en) neu angemeldet, %d Rückmeldung(en) geholt, %d übernommen%s',
            $res['gesendet'], $res['geholt'], $res['uebernommen'],
            $res['fehler'] ? ', ' . $res['fehler'] . ' unbrauchbar' : ''
        )]];
    },
    'Benachrichtigungen' => static function (): array {
        $n = notify_taeglich();
        $res = notify_flush();
        notify_cleanup();
        if ($res['gesendet'] === 0 && $res['fehler'] === 0 && $n === 0) {
            return [];
        }
        return [['status' => $res['fehler'] ? 'fehler' : 'ok', 'message' => sprintf(
            '%d gesendet, %d neu eingereiht%s', $res['gesendet'], $n,
            $res['fehler'] ? ', Fehler: ' . $res['meldung'] : ''
        )]];
    },
    'Home Assistant'    => static function (): array {
        $due = ha_due();
        if (!$due['faellig']) {
            return [];
        }
        $res = ha_publish($due['discovery']);
        return [['status' => 'ok', 'message' => sprintf('%d Nachricht(en) an MQTT%s',
            $res['nachrichten'], $due['discovery'] ? ', Entitäten angemeldet' : '')]];
    },
];
foreach ($abrufe as $name => $abruf) {
    try {
        foreach ($abruf() as $res) {
            if ($res['status'] !== 'wartet') {
                printf("%s: %s – %s\n", $name, $res['status'], $res['message']);
            }
        }
    } catch (Throwable $ex) {
        printf("%s: FEHLER – %s\n", $name, $ex->getMessage());
    }
}

if (!divera_enabled()) {
    exit("Divera-Anbindung ist deaktiviert oder unvollständig konfiguriert.\n");
}

$forms = db_all('SELECT * FROM divera_forms WHERE auto_import = 1 OR status_sync = 1');
if (!$forms) {
    exit("Kein Formular für den automatischen Import vorgemerkt.\n");
}

// Der Minutentakt ist für die Fahrzeuge gedacht; Formulare reichen seltener.
// Ein Abruf kann mehrere Seiten umfassen (50 Einträge je Seite).
$intervall = max(1, setting_int('divera_formular_intervall_minuten', 15));
if (time() - (int)state_get('divera_formular_letzter_abruf', '0') < $intervall * 60) {
    exit("Divera-Formulare: noch nicht an der Reihe.\n");
}
state_save('divera_formular_letzter_abruf', (string)time());

$gesamt = ['created' => 0, 'skipped' => 0, 'failed' => 0];

foreach ($forms as $form) {
    try {
        if (!(int)$form['auto_import']) {
            continue;
        }
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

// Bearbeitungsstand zurückmelden – auch für Formulare ohne automatischen Import
foreach ($forms as $form) {
    if (!(int)($form['status_sync'] ?? 0)) {
        continue;
    }
    try {
        $st = divera_status_sync_form($form);
        if ($st['gemeldet'] > 0 || $st['fehler'] !== '') {
            printf("%s: %d Status an Divera gemeldet%s\n", $form['name'], $st['gemeldet'],
                $st['fehler'] !== '' ? ' – FEHLER: ' . $st['fehler'] : '');
        }
    } catch (Throwable $ex) {
        printf("%s: Statusmeldung FEHLER – %s\n", $form['name'], $ex->getMessage());
    }
}

// Nur ins Änderungsprotokoll, wenn sich etwas getan hat – sonst stünde dort jeder Durchlauf
if ($gesamt['created'] > 0 || $gesamt['failed'] > 0) {
    audit('divera.cron', 'divera_form', null,
        sprintf('%d neu, %d bekannt, %d Fehler', $gesamt['created'], $gesamt['skipped'], $gesamt['failed']));
}

printf("Fertig: %d neue Einträge (Wünsche oder Themen).\n", $gesamt['created']);
