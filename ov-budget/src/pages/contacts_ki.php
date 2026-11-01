<?php
declare(strict_types=1);

/**
 * KI-Vorschläge für die Kategorie der Kontakte: je Kontakt übernehmen
 * oder verwerfen, Vorschläge für alle Kontakte ohne Kategorie anfordern.
 */

$user = require_role('admin', 'leitung');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post_str('action')) {

        case 'uebernehmen':
        case 'verwerfen':
            $id = post_int('contact_id') ?? 0;
            $c = $id ? contact_find($id) : null;
            if ($c) {
                $gesetzt = kontakt_kategorie_vorschlag_entscheiden($id, post_str('action') === 'uebernehmen', (int)$user['id']);
                audit('kontakt.kategorie_' . ($gesetzt ? 'uebernommen' : 'verworfen'), 'contact', $id, contact_name($c));
                flash($gesetzt ? 'success' : 'info', $gesetzt
                    ? 'Kategorie für „' . contact_name($c) . '" übernommen.'
                    : 'Vorschlag für „' . contact_name($c) . '" verworfen.');
            }
            redirect_route('contacts_ki');
            // no break

        case 'alle_uebernehmen':
            // Nur Kontakte, die noch keine Kategorie haben – gesetzte bleiben, wie sie sind
            $n = 0;
            foreach (kontakt_kategorie_vorschlaege_alle(1000) as $c) {
                if (empty($c['kategorie_id'])) {
                    $n += kontakt_kategorie_vorschlag_entscheiden((int)$c['id'], true, (int)$user['id']) ? 1 : 0;
                }
            }
            audit('kontakt.kategorie_alle_uebernommen', 'contact', null, (string)$n);
            flash('success', sprintf('%d Kategorie%s übernommen – nur bei Kontakten, die noch keine hatten.', $n, $n === 1 ? '' : 'n'));
            redirect_route('contacts_ki');
            // no break

        case 'alle_einreihen':
            $n = kontakt_kategorie_alle_einreihen();
            if (!ki_modul_aktiv('kontakt')) {
                flash('warn', 'Die KI-Auswertung für Kontakte ist ausgeschaltet (Verwaltung → Einstellungen → KI-Auswertung).');
            } else {
                audit('kontakt.kategorie_alle', 'contact', null, (string)$n);
                flash('success', sprintf('%d Kontakt%s eingereiht. Der minütliche Abruf fragt die KI nach und nach.', $n, $n === 1 ? '' : 'e'));
            }
            redirect_route('contacts_ki');
            // no break

        case 'alle_verwerfen':
            $n = db_exec('DELETE FROM contact_kategorie_vorschlaege');
            audit('kontakt.kategorie_alle_verworfen', 'contact', null, (string)$n);
            flash('info', 'Alle offenen Vorschläge verworfen.');
            redirect_route('contacts_ki');
            // no break
    }
}

render('contacts_ki', [
    'title'      => 'KI-Vorschläge',
    'kontakte'   => kontakt_kategorie_vorschlaege_alle(),
    'status'     => kontakt_kategorie_status(),
    'kategorien' => list_items('kontakt_kategorie'),
    'kiAktiv'    => ki_modul_aktiv('kontakt'),
    'ruhe'       => ki_ruhe_bis() > time(),
]);
