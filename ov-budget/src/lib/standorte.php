<?php
declare(strict_types=1);

/*
 * Stell- und Lagerplätze: Gebäude, Hallen und Höfe mit Stockwerken, Räumen,
 * Stellplätzen, Schränken und Regalen – als Baum. Gepflegt in der Verwaltung,
 * damit Fahrzeuge, Geräte und Material später einen eindeutigen Platz haben.
 */

/** Typen mit Beschriftung; 'oben' = darf ganz oben stehen, 'eltern' = worunter er sonst hängen darf */
const STANDORT_TYPEN = [
    'gebaeude'      => ['label' => 'Gebäude', 'mehrzahl' => 'Gebäude', 'icon' => '🏢', 'oben' => true, 'eltern' => []],
    'halle'         => ['label' => 'Halle', 'mehrzahl' => 'Hallen', 'icon' => '🏭', 'oben' => true, 'eltern' => ['gebaeude']],
    'hof'           => ['label' => 'Hof', 'mehrzahl' => 'Höfe', 'icon' => '▦', 'oben' => true, 'eltern' => []],
    'stockwerk'     => ['label' => 'Stockwerk', 'mehrzahl' => 'Stockwerke', 'icon' => '≡', 'eltern' => ['gebaeude', 'halle']],
    'raum'          => ['label' => 'Raum', 'mehrzahl' => 'Räume', 'icon' => '▢', 'eltern' => ['gebaeude', 'halle', 'stockwerk']],
    'stellplatz'    => ['label' => 'Fahrzeugstellplatz', 'mehrzahl' => 'Fahrzeugstellplätze', 'icon' => '🚒', 'eltern' => ['halle', 'gebaeude', 'stockwerk']],
    'hofstellplatz' => ['label' => 'Stellplatz auf dem Hof', 'mehrzahl' => 'Stellplätze auf dem Hof', 'icon' => '🅿', 'eltern' => ['hof']],
    'schrank'       => ['label' => 'Schrank', 'mehrzahl' => 'Schränke', 'icon' => '🗄', 'eltern' => ['raum', 'halle', 'stockwerk', 'gebaeude']],
    'regal'         => ['label' => 'Regal', 'mehrzahl' => 'Regale', 'icon' => '▤', 'eltern' => ['schrank', 'raum', 'halle']],
];
const STANDORT_MAX_SERIE = 200;

function standort_typ(string $typ): string
{
    return array_key_exists($typ, STANDORT_TYPEN) ? $typ : 'raum';
}

/** Darf ein Typ unter diesem Elterntyp hängen? Reine Funktion. */
function standort_passt(string $typ, ?string $elternTyp): bool
{
    $t = STANDORT_TYPEN[standort_typ($typ)];
    return $elternTyp === null ? !empty($t['oben']) : in_array($elternTyp, $t['eltern'], true);
}

/** Welche Typen unter diesem Elterntyp möglich sind. Reine Funktion. */
function standort_kindtypen(?string $elternTyp): array
{
    return array_keys(array_filter(STANDORT_TYPEN, static fn($t) => $elternTyp === null ? !empty($t['oben']) : in_array($elternTyp, $t['eltern'], true)));
}

function standort_all(bool $nurAktive = false): array
{
    return db_all('SELECT * FROM standorte' . ($nurAktive ? ' WHERE is_active = 1' : '') . ' ORDER BY parent_id, sort_order, name, id');
}

function standort_find(?int $id): ?array
{
    return $id > 0 ? db_row('SELECT * FROM standorte WHERE id = ?', [$id]) : null;
}

/**
 * Flache Liste in einen Baum: jede Zeile bekommt 'kinder' und 'tiefe'.
 * Waisen (Eltern gelöscht) hängen oben. Reine Funktion.
 */
function standort_baum(array $rows): array
{
    $nachEltern = [];
    $ids = array_column($rows, 'id');
    foreach ($rows as $r) {
        $p = (int)($r['parent_id'] ?? 0);
        $nachEltern[in_array($p, $ids, false) ? $p : 0][] = $r;
    }
    $bauen = static function (int $eltern, int $tiefe) use (&$bauen, $nachEltern): array {
        $out = [];
        foreach ($nachEltern[$eltern] ?? [] as $r) {
            $r['tiefe'] = $tiefe;
            $r['kinder'] = $bauen((int)$r['id'], $tiefe + 1);
            $out[] = $r;
        }
        return $out;
    };
    return $bauen(0, 0);
}

