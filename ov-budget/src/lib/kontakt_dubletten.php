<?php
declare(strict_types=1);

/**
 * Dubletten im Kontaktmodul: finden, als „keine Dublette" merken,
 * zusammenführen.
 *
 * Gefunden wird über gleiche E-Mail-Adresse, gleiche Rufnummer, gleichen
 * oder sehr ähnlichen Namen samt Organisation. Beim Zusammenführen bleibt
 * ein Kontakt stehen, die Felder lassen sich je Spalte aus den Beteiligten
 * wählen, alle Verweise (Verteiler, Termine, Gästelisten, Anwesenheiten …)
 * wandern auf den verbliebenen Kontakt, die übrigen werden gelöscht.
 *
 * Die Suche und die Auswahl der Felder sind reine Funktionen, damit sie
 * sich ohne Datenbank prüfen lassen.
 */

/* ---------------- Finden ---------------- */

/** Spalten, die die Suche braucht */
const DUBLETTE_SUCHFELDER = 'id, vorname, nachname, organisation, email, telefon, mobil, is_active, created_at';

/** Normalisierte Merkmale eines Kontakts für den Vergleich */
function dublette_merkmale(array $c): array
{
    $tel = [];
    foreach (['telefon', 'mobil'] as $f) {
        $e164 = phone_e164((string)($c[$f] ?? ''));
        // Zu kurze Nummern (Durchwahl, Notruf) sagen nichts über die Person
        if ($e164 !== null && strlen($e164) >= 8) {
            $tel[$e164] = true;
        }
    }
    $email = mb_strtolower(trim((string)($c['email'] ?? '')));
    return [
        'email'    => $email !== '' && str_contains($email, '@') ? $email : '',
        'telefone' => array_keys($tel),
        'vorname'  => import_norm((string)($c['vorname'] ?? '')),
        'nachname' => import_norm((string)($c['nachname'] ?? '')),
        'org'      => import_norm((string)($c['organisation'] ?? '')),
    ];
}

/** Gleicher Vorname, einer leer, oder einer nur die Initiale des anderen ("a" zu "anna") */
function dublette_vorname_passt(string $a, string $b): bool
{
    if ($a === $b || $a === '' || $b === '') {
        return true;
    }
    return (strlen($a) === 1 && str_starts_with($b, $a)) || (strlen($b) === 1 && str_starts_with($a, $b));
}

/** Gleiche Organisation oder eine fehlt */
function dublette_org_passt(string $a, string $b): bool
{
    return $a === $b || $a === '' || $b === '';
}

/** Schlüssel für ein Paar, unabhängig von der Reihenfolge */
function dublette_paar(int $a, int $b): string
{
    return min($a, $b) . ':' . max($a, $b);
}

/**
 * Paare möglicher Dubletten mit Grund und Stufe.
 *
 * Stufen: sicher (E-Mail, Rufnummer oder voller Name passen und nichts
 * widerspricht), wahrscheinlich (Name mit Lücke oder Tippfehler),
 * moeglich (gleiche Adresse oder Nummer, aber ein anderer Name – oft das
 * zentrale Postfach einer Behörde).
 *
 * Rückgabe: Paarschlüssel => ['a' => id, 'b' => id, 'stufe' => …, 'gruende' => […]]
 */
