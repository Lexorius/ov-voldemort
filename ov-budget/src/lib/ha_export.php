<?php
declare(strict_types=1);

/**
 * Kennzahlen nach Home Assistant melden – über MQTT mit Auto-Discovery.
 *
 * Es entsteht ein Gerät „OV-Budget" mit Sensoren (Budget, Wünsche, Aufgaben,
 * Besprechungen, Fahrzeuge) und auf Wunsch je Fahrzeug ein eigenes Gerät mit
 * Status, Funkstatus, Fristen und Standort.
 *
 * Aufbau der Themen (Basis einstellbar, Vorgabe "ovbudget"):
 *   ovbudget/status                      alle Kennzahlen als JSON, dauerhaft
 *   ovbudget/fahrzeug/<id>/status        Werte eines Fahrzeugs als JSON
 *   homeassistant/sensor/...             Anmeldung der Entitäten
 *
 * Personenbezogenes bleibt außen vor: gemeldet werden Zahlen, Zeitpunkte und
 * Fahrzeugdaten, keine Namenslisten.
 */

/** Sensoren des Hauptgeräts: Schlüssel => Beschreibung für die Anmeldung */
function ha_sensoren(): array
{
    $geld = ['unit' => 'EUR', 'device_class' => 'monetary', 'state_class' => 'total'];
    return [
        'budget_gesamt'      => ['name' => 'Budget gesamt', 'icon' => 'mdi:cash'] + $geld,
        'budget_verplant'    => ['name' => 'Budget verplant', 'icon' => 'mdi:cash-clock'] + $geld,
        'budget_frei'        => ['name' => 'Budget frei', 'icon' => 'mdi:cash-check'] + $geld,
        'budget_auslastung'  => ['name' => 'Budget-Auslastung', 'unit' => '%', 'icon' => 'mdi:percent',
                                 'state_class' => 'measurement'],
        'wuensche_offen'     => ['name' => 'Offene Wünsche', 'unit' => 'Wünsche', 'icon' => 'mdi:playlist-star',
                                 'state_class' => 'measurement'],
        'wuensche_freigegeben' => ['name' => 'Wünsche zur Bestellung freigegeben', 'unit' => 'Wünsche',
                                   'icon' => 'mdi:cart-arrow-right', 'state_class' => 'measurement'],
        'wuensche_summe'     => ['name' => 'Offene Wünsche in Euro', 'icon' => 'mdi:cash-multiple'] + $geld,
        'aufgaben_offen'     => ['name' => 'Offene Aufgaben', 'unit' => 'Aufgaben', 'icon' => 'mdi:clipboard-list',
                                 'state_class' => 'measurement'],
        'aufgaben_ueberfaellig' => ['name' => 'Überfällige Aufgaben', 'unit' => 'Aufgaben',
                                    'icon' => 'mdi:clipboard-alert', 'state_class' => 'measurement'],
        'themen_offen'       => ['name' => 'Themen im Themenspeicher', 'unit' => 'Themen', 'icon' => 'mdi:forum',
                                 'state_class' => 'measurement'],
        'besprechung_naechste' => ['name' => 'Nächste Besprechung', 'device_class' => 'timestamp',
                                   'icon' => 'mdi:calendar-clock', 'attribute' => 'besprechung_info'],
        'fahrzeuge_gesamt'   => ['name' => 'Fahrzeuge', 'unit' => 'Fahrzeuge', 'icon' => 'mdi:truck',
                                 'state_class' => 'measurement'],
        'fahrzeuge_einsatzbereit' => ['name' => 'Fahrzeuge einsatzbereit', 'unit' => 'Fahrzeuge',
                                      'icon' => 'mdi:truck-check', 'state_class' => 'measurement'],
        'fahrzeuge_ausfall'  => ['name' => 'Fahrzeuge im Ausfall', 'unit' => 'Fahrzeuge', 'icon' => 'mdi:truck-alert',
                                 'state_class' => 'measurement', 'attribute' => 'fahrzeuge_ausfall_info'],
        'auftraege_offen'    => ['name' => 'Offene Instandsetzungsaufträge', 'unit' => 'Aufträge',
                                 'icon' => 'mdi:wrench-clock', 'state_class' => 'measurement'],
        'fristen_faellig'    => ['name' => 'Fällige Fristen (HU, SP, UVV)', 'unit' => 'Fristen',
                                 'icon' => 'mdi:calendar-alert', 'state_class' => 'measurement',
                                 'attribute' => 'fristen_info'],
        'veranstaltung_naechste' => ['name' => 'Nächste Veranstaltung', 'device_class' => 'timestamp',
                                     'icon' => 'mdi:calendar-star', 'attribute' => 'veranstaltung_info'],
        'veranstaltung_zusagen' => ['name' => 'Zusagen zur nächsten Veranstaltung', 'unit' => 'Zusagen',
                                    'icon' => 'mdi:account-check', 'state_class' => 'measurement'],
        'veranstaltung_offen' => ['name' => 'Offene Rückmeldungen zur nächsten Veranstaltung', 'unit' => 'Einladungen',
                                  'icon' => 'mdi:account-question', 'state_class' => 'measurement'],
        'veranstaltung_personen' => ['name' => 'Personen bei der nächsten Veranstaltung', 'unit' => 'Personen',
                                     'icon' => 'mdi:account-group', 'state_class' => 'measurement'],
        'veranstaltungen_30_tage' => ['name' => 'Veranstaltungen in den nächsten 30 Tagen', 'unit' => 'Veranstaltungen',
                                      'icon' => 'mdi:calendar-month', 'state_class' => 'measurement'],
        'funk_gesamt'        => ['name' => 'Funkgeräte', 'unit' => 'Geräte', 'icon' => 'mdi:radio-handheld',
                                 'state_class' => 'measurement'],
        'funk_mit_karte'     => ['name' => 'Funkgeräte mit Karte', 'unit' => 'Geräte', 'icon' => 'mdi:sim',
                                 'state_class' => 'measurement'],
        'funk_ohne_zuordnung' => ['name' => 'Funkgeräte ohne Zuordnung', 'unit' => 'Geräte',
                                  'icon' => 'mdi:help-circle-outline', 'state_class' => 'measurement'],
        'funk_pruefung_bald' => ['name' => 'Funkgeräte mit fälliger Prüfung', 'unit' => 'Geräte',
                                 'icon' => 'mdi:calendar-alert', 'state_class' => 'measurement'],
        'funk_nicht_gemeldet' => ['name' => 'Funkgeräte länger nicht gemeldet', 'unit' => 'Geräte',
                                  'icon' => 'mdi:radio-off', 'state_class' => 'measurement'],
        'sim_gesamt'         => ['name' => 'SIM- und TETRA-Karten', 'unit' => 'Karten', 'icon' => 'mdi:sim',
                                 'state_class' => 'measurement'],
        'sim_tetra'          => ['name' => 'TETRA-Karten', 'unit' => 'Karten', 'icon' => 'mdi:sim-outline',
                                 'state_class' => 'measurement'],
        'sim_ohne_zuordnung' => ['name' => 'Karten ohne Zuordnung', 'unit' => 'Karten',
                                 'icon' => 'mdi:help-circle-outline', 'state_class' => 'measurement'],
        'sim_vertrag_bald'   => ['name' => 'Karten mit auslaufendem Vertrag', 'unit' => 'Karten',
                                 'icon' => 'mdi:file-document-alert', 'state_class' => 'measurement'],
        'sim_kosten_monat'   => ['name' => 'Kartenkosten je Monat', 'icon' => 'mdi:cash-sync'] + $geld,
        'ausgaben_jahr'      => ['name' => 'Ausgaben im Haushaltsjahr', 'icon' => 'mdi:cash-minus'] + $geld,
        'einnahmen_jahr'     => ['name' => 'Einnahmen im Haushaltsjahr', 'icon' => 'mdi:cash-plus'] + $geld,
        'saldo_jahr'         => ['name' => 'Saldo im Haushaltsjahr', 'icon' => 'mdi:scale-balance'] + $geld,
        'connectoren_gekoppelt' => ['name' => 'Gekoppelte Connectoren', 'unit' => 'Connectoren',
                                    'icon' => 'mdi:lan-connect', 'state_class' => 'measurement', 'diagnose' => true],
        'connector_letzter_abruf' => ['name' => 'Connector letzter Abruf', 'device_class' => 'timestamp',
                                      'icon' => 'mdi:lan-check', 'diagnose' => true],
        'sicherung_letzte'   => ['name' => 'Letzte Sicherung', 'device_class' => 'timestamp',
                                 'icon' => 'mdi:backup-restore', 'diagnose' => true],
        'sicherungen'        => ['name' => 'Sicherungen vorhanden', 'unit' => 'Sicherungen',
                                 'icon' => 'mdi:archive', 'state_class' => 'measurement', 'diagnose' => true],
        'stein_letzter_abruf' => ['name' => 'Stein.APP letzter Abruf', 'device_class' => 'timestamp',
                                  'icon' => 'mdi:cloud-download', 'diagnose' => true],
        'divera_letzter_abruf' => ['name' => 'Divera letzter Abruf', 'device_class' => 'timestamp',
                                   'icon' => 'mdi:cloud-download', 'diagnose' => true],
        'stand'              => ['name' => 'Zuletzt gemeldet', 'device_class' => 'timestamp',
                                 'icon' => 'mdi:update', 'diagnose' => true],
    ];
}

