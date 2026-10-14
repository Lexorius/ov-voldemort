<?php
declare(strict_types=1);

/*
 * THW-Kontakte: die Dienststellen, mit denen der Ortsverband zu tun hat –
 * als Baum (THW-Leitung › Landesverband › Regionalstelle › Ortsverband) –
 * und die Funktionspostfächer mit Zweck und Erreichbarkeit. Für den Baum
 * werden die Hilfen der Stell- und Lagerplätze mitbenutzt (parent_id,
 * sort_order, name).
 */

const THW_TYPEN = [
    'leitung'        => ['label' => 'THW-Leitung',       'mehrzahl' => 'THW-Leitung',       'icon' => '🏛'],
    'landesverband'  => ['label' => 'Landesverband',     'mehrzahl' => 'Landesverbände',    'icon' => '🗺'],
    'regionalstelle' => ['label' => 'Regionalstelle',    'mehrzahl' => 'Regionalstellen',   'icon' => '🏢'],
    'ortsverband'    => ['label' => 'Ortsverband',       'mehrzahl' => 'Ortsverbände',      'icon' => '⛑'],
    'sonstige'       => ['label' => 'Sonstige Dienststelle', 'mehrzahl' => 'Sonstige Dienststellen', 'icon' => '▦'],
];

function thw_typ(string $typ): string
{
    return array_key_exists($typ, THW_TYPEN) ? $typ : 'ortsverband';
}

function thw_all(bool $nurAktive = false): array
{
    return db_all('SELECT * FROM thw_dienststellen' . ($nurAktive ? ' WHERE is_active = 1' : '') . ' ORDER BY parent_id, sort_order, name, id');
}

function thw_find(?int $id): ?array
{
    return $id > 0 ? db_row('SELECT * FROM thw_dienststellen WHERE id = ?', [$id]) : null;
}

/** „Landesverband BW › Regionalstelle Heilbronn › OV Heilbronn". Reine Funktion. */
function thw_pfad(int $id, array $rows): string
{
    return standort_pfad($id, $rows);
}

/** Baum mit Suchfilter: Treffer samt ihren Vorfahren, damit der Pfad sichtbar bleibt. Reine Funktion. */
function thw_suchen(array $rows, string $q): array
{
    $q = mb_strtolower(trim($q));
    if ($q === '') {
        return $rows;
    }
    $nachId = array_column($rows, null, 'id');
    $behalten = [];
    foreach ($rows as $r) {
        $text = mb_strtolower(implode(' ', [(string)$r['name'], (string)($r['kurz'] ?? ''), (string)($r['ort'] ?? ''), (string)($r['plz'] ?? ''), (string)($r['email'] ?? '')]));
        if (str_contains($text, $q)) {
            $behalten[(int)$r['id']] = true;
            foreach (standort_vorfahren((int)$r['id'], $rows) as $v) {
                $behalten[$v] = true;
            }
        }
    }
    return array_values(array_filter($rows, static fn($r) => isset($behalten[(int)$r['id']])));
}

/** Anschrift in einer Zeile: „Musterstraße 1, 74072 Heilbronn". Reine Funktion. */
function thw_anschrift(array $d): string
{
    $ortZeile = trim(trim((string)($d['plz'] ?? '')) . ' ' . trim((string)($d['ort'] ?? '')));
    return implode(', ', array_filter([trim((string)($d['strasse'] ?? '')), $ortZeile], 'strlen'));
}

/** Dienststelle aus dem Formular speichern. Gibt [id, fehler[]] zurück. */
function thw_save_from_post(?array $existing, array $user): array
{
    $name = mb_substr(trim(post_str('name')), 0, 150);
    $fehler = [];
    if ($name === '') {
        $fehler[] = 'Bitte einen Namen angeben – etwa „OV Heilbronn" oder „Regionalstelle Heilbronn".';
    }
    $parent = post_int('parent_id');
    $alle = thw_all();
    if ($parent && $existing) {
        $verboten = array_merge([(int)$existing['id']], standort_nachkommen((int)$existing['id'], $alle));
        if (in_array((int)$parent, $verboten, true)) {
            $fehler[] = 'Eine Dienststelle kann nicht unter sich selbst hängen.';
        }
    }
    if ($parent && !thw_find($parent)) {
        $fehler[] = 'Die übergeordnete Dienststelle gibt es nicht.';
    }
    $email = mb_substr(trim(post_str('email')), 0, 150);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fehler[] = 'Die E-Mail-Adresse sieht nicht richtig aus.';
    }
    if ($fehler) {
        return [null, $fehler];
    }
    $daten = [
        'parent_id'  => $parent ?: null,
        'typ'        => thw_typ(post_str('typ', 'ortsverband')),
        'name'       => $name,
        'kurz'       => mb_substr(trim(post_str('kurz')), 0, 30),
        'strasse'    => mb_substr(trim(post_str('strasse')), 0, 150),
        'plz'        => mb_substr(trim(post_str('plz')), 0, 15),
        'ort'        => mb_substr(trim(post_str('ort')), 0, 100),
        'telefon'    => mb_substr(trim(post_str('telefon')), 0, 60),
        'email'      => $email,
        'web'        => mb_substr(trim(post_str('web')), 0, 200),
        'notiz'      => post_str('notiz'),
        'sort_order' => post_int('sort_order', 0) ?? 0,
        'is_active'  => post_bool('is_active'),
    ];
    if ($existing) {
        db_update('thw_dienststellen', $daten, 'id = ?', [(int)$existing['id']]);
        $id = (int)$existing['id'];
        audit('thw.bearbeitet', 'thw_dienststelle', $id, $name);
    } else {
        $id = db_insert('thw_dienststellen', $daten);
        audit('thw.angelegt', 'thw_dienststelle', $id, $name);
    }
    return [$id, []];
}

