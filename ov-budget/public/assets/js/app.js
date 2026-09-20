/* OV-Budget – kleine Helfer, bewusst ohne Framework */
(function () {
  'use strict';

  var euro = new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  /* Wischbare Navigation am Handy: den aktiven Eintrag in die Mitte holen.
     Bewusst über scrollLeft statt scrollIntoView – das würde die Seite mitverschieben. */
  var leiste = document.querySelector('.mainnav__inner');
  var aktiv = leiste && leiste.querySelector('.mainnav__item.is-active');
  if (aktiv && leiste.scrollWidth > leiste.clientWidth) {
    leiste.scrollLeft = aktiv.offsetLeft - (leiste.clientWidth - aktiv.offsetWidth) / 2;
  }

  function num(el) {
    if (!el) return 0;
    var v = String(el.value || '').replace(/\s|€/g, '');
    if (v.indexOf(',') > -1) v = v.replace(/\./g, '').replace(',', '.');
    var n = parseFloat(v);
    return isNaN(n) ? 0 : n;
  }

  /* Gesamtbetrag im Wunschformular automatisch berechnen */
  var anzahl = document.getElementById('f-anzahl');
  var einzel = document.getElementById('f-netto-einzel');
  var gesamt = document.getElementById('f-netto-gesamt');
  var hinweis = document.getElementById('f-gesamt-hinweis');

  if (anzahl && einzel && gesamt) {
    var touched = false;
    gesamt.addEventListener('input', function () { touched = true; });

    function recalc() {
      var total = num(anzahl) * num(einzel);
      if (!touched || !gesamt.value) {
        gesamt.value = total > 0 ? euro.format(total) : '';
      }
      if (hinweis) {
        hinweis.textContent = total > 0 ? 'Rechnerisch: ' + euro.format(total) + ' € netto' : '';
      }
    }
    anzahl.addEventListener('input', recalc);
    einzel.addEventListener('input', recalc);
    recalc();
  }

  /* Zielauswahl im Aufgabenformular umschalten */
  var targetType = document.getElementById('f-target-type');
  if (targetType) {
    var toggleTarget = function () {
      ['fachgruppe', 'funktion', 'user'].forEach(function (t) {
        var box = document.getElementById('target-' + t);
        if (box) box.hidden = (targetType.value !== t);
      });
    };
    targetType.addEventListener('change', toggleTarget);
    toggleTarget();
  }

  /* Serien: nur die Felder zeigen, die zum gewählten Rhythmus gehören */
  var regel = document.getElementById('f-regel');
  if (regel) {
    var einheit = document.getElementById('f-intervall-einheit');
    var zeigeRegel = function () {
      document.querySelectorAll('[data-regel]').forEach(function (feld) {
        feld.hidden = feld.getAttribute('data-regel').split(' ').indexOf(regel.value) === -1;
      });
      if (einheit) einheit.textContent = regel.value === 'woche' ? 'Woche(n)' : 'Monat(e)';
    };
    regel.addEventListener('change', zeigeRegel);
    zeigeRegel();
  }

  /* Filter beim Ändern direkt anwenden */
  document.querySelectorAll('form[data-autosubmit] select').forEach(function (sel) {
    sel.addEventListener('change', function () { sel.form.submit(); });
  });

  /* Verdeckte Werte (z. B. Cron-Aufruf mit Token): anzeigen und kopieren */
  document.querySelectorAll('[data-geheim]').forEach(function (el) {
    var verdeckt = el.textContent;
    var box = el.nextElementSibling;
    if (!box) return;
    var zeigen = box.querySelector('[data-geheim-zeigen]');
    var kopieren = box.querySelector('[data-geheim-kopieren]');
    if (zeigen) {
      zeigen.addEventListener('click', function () {
        var offen = el.textContent !== verdeckt;
        el.textContent = offen ? verdeckt : el.getAttribute('data-geheim');
        zeigen.textContent = offen ? 'Token anzeigen' : 'Token verbergen';
      });
    }
    if (kopieren && navigator.clipboard) {
      kopieren.addEventListener('click', function () {
        navigator.clipboard.writeText(el.getAttribute('data-geheim')).then(function () {
          var alt = kopieren.textContent;
          kopieren.textContent = 'Kopiert';
          setTimeout(function () { kopieren.textContent = alt; }, 1500);
        });
      });
    } else if (kopieren) {
      kopieren.hidden = true;
    }
  });

  /* Rückfrage vor dem Löschen */
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });

  /* Auswahl zeigen und vor zu großen Dateien warnen.
     Früher wurde die Auswahl bei Übergröße einfach geleert – am Handy sah es
     dann aus, als ließe sich gar kein Foto auswählen. */
  document.querySelectorAll('input[type=file][data-max-mb]').forEach(function (inp) {
    var hinweis = document.createElement('div');
    hinweis.className = 'small muted';
    hinweis.style.marginTop = '.3rem';
    inp.insertAdjacentElement('afterend', hinweis);

    function mb(bytes) { return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB'; }

    inp.addEventListener('change', function () {
      var grenze = parseFloat(inp.getAttribute('data-max-mb')) || 0;
      var dateien = inp.files || [];
      var gross = [];
      var summe = 0;
      for (var i = 0; i < dateien.length; i++) {
        summe += dateien[i].size;
        if (grenze > 0 && dateien[i].size > grenze * 1048576) {
          gross.push(dateien[i].name + ' (' + mb(dateien[i].size) + ')');
        }
      }
      var knopf = inp.form ? inp.form.querySelector('button[type=submit]') : null;

      if (!dateien.length) {
        hinweis.textContent = '';
        hinweis.className = 'small muted';
        if (knopf) { knopf.disabled = false; }
        return;
      }
      if (gross.length) {
        hinweis.className = 'small';
        hinweis.style.color = 'var(--bad)';
        hinweis.textContent = 'Zu groß (Grenze ' + grenze + ' MB): ' + gross.join(', ')
          + '. Die Grenze lässt sich in den Einstellungen erhöhen.';
        if (knopf) { knopf.disabled = true; }
        return;
      }
      hinweis.className = 'small muted';
      hinweis.style.color = '';
      hinweis.textContent = dateien.length + ' Datei(en) ausgewählt, zusammen ' + mb(summe) + '.';
      if (knopf) { knopf.disabled = false; }
    });
  });

  // Statusauswahl der Anwesenheit sofort sichtbar machen
  document.querySelectorAll('.chips').forEach(function (gruppe) {
    gruppe.addEventListener('change', function (ev) {
      if (!ev.target.matches('input[type=radio]')) return;
      gruppe.querySelectorAll('.chip--radio').forEach(function (chip) {
        chip.classList.toggle('is-on', chip.contains(ev.target));
      });
    });
  });
})();
