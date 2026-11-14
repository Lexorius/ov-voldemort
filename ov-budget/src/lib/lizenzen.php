<?php
declare(strict_types=1);

/**
 * Was OV-Multitool von anderen mitbringt oder nutzt – für die Seite
 * „Bibliotheken und Lizenzen". Jede mitgelieferte Bibliothek liegt als eigene,
 * unveränderte Datei unter public/assets/, ihr Lizenztext daneben; hier steht,
 * von wem sie stammt, unter welcher Lizenz sie steht und woher sie kommt.
 *
 * Kommt eine Bibliothek dazu oder ändert sich ihr Stand: hier eintragen und
 * den Lizenztext als LICENSE in ihren Ordner legen. Die Prüfung t_lizenzen
 * hält beides zusammen.
 */

/**
 * Mitgelieferte Bibliotheken. 'datei' und 'lizenztext' sind Pfade unter public/assets/.
 */
const LIZENZEN_BIBLIOTHEKEN = [
    'einsatzauftrag' => [
        'name'      => 'einsatzauftrag',
        'wofuer'    => 'Setzt den Einsatzauftrag nach dem Vordruck „Fb Fü 3“ als PDF – im Modul Einsätze.',
        'urheber'   => 'Copyright (c) 2025-2026 Johannes Rudolph',
        'lizenz'    => 'EUPL-1.2',
        'lizenzname' => 'European Union Public Licence, Version 1.2 oder später',
        'quelle'    => 'https://github.com/wattnpapa/einsatzauftrag',
        'stand'     => 'Commit f642655c20d75416f52412c1f3994618303ec0bf vom 10.11.2026',
        'datei'     => 'vendor/einsatzauftrag/einsatzauftrag.js',
        'lizenztext' => 'vendor/einsatzauftrag/LICENSE',
        'aenderung' => 'Unverändert. Die TypeScript-Quellen (src/) sind nur in eine einzelne Browser-Datei übersetzt (esbuild); '
            . 'der Quelltext steht unter der genannten Adresse. OV-Multitool ruft die Bibliothek ausschließlich über ihre öffentliche Schnittstelle auf.',
    ],
    'jspdf' => [
        'name'      => 'jsPDF',
        'wofuer'    => 'Erzeugt PDF-Dateien im Browser – für den Einsatzauftrag und die Lagekarte.',
        'urheber'   => 'Copyright (c) 2010-2025 James Hall, https://github.com/MrRio/jsPDF · (c) 2015-2025 yWorks GmbH, https://www.yworks.com/ '
            . '– mit Teilen weiterer Autorinnen und Autoren, die im Kopf der Datei genannt sind',
        'lizenz'    => 'MIT',
        'lizenzname' => 'MIT-Lizenz',
        'quelle'    => 'https://github.com/parallax/jsPDF',
        'stand'     => 'Version 4.2.1 (npm-Paket jspdf, Datei dist/jspdf.umd.min.js)',
        'datei'     => 'vendor/jspdf/jspdf.umd.min.js',
        'lizenztext' => 'vendor/jspdf/LICENSE',
        'aenderung' => 'Unverändert.',
    ],
    'qrcode' => [
        'name'      => 'QR Code Generator for JavaScript (qrcode-generator)',
        'wofuer'    => 'Zeichnet die QR-Codes – Standortmeldung, Einladungen, Abstimmungen, Zähler, Getränke.',
        'urheber'   => 'Copyright (c) 2009 Kazuhiko Arase',
        'lizenz'    => 'MIT',
        'lizenzname' => 'MIT-Lizenz',
        'quelle'    => 'https://github.com/kazuhikoarase/qrcode-generator',
        'stand'     => 'Version 1.4.4 (npm-Paket qrcode-generator)',
        'datei'     => 'js/qrcode.js',
        'lizenztext' => 'vendor/qrcode-generator/LICENSE',
        'aenderung' => 'Unverändert. „QR Code“ ist ein eingetragenes Warenzeichen von DENSO WAVE INCORPORATED.',
    ],
];

/**
 * Dienste und Daten, die nicht mitgeliefert, aber zur Laufzeit genutzt werden – jeweils nur,
 * wenn die Funktion eingeschaltet ist.
 */
const LIZENZEN_DIENSTE = [
    [
        'name'   => 'OpenStreetMap',
        'wofuer' => 'Kartenkacheln im Modul Karten (der Browser lädt sie direkt vom Kachelserver) und die Adresssuche für Entfernungen.',
        'hinweis' => 'Kartendaten © OpenStreetMap-Mitwirkende, Open Database License (ODbL) 1.0. Die Quellenangabe steht auf jeder Karte.',
        'quelle' => 'https://www.openstreetmap.org/copyright',
    ],
    [
        'name'   => 'OSRM (Open Source Routing Machine)',
        'wofuer' => 'Fahrstrecken für die Entfernungen (Testfunktion, standardmäßig aus).',
        'hinweis' => 'Demoserver des OSRM-Projekts; die Strecken beruhen auf OpenStreetMap-Daten (ODbL).',
        'quelle' => 'https://project-osrm.org/',
    ],
    [
        'name'   => 'Ollama',
        'wofuer' => 'KI-Auswertung von Dateien – ein Ollama im eigenen Netz, standardmäßig aus.',
        'hinweis' => 'Wird nicht mitgeliefert. Modelle und ihre Lizenzen wählt, wer den Dienst betreibt.',
        'quelle' => 'https://ollama.com/',
    ],
];

/** Pfad einer Datei unter public/assets – oder null, wenn sie fehlt oder der Pfad hinausführt. Reine Funktion. */
function lizenzen_pfad(string $relativ): ?string
{
    if ($relativ === '' || str_contains($relativ, '..') || str_contains($relativ, "\0")) {
        return null;
    }
    $pfad = dirname(__DIR__, 2) . '/public/assets/' . ltrim($relativ, '/');
    return is_file($pfad) ? $pfad : null;
}

/** Die Bibliotheken samt Lizenztext, Dateigröße und Adresse der mitgelieferten Datei */
function lizenzen_bibliotheken(): array
{
    $out = [];
    foreach (LIZENZEN_BIBLIOTHEKEN as $key => $b) {
        $datei = lizenzen_pfad($b['datei']);
        $text = lizenzen_pfad($b['lizenztext']);
        $out[$key] = $b + [
            'vorhanden' => $datei !== null,
            'groesse'   => $datei !== null ? (int)filesize($datei) : 0,
            'text'      => $text !== null ? (string)file_get_contents($text) : '',
            'datei_url' => base_path() . '/assets/' . $b['datei'],
            'lizenz_url' => base_path() . '/assets/' . $b['lizenztext'],
        ];
    }
    return $out;
}

/**
 * Woher die Symbole der Kartenbibliothek stammen: je Quelle und Lizenz die Anzahl.
 * Symbole ohne Quelle sind selbst hochgeladen und stehen nicht in der Liste.
 */
function lizenzen_symbolquellen(): array
{
    try {
        return db_all("SELECT quelle, lizenz, COUNT(*) AS anzahl FROM karten_symbole WHERE quelle <> '' GROUP BY quelle, lizenz ORDER BY quelle, lizenz");
    } catch (Throwable) {
        return [];
    }
}
