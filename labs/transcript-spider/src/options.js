'use strict';

var DEFAULTS = { apiKey: '', model: 'openai/gpt-4o-mini', autoSummary: false };

function flash(text, ok) {
    var el = document.getElementById('state');
    el.textContent = text;
    el.style.color = ok ? '#00e676' : '#7d8794';
    setTimeout(function () { el.textContent = ''; }, 2500);
}

chrome.storage.local.get(['spider.config'], function (res) {
    var cfg = Object.assign({}, DEFAULTS, (res && res['spider.config']) || {});
    document.getElementById('key').value = cfg.apiKey || '';
    document.getElementById('model').value = cfg.model || DEFAULTS.model;
    document.getElementById('auto').checked = !!cfg.autoSummary;
});

document.getElementById('save').addEventListener('click', function () {
    var cfg = {
        apiKey: document.getElementById('key').value.trim(),
        model: document.getElementById('model').value.trim() || DEFAULTS.model,
        autoSummary: document.getElementById('auto').checked
    };
    chrome.storage.local.set({ 'spider.config': cfg }, function () {
        flash(cfg.apiKey ? 'Saved. Summaries will use ' + cfg.model : 'Saved. Running offline.', true);
    });
});

document.getElementById('clear').addEventListener('click', function () {
    document.getElementById('key').value = '';
    chrome.storage.local.get(['spider.config'], function (res) {
        var cfg = Object.assign({}, DEFAULTS, (res && res['spider.config']) || {});
        cfg.apiKey = '';
        chrome.storage.local.set({ 'spider.config': cfg }, function () { flash('Key removed. Running offline.', true); });
    });
});
