/*
 * Web Push im Browser an- und abmelden.
 *
 * Die Seite liefert über data-Attribute den öffentlichen VAPID-Schlüssel und
 * die Adressen; der Rest läuft hier. Ohne Unterstützung im Browser bleibt der
 * Bereich mit einem Hinweis stehen.
 */
(function () {
  var box = document.getElementById('push-box');
  if (!box) {
    return;
  }

  var knopfAn = document.getElementById('push-an');
  var knopfAus = document.getElementById('push-aus');
  var meldung = document.getElementById('push-meldung');
  var schluessel = box.getAttribute('data-vapid') || '';
  var zielAn = box.getAttribute('data-url-an');
  var zielAus = box.getAttribute('data-url-aus');
  var csrf = box.getAttribute('data-csrf');

  function sage(text, art) {
    meldung.textContent = text;
    meldung.className = 'small ' + (art === 'fehler' ? 'alert alert--warn' : 'muted');
  }

  function zeige(angemeldet) {
    if (knopfAn) { knopfAn.hidden = angemeldet; }
    if (knopfAus) { knopfAus.hidden = !angemeldet; }
  }

  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    sage('Dieser Browser kann keine Push-Nachrichten empfangen. '
      + 'Auf dem iPhone hilft es, die Seite zum Home-Bildschirm hinzuzufügen.', 'fehler');
    zeige(false);
    if (knopfAn) { knopfAn.hidden = true; }
    return;
  }

  function schluesselAlsBytes(b64) {
    var rest = '='.repeat((4 - (b64.length % 4)) % 4);
    var roh = window.atob((b64 + rest).replace(/-/g, '+').replace(/_/g, '/'));
    var bytes = new Uint8Array(roh.length);
    for (var i = 0; i < roh.length; i++) {
      bytes[i] = roh.charCodeAt(i);
    }
    return bytes;
  }

  function senden(ziel, daten) {
    var form = new FormData();
    form.append('_csrf', csrf);
    form.append('abo', JSON.stringify(daten));
    form.append('geraet', navigator.userAgent.slice(0, 150));
    return fetch(ziel, { method: 'POST', body: form, credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) {
        throw new Error('Server antwortete mit ' + r.status);
      }
      return r.json();
    });
  }

  // "sw.js" liegt neben der Anwendung – der Pfad gilt auch hinter dem Ingress
  var swPfad = new URL('sw.js', window.location.href.split('?')[0]).pathname;

  navigator.serviceWorker.register(swPfad).then(function (reg) {
    return reg.pushManager.getSubscription().then(function (abo) {
      zeige(!!abo);
      if (!abo) {
        sage('Dieser Browser bekommt noch keine Benachrichtigungen.');
      } else {
        sage('Dieser Browser ist angemeldet.');
      }

      if (knopfAn) {
        knopfAn.addEventListener('click', function () {
          knopfAn.disabled = true;
          sage('Bitte die Nachfrage des Browsers bestätigen …');
          Notification.requestPermission().then(function (erlaubnis) {
            if (erlaubnis !== 'granted') {
              knopfAn.disabled = false;
              sage('Ohne Erlaubnis geht es nicht. Sie lässt sich in den Browser-Einstellungen '
                + 'für diese Seite wieder setzen.', 'fehler');
              return;
            }
            return reg.pushManager.subscribe({
              userVisibleOnly: true,
              applicationServerKey: schluesselAlsBytes(schluessel)
            }).then(function (neu) {
              return senden(zielAn, neu.toJSON());
            }).then(function () {
              knopfAn.disabled = false;
              zeige(true);
              sage('Angemeldet. Zum Prüfen unten eine Testnachricht schicken.');
            });
          }).catch(function (e) {
            knopfAn.disabled = false;
            sage('Anmeldung fehlgeschlagen: ' + e.message, 'fehler');
          });
        });
      }

      if (knopfAus) {
        knopfAus.addEventListener('click', function () {
          knopfAus.disabled = true;
          reg.pushManager.getSubscription().then(function (vorhanden) {
            if (!vorhanden) {
              return null;
            }
            var endpoint = vorhanden.endpoint;
            return vorhanden.unsubscribe().then(function () {
              return senden(zielAus, { endpoint: endpoint });
            });
          }).then(function () {
            knopfAus.disabled = false;
            zeige(false);
            sage('Dieser Browser bekommt keine Benachrichtigungen mehr.');
          }).catch(function (e) {
            knopfAus.disabled = false;
            sage('Abmelden fehlgeschlagen: ' + e.message, 'fehler');
          });
        });
      }
    });
  }).catch(function (e) {
    sage('Der Service Worker ließ sich nicht einrichten: ' + e.message
      + ' (nötig ist eine Verbindung über HTTPS).', 'fehler');
  });
})();
