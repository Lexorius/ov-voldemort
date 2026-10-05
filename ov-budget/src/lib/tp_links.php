<?php
declare(strict_types=1);

/*
 * Bezüge eines Tagesordnungspunkts: Termine aus dem Kalender, Fahrzeuge und
 * Funkgeräte, über die gesprochen wird. Sie stehen am Punkt, im Protokoll
 * und umgekehrt in Fahrzeugakte, Gerät und Termin („besprochen in").
 */

const TP_LINK_TYPEN = [
    'termin'  => ['label' => 'Termin', 'mehrzahl' => 'Termine'],
    'event'   => ['label' => 'Veranstaltung', 'mehrzahl' => 'Veranstaltungen'],
    'vehicle' => ['label' => 'Fahrzeug', 'mehrzahl' => 'Fahrzeuge'],
    'radio'   => ['label' => 'Funkgerät', 'mehrzahl' => 'Funkgeräte'],
];

function tp_link_typ(string $typ): ?string
{
    return array_key_exists($typ, TP_LINK_TYPEN) ? $typ : null;
}

/** Nur Zahlen größer null, ohne Doppelte. Reine Funktion. */
function tp_link_ids(mixed $roh): array
{
    return array_values(array_unique(array_filter(array_map('intval', (array)$roh), static fn($i) => $i > 0)));
}

/** Verknüpfungen eines Punktes als ids: ['termin' => [...], 'vehicle' => [...], 'radio' => [...]] */
function tp_links(int $tpId): array
{
    $out = array_fill_keys(array_keys(TP_LINK_TYPEN), []);
    foreach (db_all('SELECT typ, ziel_id FROM tp_links WHERE tp_id = ? ORDER BY id', [$tpId]) as $r) {
        $out[(string)$r['typ']][] = (int)$r['ziel_id'];
    }
    return $out;
}

/** Verknüpfungen neu setzen. $post: ['termine' => ids, 'vehicles' => ids, 'radios' => ids] */
function tp_links_speichern(int $tpId, array $post): void
{
    $felder = ['termin' => 'termine', 'event' => 'events', 'vehicle' => 'vehicles', 'radio' => 'radios'];
    db_exec('DELETE FROM tp_links WHERE tp_id = ?', [$tpId]);
    foreach ($felder as $typ => $feld) {
        foreach (tp_link_ids($post[$feld] ?? []) as $id) {
            db_insert('tp_links', ['tp_id' => $tpId, 'typ' => $typ, 'ziel_id' => $id]);
        }
    }
}

/**
 * Verknüpfungen mehrerer Punkte mit Namen und Adressen, zum Anzeigen:
 * [tp_id => [['typ', 'id', 'label', 'url', 'zusatz'], …]]
 */
function tp_links_fuer(array $tpIds): array
{
    $tpIds = tp_link_ids($tpIds);
    if (!$tpIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($tpIds), '?'));
    $out = [];
    foreach (db_all(
        "SELECT l.tp_id, l.typ, l.ziel_id,
                k.titel AS termin_titel, k.beginn AS termin_beginn, k.ganztag AS termin_ganztag,
                ev.titel AS event_titel, ev.beginn AS event_beginn,
                v.bezeichnung AS fahrzeug, v.funkrufname AS fahrzeug_ruf,
                r.bezeichnung AS geraet, r.funkrufname AS geraet_ruf
         FROM tp_links l
         LEFT JOIN calendar_entries k ON k.id = l.ziel_id AND l.typ = 'termin'
         LEFT JOIN events ev ON ev.id = l.ziel_id AND l.typ = 'event'
         LEFT JOIN vehicles v ON v.id = l.ziel_id AND l.typ = 'vehicle'
         LEFT JOIN radios r ON r.id = l.ziel_id AND l.typ = 'radio'
         WHERE l.tp_id IN ($in)
         ORDER BY FIELD(l.typ,'termin','event','vehicle','radio'), l.id",
        $tpIds
    ) as $r) {
        $e = tp_link_anzeige($r);
        if ($e !== null) {
            $out[(int)$r['tp_id']][] = $e;
        }
    }
    return $out;
}

