<?php
declare(strict_types=1);

/**
 * SIM-Karten des Ortsverbands.
 *
 * Eine Karte steckt selten allein herum: Sie gehört zu einem Fahrzeug
 * (Router im GKW), zu einer Fachgruppe (Tablet der FGr N), zu einer Person
 * (Diensthandy) oder allgemein zum Ortsverband. Genau das hält die
 * Zuordnung fest – Art und Status kommen aus den Auswahllisten und lassen
 * sich in der Verwaltung ändern.
 *
 * Zwei Kartenwelten stecken darin: Mobilfunkkarten (Rufnummer, ICCID,
 * Vertrag) und TETRA-Sicherheitskarten (ISSI, OPTA, keine Rufnummer).
 * Vertrag und PIN gibt es nur, wenn die Karte sie hat – beides wird im
 * Formular zugeschaltet.
 *
 * PIN und PUK stehen hier, weil sie sonst auf einem Zettel im Schrank
 * liegen. Sie sind der Leitung vorbehalten und werden in der Liste
 * verdeckt angezeigt.
 */

/** Welche Art von Karte – danach richten sich die Felder */
const SIM_ARTEN = [
    'mobilfunk' => 'Mobilfunk (SIM)',
    'tetra'     => 'TETRA-Sicherheitskarte',
];

function sim_karte_art(string $art): string
{
    return array_key_exists($art, SIM_ARTEN) ? $art : 'mobilfunk';
}

/** Ist das eine TETRA-Karte? Reine Funktion. */
function sim_ist_tetra(array $sim): bool
{
    return (string)($sim['karte_art'] ?? 'mobilfunk') === 'tetra';
}

/** Führt die Karte einen Vertrag? Reine Funktion. */
function sim_hat_vertrag(array $sim): bool
{
    return (int)($sim['hat_vertrag'] ?? 0) === 1;
}

/** Sind PIN und PUK hinterlegt? Reine Funktion. */
function sim_hat_pin(array $sim): bool
{
    return (int)($sim['hat_pin'] ?? 0) === 1;
}

/** Wem eine Karte gehören kann */
const SIM_ZIELE = [
    'ov'         => 'Ortsverband',
    'fahrzeug'   => 'Fahrzeug',
    'fachgruppe' => 'Fachgruppe',
    'person'     => 'Person',
];

function sim_ziel_typ(string $typ): string
{
    return array_key_exists($typ, SIM_ZIELE) ? $typ : 'ov';
}

function sim_find(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    return db_row(sim_select() . ' WHERE s.id = ?', [$id]);
}

/** Gemeinsamer Rumpf der Abfragen – mit allem, was die Anzeige braucht */
function sim_select(): string
{
    return 'SELECT s.*,
                   t.label AS typ_label, t.color AS typ_color,
                   st.label AS status_label, st.color AS status_color,
                   st.slug AS status_slug, st.is_final AS status_final,
                   v.bezeichnung AS fahrzeug_label, v.kennzeichen AS fahrzeug_kennzeichen,
                   fg.label AS fachgruppe_label,
                   u.display_name AS person_label
            FROM sims s
            LEFT JOIN list_items t   ON t.id  = s.typ_id
            LEFT JOIN list_items st  ON st.id = s.status_id
            LEFT JOIN vehicles   v   ON v.id  = s.ziel_id AND s.ziel_typ = \'fahrzeug\'
            LEFT JOIN list_items fg  ON fg.id = s.ziel_id AND s.ziel_typ = \'fachgruppe\'
            LEFT JOIN users      u   ON u.id  = s.ziel_id AND s.ziel_typ = \'person\'';
}

/**
 * Liste der Karten.
 * $f: q, typ_id, status_id, ziel_typ, ziel_id, aktiv ('alle'), sort
 */