/** Sensoren je Fahrzeug */
function ha_fahrzeug_sensoren(): array
{
    return [
        'status'    => ['name' => 'Status', 'icon' => 'mdi:truck'],
        'fms'       => ['name' => 'Funkstatus', 'icon' => 'mdi:radio-handheld'],
        'hu_bis'    => ['name' => 'HU bis', 'device_class' => 'date', 'icon' => 'mdi:calendar-check'],
        'sp_bis'    => ['name' => 'SP bis', 'device_class' => 'date', 'icon' => 'mdi:calendar-check'],
        'uvv_bis'   => ['name' => 'UVV bis', 'device_class' => 'date', 'icon' => 'mdi:calendar-check'],
        'km_stand'  => ['name' => 'Kilometerstand', 'unit' => 'km', 'icon' => 'mdi:counter',
                        'state_class' => 'total_increasing'],
        'auftraege' => ['name' => 'Offene Aufträge', 'unit' => 'Aufträge', 'icon' => 'mdi:wrench',
                        'state_class' => 'measurement'],
    ];
}

/** Zeitstempel für Home Assistant (ISO 8601) oder null */
function ha_zeit(?int $zeit): ?string
{
    return $zeit && $zeit > 0 ? date('c', $zeit) : null;
}

/** Eindeutiger Name des Themenbaums, z. B. "ovbudget" */
function ha_basis(): string
{
    $basis = strtolower(trim((string)setting('ha_mqtt_basis', 'ovbudget')));
    $basis = (string)preg_replace('/[^a-z0-9_-]+/', '_', $basis);
    return trim($basis, '_') ?: 'ovbudget';
}

