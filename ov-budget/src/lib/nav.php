<?php
declare(strict_types=1);

/**
 * Das Menü: welche Module es gibt, wer sie sieht – und der Baum, in dem sie stehen.
 * Der Baum hat zwei Ebenen: oben einzelne Module und Gruppen, in einer Gruppe ihre
 * Module. Ohne eigene Einstellung gilt der Grundvorschlag; in der Verwaltung lässt er
 * sich umbauen und liegt dann als JSON in den internen Merkwerten (nav_baum).
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
                        'aktiv' => ['budget', 'budget_edit', 'budget_pots', 'budget_year_edit', 'expenses', 'expense_edit', 'einnahmen_anfrage', 'budget_objektkonten', 'objektkonten', 'objektkonto_edit', 'budget_prognose'],
                        'darf' => true],
        'vehicles'  => ['label' => (string)setting('fahrzeug_modul_name', 'Fahrzeuge'), 'icon' => '⛟',
                        'route' => 'vehicles',
                        'aktiv' => ['vehicles', 'vehicle', 'vehicle_edit', 'vehicle_order', 'vehicle_order_edit', 'vehicle_kapazitaet'],
                        'darf' => can('view_vehicles')],
        'radios'    => ['label' => (string)setting('funk_modul_name', 'Funkgeräte'), 'icon' => '((•))',
                        'route' => 'radios',
                        'aktiv' => ['radios', 'radio', 'radio_edit', 'radio_group', 'radio_group_edit'],
                        'darf' => can('view_radios')],
        'sims'      => ['label' => (string)setting('sim_modul_name', 'SIM-Karten'), 'icon' => '▯',
                        'route' => 'sims', 'aktiv' => ['sims', 'sim_edit'], 'darf' => can('view_sims')],
        'tankkarten' => ['label' => (string)setting('tankkarte_modul_name', 'Tankkarten'), 'icon' => '⛽',
                        'route' => 'tankkarten', 'aktiv' => ['tankkarten', 'tankkarte_edit', 'tankkarten_import'], 'darf' => can('view_tankkarten')],
        'verbrauch' => ['label' => (string)setting('verbrauch_modul_name', 'Verbrauch'), 'icon' => '⚡',
                        'route' => 'verbrauch', 'aktiv' => ['verbrauch', 'meter', 'meter_edit', 'tarife', 'tarif_edit', 'verbrauch_bereiche'],
                        'darf' => can('view_verbrauch')],
        'contacts'  => ['label' => (string)setting('kontakte_modul_name', 'Kontakte'), 'icon' => '✉',
                        'route' => 'contacts',
                        'aktiv' => ['contacts', 'contact_edit', 'contact_groups', 'contact_group', 'contacts_import', 'contacts_dubletten', 'contacts_ki'],
                        'darf' => can('view_contacts')],
        'events'    => ['label' => (string)setting('veranstaltung_modul_name', 'Veranstaltungen'), 'icon' => '◍',
                        'route' => 'events', 'aktiv' => ['events', 'event', 'event_edit', 'verpflegung_saetze', 'verpflegung_satz_edit'], 'darf' => can('view_events')],
        'einsaetze' => ['label' => (string)setting('einsatz_modul_name', 'Einsätze'), 'icon' => '🚨',
                        'route' => 'einsaetze', 'aktiv' => ['einsaetze', 'einsatz', 'einsatz_edit', 'einsatz_auftrag'], 'darf' => can('view_einsaetze')],
        'umfragen'  => ['label' => (string)setting('umfrage_modul_name', 'Abstimmungen'), 'icon' => '🗳',
                        'route' => 'umfragen', 'aktiv' => ['umfragen', 'umfrage', 'umfrage_edit'], 'darf' => can('view_umfragen')],
        'newsletter' => ['label' => (string)setting('newsletter_modul_name', 'Newsletter'), 'icon' => '📰',
                        'route' => 'newsletter', 'aktiv' => ['newsletter', 'newsletter_detail', 'newsletter_edit', 'newsletter_sperre'],
                        'darf' => can('view_newsletter')],
        'getraenke' => ['label' => (string)setting('getraenke_modul_name', 'Getränke'), 'icon' => '🥤',
                        'route' => 'getraenke',
                        'aktiv' => ['getraenke', 'getraenk_edit', 'getraenke_lieferant_edit', 'getraenke_absender_edit', 'getraenke_bestellung', 'getraenke_bestellung_edit', 'getraenke_lager'],
                        'darf' => can('view_getraenke')],
        'standorte' => ['label' => (string)setting('standort_modul_name', 'Standorte'), 'icon' => '🏢',
                        'route' => 'standorte', 'aktiv' => ['standorte'], 'darf' => true],
        'unterkunft' => ['label' => (string)setting('unterkunft_modul_name', 'Unterkunft'), 'icon' => '🏠',
                        'route' => 'unterkunft', 'aktiv' => ['unterkunft', 'unterkunft_punkt', 'unterkunft_punkt_edit'], 'darf' => can('view_unterkunft')],
        'ablage'    => ['label' => (string)setting('ablage_modul_name', 'Dateiablage'), 'icon' => '🗂',
                        'route' => 'ablage', 'aktiv' => ['ablage', 'ablage_kategorie_edit', 'ki_suche'], 'darf' => can('view_ablage')],
        'karten'    => ['label' => (string)setting('karten_modul_name', 'Karten'), 'icon' => '🗺',
                        'route' => 'karten', 'aktiv' => ['karten', 'karte', 'karte_edit', 'karte_protokoll', 'karten_symbole', 'karten_github'], 'darf' => can('view_karten')],
        'thw'       => ['label' => (string)setting('thw_modul_name', 'THW-Kontakte'), 'icon' => '⛑',
                        'route' => 'thw', 'aktiv' => ['thw', 'thw_dienststelle_edit', 'thw_postfach_edit'], 'darf' => true],
        'stan'      => ['label' => (string)setting('stan_modul_name', 'STAN'), 'icon' => '📋',
                        'route' => 'stan', 'aktiv' => ['stan'], 'darf' => true],
        'kalender'  => ['label' => (string)setting('kalender_modul_name', 'Kalender'), 'icon' => '▤',
                        'route' => 'kalender', 'aktiv' => ['kalender', 'kalender_termin', 'kalender_edit'], 'darf' => true],
        'meetings'  => ['label' => (string)setting('besprechung_modul_name', 'Besprechungen'), 'icon' => '☰',
                        'route' => 'meetings',
                        'aktiv' => ['meetings', 'meeting', 'meeting_edit', 'meeting_series_edit', 'talking_points', 'talking_point_edit'],
                        'darf' => can('view_meetings')],
        'admin'     => ['label' => 'Verwaltung', 'icon' => '⚙', 'route' => 'admin',
                        'aktiv' => ['admin', 'admin_users', 'admin_user_edit', 'admin_lists', 'admin_list_edit',
                                    'admin_settings', 'admin_divera', 'admin_divera_form', 'admin_order_rights',
                                    'admin_stein', 'admin_divera_fahrzeuge', 'admin_connectors', 'admin_connector',
                                    'admin_log', 'admin_backup', 'admin_ha', 'admin_nav', 'admin_kalender', 'admin_standorte', 'admin_standort_edit', 'admin_mail'],
                        'darf' => can('admin')],
    ];
}

/* ==================================================================== */
/* Der Menübaum: Gruppen mit ihren Modulen                               */
/* ==================================================================== */

