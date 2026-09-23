<?php
/** @var array $event @var array $gaeste @var array $stats @var array $dateien
 *  @var array $buchungen @var array $kosten @var ?array $connector
 *  @var array $kandidaten @var string $kontaktSuche @var array $verteiler */

$id = (int)$event['id'];
$darf = can('manage_events');
$beginn = (string)$event['beginn'];
$ende = (string)($event['ende'] ?? '');
$zeit = substr($beginn, 11, 5);
$rest = (float)$event['kosten_geplant'] - (float)$kosten['ausgaben'];
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)$event['titel']) ?></h1>
    <p><?= e(de_date(substr($beginn, 0, 10))) ?><?= $zeit !== '00:00' ? ', ' . e($zeit) . ' Uhr' : '' ?>
      <?php if ($ende !== ''): ?>
        bis <?= substr($ende, 0, 10) === substr($beginn, 0, 10)
            ? e(substr($ende, 11, 5)) . ' Uhr'
            : e(de_datetime($ende)) ?>
      <?php endif; ?>
      <?= $event['ort'] ? ' · ' . e((string)$event['ort']) : '' ?>
      · <?= e(event_status_label((string)$event['status'])) ?></p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?>
      <a class="btn" href="<?= e(url('event_edit', ['id' => $id])) ?>">Bearbeiten</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('events')) ?>">Alle Veranstaltungen</a>
  </div>
</div>

<?php if (trim((string)($event['beschreibung'] ?? '')) !== ''): ?>
  <div class="card"><?= nl2br(e((string)$event['beschreibung'])) ?></div>
<?php endif; ?>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Zusagen</div>
    <div class="stat__value"><?= (int)$stats['zusagen'] + (int)$stats['vertretungen'] ?></div>
    <div class="stat__hint">von <?= (int)$stats['eingeladen'] ?> Eingeladenen</div>
  </div>
  <div class="stat">
    <div class="stat__label">Personen</div>
    <div class="stat__value"><?= (int)$stats['personen'] ?></div>
    <div class="stat__hint"><?= (int)$stats['begleiter'] ?> davon Begleiter</div>
  </div>
  <div class="stat">
    <div class="stat__label">Offen</div>
    <div class="stat__value"><?= (int)$stats['offen'] ?></div>
    <div class="stat__hint"><?= (int)$stats['absagen'] ?> Absagen</div>
  </div>
  <?php if (can('view_expenses')): ?>
    <div class="stat">
      <div class="stat__label">Ausgegeben</div>
      <div class="stat__value"><?= e(money((float)$kosten['ausgaben'])) ?></div>
      <div class="stat__hint"><?= (float)$event['kosten_geplant'] > 0
          ? 'geplant ' . e(money((float)$event['kosten_geplant'])) . ', ' .
            ($rest >= 0 ? 'noch ' . e(money($rest)) : e(money(-$rest)) . ' darüber')
          : 'ohne Planung' ?></div>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Angaben</h2>
  <dl class="dl">
    <div class="dl__item"><div class="dl__label">Beginn</div>
      <div class="dl__value"><?= e(de_datetime($beginn)) ?></div></div>
    <?php if ($ende !== ''): ?>
      <div class="dl__item"><div class="dl__label">Ende</div>
        <div class="dl__value"><?= e(de_datetime($ende)) ?></div></div>
    <?php endif; ?>
    <?php if ($event['ort']): ?>
      <div class="dl__item"><div class="dl__label">Ort</div>
        <div class="dl__value"><?= e((string)$event['ort']) ?></div></div>
    <?php endif; ?>
    <?php if ($event['fachgruppe_label']): ?>
      <div class="dl__item"><div class="dl__label">Fachgruppe</div>
        <div class="dl__value"><?= e((string)$event['fachgruppe_label']) ?></div></div>
    <?php endif; ?>
    <?php if ($event['budget_name']): ?>
      <div class="dl__item"><div class="dl__label">Budgettopf</div>
        <div class="dl__value"><?= e((string)$event['budget_name']) ?></div></div>
    <?php endif; ?>
    <?php if ($event['rueckmeldung_bis']): ?>
      <div class="dl__item"><div class="dl__label">Rückmeldung bis</div>
        <div class="dl__value"><?= e(de_date((string)$event['rueckmeldung_bis'])) ?></div></div>
    <?php endif; ?>
    <div class="dl__item"><div class="dl__label">Begleiter je Person</div>
      <div class="dl__value"><?= (int)$event['begleiter_max'] === 0
          ? 'niemand' : (int)$event['begleiter_max'] ?></div></div>
    <div class="dl__item"><div class="dl__label">Kommentare</div>
      <div class="dl__value"><?= (int)$event['kommentare_erlaubt'] === 1 ? 'erlaubt' : 'aus' ?></div></div>
    <div class="dl__item"><div class="dl__label">Vertretung</div>
      <div class="dl__value"><?= (int)$event['vertretung_erlaubt'] === 1 ? 'erlaubt' : 'aus' ?></div></div>
  </dl>
  <?php if (trim((string)($event['notiz'] ?? '')) !== ''): ?>
    <p class="small muted"><?= nl2br(e((string)$event['notiz'])) ?></p>
  <?php endif; ?>
