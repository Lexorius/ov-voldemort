<?php
declare(strict_types=1);

/** View innerhalb des Layouts rendern */
function render(string $view, array $vars = []): void
{
    $__content = render_partial($view, $vars);
    $title = $vars['title'] ?? null;
    include dirname(__DIR__, 2) . '/views/layout.php';
}

/** View ohne Layout als String */
function render_partial(string $view, array $vars = []): string
{
    $file = dirname(__DIR__, 2) . '/views/' . $view . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('View nicht gefunden: ' . $view);
    }
    extract($vars, EXTR_SKIP);
    ob_start();
    include $file;
    return (string)ob_get_clean();
}

/** Aktive Navigation markieren */
function nav_active(string $route, string ...$also): string
{
    $cur = $_GET['p'] ?? 'dashboard';
    return ($cur === $route || in_array($cur, $also, true)) ? ' is-active' : '';
}

/**
 * Kuchendiagramm als SVG, ohne Skript: $teile = [['label', 'wert', 'farbe'], …].
 * Stücke mit Wert null oder kleiner fallen weg; ein einzelnes Stück wird ein
 * voller Kreis; ohne Stück kommt ''. Jedes Stück trägt seinen Anteil als
 * <title>, damit er beim Zeigen und für Vorleser lesbar ist. Reine Funktion.
 */
function kuchen_svg(array $teile, int $groesse = 120): string
{
    $teile = array_values(array_filter($teile, static fn($t) => (float)($t['wert'] ?? 0) > 0));
    $summe = array_sum(array_map(static fn($t) => (float)$t['wert'], $teile));
    if ($summe <= 0) {
        return '';
    }
    $r = 50.0;
    $svg = '<svg class="kuchen__bild" viewBox="-52 -52 104 104" width="' . $groesse . '" height="' . $groesse . '" role="img" aria-label="Kuchendiagramm">';
    if (count($teile) === 1) {
        $svg .= '<circle r="' . $r . '" fill="' . e((string)$teile[0]['farbe']) . '"><title>' . e((string)$teile[0]['label']) . ': 100 %</title></circle>';
        return $svg . '</svg>';
    }
    $winkel = -M_PI / 2;   // oben anfangen, im Uhrzeigersinn
    foreach ($teile as $t) {
        $anteil = (float)$t['wert'] / $summe;
        $bis = $winkel + $anteil * 2 * M_PI;
        $x1 = round(cos($winkel) * $r, 3);
        $y1 = round(sin($winkel) * $r, 3);
        $x2 = round(cos($bis) * $r, 3);
        $y2 = round(sin($bis) * $r, 3);
        $gross = $anteil > 0.5 ? 1 : 0;
        $svg .= '<path d="M0 0 L' . $x1 . ' ' . $y1 . ' A' . $r . ' ' . $r . ' 0 ' . $gross . ' 1 ' . $x2 . ' ' . $y2 . ' Z" fill="' . e((string)$t['farbe']) . '" stroke="var(--surface)" stroke-width="1">'
            . '<title>' . e((string)$t['label']) . ': ' . number_format($anteil * 100, 1, ',', '.') . ' %</title></path>';
        $winkel = $bis;
    }
    return $svg . '</svg>';
}

/**
 * Legende zum Kuchen: Farbpunkt, Name, Betrag und Anteil je Stück – dieselben
 * Stücke wie bei kuchen_svg(). Reine Funktion.
 */
function kuchen_legende(array $teile): string
{
    $teile = array_values(array_filter($teile, static fn($t) => (float)($t['wert'] ?? 0) > 0));
    $summe = array_sum(array_map(static fn($t) => (float)$t['wert'], $teile));
    if ($summe <= 0) {
        return '';
    }
    $html = '<ul class="kuchen__legende">';
    foreach ($teile as $t) {
        $html .= '<li><span class="kuchen__punkt" style="background:' . e((string)$t['farbe']) . '"></span>'
            . '<span class="kuchen__name">' . e((string)$t['label']) . '</span>'
            . '<span class="kuchen__zahl">' . e(money((float)$t['wert'])) . ' · ' . number_format((float)$t['wert'] / $summe * 100, 1, ',', '.') . ' %</span></li>';
    }
    return $html . '</ul>';
}
