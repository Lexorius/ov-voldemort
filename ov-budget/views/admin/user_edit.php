<?php
/** @var array $edit @var array $errors @var array $funktionen @var bool $istIchSelbst */
$isNew = empty($edit['id']);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Benutzer anlegen' : 'Benutzer bearbeiten' ?></h1>
    <?php if (!$isNew): ?>
      <p class="muted small">
        angelegt am <?= e(de_datetime($edit['created_at'])) ?> ·
        letzte Anmeldung <?= e(de_datetime($edit['last_login']) ?: 'nie') ?>
      </p>
    <?php endif; ?>
  </div>
  <a class="btn btn--sec" href="<?= e(url('admin_users')) ?>">Zur Liste</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form" action="<?= e(url('admin_user_edit', $isNew ? [] : ['id' => $edit['id']])) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">

  <div class="grid2">
    <div class="field">
      <label for="username">Benutzername *</label>
      <input type="text" id="username" name="username" required autocapitalize="none"
             value="<?= e((string)$edit['username']) ?>" placeholder="vorname.nachname">
    </div>
    <div class="field">
      <label for="display_name">Anzeigename</label>
      <input type="text" id="display_name" name="display_name" value="<?= e((string)$edit['display_name']) ?>">
    </div>
    <div class="field">
      <label for="email">E-Mail</label>
      <input type="email" id="email" name="email" value="<?= e((string)$edit['email']) ?>">
    </div>
    <div class="field">
      <label for="phone">Telefon</label>
      <input type="tel" id="phone" name="phone" value="<?= e((string)$edit['phone']) ?>">
    </div>
    <div class="field">
      <label for="role">Rolle</label>
      <select id="role" name="role"<?= $istIchSelbst ? ' disabled' : '' ?>>
        <option value="user"<?= $edit['role'] === 'user' ? ' selected' : '' ?>>Mitglied – Wünsche und eigene Aufgaben</option>
        <option value="leitung"<?= $edit['role'] === 'leitung' ? ' selected' : '' ?>>Leitung – priorisiert, verwaltet Budget und Aufgaben</option>
        <option value="admin"<?= $edit['role'] === 'admin' ? ' selected' : '' ?>>Administration – volle Rechte</option>
      </select>
      <?php if ($istIchSelbst): ?>
        <input type="hidden" name="role" value="admin">
        <small>Die eigene Rolle kann nicht geändert werden.</small>
      <?php endif; ?>
    </div>
    <div class="field">
      <label for="fachgruppe_id">Fachgruppe</label>
      <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', (int)($edit['fachgruppe_id'] ?? 0)) ?></select>
    </div>
    <?php if ($isNew): ?>
      <div class="field">
        <label for="passwort">Startpasswort</label>
        <input type="text" id="passwort" name="passwort" autocomplete="new-password"
               placeholder="leer lassen = zufällig erzeugen">
        <small>Mindestens <?= setting_int('passwort_min_laenge', 10) ?> Zeichen. Der Benutzer muss es beim ersten Anmelden ändern.</small>
      </div>
    <?php endif; ?>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($edit['is_active']) ? 'checked' : '' ?>
             <?= $istIchSelbst ? 'disabled' : '' ?>>
      <label for="is_active">Zugang aktiv</label>
      <?php if ($istIchSelbst): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
    </div>
  </div>

  <fieldset>
    <legend>Funktionen im Ortsverband</legend>
    <div class="grid3">
      <?php foreach (list_items('funktion') as $fn): ?>
        <div class="field field--check">
          <input type="checkbox" id="fn<?= (int)$fn['id'] ?>" name="funktionen[]" value="<?= (int)$fn['id'] ?>"
                 <?= in_array((int)$fn['id'], $funktionen, true) ? 'checked' : '' ?>>
          <label for="fn<?= (int)$fn['id'] ?>"><?= e($fn['label']) ?></label>
        </div>
      <?php endforeach; ?>
    </div>
    <small class="muted">Aufgaben, die einer Funktion zugewiesen sind, erscheinen bei allen Personen mit dieser Funktion.</small>
  </fieldset>

  <div class="btnrow">
    <button class="btn" type="submit">Speichern</button>
    <a class="btn btn--sec" href="<?= e(url('admin_users')) ?>">Abbrechen</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <div class="grid2">
    <form method="post" class="card form" action="<?= e(url('admin_user_edit', ['id' => $edit['id']])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_pw">
      <h2>Passwort zurücksetzen</h2>
      <div class="field">
        <label for="neues_passwort">Neues Passwort</label>
        <input type="text" id="neues_passwort" name="neues_passwort" placeholder="leer lassen = zufällig erzeugen">
      </div>
      <div><button class="btn btn--sec" type="submit"
              data-confirm="Passwort wirklich zurücksetzen?">Zurücksetzen</button></div>
    </form>

    <?php if (!$istIchSelbst): ?>
      <form method="post" class="card" action="<?= e(url('admin_user_edit', ['id' => $edit['id']])) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <h2>Benutzer löschen</h2>
        <p class="small muted">Wünsche und Aufgaben dieser Person bleiben erhalten, verlieren aber die Zuordnung.
          Zum vorübergehenden Sperren besser den Haken "Zugang aktiv" entfernen.</p>
        <button class="btn btn--danger" type="submit"
                data-confirm="Diesen Benutzer endgültig löschen?">Endgültig löschen</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
