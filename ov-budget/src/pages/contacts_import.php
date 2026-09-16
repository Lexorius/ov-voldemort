<?php
declare(strict_types=1);

/**
 * Kontakt-Import in drei Schritten:
 *   1. Datei hochladen (CSV oder vCard)
 *   2. Spalten zuordnen und Optionen wählen, Vorschau ansehen
 *   3. importieren
 */

$user = require_role('admin', 'leitung');

contact_import_cleanup();

/* ---- Vorlage zum Ausfüllen ---- */
if (get_str('vorlage') === '1') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="kontakte_vorlage.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    $kopf = ['Anrede', 'Titel', 'Vorname', 'Nachname', 'Organisation', 'Funktion', 'Kategorie',
             'E-Mail', 'Telefon', 'Mobil', 'Straße', 'PLZ', 'Ort', 'Land', 'Briefanrede', 'Notiz'];
    foreach (contact_extra_fields() as $def) {
        $kopf[] = $def['label'];
    }
    fputcsv($out, $kopf, ';');
    fputcsv($out, ['Frau', 'Dr.', 'Anna', 'Berg', 'Stadt Musterstadt', 'Bürgermeisterin', 'Kommune / Verwaltung',
                   'anna.berg@example.org', '0221 1234', '', 'Rathausplatz 1', '12345', 'Musterstadt', '', '', ''], ';');
    fclose($out);
    exit;
}

$sitzung = $_SESSION['kontakt_import'] ?? [];
$token = (string)($sitzung['token'] ?? '');
$datei = $token !== '' ? contact_import_load($token) : null;
if ($token !== '' && !$datei) {
    unset($_SESSION['kontakt_import']);
    $sitzung = [];
    $token = '';
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post_str('action')) {

        case 'upload':
            $f = $_FILES['datei'] ?? null;
            if (!$f || (int)$f['error'] === UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Bitte eine Datei auswählen.';
                break;
            }
            if ((int)$f['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Die Datei konnte nicht hochgeladen werden (Fehlercode ' . (int)$f['error'] . ').';
                break;
            }
            if ((int)$f['size'] > upload_max_bytes()) {
                $errors[] = 'Die Datei ist zu groß (höchstens ' . bytes_human(upload_max_bytes()) . ').';
                break;
            }
            $endung = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
            if (!in_array($endung, ['csv', 'txt', 'vcf', 'vcard'], true)) {
                $errors[] = 'Bitte eine CSV- oder vCard-Datei hochladen (.csv, .txt, .vcf).';
                break;
            }

            $utf8 = import_to_utf8((string)file_get_contents((string)$f['tmp_name']));
            $probe = contact_import_parse($utf8, []);
            $anzahl = $probe['typ'] === 'vcard' ? count($probe['zeilen']) : count($probe['roh']);

            if ($anzahl === 0) {
                $errors[] = $probe['typ'] === 'vcard'
                    ? 'In der Datei wurde keine vollständige vCard gefunden.'
                    : 'Die Datei enthält keine Datenzeilen unter der Kopfzeile.';
                break;
            }
            if ($anzahl > KONTAKT_IMPORT_MAX) {
                $errors[] = sprintf('Die Datei enthält %d Einträge – höchstens %d je Import. Bitte aufteilen.',
                    $anzahl, KONTAKT_IMPORT_MAX);
                break;
            }

            if ($token !== '') {
                contact_import_discard($token);
            }
            $_SESSION['kontakt_import'] = [
                'token'   => contact_import_store(mb_substr(basename((string)$f['name']), 0, 150), $utf8),
                'mapping' => $probe['typ'] === 'csv'
                    ? import_guess_mapping($probe['header'], contact_extra_fields())
                    : null,
                'optionen' => [
                    'strategie'          => 'ueberspringen',
                    'kategorie_id'       => null,
                    'kategorien_anlegen' => 0,
                    'group_id'           => null,
                ],
                'vorschau' => false,
            ];
            flash('success', sprintf('%d Einträge gelesen. Jetzt die Zuordnung prüfen.', $anzahl));
            redirect_route('contacts_import');
            // no break

        case 'cancel':
            if ($token !== '') {
                contact_import_discard($token);
            }
            unset($_SESSION['kontakt_import']);
            flash('info', 'Import abgebrochen.');
            redirect_route('contacts_import');
            // no break

        case 'preview':
            if (!$datei) {
                break;
            }
            // Zuordnung aus dem Formular übernehmen und auf erlaubte Ziele beschränken
            $erlaubt = array_merge(
                array_keys(import_targets()),
                array_map(static fn($k) => 'extra:' . $k, array_keys(contact_extra_fields()))
            );
            $mapping = $sitzung['mapping'];
            if (is_array($mapping)) {
                foreach (array_keys($mapping) as $i) {
                    $ziel = (string)(($_POST['map'] ?? [])[$i] ?? '');
                    $mapping[$i] = in_array($ziel, $erlaubt, true) ? $ziel : '';
                }
            }
            $strategie = post_str('strategie', 'ueberspringen');
            $_SESSION['kontakt_import']['mapping'] = $mapping;
            $_SESSION['kontakt_import']['optionen'] = [
                'strategie'          => array_key_exists($strategie, IMPORT_STRATEGIEN) ? $strategie : 'ueberspringen',
                'kategorie_id'       => post_int('kategorie_id'),
                'kategorien_anlegen' => post_bool('kategorien_anlegen'),
                'group_id'           => post_int('group_id'),
            ];
            $_SESSION['kontakt_import']['vorschau'] = true;
            redirect(url('contacts_import') . '#vorschau');
            // no break

        case 'import':
            if (!$datei || empty($sitzung['vorschau'])) {
                flash('warn', 'Bitte zuerst die Vorschau aufrufen.');
                redirect_route('contacts_import');
            }
            $teile = contact_import_parse((string)$datei['content'], $sitzung['mapping']);
            $plan = import_decide($teile['zeilen'], contact_import_index(), (string)$sitzung['optionen']['strategie']);

            try {
                $ergebnis = contact_import_execute($plan, $sitzung['optionen'], $user);
            } catch (Throwable $ex) {
                error_log('Kontakt-Import fehlgeschlagen: ' . $ex->getMessage());
                flash('error', 'Der Import ist fehlgeschlagen, es wurde nichts gespeichert. '
                    . e(app_config('debug', false) ? $ex->getMessage() : 'Details stehen im Protokoll.'));
                redirect_route('contacts_import');
            }

            contact_import_discard($token);
            unset($_SESSION['kontakt_import']);

            render('contacts_import_result', [
                'title'    => 'Import abgeschlossen',
                'ergebnis' => $ergebnis,
                'dateiname' => (string)$datei['name'],
                'gruppe'   => !empty($sitzung['optionen']['group_id'])
                    ? contact_group_find((int)$sitzung['optionen']['group_id']) : null,
            ]);
            return;
    }
}

