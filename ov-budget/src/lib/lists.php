<?php
declare(strict_types=1);

/**
 * Zugriff auf die im Admin gepflegten Auswahllisten.
 * Bekannte Schlüssel: fachgruppe, funktion, dringlichkeit, wunsch_status,
 * kategorie, einheit, todo_status, todo_prioritaet, anlage_typ
 */

const LIST_KEYS = [
    'fachgruppe'      => 'Fachgruppen / Einheiten',
    'funktion'        => 'Funktionen im OV',
    'kategorie'       => 'Kategorien',
    'dringlichkeit'   => 'Dringlichkeiten',
    'wunsch_status'   => 'Status (Wünsche)',
    'einheit'         => 'Mengeneinheiten',
    'todo_status'     => 'Status (Aufgaben)',
    'todo_prioritaet' => 'Prioritäten (Aufgaben)',
    'anlage_typ'      => 'Anlagen-Typen',
];

function lists_cache(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = ['byKey' => [], 'byId' => []];
        foreach (db_all('SELECT * FROM list_items ORDER BY list_key, sort_order, label') as $r) {
            $cache['byKey'][$r['list_key']][] = $r;
            $cache['byId'][(int)$r['id']] = $r;
        }
    }
    return $cache;
}

/** Alle (aktiven) Einträge einer Liste */
function list_items(string $key, bool $onlyActive = true): array
{
    $items = lists_cache()['byKey'][$key] ?? [];
    if (!$onlyActive) {
        return $items;
    }
    return array_values(array_filter($items, static fn($i) => (int)$i['is_active'] === 1));
}

function list_item(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    return lists_cache()['byId'][$id] ?? null;
}

function list_label(?int $id, string $fallback = '–'): string
{
    $i = list_item($id);
    return $i ? $i['label'] : $fallback;
}

/** Standardeintrag einer Liste (is_default), sonst der erste */
function list_default_id(string $key): ?int
{
    foreach (list_items($key) as $i) {
        if ((int)$i['is_default'] === 1) {
            return (int)$i['id'];
        }
    }
    $first = list_items($key)[0] ?? null;
    return $first ? (int)$first['id'] : null;
}

function list_id_by_slug(string $key, string $slug): ?int
{
    foreach (list_items($key, false) as $i) {
        if ($i['slug'] === $slug) {
            return (int)$i['id'];
        }
    }
    return null;
}

/** Sucht einen Listeneintrag anhand eines Freitextes (für den Divera-Import) */
function list_id_by_text(string $key, string $text): ?int
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }
    $needle = mb_strtolower($text);
    $slug = slugify($text);
    foreach (list_items($key, false) as $i) {
        if (mb_strtolower($i['label']) === $needle || $i['slug'] === $slug) {
            return (int)$i['id'];
        }
    }
    // Zweiter Versuch: Teilstring
    foreach (list_items($key, false) as $i) {
        if ($i['slug'] !== '' && str_contains($slug, $i['slug'])) {
            return (int)$i['id'];
        }
        if (str_contains($needle, mb_strtolower($i['label']))) {
            return (int)$i['id'];
        }
    }
    return null;
}

/** <option>-Liste rendern */
function list_options(string $key, ?int $selected, string $empty = '– bitte wählen –', bool $onlyActive = true): string
{
    $html = '';
    if ($empty !== '') {
        $html .= '<option value="">' . e($empty) . '</option>';
    }
    foreach (list_items($key, $onlyActive) as $i) {
        $sel = ((int)$i['id'] === (int)$selected) ? ' selected' : '';
        $html .= '<option value="' . (int)$i['id'] . '"' . $sel . '>' . e($i['label']) . '</option>';
    }
    // Inaktiver, aber gesetzter Wert bleibt sichtbar
    if ($selected && $onlyActive) {
        $cur = list_item($selected);
        if ($cur && (int)$cur['is_active'] === 0 && $cur['list_key'] === $key) {
            $html .= '<option value="' . (int)$cur['id'] . '" selected>' . e($cur['label']) . ' (inaktiv)</option>';
        }
    }
    return $html;
}