/** Kennzahlen zusammentragen */
function ha_werte(): array
{
    $jahr = setting_int('haushaltsjahr', (int)date('Y'));
    $heute = date('Y-m-d');

    $budget = db_row(
        'SELECT COALESCE(SUM(b.betrag_netto), 0) AS gesamt,
                COALESCE(SUM((SELECT COALESCE(SUM(w.netto_gesamt), 0) FROM wishes w
                               LEFT JOIN list_items s ON s.id = w.status_id
                              WHERE w.budget_id = b.id AND COALESCE(s.is_final, 0) = 0)), 0) AS verplant
         FROM budgets b WHERE b.jahr = ? AND b.is_active = 1',
        [$jahr]
    ) ?: ['gesamt' => 0, 'verplant' => 0];
    $gesamt = (float)$budget['gesamt'];
    $verplant = (float)$budget['verplant'];

    $wuensche = wish_query(['offen' => 1]);
    $statsW = wish_stats($wuensche);
    $freigegeben = 0;
    foreach ($wuensche as $w) {
        if (($w['status_slug'] ?? '') === 'freigegeben') {
            $freigegeben++;
        }
    }

    $aufgaben = todo_query(['offen' => 1]);
    $ueberfaellig = 0;
    foreach ($aufgaben as $a) {
        if (!empty($a['faellig_am']) && $a['faellig_am'] < $heute) {
            $ueberfaellig++;
        }
    }

    $warnTage = setting_int('fahrzeug_warn_tage', 30);
    $fahrzeuge = vehicle_query(['nur_aktive' => 1]);
    $einsatzbereit = 0;
    $ausfall = [];
    $auftraege = 0;
    $fristen = [];
    foreach ($fahrzeuge as $v) {
        if (($v['status_slug'] ?? '') === 'einsatzbereit') {
            $einsatzbereit++;
        } elseif (($v['status_slug'] ?? '') === 'nicht-einsatzbereit') {
            $ausfall[] = $v['bezeichnung'];
        }
        $auftraege += (int)$v['offene_auftraege'];
        foreach (vehicle_deadlines($v, $warnTage) as $f) {
            if ($f['status'] !== 'ok') {
                $fristen[] = sprintf('%s: %s %s (%s)', $v['bezeichnung'], $f['label'],
                    de_date($f['datum']), $f['status'] === 'abgelaufen' ? 'abgelaufen' : 'bald fällig');
            }
        }
    }

    $naechste = meeting_next();
    $besprechung = null;
    $besprechungInfo = [];
    if ($naechste) {
        $zeit = strtotime((string)$naechste['datum'] . ' ' . (string)($naechste['beginn'] ?: '00:00:00'));
        $besprechung = ha_zeit($zeit ?: null);
        $besprechungInfo = [
            'titel' => (string)$naechste['titel'],
            'ort'   => (string)$naechste['ort'],
            'datum' => de_date($naechste['datum']),
        ];
    }

    $funk = radio_stats(radio_query([]), setting_int('funk_pruefung_warnung_tage', 30));
    $sim = sim_stats(sim_query([]), setting_int('sim_vertrag_warnung_tage', 60));
    $veranstaltung = ha_veranstaltung_werte(
        event_query(['zeit' => 'kommend', 'sort' => 'alt']),
        static fn(array $e): array => event_stats(event_guests((int)$e['id']))
    );
    $ausgaben = expense_total($jahr, 'betrag_brutto', 'ausgabe');
    $einnahmen = expense_total($jahr, 'betrag_brutto', 'einnahme');
    $gekoppelt = 0;
    foreach (connector_all(true) as $c) {
        if (connector_gekoppelt($c)) {
            $gekoppelt++;
        }
    }
    $sicherungen = function_exists('backup_list') && backup_problem() === null ? count(backup_list()) : 0;

    return [
        'budget_gesamt'         => round($gesamt, 2),
        'budget_verplant'       => round($verplant, 2),
        'budget_frei'           => round($gesamt - $verplant, 2),
        'budget_auslastung'     => $gesamt > 0 ? round($verplant / $gesamt * 100, 1) : 0,
        'wuensche_offen'        => count($wuensche),
        'wuensche_freigegeben'  => $freigegeben,
        'wuensche_summe'        => round((float)$statsW['netto_offen'], 2),
        'aufgaben_offen'        => count($aufgaben),
        'aufgaben_ueberfaellig' => $ueberfaellig,
        'themen_offen'          => count(tp_backlog()),
        'besprechung_naechste'  => $besprechung,
        'besprechung_info'      => $besprechungInfo,
        'fahrzeuge_gesamt'      => count($fahrzeuge),
        'fahrzeuge_einsatzbereit' => $einsatzbereit,
        'fahrzeuge_ausfall'     => count($ausfall),
        'fahrzeuge_ausfall_info' => ['fahrzeuge' => $ausfall],
        'auftraege_offen'       => $auftraege,
        'fristen_faellig'       => count($fristen),
        'fristen_info'          => ['fristen' => $fristen],
        'veranstaltung_naechste' => $veranstaltung['naechste'],
        'veranstaltung_info'    => $veranstaltung['info'],
        'veranstaltung_zusagen' => $veranstaltung['zusagen'],
        'veranstaltung_offen'   => $veranstaltung['offen'],
        'veranstaltung_personen' => $veranstaltung['personen'],
        'veranstaltungen_30_tage' => $veranstaltung['in_30_tagen'],
        'funk_gesamt'           => (int)$funk['anzahl'],
        'funk_mit_karte'        => (int)$funk['mit_karte'],
        'funk_ohne_zuordnung'   => (int)$funk['ohne_zuordnung'],
        'funk_pruefung_bald'    => (int)$funk['pruefung_bald'],
        'funk_nicht_gemeldet'   => (int)$funk['lange_nicht_gesehen'],
        'sim_gesamt'            => (int)$sim['anzahl'],
        'sim_tetra'             => (int)$sim['tetra'],
        'sim_ohne_zuordnung'    => (int)$sim['ohne_zuordnung'],
        'sim_vertrag_bald'      => (int)$sim['vertrag_bald'],
        'sim_kosten_monat'      => round((float)$sim['kosten'], 2),
        'ausgaben_jahr'         => round($ausgaben, 2),
        'einnahmen_jahr'        => round($einnahmen, 2),
        'saldo_jahr'            => round($einnahmen - $ausgaben, 2),
        'connectoren_gekoppelt' => $gekoppelt,
        'connector_letzter_abruf' => ha_zeit(max(
            (int)state_get('connector_letzter_abruf', '0'),
            (int)state_get('veranstaltung_letzter_abruf', '0'),
            (int)state_get('connector_bestand_letzter_abruf', '0')
        )),
        'sicherung_letzte'      => ha_zeit((int)strtotime(state_get('backup_letzte', '') ?: '') ?: null),
        'sicherungen'           => $sicherungen,
        'stein_letzter_abruf'   => ha_zeit((int)state_get('stein_letzter_abruf', '0')),
        'divera_letzter_abruf'  => ha_zeit(max(
            (int)state_get('divera_status_letzter_abruf', '0'),
            (int)state_get('divera_stamm_letzter_abruf', '0')
        )),
        'stand'                 => date('c'),
    ];
}