/** Baum als Liste in Lesereihenfolge (Tiefensuche). Reine Funktion. */
function standort_flach(array $baum): array
{
    $out = [];
    foreach ($baum as $k) {
        $kinder = $k['kinder'];
        unset($k['kinder']);
        $out[] = $k;
        foreach (standort_flach($kinder) as $u) {
            $out[] = $u;
        }
    }
    return $out;
}

/** „Haupthaus › 2. Stock › Raum 12". $rows: alle Standorte (id => Zeile). Reine Funktion. */
function standort_pfad(int $id, array $rows, string $trenner = ' › '): string
{
    $nachId = isset($rows[$id]) && !isset($rows[0]) ? $rows : array_column($rows, null, 'id');
    $teile = [];
    $schutz = 0;
    while ($id > 0 && isset($nachId[$id]) && $schutz++ < 50) {
        array_unshift($teile, (string)$nachId[$id]['name']);
        $id = (int)($nachId[$id]['parent_id'] ?? 0);
    }
    return implode($trenner, $teile);
}

/** Alle Nachkommen eines Knotens (ids), zum Verhindern von Schleifen. Reine Funktion. */
function standort_nachkommen(int $id, array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        if ((int)($r['parent_id'] ?? 0) === $id) {
            $out[] = (int)$r['id'];
            foreach (standort_nachkommen((int)$r['id'], $rows) as $u) {
                $out[] = $u;
            }
        }
    }
    return $out;
}

/** Optionen für eine Auswahl, eingerückt nach Tiefe. */
function standort_optionen(array $rows, ?int $selected, string $leer = '– keiner –', array $ausser = []): string
{
    $html = '<option value="">' . e($leer) . '</option>';
    foreach (standort_flach(standort_baum($rows)) as $s) {
        if (in_array((int)$s['id'], $ausser, true)) {
            continue;
        }
        $html .= '<option value="' . (int)$s['id'] . '"' . ((int)$s['id'] === (int)$selected ? ' selected' : '') . '>'
            . e(str_repeat('— ', (int)$s['tiefe']) . $s['name'] . ' (' . STANDORT_TYPEN[standort_typ((string)$s['typ'])]['label'] . ')') . '</option>';
    }
    return $html;
}

function standort_naechste_sort(?int $parentId): int
{
    return (int)db_val('SELECT COALESCE(MAX(sort_order),0) + 10 FROM standorte WHERE ' . ($parentId ? 'parent_id = ?' : 'parent_id IS NULL'), $parentId ? [$parentId] : [], 10);
}

/**
 * Standort aus dem Formular speichern – einzeln oder als Serie („Raum" 1–16).
 * Gibt [ids, fehler[]] zurück.
 */
