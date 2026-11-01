<?php
declare(strict_types=1);

/**
 * Schlagwörter der Kontakte: offene KI-Vorschläge je Kontakt übernehmen
 * oder verwerfen, Vorschläge für alle Kontakte ohne Schlagwörter anfordern.
 */

$user = require_role('admin', 'leitung');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post_str('action')) {

        case 'entscheiden':
            $id = post_int('contact_id') ?? 0;
            $c = $id ? contact_find($id) : null;
            if ($c) {
                $n = kontakt_tag_vorschlaege_entscheiden($id, wahl_ids('vorschlag'));
                audit('kontakt.tags_entschieden', 'contact', $id, contact_name($c) . ': ' . $n . ' übernommen');
                flash('success', $n > 0
                    ? sprintf('%d Schlagwort%s für „%s" übernommen.', $n, $n === 1 ? '' : 'e', contact_name($c))
                    : 'Vorschläge für „' . contact_name($c) . '" verworfen.');
            }
            redirect_route('contacts_tags');
            // no break

        case 'alle_einreihen':
            $n = kontakt_tags_alle_einreihen();
            if (!ki_modul_aktiv('kontakt')) {
                flash('warn', 'Die KI-Auswertung für Kontakte ist ausgeschaltet (Verwaltung → Einstellungen → KI-Auswertung).');
            } elseif (!kontakt_tags_liste()) {
                flash('warn', 'Die Auswahlliste „Schlagwörter (Kontakte)" ist leer – ohne Liste gibt es nichts vorzuschlagen.');
            } else {
                audit('kontakt.tags_alle', 'contact', null, (string)$n);
                flash('success', sprintf('%d Kontakt%s eingereiht. Der minütliche Abruf fragt die KI nach und nach.', $n, $n === 1 ? '' : 'e'));
            }
            redirect_route('contacts_tags');
            // no break

        case 'alle_verwerfen':
            $n = db_exec('DELETE FROM contact_tag_vorschlaege');
            audit('kontakt.tags_verworfen', 'contact', null, (string)$n);
            flash('info', 'Alle offenen Vorschläge verworfen.');
            redirect_route('contacts_tags');
            // no break
    }
}

render('contacts_tags', [
    'title'     => 'Schlagwörter',
    'kontakte'  => kontakt_tag_vorschlaege_alle(),
    'status'    => kontakt_tags_status(),
    'tags'      => kontakt_tags_liste(),
    'kiAktiv'   => ki_modul_aktiv('kontakt'),
    'ruhe'      => ki_ruhe_bis() > time(),
]);