/**
 * Kennzahlen zur nächsten Veranstaltung. Gemeldet werden nur Zahlen, Zeitpunkt,
 * Titel, Art und Ort – keine Gäste, keine Namen. $kommende ist aufsteigend
 * sortiert; $statsVon liefert die Gästestatistik zu einer Veranstaltung.
 * Reine Funktion.
 */
function ha_veranstaltung_werte(array $kommende, callable $statsVon, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $out = ['naechste' => null, 'info' => [], 'zusagen' => null, 'offen' => null,
            'personen' => null, 'in_30_tagen' => 0];
    $grenze = $jetzt + 30 * 86400;
    $erste = null;
    foreach ($kommende as $e) {
        if (($e['status'] ?? '') === 'abgesagt') {
            continue;
        }
        $beginn = strtotime((string)$e['beginn']);
        if ($beginn === false) {
            continue;
        }
        if ($beginn <= $grenze) {
            $out['in_30_tagen']++;
        }
        $erste ??= $e;
    }
    if ($erste === null) {
        return $out;
    }
    $stats = event_mit_gaesteliste($erste) ? $statsVon($erste) : [];
    $out['naechste'] = ha_zeit((int)strtotime((string)$erste['beginn']));
    $out['info'] = array_filter([
        'titel' => (string)($erste['titel'] ?? ''),
        'art'   => (string)($erste['typ_label'] ?? ''),
        'ort'   => (string)($erste['ort'] ?? ''),
        'datum' => de_date((string)$erste['beginn']),
        'gaesteliste' => event_mit_gaesteliste($erste) ? 'ja' : 'nein',
    ], static fn($x) => $x !== '');
    $out['zusagen'] = event_mit_gaesteliste($erste) ? (int)($stats['zusagen'] ?? 0) : null;
    $out['offen'] = event_mit_gaesteliste($erste) ? (int)($stats['offen'] ?? 0) : null;
    $out['personen'] = event_personen($erste, $stats);
    return $out;
}