</div>

<?php if (can('view_expenses')): ?>
<div class="card" id="geld">
  <div class="card__head">
    <h2>Ausgaben und Einnahmen</h2>
    <?php if (can('manage_budget')): ?>
      <a class="btn btn--sec btn--sm" href="<?= e(url('expense_edit', ['event_id' => $id])) ?>">+ Buchung</a>
    <?php endif; ?>
  </div>
  <?php if (!$buchungen): ?>
    <div class="empty">Für diese Veranstaltung ist noch nichts gebucht.
      Buchungen bekommen den Bezug beim Erfassen oder Bearbeiten.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Datum</th><th>Bezeichnung</th><th>Kategorie</th><th style="text-align:right">Betrag</th></tr></thead>
        <tbody>
        <?php foreach ($buchungen as $b): ?>
          <tr>
            <td class="small"><?= e(de_date((string)$b['datum'])) ?></td>
            <td><a href="<?= e(url('expense_edit', ['id' => $b['id']])) ?>"><?= e((string)$b['bezeichnung']) ?></a>
              <?php if ($b['lieferant']): ?><div class="small muted"><?= e((string)$b['lieferant']) ?></div><?php endif; ?></td>
            <td class="small"><?= e((string)($b['kategorie_label'] ?? '–')) ?></td>
            <td style="text-align:right"<?= (string)$b['art'] === 'einnahme' ? ' class="ok"' : '' ?>>
              <?= (string)$b['art'] === 'einnahme' ? '+' : '' ?><?= e(money((float)$b['betrag_brutto'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
          <th colspan="3">Ausgaben <?= e(money((float)$kosten['ausgaben'])) ?>,
            Einnahmen <?= e(money((float)$kosten['einnahmen'])) ?></th>
          <th style="text-align:right"><?= e(money((float)$kosten['einnahmen'] - (float)$kosten['ausgaben'])) ?></th>
        </tr></tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card" id="gaeste">
  <div class="card__head">
    <h2>Gästeliste</h2>
    <span class="muted small"><?= (int)$stats['eingeladen'] ?> eingeladen</span>
  </div>
  <?php if ($gaeste): ?>
    <div class="btnrow">
      <a class="btn btn--sec btn--sm" href="<?= e(url('event_print', ['id' => $id, 'wen' => 'zusagen'])) ?>">Einlassliste drucken</a>
      <a class="btn btn--sec btn--sm" href="<?= e(url('event_print', ['id' => $id])) ?>">Ganze Liste drucken</a>
      <?php if ($darf): ?>
        <a class="btn btn--sec btn--sm" href="<?= e(url('event_export', ['id' => $id])) ?>">CSV</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!$gaeste): ?>
    <div class="empty">Noch niemand eingeladen.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr>
          <th>Name</th><th>Rückmeldung</th><th>Einladung</th><?php if ($darf): ?><th></th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($gaeste as $g): ?>
          <?php
            $farbe = match ((string)$g['status']) {
                'zusage'     => '#15803d',
                'vertretung' => '#0284c7',
                'absage'     => '#b91c1c',
                default      => '',
            };
            $adresse = $connector !== null ? event_invite_url($connector, (string)$g['code']) : '';
          ?>
          <tr>
            <td>
              <strong><?= e(event_guest_name($g)) ?></strong>
              <?php if ($g['organisation']): ?>
                <div class="small muted"><?= e((string)$g['organisation']) ?></div>
              <?php endif; ?>
              <?php if ($g['contact_id'] && can('view_contacts')): ?>
                <div class="small"><a href="<?= e(url('contact_edit', ['id' => $g['contact_id']])) ?>">Kontakt</a></div>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge" style="<?= $farbe !== '' ? 'background:' . $farbe : 'background:#94a3b8' ?>">
                <?= e(event_antwort_label((string)$g['status'])) ?></span>
              <?php if ((int)$g['begleiter'] > 0): ?>
                <span class="badge badge--outline">+<?= (int)$g['begleiter'] ?> Begleiter</span>
              <?php endif; ?>
              <?php if ($g['vertretung']): ?>
                <div class="small">Vertretung: <?= e((string)$g['vertretung']) ?></div>
              <?php endif; ?>
              <?php if (trim((string)($g['kommentar'] ?? '')) !== ''): ?>
                <div class="small muted">„<?= e((string)$g['kommentar']) ?>"</div>
              <?php endif; ?>
              <?php if ($g['geantwortet_am']): ?>
                <div class="small muted"><?= e(de_datetime((string)$g['geantwortet_am'])) ?>
                  <?= (string)$g['quelle'] === 'einladung' ? '· über die Einladung' : '· von Hand' ?></div>
              <?php endif; ?>
            </td>
            <td class="small mono" style="word-break:break-all">
              <?= e((string)$g['code']) ?>
              <?php if ($adresse !== ''): ?>
                <div class="muted"><?= e($adresse) ?></div>
              <?php endif; ?>
            </td>
            <?php if ($darf): ?>
            <td>
              <details>
                <summary class="small">Ändern</summary>
                <form method="post" action="<?= e(url('event_action')) ?>" class="form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="gast_antwort">
                  <input type="hidden" name="event_id" value="<?= $id ?>">
                  <input type="hidden" name="guest_id" value="<?= (int)$g['id'] ?>">
                  <div class="field">
                    <label for="status<?= (int)$g['id'] ?>">Rückmeldung</label>
                    <select id="status<?= (int)$g['id'] ?>" name="status">
                      <?php foreach (EVENT_ANTWORTEN as $key => $label): ?>
                        <?php if ($key === 'vertretung' && (int)$event['vertretung_erlaubt'] !== 1) { continue; } ?>
                        <option value="<?= e($key) ?>" <?= $key === (string)$g['status'] ? 'selected' : '' ?>>
                          <?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <?php if ((int)$event['begleiter_max'] > 0): ?>
                    <div class="field">
                      <label for="begleiter<?= (int)$g['id'] ?>">Begleiter</label>
                      <input type="number" id="begleiter<?= (int)$g['id'] ?>" name="begleiter" min="0"
                             max="<?= (int)$event['begleiter_max'] ?>" value="<?= (int)$g['begleiter'] ?>">
                    </div>
                  <?php endif; ?>
                  <?php if ((int)$event['vertretung_erlaubt'] === 1): ?>
                    <div class="field">
                      <label for="vertretung<?= (int)$g['id'] ?>">Vertretung</label>
                      <input type="text" id="vertretung<?= (int)$g['id'] ?>" name="vertretung" maxlength="150"
                             value="<?= e((string)$g['vertretung']) ?>">
                    </div>
                  <?php endif; ?>
                  <div class="btnrow">
                    <button class="btn btn--sm" type="submit">Eintragen</button>
                  </div>
                </form>
                <div class="btnrow">
                  <form method="post" action="<?= e(url('event_action')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="gast_code">
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <input type="hidden" name="guest_id" value="<?= (int)$g['id'] ?>">
                    <button class="btn btn--sec btn--sm" type="submit"
                            data-confirm="Neuen Einladungscode erzeugen? Der bisherige Link funktioniert dann nicht mehr.">Code neu</button>
                  </form>
                  <form method="post" action="<?= e(url('event_action')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="gast_weg">
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <input type="hidden" name="guest_id" value="<?= (int)$g['id'] ?>">
                    <button class="btn btn--sec btn--sm" type="submit"
                            data-confirm="Von der Gästeliste nehmen? Die Rückmeldung geht dabei verloren.">Entfernen</button>
                  </form>
                </div>
              </details>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($darf): ?>
    <h3>Einladen</h3>
    <?php if ($verteiler): ?>
      <form method="post" action="<?= e(url('event_action')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="gast_gruppe">
        <input type="hidden" name="event_id" value="<?= $id ?>">
        <div class="filters">
          <div class="field">
            <label for="group_id">Ganzen Verteiler einladen</label>
            <select id="group_id" name="group_id">
              <?php foreach ($verteiler as $v): ?>
                <option value="<?= (int)$v['id'] ?>"><?= e((string)$v['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>&nbsp;</label>
            <button class="btn btn--sec" type="submit">Übernehmen</button>
          </div>
        </div>
      </form>
    <?php endif; ?>

    <?php if (can('view_contacts')): ?>
      <form method="get" class="form">
        <input type="hidden" name="p" value="event">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="filters">
          <div class="field">
            <label for="kontakt_suche">Kontakt suchen</label>
            <input type="search" id="kontakt_suche" name="kontakt_suche" value="<?= e($kontaktSuche) ?>"
                   placeholder="Name, Organisation, Ort">
          </div>
          <div class="field">
            <label>&nbsp;</label>
            <button class="btn btn--sec" type="submit">Suchen</button>
          </div>
        </div>
      </form>
      <?php if ($kandidaten): ?>
        <div class="tablewrap">
          <table class="data">
            <tbody>
            <?php foreach (array_slice($kandidaten, 0, 50) as $k): ?>
              <tr>
                <td><?= e(contact_name($k)) ?>
                  <?php if ($k['organisation']): ?>
                    <span class="muted small">· <?= e((string)$k['organisation']) ?></span>
                  <?php endif; ?></td>
                <td style="text-align:right">
                  <form method="post" action="<?= e(url('event_action')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="gast_add">
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <input type="hidden" name="contact_id" value="<?= (int)$k['id'] ?>">
                    <button class="btn btn--sec btn--sm" type="submit">Einladen</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php elseif ($kontaktSuche !== ''): ?>
        <div class="empty">Kein Kontakt gefunden, der noch nicht eingeladen ist.</div>
      <?php endif; ?>
    <?php endif; ?>

    <form method="post" action="<?= e(url('event_action')) ?>" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="gast_add">
      <input type="hidden" name="event_id" value="<?= $id ?>">
      <div class="filters">
        <div class="field">
          <label for="name">Ohne Kontakt einladen</label>
          <input type="text" id="name" name="name" maxlength="150" placeholder="Name der Person">
        </div>
        <div class="field">
          <label>&nbsp;</label>
          <button class="btn btn--sec" type="submit">Einladen</button>
        </div>
      </div>
    </form>
  <?php endif; ?>
</div>

<div class="card" id="einladungen">
  <h2>Einladungen im Netz</h2>
  <?php if ($connector === null): ?>
    <p class="small">Für diese Veranstaltung ist kein Connector eingetragen. Die Einladungscodes
      gibt es trotzdem – nur steht keine Seite im Netz, auf der sich jemand zurückmelden kann.
      <?php if ($darf): ?>
        Ein Connector lässt sich beim <a href="<?= e(url('event_edit', ['id' => $id])) ?>">Bearbeiten</a>
        auswählen; angelegt wird er in der <a href="<?= e(url('admin_connectors')) ?>">Verwaltung</a>.
      <?php endif; ?></p>
  <?php else: ?>
    <dl class="dl">
      <div class="dl__item"><div class="dl__label">Connector</div>
        <div class="dl__value"><?= e((string)$connector['name']) ?>
          <?php if (!connector_gekoppelt($connector)): ?>
            <span class="badge" style="background:var(--warn)">nicht gekoppelt</span>
          <?php elseif ((int)$connector['fuer_veranstaltungen'] !== 1): ?>
            <span class="badge" style="background:var(--warn)">nicht für Veranstaltungen freigegeben</span>
          <?php endif; ?></div></div>
      <div class="dl__item"><div class="dl__label">Einladungsadresse</div>
        <div class="dl__value mono small" style="word-break:break-all">
          <?= e(event_invite_url($connector, str_repeat('X', (int)$event['code_laenge']))) ?></div></div>
    </dl>
    <p class="small muted">Auf der Einladungsseite stehen Titel, Zeitpunkt, Ort und der Hinweistext –
      mehr weiß der Connector nicht. Von den Codes liegen dort nur Prüfsummen, Namen gar nicht.</p>
    <?php if ($darf): ?>
      <div class="btnrow">
        <form method="post" action="<?= e(url('event_action')) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="einladungen">
          <input type="hidden" name="event_id" value="<?= $id ?>">
          <button class="btn btn--sec" type="submit">Jetzt anmelden</button>
        </form>
        <form method="post" action="<?= e(url('event_action')) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="abholen">
          <input type="hidden" name="event_id" value="<?= $id ?>">
          <button class="btn btn--sec" type="submit">Rückmeldungen abholen</button>
        </form>
      </div>
      <p class="small muted">Beides passiert von allein im Takt der Einstellung
        „Rückmeldungen abholen alle (Minuten)". Die Knöpfe sind für die Ungeduld.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card" id="dateien">
  <div class="card__head">
    <h2>Dateien</h2>
    <span class="muted small"><?= count($dateien) ?></span>
  </div>
  <?php if (!$dateien): ?>
    <div class="empty">Noch nichts abgelegt – hier gehören Rechnungen, Angebote und das Programm hin.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Datei</th><th>Größe</th><th style="text-align:right">Betrag</th>
          <?php if ($darf): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($dateien as $f): ?>
          <tr>
            <td>
              <a href="<?= e(url('event_file', ['id' => $f['id']])) ?>"><?= e((string)($f['titel'] ?: $f['orig_name'])) ?></a>
              <div class="small muted"><?= e((string)$f['orig_name']) ?>
                · <?= e(de_datetime((string)$f['created_at'])) ?>
                <?= $f['hochgeladen_von'] ? '· ' . e((string)$f['hochgeladen_von']) : '' ?></div>
            </td>
            <td class="small"><?= e(bytes_human((int)$f['size_bytes'])) ?></td>
            <td class="small" style="text-align:right"><?= $f['betrag'] !== null
                ? e(money((float)$f['betrag'])) : '<span class="muted">–</span>' ?></td>
            <?php if ($darf): ?>
              <td style="text-align:right">
                <form method="post" action="<?= e(url('event_action')) ?>" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="datei_weg">
                  <input type="hidden" name="event_id" value="<?= $id ?>">
                  <input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>">
                  <button class="btn btn--sec btn--sm" type="submit"
                          data-confirm="Diese Datei entfernen?">Entfernen</button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (efile_betrag($dateien) > 0): ?>
      <p class="small muted">An den Dateien hängen Beträge über zusammen
        <?= e(money(efile_betrag($dateien))) ?>. Gebucht ist davon nur, was oben unter
        „Ausgaben und Einnahmen" steht.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($darf): ?>
    <form method="post" action="<?= e(url('event_action')) ?>" class="form" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="datei">
      <input type="hidden" name="event_id" value="<?= $id ?>">
      <div class="grid2">
        <div class="field">
          <label for="dateien">Datei hinzufügen</label>
          <input type="file" id="dateien" name="dateien[]" multiple required>
        </div>
        <div class="field">
          <label for="titel">Bezeichnung <span class="muted">(freiwillig)</span></label>
          <input type="text" id="titel" name="titel" maxlength="200" placeholder="z. B. Rechnung Zelt">
        </div>
      </div>
      <div class="field">
        <label for="betrag">Betrag auf der Rechnung <span class="muted">(freiwillig)</span></label>
        <input type="text" id="betrag" name="betrag" inputmode="decimal" placeholder="0,00">
        <small class="muted">Nur als Merkposten an der Datei. Für das Budget zählt eine Buchung.</small>
      </div>
      <div class="btnrow">
        <button class="btn" type="submit">Hochladen</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php if ($darf): ?>
<div class="card">
  <h2>Veranstaltung löschen</h2>
  <p class="small">Gästeliste, Rückmeldungen und Dateien verschwinden mit. Erfasste Buchungen
    bleiben im Budget stehen und verlieren nur den Bezug.</p>
  <form method="post" action="<?= e(url('event_action')) ?>" class="inline-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="loeschen">
    <input type="hidden" name="event_id" value="<?= $id ?>">
    <button class="btn btn--sec" type="submit"
            data-confirm="Diese Veranstaltung mit Gästeliste, Rückmeldungen und Dateien löschen?"
            data-confirm2="Bist du wirklich sicher?">Löschen</button>
  </form>
</div>
<?php endif; ?>