function dublette_paare(array $kontakte): array
{
    $m = [];
    foreach ($kontakte as $c) {
        $m[(int)$c['id']] = dublette_merkmale($c);
    }

    $paare = [];
    $melden = static function (int $a, int $b, string $stufe, string $grund) use (&$paare): void {
        if ($a === $b) {
            return;
        }
        $k = dublette_paar($a, $b);
        $paare[$k] ??= ['a' => min($a, $b), 'b' => max($a, $b), 'stufe' => 'moeglich', 'gruende' => []];
        if (!in_array($grund, $paare[$k]['gruende'], true)) {
            $paare[$k]['gruende'][] = $grund;
        }
        $rang = ['moeglich' => 0, 'wahrscheinlich' => 1, 'sicher' => 2];
        if ($rang[$stufe] > $rang[$paare[$k]['stufe']]) {
            $paare[$k]['stufe'] = $stufe;
        }
    };

    /* Derselbe Mensch? Ohne Namen lässt sich das nicht sagen – dann zählt das Merkmal allein. */
    $gleicherMensch = static function (array $x, array $y): ?bool {
        if ($x['nachname'] === '' || $y['nachname'] === '') {
            return null;
        }
        return $x['nachname'] === $y['nachname'] && dublette_vorname_passt($x['vorname'], $y['vorname']);
    };

    /* 1. Gleiche E-Mail-Adresse */
    $index = [];
    foreach ($m as $id => $x) {
        if ($x['email'] !== '') {
            $index[$x['email']][] = $id;
        }
    }
    foreach ($index as $ids) {
        foreach (dublette_kombinationen($ids) as [$a, $b]) {
            $gleich = $gleicherMensch($m[$a], $m[$b]);
            $melden($a, $b, $gleich === false ? 'moeglich' : 'sicher',
                $gleich === false ? 'gleiche E-Mail-Adresse, aber anderer Name' : 'gleiche E-Mail-Adresse');
        }
    }

    /* 2. Gleiche Rufnummer (Telefon oder Mobil, kreuzweise) */
    $index = [];
    foreach ($m as $id => $x) {
        foreach ($x['telefone'] as $t) {
            $index[$t][] = $id;
        }
    }
    foreach ($index as $ids) {
        foreach (dublette_kombinationen(array_values(array_unique($ids))) as [$a, $b]) {
            $gleich = $gleicherMensch($m[$a], $m[$b]);
            $melden($a, $b, $gleich === false ? 'moeglich' : 'sicher',
                $gleich === false ? 'gleiche Rufnummer, aber anderer Name' : 'gleiche Rufnummer');
        }
    }

    /* 3. Gleicher Nachname: voller Name, Initiale, fehlender Vorname, Tippfehler */
    $index = [];
    foreach ($m as $id => $x) {
        if ($x['nachname'] !== '') {
            $index[$x['nachname']][] = $id;
        }
    }
    foreach ($index as $ids) {
        foreach (dublette_kombinationen($ids) as [$a, $b]) {
            $x = $m[$a];
            $y = $m[$b];
            if (!dublette_vorname_passt($x['vorname'], $y['vorname'])) {
                continue;
            }
            $vollerName = $x['vorname'] !== '' && $x['vorname'] === $y['vorname'];
            if ($vollerName) {
                if (dublette_org_passt($x['org'], $y['org'])) {
                    $melden($a, $b, 'sicher', 'gleicher Name');
                } else {
                    $melden($a, $b, 'moeglich', 'gleicher Name, andere Organisation');
                }
            } elseif ($x['org'] !== '' && $x['org'] === $y['org']) {
                // Ohne Vornamen trägt die gemeinsame Organisation den Verdacht
                $melden($a, $b, 'wahrscheinlich', 'gleicher Nachname und gleiche Organisation');
            } elseif ($x['vorname'] === '' && $y['vorname'] === '' && dublette_org_passt($x['org'], $y['org'])) {
                $melden($a, $b, 'wahrscheinlich', 'gleicher Nachname ohne Vornamen');
            }
        }
    }

    /* 4. Tippfehler im Nachnamen: ein Buchstabe anders, gleicher Vorname oder gleiche Organisation */
    $bloecke = [];
    foreach ($m as $id => $x) {
        if ($x['nachname'] !== '' && strlen($x['nachname']) >= 4) {
            if ($x['vorname'] !== '') {
                $bloecke['v:' . $x['vorname']][] = $id;
            }
            if ($x['org'] !== '') {
                $bloecke['o:' . $x['org']][] = $id;
            }
        }
    }
    foreach ($bloecke as $ids) {
        if (count($ids) > 400) {
            continue;   // riesige Blöcke (etwa alle „Peter") kosten mehr, als sie finden
        }
        foreach (dublette_kombinationen($ids) as [$a, $b]) {
            $x = $m[$a];
            $y = $m[$b];
            if ($x['nachname'] === $y['nachname'] || abs(strlen($x['nachname']) - strlen($y['nachname'])) > 1) {
                continue;
            }
            if (!dublette_vorname_passt($x['vorname'], $y['vorname']) || !dublette_org_passt($x['org'], $y['org'])) {
                continue;
            }
            if (levenshtein($x['nachname'], $y['nachname']) === 1) {
                $melden($a, $b, 'wahrscheinlich', 'ähnlicher Nachname (Tippfehler?)');
            }
        }
    }

    /* 5. Reine Organisationen ohne Ansprechpartner mit gleichem Namen */
    $index = [];
    foreach ($m as $id => $x) {
        if ($x['nachname'] === '' && $x['org'] !== '') {
            $index[$x['org']][] = $id;
        }
    }
    foreach ($index as $ids) {
        foreach (dublette_kombinationen($ids) as [$a, $b]) {
            $melden($a, $b, 'sicher', 'gleiche Organisation ohne Ansprechpartner');
        }
    }

    return $paare;
}