/** Eine Zeile aus tp_links_fuer in Beschriftung und Adresse. Reine Funktion bis auf url(). */
function tp_link_anzeige(array $r): ?array
{
    $typ = (string)$r['typ'];
    $id = (int)$r['ziel_id'];
    return match ($typ) {
        'termin' => $r['termin_titel'] === null ? null : [
            'typ' => 'termin', 'id' => $id, 'label' => (string)$r['termin_titel'],
            'zusatz' => de_date(substr((string)$r['termin_beginn'], 0, 10)) . ((int)($r['termin_ganztag'] ?? 0) === 1 ? '' : ', ' . substr((string)$r['termin_beginn'], 11, 5) . ' Uhr'),
            'url' => url('kalender_edit', ['id' => $id]),
        ],
        'event' => ($r['event_titel'] ?? null) === null ? null : [
            'typ' => 'event', 'id' => $id, 'label' => (string)$r['event_titel'],
            'zusatz' => de_date(substr((string)$r['event_beginn'], 0, 10)),
            'url' => url('event', ['id' => $id]),
        ],
        'vehicle' => $r['fahrzeug'] === null ? null : [
            'typ' => 'vehicle', 'id' => $id, 'label' => (string)$r['fahrzeug'], 'zusatz' => (string)($r['fahrzeug_ruf'] ?? ''),
            'url' => url('vehicle', ['id' => $id]),
        ],
        'radio' => $r['geraet'] === null ? null : [
            'typ' => 'radio', 'id' => $id, 'label' => (string)$r['geraet'], 'zusatz' => (string)($r['geraet_ruf'] ?? ''),
            'url' => url('radio', ['id' => $id]),
        ],
        default => null,
    };
}

/** Als Text fürs Protokoll: „Fahrzeug GKW 1 · Termin Übung (17.10.2026)". Reine Funktion. */
function tp_links_text(array $liste): string
{
    $teile = [];
    foreach ($liste as $l) {
        $teile[] = TP_LINK_TYPEN[$l['typ']]['label'] . ' ' . $l['label'] . ($l['zusatz'] !== '' ? ' (' . $l['zusatz'] . ')' : '');
    }
    return implode(' · ', $teile);
}

/**
 * Kurzbericht einer Veranstaltung für Tagesordnung und Protokoll: Wann, wo,
 * Budgettopf, geplante und gebuchte Kosten, Verpflegungskalkulation.
 */
function tp_event_kurzbericht(int $eventId): ?array
{
    $e = event_find($eventId);
    if (!$e) {
        return null;
    }
    $kosten = event_kosten($eventId);
    $out = [
        'id'       => (int)$e['id'],
        'titel'    => (string)$e['titel'],
        'beginn'   => (string)$e['beginn'],
        'ende'     => $e['ende'] ? (string)$e['ende'] : null,
        'ort'      => (string)$e['ort'],
        'typ'      => (string)($e['typ_label'] ?? ''),
        'status'   => (string)$e['status'],
        'budget'   => (string)($e['budget_name'] ?? ''),
        'geplant'  => (float)$e['kosten_geplant'],
        'gebucht'  => (float)$kosten['ausgaben'],
        'personen' => null,
        'verpflegung' => null,
    ];
    if ((int)($e['verpflegung'] ?? 0) === 1) {
        $personen = verpflegung_personen($e, event_stats(event_guests($eventId)));
        $k = verpflegung_kalkulation($e, verpflegung_saetze((string)$e['beginn']), $personen);
        $out['personen'] = $personen;
        $out['verpflegung'] = $k;
    }
    return $out;
}

/** Kurzberichte zu allen Veranstaltungen in den Bezügen: [event_id => bericht] */
function tp_event_kurzberichte(array $tpLinks): array
{
    $out = [];
    foreach ($tpLinks as $liste) {
        foreach ($liste as $l) {
            if ($l['typ'] === 'event' && !isset($out[(int)$l['id']])) {
                $b = tp_event_kurzbericht((int)$l['id']);
                if ($b !== null) {
                    $out[(int)$l['id']] = $b;
                }
            }
        }
    }
    return $out;
}