/**
 * Der Grundvorschlag: Übersicht, Dateiablage und Verwaltung stehen für sich, alles andere
 * in Gruppen. Ein Knoten ist entweder ein Modul (['modul' => Schlüssel]) oder eine Gruppe
 * (['gruppe' => Name, 'icon' => Zeichen, 'module' => [Schlüssel, …]]).
 * Die Namen sind bewusst kurz: am Handy steht die Leiste unten, mit wenig Platz je Eintrag.
 */
const NAV_GRUNDVORSCHLAG = [
    ['modul' => 'dashboard'],
    ['gruppe' => 'Einsatz',    'icon' => '🚨', 'module' => ['einsaetze', 'karten']],
    ['gruppe' => 'Planung',    'icon' => '€',  'module' => ['wishes', 'budget', 'todos']],
    ['gruppe' => 'Termine',    'icon' => '▤',  'module' => ['kalender', 'meetings', 'events', 'umfragen']],
    ['gruppe' => 'Kontakte',   'icon' => '✉',  'module' => ['contacts', 'thw', 'newsletter']],
    ['gruppe' => 'Technik',    'icon' => '⛟',  'module' => ['vehicles', 'radios', 'sims', 'tankkarten', 'stan']],
    ['gruppe' => 'Unterkunft', 'icon' => '🏠', 'module' => ['unterkunft', 'standorte', 'verbrauch', 'getraenke']],
    ['modul' => 'ablage'],
    ['modul' => 'admin'],
];
const NAV_GRUPPE_NAME_MAX = 40;
const NAV_GRUPPEN_MAX = 20;
const NAV_GRUPPE_ICON = '▸';

