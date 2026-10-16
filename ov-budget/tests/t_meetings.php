<?php
/** Tests für Tagesordnungszeiten und Reihenfolge. */
declare(strict_types=1);

require dirname(__DIR__) . '/src/lib/meetings.php';
if (!function_exists('de_date')) { function de_date(?string $d): string { return $d ? date('d.m.Y', strtotime($d)) : ''; } }

$ok = 0;
$bad = 0;
function is_it(string $name, mixed $got, mixed $want): void
{
    global $ok, $bad;
    if ($got === $want) {
        $ok++;
        return;
    }
    $bad++;
    echo "FEHL  $name\n      erwartet: " . var_export($want, true) . "\n      erhalten: " . var_export($got, true) . "\n";
}

echo "=== Uhrzeiten der Tagesordnung ===\n";
is_it('fortlaufend', meeting_agenda_times('19:30', [15, 10, 20]), ['19:30', '19:45', '19:55']);
is_it('Sekunden aus der Datenbank', meeting_agenda_times('19:30:00', [5, 5]), ['19:30', '19:35']);
is_it('fehlende Dauer nimmt Vorgabe', meeting_agenda_times('19:00', [null, 0, 30], 10), ['19:00', '19:10', '19:20']);
is_it('ueber die volle Stunde', meeting_agenda_times('19:50', [20, 5]), ['19:50', '20:10']);
is_it('ueber Mitternacht', meeting_agenda_times('23:50', [15, 5]), ['23:50', '00:05']);
is_it('ohne Beginn leere Zeiten', meeting_agenda_times(null, [10, 10]), ['', '']);
is_it('kaputter Beginn leere Zeiten', meeting_agenda_times('abends', [10]), ['']);
is_it('keine Punkte', meeting_agenda_times('19:00', []), []);

echo "\n=== Dauer ===\n";
is_it('Summe mit Vorgabe', meeting_total_minutes([15, null, 20], 10), 45);
is_it('leer', meeting_total_minutes([]), 0);
is_it('unter einer Stunde', minutes_human(45), '45 Min.');
is_it('genau eine Stunde', minutes_human(60), '1 Std.');
is_it('Stunden und Minuten', minutes_human(95), '1 Std. 35 Min.');

echo "\n=== Reihenfolge ===\n";
is_it('nach oben', tp_reorder([1, 2, 3], 2, 'hoch'), [2, 1, 3]);
is_it('nach unten', tp_reorder([1, 2, 3], 2, 'runter'), [1, 3, 2]);
is_it('oben am Rand bleibt', tp_reorder([1, 2, 3], 1, 'hoch'), [1, 2, 3]);
is_it('unten am Rand bleibt', tp_reorder([1, 2, 3], 3, 'runter'), [1, 2, 3]);
is_it('unbekannte id bleibt', tp_reorder([1, 2, 3], 9, 'hoch'), [1, 2, 3]);
is_it('Zeichenketten aus der Datenbank', tp_reorder(['4', '7'], 7, 'hoch'), [7, 4]);

is_it('Datumstext eintägig', meeting_datum_text(['datum' => '2026-12-05', 'ende_datum' => null]), '05.12.2026');
is_it('Datumstext mehrtägig', meeting_datum_text(['datum' => '2026-12-05', 'ende_datum' => '2026-12-06']), '05.12.2026 bis 06.12.2026');
is_it('Datumstext gleicher Tag', meeting_datum_text(['datum' => '2026-12-05', 'ende_datum' => '2026-12-05']), '05.12.2026');
is_it('Fahrzeug-Unterabfrage', str_contains(meeting_fahrzeuge_sub('m'), 'FROM meeting_vehicles mv JOIN vehicles v') && str_ends_with(meeting_fahrzeuge_sub('x'), 'mv.meeting_id = x.id)'), true);

is_it('Art des Treffens: gültig oder Vorgabe', [meeting_treffen('online'), meeting_treffen('ausserhaus'), meeting_treffen('quatsch'), meeting_treffen('')], ['online', 'ausserhaus', 'inhaus', 'inhaus']);
is_it('Link: leer geht, https mit Rechner geht', [meeting_link_pruefen(''), meeting_link_pruefen('https://teams.microsoft.com/l/meetup-join/abc'), meeting_link_pruefen('https://thw.webex.com/meet/ov')], [null, null, null]);
is_it('Link: http, ohne Punkt, zu lang werden abgewiesen', [meeting_link_pruefen('http://teams.microsoft.com/x') !== null, meeting_link_pruefen('https://localhost/x') !== null, meeting_link_pruefen('https://a.de/' . str_repeat('x', 500)) !== null, meeting_link_pruefen('teams.microsoft.com') !== null], [true, true, true, true]);
is_it('Treffen-Text', [meeting_treffen_text(['treffen' => 'inhaus']), meeting_treffen_text(['treffen' => 'online']), meeting_treffen_text(['treffen' => 'ausserhaus']), meeting_treffen_text([])], ['', '💻 Online', '📍 Außer Haus', '']);
is_it('Navi nur außer Haus mit Ort', [meeting_navi(['treffen' => 'inhaus', 'ort' => 'Musterstraße 1']), meeting_navi(['treffen' => 'ausserhaus', 'ort' => '  '])], [null, null]);

echo "\n$ok bestanden, $bad fehlgeschlagen\n";
exit($bad > 0 ? 1 : 0);
