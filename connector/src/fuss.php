<?php
/**
 * Fußzeile der offenen Seiten: Betreiber, Impressum, Datenschutz.
 *
 * Die Angaben kommen signiert aus OV-Multitool (Verwaltung → Einstellungen
 * → Connector) und liegen in daten/einstellungen.json. Fehlen sie, bleibt die
 * Fußzeile leer – und die Seite verrät nichts.
 */
declare(strict_types=1);

$fuss = con_einstellungen();
$teile = [];
if ($fuss['betreiber'] !== '') {
    $teile[] = htmlspecialchars($fuss['betreiber']);
}
if ($fuss['impressum_url'] !== '') {
    $teile[] = '<a href="' . htmlspecialchars($fuss['impressum_url']) . '" rel="noopener">Impressum</a>';
}
if ($fuss['datenschutz_url'] !== '') {
    $teile[] = '<a href="' . htmlspecialchars($fuss['datenschutz_url']) . '" rel="noopener">Datenschutz</a>';
}
if ($teile) {
    echo '<footer class="klein" style="margin-top:2.5rem;text-align:center;color:#64748b">'
        . implode(' · ', $teile) . "</footer>\n";
}