/** Der Kurzbericht als Zeilen fürs Protokoll. Reine Funktion. */
function tp_event_kurzbericht_zeilen(array $b): array
{
    $z = [];
    $wann = de_date(substr($b['beginn'], 0, 10)) . (substr($b['beginn'], 11, 5) !== '00:00' ? ', ' . substr($b['beginn'], 11, 5) . ' Uhr' : '')
        . ($b['ende'] && substr($b['ende'], 0, 10) !== substr($b['beginn'], 0, 10) ? ' bis ' . de_date(substr($b['ende'], 0, 10)) : '');
    $z[] = 'Veranstaltung ' . $b['titel'] . ($b['typ'] !== '' ? ' (' . $b['typ'] . ')' : '') . ' – ' . $wann . ($b['ort'] !== '' ? ', ' . $b['ort'] : '');
    $z[] = 'Budgettopf: ' . ($b['budget'] !== '' ? $b['budget'] : 'keiner') . ' · geplant ' . money($b['geplant']) . ' · gebucht ' . money($b['gebucht']);
    if ($b['verpflegung'] !== null) {
        $teile = [];
        foreach ($b['verpflegung']['zeilen'] as $zeile) {
            if ($zeile['anzahl'] > 0) {
                $teile[] = $zeile['anzahl'] . ' × ' . $zeile['label'] . ' à ' . money($zeile['satz']);
            }
        }
        $z[] = 'Verpflegung: ' . (int)$b['personen'] . ' Personen' . ($teile ? ', ' . implode(', ', $teile) : '') . ' = ' . money($b['verpflegung']['gesamt'])
            . ($b['verpflegung']['ohne_satz'] ? ' (Tagessatz fehlt)' : '');
    }
    return $z;
}

/** Tagesordnungspunkte, die auf ein Fahrzeug, Gerät oder einen Termin verweisen – jüngste zuerst */
function tps_fuer(string $typ, int $zielId): array
{
    $typ = tp_link_typ($typ);
    if ($typ === null || $zielId <= 0) {
        return [];
    }
    return db_all(
        tp_select() . ' JOIN tp_links l ON l.tp_id = tp.id WHERE l.typ = ? AND l.ziel_id = ?
         ORDER BY COALESCE(m.datum, tp.created_at) DESC, tp.id DESC LIMIT 50',
        [$typ, $zielId]
    );
}

/** Was im Formular zur Auswahl steht: anstehende Termine, aktive Fahrzeuge und Geräte – plus schon Verknüpftes */
function tp_links_auswahl(array $links, array $u): array
{
    $wochen = max(1, setting_int('kalender_besprechung_wochen', 6));
    $von = date('Y-m-d', time() - 7 * 86400);
    $bis = date('Y-m-d', time() + $wochen * 7 * 86400);
    $termine = [];
    foreach (kalender_termine($von, $bis, $u) as $t) {
        $termine[(int)$t['id']] = $t;
    }
    if ($links['termin']) {
        $in = implode(',', array_fill(0, count($links['termin']), '?'));
        foreach (db_all("SELECT k.*, u.display_name AS ziel_name FROM calendar_entries k LEFT JOIN users u ON u.id = k.ziel_id AND k.ziel = 'user' WHERE k.id IN ($in)", $links['termin']) as $t) {
            $termine[(int)$t['id']] = $t;
        }
    }
    uasort($termine, static fn($a, $b) => strcmp((string)$a['beginn'], (string)$b['beginn']));
    $events = can('view_events') ? db_all(
        "SELECT e.id, e.titel, e.beginn FROM events e WHERE (e.status IN ('geplant','laeuft') AND e.beginn >= ?)"
        . ($links['event'] ? ' OR e.id IN (' . implode(',', $links['event']) . ')' : '') . ' ORDER BY e.beginn',
        [$von . ' 00:00:00']
    ) : [];
    return [
        'termine'  => array_values($termine),
        'events'   => $events,
        'vehicles' => can('view_vehicles') ? db_all('SELECT id, bezeichnung, funkrufname FROM vehicles WHERE is_active = 1' . ($links['vehicle'] ? ' OR id IN (' . implode(',', $links['vehicle']) . ')' : '') . ' ORDER BY bezeichnung') : [],
        'radios'   => can('view_radios') ? db_all('SELECT id, bezeichnung, funkrufname FROM radios WHERE is_active = 1' . ($links['radio'] ? ' OR id IN (' . implode(',', $links['radio']) . ')' : '') . ' ORDER BY bezeichnung') : [],
    ];
}
