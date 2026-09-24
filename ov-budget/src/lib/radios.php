<?php
declare(strict_types=1);

/**
 * Funkgeräte des Ortsverbands.
 *
 * Ein Gerät ist ein Stück Technik mit Seriennummer: HRT, MRT, Feststation,
 * Meldeempfänger. Zwei Verbindungen machen es nützlich:
 *
 *   Karte  →  Gerät    Eine SIM- oder TETRA-Karte wird in ein Gerät gebucht
 *   Gerät  →  Fahrzeug Das Gerät wird einem Fahrzeug zugeordnet
 *                      (oder einer Fachgruppe, einer Person, dem Ortsverband)
 *
 * So lässt sich von der Fahrzeugakte bis zur ISSI durchsehen, ohne dass
 * jemand drei Listen führen muss. Art und Status kommen aus den
 * Auswahllisten und lassen sich in der Verwaltung ändern.
 */

/** Wem ein Gerät gehören kann – dieselben Ziele wie bei den Karten */
const RADIO_ZIELE = SIM_ZIELE;

function radio_ziel_typ(string $typ): string
{
    return sim_ziel_typ($typ);
}

function radio_find(?int $id): ?array
{
    return $id ? db_row(radio_select() . ' WHERE r.id = ?', [$id]) : null;
}

/** Gemeinsamer Rumpf der Abfragen – mit Zuordnung und Kartenzahl */
function radio_select(): string
{
    return 'SELECT r.*,
                   t.label AS typ_label, t.color AS typ_color,
                   st.label AS status_label, st.color AS status_color, st.slug AS status_slug,
                   v.bezeichnung AS fahrzeug_label, v.kennzeichen AS fahrzeug_kennzeichen,
                   fg.label AS fachgruppe_label,
                   u.display_name AS person_label,
                   (SELECT COUNT(*) FROM sims s WHERE s.radio_id = r.id) AS karten,
                   g.name AS gruppe_name, g.lagerort AS gruppe_lagerort
            FROM radios r
            LEFT JOIN radio_groups g ON g.id = r.group_id
            LEFT JOIN list_items t   ON t.id  = r.typ_id
            LEFT JOIN list_items st  ON st.id = r.status_id
            LEFT JOIN vehicles   v   ON v.id  = r.ziel_id AND r.ziel_typ = \'fahrzeug\'
            LEFT JOIN list_items fg  ON fg.id = r.ziel_id AND r.ziel_typ = \'fachgruppe\'
            LEFT JOIN users      u   ON u.id  = r.ziel_id AND r.ziel_typ = \'person\'';
}

/**
 * Liste der Geräte.
 * $f: q, typ_id, status_id, ziel_typ, ziel_id, ohne_karte, aktiv ('alle'), sort
 */
