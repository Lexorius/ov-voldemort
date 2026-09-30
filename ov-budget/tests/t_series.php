<?php
/**
 * Tests für wiederkehrende Besprechungen.
 * Erwartete Daten sind von Hand aus dem Kalender abgeleitet
 * (1.1.2026 = Donnerstag, 16.9.2026 = Mittwoch).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/lib/series.php';

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

function serie(array $x): array
{
    return $x + ['intervall' => 1, 'wochentag' => 1, 'nte' => 1, 'monatstag' => 1, 'end_datum' => null];
}

echo "=== Hilfsfunktion n-ter Wochentag ===\n";
is_it('2. Montag im September 2026', serie_nter_wochentag(2026, 9, 2, 1), '2026-09-14');
is_it('letzter Montag im September 2026', serie_nter_wochentag(2026, 9, -1, 1), '2026-09-28');
is_it('letzter Freitag im Oktober 2026', serie_nter_wochentag(2026, 10, -1, 5), '2026-10-30');
is_it('1. Montag, Monat beginnt am Montag (Feb 2027)', serie_nter_wochentag(2027, 2, 1, 1), '2027-02-01');
is_it('4. Montag im Februar 2027', serie_nter_wochentag(2027, 2, 4, 1), '2027-02-22');
is_it('letzter Montag im Februar 2027 = 4.', serie_nter_wochentag(2027, 2, -1, 1), '2027-02-22');
is_it('letzter Tag ist selbst der Wochentag (Nov 2026, Montag 30.)', serie_nter_wochentag(2026, 11, -1, 1), '2026-11-30');

echo "\n=== alle 2 Wochen montags ===\n";
$zweiwoechig = serie(['regel' => 'woche', 'intervall' => 2, 'wochentag' => 1, 'start_datum' => '2026-09-01']);
is_it('Rhythmus ab dem ersten Montag nach Serienbeginn',
    series_occurrences($zweiwoechig, '2026-09-01', '2026-10-31'), ['2026-09-07', '2026-09-21', '2026-10-05', '2026-10-19']);
is_it('Abfrage ab heute bleibt im Rhythmus (nicht der 14.9.)',
    series_occurrences($zweiwoechig, '2026-09-16', '2026-10-31'), ['2026-09-21', '2026-10-05', '2026-10-19']);
is_it('weit in der Zukunft, ueber den Jahreswechsel',
    series_occurrences($zweiwoechig, '2027-01-01', '2027-01-31'), ['2027-01-11', '2027-01-25']);
is_it('Abfrage genau an einem Termin schliesst ihn ein',
    series_occurrences($zweiwoechig, '2026-10-05', '2026-10-05'), ['2026-10-05']);
is_it('Abfrage genau zwischen zwei Terminen',
    series_occurrences($zweiwoechig, '2026-10-06', '2026-10-18'), []);

is_it('Ende der Serie einschliesslich',
    series_occurrences(['end_datum' => '2026-10-05'] + $zweiwoechig, '2026-09-01', '2026-12-31'),
    ['2026-09-07', '2026-09-21', '2026-10-05']);
is_it('Serie beginnt an einem Montag: dieser zaehlt',
    series_occurrences(serie(['regel' => 'woche', 'wochentag' => 1, 'start_datum' => '2026-09-14']), '2026-09-01', '2026-09-28'),
    ['2026-09-14', '2026-09-21', '2026-09-28']);
is_it('Sonntag',
    series_occurrences(serie(['regel' => 'woche', 'wochentag' => 7, 'start_datum' => '2026-09-14']), '2026-09-14', '2026-09-30'),
    ['2026-09-20', '2026-09-27']);
is_it('Abfrage vor Serienbeginn liefert erst ab Beginn',
    series_occurrences(serie(['regel' => 'woche', 'wochentag' => 3, 'start_datum' => '2026-09-16']), '2026-01-01', '2026-09-30'),
    ['2026-09-16', '2026-09-23', '2026-09-30']);
is_it('leerer Zeitraum', series_occurrences($zweiwoechig, '2026-10-31', '2026-10-01'), []);
is_it('Serie schon beendet', series_occurrences(['end_datum' => '2026-08-31'] + $zweiwoechig, '2026-09-01', '2026-12-31'), []);

echo "\n=== jeden 2. Montag im Monat ===\n";
$zweiterMontag = serie(['regel' => 'monat_wochentag', 'nte' => 2, 'wochentag' => 1, 'start_datum' => '2026-09-01']);
is_it('Sept., Okt., Nov. 2026',
    series_occurrences($zweiterMontag, '2026-09-01', '2026-11-30'), ['2026-09-14', '2026-10-12', '2026-11-09']);
is_it('ab heute: der 14.9. ist schon vorbei',
    series_occurrences($zweiterMontag, '2026-09-16', '2026-11-30'), ['2026-10-12', '2026-11-09']);
is_it('Serienbeginn nach dem Termin im ersten Monat',
    series_occurrences(['start_datum' => '2026-09-20'] + $zweiterMontag, '2026-09-01', '2026-10-31'), ['2026-10-12']);
is_it('ueber den Jahreswechsel, erster Montag',
    series_occurrences(serie(['regel' => 'monat_wochentag', 'nte' => 1, 'wochentag' => 1, 'start_datum' => '2026-11-01']),
        '2026-11-01', '2027-02-28'),
    ['2026-11-02', '2026-12-07', '2027-01-04', '2027-02-01']);

$quartal = serie(['regel' => 'monat_wochentag', 'intervall' => 3, 'nte' => 2, 'wochentag' => 1, 'start_datum' => '2026-09-01']);
is_it('alle 3 Monate am 2. Montag',
    series_occurrences($quartal, '2026-09-01', '2027-03-31'), ['2026-09-14', '2026-12-14', '2027-03-08']);
is_it('alle 3 Monate: dazwischen nichts',
    series_occurrences($quartal, '2027-01-01', '2027-02-28'), []);
is_it('alle 3 Monate: Sprung mitten in die Serie',
    series_occurrences($quartal, '2027-03-01', '2027-03-31'), ['2027-03-08']);

is_it('jeden letzten Freitag im Monat',
    series_occurrences(serie(['regel' => 'monat_wochentag', 'nte' => -1, 'wochentag' => 5, 'start_datum' => '2026-10-01']),
        '2026-10-01', '2026-11-30'),
    ['2026-10-30', '2026-11-27']);

echo "\n=== monatlich an einem Kalendertag ===\n";
is_it('am 31.: kurze Monate fallen auf den Monatsletzten',
    series_occurrences(serie(['regel' => 'monat_tag', 'monatstag' => 31, 'start_datum' => '2027-01-01']), '2027-01-01', '2027-04-30'),
    ['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30']);
is_it('Schaltjahr', series_occurrences(serie(['regel' => 'monat_tag', 'monatstag' => 31, 'start_datum' => '2028-02-01']), '2028-02-01', '2028-02-29'),
    ['2028-02-29']);
is_it('am 15.', series_occurrences(serie(['regel' => 'monat_tag', 'monatstag' => 15, 'start_datum' => '2026-09-16']), '2026-09-16', '2026-11-30'),
    ['2026-10-15', '2026-11-15']);
is_it('unbekannte Regel', series_occurrences(serie(['regel' => 'jaehrlich', 'start_datum' => '2026-01-01']), '2026-01-01', '2026-12-31'), []);

echo "\n=== Beschreibung ===\n";
is_it('jeden Montag mit Uhrzeit', series_describe(serie(['regel' => 'woche', 'beginn' => '19:30:00'])), 'jeden Montag um 19:30 Uhr');
is_it('alle 2 Wochen', series_describe(serie(['regel' => 'woche', 'intervall' => 2])), 'alle 2 Wochen montags');
is_it('jeden 2. Montag im Monat', series_describe(serie(['regel' => 'monat_wochentag', 'nte' => 2])), 'jeden 2. Montag im Monat');
is_it('letzter Freitag', series_describe(serie(['regel' => 'monat_wochentag', 'nte' => -1, 'wochentag' => 5])), 'jeden letzten Freitag im Monat');
is_it('Quartal', series_describe(serie(['regel' => 'monat_wochentag', 'intervall' => 3, 'nte' => 2, 'wochentag' => 2])), 'alle 3 Monate am 2. Dienstag');
is_it('monatlich am 15.', series_describe(serie(['regel' => 'monat_tag', 'monatstag' => 15])), 'monatlich am 15.');
is_it('alle 3 Monate am 1.', series_describe(serie(['regel' => 'monat_tag', 'intervall' => 3])), 'alle 3 Monate am 1.');
is_it('einstellige Stunde', series_describe(serie(['regel' => 'woche', 'beginn' => '9:05'])), 'jeden Montag um 09:05 Uhr');

echo "\n$ok bestanden, $bad fehlgeschlagen\n";
exit($bad > 0 ? 1 : 0);