function standort_save_from_post(?array $existing, array $user): array
{
    $errors = [];
    $name = trim(post_str('name'));
    $typ = standort_typ(post_str('typ', (string)($existing['typ'] ?? 'raum')));
    $parentId = post_int('parent_id') ?: null;
    $parent = $parentId ? standort_find($parentId) : null;
    if ($parentId && !$parent) {
        $errors[] = 'Den gewählten übergeordneten Platz gibt es nicht.';
        $parentId = null;
    }
    if ($name === '') {
        $errors[] = 'Bitte einen Namen angeben.';
    }
    if (!standort_passt($typ, $parent ? (string)$parent['typ'] : null)) {
        $label = STANDORT_TYPEN[$typ]['label'];
        $erlaubt = STANDORT_TYPEN[$typ]['eltern'];
        $errors[] = $parent === null
            ? sprintf('%s braucht einen übergeordneten Platz: %s.', $label, implode(', ', array_map(static fn($e) => STANDORT_TYPEN[$e]['label'], $erlaubt)))
            : ($erlaubt
                ? sprintf('%s kann nur unter %s liegen%s.', $label, implode(', ', array_map(static fn($e) => STANDORT_TYPEN[$e]['label'], $erlaubt)), !empty(STANDORT_TYPEN[$typ]['oben']) ? ' – oder ganz oben' : '')
                : sprintf('%s liegt immer ganz oben, ohne übergeordneten Platz.', $label));
    }
    if ($existing && $parentId) {
        $alle = standort_all();
        if ($parentId === (int)$existing['id'] || in_array($parentId, standort_nachkommen((int)$existing['id'], $alle), true)) {
            $errors[] = 'Ein Platz kann nicht unter sich selbst oder einem seiner Unterplätze liegen.';
        }
    }
    $anzahl = $existing ? 1 : max(1, min(STANDORT_MAX_SERIE, (int)post_int('anzahl', 1)));
    $start = max(0, (int)post_int('start', 1));
    if ($errors) {
        return [[], $errors];
    }
    $data = [
        'typ'       => $typ,
        'parent_id' => $parentId,
        'kurz'      => mb_substr(trim(post_str('kurz')), 0, 30),
        'notiz'     => post_str('notiz'),
        'is_active' => post_bool('is_active'),
        'updated_by' => (int)$user['id'],
    ];
    if ($existing) {
        $data['name'] = mb_substr($name, 0, 120);
        db_update('standorte', $data, 'id = ?', [(int)$existing['id']]);
        audit('standort.bearbeitet', 'standort', (int)$existing['id'], $data['name']);
        return [[(int)$existing['id']], []];
    }
    $ids = [];
    $sort = standort_naechste_sort($parentId);
    for ($i = 0; $i < $anzahl; $i++) {
        $zeile = $data + ['created_by' => (int)$user['id']];
        $zeile['name'] = mb_substr($anzahl > 1 ? trim($name) . ' ' . ($start + $i) : $name, 0, 120);
        $zeile['kurz'] = $anzahl > 1 && $data['kurz'] !== '' ? mb_substr($data['kurz'] . ($start + $i), 0, 30) : $data['kurz'];
        $zeile['sort_order'] = $sort + $i * 10;
        $ids[] = db_insert('standorte', $zeile);
    }
    audit('standort.angelegt', 'standort', $ids[0], $anzahl > 1 ? sprintf('%s %d–%d', $name, $start, $start + $anzahl - 1) : $name);
    return [$ids, []];
}

/** Löschen nur ohne Unterplätze. Gibt eine Fehlermeldung oder null zurück. */
function standort_delete(array $s): ?string
{
    $kinder = (int)db_val('SELECT COUNT(*) FROM standorte WHERE parent_id = ?', [(int)$s['id']], 0);
    if ($kinder > 0) {
        return sprintf('„%s" hat noch %d Unterplatz/Unterplätze – bitte erst diese löschen oder verschieben.', (string)$s['name'], $kinder);
    }
    db_exec('DELETE FROM standorte WHERE id = ?', [(int)$s['id']]);
    audit('standort.geloescht', 'standort', (int)$s['id'], (string)$s['name']);
    return null;
}

/** Geschwister neu durchnummerieren und einen davon verschieben. Reine Funktion auf der Liste. */
function standort_verschieben(array $geschwister, int $id, string $richtung): array
{
    $ids = array_map('intval', array_column($geschwister, 'id'));
    $pos = array_search($id, $ids, true);
    if ($pos === false) {
        return $ids;
    }
    $neu = $richtung === 'hoch' ? $pos - 1 : $pos + 1;
    if ($neu < 0 || $neu >= count($ids)) {
        return $ids;
    }
    [$ids[$pos], $ids[$neu]] = [$ids[$neu], $ids[$pos]];
    return $ids;
}

function standort_move(array $s, string $richtung): void
{
    $parentId = (int)($s['parent_id'] ?? 0);
    $geschwister = db_all('SELECT id FROM standorte WHERE ' . ($parentId ? 'parent_id = ?' : 'parent_id IS NULL') . ' ORDER BY sort_order, name, id', $parentId ? [$parentId] : []);
    foreach (standort_verschieben($geschwister, (int)$s['id'], $richtung) as $i => $id) {
        db_update('standorte', ['sort_order' => ($i + 1) * 10], 'id = ?', [$id]);
    }
}

/** Zählung je Typ für die Übersicht. Reine Funktion. */
function standort_zaehlung(array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        $out[(string)$r['typ']] = ($out[(string)$r['typ']] ?? 0) + 1;
    }
    return $out;
}