/** Alle Zweierkombinationen einer Liste */
function dublette_kombinationen(array $ids): array
{
    $out = [];
    $n = count($ids);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $out[] = [(int)$ids[$i], (int)$ids[$j]];
        }
    }
    return $out;
}

/**
 * Paare zu Gruppen zusammenfassen. Als „keine Dublette" gemerkte Paare
 * fallen vorher weg. Rückgabe sortiert: sichere zuerst, dann nach der
 * kleinsten Nummer.
 *
 * Gruppe: ['ids' => [...], 'stufe' => …, 'gruende' => [...]]
 */
function kontakt_dubletten_finden(array $kontakte, array $ignoriert = []): array
{
    $paare = dublette_paare($kontakte);
    foreach ($ignoriert as $p) {
        unset($paare[is_array($p) ? dublette_paar((int)$p[0], (int)$p[1]) : (string)$p]);
    }
    if (!$paare) {
        return [];
    }

    // Union-Find: jeder Kontakt zeigt auf seinen Vertreter
    $eltern = [];
    $finde = static function (int $x) use (&$eltern, &$finde): int {
        $eltern[$x] ??= $x;
        while ($eltern[$x] !== $x) {
            $eltern[$x] = $eltern[$eltern[$x]];
            $x = $eltern[$x];
        }
        return $x;
    };
    foreach ($paare as $p) {
        $ra = $finde($p['a']);
        $rb = $finde($p['b']);
        if ($ra !== $rb) {
            $eltern[max($ra, $rb)] = min($ra, $rb);
        }
    }

    $gruppen = [];
    $rang = ['moeglich' => 0, 'wahrscheinlich' => 1, 'sicher' => 2];
    foreach ($paare as $p) {
        $r = $finde($p['a']);
        $g = &$gruppen[$r];
        $g ??= ['ids' => [], 'stufe' => 'moeglich', 'gruende' => []];
        foreach ([$p['a'], $p['b']] as $id) {
            if (!in_array($id, $g['ids'], true)) {
                $g['ids'][] = $id;
            }
        }
        foreach ($p['gruende'] as $grund) {
            if (!in_array($grund, $g['gruende'], true)) {
                $g['gruende'][] = $grund;
            }
        }
        if ($rang[$p['stufe']] > $rang[$g['stufe']]) {
            $g['stufe'] = $p['stufe'];
        }
        unset($g);
    }

    foreach ($gruppen as &$g) {
        sort($g['ids']);
    }
    unset($g);
    usort($gruppen, static fn($x, $y) => [$rang[$y['stufe']], $x['ids'][0]] <=> [$rang[$x['stufe']], $y['ids'][0]]);

    return array_values($gruppen);
}

