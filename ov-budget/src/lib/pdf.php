<?php
declare(strict_types=1);

/**
 * Ein kleiner PDF-Schreiber ohne fremde Bibliothek – für Protokolle und
 * andere Textdokumente: Überschriften, Absätze, Aufzählungen, Schlüssel-Wert-
 * Zeilen, Linien, Seitenumbruch mit Seitenzahlen. Mehr braucht ein Protokoll
 * nicht, und mehr würde eine echte Bibliothek erfordern.
 *
 * Schriften sind die 14 Standardschriften (Helvetica), die jeder Betrachter
 * mitbringt; Text wird nach Windows-1252 gewandelt, damit Umlaute und das
 * Eurozeichen ohne eingebettete Schrift funktionieren. Die Zeichenbreiten
 * sind Näherungen je Zeichenklasse – zum Umbrechen genau genug.
 *
 *   $pdf = new OvbPdf('Protokoll Dienstbesprechung');
 *   $pdf->titel('Dienstbesprechung');
 *   $pdf->absatz('…');
 *   $bytes = $pdf->ausgabe();
 */
class OvbPdf
{
    public const BREITE = 595.28;    // A4 in Punkt
    public const HOEHE = 841.89;
    public const RAND = 56.0;        // knapp 20 mm
    public const RAND_UNTEN = 64.0;

    private array $seiten = [];      // fertige Inhaltsströme
    private string $strom = '';      // laufende Seite
    private float $y;
    private float $schrift = 10.5;
    private string $titel;
    private string $autor;
    private int $jetzt;

    public function __construct(string $titel = '', string $autor = 'OV-Multitool', ?int $jetzt = null)
    {
        $this->titel = $titel;
        $this->autor = $autor;
        $this->jetzt = $jetzt ?? time();
        $this->y = self::HOEHE - self::RAND;
    }

    /* ---------------------------------------------------------------- Maße */

    /** Breite einer Zeichenkette in Punkt – Näherung je Zeichenklasse. Reine Funktion. */
    public static function textbreite(string $text, float $groesse, bool $fett = false): float
    {
        $summe = 0.0;
        $laenge = mb_strlen($text);
        for ($i = 0; $i < $laenge; $i++) {
            $c = mb_substr($text, $i, 1);
            $summe += self::zeichenbreite($c);
        }
        return $summe * $groesse * ($fett ? 1.06 : 1.0);
    }

    /** Breite eines Zeichens in em (Helvetica, gerundet). Reine Funktion. */
    private static function zeichenbreite(string $c): float
    {
        if ($c === ' ') {
            return 0.278;
        }
        if (str_contains("ijl.,:;'|!I'`", $c) || $c === '"') {
            return 0.3;
        }
        if (str_contains('ftr()[]{}-/\\', $c)) {
            return 0.36;
        }
        if (str_contains('mwMW%@', $c) || $c === '–' || $c === '—') {
            return 0.88;
        }
        if (preg_match('/[A-ZÄÖÜ]/u', $c)) {
            return 0.69;
        }
        if (preg_match('/[0-9]/', $c)) {
            return 0.556;
        }
        return 0.54;
    }

