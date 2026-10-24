<?php
declare(strict_types=1);

/** Aktionen an einer Abstimmung: freigeben, schließen, abholen, Code neu, Antworten löschen, löschen */
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('umfragen');
}
if (!can('manage_umfragen')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('umfragen');
}

$umfrage = umfrage_find(post_int('id'));
if ($umfrage === null) {
    flash('error', 'Diese Abstimmung gibt es nicht (mehr).');
    redirect_route('umfragen');
}
$zurueck = url('umfrage', ['id' => (int)$umfrage['id']]);
$connector = connector_find((int)($umfrage['connector_id'] ?? 0));

/** Fragen und Codes beim Connector auf den neuesten Stand bringen – Fehler als Hinweis */
$anmelden = static function (?array $c, string $sonst): void {
    if ($c === null || !connector_taugt($c, 'umfragen')) {
        return;
    }
    try {
        connector_push_umfragen($c, true);
        flash('success', $sonst);
    } catch (Throwable $ex) {
        flash('warn', 'Gespeichert, aber der Connector war nicht erreichbar: ' . e($ex->getMessage())
            . ' Der nächste automatische Abruf holt das nach.');
    }
};

switch (post_str('action')) {
    case 'freigeben':
        if ($connector === null || !connector_taugt($connector, 'umfragen')) {
            flash('error', 'Zum Freigeben braucht die Abstimmung einen gekoppelten Connector mit der Verwendung „Abstimmungen".');
            break;
        }
        if (strtotime((string)$umfrage['ende']) < time()) {
            flash('error', 'Das Ende liegt in der Vergangenheit. Bitte erst das Zeitfenster anpassen.');
            break;
        }
        umfrage_status_setzen($umfrage, 'offen');
        $anmelden($connector, 'Abstimmung freigegeben und beim Connector angemeldet. Der Connector nimmt im Zeitfenster Antworten an.');
        break;

    case 'schliessen':
        // Erst holen, was noch draußen liegt – beim Anmelden ohne diese Abstimmung wirft der Connector den Rest weg
        if ($connector !== null && connector_taugt($connector, 'umfragen')) {
            try {
                connector_fetch_umfragen($connector);
            } catch (Throwable $ex) {
                flash('warn', 'Der Connector war nicht erreichbar – dort liegende Antworten werden beim nächsten Abruf geholt: ' . e($ex->getMessage()));
                break;
            }
        }
        umfrage_status_setzen($umfrage, 'geschlossen');
        $anmelden($connector, 'Abstimmung geschlossen. Beim Connector ist sie abgemeldet, dort liegt nichts mehr.');
        if ($connector === null) {
            flash('success', 'Abstimmung geschlossen.');
        }
        break;

    case 'entwurf':
        umfrage_status_setzen($umfrage, 'entwurf');
        $anmelden($connector, 'Zurück in den Entwurf. Beim Connector ist die Abstimmung abgemeldet.');
        break;

    case 'abholen':
        if ($connector === null || !connector_taugt($connector, 'umfragen')) {
            flash('error', 'Kein zuständiger Connector eingetragen.');
            break;
        }
        try {
            $res = connector_fetch_umfragen($connector);
            flash('success', sprintf('%d Antwort(en) geholt, %d übernommen%s.', $res['geholt'], $res['uebernommen'],
                $res['fehler'] ? ', ' . $res['fehler'] . ' unbrauchbar' : ''));
            state_save('umfrage_letzter_abruf', (string)time());
        } catch (Throwable $ex) {
            flash('error', 'Der Connector war nicht erreichbar: ' . e($ex->getMessage()));
        }
        break;

    case 'code_neu':
        umfrage_code_erneuern($umfrage);
        $anmelden($connector, 'Neuer Code. Der alte QR-Code und der alte Link gehen ins Leere.');
        if ($connector === null) {
            flash('success', 'Neuer Code erzeugt.');
        }
        break;

    case 'antworten_loeschen':
        $n = umfrage_antworten_loeschen($umfrage);
        flash('success', sprintf('%d Antwort(en) gelöscht.', $n));
        break;

    case 'loeschen':
        $titel = (string)$umfrage['titel'];
        umfrage_delete($umfrage);
        // Beim Connector abmelden, damit dort nichts hängen bleibt
        if ($connector !== null && connector_taugt($connector, 'umfragen')) {
            try {
                connector_push_umfragen($connector, true);
            } catch (Throwable $ex) {
                flash('warn', 'Der Connector war nicht erreichbar; der nächste Abruf meldet die Abstimmung ab.');
            }
        }
        flash('success', sprintf('Abstimmung „%s" samt Antworten gelöscht.', e($titel)));
        redirect_route('umfragen');

    default:
        flash('error', 'Unbekannte Aktion.');
}

redirect($zurueck);
