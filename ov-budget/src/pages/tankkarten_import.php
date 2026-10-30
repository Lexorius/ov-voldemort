<?php
declare(strict_types=1);

/**
 * Tankkarten-Import in drei Schritten:
 *   1. CSV hochladen
 *   2. Spalten zuordnen und Optionen wählen, Vorschau ansehen
 *   3. importieren
 */

$user = require_login();
if (!can('manage_tankkarten')) {
    flash('error', 'Tankkarten darf nur die Leitung pflegen.');
    redirect_route('tankkarten');
}

contact_import_cleanup();

/* ---- Vorlage zum Ausfüllen ---- */
if (get_str('vorlage') === '1') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="tankkarten_vorlage.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Anbieter', 'Kartennummer', 'Schaltet frei', 'Status', 'Kundennummer', 'Gültig bis', 'Ausgegeben am',
                   'Fahrzeuge', 'Liegt bei', 'Aufbewahrung', 'PIN', 'Verbleib', 'Verbleib seit', 'Grund der Sperrung', 'Notiz'], ';');
    fputcsv($out, ['DKV', '7083001234567890', 'Kraftstoff', 'Im Einsatz', 'K-4711', '30.06.2027', '10.01.2026',
                   'THW 99020, MTW', 'Anna Admin', 'Handschuhfach GKW 1', '', '', '', '', ''], ';');
    fputcsv($out, ['EnBW mobility+', 'DE*ENB*C12345678', 'Laden', 'Im Einsatz', '', '', '',
                   'MTW E', '', 'Ladekabeltasche', '', '', '', '', 'Ladekarte für den E-MTW'], ';');
    fclose($out);
    exit;
}

$sitzung = $_SESSION['tankkarten_import'] ?? [];
$token = (string)($sitzung['token'] ?? '');
$datei = $token !== '' ? contact_import_load($token) : null;
if ($token !== '' && !$datei) {
    unset($_SESSION['tankkarten_import']);
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
            if (!in_array($endung, ['csv', 'txt'], true)) {
                $errors[] = 'Bitte eine CSV-Datei hochladen (.csv, .txt).';
                break;
            }
            $utf8 = import_to_utf8((string)file_get_contents((string)$f['tmp_name']));
            $probe = tankkarte_import_parse($utf8);
            if (count($probe['roh']) === 0) {
                $errors[] = 'Die Datei enthält keine Datenzeilen unter der Kopfzeile.';
                break;
            }
            if (count($probe['roh']) > TANKKARTE_IMPORT_MAX) {
                $errors[] = sprintf('Die Datei enthält %d Einträge – höchstens %d je Import. Bitte aufteilen.', count($probe['roh']), TANKKARTE_IMPORT_MAX);
                break;
            }
            if ($token !== '') {
                contact_import_discard($token);
            }
            $_SESSION['tankkarten_import'] = [
                'token'    => contact_import_store(mb_substr(basename((string)$f['name']), 0, 150), $utf8),
                'mapping'  => $probe['mapping'],
                'optionen' => ['strategie' => 'ueberspringen', 'anbieter_id' => list_default_id('tankkarte_anbieter'), 'anbieter_anlegen' => 0],
                'vorschau' => false,
            ];
            flash('success', sprintf('%d Zeilen gelesen. Jetzt die Zuordnung prüfen.', count($probe['roh'])));
            redirect_route('tankkarten_import');
            // no break

        case 'cancel':
            if ($token !== '') {
                contact_import_discard($token);
            }
            unset($_SESSION['tankkarten_import']);
            flash('info', 'Import abgebrochen.');
            redirect_route('tankkarten_import');
            // no break

        case 'preview':
            if (!$datei) {
                break;
            }
            $erlaubt = array_keys(tankkarte_import_ziele());
            $mapping = $sitzung['mapping'];
            foreach (array_keys($mapping) as $i) {
                $ziel = (string)(($_POST['map'] ?? [])[$i] ?? '');
                $mapping[$i] = in_array($ziel, $erlaubt, true) ? $ziel : '';
            }
            $strategie = post_str('strategie', 'ueberspringen');
            $_SESSION['tankkarten_import']['mapping'] = $mapping;
            $_SESSION['tankkarten_import']['optionen'] = [
                'strategie'        => array_key_exists($strategie, TANKKARTE_IMPORT_STRATEGIEN) ? $strategie : 'ueberspringen',
                'anbieter_id'      => post_int('anbieter_id'),
                'anbieter_anlegen' => post_bool('anbieter_anlegen'),
            ];
            $_SESSION['tankkarten_import']['vorschau'] = true;
            redirect(url('tankkarten_import') . '#vorschau');
            // no break

        case 'import':
            if (!$datei || empty($sitzung['vorschau'])) {
                flash('warn', 'Bitte zuerst die Vorschau aufrufen.');
                redirect_route('tankkarten_import');
            }
            $teile = tankkarte_import_parse((string)$datei['content'], $sitzung['mapping']);
            $plan = tankkarte_import_plan($teile['zeilen'], tankkarte_import_kontext($sitzung['optionen']), tankkarte_import_index(), (string)$sitzung['optionen']['strategie']);
            try {
                $ergebnis = tankkarte_import_execute($plan, $sitzung['optionen'], $user);
            } catch (Throwable $ex) {
                error_log('Tankkarten-Import fehlgeschlagen: ' . $ex->getMessage());
                flash('error', 'Der Import ist fehlgeschlagen. ' . e(app_config('debug', false) ? $ex->getMessage() : 'Details stehen im Protokoll.'));
                redirect_route('tankkarten_import');
            }
            contact_import_discard($token);
            unset($_SESSION['tankkarten_import']);
            render('tankkarten_import', ['title' => 'Import abgeschlossen', 'ergebnis' => $ergebnis, 'dateiname' => (string)$datei['name'],
                'errors' => [], 'datei' => null, 'sitzung' => [], 'teile' => null, 'plan' => [], 'zaehler' => []]);
            return;
    }
}

/* ---- Anzeige ---- */
$daten = ['title' => 'Tankkarten importieren', 'errors' => $errors, 'datei' => $datei, 'sitzung' => $sitzung,
          'teile' => null, 'plan' => [], 'zaehler' => [], 'ergebnis' => null, 'dateiname' => ''];
if ($datei) {
    $teile = tankkarte_import_parse((string)$datei['content'], $sitzung['mapping']);
    $daten['teile'] = $teile;
    if (!empty($sitzung['vorschau'])) {
        $plan = tankkarte_import_plan($teile['zeilen'], tankkarte_import_kontext($sitzung['optionen']), tankkarte_import_index(), (string)$sitzung['optionen']['strategie']);
        $zaehler = ['neu' => 0, 'ueberschreiben' => 0, 'ueberspringen' => 0, 'fehler' => 0];
        foreach ($plan as $e) {
            $zaehler[$e['aktion']]++;
        }
        $daten['plan'] = $plan;
        $daten['zaehler'] = $zaehler;
    }
}
render('tankkarten_import', $daten);
