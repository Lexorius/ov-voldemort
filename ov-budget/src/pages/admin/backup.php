<?php
declare(strict_types=1);

/**
 * Sicherung und Wiederherstellung – nur für die Administration.
 *
 * Herunterladen läuft über GET (?datei=…), alles andere über POST mit
 * CSRF-Token. Wiederherstellen verlangt zusätzlich das eigene Passwort und
 * ein getipptes Bestätigungswort: Es ersetzt die ganze Datenbank und die
 * Dateiablage.
 */
$me = require_role('admin');

$fehler = '';
$hinweis = '';
$verlauf = [];
$ergebnis = null;

/* ---------- Herunterladen ---------- */
$datei = get_str('datei');
if ($datei !== '') {
    $pfad = backup_path($datei);
    if ($pfad === null) {
        http_response_code(404);
        render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Sicherung gibt es nicht (mehr).']);
        return;
    }
    audit('sicherung.heruntergeladen', 'backup', null, $datei);
    header('Content-Type: application/zip');
    header('Content-Length: ' . (string)filesize($pfad));
    header('Content-Disposition: attachment; filename="' . $datei . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($pfad);
    exit;
}

/* ---------- Aktionen ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch (post_str('action')) {
            case 'erstellen':
                $res = backup_create('manuell', $me);
                audit('sicherung.erstellt', 'backup', null, $res['name']);
                $hinweis = sprintf('Sicherung %s angelegt (%s).', $res['name'], bytes_human($res['groesse']));
                break;

            case 'hochladen':
                $name = backup_uebernehmen($_FILES['sicherung'] ?? []);
                audit('sicherung.hochgeladen', 'backup', null, $name);
                $hinweis = sprintf('Sicherung als %s übernommen. Sie lässt sich jetzt unten wiederherstellen.', $name);
                break;

            case 'loeschen':
                $name = post_str('name');
                if (!backup_delete($name)) {
                    throw new BackupException('Diese Sicherung gibt es nicht (mehr).');
                }
                audit('sicherung.geloescht', 'backup', null, $name);
                $hinweis = sprintf('Sicherung %s gelöscht.', $name);
                break;

            case 'wiederherstellen':
                $name = post_str('name');
                if (post_str('bestaetigung') !== BACKUP_BESTAETIGUNG) {
                    throw new BackupException('Zur Bestätigung bitte genau „' . BACKUP_BESTAETIGUNG . '" eintippen.');
                }
                if (!password_verify((string)post('passwort', ''), (string)$me['password_hash'])) {
                    throw new BackupException('Das Passwort stimmt nicht.');
                }
                if (backup_path($name) === null) {
                    throw new BackupException('Diese Sicherung gibt es nicht (mehr).');
                }
                // Das kann dauern – lieber nicht mitten drin abbrechen
                @set_time_limit(600);
                ignore_user_abort(true);
                $ergebnis = backup_restore($name, static function (string $m) use (&$verlauf): void {
                    $verlauf[] = $m;
                });
                audit('sicherung.wiederhergestellt', 'backup', null,
                    sprintf('%s (Stand %s, Fassung %s)', $name, $ergebnis['zeit'], $ergebnis['fassung']));
                $hinweis = 'Wiederherstellung abgeschlossen.';
                break;

            default:
                $fehler = 'Unbekannte Aktion.';
        }
    } catch (BackupException $ex) {
        $fehler = $ex->getMessage();
    } catch (Throwable $ex) {
        error_log('OV-Budget Sicherung: ' . $ex->getMessage());
        $fehler = 'Das hat nicht geklappt: ' . $ex->getMessage();
    }
}

render('admin/backup', [
    'title'        => 'Sicherung',
    'problem'      => backup_problem(),
    'liste'        => backup_list(),
    'fehler'       => $fehler,
    'hinweis'      => $hinweis,
    'verlauf'      => $verlauf,
    'ergebnis'     => $ergebnis,
    'letzte'       => state_get('backup_letzte', ''),
    'automatik'    => setting_int('backup_automatisch_tage', 0),
    'aufheben'     => setting_int('backup_aufheben_anzahl', 7),
    'letzteAuto'   => state_get('backup_letzte_automatik', ''),
    'uploadMax'    => min(BACKUP_MAX_UPLOAD, (int)backup_ini_bytes((string)ini_get('upload_max_filesize')),
                          (int)backup_ini_bytes((string)ini_get('post_max_size'))),
]);
