<?php
declare(strict_types=1);
/* PDF-Schreiber: Aufbau der Datei, Kodierung, Umbruch, Seitenwechsel, Querverweise */
require dirname(__DIR__) . '/src/lib/pdf.php';

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Kodierung und Umbruch ---------- */
$check('Umlaute nach Windows-1252, Klammern maskiert', OvbPdf::kodieren('Grüße (Test) \\ Ärger €') === "Gr\xfc\xdfe \\(Test\\) \\\\ \xc4rger \x80");
$check('Zeilenumbruch im Text wird Leerzeichen', OvbPdf::kodieren("a\nb") === 'a b');
$check('Gedankenstrich, Anführungszeichen und Auslassung sind in Windows-1252', OvbPdf::kodieren("\u{2013} \u{201E}x\u{201C} \u{2026}") === "\x96 \x84x\x93 \x85");
// Im Add-on (mbstring) wird Unbekanntes ein Fragezeichen, im lokalen Shim über iconv fällt es weg – beides kein Fehler
$check('Unbekanntes wird Fragezeichen oder fällt weg, aber kein Fehler', in_array(OvbPdf::kodieren("Pfeil \u{2192} da"), ['Pfeil ? da', 'Pfeil  da'], true));
$check('Breite wächst mit dem Text', OvbPdf::textbreite('iii', 10) < OvbPdf::textbreite('WWW', 10) && OvbPdf::textbreite('Hallo Welt', 10) > 40 && OvbPdf::textbreite('Hallo Welt', 10) < 60);
$zeilen = OvbPdf::umbrechen('Dies ist ein ziemlich langer Satz, der sicher nicht in eine schmale Spalte passt und umgebrochen werden muss.', 120, 10.5);
$check('Umbruch in mehrere Zeilen, jede passt', count($zeilen) >= 4 && max(array_map(static fn($z) => OvbPdf::textbreite($z, 10.5), $zeilen)) <= 120
    && implode(' ', $zeilen) === 'Dies ist ein ziemlich langer Satz, der sicher nicht in eine schmale Spalte passt und umgebrochen werden muss.');
$check('Absätze bleiben, Leerzeile bleibt', OvbPdf::umbrechen("Eins\n\nZwei", 500, 10) === ['Eins', '', 'Zwei']);
$check('überlanges Wort wird hart getrennt', count(OvbPdf::umbrechen(str_repeat('x', 200), 100, 10)) > 1);

/* ---------- Eine Seite ---------- */
$pdf = new OvbPdf('Protokoll Test', 'THW OV', 1792900000);
$pdf->titel('Dienstbesprechung');
$pdf->absatz('Protokoll', true);
$pdf->schluessel('Datum', 'Di., 27.10.2026, 19:00 Uhr');
$pdf->schluessel('Anwesend', 'Anna Ärztin, Ben Bauer');
$pdf->linie();
$pdf->ueberschrift('TOP 1  Fahrzeuge', 2);
$pdf->absatz('Alles in Ordnung (fast).');
$pdf->punkt('Anmerkung eins');
$pdf->abstand(5);
$bytes = $pdf->ausgabe();
$check('Kopf und Ende', str_starts_with($bytes, "%PDF-1.4\n") && str_ends_with($bytes, "%%EOF\n"));
$check('eine Seite, zwei Schriften, Titel in den Metadaten', str_contains($bytes, '/Count 1') && str_contains($bytes, '/BaseFont /Helvetica ') && str_contains($bytes, '/BaseFont /Helvetica-Bold')
    && str_contains($bytes, '/Title (Protokoll Test)') && str_contains($bytes, '/Author (THW OV)') && str_contains($bytes, '/CreationDate (D:' . date('YmdHis', 1792900000) . ')'));
$check('Texte im Strom, Umlaut als Windows-1252, Klammern maskiert', str_contains($bytes, '(Dienstbesprechung) Tj') && str_contains($bytes, "(Anna \xc4rztin, Ben Bauer) Tj")
    && str_contains($bytes, '(Alles in Ordnung \\(fast\\).) Tj') && str_contains($bytes, '/F2 17.0 Tf') && str_contains($bytes, '(Seite 1 von 1) Tj'));
$check('Linie gezeichnet', (bool)preg_match('/0\.5 w [0-9.]+ [0-9.]+ m [0-9.]+ [0-9.]+ l S/', $bytes));

// Querverweise: startxref zeigt auf xref, jeder Eintrag auf "n 0 obj"
preg_match('/startxref\n(\d+)\n/', $bytes, $m);
$check('startxref zeigt auf die Tabelle', isset($m[1]) && substr($bytes, (int)$m[1], 4) === 'xref');
preg_match_all('/^(\d{10}) 00000 n /m', $bytes, $eintraege);
$alle = true;
foreach ($eintraege[1] as $i => $off) {
    if (substr($bytes, (int)$off, strlen(($i + 1) . ' 0 obj')) !== ($i + 1) . ' 0 obj') { $alle = false; }
}
$check('jeder Querverweis trifft sein Objekt', $alle && count($eintraege[1]) === 7);
$check('Stromlänge stimmt', (bool)preg_match('/\/Length (\d+) >>\nstream\n/', $bytes, $l) && str_contains($bytes, "stream\n" . substr($bytes, strpos($bytes, "stream\n") + 7, (int)$l[1]) . "\nendstream"));

/* ---------- Seitenwechsel ---------- */
$pdf = new OvbPdf('Lang');
for ($i = 1; $i <= 60; $i++) {
    $pdf->absatz('Zeile ' . $i . ' mit etwas Text, damit die Seite voll wird.');
}
$bytes = $pdf->ausgabe();
$check('zwei Seiten mit Seitenzahlen', $pdf->seitenzahl() === 2 && str_contains($bytes, '/Count 2') && str_contains($bytes, '(Seite 1 von 2) Tj') && str_contains($bytes, '(Seite 2 von 2) Tj'));
$pdf = new OvbPdf('Leer');
$check('leeres Dokument ist trotzdem gültig', str_contains($pdf->ausgabe(), '/Count 1'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