/** Name einer Gruppe: ohne Leerraum am Rand, höchstens 40 Zeichen. Reine Funktion. */
function nav_gruppe_name(mixed $name): string
{
    return mb_substr(trim((string)preg_replace('/\s+/u', ' ', is_scalar($name) ? (string)$name : '')), 0, NAV_GRUPPE_NAME_MAX);
}

/** Zeichen einer Gruppe: ein kurzes Symbol, sonst die Vorgabe. Reine Funktion. */
function nav_gruppe_icon(mixed $icon): string
{
    $icon = mb_substr(trim(is_scalar($icon) ? (string)$icon : ''), 0, 4);
    return $icon !== '' ? $icon : NAV_GRUPPE_ICON;
}

/**
 * Einen Baum in Form bringen: nur bekannte Module, jedes einmal (das erste Vorkommen zählt),
 * Gruppen mit Namen und Zeichen. Module, die im Baum fehlen – etwa ein neues nach einem
 * Update –, kommen auf die oberste Ebene, vor die Verwaltung, wenn die am Ende steht.
 * Leere Gruppen bleiben stehen: sie lassen sich in der Verwaltung füllen. Reine Funktion.
 */
function nav_baum_bereinigen(mixed $baum, array $modulKeys): array
{
    $bekannt = array_fill_keys($modulKeys, true);
    $gesehen = [];
    $out = [];
    foreach (is_array($baum) ? $baum : [] as $knoten) {
        if (!is_array($knoten)) {
            continue;
        }
        if (array_key_exists('gruppe', $knoten)) {
            $name = nav_gruppe_name($knoten['gruppe']);
            if ($name === '' || count(array_filter($out, static fn($k) => isset($k['gruppe']))) >= NAV_GRUPPEN_MAX) {
                $name = '';
            }
            $module = [];
            foreach ((array)($knoten['module'] ?? []) as $key) {
                $key = is_scalar($key) ? (string)$key : '';
                if (isset($bekannt[$key]) && !isset($gesehen[$key])) {
                    $gesehen[$key] = true;
                    $module[] = $key;
                }
            }
            if ($name === '') {
                // eine Gruppe ohne Namen gibt es nicht: ihre Module stehen dann für sich
                foreach ($module as $key) {
                    $out[] = ['modul' => $key];
                }
                continue;
            }
            $out[] = ['gruppe' => $name, 'icon' => nav_gruppe_icon($knoten['icon'] ?? ''), 'module' => $module];
        } elseif (isset($knoten['modul']) && is_scalar($knoten['modul'])) {
            $key = (string)$knoten['modul'];
            if (isset($bekannt[$key]) && !isset($gesehen[$key])) {
                $gesehen[$key] = true;
                $out[] = ['modul' => $key];
            }
        }
    }
    $fehlend = array_values(array_filter($modulKeys, static fn($k) => !isset($gesehen[$k])));
    if ($fehlend) {
        $letzter = $out ? $out[count($out) - 1] : null;
        $ende = $letzter !== null && ($letzter['modul'] ?? '') === 'admin' ? [array_pop($out)] : [];
        foreach ($fehlend as $key) {
            $out[] = ['modul' => $key];
        }
        $out = array_merge($out, $ende);
    }
    return $out;
}

/** Ist in den internen Merkwerten ein eigener Baum abgelegt? */
function nav_baum_eigen(): bool
{
    return trim(state_get('nav_baum', '')) !== '';
}

