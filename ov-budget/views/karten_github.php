<?php
/** @var ?array $stand @var int $maxMb */
$schritt = (string)($stand['schritt'] ?? '');
$info = $stand['info'] ?? null;
?>
<div class="pagehead">
  <div>
    <h1>Symbole von GitHub holen</h1>
    <p>Ein öffentliches Repository mit Zeichen einmalig in die Symbolbibliothek übernehmen – etwa taktische Zeichen.</p>
  </div>
  <div class="btnrow"><a class="btn btn--sec" href="<?= e(url('karten_symbole')) ?>">Zur Symbolbibliothek</a></div>
</div>

<div class="alert alert--warn">
  <strong>Die Anwendung bringt keine Symbole mit und holt nichts von selbst.</strong>
  Dieser Abruf lädt ein Repository auf euren Server – nur jetzt, nur auf euren Knopfdruck. Ob und wie ihr die Zeichen
  verwenden dürft, regelt die Lizenz des Repositorys: bitte dort nachlesen. Namensnennung und andere Auflagen sind eure Sache;
  Quelle und Lizenz werden an jedem übernommenen Symbol vermerkt.
</div>

<?php if ($schritt === ''): ?>
  <section class="card">
    <h2>1. Repository nennen</h2>
    <form method="post" action="<?= e(url('karten_github')) ?>" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="pruefen">
      <div class="field" style="max-width:34rem">
        <label for="repo">Repository</label>
        <input type="text" id="repo" name="repo" required maxlength="200" placeholder="z. B. jonas-koeritz/Taktische-Zeichen" autocomplete="off">
        <small class="muted">„eigner/name" oder die Adresse der GitHub-Seite. Es geht nur mit öffentlichen Repositorys bei github.com.</small>
      </div>
      <div class="btnrow"><button class="btn" type="submit">Bei GitHub nachsehen</button></div>
      <p class="small muted" style="margin:.5rem 0 0">Noch wird nichts geladen: Im nächsten Schritt seht ihr Lizenz und Pakete und entscheidet dann.</p>
    </form>
  </section>

<?php elseif ($schritt === 'quelle'): ?>
  <section class="card">
    <h2>2. Paket wählen und Lizenz bestätigen</h2>
    <dl class="dl">
      <div class="dl__item"><div class="dl__label">Repository</div><div class="dl__value"><a href="<?= e((string)$info['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$info['name']) ?></a>
        <?php if ((string)$info['beschreibung'] !== ''): ?><div class="small muted"><?= e((string)$info['beschreibung']) ?></div><?php endif; ?></div></div>
      <div class="dl__item"><div class="dl__label">Lizenz laut GitHub</div><div class="dl__value">
        <?php if ((string)$info['lizenz'] !== '' || (string)$info['lizenz_name'] !== ''): ?>
          <strong><?= e((string)($info['lizenz'] ?: $info['lizenz_name'])) ?></strong><?= (string)$info['lizenz_name'] !== '' && (string)$info['lizenz'] !== '' ? ' – ' . e((string)$info['lizenz_name']) : '' ?>
        <?php else: ?>
          <strong style="color:var(--bad)">keine Lizenz erkannt</strong> – ohne Lizenz ist eine Nutzung in der Regel nicht erlaubt.
        <?php endif; ?>
        <div class="small muted">Das ist die Angabe für das ganze Repository. Für fertige Zeichen in einem Release kann etwas anderes gelten – es steht in der Beschreibung des Repositorys.</div></div></div>
    </dl>
    <form method="post" action="<?= e(url('karten_github')) ?>" class="form" style="margin-top:.8rem">
      <?= csrf_field() ?>
      <fieldset>
        <legend>Was soll geholt werden?</legend>
        <?php foreach ($info['quellen'] as $i => $q): $zuGross = $q['art'] === 'release' && (int)$q['bytes'] > $maxMb * 1048576; ?>
          <div class="field field--check">
            <input type="radio" id="quelle<?= (int)$i ?>" name="quelle" value="<?= (int)$i ?>" required<?= $zuGross ? ' disabled' : '' ?><?= $i === 0 && !$zuGross ? ' checked' : '' ?>>
            <label for="quelle<?= (int)$i ?>">
              <?php if ($q['art'] === 'release'): ?>
                Release <?= e((string)$q['ref']) ?>: <strong><?= e((string)$q['name']) ?></strong> · <?= e(bytes_human((int)$q['bytes'])) ?>
                <?= $zuGross ? ' <span style="color:var(--bad)">– größer als ' . (int)$maxMb . ' MB</span>' : '' ?>
              <?php else: ?>
                <?= e((string)$q['name']) ?> · etwa <?= e(bytes_human((int)$q['bytes'])) ?>
              <?php endif; ?>
            </label>
          </div>
        <?php endforeach; ?>
        <p class="small muted">Im Release-Paket liegen meist die fertigen Zeichen. Der Quelltext enthält bei manchen Repositorys nur Vorlagen, aus denen die Zeichen erst erzeugt werden.</p>
      </fieldset>
      <div class="field" style="max-width:26rem">
        <label for="lizenz">Lizenz, wie sie am Symbol stehen soll</label>
        <input type="text" id="lizenz" name="lizenz" maxlength="80" value="<?= e((string)$info['lizenz']) ?>" placeholder="z. B. CC0-1.0 oder CC-BY-4.0">
        <small class="muted">Vorbelegt mit der Angabe von GitHub – bitte anpassen, wenn für die Zeichen etwas anderes gilt.</small>
      </div>
      <div class="field field--check">
        <input type="checkbox" id="lizenz_ok" name="lizenz_ok" value="1" required>
        <label for="lizenz_ok">Wir haben die Lizenz des Repositorys gelesen und dürfen die Zeichen in unserem Ortsverband verwenden.</label>
      </div>
      <div class="btnrow">
        <button class="btn" type="submit" name="action" value="laden">Paket holen</button>
        <button class="btn btn--sec" type="submit" name="action" value="verwerfen" formnovalidate>Abbrechen</button>
      </div>
      <p class="small muted" style="margin:.5rem 0 0">Das Holen kann bei großen Paketen ein, zwei Minuten dauern. Übernommen wird danach erst, was ihr im nächsten Schritt ankreuzt.</p>
    </form>
  </section>

