/*
 * Zeichnet den QR-Code eines Blocks [data-qr] und bietet ihn zum Drucken
 * und als Bild an. Die Erzeugung übernimmt die Bibliothek qrcode.js.
 *
 * Beschriftung über Attribute am Block, mit den Vorgaben der Fahrzeugseite:
 *   data-qr-titel      Überschrift (sonst die h1 der Seite)
 *   data-qr-untertitel Zeile darunter (sonst "Standort melden")
 *   data-qr-hinweis    Text unter dem Code
 */
(function () {
  var block = document.querySelector('[data-qr]');
  if (!block || typeof qrcode !== 'function') {
    return;
  }
  var ziel = document.getElementById('qr-bild');
  var text = block.getAttribute('data-qr');

  // Fassung 0 heißt: die Bibliothek sucht die kleinste passende selbst
  var code = qrcode(0, 'M');
  code.addData(text);
  code.make();
  ziel.innerHTML = code.createSvgTag({ cellSize: 6, margin: 4, scalable: true });

  var h1 = document.querySelector('h1');
  var titel = block.getAttribute('data-qr-titel') || (h1 ? h1.textContent.trim() : 'Fahrzeug');
  var untertitel = block.getAttribute('data-qr-untertitel') || 'Standort melden';
  var hinweis = block.getAttribute('data-qr-hinweis')
    || 'Mit der Kamera scannen und dem Ortsverband melden, wo dieses Fahrzeug steht.';

  function esc(s) {
    return String(s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  var drucken = document.getElementById('qr-drucken');
  if (drucken) {
    drucken.addEventListener('click', function () {
      var fenster = window.open('', '_blank');
      if (!fenster) {
        window.print();
        return;
      }
      fenster.document.write('<!doctype html><html lang="de"><head><meta charset="utf-8">'
        + '<title>' + esc(untertitel) + '</title><style>'
        + 'body{font:15px/1.5 system-ui,sans-serif;text-align:center;margin:2cm 1cm;color:#111}'
        + 'h1{font-size:1.4rem;margin:0 0 .2rem}h2{font-size:1rem;font-weight:400;color:#475569;margin:0 0 1.2rem}'
        + 'svg{width:11cm;height:11cm}p{font-size:.85rem;color:#475569;margin-top:1rem}'
        + '.adresse{font-family:monospace;font-size:.8rem;word-break:break-all}'
        + '</style></head><body>'
        + '<h1>' + esc(titel) + '</h1>'
        + '<h2>' + esc(untertitel) + '</h2>'
        + ziel.innerHTML
        + '<p>' + esc(hinweis) + '</p>'
        + '<p class="adresse">' + esc(text) + '</p>'
        + '</body></html>');
      fenster.document.close();
      fenster.focus();
      fenster.print();
    });
  }

  // Als Bild (SVG) speichern – zum Weitergeben in Nachrichten und Aushängen
  var laden = document.getElementById('qr-laden');
  if (laden) {
    laden.addEventListener('click', function () {
      var svg = ziel.innerHTML.replace('<svg', '<svg xmlns="http://www.w3.org/2000/svg"');
      var blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'qr_' + titel.replace(/[^\wäöüÄÖÜß-]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 60) + '.svg';
      document.body.appendChild(a);
      a.click();
      setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    });
  }
})();