/** Der Baum, wie er gilt: der gespeicherte, sonst der Grundvorschlag – immer bereinigt */
function nav_baum(): array
{
    $keys = array_keys(nav_module());
    $roh = trim(state_get('nav_baum', ''));
    $baum = $roh !== '' ? json_decode($roh, true) : null;
    return nav_baum_bereinigen(is_array($baum) && $baum ? $baum : NAV_GRUNDVORSCHLAG, $keys);
}

function nav_baum_speichern(array $baum): void
{
    $baum = nav_baum_bereinigen($baum, array_keys(nav_module()));
    state_save('nav_baum', (string)json_encode($baum, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Der Baum, wie die angemeldete Person ihn sieht. Module ohne Recht fallen weg, Gruppen
 * ohne sichtbares Modul ebenso; eine Gruppe mit nur einem sichtbaren Modul zeigt das Modul
 * selbst. Je Knoten: typ ('modul' | 'gruppe'), aktiv – beim Modul key und m (die
 * Definition), bei der Gruppe id, name, icon, route (das erste Modul) und module.
 * Reine Funktion über Baum und Module.
 */
function nav_baum_sichtbar(array $baum, array $module, string $seite): array
{
    $out = [];
    $nr = 0;
    $modul = static function (string $key) use ($module, $seite): ?array {
        $m = $module[$key] ?? null;
        return $m && !empty($m['darf']) ? ['typ' => 'modul', 'key' => $key, 'm' => $m, 'aktiv' => in_array($seite, (array)$m['aktiv'], true)] : null;
    };
    foreach ($baum as $knoten) {
        if (isset($knoten['modul'])) {
            if (($m = $modul((string)$knoten['modul'])) !== null) {
                $out[] = $m;
            }
            continue;
        }
        $nr++;
        $drin = array_values(array_filter(array_map($modul, (array)($knoten['module'] ?? []))));
        if (count($drin) === 1) {
            $out[] = $drin[0];
        } elseif ($drin) {
            $out[] = ['typ' => 'gruppe', 'id' => 'g' . $nr, 'name' => (string)$knoten['gruppe'], 'icon' => (string)($knoten['icon'] ?? NAV_GRUPPE_ICON),
                      'route' => (string)$drin[0]['m']['route'], 'module' => $drin,
                      'aktiv' => (bool)array_filter($drin, static fn($d) => $d['aktiv'])];
        }
    }
    return $out;
}

/** Das Menü der angemeldeten Person */
function nav_baum_fuer(): array
{
    return nav_baum_sichtbar(nav_baum(), nav_module(), (string)($_GET['p'] ?? 'dashboard'));
}

/** Alle Module, die die angemeldete Person sieht, in der Reihenfolge des Baums: [key => Definition] */
function nav_leiste(): array
{
    $out = [];
    foreach (nav_baum_fuer() as $knoten) {
        foreach ($knoten['typ'] === 'gruppe' ? $knoten['module'] : [$knoten] as $m) {
            $out[$m['key']] = $m['m'];
        }
    }
    return $out;
}

/* ---------------- Den Baum umbauen – reine Funktionen für die Verwaltung ---------------- */

/** Einen Eintrag einer Liste um eine Stelle verschieben (-1 hoch, +1 runter). Reine Funktion. */
function nav_liste_verschieben(array $liste, int $i, int $richtung): array
{
    $liste = array_values($liste);
    $j = $i + ($richtung < 0 ? -1 : 1);
    if ($i < 0 || $i >= count($liste) || $j < 0 || $j >= count($liste)) {
        return $liste;
    }
    [$liste[$i], $liste[$j]] = [$liste[$j], $liste[$i]];
    return $liste;
}

/**
 * Einen Knoten der obersten Ebene ($modul leer) oder ein Modul in seiner Gruppe um eine
 * Stelle verschieben. $knoten ist die Stelle auf der obersten Ebene. Reine Funktion.
 */
function nav_baum_verschieben(array $baum, int $knoten, string $modul, int $richtung): array
{
    $baum = array_values($baum);
    if ($modul === '') {
        return nav_liste_verschieben($baum, $knoten, $richtung);
    }
    if (isset($baum[$knoten]['module'])) {
        $i = array_search($modul, $baum[$knoten]['module'], true);
        if ($i !== false) {
            $baum[$knoten]['module'] = nav_liste_verschieben($baum[$knoten]['module'], (int)$i, $richtung);
        }
    }
    return $baum;
}

/**
 * Ein Modul umhängen: $ziel ist die Stelle einer Gruppe auf der obersten Ebene oder -1 für
 * die oberste Ebene selbst. In der Gruppe kommt es ans Ende; auf der obersten Ebene an die
 * Stelle seiner bisherigen Gruppe. Reine Funktion.
 */
function nav_baum_umhaengen(array $baum, string $modul, int $ziel): array
{
    $baum = array_values($baum);
    if ($ziel >= 0 && !isset($baum[$ziel]['gruppe'])) {
        return $baum;
    }
    $zielName = $ziel >= 0 ? $baum[$ziel]['gruppe'] : null;
    $herkunft = null;   // Stelle auf der obersten Ebene, an der das Modul stand
    foreach ($baum as $i => $k) {
        if (($k['modul'] ?? null) === $modul) {
            $herkunft = $i;
            unset($baum[$i]);
            break;
        }
        if (isset($k['module']) && in_array($modul, $k['module'], true)) {
            if ($i === $ziel) {
                return array_values($baum);   // steht schon dort
            }
            $baum[$i]['module'] = array_values(array_diff($k['module'], [$modul]));
            $herkunft = $i;
            break;
        }
    }
    if ($herkunft === null) {
        return array_values($baum);
    }
    if ($zielName === null) {
        // hinter die Gruppe, aus der es kommt (oder an seine alte Stelle)
        $neu = [];
        foreach ($baum as $i => $k) {
            $neu[] = $k;
            if ($i === $herkunft) {
                $neu[] = ['modul' => $modul];
            }
        }
        return isset($baum[$herkunft]) ? $neu : array_merge(array_values($baum), [['modul' => $modul]]);
    }
    foreach ($baum as $i => $k) {
        if ($i === $ziel) {
            $baum[$i]['module'][] = $modul;
        }
    }
    return array_values($baum);
}

/** Eine neue, leere Gruppe – vor der Verwaltung, wenn die am Ende steht. Reine Funktion. */
function nav_baum_gruppe_neu(array $baum, string $name, string $icon = ''): array
{
    $baum = array_values($baum);
    $name = nav_gruppe_name($name);
    if ($name === '' || count(array_filter($baum, static fn($k) => isset($k['gruppe']))) >= NAV_GRUPPEN_MAX) {
        return $baum;
    }
    $neu = ['gruppe' => $name, 'icon' => nav_gruppe_icon($icon), 'module' => []];
    $letzter = $baum ? $baum[count($baum) - 1] : null;
    if ($letzter !== null && ($letzter['modul'] ?? '') === 'admin') {
        array_splice($baum, count($baum) - 1, 0, [$neu]);
        return $baum;
    }
    $baum[] = $neu;
    return $baum;
}

/** Name und Zeichen einer Gruppe ändern; ein leerer Name ändert nichts. Reine Funktion. */
function nav_baum_gruppe_aendern(array $baum, int $knoten, string $name, string $icon): array
{
    $baum = array_values($baum);
    if (isset($baum[$knoten]['gruppe']) && nav_gruppe_name($name) !== '') {
        $baum[$knoten]['gruppe'] = nav_gruppe_name($name);
        $baum[$knoten]['icon'] = nav_gruppe_icon($icon);
    }
    return $baum;
}

/** Eine Gruppe auflösen: ihre Module stehen danach an ihrer Stelle auf der obersten Ebene. Reine Funktion. */
function nav_baum_gruppe_aufloesen(array $baum, int $knoten): array
{
    $baum = array_values($baum);
    if (!isset($baum[$knoten]['gruppe'])) {
        return $baum;
    }
    $einzeln = array_map(static fn($key) => ['modul' => $key], (array)$baum[$knoten]['module']);
    array_splice($baum, $knoten, 1, $einzeln);
    return $baum;
}

/** Alles auf eine Ebene: jede Gruppe aufgelöst, die Reihenfolge bleibt. Reine Funktion. */
function nav_baum_flach(array $baum): array
{
    $out = [];
    foreach ($baum as $k) {
        foreach (isset($k['module']) ? (array)$k['module'] : [(string)($k['modul'] ?? '')] as $key) {
            if ($key !== '') {
                $out[] = ['modul' => $key];
            }
        }
    }
    return $out;
}
