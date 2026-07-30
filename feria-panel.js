/* =========================================================================
   Panel de Feria — lógica del operador.

   Todo vive en el navegador: el diseño en memoria, las fichas en IndexedDB y
   el catálogo en el Cache API. El panel debe seguir funcionando con el WiFi
   caído, que es el escenario normal en una feria.
   ========================================================================= */

(function () {
    'use strict';

    var cfg = window.feriaConfig || {};

    var DB_NAME  = 'collar_feria';
    var DB_STORE = 'fichas';
    var MAX_FALLBACK = 9;

    /* ---------------------------------------------------------------------
     * Estado
     * ------------------------------------------------------------------ */

    var state = {
        editingId:   null,
        size:        'S',
        collarColor: cfg.collarColors ? cfg.collarColors[1] : { slug: 'collar2', label: 'Negro', hex: '#000000' },
        charmColor:  '#FF64DC',
        charms:      []
    };

    var fichas = [];
    var db = null;

    /* ---------------------------------------------------------------------
     * Utilidades
     * ------------------------------------------------------------------ */

    function $(id) { return document.getElementById(id); }

    function money(n) {
        return '$' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function maxCharms() {
        return (cfg.maxBySize && cfg.maxBySize[state.size]) || MAX_FALLBACK;
    }

    function isSoldOut(hex) {
        return (cfg.soldOut || []).indexOf(hex) !== -1;
    }

    /**
     * Equivalente exacto de rawurlencode() de PHP. Las URLs que arma el navegador
     * deben coincidir carácter por carácter con las que precarga el service worker,
     * o la caché falla justo cuando no hay internet. encodeURIComponent deja pasar
     * !'()* sin codificar; rawurlencode no.
     */
    function rawEncode(str) {
        return encodeURIComponent(str).replace(/[!'()*]/g, function (c) {
            return '%' + c.charCodeAt(0).toString(16).toUpperCase();
        });
    }

    /** Codifica solo el nombre del archivo: los assets tienen espacios en el nombre. */
    function assetUrl(path, file) {
        return cfg.assetsUrl + path + '/' + rawEncode(file);
    }

    function charmSrc(charm) {
        if (charm.isEmoji) {
            return assetUrl('emojis/' + charm.colorHex.replace('#', ''), charm.value);
        }
        if (charm.isGlitter) {
            var kind = charm.colorHex === '#GOLD' ? 'gold' : 'silver';
            return assetUrl('charms/glitter/' + kind, charm.value + '_128.png');
        }
        return null;
    }

    /** Nombre legible de un charm, para la ficha de producción. */
    function charmLabel(charm) {
        var colorName = 'color';
        (cfg.charmColors || []).forEach(function (c) {
            if (c.hex === charm.colorHex) colorName = c.label;
        });

        if (charm.isEmoji) {
            // 'PAGINA WEB COLLARES-31.png' -> 'Emoji 31'. El número es el identificador
            // real del catálogo impreso, así producción no tiene que adivinar.
            var num = charm.value.replace(/[^0-9]/g, '');
            return 'Emoji ' + num + ' (' + colorName + ')';
        }
        return charm.value + ' (' + colorName + ')';
    }

    function calcPrice(size, count) {
        if (!cfg.basePrices || count < cfg.minCharms) return 0;
        var max = (cfg.maxBySize && cfg.maxBySize[size]) || MAX_FALLBACK;
        if (count > max) return 0;
        var extras = Math.max(0, count - cfg.baseCharms);
        return cfg.basePrices[size] + (extras * cfg.extraCharm[size]);
    }

    function collarColorAllowed(color) {
        // XS tiene stock limitado de colores de collar, igual que en la tienda.
        if (state.size !== 'XS') return true;
        return (cfg.xsColors || []).indexOf(color.label) !== -1;
    }

    var toastTimer = null;
    function toast(msg, isError) {
        var el = $('feria-toast');
        el.textContent = msg;
        el.className = 'feria-toast' + (isError ? ' is-error' : '');
        el.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.hidden = true; }, 2600);
    }

    /* ---------------------------------------------------------------------
     * IndexedDB
     * ------------------------------------------------------------------ */

    function openDB() {
        return new Promise(function (resolve, reject) {
            var req = indexedDB.open(DB_NAME, 1);
            req.onupgradeneeded = function (e) {
                var d = e.target.result;
                if (!d.objectStoreNames.contains(DB_STORE)) {
                    d.createObjectStore(DB_STORE, { keyPath: 'id' });
                }
            };
            req.onsuccess = function (e) { resolve(e.target.result); };
            req.onerror   = function () { reject(req.error); };
        });
    }

    function dbRun(mode, fn) {
        return new Promise(function (resolve, reject) {
            if (!db) return reject(new Error('sin base de datos'));
            var tx    = db.transaction(DB_STORE, mode);
            var store = tx.objectStore(DB_STORE);
            var out   = fn(store);
            tx.oncomplete = function () { resolve(out && out.result !== undefined ? out.result : out); };
            tx.onerror    = function () { reject(tx.error); };
        });
    }

    function loadFichas() {
        return dbRun('readonly', function (s) { return s.getAll(); }).then(function (rows) {
            fichas = (rows || []).sort(function (a, b) { return b.createdAt - a.createdAt; });
            renderList();
        });
    }

    /* ---------------------------------------------------------------------
     * Construcción de la interfaz
     * ------------------------------------------------------------------ */

    function buildSizes() {
        var row = $('feria-size-row');
        row.innerHTML = '';
        (cfg.sizes || ['XS', 'S', 'M', 'L', 'XL']).forEach(function (size) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'feria-size-btn' + (size === state.size ? ' is-active' : '');
            b.textContent = size;
            b.addEventListener('click', function () { setSize(size); });
            row.appendChild(b);
        });
    }

    function buildCollarColors() {
        var row = $('feria-collar-colors');
        row.innerHTML = '';
        (cfg.collarColors || []).forEach(function (color) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'feria-swatch';
            b.style.background = color.hex;
            b.title = color.label;
            b.setAttribute('aria-label', color.label);
            b.dataset.slug = color.slug;
            b.addEventListener('click', function () { setCollarColor(color); });
            row.appendChild(b);
        });
        paintCollarColors();
    }

    function paintCollarColors() {
        var row = $('feria-collar-colors');
        Array.prototype.forEach.call(row.children, function (b) {
            var color = (cfg.collarColors || []).filter(function (c) { return c.slug === b.dataset.slug; })[0];
            if (!color) return;
            var ok = collarColorAllowed(color);
            b.classList.toggle('is-disabled', !ok);
            b.classList.toggle('is-active', color.slug === state.collarColor.slug);
        });
    }

    function buildCharmColors() {
        var row = $('feria-charm-colors');
        row.innerHTML = '';
        (cfg.charmColors || []).forEach(function (color) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'feria-charm-color';
            b.title = color.label + (isSoldOut(color.hex) ? ' — agotado' : '');
            b.setAttribute('aria-label', color.label);
            b.dataset.hex = color.hex;

            if (color.hex === '#GOLD') {
                b.style.backgroundImage = "url('" + assetUrl('texturas/dorada', 'PAGINA WEB COLLARES-11.png') + "')";
            } else if (color.hex === '#SILVER') {
                b.style.backgroundImage = "url('" + assetUrl('texturas/plateada', 'PAGINA WEB COLLARES-10.png') + "')";
            } else {
                b.style.background = color.hex;
            }

            if (isSoldOut(color.hex)) {
                b.classList.add('is-disabled');
            } else {
                b.addEventListener('click', function () { setCharmColor(color.hex); });
            }
            row.appendChild(b);
        });
        paintCharmColors();
    }

    function paintCharmColors() {
        Array.prototype.forEach.call($('feria-charm-colors').children, function (b) {
            b.classList.toggle('is-active', b.dataset.hex === state.charmColor);
        });
    }

    function buildKeyboard() {
        var pad = $('feria-keyboard');
        pad.innerHTML = '';
        (cfg.glitter || []).forEach(function (ch) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'feria-key';
            b.textContent = ch;
            b.addEventListener('click', function () { addCharm(ch, false); });
            pad.appendChild(b);
        });
        paintKeyboard();
    }

    /** Las teclas se pintan del color de charm activo para que el operador vea el resultado. */
    function paintKeyboard() {
        var isGold   = state.charmColor === '#GOLD';
        var isSilver = state.charmColor === '#SILVER';

        Array.prototype.forEach.call($('feria-keyboard').children, function (b) {
            b.style.backgroundImage = '';
            b.style.color = '';
            b.style.webkitTextFillColor = '';

            if (isGold || isSilver) {
                var tex = isGold
                    ? assetUrl('texturas/dorada', 'PAGINA WEB COLLARES-11.png')
                    : assetUrl('texturas/plateada', 'PAGINA WEB COLLARES-10.png');
                b.style.backgroundImage = "url('" + tex + "')";
                b.style.color = '#fff';
                b.style.textShadow = '0 1px 3px rgba(0,0,0,.6)';
            } else {
                b.style.color = state.charmColor;
                b.style.textShadow = 'none';
            }
        });
    }

    function buildEmojis() {
        var grid = $('feria-emoji-grid');
        grid.innerHTML = '';

        var files = (cfg.emojis || {})[state.charmColor] || [];

        if (!files.length) {
            grid.innerHTML = '<p class="feria-empty">No hay emojis en este color.</p>';
            return;
        }

        files.forEach(function (file) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'feria-emoji';

            var img = document.createElement('img');
            img.src = assetUrl('emojis/' + state.charmColor.replace('#', ''), file);
            img.alt = '';
            img.loading = 'lazy';
            b.appendChild(img);

            b.addEventListener('click', function () { addCharm(file, true); });
            grid.appendChild(b);
        });
    }

    /* ---------------------------------------------------------------------
     * Acciones sobre el diseño
     * ------------------------------------------------------------------ */

    function setSize(size) {
        state.size = size;

        Array.prototype.forEach.call($('feria-size-row').children, function (b) {
            b.classList.toggle('is-active', b.textContent === size);
        });

        // Si la talla nueva admite menos charms, recortar y avisar.
        var max = maxCharms();
        if (state.charms.length > max) {
            state.charms = state.charms.slice(0, max);
            toast('La talla ' + size + ' admite máximo ' + max + ' charms.', true);
        }

        // XS restringe los colores de collar: si el actual no aplica, mover al primero válido.
        if (!collarColorAllowed(state.collarColor)) {
            var valid = (cfg.collarColors || []).filter(collarColorAllowed)[0];
            if (valid) {
                state.collarColor = valid;
                toast('Talla XS: color cambiado a ' + valid.label + '.', true);
            }
        }

        paintCollarColors();
        renderAll();
    }

    function setCollarColor(color) {
        state.collarColor = color;
        paintCollarColors();
        renderPreview();
    }

    function setCharmColor(hex) {
        state.charmColor = hex;
        paintCharmColors();
        paintKeyboard();
        buildEmojis();
    }

    function addCharm(value, isEmoji) {
        if (state.charms.length >= maxCharms()) {
            toast('Máximo ' + maxCharms() + ' charms para la talla ' + state.size + '.', true);
            return;
        }

        var isGlitter = !isEmoji && (state.charmColor === '#GOLD' || state.charmColor === '#SILVER');

        state.charms.push({
            value:     value,
            isEmoji:   !!isEmoji,
            colorHex:  state.charmColor,
            isGlitter: isGlitter
        });

        renderAll();
    }

    function removeCharm(index) {
        state.charms.splice(index, 1);
        renderAll();
    }

    function resetDesign() {
        state.editingId = null;
        state.charms = [];
        ['feria-pet', 'feria-name', 'feria-phone', 'feria-notes'].forEach(function (id) {
            $(id).value = '';
        });
        $('feria-save').textContent = 'Guardar ficha';
        renderAll();
    }

    /* ---------------------------------------------------------------------
     * Render
     * ------------------------------------------------------------------ */

    function charmNode(charm, onClick) {
        var el = document.createElement('div');
        el.className = 'feria-charm';

        var src = charmSrc(charm);
        if (src) {
            var img = document.createElement('img');
            img.src = src;
            img.alt = charm.value;
            // Si falta la imagen glitter, caer a texto en vez de dejar un hueco.
            img.addEventListener('error', function () {
                el.textContent = charm.value;
                el.style.color = charm.colorHex === '#GOLD' ? '#FFD700' : '#C0C0C0';
            });
            el.appendChild(img);
        } else {
            el.textContent = charm.value;
            el.style.color = charm.colorHex;
        }

        if (onClick) el.addEventListener('click', onClick);
        return el;
    }

    function renderPreview() {
        var band = $('feria-preview-band');
        var host = $('feria-preview-charms');

        band.style.background = state.collarColor.hex;
        host.innerHTML = '';

        state.charms.forEach(function (charm, i) {
            host.appendChild(charmNode(charm, function () { removeCharm(i); }));
        });

        $('feria-preview-empty').style.display = state.charms.length ? 'none' : '';
    }

    function renderSummary() {
        var count = state.charms.length;
        var max   = maxCharms();
        var price = calcPrice(state.size, count);

        $('feria-sum-size').textContent   = state.size;
        $('feria-sum-color').textContent  = state.collarColor.label;
        $('feria-sum-charms').textContent = count + ' / ' + max;
        $('feria-sum-price').textContent  = price > 0 ? money(price) : '—';

        var note = $('feria-price-note');
        if (count < cfg.minCharms) {
            note.textContent = 'Faltan ' + (cfg.minCharms - count) + ' charms para el mínimo de ' + cfg.minCharms + '.';
            note.className = 'feria-price-note is-warn';
        } else if (count > cfg.baseCharms) {
            var extras = count - cfg.baseCharms;
            note.textContent = 'Incluye ' + cfg.baseCharms + ' charms + ' + extras +
                ' extra a ' + money(cfg.extraCharm[state.size]) + ' c/u.';
            note.className = 'feria-price-note';
        } else {
            note.textContent = 'Incluye ' + cfg.baseCharms + ' charms.';
            note.className = 'feria-price-note';
        }

        var limit = $('feria-limit');
        limit.textContent = count >= max ? 'Límite alcanzado' : (max - count) + ' disponibles';
        limit.classList.toggle('is-full', count >= max);

        var locked = count >= max;
        $('feria-panel-letters').classList.toggle('is-locked', locked);
        $('feria-panel-emojis').classList.toggle('is-locked', locked);
    }

    function renderAll() {
        renderPreview();
        renderSummary();
    }

    /* ---------------------------------------------------------------------
     * Guardar / editar fichas
     * ------------------------------------------------------------------ */

    function currentFicha() {
        var count = state.charms.length;

        // Al corregir una ficha se conserva su hora original: es la hora de la venta,
        // no la de la corrección, y es lo que ordena la lista y sale en el PNG.
        var previous = state.editingId
            ? fichas.filter(function (x) { return x.id === state.editingId; })[0]
            : null;

        return {
            id:        state.editingId || ('F' + Date.now().toString(36).toUpperCase()),
            createdAt: previous ? previous.createdAt : Date.now(),
            evento:    cfg.evento || '',
            size:      state.size,
            collarColor: state.collarColor,
            charms:    state.charms.slice(),
            price:     calcPrice(state.size, count),
            completa:  count >= cfg.minCharms,
            customer: {
                pet:   $('feria-pet').value.trim(),
                name:  $('feria-name').value.trim(),
                phone: $('feria-phone').value.trim(),
                notes: $('feria-notes').value.trim()
            }
        };
    }

    function saveFicha() {
        if (!state.charms.length) {
            toast('Arma el collar antes de guardar.', true);
            return;
        }

        var ficha = currentFicha();

        dbRun('readwrite', function (s) { return s.put(ficha); })
            .then(loadFichas)
            .then(function () {
                var msg = state.editingId ? 'Ficha actualizada' : 'Ficha guardada';
                if (!ficha.completa) msg += ' — diseño incompleto';
                toast(msg);
                resetDesign();
            })
            .catch(function () { toast('No se pudo guardar la ficha.', true); });
    }

    function editFicha(id) {
        var f = fichas.filter(function (x) { return x.id === id; })[0];
        if (!f) return;

        state.editingId   = f.id;
        state.size        = f.size;
        state.collarColor = f.collarColor;
        state.charms      = f.charms.slice();

        $('feria-pet').value   = f.customer.pet || '';
        $('feria-name').value  = f.customer.name || '';
        $('feria-phone').value = f.customer.phone || '';
        $('feria-notes').value = f.customer.notes || '';
        $('feria-save').textContent = 'Actualizar ficha';

        buildSizes();
        paintCollarColors();
        renderAll();
        closeDrawers();
        toast('Editando ficha ' + f.id);
    }

    function duplicateFicha(id) {
        var f = fichas.filter(function (x) { return x.id === id; })[0];
        if (!f) return;

        state.editingId   = null;
        state.size        = f.size;
        state.collarColor = f.collarColor;
        state.charms      = f.charms.slice();

        $('feria-pet').value   = '';
        $('feria-name').value  = f.customer.name || '';
        $('feria-phone').value = f.customer.phone || '';
        $('feria-notes').value = '';
        $('feria-save').textContent = 'Guardar ficha';

        buildSizes();
        paintCollarColors();
        renderAll();
        closeDrawers();
        toast('Diseño copiado — cambia el nombre del peludo');
    }

    function deleteFicha(id) {
        if (!window.confirm('¿Borrar esta ficha? No se puede deshacer.')) return;
        dbRun('readwrite', function (s) { return s.delete(id); })
            .then(loadFichas)
            .then(function () { toast('Ficha borrada'); });
    }

    function renderList() {
        var host = $('feria-list');
        $('feria-count').textContent = fichas.length;

        if (!fichas.length) {
            host.innerHTML = '<p class="feria-empty">Todavía no hay fichas guardadas.<br>' +
                'Las que guardes quedan en esta tablet, incluso sin internet.</p>';
            return;
        }

        host.innerHTML = '';

        fichas.forEach(function (f) {
            var card = document.createElement('div');
            card.className = 'feria-card';

            var title = f.customer.pet || f.customer.name || 'Sin nombre';
            var time  = new Date(f.createdAt).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });

            var top = document.createElement('div');
            top.className = 'feria-card-top';
            top.innerHTML = '<span class="feria-card-title"></span><span class="feria-card-time"></span>';
            top.children[0].textContent = title;
            top.children[1].textContent = time;
            card.appendChild(top);

            var band = document.createElement('div');
            band.className = 'feria-card-band';
            band.style.background = f.collarColor.hex;
            f.charms.forEach(function (c) { band.appendChild(charmNode(c, null)); });
            card.appendChild(band);

            var meta = document.createElement('div');
            meta.className = 'feria-card-meta';
            meta.textContent = f.size + ' · ' + f.collarColor.label + ' · ' + f.charms.length + ' charms · ' +
                (f.completa ? money(f.price) : 'INCOMPLETA') +
                (f.customer.phone ? ' · ' + f.customer.phone : '');
            card.appendChild(meta);

            var actions = document.createElement('div');
            actions.className = 'feria-card-actions';

            [
                ['Abrir',    function () { editFicha(f.id); },      ''],
                ['Copiar',   function () { duplicateFicha(f.id); }, ''],
                ['Borrar',   function () { deleteFicha(f.id); },    'is-danger']
            ].forEach(function (def) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'feria-card-btn ' + def[2];
                b.textContent = def[0];
                b.addEventListener('click', def[1]);
                actions.appendChild(b);
            });

            card.appendChild(actions);
            host.appendChild(card);
        });
    }

    /* ---------------------------------------------------------------------
     * Ficha en PNG (canvas)
     * ------------------------------------------------------------------ */

    function loadImage(src) {
        return new Promise(function (resolve) {
            var img = new Image();
            img.onload  = function () { resolve(img); };
            img.onerror = function () { resolve(null); };
            img.src = src;
        });
    }

    /**
     * Recorta un texto al ancho disponible. fillText no ajusta ni corta: sin esto,
     * un nombre largo se sale del lienzo y desaparece sin dejar rastro.
     */
    function fitText(ctx, text, maxWidth) {
        var s = String(text || '');
        if (ctx.measureText(s).width <= maxWidth) return s;

        while (s.length > 1 && ctx.measureText(s + '…').width > maxWidth) {
            s = s.slice(0, -1);
        }
        return s + '…';
    }

    /** Parte un texto en líneas que quepan en maxWidth, respetando las palabras. */
    function wrapText(ctx, text, maxWidth, maxLines) {
        var words = String(text || '').split(/\s+/).filter(Boolean);
        var lines = [];
        var line = '';

        words.forEach(function (word) {
            var probe = line ? line + ' ' + word : word;
            if (ctx.measureText(probe).width <= maxWidth) {
                line = probe;
            } else {
                if (line) lines.push(line);
                line = word;
            }
        });
        if (line) lines.push(line);

        if (lines.length > maxLines) {
            lines = lines.slice(0, maxLines);
            lines[maxLines - 1] = fitText(ctx, lines[maxLines - 1] + ' …', maxWidth);
        }
        return lines;
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y,     x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x,     y + h, r);
        ctx.arcTo(x,     y + h, x,     y,     r);
        ctx.arcTo(x,     y,     x + w, y,     r);
        ctx.closePath();
    }

    /**
     * Dibuja la ficha. Sirve a dos públicos a la vez: el cliente, que quiere ver
     * su collar, y producción, que necesita la lista exacta de charms en orden.
     */
    function renderFichaPNG(f) {
        var canvas = $('feria-canvas');
        var ctx    = canvas.getContext('2d');
        var W = canvas.width;

        var srcs = f.charms.map(charmSrc);

        return Promise.all(srcs.map(function (s) { return s ? loadImage(s) : Promise.resolve(null); }))
            .then(function (images) {

                // La altura se calcula según el contenido. Con 9 charms y notas largas
                // el alto fijo de 1600 se quedaba corto y la cotización quedaba fuera
                // del lienzo, que es justo el dato que el cliente mira.
                var noteLines = 0;
                if (f.customer.notes) {
                    ctx.font = 'bold 29px Helvetica, Arial, sans-serif';
                    noteLines = wrapText(ctx, f.customer.notes, W - 60 - 360, 3).length;
                }

                var H = 520                                        // cabecera + collar
                      + 58 + 144                                   // specs
                      + Math.max(48, f.charms.length * 38 + 14)    // lista en orden
                      + 58 + 144                                   // cliente
                      + (noteLines ? 48 + (noteLines - 1) * 36 : 0)
                      + 12 + 110                                   // caja de cotización
                      + 110;                                       // pie

                H = Math.max(1600, H);

                // Asignar width/height reinicia el contexto; por eso va antes de dibujar.
                canvas.height = H;

                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, W, H);

                // --- Cabecera ---
                ctx.fillStyle = '#17181c';
                ctx.fillRect(0, 0, W, 150);

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 46px Helvetica, Arial, sans-serif';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'alphabetic';
                ctx.fillText('Ficha de collar', 60, 76);

                ctx.fillStyle = '#9ca3af';
                ctx.font = '26px Helvetica, Arial, sans-serif';
                var head = [f.evento, new Date(f.createdAt).toLocaleDateString('es-CO')]
                    .filter(Boolean).join('  ·  ');
                // Se recorta para no chocar con el ID, que va alineado a la derecha.
                ctx.fillText(fitText(ctx, head, 900), 60, 118);

                ctx.textAlign = 'right';
                ctx.fillStyle = '#6b7280';
                ctx.font = 'bold 28px Helvetica, Arial, sans-serif';
                ctx.fillText(f.id, W - 60, 118);
                ctx.textAlign = 'left';

                // --- Collar ---
                var bandH = 150;
                var bandY = 260;
                var slot  = 120;
                var bandW = Math.max(560, f.charms.length * slot + 120);
                if (bandW > W - 120) bandW = W - 120;
                var bandX = (W - bandW) / 2;

                ctx.fillStyle = f.collarColor.hex;
                roundRect(ctx, bandX, bandY, bandW, bandH, bandH / 2);
                ctx.fill();

                var usable = bandW - 100;
                var step   = f.charms.length ? Math.min(slot, usable / f.charms.length) : 0;
                var startX = W / 2 - (step * (f.charms.length - 1)) / 2;
                var midY   = bandY + bandH / 2;

                f.charms.forEach(function (charm, i) {
                    var cx = startX + i * step;
                    var img = images[i];

                    if (img) {
                        var h = 96;
                        var w = img.width * (h / img.height);
                        ctx.drawImage(img, cx - w / 2, midY - h / 2, w, h);
                    } else {
                        ctx.fillStyle = charm.colorHex === '#GOLD' ? '#FFD700'
                            : charm.colorHex === '#SILVER' ? '#C0C0C0'
                            : charm.colorHex;
                        ctx.font = 'bold 84px Helvetica, Arial, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(charm.value, cx, midY + 2);
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'alphabetic';
                    }
                });

                // --- Datos ---
                var y = 520;

                function section(label) {
                    ctx.fillStyle = '#9ca3af';
                    ctx.font = 'bold 22px Helvetica, Arial, sans-serif';
                    ctx.fillText(label.toUpperCase(), 60, y);
                    y += 14;
                    ctx.strokeStyle = '#e5e7eb';
                    ctx.lineWidth = 2;
                    ctx.beginPath();
                    ctx.moveTo(60, y);
                    ctx.lineTo(W - 60, y);
                    ctx.stroke();
                    y += 44;
                }

                var VALUE_X = 360;
                var VALUE_W = W - 60 - VALUE_X;

                function row(label, value) {
                    ctx.fillStyle = '#6b7280';
                    ctx.font = '27px Helvetica, Arial, sans-serif';
                    ctx.fillText(label, 60, y);

                    ctx.fillStyle = '#17181c';
                    ctx.font = 'bold 29px Helvetica, Arial, sans-serif';
                    ctx.fillText(fitText(ctx, value || '—', VALUE_W), VALUE_X, y);
                    y += 48;
                }

                /** Como row(), pero reparte el valor en varias líneas en vez de recortarlo. */
                function rowMultiline(label, value, maxLines) {
                    ctx.fillStyle = '#6b7280';
                    ctx.font = '27px Helvetica, Arial, sans-serif';
                    ctx.fillText(label, 60, y);

                    ctx.fillStyle = '#17181c';
                    ctx.font = 'bold 29px Helvetica, Arial, sans-serif';

                    var lines = wrapText(ctx, value, VALUE_W, maxLines || 3);
                    lines.forEach(function (line, i) {
                        ctx.fillText(line, VALUE_X, y + (i * 36));
                    });
                    y += 48 + Math.max(0, lines.length - 1) * 36;
                }

                section('Especificaciones');
                row('Talla',  f.size);
                row('Color',  f.collarColor.label);
                row('Charms', f.charms.length + ' unidades');

                // Lista en orden: es lo que producción necesita para armarlo sin ambigüedad.
                ctx.fillStyle = '#6b7280';
                ctx.font = '27px Helvetica, Arial, sans-serif';
                ctx.fillText('Orden', 60, y);

                ctx.fillStyle = '#17181c';
                ctx.font = 'bold 26px Helvetica, Arial, sans-serif';

                var lineY = y;
                f.charms.forEach(function (charm, i) {
                    ctx.fillText((i + 1) + '. ' + charmLabel(charm), 360, lineY);
                    lineY += 38;
                });
                y = Math.max(y + 48, lineY + 14);

                section('Cliente');
                row('Peludo',   f.customer.pet);
                row('Nombre',   f.customer.name);
                row('WhatsApp', f.customer.phone);
                // Las notas son instrucciones para producción: se ajustan en varias
                // líneas en vez de recortarse, que es donde más duele perder texto.
                if (f.customer.notes) rowMultiline('Notas', f.customer.notes, 3);

                // --- Cotización ---
                y += 12;
                ctx.fillStyle = f.completa ? '#FF015B' : '#b45309';
                roundRect(ctx, 60, y, W - 120, 110, 18);
                ctx.fill();

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 30px Helvetica, Arial, sans-serif';
                ctx.fillText(f.completa ? 'Cotización' : 'Diseño incompleto', 96, y + 66);

                ctx.textAlign = 'right';
                ctx.font = 'bold 48px Helvetica, Arial, sans-serif';
                ctx.fillText(
                    f.completa ? money(f.price) : 'Mínimo ' + cfg.minCharms + ' charms',
                    W - 96, y + 72
                );
                ctx.textAlign = 'left';

                // --- Pie ---
                ctx.fillStyle = '#9ca3af';
                ctx.font = '22px Helvetica, Arial, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('Precio de referencia, no es un comprobante de pago.', W / 2, H - 46);
                ctx.textAlign = 'left';

                return new Promise(function (resolve) {
                    canvas.toBlob(function (blob) { resolve(blob); }, 'image/png');
                });
            });
    }

    function fichaFileName(f) {
        // Se conserva cualquier letra Unicode (Ñ, Ü, tildes) y solo se quita la
        // puntuación: una lista blanca de acentos siempre deja alguno fuera y el
        // nombre del peludo queda mutilado en el archivo.
        var who = (f.customer.pet || f.customer.name || 'ficha')
            .replace(/[^\p{L}\p{N}\s-]/gu, '')
            .trim()
            .replace(/\s+/g, '-');
        return f.id + '_' + (who || 'ficha') + '.png';
    }

    function downloadBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
    }

    /* ---------------------------------------------------------------------
     * Exportación: ZIP sin librerías
     *
     * Las entradas van sin comprimir (método 0). Los PNG ya están comprimidos,
     * así que no se pierde casi nada y evitamos cargar una librería de 200 KB
     * que además habría que servir offline.
     * ------------------------------------------------------------------ */

    var CRC_TABLE = (function () {
        var table = new Uint32Array(256);
        for (var n = 0; n < 256; n++) {
            var c = n;
            for (var k = 0; k < 8; k++) {
                c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
            }
            table[n] = c >>> 0;
        }
        return table;
    })();

    function crc32(bytes) {
        var c = 0xFFFFFFFF;
        for (var i = 0; i < bytes.length; i++) {
            c = CRC_TABLE[(c ^ bytes[i]) & 0xFF] ^ (c >>> 8);
        }
        return (c ^ 0xFFFFFFFF) >>> 0;
    }

    function dosDateTime(date) {
        var time = ((date.getHours() & 31) << 11) | ((date.getMinutes() & 63) << 5) | ((date.getSeconds() / 2) & 31);
        var day  = (((date.getFullYear() - 1980) & 127) << 9) | (((date.getMonth() + 1) & 15) << 5) | (date.getDate() & 31);
        return { time: time, date: day };
    }

    function buildZip(entries) {
        var chunks  = [];
        var central = [];
        var offset  = 0;
        var encoder = new TextEncoder();

        entries.forEach(function (entry) {
            var nameBytes = encoder.encode(entry.name);
            var data      = entry.data;
            var crc       = crc32(data);
            var dt        = dosDateTime(entry.date || new Date());

            var local = new DataView(new ArrayBuffer(30));
            local.setUint32(0, 0x04034b50, true);
            local.setUint16(4, 20, true);
            local.setUint16(6, 0x0800, true);  // nombres en UTF-8
            local.setUint16(8, 0, true);       // método 0: almacenado
            local.setUint16(10, dt.time, true);
            local.setUint16(12, dt.date, true);
            local.setUint32(14, crc, true);
            local.setUint32(18, data.length, true);
            local.setUint32(22, data.length, true);
            local.setUint16(26, nameBytes.length, true);
            local.setUint16(28, 0, true);

            chunks.push(new Uint8Array(local.buffer), nameBytes, data);

            var dir = new DataView(new ArrayBuffer(46));
            dir.setUint32(0, 0x02014b50, true);
            dir.setUint16(4, 20, true);
            dir.setUint16(6, 20, true);
            dir.setUint16(8, 0x0800, true);
            dir.setUint16(10, 0, true);
            dir.setUint16(12, dt.time, true);
            dir.setUint16(14, dt.date, true);
            dir.setUint32(16, crc, true);
            dir.setUint32(20, data.length, true);
            dir.setUint32(24, data.length, true);
            dir.setUint16(28, nameBytes.length, true);
            dir.setUint32(42, offset, true);

            central.push(new Uint8Array(dir.buffer), nameBytes);
            offset += 30 + nameBytes.length + data.length;
        });

        var centralSize = central.reduce(function (n, c) { return n + c.length; }, 0);

        var end = new DataView(new ArrayBuffer(22));
        end.setUint32(0, 0x06054b50, true);
        end.setUint16(8,  entries.length, true);
        end.setUint16(10, entries.length, true);
        end.setUint32(12, centralSize, true);
        end.setUint32(16, offset, true);

        return new Blob(chunks.concat(central, [new Uint8Array(end.buffer)]), { type: 'application/zip' });
    }

    function csvCell(v) {
        var s = String(v === undefined || v === null ? '' : v);

        // Excel evalúa como fórmula lo que empiece por = @ + o -, así que hay que
        // neutralizarlo con un apóstrofo. Pero un + seguido solo de dígitos es un
        // teléfono (+57...), no una fórmula: ahí el apóstrofo queda visible en la
        // celda y ensucia la columna. Solo se marca cuando de verdad parece código.
        var looksLikeFormula = /^[=@\t\r]/.test(s) || /^[+\-](?![\d\s().,-]*$)/.test(s);
        if (looksLikeFormula) s = "'" + s;

        return '"' + s.replace(/"/g, '""') + '"';
    }

    /** Fecha que Excel reconoce como tal: dd/mm/aaaa hh:mm. */
    function csvDate(ts) {
        var d = new Date(ts);
        function p(n) { return (n < 10 ? '0' : '') + n; }
        return p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + d.getFullYear() +
               ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    }

    function buildCSV() {
        var rows = [[
            'ID', 'Fecha', 'Evento', 'Peludo', 'Cliente', 'WhatsApp',
            'Talla', 'Color', 'Charms', 'Diseño', 'Cotización', 'Completa', 'Notas'
        ]];

        fichas.forEach(function (f) {
            rows.push([
                f.id,
                csvDate(f.createdAt),
                f.evento,
                f.customer.pet,
                f.customer.name,
                f.customer.phone,
                f.size,
                f.collarColor.label,
                f.charms.length,
                f.charms.map(charmLabel).join(' | '),
                f.completa ? f.price : '',
                f.completa ? 'Sí' : 'No',
                f.customer.notes
            ]);
        });

        // BOM para que Excel abra los acentos correctamente.
        return '﻿' + rows.map(function (r) { return r.map(csvCell).join(';'); }).join('\r\n');
    }

    function exportAll() {
        if (!fichas.length) {
            toast('No hay fichas para exportar.', true);
            return;
        }

        var btn = $('feria-export');
        btn.disabled = true;
        btn.textContent = 'Generando…';

        var entries = [];
        var encoder = new TextEncoder();

        entries.push({
            name: 'fichas.csv',
            data: encoder.encode(buildCSV()),
            date: new Date()
        });

        // Secuencial a propósito: en una tablet, generar 40 canvas en paralelo la tumba.
        var chain = Promise.resolve();

        fichas.forEach(function (f, i) {
            chain = chain.then(function () {
                btn.textContent = 'Generando… ' + (i + 1) + '/' + fichas.length;
                return renderFichaPNG(f).then(function (blob) {
                    return blob.arrayBuffer().then(function (buf) {
                        entries.push({
                            name: fichaFileName(f),
                            data: new Uint8Array(buf),
                            date: new Date(f.createdAt)
                        });
                    });
                });
            });
        });

        chain.then(function () {
            var stamp = new Date().toISOString().slice(0, 10);
            var slug  = (cfg.evento || 'feria').replace(/[^\w-]/g, '-').toLowerCase();
            downloadBlob(buildZip(entries), 'fichas_' + slug + '_' + stamp + '.zip');
            toast(fichas.length + ' fichas exportadas');
        }).catch(function () {
            toast('Falló la exportación.', true);
        }).then(function () {
            btn.disabled = false;
            btn.textContent = 'Exportar todo';
        });
    }

    /* ---------------------------------------------------------------------
     * Modo offline
     * ------------------------------------------------------------------ */

    var swReg = null;

    function registerSW() {
        if (!('serviceWorker' in navigator)) return;

        navigator.serviceWorker.register('/feria-sw.js', { scope: '/' })
            .then(function (reg) { swReg = reg; })
            .catch(function () { /* sin service worker el panel sigue funcionando online */ });

        navigator.serviceWorker.addEventListener('message', function (e) {
            var msg = e.data || {};
            if (msg.type !== 'precache-progress') return;

            var pct = Math.round((msg.done / msg.total) * 100);
            $('feria-precache-fill').style.width = pct + '%';
            $('feria-precache-text').textContent = msg.done >= msg.total
                ? 'Listo: ' + msg.total + ' archivos disponibles sin internet.'
                : 'Descargando ' + msg.done + ' de ' + msg.total + '…';

            if (msg.done >= msg.total) {
                $('feria-precache').disabled = false;
                toast('Panel listo para trabajar sin internet');
            }
        });
    }

    function startPrecache() {
        if (!navigator.serviceWorker || !navigator.serviceWorker.controller) {
            toast('El modo offline no está disponible en este navegador.', true);
            return;
        }

        $('feria-precache').disabled = true;
        $('feria-precache-progress').hidden = false;
        $('feria-precache-fill').style.width = '0%';
        $('feria-precache-text').textContent = 'Preparando…';

        navigator.serviceWorker.controller.postMessage({
            type: 'precache',
            urls: (cfg.precache || []).concat([location.href])
        });
    }

    function paintNetwork() {
        var el = $('feria-net');
        var off = !navigator.onLine;
        el.classList.toggle('is-offline', off);
        el.title = off ? 'Sin conexión — el panel sigue funcionando' : 'En línea';
    }

    /* ---------------------------------------------------------------------
     * Cajones y eventos
     * ------------------------------------------------------------------ */

    function openDrawer(id) {
        $(id).classList.add('is-open');
        $(id).setAttribute('aria-hidden', 'false');
        $('feria-scrim').hidden = false;
    }

    function closeDrawers() {
        ['feria-drawer', 'feria-menu'].forEach(function (id) {
            $(id).classList.remove('is-open');
            $(id).setAttribute('aria-hidden', 'true');
        });
        $('feria-scrim').hidden = true;
    }

    function wipeData() {
        if (!window.confirm('Esto borra TODAS las fichas y los datos personales de esta tablet.\n\n¿Ya exportaste?')) return;
        if (!window.confirm('Confirmación final: se borrarán ' + fichas.length + ' fichas. No se puede deshacer.')) return;

        dbRun('readwrite', function (s) { return s.clear(); })
            .then(loadFichas)
            .then(function () {
                resetDesign();
                closeDrawers();
                toast('Datos del evento borrados');
            });
    }

    function bindEvents() {
        $('feria-save').addEventListener('click', saveFicha);
        $('feria-clear').addEventListener('click', function () {
            if (state.charms.length && !window.confirm('¿Descartar este diseño?')) return;
            resetDesign();
        });

        $('feria-open-list').addEventListener('click', function () { openDrawer('feria-drawer'); });
        $('feria-open-menu').addEventListener('click', function () { openDrawer('feria-menu'); });
        $('feria-close-list').addEventListener('click', closeDrawers);
        $('feria-close-menu').addEventListener('click', closeDrawers);
        $('feria-scrim').addEventListener('click', closeDrawers);

        $('feria-export').addEventListener('click', exportAll);
        $('feria-precache').addEventListener('click', startPrecache);
        $('feria-wipe').addEventListener('click', wipeData);

        $('feria-tabs').addEventListener('click', function (e) {
            var tab = e.target.closest('.feria-tab');
            if (!tab) return;

            Array.prototype.forEach.call(this.children, function (b) {
                b.classList.toggle('is-active', b === tab);
            });

            var which = tab.dataset.tab;
            $('feria-panel-letters').classList.toggle('is-active', which === 'letters');
            $('feria-panel-emojis').classList.toggle('is-active', which === 'emojis');
        });

        window.addEventListener('online',  paintNetwork);
        window.addEventListener('offline', paintNetwork);

        // Un tirón accidental hacia abajo no debe recargar y perder el diseño en curso.
        window.addEventListener('beforeunload', function (e) {
            if (!state.charms.length) return;
            e.preventDefault();
            e.returnValue = '';
        });
    }

    /* ---------------------------------------------------------------------
     * Arranque
     * ------------------------------------------------------------------ */

    function init() {
        // Se conserva el color por defecto (Negro, igual que en la tienda) y solo se
        // cambia si la talla inicial no lo admite.
        if (!collarColorAllowed(state.collarColor)) {
            var first = (cfg.collarColors || []).filter(collarColorAllowed)[0];
            if (first) state.collarColor = first;
        }

        // Si el color de charm por defecto está agotado, mover al primero disponible.
        if (isSoldOut(state.charmColor)) {
            var avail = (cfg.charmColors || []).filter(function (c) { return !isSoldOut(c.hex); })[0];
            if (avail) state.charmColor = avail.hex;
        }

        buildSizes();
        buildCollarColors();
        buildCharmColors();
        buildKeyboard();
        buildEmojis();
        renderAll();
        bindEvents();
        paintNetwork();
        registerSW();

        openDB()
            .then(function (d) { db = d; return loadFichas(); })
            .catch(function () { toast('No se pudo abrir el almacenamiento local.', true); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
