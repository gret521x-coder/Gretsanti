'use strict';

function withTab(fn) {
    chrome.tabs.query({ active: true, currentWindow: true }, function (tabs) {
        var tab = tabs && tabs[0];
        if (!tab || !tab.id) { setStatus('No active tab.'); return; }
        fn(tab.id);
    });
}

function setStatus(text) {
    document.getElementById('status').textContent = text;
}

function send(type) {
    withTab(function (id) {
        chrome.tabs.sendMessage(id, { type: type }, function (res) {
            if (chrome.runtime.lastError) {
                setStatus('This page cannot host the spider (open the transcript site, then reload it).');
                return;
            }
            refresh();
        });
    });
}

function refresh() {
    withTab(function (id) {
        chrome.tabs.sendMessage(id, { type: 'SPIDER_STATUS' }, function (res) {
            if (chrome.runtime.lastError || !res) {
                setStatus('Open the transcript site and reload it once.');
                return;
            }
            setStatus((res.running ? 'Watching' : 'Ready') + ' \u00b7 ' + res.lines +
                      ' lines \u00b7 ' + (res.selector ? 'box picked' : 'no box picked'));
        });
    });
}

document.getElementById('open').addEventListener('click', function () { send('SPIDER_OPEN'); });
document.getElementById('pick').addEventListener('click', function () { send('SPIDER_PICK'); window.close(); });
document.getElementById('start').addEventListener('click', function () { send('SPIDER_START'); });
document.getElementById('stop').addEventListener('click', function () { send('SPIDER_STOP'); });
document.getElementById('sum').addEventListener('click', function () { send('SPIDER_SUMMARIZE_NOW'); });
document.getElementById('opts').addEventListener('click', function () { chrome.runtime.openOptionsPage(); });

refresh();