/** Werte eines Fahrzeugs. Reine Funktion – ohne Funkkennungen (ISSI, OPTA). */
function ha_fahrzeug_werte(array $v): array
{
    $fms = $v['fms_status'] ?? null;
    return [
        'status'    => (string)($v['status_label'] ?? 'unbekannt'),
        'fms'       => $fms !== null ? (string)(int)$fms : null,
        'hu_bis'    => $v['hu_bis'] ?: null,
        'sp_bis'    => $v['sp_bis'] ?: null,
        'uvv_bis'   => ($v['uvv_bis'] ?? null) ?: null,
        'km_stand'  => $v['km_stand'] !== null ? (int)$v['km_stand'] : null,
        'auftraege' => (int)($v['offene_auftraege'] ?? 0),
        'info'      => array_filter([
            'kennzeichen' => (string)($v['kennzeichen'] ?? ''),
            'funkrufname' => (string)($v['funkrufname'] ?? ''),
            'fachgruppe'  => (string)($v['fachgruppe_label'] ?? ''),
            'fms_hinweis' => (string)($v['fms_note'] ?? ''),
        ], static fn($x) => $x !== ''),
        'latitude'  => $v['geo_lat'] !== null ? (float)$v['geo_lat'] : null,
        'longitude' => $v['geo_lng'] !== null ? (float)$v['geo_lng'] : null,
    ];
}