<?php elseif ($schritt === 'auswahl'): $inhalt = $stand['inhalt']; $nr = -1; ?>
  <section class="card">
    <h2>3. Ordner wählen und übernehmen</h2>
    <p class="small muted">Aus <strong><?= e((string)$info['name']) ?></strong> (<?= e((string)$stand['quelle']['name']) ?><?= (string)$stand['quelle']['ref'] !== '' ? ', Stand ' . e((string)$stand['quelle']['ref']) : '' ?>):
      <?= (int)$inhalt['svg'] ?> SVG und <?= (int)$inhalt['bild'] ?> Bilder in <?= count($inhalt['ordner']) ?> Ordnern.
      <?= !empty($inhalt['abgeschnitten']) ? 'Das Paket ist sehr groß – es wurde nur der Anfang durchgesehen.' : '' ?>
      Jeder Ordner wird eine Gruppe der Bibliothek; der Name des Symbols ist der Dateiname.</p>
    <form method="post" action="<?= e(url('karten_github')) ?>" class="form">
      <?= csrf_field() ?>
      <div class="grid2">
        <div class="field">
          <label for="typen">Dateitypen</label>
          <select id="typen" name="typen">
            <option value="svg">nur SVG (bleibt in jeder Größe scharf)</option>
            <option value="bild">nur Bilder (PNG, JPG …)</option>
            <option value="beides">beides</option>
          </select>
          <small class="muted">Liegt ein Zeichen als SVG und als PNG im Paket, genügt die SVG.</small>
        </div>
        <div class="field">
          <label for="vorsatz">Vor den Gruppennamen setzen <span class="muted">(freiwillig)</span></label>
          <input type="text" id="vorsatz" name="vorsatz" maxlength="40" placeholder="z. B. TZ">
          <small class="muted">Aus dem Ordner „THW_Fahrzeuge" wird die Gruppe „THW Fahrzeuge" – mit Vorsatz „TZ THW Fahrzeuge".</small>
        </div>
      </div>
      <div class="tablewrap" style="max-height:28rem;overflow:auto">
        <table class="data">
          <thead><tr><th style="width:2rem"></th><th>Ordner im Paket</th><th>wird Gruppe</th><th class="num">SVG</th><th class="num">Bilder</th></tr></thead>
          <tbody>
          <?php foreach ($inhalt['ordner'] as $pfad => $zahl): $nr++; ?>
            <tr>
              <td><input type="checkbox" id="ordner<?= (int)$nr ?>" name="ordner[]" value="<?= (int)$nr ?>"></td>
              <td><label for="ordner<?= (int)$nr ?>" class="mono small"><?= (string)$pfad !== '' ? e((string)$pfad) : '(oberste Ebene)' ?></label></td>
              <td class="small"><?= e(karten_github_gruppe((string)$pfad)) ?: '<span class="muted">ohne Gruppe</span>' ?></td>
              <td class="num"><?= (int)$zahl['svg'] ?: '<span class="muted">–</span>' ?></td>
              <td class="num"><?= (int)$zahl['bild'] ?: '<span class="muted">–</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="small muted">Lizenz am Symbol: <strong><?= e((string)$stand['lizenz'] ?: 'keine Angabe') ?></strong>. Jede SVG wird geprüft wie eine hochgeladene –
        mit Skript oder Verweisen nach außen bleibt sie draußen. Was aus dieser Quelle schon in der Bibliothek liegt, wird nicht doppelt angelegt.
        Höchstens <?= (int)KARTEN_GITHUB_MAX_IMPORT ?> Symbole je Durchgang.</p>
      <div class="btnrow">
        <button class="btn" type="submit" name="action" value="importieren">Angekreuzte Ordner übernehmen</button>
        <button class="btn btn--sec" type="submit" name="action" value="verwerfen" formnovalidate data-confirm="Das geholte Paket verwerfen? Es wird vom Server gelöscht.">Paket verwerfen</button>
      </div>
    </form>
  </section>
<?php endif; ?>