    /** Text in Zeilen umbrechen, die in die Breite passen. Reine Funktion. */
    public static function umbrechen(string $text, float $breite, float $groesse, bool $fett = false): array
    {
        $zeilen = [];
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $absatz) {
            $absatz = rtrim($absatz);
            if ($absatz === '') {
                $zeilen[] = '';
                continue;
            }
            $zeile = '';
            foreach (preg_split('/\s+/', $absatz) ?: [] as $wort) {
                // Überlange Wörter (Adressen) hart trennen
                while (self::textbreite($wort, $groesse, $fett) > $breite && mb_strlen($wort) > 1) {
                    $n = max(1, (int)floor(mb_strlen($wort) * $breite / self::textbreite($wort, $groesse, $fett)) - 1);
                    $teil = mb_substr($wort, 0, $n);
                    if ($zeile !== '') {
                        $zeilen[] = $zeile;
                        $zeile = '';
                    }
                    $zeilen[] = $teil;
                    $wort = mb_substr($wort, $n);
                }
                $versuch = $zeile === '' ? $wort : $zeile . ' ' . $wort;
                if ($zeile !== '' && self::textbreite($versuch, $groesse, $fett) > $breite) {
                    $zeilen[] = $zeile;
                    $zeile = $wort;
                } else {
                    $zeile = $versuch;
                }
            }
            $zeilen[] = $zeile;
        }
        return $zeilen;
    }

    /* ---------------------------------------------------------------- Inhalt */

    public function titel(string $text): void
    {
        $this->zeilen($text, 17.0, true, 0.0, 22.0);
        $this->y -= 4.0;
    }

    /** Überschrift: Stufe 1 groß, Stufe 2 fett in Textgröße */
    public function ueberschrift(string $text, int $stufe = 1): void
    {
        $groesse = $stufe === 1 ? 13.0 : 11.0;
        $this->platz($groesse * 1.4 + 10.0);
        $this->y -= $stufe === 1 ? 8.0 : 4.0;
        $this->zeilen($text, $groesse, true, 0.0, $groesse * 1.35);
        $this->y -= 2.0;
    }

    /** Absatz, wahlweise fett, eingerückt oder grau */
    public function absatz(string $text, bool $fett = false, float $einzug = 0.0, bool $grau = false): void
    {
        if (trim($text) === '') {
            return;
        }
        $this->zeilen($text, $this->schrift, $fett, $einzug, $this->schrift * 1.38, $grau);
        $this->y -= 3.0;
    }

    /** "Schlüssel: Wert" mit fester Spalte für den Schlüssel */
    public function schluessel(string $schluessel, string $wert, float $spalte = 95.0): void
    {
        if (trim($wert) === '') {
            return;
        }
        $groesse = $this->schrift;
        $hoehe = $groesse * 1.38;
        $breite = self::BREITE - 2 * self::RAND - $spalte;
        $zeilen = self::umbrechen($wert, $breite, $groesse);
        $this->platz($hoehe * min(3, count($zeilen)));
        $this->text(self::RAND, $this->y - $groesse, $schluessel, $groesse, true);
        foreach ($zeilen as $i => $z) {
            if ($i > 0) {
                $this->platz($hoehe);
            }
            $this->text(self::RAND + $spalte, $this->y - $groesse, $z, $groesse, false);
            $this->y -= $hoehe;
        }
        $this->y -= 1.0;
    }

    /** Aufzählungspunkt */
    public function punkt(string $text, float $einzug = 12.0): void
    {
        $groesse = $this->schrift;
        $hoehe = $groesse * 1.38;
        $breite = self::BREITE - 2 * self::RAND - $einzug - 10.0;
        $zeilen = self::umbrechen($text, $breite, $groesse);
        $this->platz($hoehe);
        $this->text(self::RAND + $einzug, $this->y - $groesse, '–', $groesse, false);
        foreach ($zeilen as $i => $z) {
            if ($i > 0) {
                $this->platz($hoehe);
            }
            $this->text(self::RAND + $einzug + 10.0, $this->y - $groesse, $z, $groesse, false);
            $this->y -= $hoehe;
        }
    }

    public function linie(): void
    {
        $this->platz(8.0);
        $this->y -= 3.0;
        $this->strom .= sprintf("0.6 G 0.5 w %.2F %.2F m %.2F %.2F l S 0 G\n",
            self::RAND, $this->y, self::BREITE - self::RAND, $this->y);
        $this->y -= 5.0;
    }

    public function abstand(float $punkte = 6.0): void
    {
        $this->y -= $punkte;
    }

    public function neue_seite(): void
    {
        $this->seiten[] = $this->strom;
        $this->strom = '';
        $this->y = self::HOEHE - self::RAND;
    }

    /* ---------------------------------------------------------------- Intern */

    /** Mehrere Zeilen Text setzen, mit Umbruch und Seitenwechsel */
    private function zeilen(string $text, float $groesse, bool $fett, float $einzug, float $hoehe, bool $grau = false): void
    {
        $breite = self::BREITE - 2 * self::RAND - $einzug;
        foreach (self::umbrechen($text, $breite, $groesse, $fett) as $z) {
            $this->platz($hoehe);
            if ($z !== '') {
                $this->text(self::RAND + $einzug, $this->y - $groesse, $z, $groesse, $fett, $grau);
            }
            $this->y -= $hoehe;
        }
    }

    /** Reicht der Platz noch? Sonst neue Seite. */
    private function platz(float $hoehe): void
    {
        if ($this->y - $hoehe < self::RAND_UNTEN) {
            $this->neue_seite();
        }
    }

    private function text(float $x, float $y, string $text, float $groesse, bool $fett, bool $grau = false): void
    {
        $this->strom .= sprintf("BT %s/F%d %.1F Tf %.2F %.2F Td (%s) Tj ET%s\n",
            $grau ? '0.35 g ' : '', $fett ? 2 : 1, $groesse, $x, $y, self::kodieren($text), $grau ? ' 0 g' : '');
    }

    /**
     * Text für den PDF-Strom: Windows-1252, Klammern und Backslash maskiert.
     * Über mbstring, nicht iconv – iconv ist im Add-on nicht installiert.
     * Was Windows-1252 nicht kennt (etwa Pfeile), wird ein Fragezeichen. Reine Funktion.
     */
    public static function kodieren(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        if (function_exists('mb_convert_encoding')) {
            $alt = mb_substitute_character();
            mb_substitute_character(0x3F);
            $cp = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
            mb_substitute_character($alt);
        } elseif (function_exists('iconv')) {
            $cp = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        } else {
            $cp = false;
        }
        if (!is_string($cp)) {
            $cp = preg_replace('/[^\x20-\x7e]/', '?', $text) ?? '';
        }
        return strtr($cp, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }

    /* ---------------------------------------------------------------- Ausgabe */

    /** Die fertige Datei als Bytes */
    public function ausgabe(): string
    {
        $seiten = $this->seiten;
        $seiten[] = $this->strom;
        $anzahl = count($seiten);

        // Objekte: 1 Katalog, 2 Seitenbaum, 3 Helvetica, 4 Helvetica-Bold, 5 Info, dann je Seite Seite + Inhalt
        $objekte = [];
        $objekte[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $seitenRefs = [];
        for ($i = 0; $i < $anzahl; $i++) {
            $seitenRefs[] = (6 + $i * 2) . ' 0 R';
        }
        $objekte[2] = sprintf("<< /Type /Pages /Kids [%s] /Count %d >>", implode(' ', $seitenRefs), $anzahl);
        $objekte[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objekte[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
        $objekte[5] = sprintf("<< /Title (%s) /Author (%s) /Producer (OV-Multitool) /CreationDate (D:%s) >>",
            self::kodieren($this->titel), self::kodieren($this->autor), date('YmdHis', $this->jetzt));

        foreach ($seiten as $i => $inhalt) {
            $fuss = sprintf("BT 0.45 g /F1 8 Tf %.2F %.2F Td (%s) Tj ET BT 0.45 g /F1 8 Tf %.2F %.2F Td (%s) Tj ET 0 g\n",
                self::RAND, 34.0, self::kodieren($this->titel),
                self::BREITE - self::RAND - 60.0, 34.0, self::kodieren(sprintf('Seite %d von %d', $i + 1, $anzahl)));
            $strom = $inhalt . $fuss;
            $nr = 6 + $i * 2;
            $objekte[$nr] = sprintf("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>",
                self::BREITE, self::HOEHE, $nr + 1);
            $objekte[$nr + 1] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($strom), $strom);
        }

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        $max = max(array_keys($objekte));
        for ($n = 1; $n <= $max; $n++) {
            $offsets[$n] = strlen($out);
            $out .= $n . " 0 obj\n" . $objekte[$n] . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($n = 1; $n <= $max; $n++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$n]);
        }
        $out .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $out;
    }

    /** Wie viele Seiten es bis jetzt sind */
    public function seitenzahl(): int
    {
        return count($this->seiten) + 1;
    }
}