/** Gerätebeschreibung für die Anmeldung. Reine Funktion. */
function ha_device(string $basis, string $ovName, string $version, ?array $fahrzeug = null): array
{
    if ($fahrzeug === null) {
        return [
            'identifiers'  => [$basis],
            'name'         => 'OV-Budget',
            'manufacturer' => 'OV-Budget',
            'model'        => $ovName,
            'sw_version'   => $version,
        ];
    }
    return [
        'identifiers'  => [$basis . '_fz_' . (int)$fahrzeug['id']],
        'name'         => (string)$fahrzeug['bezeichnung'],
        'manufacturer' => 'OV-Budget',
        'model'        => trim((string)($fahrzeug['typ_label'] ?? '') ?: 'Fahrzeug'),
        'via_device'   => $basis,
        'sw_version'   => $version,
    ];
}

/**
 * Anmeldungen (Discovery) als [topic, payload, retain].
 * Reine Funktion – $fahrzeuge ist die Liste der zu meldenden Fahrzeuge.
 */
function ha_discovery_messages(string $basis, string $ovName, string $version, array $fahrzeuge, bool $mitPosition): array
{
    $prefix = 'homeassistant';
    $geraet = ha_device($basis, $ovName, $version);
    $out = [];

    foreach (ha_sensoren() as $key => $s) {
        $cfg = [
            'name'                => $s['name'],
            'unique_id'           => $basis . '_' . $key,
            'object_id'           => $basis . '_' . $key,
            'state_topic'         => $basis . '/status',
            'value_template'      => '{{ value_json.' . $key . ' }}',
            'availability_topic'  => $basis . '/verfuegbar',
            'device'              => $geraet,
        ];
        foreach (['unit' => 'unit_of_measurement', 'device_class' => 'device_class',
                  'state_class' => 'state_class', 'icon' => 'icon'] as $von => $nach) {
            if (!empty($s[$von])) {
                $cfg[$nach] = $s[$von];
            }
        }
        if (!empty($s['attribute'])) {
            $cfg['json_attributes_topic'] = $basis . '/status';
            $cfg['json_attributes_template'] = '{{ value_json.' . $s['attribute'] . ' | tojson }}';
        }
        if (!empty($s['diagnose'])) {
            $cfg['entity_category'] = 'diagnostic';
        }
        $out[] = [$prefix . '/sensor/' . $basis . '/' . $key . '/config',
                  json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true];
    }

    foreach ($fahrzeuge as $v) {
        $id = (int)$v['id'];
        $thema = $basis . '/fahrzeug/' . $id . '/status';
        $fzGeraet = ha_device($basis, $ovName, $version, $v);
        foreach (ha_fahrzeug_sensoren() as $key => $s) {
            $cfg = [
                'name'               => $s['name'],
                'unique_id'          => $basis . '_fz' . $id . '_' . $key,
                'object_id'          => $basis . '_fz' . $id . '_' . $key,
                'state_topic'        => $thema,
                'value_template'     => '{{ value_json.' . $key . ' }}',
                'availability_topic' => $basis . '/verfuegbar',
                'device'             => $fzGeraet,
            ];
            foreach (['unit' => 'unit_of_measurement', 'device_class' => 'device_class',
                      'state_class' => 'state_class', 'icon' => 'icon'] as $von => $nach) {
                if (!empty($s[$von])) {
                    $cfg[$nach] = $s[$von];
                }
            }
            if ($key === 'status') {
                $cfg['json_attributes_topic'] = $thema;
                $cfg['json_attributes_template'] = '{{ value_json.info | tojson }}';
            }
            $out[] = [$prefix . '/sensor/' . $basis . '/fz' . $id . '_' . $key . '/config',
                      json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true];
        }
        if ($mitPosition) {
            $cfg = [
                'name'               => 'Standort',
                'unique_id'          => $basis . '_fz' . $id . '_position',
                'object_id'          => $basis . '_fz' . $id . '_position',
                'state_topic'        => $basis . '/fahrzeug/' . $id . '/position',
                'json_attributes_topic' => $basis . '/fahrzeug/' . $id . '/position',
                'availability_topic' => $basis . '/verfuegbar',
                'device'             => $fzGeraet,
                'source_type'        => 'gps',
            ];
            $out[] = [$prefix . '/device_tracker/' . $basis . '/fz' . $id . '_position/config',
                      json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true];
        }
    }
    return $out;
}