function sim_query(array $f = []): array
{
    $w = [];
    $p = [];

    if (!empty($f['q'])) {
        $w[] = '(s.rufnummer LIKE ? OR s.iccid LIKE ? OR s.issi LIKE ? OR s.opta LIKE ?'
            . ' OR s.anbieter LIKE ? OR s.geraet LIKE ? OR s.tarif LIKE ? OR s.notiz LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like, $like, $like, $like, $like);
    }
    foreach (['typ_id', 'status_id'] as $spalte) {
        if (!empty($f[$spalte])) {
            $w[] = 's.' . $spalte . ' = ?';
            $p[] = (int)$f[$spalte];
        }
    }
    if (!empty($f['karte_art'])) {
        $w[] = 's.karte_art = ?';
        $p[] = sim_karte_art((string)$f['karte_art']);
    }
    if (!empty($f['ziel_typ'])) {
        $w[] = 's.ziel_typ = ?';
        $p[] = sim_ziel_typ((string)$f['ziel_typ']);
        if (!empty($f['ziel_id'])) {
            $w[] = 's.ziel_id = ?';
            $p[] = (int)$f['ziel_id'];
        }
    }
    if (($f['aktiv'] ?? '') !== 'alle') {
        $w[] = 's.is_active = 1';
    }

    $order = match ($f['sort'] ?? '') {
        'nummer'  => 's.rufnummer ASC',
        'vertrag' => 's.vertrag_bis IS NULL, s.vertrag_bis ASC',
        'kosten'  => 's.kosten_monat DESC',
        'neu'     => 's.created_at DESC',
        default   => 's.karte_art, t.sort_order, s.ziel_typ, s.rufnummer, s.issi',
    };

    return db_all(sim_select()
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY ' . $order, $p);
}

/** Karten eines Fahrzeugs – für die Fahrzeugakte */
function sim_for_vehicle(int $vehicleId): array
{
    return sim_query(['ziel_typ' => 'fahrzeug', 'ziel_id' => $vehicleId]);
}

/** Wem gehört die Karte? Reine Funktion. */
function sim_ziel_text(array $sim): string
{
    return match ((string)$sim['ziel_typ']) {
        'fahrzeug'   => trim((string)($sim['fahrzeug_label'] ?? '')) !== ''
            ? trim((string)$sim['fahrzeug_label']
                . ((string)($sim['fahrzeug_kennzeichen'] ?? '') !== ''
                    ? ' · ' . (string)$sim['fahrzeug_kennzeichen'] : ''))
            : 'Fahrzeug (gelöscht)',
        'fachgruppe' => trim((string)($sim['fachgruppe_label'] ?? '')) !== ''
            ? (string)$sim['fachgruppe_label'] : 'Fachgruppe (gelöscht)',
        'person'     => trim((string)($sim['person_label'] ?? '')) !== ''
            ? (string)$sim['person_label'] : 'Person (gelöscht)',
        default      => 'Ortsverband',
    };
}

/**
 * Woran man die Karte erkennt: bei Mobilfunk die Rufnummer, bei TETRA die
 * ISSI. Reine Funktion.
 */
function sim_kennung(array $sim): string
{
    if (sim_ist_tetra($sim)) {
        $issi = trim((string)($sim['issi'] ?? ''));
        $opta = trim((string)($sim['opta'] ?? ''));
        return $issi !== '' ? $issi : ($opta !== '' ? $opta : trim((string)($sim['iccid'] ?? '')));
    }
    return trim((string)($sim['rufnummer'] ?? '')) !== ''
        ? trim((string)$sim['rufnummer'])
        : trim((string)($sim['iccid'] ?? ''));
}

/** Verdeckte Anzeige für PIN und PUK. Reine Funktion. */
function sim_verdeckt(string $wert): string
{
    $wert = trim($wert);
    return $wert === '' ? '' : str_repeat('•', max(4, mb_strlen($wert)));
}

/**
 * Wie lange läuft der Vertrag noch? Reine Funktion.
 * Rückgabe: ['tage' => int|null, 'stufe' => 'ok'|'bald'|'faellig'|'offen']
 */
function sim_vertrag(array $sim, int $warnTage = 60, ?string $heute = null): array
{
    $bis = sim_hat_vertrag($sim) ? trim((string)($sim['vertrag_bis'] ?? '')) : '';
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
function sim_stats(array $sims, int $warnTage = 60, ?string $heute = null): array
{
    $s = ['anzahl' => count($sims), 'kosten' => 0.0, 'ohne_zuordnung' => 0,
          'vertrag_bald' => 0, 'tetra' => 0];
    foreach ($sims as $sim) {
        if (sim_hat_vertrag($sim)) {
            $s['kosten'] += (float)($sim['kosten_monat'] ?? 0);
        }
        if ((string)$sim['ziel_typ'] === 'ov') {
            $s['ohne_zuordnung']++;
        }
        if (sim_ist_tetra($sim)) {
            $s['tetra']++;
        }
        $vertrag = sim_vertrag($sim, $warnTage, $heute);
        if (in_array($vertrag['stufe'], ['bald', 'faellig'], true)) {
            $s['vertrag_bald']++;
        }
    }
    return $s;
}

/** Karte aus dem Formular speichern. Gibt [id, fehler[]] zurück. */
function sim_save_from_post(?array $sim, array $user): array
{
    $fehler = [];

    $karteArt = sim_karte_art(post_str('karte_art', 'mobilfunk'));
    $tetra = $karteArt === 'tetra';

    // Wie im Kontaktmodul: international geschrieben, Unlesbares bleibt stehen
    $rufnummer = $tetra ? '' : phone_human(post_str('rufnummer'));
    $iccid = preg_replace('/[^0-9A-Za-z]/', '', post_str('iccid')) ?? '';
    $issi = $tetra ? preg_replace('/[^0-9]/', '', post_str('issi')) ?? '' : '';
    $opta = $tetra ? mb_substr(post_str('opta'), 0, 60) : '';

    if ($tetra) {
        if ($issi === '' && $iccid === '' && $opta === '') {
            $fehler[] = 'Bitte wenigstens ISSI, OPTA oder die Kartennummer angeben.';
        }
        if ($issi !== '' && (mb_strlen($issi) < 5 || mb_strlen($issi) > 16)) {
            $fehler[] = 'Die ISSI besteht aus Ziffern und hat üblicherweise 7 bis 16 Stellen.';
        }
    } else {
        if ($rufnummer === '' && $iccid === '') {
            $fehler[] = 'Bitte wenigstens die Rufnummer oder die Kartennummer (ICCID) angeben.';
        }
        if ($iccid !== '' && (mb_strlen($iccid) < 10 || mb_strlen($iccid) > 22)) {
            $fehler[] = 'Die Kartennummer (ICCID) hat üblicherweise 18 bis 22 Stellen.';
        }
    }

    // Vertrag und PIN stehen nur in der Karte, wenn sie angehakt sind
    $hatVertrag = post_bool('hat_vertrag');
    $hatPin = post_bool('hat_pin');

    // Im Formular steht je Art ein eigenes Auswahlfeld; gültig ist das zur
    // gewählten Art. So klappt es auch ohne JavaScript.
    $zielTyp = sim_ziel_typ(post_str('ziel_typ', 'ov'));
    $zielId = $zielTyp === 'ov' ? null : (post_int('ziel_id_' . $zielTyp) ?? post_int('ziel_id'));
    if ($zielTyp !== 'ov' && !$zielId) {
        $fehler[] = 'Bitte auswählen, zu wem die Karte gehört.';
    }

    // Dieselbe ISSI zweimal gibt es im Funknetz nicht
    if ($issi !== '') {
        $doppeltIssi = db_val('SELECT id FROM sims WHERE issi = ? AND issi <> \'\' AND id <> ?',
            [$issi, (int)($sim['id'] ?? 0)]);
        if ($doppeltIssi) {
            $fehler[] = 'Diese ISSI steht schon auf einer anderen Karte.';
        }
    }

    // Dieselbe Rufnummer zweimal ist fast immer ein Versehen
    if ($rufnummer !== '') {
        $doppelt = db_val('SELECT id FROM sims WHERE rufnummer = ? AND id <> ?',
            [$rufnummer, (int)($sim['id'] ?? 0)]);
        if ($doppelt) {
            $fehler[] = 'Diese Rufnummer steht schon auf einer anderen Karte.';
        }
    }

    if ($fehler) {
        return [null, $fehler];
    }

    $daten = [
        'karte_art'     => $karteArt,
        'rufnummer'     => mb_substr($rufnummer, 0, 40),
        'iccid'         => mb_substr($iccid, 0, 30),
        'issi'          => mb_substr($issi, 0, 40),
        'opta'          => $opta,
        'typ_id'        => post_int('typ_id'),
        'status_id'     => post_int('status_id'),
        'hat_vertrag'   => $hatVertrag,
        'anbieter'      => $hatVertrag ? mb_substr(post_str('anbieter'), 0, 80) : '',
        'tarif'         => $hatVertrag ? mb_substr(post_str('tarif'), 0, 120) : '',
        'datenvolumen'  => $hatVertrag ? mb_substr(post_str('datenvolumen'), 0, 40) : '',
        'kosten_monat'  => $hatVertrag && post_str('kosten_monat') !== '' ? post_dec('kosten_monat') : null,
        'vertrag_bis'   => $hatVertrag ? post_date('vertrag_bis') : null,
        'hat_pin'       => $hatPin,
        'pin'           => $hatPin ? mb_substr(post_str('pin'), 0, 20) : '',
        'puk'           => $hatPin ? mb_substr(post_str('puk'), 0, 30) : '',
        'geraet'        => mb_substr(post_str('geraet'), 0, 150),
        'ziel_typ'      => $zielTyp,
        'ziel_id'       => $zielId,
        'ausgegeben_am' => post_date('ausgegeben_am'),
        'notiz'         => post_str('notiz'),
        'is_active'     => post_bool('is_active'),
        'updated_by'    => (int)$user['id'],
    ];

    if ($sim) {
        db_update('sims', $daten, 'id = ?', [(int)$sim['id']]);
        $id = (int)$sim['id'];
        audit('sim.bearbeitet', 'sim', $id, sim_bezeichnung($daten));
    } else {
        $daten['created_by'] = (int)$user['id'];
        $id = db_insert('sims', $daten);
        audit('sim.angelegt', 'sim', $id, sim_bezeichnung($daten));
    }
    return [$id, []];
}

/** Kurzer Name einer Karte fürs Protokoll und für Listen. Reine Funktion. */
function sim_bezeichnung(array $sim): string
{
    $kennung = sim_kennung($sim);
    if ($kennung === '') {
        return sim_ist_tetra($sim) ? 'TETRA-Karte' : 'SIM-Karte';
    }
    return sim_ist_tetra($sim) ? 'ISSI ' . $kennung : $kennung;
}

function sim_delete(array $sim): void
{
    db_exec('DELETE FROM sims WHERE id = ?', [(int)$sim['id']]);
    audit('sim.geloescht', 'sim', (int)$sim['id'], sim_bezeichnung($sim));
}

/** Karte einem Ziel zuordnen – etwa direkt aus der Fahrzeugakte */
function sim_assign(array $sim, string $zielTyp, ?int $zielId, array $user): void
{
    $zielTyp = sim_ziel_typ($zielTyp);
    db_update('sims', [
        'ziel_typ'   => $zielTyp,
        'ziel_id'    => $zielTyp === 'ov' ? null : $zielId,
        'updated_by' => (int)$user['id'],
    ], 'id = ?', [(int)$sim['id']]);
    audit('sim.zugeordnet', 'sim', (int)$sim['id'], $zielTyp . ':' . (string)($zielId ?? '–'));
}
