/* Call Spider — content script.
   Watches the transcript box you pick, collects every line as it appears, keeps the count and
   the time, and shows it all in a small panel that floats over the page. Summarize when you like. */
(function () {
    'use strict';

    if (window.__callSpiderLoaded) return;
    window.__callSpiderLoaded = true;

    var H = window.SpiderHarvest;
    if (!H) return;

    var MAX_LINES = 5000;

    var state = {
        selector: '',
        container: null,
        observer: null,
        counts: {},
        lines: [],
        running: false,
        summary: '',
        panelOpen: false,
        harvestTimer: null
    };

    var hostEl = null;      /* shadow host */
    var ui = {};            /* cached nodes inside the shadow root */
    var picker = null;

    /* ------------------------------------------------------------------ storage */
    function selectorKey() { return 'spider.selector.' + location.hostname; }

    function loadSelector(done) {
        try {
            chrome.storage.local.get([selectorKey(), 'spider.config'], function (res) {
                state.selector = (res && res[selectorKey()]) || '';
                state.config = (res && res['spider.config']) || {};
                done();
            });
        } catch (e) {
            done();
        }
    }

    function saveSelector(sel) {
        state.selector = sel;
        try {
            var patch = {};
            patch[selectorKey()] = sel;
            chrome.storage.local.set(patch);
        } catch (e) { /* storage is a nicety, not a requirement */ }
    }

    /* ------------------------------------------------------------------- panel */
    var STYLE = [
        ':host{all:initial}',
        '*{box-sizing:border-box;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}',
        '.panel{position:fixed;top:18px;right:18px;width:380px;max-width:calc(100vw - 24px);',
        '  background:#08090b;color:#e6edf3;border:1px solid #1f242d;border-radius:12px;',
        '  box-shadow:0 18px 50px rgba(0,0,0,.6);z-index:2147483647;overflow:hidden;display:flex;flex-direction:column;',
        '  max-height:min(70vh,620px)}',
        '.bar{display:flex;align-items:center;gap:6px;padding:8px 10px;background:#0d0f13;border-bottom:1px solid #1f242d;cursor:move;user-select:none}',
        '.bar b{font-size:11px;letter-spacing:.18em;color:#38bdf8;font-weight:600}',
        '.meta{font-size:10px;color:#7d8794;margin-left:2px}',
        '.grow{flex:1}',
        'button{font:inherit;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#c7d2de;',
        '  background:#11151b;border:1px solid #1f242d;border-radius:6px;padding:4px 7px;cursor:pointer}',
        'button:hover{border-color:#38bdf8;color:#e6edf3}',
        'button[data-act="toggle"].on{border-color:rgba(0,230,118,.5);color:#00e676}',
        '.body{overflow:auto;padding:8px 10px;flex:1;min-height:80px}',
        '.lines{font-size:11px;line-height:1.6;color:#aab6c3;white-space:pre-wrap;word-break:break-word}',
        '.lines .t{color:#4b535e;margin-right:6px}',
        '.hint{font-size:11px;color:#7d8794;line-height:1.6}',
        '.summary{border-top:1px solid #1f242d;padding:8px 10px;font-size:11px;line-height:1.6;',
        '  color:#c7d2de;white-space:pre-wrap;max-height:45%;overflow:auto;background:#0a0c10}',
        '.summary b{color:#38bdf8}',
        '.bubble{position:fixed;top:18px;right:18px;width:44px;height:44px;border-radius:50%;',
        '  background:#0d0f13;border:1px solid #1f242d;color:#38bdf8;font-size:19px;cursor:pointer;',
        '  display:flex;align-items:center;justify-content:center;z-index:2147483647;',
        '  box-shadow:0 10px 24px rgba(0,0,0,.5)}',
        '.bubble.rec{border-color:rgba(0,230,118,.6);animation:pulse 1.6s infinite}',
        '@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(0,230,118,.35)}50%{box-shadow:0 0 0 7px rgba(0,230,118,0)}}',
        '@media (prefers-reduced-motion: reduce){.bubble.rec{animation:none}}'
    ].join('');

    function buildPanel() {
        if (hostEl) return;
        hostEl = document.createElement('div');
        hostEl.id = '__call_spider_host';
        hostEl.style.cssText = 'all:initial;position:fixed;inset:0;pointer-events:none;z-index:2147483647';
        var shadow = hostEl.attachShadow({ mode: 'open' });

        var style = document.createElement('style');
        style.textContent = STYLE;
        shadow.appendChild(style);

        var bubble = document.createElement('button');
        bubble.className = 'bubble';
        bubble.title = 'Call Spider';
        bubble.textContent = '\uD83D\uDD77';
        bubble.style.pointerEvents = 'auto';
        bubble.addEventListener('click', function () { openPanel(); });
        shadow.appendChild(bubble);
        ui.bubble = bubble;

        var panel = document.createElement('div');
        panel.className = 'panel';
        panel.style.pointerEvents = 'auto';
        panel.innerHTML = [
            '<div class="bar" data-drag="1">',
            '  <b>SPIDER</b><span class="meta" data-el="meta">idle</span><span class="grow"></span>',
            '  <button data-act="pick" title="Pick the transcript box">pick</button>',
            '  <button data-act="toggle" title="Start or stop watching">start</button>',
            '  <button data-act="sum" title="Summarize what we have">summarize</button>',
            '  <button data-act="copy" title="Copy the transcript">copy</button>',
            '  <button data-act="min" title="Hide">\u2013</button>',
            '</div>',
            '<div class="body"><div class="lines" data-el="lines"></div><div class="hint" data-el="hint">' +
            '  Press <b>pick</b>, then click the box on the page that shows the live transcript.</div></div>',
            '<div class="summary" data-el="summary" hidden></div>'
        ].join('');
        shadow.appendChild(panel);
        ui.panel = panel;
        ui.meta = panel.querySelector('[data-el="meta"]');
        ui.lines = panel.querySelector('[data-el="lines"]');
        ui.hint = panel.querySelector('[data-el="hint"]');
        ui.summary = panel.querySelector('[data-el="summary"]');
        ui.toggle = panel.querySelector('[data-act="toggle"]');

        panel.addEventListener('click', function (e) {
            var act = e.target && e.target.getAttribute && e.target.getAttribute('data-act');
            if (!act) return;
            if (act === 'pick') startPicker();
            if (act === 'toggle') toggleRunning();
            if (act === 'sum') summarize();
            if (act === 'copy') copyTranscript();
            if (act === 'min') closePanel();
        });

        makeDraggable(panel, panel.querySelector('.bar'));

        document.documentElement.appendChild(hostEl);
        render();
    }

    function makeDraggable(panel, handle) {
        var drag = null;
        handle.addEventListener('mousedown', function (e) {
            if (e.target.getAttribute && e.target.getAttribute('data-act')) return;
            var r = panel.getBoundingClientRect();
            drag = { dx: e.clientX - r.left, dy: e.clientY - r.top };
            panel.style.left = r.left + 'px';
            panel.style.top = r.top + 'px';
            panel.style.right = 'auto';
            e.preventDefault();
        });
        window.addEventListener('mousemove', function (e) {
            if (!drag) return;
            var x = Math.max(0, Math.min(window.innerWidth - 80, e.clientX - drag.dx));
            var y = Math.max(0, Math.min(window.innerHeight - 40, e.clientY - drag.dy));
            panel.style.left = x + 'px';
            panel.style.top = y + 'px';
        });
        window.addEventListener('mouseup', function () { drag = null; });
    }

    function openPanel() {
        buildPanel();
        state.panelOpen = true;
        ui.panel.style.display = 'flex';
        ui.bubble.style.display = 'none';
        render();
    }

    function closePanel() {
        state.panelOpen = false;
        if (ui.panel) ui.panel.style.display = 'none';
        if (ui.bubble) ui.bubble.style.display = 'flex';
    }

    function togglePanel() {
        if (state.panelOpen) closePanel(); else openPanel();
    }

    function render() {
        if (!ui.panel) return;
        var status = state.running ? 'watching' : (state.lines.length ? 'stopped' : 'idle');
        ui.meta.textContent = state.selector
            ? status + ' \u00b7 ' + state.lines.length + ' lines'
            : status + ' \u00b7 no box picked';
        ui.toggle.textContent = state.running ? 'stop' : 'start';
        ui.toggle.classList.toggle('on', state.running);
        ui.toggle.disabled = !state.selector;
        ui.hint.style.display = state.selector ? 'none' : 'block';
        if (ui.bubble) ui.bubble.classList.toggle('rec', state.running);

        var shown = state.lines.slice(-400);
        ui.lines.innerHTML = '';
        shown.forEach(function (line) {
            var row = document.createElement('div');
            var t = document.createElement('span');
            t.className = 't';
            t.textContent = line.clock;
            row.appendChild(t);
            row.appendChild(document.createTextNode(line.text));
            ui.lines.appendChild(row);
        });
        ui.lines.parentElement.scrollTop = ui.lines.parentElement.scrollHeight;

        if (state.summary) {
            ui.summary.hidden = false;
            ui.summary.innerHTML = '';
            renderSummary(ui.summary);
        } else {
            ui.summary.hidden = true;
        }
    }

    function renderSummary(target) {
        /* the summary is markdown-ish; render only the two things it uses: ## headings and - bullets */
        state.summary.split('\n').forEach(function (raw) {
            var line = raw.trim();
            if (!line) { target.appendChild(document.createElement('br')); return; }
            var row = document.createElement('div');
            if (/^#{1,3}\s/.test(line)) {
                var b = document.createElement('b');
                b.textContent = line.replace(/^#{1,3}\s/, '').toUpperCase();
                row.appendChild(b);
            } else if (/^[*-]\s/.test(line)) {
                row.textContent = '\u2022 ' + line.replace(/^[*-]\s/, '');
            } else {
                row.textContent = line.replace(/\*\*/g, '').replace(/_/g, '');
            }
            target.appendChild(row);
        });
    }

    /* ------------------------------------------------------------------ picker */
    function startPicker() {
        if (picker) stopPicker();
        buildPanel();

        var shade = document.createElement('div');
        shade.style.cssText = 'position:fixed;inset:0;z-index:2147483646;cursor:crosshair;background:rgba(0,0,0,.25)';
        var outline = document.createElement('div');
        outline.style.cssText = 'position:fixed;border:2px solid #38bdf8;border-radius:4px;' +
            'pointer-events:none;z-index:2147483647;box-shadow:0 0 0 9999px rgba(0,0,0,.35)';
        hostEl.shadowRoot.appendChild(shade);
        hostEl.shadowRoot.appendChild(outline);

        function hover(e) {
            var el = document.elementFromPoint(e.clientX, e.clientY);
            if (!el || el === hostEl) return;
            var r = el.getBoundingClientRect();
            outline.style.left = r.left + 'px';
            outline.style.top = r.top + 'px';
            outline.style.width = r.width + 'px';
            outline.style.height = r.height + 'px';
        }

        function click(e) {
            e.preventDefault();
            e.stopPropagation();
            var el = document.elementFromPoint(e.clientX, e.clientY);
            stopPicker();
            if (!el) return;
            var sel = cssPath(el);
            var check = document.querySelector(sel);
            if (!check) {
                setSummary('_Could not build a stable selector for that box. Try a bigger element._');
                return;
            }
            saveSelector(sel);
            attach(check);
            start();
            openPanel();
        }

        shade.addEventListener('mousemove', hover);
        shade.addEventListener('click', click, true);
        picker = { shade: shade, outline: outline };

        function stopPicker() {
            if (!picker) return;
            shade.removeEventListener('click', click, true);
            picker.shade.remove();
            picker.outline.remove();
            picker = null;
        }
        picker.stop = stopPicker;
    }

    function stopPicker() {
        if (picker && picker.stop) picker.stop();
        picker = null;
    }

    function cssPath(el) {
        var parts = [];
        var node = el;
        while (node && node.nodeType === 1 && parts.length < 6) {
            var sel = node.tagName.toLowerCase();
            if (node.id) {
                sel = '#' + cssEscape(node.id);
                parts.unshift(sel);
                break;
            }
            var cls = (node.className && typeof node.className === 'string')
                ? node.className.trim().split(/\s+/).filter(Boolean).slice(0, 2) : [];
            cls.forEach(function (c) { sel += '.' + cssEscape(c); });
            var parent = node.parentElement;
            if (parent) {
                var twins = Array.prototype.filter.call(parent.children, function (c) {
                    return c.tagName === node.tagName;
                });
                if (twins.length > 1) sel += ':nth-of-type(' + (twins.indexOf(node) + 1) + ')';
            }
            parts.unshift(sel);
            try {
                if (document.querySelectorAll(parts.join(' > ')).length === 1) break;
            } catch (e) { /* an invalid step just means we keep walking up */ }
            node = parent;
        }
        return parts.join(' > ');
    }

    function cssEscape(value) {
        if (window.CSS && CSS.escape) return CSS.escape(value);
        return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    }

    /* ----------------------------------------------------------------- harvest */
    function attach(container) {
        detach();
        state.container = container;
        if (!container) return;
        state.observer = new MutationObserver(scheduleHarvest);
        state.observer.observe(container, { childList: true, subtree: true, characterData: true });
        harvest();
    }

    function detach() {
        if (state.observer) { state.observer.disconnect(); state.observer = null; }
    }

    function scheduleHarvest() {
        if (state.harvestTimer) return;
        state.harvestTimer = setTimeout(function () {
            state.harvestTimer = null;
            harvest();
        }, 250);
    }

    function harvest() {
        if (!state.container) return;
        var text = state.container.innerText || state.container.textContent || '';
        var result = H.extractNewLines(text, state.counts);
        state.counts = result.counts;
        if (!result.fresh.length) return;

        var now = Date.now();
        result.fresh.forEach(function (line) {
            state.lines.push({ clock: H.clockOf(now), at: now, text: line });
        });
        if (state.lines.length > MAX_LINES) state.lines = state.lines.slice(-MAX_LINES);
        render();
    }

    function start() {
        if (!state.selector) { startPicker(); return; }
        if (!state.container) {
            var el = document.querySelector(state.selector);
            if (!el) {
                setSummary('_The box you picked is gone from this page. Pick it again._');
                return;
            }
            attach(el);
        }
        state.running = true;
        harvest();
        render();
    }

    function stop() {
        state.running = false;
        detach();
        render();
    }

    function toggleRunning() {
        if (state.running) {
            stop();
            if (state.config && state.config.autoSummary && state.lines.length > 8) summarize();
        } else {
            start();
        }
    }

    /* --------------------------------------------------------------- summarize */
    function setSummary(markdown) {
        state.summary = markdown;
        render();
    }

    function summarize() {
        var transcript = H.transcriptText(state.lines);
        if (!transcript.trim()) {
            setSummary('_Nothing captured yet. Pick the transcript box and press start._');
            return;
        }
        setSummary('_Summarizing\u2026_');

        try {
            chrome.runtime.sendMessage({ type: 'SPIDER_SUMMARIZE', transcript: transcript }, function (res) {
                if (chrome.runtime.lastError || !res) {
                    finishWithOffline(transcript);
                    return;
                }
                if (res.ok) {
                    setSummary(res.text);
                    return;
                }
                if (res.needsKey) {
                    finishWithOffline(transcript);
                    return;
                }
                setSummary('_Summary failed: ' + (res.error || 'unknown error') + '_\n\n' +
                           H.offlineSummary(transcript));
            });
        } catch (e) {
            finishWithOffline(transcript);
        }
    }

    function finishWithOffline(transcript) {
        setSummary(H.offlineSummary(transcript));
    }

    function copyTranscript() {
        var text = state.lines.map(function (l) { return '[' + l.clock + '] ' + l.text; }).join('\n');
        var body = state.summary ? text + '\n\n---\n' + state.summary : text;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(body).then(function () {
                ui.meta.textContent = 'copied \u00b7 ' + state.lines.length + ' lines';
                setTimeout(render, 1500);
            }, function () {
                setSummary('_Clipboard was blocked by the page. Select the text and copy it by hand._');
            });
        }
    }

    /* ---------------------------------------------------------------- messages */
    chrome.runtime.onMessage.addListener(function (msg, sender, sendResponse) {
        if (!msg || !msg.type) return;
        if (msg.type === 'SPIDER_OPEN') {
            openPanel();
            sendResponse({ open: true, running: state.running, lines: state.lines.length, selector: state.selector });
        }
        if (msg.type === 'SPIDER_TOGGLE') {
            togglePanel();
            sendResponse({ open: state.panelOpen });
        }
        if (msg.type === 'SPIDER_START') { start(); sendResponse({ running: state.running }); }
        if (msg.type === 'SPIDER_STOP') { stop(); sendResponse({ running: state.running }); }
        if (msg.type === 'SPIDER_PICK') { startPicker(); sendResponse({ picking: true }); }
        if (msg.type === 'SPIDER_SUMMARIZE_NOW') { summarize(); sendResponse({ started: true }); }
        if (msg.type === 'SPIDER_STATUS') {
            sendResponse({
                running: state.running,
                lines: state.lines.length,
                selector: state.selector,
                open: state.panelOpen
            });
        }
        return true;
    });

    /* ------------------------------------------------------------------ start */
    function boot() {
        loadSelector(function () {
            buildPanel();
            closePanel();
            if (state.selector) {
                var el = document.querySelector(state.selector);
                if (el) attach(el);
            }
            render();

            /* the panel must survive the site re-rendering its own body */
            var keepAlive = setInterval(function () {
                if (!document.documentElement.contains(hostEl)) {
                    document.documentElement.appendChild(hostEl);
                }
                if (state.running && state.container && !document.contains(state.container)) {
                    var again = document.querySelector(state.selector);
                    if (again) attach(again);
                }
            }, 2000);
            window.addEventListener('pagehide', function () { clearInterval(keepAlive); });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