/** Anmeldungen zurücknehmen: leere Nachricht auf jedes Konfigurationsthema */
function ha_discovery_remove(array $discovery): array
{
    return array_map(static fn($m) => [$m[0], '', true], $discovery);
}

/** Wertnachrichten. Reine Funktion. */
function ha_state_messages(string $basis, array $werte, array $fahrzeuge, bool $mitPosition): array
{
    $out = [[$basis . '/verfuegbar', 'online', true]];
    $out[] = [$basis . '/status', (string)json_encode($werte, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true];

    foreach ($fahrzeuge as $v) {
        $id = (int)$v['id'];
        $w = ha_fahrzeug_werte($v);
        $out[] = [$basis . '/fahrzeug/' . $id . '/status',
                  (string)json_encode($w, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true];
        if ($mitPosition && $w['latitude'] !== null && $w['longitude'] !== null) {
            $out[] = [$basis . '/fahrzeug/' . $id . '/position', (string)json_encode([
                'latitude'  => $w['latitude'],
                'longitude' => $w['longitude'],
                'gps_accuracy' => 50,
            ], JSON_UNESCAPED_SLASHES), true];
        }
    }
    return $out;
}

/** Welche Fahrzeuge werden einzeln gemeldet? */
function ha_fahrzeuge(): array
{
    return setting_bool('ha_mqtt_fahrzeuge', false) ? vehicle_query(['nur_aktive' => 1]) : [];
}

/**
 * Alles senden. $mitDiscovery meldet die Entitäten (neu) an.
 * Rückgabe: ['nachrichten' => int, 'entitaeten' => int]
 */
function ha_publish(bool $mitDiscovery = true): array
{
    $basis = ha_basis();
    $fahrzeuge = ha_fahrzeuge();
    $position = setting_bool('ha_mqtt_position', false);

    $nachrichten = [];
    $discovery = [];
    if ($mitDiscovery) {
        $discovery = ha_discovery_messages($basis, (string)setting('ov_name', 'THW Ortsverband'),
            app_version(), $fahrzeuge, $position);
        $nachrichten = $discovery;
    }
    $nachrichten = array_merge($nachrichten, ha_state_messages($basis, ha_werte(), $fahrzeuge, $position));

    $anzahl = mqtt_publish($nachrichten);
    state_save('ha_mqtt_letzter_lauf', (string)time());
    if ($mitDiscovery) {
        state_save('ha_mqtt_letzte_anmeldung', (string)time());
    }
    return ['nachrichten' => $anzahl, 'entitaeten' => count($discovery)];
}

/** Entitäten in Home Assistant wieder entfernen */
function ha_entfernen(): int
{
    $basis = ha_basis();
    $discovery = ha_discovery_messages($basis, (string)setting('ov_name', ''), app_version(),
        vehicle_query(['nur_aktive' => 1]), true);
    $nachrichten = ha_discovery_remove($discovery);
    $nachrichten[] = [$basis . '/verfuegbar', 'offline', true];
    $anzahl = mqtt_publish($nachrichten);
    state_save('ha_mqtt_letzte_anmeldung', '0');
    return $anzahl;
}

/**
 * Ist ein Lauf fällig? Die Anmeldung wird einmal am Tag wiederholt, damit
 * neue Fahrzeuge und Umbenennungen in Home Assistant ankommen.
 */
function ha_due(?int $jetzt = null): array
{
    $jetzt ??= time();
    if (!setting_bool('ha_mqtt_aktiv', false)) {
        return ['faellig' => false, 'discovery' => false];
    }
    $intervall = max(1, setting_int('ha_mqtt_intervall_minuten', 5)) * 60;
    $letzter = (int)state_get('ha_mqtt_letzter_lauf', '0');
    $anmeldung = (int)state_get('ha_mqtt_letzte_anmeldung', '0');
    return [
        'faellig'   => $jetzt - $letzter >= $intervall,
        'discovery' => $jetzt - $anmeldung >= 86400,
    ];
}
