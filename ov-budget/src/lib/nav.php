<?php
declare(strict_types=1);

/**
 * Die Menüleiste: welche Module es gibt, wer sie sieht, in welcher
 * Reihenfolge sie stehen. Die Reihenfolge ist in der Verwaltung einstellbar
 * und liegt als Liste der Schlüssel in den internen Merkwerten.
 */

/** Alle Module in ihrer Vorgabereihenfolge. Reine Funktion bis auf setting()/can(). */
function nav_module(): array
{
    return [
        'dashboard' => ['label' => 'Übersicht', 'icon' => '▦', 'route' => 'dashboard',
                        'aktiv' => ['dashboard'], 'darf' => true],
        'wishes'    => ['label' => (string)setting('wunsch_modul_name', 'Wünsch dir was'), 'icon' => '★',
                        'route' => 'wishes', 'aktiv' => ['wishes', 'wish', 'wish_edit', 'bestellungen', 'bestellung', 'bestellung_edit'], 'darf' => true],
        'todos'     => ['label' => (string)setting('todo_modul_name', 'Aufgaben'), 'icon' => '☑',
                        'route' => 'todos', 'aktiv' => ['todos', 'todo', 'todo_edit'], 'darf' => true],
        'budget'    => ['label' => (string)setting('budget_modul_name', 'Budget'), 'icon' => '€', 'route' => 'budget',
                        'aktiv' => ['budget', 'budget_edit', 'budget_pots', 'budget_year_edit', 'expenses', 'expense_edit'],
                        'darf' => true],
        'vehicles'  => ['label' => (string)setting('fahrzeug_modul_name', 'Fahrzeuge'), 'icon' => '⛟',
                        'route' => 'vehicles',
                        'aktiv' => ['vehicles', 'vehicle', 'vehicle_edit', 'vehicle_order', 'vehicle_order_edit'],
                        'darf' => can('view_vehicles')],
        'radios'    => ['label' => (string)setting('funk_modul_name', 'Funkgeräte'), 'icon' => '((•))',
                        'route' => 'radios',
                        'aktiv' => ['radios', 'radio', 'radio_edit', 'radio_group', 'radio_group_edit'],
                        'darf' => can('view_radios')],
        'sims'      => ['label' => (string)setting('sim_modul_name', 'SIM-Karten'), 'icon' => '▯',
                        'route' => 'sims', 'aktiv' => ['sims', 'sim_edit'], 'darf' => can('view_sims')],
        'verbrauch' => ['label' => (string)setting('verbrauch_modul_name', 'Verbrauch'), 'icon' => '⚡',
                        'route' => 'verbrauch', 'aktiv' => ['verbrauch', 'meter', 'meter_edit', 'tarife', 'tarif_edit'],
                        'darf' => can('view_verbrauch')],
        'contacts'  => ['label' => (string)setting('kontakte_modul_name', 'Kontakte'), 'icon' => '✉',
                        'route' => 'contacts',
                        'aktiv' => ['contacts', 'contact_edit', 'contact_groups', 'contact_group', 'contacts_import'],
                        'darf' => can('view_contacts')],
        'events'    => ['label' => (string)setting('veranstaltung_modul_name', 'Veranstaltungen'), 'icon' => '◍',
                        'route' => 'events', 'aktiv' => ['events', 'event', 'event_edit', 'verpflegung_saetze', 'verpflegung_satz_edit'], 'darf' => can('view_events')],
        'kalender'  => ['label' => (string)setting('kalender_modul_name', 'Kalender'), 'icon' => '▤',
                        'route' => 'kalender', 'aktiv' => ['kalender', 'kalender_edit'], 'darf' => true],
        'meetings'  => ['label' => (string)setting('besprechung_modul_name', 'Besprechungen'), 'icon' => '☰',
                        'route' => 'meetings',
                        'aktiv' => ['meetings', 'meeting', 'meeting_edit', 'meeting_series_edit', 'talking_points', 'talking_point_edit'],
                        'darf' => can('view_meetings')],
        'admin'     => ['label' => 'Verwaltung', 'icon' => '⚙', 'route' => 'admin',
                        'aktiv' => ['admin', 'admin_users', 'admin_user_edit', 'admin_lists', 'admin_list_edit',
                                    'admin_settings', 'admin_divera', 'admin_divera_form', 'admin_order_rights',
                                    'admin_stein', 'admin_divera_fahrzeuge', 'admin_connectors', 'admin_connector',
                                    'admin_log', 'admin_backup', 'admin_ha', 'admin_nav', 'admin_kalender'],
                        'darf' => can('admin')],
    ];
}

/**
 * Module in die gewünschte Reihenfolge bringen: erst die Schlüssel aus
 * $reihenfolge (Komma-Liste), dann alle übrigen in Vorgabereihenfolge.
 * Unbekannte Schlüssel werden übergangen. Reine Funktion.
 */
function nav_sortieren(array $module, string $reihenfolge): array
{
    $out = [];
    foreach (array_filter(array_map('trim', explode(',', $reihenfolge))) as $key) {
        if (isset($module[$key]) && !isset($out[$key])) {
            $out[$key] = $module[$key];
        }
    }
    foreach ($module as $key => $m) {
        if (!isset($out[$key])) {
            $out[$key] = $m;
        }
    }
    return $out;
}

/** Einen Schlüssel in der Liste um eine Stelle verschieben (-1 hoch, +1 runter). Reine Funktion. */
function nav_verschieben(array $keys, string $key, int $richtung): array
{
    $keys = array_values($keys);
    $i = array_search($key, $keys, true);
    if ($i === false) {
        return $keys;
    }
    $j = $i + ($richtung < 0 ? -1 : 1);
    if ($j < 0 || $j >= count($keys)) {
        return $keys;
    }
    [$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];
    return $keys;
}

/** Gespeicherte Reihenfolge als Komma-Liste ('' = Vorgabe) */
function nav_reihenfolge(): string
{
    return state_get('nav_reihenfolge', '');
}

/** Die Menüleiste, wie sie die angemeldete Person sieht: sortiert und gefiltert */
function nav_leiste(): array
{
    return array_filter(nav_sortieren(nav_module(), nav_reihenfolge()), static fn($m) => $m['darf']);
}
