<?php
declare(strict_types=1);

/** Kalender aus Home Assistant auswählen, Bundesland für Feiertage, Abruf anstoßen */

require_role('admin');

$fehler = '';
$hinweis = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post_str('action')) {
        case 'speichern':
            $gewaehlt = array_values(array_filter((array)post('entitaeten', []),
                static fn($e) => is_string($e) && preg_match('/^calendar\.[a-z0-9_]+$/', $e) === 1));
            $frei = array_values(array_filter(array_map('trim', explode(',', post_str('weitere'))),
                static fn($e) => preg_match('/^calendar\.[a-z0-9_]+$/', $e) === 1));
            $alle = array_values(array_unique(array_merge($gewaehlt, $frei)));
            setting_save('kalender_ha_entitaeten', implode(',', $alle));
            $land = strtoupper(post_str('bundesland'));
            setting_save('kalender_bundesland', array_key_exists($land, KALENDER_BUNDESLAENDER) ? $land : 'BW');
            setting_save('kalender_ha_intervall_minuten', (string)max(5, (int)post_int('intervall', 60)));
            settings_reset_cache();
            state_save('kalender_ha_letzter_abruf', '0');   // beim nächsten Minutenlauf holen
            audit('kalender.einstellungen', 'settings', null, implode(',', $alle));
            flash('success', count($alle) . ' Kalender ausgewählt – der nächste Abruf holt sie.');
            redirect_route('admin_kalender');
            // kein break: redirect beendet

        case 'ics_neu':
            $token = ics_token_neu();
            state_save('kalender_ics_letzter_abruf', '0');
            state_save('kalender_ics_letzte_ip', '');
            audit('kalender.ics_token', 'settings', null, 'neu');
            flash('success', 'Neues Token erzeugt. Ein altes Token gilt ab jetzt nicht mehr – die Adresse in Home Assistant und auf den Geräten erneuern.');
            redirect_route('admin_kalender');
            // kein break: redirect beendet

        case 'ics_weg':
            ics_token_weg();
            audit('kalender.ics_token', 'settings', null, 'zurückgezogen');
            flash('success', 'Kalender-Abo abgeschaltet. Die bisherige Adresse liefert nichts mehr.');
            redirect_route('admin_kalender');
            // kein break

        case 'ics_optionen':
            foreach (['termine', 'veranstaltungen', 'besprechungen', 'fristen', 'beschreibung'] as $schalter) {
                setting_save('kalender_ics_' . $schalter, post_bool('ics_' . $schalter) ? '1' : '0');
            }
            settings_reset_cache();
            audit('kalender.ics_optionen', 'settings', null, json_encode(ics_optionen()));
            flash('success', 'Inhalt des Kalender-Abos gespeichert.');
            redirect_route('admin_kalender');
            // kein break

        case 'abrufen':
            $res = kalender_ha_sync();
            if ($res['fehler']) {
                $fehler = 'Nicht alles ließ sich holen: ' . implode(' · ', array_map(static fn($k, $v) => $k . ': ' . $v, array_keys($res['fehler']), $res['fehler']));
            } else {
                $hinweis = sprintf('%d Kalender geholt, %d Einträge.', $res['kalender'], $res['eintraege']);
            }
            break;
    }
}

$verfuegbar = [];
$listeFehler = '';
try {
    $verfuegbar = kalender_ha_liste();
} catch (Throwable $ex) {
    $listeFehler = $ex->getMessage();
}

render('admin/kalender', [
    'title'       => 'Kalender',
    'verfuegbar'  => $verfuegbar,
    'listeFehler' => $listeFehler,
    'ausgewaehlt' => kalender_ha_ausgewaehlt(),
    'bundesland'  => (string)setting('kalender_bundesland', 'BW'),
    'intervall'   => max(5, setting_int('kalender_ha_intervall_minuten', 60)),
    'letzterAbruf' => (int)state_get('kalender_ha_letzter_abruf', '0'),
    'anzahl'      => (int)db_val('SELECT COUNT(*) FROM calendar_ha', [], 0),
    'fehler'      => $fehler,
    'hinweis'     => $hinweis,
    'icsAktiv'    => ics_aktiv(),
    'icsAdresse'  => ics_aktiv() ? ics_adresse() : '',
    'icsOptionen' => ics_optionen(),
    'icsLetzter'  => (int)state_get('kalender_ics_letzter_abruf', '0'),
    'icsIp'       => state_get('kalender_ics_letzte_ip', ''),
    'fachgruppen' => function_exists('list_items') ? list_items('fachgruppe') : [],
]);
