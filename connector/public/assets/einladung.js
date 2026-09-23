/*
 * Rückmeldung auf eine Einladung – verschlüsselt für OV-Budget.
 *
 * Ablauf, genau wie bei den Standortmeldungen (melden.js):
 *   1. flüchtiges Schlüsselpaar erzeugen, gemeinsames Geheimnis mit dem
 *      öffentlichen Schlüssel von OV-Budget aushandeln (ECDH, P-256)
 *   2. daraus Schlüssel und Zufallswert ableiten (HKDF-SHA256)
 *   3. Angaben mit AES-GCM verschlüsseln und abschicken
 *
 * Der Connector bekommt nur den Geheimtext. Er weiß, zu welcher Veranstaltung
 * die Einladung gehört – wer geantwortet hat und was, erfährt nur OV-Budget.
 */
(function () {
  var box = document.getElementById('box');
  if (!box) { return; }

  var code = box.getAttribute('data-code');
  var basis = box.getAttribute('data-basis') || '';
  var serverSchluessel = box.getAttribute('data-schluessel');
  var formular = document.getElementById('formular');
  var meldung = document.getElementById('meldung');
  var knopf = document.getElementById('senden');
  var feldVertretung = document.getElementById('feld-vertretung');
  var feldBegleiter = document.getElementById('feld-begleiter');

  function sage(text, art) {
    meldung.textContent = text;
    meldung.className = 'meldung' + (art ? ' ' + art : '');
  }

  function bytesZuB64u(bytes) {
    var s = '';
    var b = new Uint8Array(bytes);
    for (var i = 0; i < b.length; i++) { s += String.fromCharCode(b[i]); }
    return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  function b64uZuBytes(s) {
    var rest = '='.repeat((4 - (s.length % 4)) % 4);
    var roh = atob((s + rest).replace(/-/g, '+').replace(/_/g, '/'));
    var b = new Uint8Array(roh.length);
    for (var i = 0; i < roh.length; i++) { b[i] = roh.charCodeAt(i); }
    return b;
  }

  if (!window.isSecureContext || !window.crypto || !window.crypto.subtle) {
    sage('Dieses Gerät kann hier nicht antworten. Nötig sind eine verschlüsselte Verbindung (https) '
      + 'und ein aktueller Browser.', 'schlecht');
    knopf.disabled = true;
    return;
  }

  // Zusatzfelder nur zeigen, wenn sie zur Auswahl passen
  function felderZeigen() {
    var gewaehlt = (formular.querySelector('input[name=status]:checked') || {}).value;
    if (feldVertretung) { feldVertretung.hidden = gewaehlt !== 'vertretung'; }
    if (feldBegleiter) { feldBegleiter.hidden = gewaehlt === 'absage'; }
  }
  Array.prototype.forEach.call(formular.querySelectorAll('input[name=status]'), function (r) {
    r.addEventListener('change', felderZeigen);
  });
  felderZeigen();

  async function verschluesseln(inhalt) {
    var empfaenger = await crypto.subtle.importKey(
      'raw', b64uZuBytes(serverSchluessel), { name: 'ECDH', namedCurve: 'P-256' }, false, []);
    var eigenes = await crypto.subtle.generateKey({ name: 'ECDH', namedCurve: 'P-256' }, false, ['deriveBits']);
    var geheim = await crypto.subtle.deriveBits({ name: 'ECDH', public: empfaenger }, eigenes.privateKey, 256);
    var eigenerPunkt = new Uint8Array(await crypto.subtle.exportKey('raw', eigenes.publicKey));

    var salz = crypto.getRandomValues(new Uint8Array(16));
    var basisSchluessel = await crypto.subtle.importKey('raw', geheim, 'HKDF', false, ['deriveBits']);
    var info = new TextEncoder().encode('OV-Budget Standort v1');
    var abgeleitet = new Uint8Array(await crypto.subtle.deriveBits(
      { name: 'HKDF', hash: 'SHA-256', salt: salz, info: info }, basisSchluessel, 352));
    var schluessel = await crypto.subtle.importKey('raw', abgeleitet.slice(0, 32), 'AES-GCM', false, ['encrypt']);
    var nonce = abgeleitet.slice(32, 44);

    var geheimtext = new Uint8Array(await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: nonce }, schluessel, new TextEncoder().encode(JSON.stringify(inhalt))));

    var paket = new Uint8Array(1 + 65 + 16 + geheimtext.length);
    paket[0] = 1;                       // Fassung des Formats
    paket.set(eigenerPunkt, 1);
    paket.set(salz, 66);
    paket.set(geheimtext, 82);
    return bytesZuB64u(paket);
  }

  async function senden() {
    var status = (formular.querySelector('input[name=status]:checked') || {}).value || 'zusage';
    var begleiterFeld = document.getElementById('begleiter');
    var vertretungFeld = document.getElementById('vertretung');
    var kommentarFeld = document.getElementById('kommentar');

    if (status === 'vertretung' && vertretungFeld && vertretungFeld.value.trim() === '') {
      throw new Error('Bitte tragen Sie ein, wer für Sie kommt.');
    }

    var daten = await verschluesseln({
      status: status,
      begleiter: status === 'absage' || !begleiterFeld ? 0 : parseInt(begleiterFeld.value, 10) || 0,
      vertretung: status === 'vertretung' && vertretungFeld ? vertretungFeld.value.trim().slice(0, 150) : '',
      kommentar: kommentarFeld ? kommentarFeld.value.trim().slice(0, 2000) : '',
      zeit: Math.floor(Date.now() / 1000)
    });

    var antwort = await fetch(basis + 'index.php?p=rueckmeldung', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: code, daten: daten })
    });
    var ergebnis = await antwort.json().catch(function () { return {}; });
    if (!antwort.ok || !ergebnis.ok) {
      throw new Error(ergebnis.fehler || ('Server antwortete mit ' + antwort.status));
    }
    return status;
  }

  formular.addEventListener('submit', function (ereignis) {
    ereignis.preventDefault();
    knopf.disabled = true;
    sage('Wird gesendet …');
    senden().then(function (status) {
      knopf.disabled = false;
      knopf.textContent = 'Rückmeldung ändern';
      sage(status === 'absage'
        ? 'Danke, die Absage ist angekommen. Sie können sie hier jederzeit ändern.'
        : 'Danke, die Rückmeldung ist angekommen. Sie können sie hier jederzeit ändern.', 'gut');
    }).catch(function (fehler) {
      knopf.disabled = false;
      sage('Hat nicht geklappt: ' + ((fehler && fehler.message) || 'unbekannter Grund') + '.', 'schlecht');
    });
  });
})();