/** Lesbare Bezeichnung einer Stufe samt Farbe */
function dublette_stufe(string $stufe): array
{
    return match ($stufe) {
        'sicher'         => ['sicher', '#b91c1c'],
        'wahrscheinlich' => ['wahrscheinlich', '#b45309'],
        default          => ['möglich', '#64748b'],
    };
}

/* ---------------- Datenbank: Suche und Merkliste ---------------- */

/** Alle Kontakte (auch inaktive) mit den Suchfeldern */
function kontakt_dubletten_kandidaten(): array
{
    return db_all('SELECT ' . DUBLETTE_SUCHFELDER . ' FROM contacts ORDER BY id');
}

/** Gemerkte Paare „keine Dublette" als [a, b] */
function kontakt_dubletten_ignoriert(): array
{
    return array_map(
        static fn($r) => [(int)$r['contact_a'], (int)$r['contact_b']],
        db_all('SELECT contact_a, contact_b FROM contact_dubletten_ignoriert')
    );
}

/** Alle Paare einer Gruppe als „keine Dublette" merken */
function kontakt_dubletten_ignorieren(array $ids, array $user): int
{
    $n = 0;
    foreach (dublette_kombinationen(array_values(array_unique(array_filter(array_map('intval', $ids))))) as [$a, $b]) {
        $n += db_exec(
            'INSERT IGNORE INTO contact_dubletten_ignoriert (contact_a, contact_b, created_by) VALUES (?,?,?)',
            [min($a, $b), max($a, $b), (int)$user['id']]
        );
    }
    return $n;
}

function kontakt_dubletten_wieder_pruefen(int $a, int $b): void
{
    db_exec('DELETE FROM contact_dubletten_ignoriert WHERE contact_a = ? AND contact_b = ?', [min($a, $b), max($a, $b)]);
}

/* ---------------- Zusammenführen ---------------- */

/** Felder, die sich beim Zusammenführen je Kontakt wählen lassen */
const DUBLETTE_FELDER = [
    'anrede'       => 'Anrede',
    'titel'        => 'Titel',
    'vorname'      => 'Vorname',
    'nachname'     => 'Nachname',
    'organisation' => 'Organisation',
    'position'     => 'Funktion / Position',
    'kategorie_id' => 'Kategorie',
    'email'        => 'E-Mail',
    'telefon'      => 'Telefon',
    'mobil'        => 'Mobil',
    'strasse'      => 'Straße',
    'plz'          => 'PLZ',
    'ort'          => 'Ort',
    'land'         => 'Land',
    'anschreiben'  => 'Briefanrede',
    'notiz'        => 'Notiz',
];

/** Tabellen, deren contact_id auf den verbliebenen Kontakt umgehängt wird */
const DUBLETTE_VERWEISE = [
    'contact_group_members'        => 'Verteiler',
    'calendar_kontakte'            => 'Termine',
    'thw_dienststelle_kontakte'    => 'THW-Dienststellen',
    'meeting_attendees'            => 'Anwesenheiten',
    'meeting_protokoll_empfaenger' => 'Protokollempfänger',
    'event_guests'                 => 'Gästelisten',
    'umfrage_empfaenger'           => 'Abstimmungsberichte',
    'newsletter_kontakte'          => 'Newsletter',
];

/** Wert eines Feldes als Vergleichstext; Zusatzfelder über "extra:schluessel" */
function dublette_wert(array $c, string $feld): string
{
    if (str_starts_with($feld, 'extra:')) {
        return trim((string)(extra_values($c)[substr($feld, 6)] ?? ''));
    }
    return trim((string)($c[$feld] ?? ''));
}

