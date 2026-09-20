/*
 * Service Worker für Web Push.
 *
 * Er tut bewusst nichts weiter: kein Zwischenspeichern von Seiten, nur
 * Benachrichtigungen annehmen und beim Antippen die passende Seite öffnen.
 */
self.addEventListener('install', function (ev) {
  self.skipWaiting();
});

self.addEventListener('activate', function (ev) {
  ev.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (ev) {
  var daten = { titel: 'OV-Budget', text: '', url: '' };
  if (ev.data) {
    try {
      daten = Object.assign(daten, ev.data.json());
    } catch (e) {
      daten.text = ev.data.text();
    }
  }
  ev.waitUntil(self.registration.showNotification(daten.titel || 'OV-Budget', {
    body: daten.text || '',
    tag: daten.tag || undefined,
    data: { url: daten.url || '' }
  }));
});

self.addEventListener('notificationclick', function (ev) {
  ev.notification.close();
  var ziel = (ev.notification.data && ev.notification.data.url) || '';
  if (!ziel) {
    return;
  }
  ev.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (fenster) {
    for (var i = 0; i < fenster.length; i++) {
      // Ist die Anwendung schon offen, dort weiterarbeiten
      if (fenster[i].url.indexOf(ziel) !== -1 && 'focus' in fenster[i]) {
        return fenster[i].focus();
      }
    }
    return self.clients.openWindow(ziel);
  }));
});
