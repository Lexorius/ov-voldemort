/* OV-Budget – kleine Helfer, bewusst ohne Framework */
(function () {
  'use strict';

  var euro = new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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

  /* Filter beim Ändern direkt anwenden */
  document.querySelectorAll('form[data-autosubmit] select').forEach(function (sel) {
    sel.addEventListener('change', function () { sel.form.submit(); });
  });

  /* Rückfrage vor dem Löschen */
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });

  /* Dateigröße vor dem Upload prüfen */
  document.querySelectorAll('input[type=file][data-max-mb]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var max = parseFloat(inp.getAttribute('data-max-mb')) * 1024 * 1024;
      for (var i = 0; i < inp.files.length; i++) {
        if (inp.files[i].size > max) {
          alert('"' + inp.files[i].name + '" ist größer als ' + inp.getAttribute('data-max-mb') + ' MB.');
          inp.value = '';
          return;
        }
      }
    });
  });
})();