/**
 * Welcher Kontakt liefert welches Feld? Vorgabe: der verbleibende Kontakt,
 * wenn er dort etwas stehen hat, sonst der erste mit einem Wert. Für die
 * Notiz gilt „alle", sobald zwei verschiedene Texte da sind.
 *
 * $kontakte: id => Zeile. Rückgabe: Feld => Kontakt-ID bzw. 'alle'.
 */
function dublette_vorschlag(array $kontakte, int $zielId, array $extraFelder = []): array
{
    $felder = array_keys(DUBLETTE_FELDER);
    foreach (array_keys($extraFelder) as $k) {
        $felder[] = 'extra:' . $k;
    }
    $reihe = array_merge([$zielId], array_diff(array_map('intval', array_keys($kontakte)), [$zielId]));

    $wahl = [];
    foreach ($felder as $feld) {
        $werte = [];
        foreach ($reihe as $id) {
            $w = dublette_wert($kontakte[$id], $feld);
            if ($w !== '') {
                $werte[$id] = $w;
            }
        }
        if ($feld === 'notiz' && count(array_unique($werte)) > 1) {
            $wahl[$feld] = 'alle';
            continue;
        }
        $wahl[$feld] = $werte ? (int)array_key_first($werte) : $zielId;
    }
    return $wahl;
}

/**
 * Die Daten des verbliebenen Kontakts nach der Auswahl. Reine Funktion.
 *
 * Unzulässige Wahlen (fremde ID, fehlendes Feld) fallen auf den Vorschlag
 * zurück. Aktiv bleibt der Kontakt, wenn einer der Beteiligten aktiv war.
 */
function dublette_zusammenfuehren(array $kontakte, int $zielId, array $wahl, array $extraFelder = []): array
{
    $vorschlag = dublette_vorschlag($kontakte, $zielId, $extraFelder);
    $daten = [];
    $extra = extra_values($kontakte[$zielId]);

    foreach ($vorschlag as $feld => $vorgabe) {
        $quelle = $wahl[$feld] ?? $vorgabe;
        if ($quelle !== 'alle' && !isset($kontakte[(int)$quelle])) {
            $quelle = $vorgabe;
        }

        if ($feld === 'notiz' && $quelle === 'alle') {
            $texte = [];
            foreach ($kontakte as $c) {
                $t = trim((string)($c['notiz'] ?? ''));
                if ($t !== '' && !in_array($t, $texte, true)) {
                    $texte[] = $t;
                }
            }
            $daten['notiz'] = implode("\n\n", $texte);
            continue;
        }
        if ($quelle === 'alle') {
            $quelle = $vorgabe === 'alle' ? $zielId : $vorgabe;
        }
        $q = $kontakte[(int)$quelle];

        if (str_starts_with($feld, 'extra:')) {
            $key = substr($feld, 6);
            $w = dublette_wert($q, $feld);
            if ($w !== '') {
                $extra[$key] = $w;
            } else {
                unset($extra[$key]);
            }
            continue;
        }
        if ($feld === 'kategorie_id') {
            $daten[$feld] = !empty($q['kategorie_id']) ? (int)$q['kategorie_id'] : null;
            continue;
        }
        $daten[$feld] = (string)($q[$feld] ?? '');
    }

    $daten['extra'] = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;
    $aktiv = 0;
    foreach ($kontakte as $c) {
        $aktiv = max($aktiv, (int)($c['is_active'] ?? 0));
    }
    $daten['is_active'] = $aktiv;

    return $daten;
}

/**
 * Zusammenführen in der Datenbank – in einer Transaktion. Der Kontakt
 * $zielId bleibt, alle anderen in $ids werden gelöscht, nachdem ihre
 * Verweise umgehängt wurden. Wo der verbleibende Kontakt schon eingetragen
 * ist (derselbe Verteiler, derselbe Termin), bleibt sein Eintrag und der
 * doppelte verschwindet mit dem Löschen.
 *
 * Rückgabe: ['ziel' => id, 'geloescht' => n, 'umgehaengt' => n, 'name' => …]
 */