/** Löschen – nur ohne Unterstellen; Postfächer verlieren die Zuordnung. Gibt eine Fehlermeldung oder null. */
function thw_delete(array $d): ?string
{
    if ((int)db_val('SELECT COUNT(*) FROM thw_dienststellen WHERE parent_id = ?', [(int)$d['id']], 0) > 0) {
        return 'Unter dieser Dienststelle hängen noch andere – erst die verschieben oder löschen.';
    }
    db_exec('DELETE FROM thw_dienststellen WHERE id = ?', [(int)$d['id']]);
    audit('thw.geloescht', 'thw_dienststelle', (int)$d['id'], (string)$d['name']);
    return null;
}

/* ---------- Funktionspostfächer ---------- */

function postfach_all(bool $nurAktive = false): array
{
    return db_all('SELECT p.*, d.name AS dienststelle_name, d.kurz AS dienststelle_kurz FROM thw_postfaecher p
                   LEFT JOIN thw_dienststellen d ON d.id = p.dienststelle_id' . ($nurAktive ? ' WHERE p.is_active = 1' : '') . '
                   ORDER BY d.sort_order, d.name, p.sort_order, p.name, p.id');
}

function postfach_find(?int $id): ?array
{
    return $id > 0 ? db_row('SELECT * FROM thw_postfaecher WHERE id = ?', [$id]) : null;
}

/** Postfächer durchsuchen (Name, Adresse, Zweck, Dienststelle). Reine Funktion. */
function postfach_suchen(array $rows, string $q): array
{
    $q = mb_strtolower(trim($q));
    if ($q === '') {
        return $rows;
    }
    return array_values(array_filter($rows, static fn($p) => str_contains(mb_strtolower(implode(' ', [(string)$p['name'], (string)$p['email'], (string)($p['beschreibung'] ?? ''), (string)($p['dienststelle_name'] ?? '')])), $q)));
}

/** Erreichbarkeit als Text: „offizielles Netz · Behördennetz". Reine Funktion. */
function postfach_netz_text(array $p): string
{
    $teile = [];
    if ((int)($p['offiziell'] ?? 0) === 1) {
        $teile[] = 'offizielles Netz';
    }
    if ((int)($p['behoerdennetz'] ?? 0) === 1) {
        $teile[] = 'Behördennetz';
    }
    return $teile ? implode(' · ', $teile) : 'Erreichbarkeit nicht angegeben';
}

/** Postfach aus dem Formular speichern. Gibt [id, fehler[]] zurück. */
function postfach_save_from_post(?array $existing, array $user): array
{
    $name = mb_substr(trim(post_str('name')), 0, 150);
    $email = mb_substr(trim(post_str('email')), 0, 150);
    $fehler = [];
    if ($name === '') {
        $fehler[] = 'Bitte eine Bezeichnung angeben – etwa „Einsatz" oder „Verwaltung".';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fehler[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
    }
    $dst = post_int('dienststelle_id');
    if ($dst && !thw_find($dst)) {
        $fehler[] = 'Die Dienststelle gibt es nicht.';
    }
    if ($fehler) {
        return [null, $fehler];
    }
    $daten = [
        'dienststelle_id' => $dst ?: null,
        'name'            => $name,
        'email'           => $email,
        'beschreibung'    => mb_substr(trim(post_str('beschreibung')), 0, 500),
        'offiziell'       => post_bool('offiziell'),
        'behoerdennetz'   => post_bool('behoerdennetz'),
        'notiz'           => post_str('notiz'),
        'sort_order'      => post_int('sort_order', 0) ?? 0,
        'is_active'       => post_bool('is_active'),
    ];
    if ($existing) {
        db_update('thw_postfaecher', $daten, 'id = ?', [(int)$existing['id']]);
        $id = (int)$existing['id'];
        audit('postfach.bearbeitet', 'thw_postfach', $id, $name);
    } else {
        $id = db_insert('thw_postfaecher', $daten);
        audit('postfach.angelegt', 'thw_postfach', $id, $name);
    }
    return [$id, []];
}

function postfach_delete(array $p): void
{
    db_exec('DELETE FROM thw_postfaecher WHERE id = ?', [(int)$p['id']]);
    audit('postfach.geloescht', 'thw_postfach', (int)$p['id'], (string)$p['name']);
}

/** Postfächer je Dienststelle bündeln: [dienststelle_id => [...]], 0 = ohne. Reine Funktion. */
function postfach_je_dienststelle(array $rows): array
{
    $out = [];
    foreach ($rows as $p) {
        $out[(int)($p['dienststelle_id'] ?? 0)][] = $p;
    }
    return $out;
}