function radio_query(array $f = []): array
{
    $w = [];
    $p = [];

    if (!empty($f['q'])) {
        $w[] = '(r.bezeichnung LIKE ? OR r.seriennummer LIKE ? OR r.inventarnummer LIKE ?'
            . ' OR r.funkrufname LIKE ? OR r.hersteller LIKE ? OR r.modell LIKE ?'
            . ' OR r.standort LIKE ? OR r.notiz LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like, $like, $like, $like, $like);
    }
    foreach (['typ_id', 'status_id'] as $spalte) {
        if (!empty($f[$spalte])) {
            $w[] = 'r.' . $spalte . ' = ?';
            $p[] = (int)$f[$spalte];
        }
    }
    if (!empty($f['ziel_typ'])) {
        $w[] = 'r.ziel_typ = ?';
        $p[] = radio_ziel_typ((string)$f['ziel_typ']);
        if (!empty($f['ziel_id'])) {
            $w[] = 'r.ziel_id = ?';
            $p[] = (int)$f['ziel_id'];
        }
    }
    if (!empty($f['group_id'])) {
        $w[] = 'r.group_id = ?';
        $p[] = (int)$f['group_id'];
    }
    if (!empty($f['ohne_gruppe'])) {
        $w[] = 'r.group_id IS NULL';
    }
    if (!empty($f['ohne_karte'])) {
        $w[] = 'NOT EXISTS (SELECT 1 FROM sims s WHERE s.radio_id = r.id)';
    }
    if (($f['aktiv'] ?? '') !== 'alle') {
        $w[] = 'r.is_active = 1';
    }

    $order = match ($f['sort'] ?? '') {
        'pruefung' => 'r.pruefung_bis IS NULL, r.pruefung_bis ASC',
        'ziel'     => 'r.ziel_typ, r.bezeichnung',
        'neu'      => 'r.created_at DESC',
        default    => 't.sort_order, r.bezeichnung',
    };

    return db_all(radio_select()
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY ' . $order, $p);
}

/** Geräte eines Fahrzeugs – für die Fahrzeugakte */
function radio_for_vehicle(int $vehicleId): array
{
    return radio_query(['ziel_typ' => 'fahrzeug', 'ziel_id' => $vehicleId]);
}

/** Wem gehört das Gerät? Reine Funktion. */
function radio_ziel_text(array $radio): string
{
    return sim_ziel_text($radio);
}

/** Karten, die in diesem Gerät stecken */
function radio_cards(int $radioId): array
{
    return db_all(sim_select() . ' WHERE s.radio_id = ? ORDER BY s.karte_art, s.rufnummer, s.issi',
        [$radioId]);
}

/** Karten, die in keinem Gerät stecken – zum Einbuchen */
function radio_cards_free(): array
{
    return db_all(sim_select() . ' WHERE s.radio_id IS NULL AND s.is_active = 1'
        . ' ORDER BY s.karte_art, s.rufnummer, s.issi');
}

/**
 * Wann ist die Prüfung fällig? Reine Funktion.
 * Rückgabe: ['tage' => int|null, 'stufe' => 'ok'|'bald'|'faellig'|'offen']
 */
function radio_pruefung(array $radio, int $warnTage = 30, ?string $heute = null): array
{
    $bis = trim((string)($radio['pruefung_bis'] ?? ''));
    if ($bis === '') {
        return ['tage' => null, 'stufe' => 'offen'];
    }
    $heute ??= date('Y-m-d');
    $tage = (int)floor((strtotime($bis) - strtotime($heute)) / 86400);
    return [
        'tage'  => $tage,
        'stufe' => $tage < 0 ? 'faellig' : ($tage <= $warnTage ? 'bald' : 'ok'),
    ];
}

/** Zahlen für den Kopf der Liste. Reine Funktion. */
function radio_stats(array $radios, int $warnTage = 30, ?string $heute = null): array
{
    $s = ['anzahl' => count($radios), 'mit_karte' => 0, 'ohne_zuordnung' => 0,
          'pruefung_bald' => 0, 'lange_nicht_gesehen' => 0];
    foreach ($radios as $r) {
        if ((int)($r['karten'] ?? 0) > 0) {
            $s['mit_karte']++;
        }
        if ((string)$r['ziel_typ'] === 'ov') {
            $s['ohne_zuordnung']++;
        }
        if (in_array(radio_pruefung($r, $warnTage, $heute)['stufe'], ['bald', 'faellig'], true)) {
            $s['pruefung_bald']++;
        }
        if (radio_gesehen($r)['stufe'] !== 'frisch') {
            $s['lange_nicht_gesehen']++;
        }
    }
    return $s;
}

/* ==================================================================== */
/* Gruppen: ein Koffer, eine Ladeschale, ein Satz                        */
/* ==================================================================== */

function radio_group_select(): string
{
    return 'SELECT g.*,
                   v.bezeichnung AS fahrzeug_label, v.kennzeichen AS fahrzeug_kennzeichen,
                   fg.label AS fachgruppe_label,
                   u.display_name AS person_label,
                   (SELECT COUNT(*) FROM radios r WHERE r.group_id = g.id AND r.is_active = 1) AS geraete
            FROM radio_groups g
            LEFT JOIN vehicles   v  ON v.id  = g.ziel_id AND g.ziel_typ = \'fahrzeug\'
            LEFT JOIN list_items fg ON fg.id = g.ziel_id AND g.ziel_typ = \'fachgruppe\'
            LEFT JOIN users      u  ON u.id  = g.ziel_id AND g.ziel_typ = \'person\'';
}

function radio_group_find(?int $id): ?array
{
    return $id ? db_row(radio_group_select() . ' WHERE g.id = ?', [$id]) : null;
}

function radio_group_all(bool $nurAktive = true): array
{
    return db_all(radio_group_select()
        . ($nurAktive ? ' WHERE g.is_active = 1' : '')
        . ' ORDER BY g.name');
}

/** Die Geräte einer Gruppe */
function radio_group_members(int $groupId): array
{
    return radio_query(['group_id' => $groupId, 'aktiv' => 'alle']);
}

/** Gruppe aus dem Formular speichern. Gibt [id, fehler[]] zurück. */
function radio_group_save_from_post(?array $gruppe, array $user): array
{
    $name = mb_substr(post_str('name'), 0, 150);
    if ($name === '') {
        return [null, ['Bitte einen Namen angeben – etwa „HRT-Koffer Zugtrupp".']];
    }

    $zielTyp = radio_ziel_typ(post_str('ziel_typ', 'ov'));
    $zielId = $zielTyp === 'ov' ? null : (post_int('ziel_id_' . $zielTyp) ?? post_int('ziel_id'));
    if ($zielTyp !== 'ov' && !$zielId) {
        return [null, ['Bitte auswählen, zu wem die Gruppe gehört.']];
    }

    $daten = [
        'name'         => $name,
        'beschreibung' => post_str('beschreibung'),
        'lagerort'     => mb_substr(post_str('lagerort'), 0, 150),
        'ziel_typ'     => $zielTyp,
        'ziel_id'      => $zielId,
        'is_active'    => post_bool('is_active'),
    ];

    if ($gruppe) {
        db_update('radio_groups', $daten, 'id = ?', [(int)$gruppe['id']]);
        $id = (int)$gruppe['id'];
        audit('funkgruppe.bearbeitet', 'radio_group', $id, $name);
    } else {
        $id = db_insert('radio_groups', $daten);
        audit('funkgruppe.angelegt', 'radio_group', $id, $name);
    }
    return [$id, []];
}

function radio_group_delete(array $gruppe): void
{
    db_exec('UPDATE radios SET group_id = NULL WHERE group_id = ?', [(int)$gruppe['id']]);
    db_exec('DELETE FROM radio_groups WHERE id = ?', [(int)$gruppe['id']]);
    audit('funkgruppe.geloescht', 'radio_group', (int)$gruppe['id'], (string)$gruppe['name']);
}

/* ==================================================================== */
/* Bestandsmeldung per QR-Code                                           */
/* ==================================================================== */

/**
 * Was auf dem QR-Code steht – Bezeichnung für den Anker der Adresse.
 * Der Connector erfährt sie nie. Reine Funktionen.
 */
function radio_qr_name(array $radio): string
{
    return trim(implode(' · ', array_filter([
        trim((string)($radio['bezeichnung'] ?? '')),
        trim((string)($radio['funkrufname'] ?? '')),
    ])));
}

function radio_group_qr_name(array $gruppe): string
{
    $anzahl = (int)($gruppe['geraete'] ?? 0);
    return trim((string)($gruppe['name'] ?? ''))
        . ($anzahl > 0 ? ' · ' . $anzahl . ' Geräte' : '');
}

/**
 * Wie lange ist die letzte Sichtung her? Reine Funktion.
 * Rückgabe: ['tage' => int|null, 'stufe' => 'frisch'|'alt'|'nie']
 */
function radio_gesehen(array $ziel, int $frischTage = 30, ?string $jetzt = null): array
{
    $wann = trim((string)($ziel['zuletzt_gesehen'] ?? ''));
    if ($wann === '') {
        return ['tage' => null, 'stufe' => 'nie'];
    }
    $jetzt ??= date('Y-m-d H:i:s');
    $tage = (int)floor((strtotime($jetzt) - strtotime($wann)) / 86400);
    return ['tage' => $tage, 'stufe' => $tage <= $frischTage ? 'frisch' : 'alt'];
}

/**
 * Eine Bestandsmeldung eintragen – vom QR-Code am Lagerort.
 * $art: 'geraet' oder 'gruppe'. Rückgabe: Klartext fürs Protokoll.
 */
function radio_bestand_anwenden(string $art, array $ziel, array $daten, int $ts): string
{
    $wann = date('Y-m-d H:i:s', $ts > 0 ? $ts : time());
    $melder = mb_substr(trim((string)($daten['melder'] ?? '')), 0, 60);

    if ($art === 'gruppe') {
        $gesamt = (int)($ziel['geraete'] ?? 0);
        $anzahl = $daten['anzahl'] ?? null;
        $anzahl = $anzahl === null || $anzahl === '' ? $gesamt : max(0, min(9999, (int)$anzahl));
        db_update('radio_groups', [
            'zuletzt_gesehen' => $wann,
            'zuletzt_anzahl'  => $anzahl,
            'zuletzt_melder'  => $melder,
        ], 'id = ?', [(int)$ziel['id']]);

        // Vollzählig? Dann gelten alle Geräte der Gruppe als gesehen
        if ($anzahl >= $gesamt && $gesamt > 0) {
            db_update('radios', ['zuletzt_gesehen' => $wann, 'zuletzt_melder' => $melder],
                'group_id = ?', [(int)$ziel['id']]);
        }
        $text = sprintf('%s: %d von %d Geräten am Lagerort', (string)$ziel['name'], $anzahl, $gesamt);
    } else {
        db_update('radios', ['zuletzt_gesehen' => $wann, 'zuletzt_melder' => $melder],
            'id = ?', [(int)$ziel['id']]);
        $text = sprintf('%s am Lagerort', (string)$ziel['bezeichnung']);
    }

    audit('funk.bestand', $art === 'gruppe' ? 'radio_group' : 'radio', (int)$ziel['id'],
        $text . ($melder !== '' ? ' (' . $melder . ')' : ''));
    return $text;
}

/** Gerät aus dem Formular speichern. Gibt [id, fehler[]] zurück. */
function radio_save_from_post(?array $radio, array $user): array
{
    $fehler = [];

    $bezeichnung = mb_substr(post_str('bezeichnung'), 0, 150);
    if ($bezeichnung === '') {
        $fehler[] = 'Bitte eine Bezeichnung angeben – etwa „MRT GKW 1" oder „HRT 03".';
    }

    $zielTyp = radio_ziel_typ(post_str('ziel_typ', 'ov'));
    $zielId = $zielTyp === 'ov' ? null : (post_int('ziel_id_' . $zielTyp) ?? post_int('ziel_id'));
    if ($zielTyp !== 'ov' && !$zielId) {
        $fehler[] = 'Bitte auswählen, zu wem das Gerät gehört.';
    }

    $seriennummer = mb_substr(post_str('seriennummer'), 0, 60);
    if ($seriennummer !== '') {
        $doppelt = db_val('SELECT id FROM radios WHERE seriennummer = ? AND seriennummer <> \'\' AND id <> ?',
            [$seriennummer, (int)($radio['id'] ?? 0)]);
        if ($doppelt) {
            $fehler[] = 'Diese Seriennummer steht schon an einem anderen Gerät.';
        }
    }

    if ($fehler) {
        return [null, $fehler];
    }

    $daten = [
        'bezeichnung'    => $bezeichnung,
        'typ_id'         => post_int('typ_id'),
        'status_id'      => post_int('status_id'),
        'hersteller'     => mb_substr(post_str('hersteller'), 0, 80),
        'modell'         => mb_substr(post_str('modell'), 0, 80),
        'seriennummer'   => $seriennummer,
        'inventarnummer' => mb_substr(post_str('inventarnummer'), 0, 60),
        'funkrufname'    => mb_substr(post_str('funkrufname'), 0, 80),
        'group_id'       => post_int('group_id'),
        'ziel_typ'       => $zielTyp,
        'ziel_id'        => $zielId,
        'standort'       => mb_substr(post_str('standort'), 0, 150),
        'beschafft_am'   => post_date('beschafft_am'),
        'pruefung_bis'   => post_date('pruefung_bis'),
        'notiz'          => post_str('notiz'),
        'is_active'      => post_bool('is_active'),
        'updated_by'     => (int)$user['id'],
    ];

    if ($radio) {
        db_update('radios', $daten, 'id = ?', [(int)$radio['id']]);
        $id = (int)$radio['id'];
        audit('funk.bearbeitet', 'radio', $id, $bezeichnung);
    } else {
        $daten['created_by'] = (int)$user['id'];
        $id = db_insert('radios', $daten);
        audit('funk.angelegt', 'radio', $id, $bezeichnung);
    }
    return [$id, []];
}

function radio_delete(array $radio): void
{
    // Karten im Gerät bleiben bestehen und liegen danach wieder frei
    db_exec('UPDATE sims SET radio_id = NULL WHERE radio_id = ?', [(int)$radio['id']]);
    db_exec('DELETE FROM radios WHERE id = ?', [(int)$radio['id']]);
    audit('funk.geloescht', 'radio', (int)$radio['id'], (string)$radio['bezeichnung']);
}

/**
 * Karte in ein Gerät buchen. Auf Wunsch übernimmt die Karte auch die
 * Zuordnung des Geräts – steckt sie im MRT des GKW 1, gehört sie dorthin.
 */
function radio_card_add(array $radio, array $sim, array $user, bool $zuordnungUebernehmen = true): void
{
    $daten = ['radio_id' => (int)$radio['id'], 'updated_by' => (int)$user['id']];
    if ($zuordnungUebernehmen) {
        $daten['ziel_typ'] = (string)$radio['ziel_typ'];
        $daten['ziel_id'] = $radio['ziel_id'] !== null ? (int)$radio['ziel_id'] : null;
    }
    db_update('sims', $daten, 'id = ?', [(int)$sim['id']]);
    audit('funk.karte', 'radio', (int)$radio['id'],
        sprintf('%s eingebucht', sim_bezeichnung($sim)));
}

/** Karte wieder aus dem Gerät nehmen */
function radio_card_remove(array $sim, array $user): void
{
    db_update('sims', ['radio_id' => null, 'updated_by' => (int)$user['id']], 'id = ?', [(int)$sim['id']]);
    audit('funk.karte_weg', 'sim', (int)$sim['id'], sim_bezeichnung($sim));
}
