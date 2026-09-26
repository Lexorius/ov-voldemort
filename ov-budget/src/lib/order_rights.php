<?php
declare(strict_types=1);

/*
 * Bestellberechtigungen
 *
 * Wer einen Wunsch zur Bestellung freigeben oder als bestellt markieren
 * darf, hängt an der Rolle des Benutzers und an seinen Funktionen im OV
 * (Ortsbeauftragte:r, Verwaltungsbeauftragte:r ...). Hat jemand mehrere,
 * gilt das Günstigste: ein Recht reicht, und die höchste Freigabegrenze
 * zählt – eine Zeile ohne Grenze bedeutet unbegrenzt.
 */

const BESTELL_ROLLEN = ['admin' => 'Administration', 'leitung' => 'Leitung', 'user' => 'Mitglied'];

/** Alle hinterlegten Zeilen, Schlüssel "rolle:admin" bzw. "funktion:12" */
function order_rights_rows(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = [];
        foreach (db_all('SELECT * FROM bestell_rechte') as $r) {
            $key = $r['rolle'] !== null ? 'rolle:' . $r['rolle'] : 'funktion:' . (int)$r['funktion_id'];
            $rows[$key] = $r;
        }
    }
    return $rows;
}

/**
 * Rechte aus den zutreffenden Zeilen zusammenfassen (ohne Datenbank).
 * $treffer: [['label' => ..., 'row' => Zeile], ...]
 * Rückgabe: freigeben, grenze (null = unbegrenzt), bestellen,
 *           freigabe_durch / bestellen_durch (Bezeichnungen der Quellen)
 */
function order_rights_combine(array $treffer): array
{
    $out = ['freigeben' => false, 'grenze' => 0.0, 'bestellen' => false,
            'freigabe_durch' => [], 'bestellen_durch' => []];

    foreach ($treffer as $t) {
        $r = $t['row'];
        if ((int)$r['darf_freigeben']) {
            $out['freigeben'] = true;
            $out['freigabe_durch'][] = $t['label'];
            $grenze = $r['freigabe_grenze'];
            if ($grenze === null || $grenze === '') {
                $out['grenze'] = null;
            } elseif ($out['grenze'] !== null) {
                $out['grenze'] = max($out['grenze'], (float)$grenze);
            }
        }
        if ((int)$r['darf_bestellen']) {
            $out['bestellen'] = true;
            $out['bestellen_durch'][] = $t['label'];
        }
    }
    if (!$out['freigeben']) {
        $out['grenze'] = 0.0;
    }
    return $out;
}

/** Zutreffende Zeilen für Rolle und Funktionen eines Benutzers */
function order_rights_matches(string $rolle, array $funktionIds, array $rows): array
{
    $treffer = [];
    if (isset($rows['rolle:' . $rolle])) {
        $treffer[] = ['label' => BESTELL_ROLLEN[$rolle] ?? $rolle, 'row' => $rows['rolle:' . $rolle]];
    }
    foreach ($funktionIds as $fid) {
        if (isset($rows['funktion:' . (int)$fid])) {
            $treffer[] = ['label' => list_label((int)$fid), 'row' => $rows['funktion:' . (int)$fid]];
        }
    }
    return $treffer;
}

/** Wirksame Bestellrechte eines Benutzers */
function order_rights_for_user(?array $u = null): array
{
    static $cache = [];
    $u ??= current_user();
    if (!$u) {
        return order_rights_combine([]);
    }
    $id = (int)$u['id'];
    if (!isset($cache[$id])) {
        $cache[$id] = order_rights_combine(
            order_rights_matches((string)$u['role'], user_functions($id), order_rights_rows())
        );
    }
    return $cache[$id];
}

/** Eine Freigabegrenze lesbar machen */
function order_limit_text(?float $grenze): string
{
    return $grenze === null ? 'unbegrenzt' : 'bis ' . money($grenze);
}

/**
 * Darf der Benutzer diesen Wunsch freigeben?
 * Gibt null zurück, wenn ja – sonst den Grund in einem Satz.
 * $wish braucht status_slug und status_final (wish_query / wish_find_full).
 */
function wish_release_denied(array $wish, ?array $u = null, ?bool $eigeneErlaubt = null): ?string
{
    $u ??= current_user();
    if (!$u) {
        return 'Nicht angemeldet.';
    }
    if (!wish_releasable($wish)) {
        return 'Dieser Wunsch ist bereits freigegeben, bestellt oder abgeschlossen.';
    }
    $rechte = order_rights_for_user($u);
    if (!$rechte['freigeben']) {
        return 'Du hast keine Berechtigung, Wünsche zur Bestellung freizugeben.';
    }
    $betrag = (float)($wish['netto_gesamt'] ?? 0);
    if ($rechte['grenze'] !== null && $betrag > $rechte['grenze'] + 0.004) {
        return sprintf('Deine Freigabegrenze liegt bei %s, dieser Wunsch kostet %s.',
            money($rechte['grenze']), money($betrag));
    }
    $eigeneErlaubt ??= setting_bool('bestell_eigene_freigeben', true);
    if (!$eigeneErlaubt && (int)($wish['created_by'] ?? 0) === (int)$u['id']) {
        return 'Eigene Wünsche muss eine andere Person freigeben.';
    }
    return null;
}

