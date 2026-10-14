<?php
/**
 * Mehrere Stell- und Lagerplätze wählen – Dropdown mit Pfad, gewählte als Liste.
 * @var array  $standorte  alle Plätze (standort_all)
 * @var array  $gewaehlt   ids, die schon dran hängen
 * @var string $hinweis    Satz unter der Auswahl
 */
$standorte ??= [];
$gewaehlt ??= [];
$hinweis ??= '';
$aktive = array_values(array_filter($standorte, static fn($x) => (int)$x['is_active'] === 1 || in_array((int)$x['id'], array_map('intval', $gewaehlt), true)));
if (!$aktive || !function_exists('standort_flach')) {
    return;
}
$optionen = [];
foreach (standort_flach(standort_baum($aktive)) as $s) {
    $optionen[] = ['id' => (int)$s['id'], 'text' => (string)$s['name'], 'sub' => standort_pfad((int)($s['parent_id'] ?? 0), $standorte), 'gruppe' => ''];
}
echo render_partial('partials/wahl_liste', ['name' => 'standorte', 'feld' => 'standorte_feld', 'label' => 'Standorte', 'optionen' => $optionen, 'gewaehlt' => $gewaehlt,
    'leer' => '– Platz wählen –', 'hinweis' => $hinweis]);