function kontakt_dubletten_zusammenfuehren(int $zielId, array $ids, array $wahl, array $user): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!in_array($zielId, $ids, true)) {
        $ids[] = $zielId;
    }
    $kontakte = [];
    foreach ($ids as $id) {
        $c = contact_find($id);
        if ($c) {
            $kontakte[$id] = $c;
        }
    }
    if (count($kontakte) < 2 || !isset($kontakte[$zielId])) {
        throw new RuntimeException('Zum Zusammenführen braucht es mindestens zwei vorhandene Kontakte.');
    }

    $daten = dublette_zusammenfuehren($kontakte, $zielId, $wahl, contact_extra_fields());
    $daten['updated_by'] = (int)$user['id'];
    $quellen = array_values(array_diff(array_keys($kontakte), [$zielId]));
    $umgehaengt = 0;

    db()->beginTransaction();
    try {
        db_update('contacts', $daten, 'id = ?', [$zielId]);

        foreach ($quellen as $qid) {
            // Gäste behalten ihren Namen, falls ihr Eintrag beim Löschen stehen bleibt
            db_exec('UPDATE event_guests SET name = ? WHERE contact_id = ? AND name = \'\'',
                [mb_substr(contact_name($kontakte[$qid]), 0, 150), $qid]);
            foreach (array_keys(DUBLETTE_VERWEISE) as $tabelle) {
                $umgehaengt += db_exec(
                    'UPDATE IGNORE ' . $tabelle . ' SET contact_id = ? WHERE contact_id = ?',
                    [$zielId, $qid]
                );
            }
            db_exec('DELETE FROM contacts WHERE id = ?', [$qid]);
        }
        // Merkliste: Paare mit gelöschten Kontakten gehen per Fremdschlüssel mit
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }

    $name = contact_name(array_merge($kontakte[$zielId], $daten));
    audit('kontakt.zusammengefuehrt', 'contact', $zielId, sprintf(
        '%s – aus #%s, %d Verweise umgehängt',
        $name,
        implode(', #', $quellen),
        $umgehaengt
    ));

    return ['ziel' => $zielId, 'geloescht' => count($quellen), 'umgehaengt' => $umgehaengt, 'name' => $name];
}

/** Wie oft hängt an jedem Kontakt etwas? id => [Tabelle => Anzahl] */
function kontakt_dubletten_verweise(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    $out = array_fill_keys($ids, []);
    if (!$ids) {
        return $out;
    }
    $platz = implode(',', array_fill(0, count($ids), '?'));
    foreach (DUBLETTE_VERWEISE as $tabelle => $label) {
        foreach (db_all('SELECT contact_id, COUNT(*) AS n FROM ' . $tabelle
            . ' WHERE contact_id IN (' . $platz . ') GROUP BY contact_id', $ids) as $r) {
            $out[(int)$r['contact_id']][$label] = (int)$r['n'];
        }
    }
    return $out;
}

/**
 * Vorgabe für den verbleibenden Kontakt: der mit den meisten gefüllten
 * Feldern und Verweisen, bei Gleichstand der älteste. Reine Funktion.
 */
function dublette_ziel_vorschlag(array $kontakte, array $verweise = []): int
{
    $best = null;
    $bestWert = null;
    foreach ($kontakte as $id => $c) {
        $gefuellt = 0;
        foreach (array_keys(DUBLETTE_FELDER) as $f) {
            if (dublette_wert($c, $f) !== '') {
                $gefuellt++;
            }
        }
        $gefuellt += count(extra_values($c));
        $wert = [$gefuellt + array_sum($verweise[(int)$id] ?? []), -(int)$id];
        if ($bestWert === null || $wert > $bestWert) {
            $bestWert = $wert;
            $best = (int)$id;
        }
    }
    return (int)$best;
}