/* ---- Anzeige ---- */

$daten = [
    'title'     => 'Kontakte importieren',
    'errors'    => $errors,
    'datei'     => $datei,
    'sitzung'   => $sitzung,
    'teile'     => null,
    'plan'      => [],
    'zaehler'   => [],
    'verteiler' => contact_groups_all(true),
    'kategorien' => [],
];

if ($datei) {
    $teile = contact_import_parse((string)$datei['content'], $sitzung['mapping']);
    $daten['teile'] = $teile;

    if (!empty($sitzung['vorschau'])) {
        $plan = import_decide($teile['zeilen'], contact_import_index(), (string)$sitzung['optionen']['strategie']);
        $zaehler = ['neu' => 0, 'ergaenzen' => 0, 'ueberschreiben' => 0, 'ueberspringen' => 0, 'fehler' => 0];
        foreach ($plan as $e) {
            $zaehler[$e['aktion']]++;
        }
        $daten['plan'] = $plan;
        $daten['zaehler'] = $zaehler;

        // Kategorien für die Vorschau auflösen, ohne etwas anzulegen
        $items = list_items('kontakt_kategorie', false);
        foreach ($plan as $e) {
            $text = $e['kontakt']['kategorie_text'];
            if ($text !== '' && !array_key_exists($text, $daten['kategorien'])) {
                $id = import_match_list($text, $items);
                $daten['kategorien'][$text] = $id ? list_label($id) : null;
            }
        }
    }
}

render('contacts_import', $daten);