/**
 * Statuswechsel über das Formular oder die Statusauswahl prüfen, damit
 * niemand die Freigabe am Knopf vorbei setzt. null = in Ordnung.
 *
 * Geprüft wird, wenn der Wunsch neu auf „freigegeben“ gesetzt wird, und
 * wenn ein freigegebener Wunsch nachträglich teurer wird.
 */
function wish_status_change_denied(?array $existing, ?int $neuerStatus, float $gesamt, array $user): ?string
{
    $frei = list_id_by_slug('wunsch_status', 'freigegeben');
    if (!$frei || $neuerStatus !== $frei) {
        return null;
    }
    $warFrei = $existing && (int)$existing['status_id'] === $frei;
    $pruefling = [
        'netto_gesamt' => $gesamt,
        'created_by'   => $existing['created_by'] ?? $user['id'],
        'status_slug'  => '',
        'status_final' => 0,
    ];
    if (!$warFrei) {
        return wish_release_denied($pruefling, $user);
    }
    if ($gesamt > (float)$existing['netto_gesamt'] + 0.004) {
        $grund = wish_release_denied($pruefling, $user);
        if ($grund !== null) {
            return 'Der Betrag liegt über dem freigegebenen Betrag. ' . $grund;
        }
    }
    return null;
}

/** Aktive Benutzer mit Rolle und Funktions-IDs */
function order_rights_users(): array
{
    $out = [];
    foreach (db_all(
        "SELECT u.id, u.role, COALESCE(NULLIF(u.display_name, ''), u.username) AS name,
                GROUP_CONCAT(uf.function_id) AS funktionen
         FROM users u
         LEFT JOIN user_functions uf ON uf.user_id = u.id
         WHERE u.is_active = 1
         GROUP BY u.id, u.role, u.display_name, u.username
         ORDER BY name"
    ) as $u) {
        $u['funktion_ids'] = $u['funktionen'] ? array_map('intval', explode(',', (string)$u['funktionen'])) : [];
        $out[] = $u;
    }
    return $out;
}

/**
 * Aktive Benutzer, die einen Wunsch über diesen Betrag freigeben dürfen –
 * für den Hinweis, an wen man sich wenden kann.
 */
function order_release_people(float $betrag, ?int $ausser = null): array
{
    $namen = [];
    foreach (order_rights_users() as $u) {
        if ($ausser !== null && (int)$u['id'] === $ausser) {
            continue;
        }
        $r = order_rights_combine(order_rights_matches((string)$u['role'], $u['funktion_ids'], order_rights_rows()));
        if ($r['freigeben'] && ($r['grenze'] === null || $betrag <= $r['grenze'] + 0.004)) {
            $namen[] = (string)$u['name'];
        }
    }
    return $namen;
}

/** Zeilen aus dem Verwaltungsformular speichern */
function order_rights_save_from_post(): void
{
    $zeilen = [];
    foreach (array_keys(BESTELL_ROLLEN) as $rolle) {
        $zeilen[] = ['rolle' => $rolle, 'funktion_id' => null, 'key' => 'rolle_' . $rolle];
    }
    foreach (list_items('funktion', false) as $f) {
        $zeilen[] = ['rolle' => null, 'funktion_id' => (int)$f['id'], 'key' => 'funktion_' . (int)$f['id']];
    }

    $freigeben = (array)post('freigeben', []);
    $bestellen = (array)post('bestellen', []);
    $grenzen = (array)post('grenze', []);

    foreach ($zeilen as $z) {
        $k = $z['key'];
        $roh = trim(str_replace([' ', '€'], '', (string)($grenzen[$k] ?? '')));
        // Deutsche Schreibweise: 1.500,50
        if (str_contains($roh, ',')) {
            $roh = str_replace(['.', ','], ['', '.'], $roh);
        }
        $grenze = ($roh === '' || !is_numeric($roh)) ? null : max(0.0, round((float)$roh, 2));

        $werte = [
            isset($freigeben[$k]) ? 1 : 0,
            $grenze,
            isset($bestellen[$k]) ? 1 : 0,
        ];
        db_exec(
            'INSERT INTO bestell_rechte (rolle, funktion_id, darf_freigeben, freigabe_grenze, darf_bestellen)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE darf_freigeben = VALUES(darf_freigeben),
                                     freigabe_grenze = VALUES(freigabe_grenze),
                                     darf_bestellen = VALUES(darf_bestellen)',
            array_merge([$z['rolle'], $z['funktion_id']], $werte)
        );
    }
}
