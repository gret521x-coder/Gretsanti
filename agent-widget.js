/* ============================================================================
   KDL // AIDE — floating agent widget
   ----------------------------------------------------------------------------
   Drop this on any page:

      <script>
        window.AGENT_CONFIG = { endpoint: 'api-ai.php' };
      </script>
      <script src="agent-widget.js" defer></script>

   Everything else is optional. No dependencies, no build step.

   window.AGENT_CONFIG = {
     endpoint:   'api-ai.php',     // your PHP proxy (keeps the key off the page)
     directUrl:  'https://openrouter.ai/api/v1/chat/completions',
     apiKey:     '',               // ONLY used when endpoint is '' (key visible!)
     model:      'openai/gpt-4o-mini',
     title:      'KDL // AIDE',
     subtitle:   'mission support',
     greeting:   '...',
     systemHint: '',               // extra line appended to the proxy's prompt
     accent:     '#38bdf8',
     position:   'right',          // 'right' | 'left'
     maxTurns:   12,               // history sent with each request
     persist:    true,             // sessionStorage transcript
     storageKey: 'kdl_agent_chat'
   }

   Programmatic control:
     Agent.open() · Agent.close() · Agent.toggle() · Agent.ask('text') · Agent.reset()
   ========================================================================== */
(function () {
  'use strict';
  if (window.__kdlAgentLoaded) return;
  window.__kdlAgentLoaded = true;

  var CFG = {
    endpoint: 'api-ai.php',
    directUrl: 'https://openrouter.ai/api/v1/chat/completions',
    apiKey: '',
    model: 'openai/gpt-4o-mini',
    title: 'KDL // AIDE',
    subtitle: 'mission support',
    greeting: 'Aide online. Ask me about the payloads, the launch codes or anything on this site.',
    systemHint: '',
    accent: '#38bdf8',
    position: 'right',
    maxTurns: 12,
    persist: true,
    storageKey: 'kdl_agent_chat',
    demo: false                  // true = never hit the network, reply with a scripted sample
  };
  var user = window.AGENT_CONFIG || {};
  for (var k in user) if (Object.prototype.hasOwnProperty.call(user, k)) CFG[k] = user[k];

  var S = { open: false, busy: false, built: false, msgs: [], ctrl: null, retry: null };

  /* ------------------------------------------------------------- helpers */
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function inline(t) {
    return esc(t)
      .replace(/`([^`\n]+)`/g, '<code>$1</code>')
      .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
      .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer nofollow">$1</a>')
      .replace(/\n/g, '<br>');
  }
  function mdToHtml(src) {
    var parts = String(src == null ? '' : src).split(/```/), out = '';
    for (var i = 0; i < parts.length; i++) {
      out += (i % 2 === 1)
        ? '<pre><code>' + esc(parts[i].replace(/^\n/, '')) + '</code></pre>'
        : inline(parts[i]);
    }
    return out;
  }
  function el(tag, cls, txt) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (txt != null) n.textContent = txt;
    return n;
  }
  function reduceMotion() {
    try { return window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) { return false; }
  }

  /* --------------------------------------------------------------- styles */
  var CSS = [
    '.kdl-agent-bubble{position:fixed;z-index:2147483000;bottom:22px;width:56px;height:56px;border:none;',
    'border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;',
    'background:radial-gradient(circle at 30% 25%,#0b1a24,#050a0e);color:ACCENT;',
    'box-shadow:0 10px 30px rgba(0,0,0,.55),0 0 0 1px rgba(ACCENT,.45),0 0 22px rgba(ACCENT,.28);',
    'transition:transform .15s ease,box-shadow .2s ease}',
    '.kdl-agent-bubble:hover{transform:translateY(-2px);box-shadow:0 14px 34px rgba(0,0,0,.6),0 0 0 1px rgba(ACCENT,.7),0 0 30px rgba(ACCENT,.4)}',
    '.kdl-agent-bubble svg{width:26px;height:26px}',
    '.kdl-agent-dot{position:absolute;top:6px;right:5px;width:9px;height:9px;border-radius:50%;',
    'background:#00e676;box-shadow:0 0 8px rgba(0,230,118,.9);opacity:0;transition:opacity .2s}',
    '.kdl-agent-dot.on{opacity:1}',
    '.kdl-agent-panel{position:fixed;z-index:2147483000;bottom:92px;width:370px;max-width:calc(100vw - 24px);',
    'height:min(560px,calc(100vh - 130px));display:none;flex-direction:column;overflow:hidden;',
    'background:#08090b;border:1px solid #1f242d;border-radius:14px;',
    'box-shadow:0 24px 70px rgba(0,0,0,.7),0 0 0 1px rgba(ACCENT,.12);',
    'font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#fff}',
    '.kdl-agent-panel.open{display:flex;animation:kdlAgentIn .18s cubic-bezier(.2,.9,.3,1.1)}',
    '@keyframes kdlAgentIn{from{opacity:0;transform:translateY(10px) scale(.98)}to{opacity:1;transform:none}}',
    '.kdl-agent-head{flex:0 0 auto;display:flex;align-items:center;gap:8px;padding:11px 12px;',
    'border-bottom:1px solid #1f242d;background:linear-gradient(180deg,#0d0f13,#08090b)}',
    '.kdl-agent-title{font-size:12px;letter-spacing:.12em;font-weight:700;color:#fff}',
    '.kdl-agent-sub{font-size:10px;color:#8b949e;letter-spacing:.06em;margin-top:1px}',
    '.kdl-agent-live{width:7px;height:7px;border-radius:50%;background:#00e676;box-shadow:0 0 7px rgba(0,230,118,.85)}',
    '.kdl-agent-x{margin-left:auto;width:26px;height:26px;border:1px solid #1f242d;background:transparent;',
    'color:#8b949e;border-radius:7px;cursor:pointer;font-size:13px;line-height:1}',
    '.kdl-agent-x:hover{color:#fff;border-color:rgba(ACCENT,.6)}',
    '.kdl-agent-log{flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:9px;',
    'scrollbar-width:thin;scrollbar-color:#1f242d transparent}',
    '.kdl-agent-log::-webkit-scrollbar{width:7px}',
    '.kdl-agent-log::-webkit-scrollbar-thumb{background:#1f242d;border-radius:6px}',
    '.kdl-agent-msg{max-width:86%;font-size:12.5px;line-height:1.55;padding:9px 11px;border-radius:11px;',
    'white-space:normal;word-break:break-word;overflow-wrap:anywhere}',
    '.kdl-agent-msg.agent{align-self:flex-start;background:#0d0f13;border:1px solid #1f242d;border-bottom-left-radius:4px}',
    '.kdl-agent-msg.user{align-self:flex-end;background:rgba(ACCENT,.13);border:1px solid rgba(ACCENT,.34);',
    'border-bottom-right-radius:4px}',
    '.kdl-agent-msg.err{align-self:stretch;max-width:100%;background:rgba(255,61,0,.1);border:1px solid rgba(255,61,0,.35);color:#ffb3a0}',
    '.kdl-agent-msg code{background:rgba(255,255,255,.07);padding:1px 4px;border-radius:4px;font-size:11.5px}',
    '.kdl-agent-msg pre{margin:6px 0;padding:8px;background:#000;border:1px solid #1f242d;border-radius:7px;overflow-x:auto}',
    '.kdl-agent-msg pre code{background:none;padding:0}',
    '.kdl-agent-msg a{color:ACCENT;text-decoration:underline;text-underline-offset:2px}',
    '.kdl-agent-msg strong{color:#fff}',
    '.kdl-agent-typing{display:inline-flex;gap:4px;align-items:center;height:12px}',
    '.kdl-agent-typing i{width:5px;height:5px;border-radius:50%;background:#8b949e;animation:kdlAgentBlink 1s infinite}',
    '.kdl-agent-typing i:nth-child(2){animation-delay:.15s}',
    '.kdl-agent-typing i:nth-child(3){animation-delay:.3s}',
    '@keyframes kdlAgentBlink{0%,100%{opacity:.25}50%{opacity:1}}',
    '.kdl-agent-foot{flex:0 0 auto;border-top:1px solid #1f242d;padding:9px;background:#0a0b0e}',
    '.kdl-agent-row{display:flex;gap:7px;align-items:flex-end}',
    '.kdl-agent-in{flex:1;min-height:38px;max-height:110px;resize:none;background:#050609;color:#fff;',
    'border:1px solid #1f242d;border-radius:9px;padding:9px 10px;font-family:inherit;font-size:12.5px;',
    'line-height:1.4;outline:none}',
    '.kdl-agent-in:focus{border-color:rgba(ACCENT,.65);box-shadow:0 0 0 3px rgba(ACCENT,.12)}',
    '.kdl-agent-send{width:40px;height:38px;flex:0 0 auto;border:none;border-radius:9px;cursor:pointer;',
    'background:ACCENT;color:#04121a;font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:center}',
    '.kdl-agent-send:disabled{opacity:.4;cursor:default}',
    '.kdl-agent-send.stop{background:#ff3d00;color:#fff}',
    '.kdl-agent-hint{font-size:10px;color:#484f58;letter-spacing:.04em;margin-top:6px;text-align:center}',
    '@media (max-width:520px){',
    '.kdl-agent-panel{left:10px;right:10px;bottom:86px;width:auto;height:min(70vh,540px)}',
    '.kdl-agent-bubble{width:52px;height:52px;bottom:18px}',
    '}',
    '@media (prefers-reduced-motion:reduce){.kdl-agent-panel.open{animation:none}.kdl-agent-typing i{animation:none}}'
  ].join('').replace(/ACCENT/g, CFG.accent);

  function injectCss() {
    if (document.getElementById('kdl-agent-css')) return;
    var st = document.createElement('style');
    st.id = 'kdl-agent-css';
    st.textContent = CSS;
    document.head.appendChild(st);
  }

  /* ------------------------------------------------------------------ DOM */
  var N = {};

  function build() {
    if (S.built) return;
    S.built = true;
    injectCss();

    var bubble = el('button', 'kdl-agent-bubble');
    bubble.type = 'button';
    bubble.setAttribute('aria-label', 'Open the assistant');
    bubble.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.9 9.9 0 0 1-3.2-.6L4 21l1.3-3.9A8.2 8.2 0 0 1 3 11.5 8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5Z"/><circle cx="9" cy="11.5" r=".9" fill="currentColor" stroke="none"/><circle cx="12.5" cy="11.5" r=".9" fill="currentColor" stroke="none"/><circle cx="16" cy="11.5" r=".9" fill="currentColor" stroke="none"/></svg>';
    var dot = el('span', 'kdl-agent-dot');
    bubble.appendChild(dot);
    bubble.addEventListener('click', function () { toggle(); });
    N.bubble = bubble; N.dot = dot;

    var panel = el('div', 'kdl-agent-panel');
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', CFG.title);
    panel.setAttribute('aria-modal', 'false');

    var head = el('div', 'kdl-agent-head');
    var live = el('span', 'kdl-agent-live');
    var titles = el('div');
    titles.appendChild(el('div', 'kdl-agent-title', CFG.title));
    titles.appendChild(el('div', 'kdl-agent-sub', CFG.subtitle));
    var x = el('button', 'kdl-agent-x', '✕');
    x.type = 'button';
    x.setAttribute('aria-label', 'Close the assistant');
    x.addEventListener('click', function () { close(); });
    head.appendChild(live); head.appendChild(titles); head.appendChild(x);

    var log = el('div', 'kdl-agent-log');
    log.setAttribute('role', 'log');
    log.setAttribute('aria-live', 'polite');

    var foot = el('div', 'kdl-agent-foot');
    var row = el('div', 'kdl-agent-row');
    var input = el('textarea', 'kdl-agent-in');
    input.rows = 1;
    input.placeholder = 'Ask the aide…';
    input.setAttribute('aria-label', 'Message the assistant');
    input.maxLength = 2000;
    var send = el('button', 'kdl-agent-send', '➤');
    send.type = 'button';
    send.setAttribute('aria-label', 'Send message');
    send.addEventListener('click', function () { onSendClick(); });
    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); onSendClick(); }
    });
    input.addEventListener('input', function () {
      input.style.height = 'auto';
      input.style.height = Math.min(110, input.scrollHeight) + 'px';
    });
    row.appendChild(input); row.appendChild(send);
    var hint = el('div', 'kdl-agent-hint', 'Enter to send · Shift+Enter for a new line · Esc to close');
    foot.appendChild(row); foot.appendChild(hint);

    panel.appendChild(head); panel.appendChild(log); panel.appendChild(foot);
    document.body.appendChild(bubble);
    document.body.appendChild(panel);
    if (CFG.position === 'left') {
      bubble.style.left = '22px'; panel.style.left = '20px';
    } else {
      bubble.style.right = '22px'; panel.style.right = '20px';
    }

    N.panel = panel; N.log = log; N.input = input; N.send = send;

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && S.open) close();
    });

    restore();
    if (!S.msgs.length) {
      pushMessage('agent', CFG.greeting);
      renderBubble('agent', CFG.greeting);
    }
  }

  /* ------------------------------------------------------------ transcript */
  function save() {
    if (!CFG.persist) return;
    try {
      sessionStorage.setItem(CFG.storageKey, JSON.stringify(S.msgs.slice(-40)));
    } catch (e) {}
  }
  function restore() {
    if (!CFG.persist) return;
    try {
      var raw = sessionStorage.getItem(CFG.storageKey);
      var arr = raw ? JSON.parse(raw) : null;
      if (Array.isArray(arr)) {
        arr.forEach(function (m) {
          if (m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string') {
            S.msgs.push({ role: m.role, content: m.content.slice(0, 4000) });
            renderBubble(m.role === 'user' ? 'user' : 'agent', m.content);
          }
        });
      }
    } catch (e) {}
  }

  /* ----------------------------------------------------------------- render */
  function renderBubble(who, text) {
    var b = el('div', 'kdl-agent-msg ' + who);
    if (who === 'user') b.textContent = text;
    else b.innerHTML = mdToHtml(text);
    N.log.appendChild(b);
    scrollEnd();
    return b;
  }
  function pushMessage(role, text) {
    S.msgs.push({ role: role, content: String(text).slice(0, 4000) });
    save();
  }
  function scrollEnd() {
    if (N.log) N.log.scrollTop = N.log.scrollHeight;
  }
  function note(text, cls) {
    var b = el('div', 'kdl-agent-msg ' + (cls || 'err'), text);
    N.log.appendChild(b);
    scrollEnd();
    return b;
  }
  function setBusy(on) {
    S.busy = on;
    if (!N.send) return;
    N.send.textContent = on ? '■' : '➤';
    N.send.className = 'kdl-agent-send' + (on ? ' stop' : '');
    N.send.setAttribute('aria-label', on ? 'Stop generating' : 'Send message');
    N.send.disabled = false;
  }

  /* -------------------------------------------------------------- transport */
  function endpoint() {
    if (!CFG.endpoint) return CFG.directUrl;
    return CFG.endpoint;
  }
  function body(messages) {
    var payload = { model: CFG.model, messages: messages, stream: true };
    if (CFG.systemHint) payload.hint = CFG.systemHint;
    return JSON.stringify(payload);
  }
  function headers() {
    var h = { 'Content-Type': 'application/json' };
    if (!CFG.endpoint && CFG.apiKey) h.Authorization = 'Bearer ' + CFG.apiKey;
    return h;
  }
  function historyPayload() {
    var cap = Math.max(2, CFG.maxTurns * 2);
    return S.msgs.slice(-cap).map(function (m) { return { role: m.role, content: m.content }; });
  }

  function demoReply(text, onDelta) {
    var canned = 'Demo mode — no PHP here, so this is a scripted answer. Ask about "payloads", "bardagulan" or "aurora".\n\n'
      + 'You said: **' + String(text).slice(0, 80) + '**\n\n'
      + 'On your host the same message goes to `api-ai.php`, which adds the key and streams a real OpenRouter answer back through this panel.';
    var words = canned.split(/(\s+)/);
    return new Promise(function (resolve) {
      var i = 0;
      (function step() {
        if (i >= words.length) return resolve();
        onDelta(words[i++]);
        setTimeout(step, 26);
      })();
    });
  }

  function streamReply(messages, onDelta) {
    S.ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    var opts = { method: 'POST', headers: headers(), body: body(messages) };
    if (S.ctrl) opts.signal = S.ctrl.signal;

    return fetch(endpoint(), opts).then(function (res) {
      var type = (res.headers && res.headers.get ? (res.headers.get('content-type') || '') : '');
      if (!res.ok) {
        return res.text().then(function (t) {
          var msg = 'Request failed (' + res.status + ')';
          try { var j = JSON.parse(t); if (j && (j.error || j.message)) msg = typeof j.error === 'string' ? j.error : (j.error.message || j.message); } catch (e) {}
          throw new Error(msg);
        });
      }
      if (!res.body || !res.body.getReader || type.indexOf('event-stream') < 0) {
        return res.json().then(function (data) {
          var text = extractFull(data);
          if (text) onDelta(text);
          return text || '';
        });
      }
      var reader = res.body.getReader();
      var dec = new TextDecoder();
      var buf = '';
      var acc = '';
      function pump() {
        return reader.read().then(function (r) {
          if (r.done) return acc;
          buf += dec.decode(r.value, { stream: true });
          var lines = buf.split('\n');
          buf = lines.pop();
          lines.forEach(function (line) {
            line = line.trim();
            if (!line || line.indexOf('data:') !== 0) return;
            var data = line.slice(5).trim();
            if (data === '[DONE]') return;
            var upstreamError = null;
            try {
              var parsed = JSON.parse(data);
              if (parsed && parsed.error) {
                upstreamError = (typeof parsed.error === 'string') ? parsed.error : (parsed.error.message || 'upstream error');
              }
            } catch (e) {}
            if (upstreamError) throw new Error(upstreamError);
            var piece = extractDelta(data);
            if (piece) { acc += piece; onDelta(piece); }
          });
          return pump();
        });
      }
      return pump();
    });
  }

  function extractDelta(raw) {
    try {
      var j = JSON.parse(raw);
      if (typeof j.delta === 'string') return j.delta;
      if (typeof j.text === 'string') return j.text;
      var c = j.choices && j.choices[0];
      if (!c) return '';
      if (c.delta && typeof c.delta.content === 'string') return c.delta.content;
      if (c.message && typeof c.message.content === 'string') return c.message.content;
      if (typeof c.text === 'string') return c.text;
    } catch (e) {}
    return '';
  }
  function extractFull(data) {
    if (!data) return '';
    if (typeof data === 'string') return data;
    if (typeof data.delta === 'string') return data.delta;
    if (typeof data.text === 'string') return data.text;
    var c = data.choices && data.choices[0];
    if (c) {
      if (c.message && typeof c.message.content === 'string') return c.message.content;
      if (c.delta && typeof c.delta.content === 'string') return c.delta.content;
      if (typeof c.text === 'string') return c.text;
    }
    return '';
  }

  /* ------------------------------------------------------------------ send */
  function onSendClick() {
    if (S.busy) { stop(); return; }
    var v = N.input.value.trim();
    if (!v) return;
    N.input.value = '';
    N.input.style.height = 'auto';
    send(v);
  }

  function send(text) {
    if (S.busy) return;
    text = String(text || '').trim();
    if (!text) return;
    if (CFG.demo) { demoOnly(text); return; }
    S.retry = text;
    renderBubble('user', text);
    pushMessage('user', text);

    var holder = el('div', 'kdl-agent-msg agent');
    holder.innerHTML = '<span class="kdl-agent-typing"><i></i><i></i><i></i></span>';
    N.log.appendChild(holder);
    scrollEnd();
    setBusy(true);

    var acc = '';
    streamReply(historyPayload(), function (piece) {
      acc += piece;
      holder.innerHTML = mdToHtml(acc);
      scrollEnd();
    }).then(function () {
      setBusy(false);
      if (!acc) {
        holder.innerHTML = mdToHtml('_(empty reply)_');
      } else {
        pushMessage('assistant', acc);
      }
      S.retry = null;
      S.ctrl = null;
    }).catch(function (err) {
      setBusy(false);
      S.ctrl = null;
      holder.parentNode && holder.parentNode.removeChild(holder);
      if (err && (err.name === 'AbortError')) {
        if (acc) { holder.innerHTML = mdToHtml(acc); pushMessage('assistant', acc); }
        else { note('Stopped.'); }
        return;
      }
      var box = note('⚠ ' + (err && err.message ? err.message : 'Something went wrong.') + '  ·  tap to retry');
      box.style.cursor = 'pointer';
      box.addEventListener('click', function () {
        box.remove();
        if (S.retry) { N.input.value = S.retry; onSendClick(); }
      });
    });
  }

  function demoOnly(text) {
    renderBubble('user', text);
    pushMessage('user', text);
    var holder = el('div', 'kdl-agent-msg agent');
    holder.innerHTML = '<span class="kdl-agent-typing"><i></i><i></i><i></i></span>';
    N.log.appendChild(holder);
    scrollEnd();
    setBusy(true);
    var acc = '';
    demoReply(text, function (piece) {
      acc += piece;
      holder.innerHTML = mdToHtml(acc);
      scrollEnd();
    }).then(function () {
      setBusy(false);
      pushMessage('assistant', acc);
    });
  }

  function stop() {
    if (S.ctrl) { try { S.ctrl.abort(); } catch (e) {} }
    setBusy(false);
  }

  /* ------------------------------------------------------------ open/close */
  function open() {
    build();
    S.open = true;
    N.panel.classList.add('open');
    N.dot.classList.remove('on');
    setTimeout(function () { try { N.input.focus(); } catch (e) {} }, 60);
    scrollEnd();
  }
  function close() {
    S.open = false;
    if (N.panel) N.panel.classList.remove('open');
  }
  function toggle() { S.open ? close() : open(); }
  function reset() {
    S.msgs = [];
    if (N.log) N.log.innerHTML = '';
    try { sessionStorage.removeItem(CFG.storageKey); } catch (e) {}
    if (N.log) {
      pushMessage('agent', CFG.greeting);
      renderBubble('agent', CFG.greeting);
    }
  }
  function ask(text) {
    build();
    open();
    if (text) send(text);
  }

  /* mount */
  function mount() {
    build();
    if (!S.open) { /* panel stays closed until asked */ }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
  else mount();

  window.Agent = { open: open, close: close, toggle: toggle, reset: reset, ask: ask, config: CFG };
})();
