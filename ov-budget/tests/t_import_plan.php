<?php
/** Tests für Kategoriezuordnung, Zusatzfelder, Entscheidung und Zusammenführen. */
declare(strict_types=1);

require dirname(__DIR__) . '/src/lib/import.php';

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

echo "=== Kategorie strikt zuordnen ===\n";
$kat = [
    ['id' => 1, 'label' => 'Kommune / Verwaltung', 'slug' => 'kommune'],
    ['id' => 2, 'label' => 'Politik', 'slug' => 'politik'],
    ['id' => 3, 'label' => 'IT / Kommunikation', 'slug' => 'it'],
    ['id' => 4, 'label' => 'Presse / Medien', 'slug' => 'presse'],
    ['id' => 5, 'label' => 'Feuerwehr', 'slug' => 'feuerwehr'],
];
is_it('gleicher Name', import_match_list('Politik', $kat), 2);
is_it('Gross-/Kleinschreibung egal', import_match_list('POLITIK', $kat), 2);
is_it('gleicher Schluessel', import_match_list('it', $kat), 3);
is_it('Anfang der Bezeichnung', import_match_list('Kommune', $kat), 1);
is_it('Anfang mit Zeichen', import_match_list('Presse/Medien', $kat), 4);
// Die Falle der alten Teilstring-Suche
is_it('"Mitglieder" landet NICHT bei IT', import_match_list('Mitglieder', $kat), null);
is_it('"Politiker" ist nicht Politik', import_match_list('Politiker', $kat), null);
is_it('zu kurz fuer Anfangsvergleich', import_match_list('Ko', $kat), null);
is_it('Wehr ist nicht Feuerwehr', import_match_list('Wehr', $kat), null);
is_it('leer', import_match_list('', $kat), null);

echo "\n=== Zusatzfelder ===\n";
is_it('bool ja', import_extra_value('Ja', 'bool'), '1');
is_it('bool x', import_extra_value('x', 'bool'), '1');
is_it('bool nein', import_extra_value('nein', 'bool'), '0');
is_it('bool leer bleibt leer', import_extra_value('', 'bool'), '');
is_it('Datum deutsch', import_extra_value('5.3.2019', 'date'), '2019-03-05');
is_it('Datum ISO', import_extra_value('2019-03-05', 'date'), '2019-03-05');
is_it('Datum Unfug', import_extra_value('irgendwann', 'date'), '');
is_it('Zahl deutsch', import_extra_value('1.234,50', 'number'), '1234.50');
is_it('Zahl Unfug', import_extra_value('viel', 'number'), '');
is_it('Text bleibt', import_extra_value(' Patenschaft B1 ', 'text'), 'Patenschaft B1');

echo "\n=== Entscheidung je Zeile ===\n";
$zeilen = [
    ['nachname' => 'Berg', 'vorname' => 'Anna', 'email' => 'anna@x.de'],    // 1: per Mail vorhanden
    ['nachname' => 'Stein', 'vorname' => 'Peter'],                          // 2: per Name vorhanden
    ['nachname' => 'Neu', 'organisation' => 'Firma'],                       // 3: neu
    ['telefon' => '0123'],                                                  // 4: unbrauchbar
    ['nachname' => 'Neu', 'organisation' => 'Firma'],                       // 5: doppelt in Datei
    ['nachname' => 'Anders', 'email' => 'ANNA@x.de'],                       // 6: gleiche Mail wie Zeile 1
];
$index = [
    'm:anna@x.de'                       => 10,
    'n:peter|stein|'                    => 20,
];

$plan = import_decide($zeilen, $index, 'ueberspringen');
is_it('Aktionen beim Ueberspringen', array_column($plan, 'aktion'),
    ['ueberspringen', 'ueberspringen', 'neu', 'fehler', 'ueberspringen', 'ueberspringen']);
is_it('Treffer per Mail', $plan[0]['id'], 10);
is_it('Treffer per Name', $plan[1]['id'], 20);
is_it('Grund bei Unbrauchbarem', $plan[3]['grund'], 'weder Nachname noch Organisation');
is_it('Grund bei Doppeltem', $plan[4]['grund'], 'doppelt in der Datei, wie Zeile 3');
is_it('gleiche Mail anders geschrieben ist doppelt', $plan[5]['grund'], 'doppelt in der Datei, wie Zeile 1');

$plan = import_decide($zeilen, $index, 'ergaenzen');
is_it('Aktionen beim Ergaenzen', array_column($plan, 'aktion'),
    ['ergaenzen', 'ergaenzen', 'neu', 'fehler', 'ueberspringen', 'ueberspringen']);

$plan = import_decide($zeilen, $index, 'neu');
is_it('Immer neu: Vorhandene werden neu, Doppelte in der Datei trotzdem nicht',
    array_column($plan, 'aktion'), ['neu', 'neu', 'neu', 'fehler', 'ueberspringen', 'ueberspringen']);
is_it('Immer neu: keine id', $plan[0]['id'], null);

is_it('unbekannte Strategie wird zu Ueberspringen',
    array_column(import_decide([$zeilen[0]], $index, 'loeschen'), 'aktion'), ['ueberspringen']);
is_it('Zeilennummern beginnen bei 1', array_column(import_decide($zeilen, [], 'neu'), 'zeile'), [1, 2, 3, 4, 5, 6]);
is_it('leere Datei', import_decide([], $index, 'neu'), []);

echo "\n=== Zusammenfuehren ===\n";
$alt = ['vorname' => 'Anna', 'nachname' => 'Berg', 'email' => '', 'telefon' => '0221 1', 'ort' => 'Köln', 'notiz' => ''];
$neu = ['vorname' => 'Anna', 'nachname' => 'Berg', 'email' => 'anna@x.de', 'telefon' => '0221 2', 'ort' => '', 'notiz' => 'importiert'];

is_it('Ergaenzen fuellt nur Leeres', import_merge($alt, $neu, 'ergaenzen'),
    ['email' => 'anna@x.de', 'notiz' => 'importiert']);
is_it('Ueberschreiben setzt Abweichendes', import_merge($alt, $neu, 'ueberschreiben'),
    ['email' => 'anna@x.de', 'telefon' => '0221 2', 'notiz' => 'importiert']);
is_it('leerer Importwert loescht nie', array_key_exists('ort', import_merge($alt, $neu, 'ueberschreiben')), false);
is_it('Gleiches ergibt keine Aenderung', import_merge($alt, $alt, 'ueberschreiben'), []);

is_it('Zusatzfelder ergaenzen',
    import_merge_extra(['newsletter' => '1', 'beitrag' => ''], ['newsletter' => '0', 'beitrag' => '25'], 'ergaenzen'),
    ['newsletter' => '1', 'beitrag' => '25']);
is_it('Zusatzfelder ueberschreiben',
    import_merge_extra(['newsletter' => '1'], ['newsletter' => '0', 'neu' => 'x'], 'ueberschreiben'),
    ['newsletter' => '0', 'neu' => 'x']);
is_it('leerer Zusatzwert loescht nie',
    import_merge_extra(['newsletter' => '1'], ['newsletter' => ''], 'ueberschreiben'), ['newsletter' => '1']);

echo "\n$ok bestanden, $bad fehlgeschlagen\n";
exit($bad > 0 ? 1 : 0);
