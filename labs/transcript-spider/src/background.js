/* Call Spider — service worker.
   Decides where the summary comes from: OpenRouter when a key is saved, the local
   keyword summarizer otherwise. The transcript only leaves the machine if you set a key. */
'use strict';

try { importScripts('lib/harvest.js'); } catch (e) { /* the content script has its own copy */ }

var H = self.SpiderHarvest;

var ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
var DEFAULT_MODEL = 'openai/gpt-4o-mini';

function getConfig(cb) {
    chrome.storage.local.get(['spider.config'], function (res) {
        cb((res && res['spider.config']) || {});
    });
}

function summarizeWithModel(apiKey, model, transcript, done) {
    var prompt = H.buildPrompt(transcript);
    fetch(ENDPOINT, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + apiKey,
            'X-Title': 'Call Spider'
        },
        body: JSON.stringify({
            model: model || DEFAULT_MODEL,
            temperature: 0.2,
            messages: [
                { role: 'system', content: prompt.system },
                { role: 'user', content: prompt.user }
            ]
        })
    }).then(function (res) {
        return res.text().then(function (body) {
            if (!res.ok) {
                var detail = body;
                try { detail = JSON.parse(body).error.message || body; } catch (e) { /* keep the raw body */ }
                done({ ok: false, error: res.status + ' ' + String(detail).slice(0, 300) });
                return;
            }
            var text = '';
            try {
                var json = JSON.parse(body);
                text = (json.choices && json.choices[0] && json.choices[0].message &&
                        json.choices[0].message.content) || '';
            } catch (e) {
                done({ ok: false, error: 'The provider answered with something that is not JSON.' });
                return;
            }
            if (!text) {
                done({ ok: false, error: 'The provider returned an empty answer.' });
                return;
            }
            done({ ok: true, text: text, engine: 'openrouter:' + (model || DEFAULT_MODEL) });
        });
    }).catch(function (err) {
        done({ ok: false, error: (err && err.message) || 'network error' });
    });
}

chrome.runtime.onMessage.addListener(function (msg, sender, sendResponse) {
    if (!msg || msg.type !== 'SPIDER_SUMMARIZE') return;

    var transcript = msg.transcript || '';
    getConfig(function (config) {
        if (!config.apiKey) {
            sendResponse({
                ok: true,
                needsKey: true,
                text: H.offlineSummary(transcript),
                engine: 'offline'
            });
            return;
        }
        summarizeWithModel(config.apiKey, config.model, transcript, function (result) {
            if (result.ok) {
                sendResponse(result);
            } else {
                sendResponse({
                    ok: false,
                    error: result.error,
                    text: H.offlineSummary(transcript)
                });
            }
        });
    });

    return true;    /* keep the channel open for the async reply */
});

chrome.runtime.onInstalled.addListener(function () {
    chrome.storage.local.get(['spider.config'], function (res) {
        if (!res || !res['spider.config']) {
            chrome.storage.local.set({
                'spider.config': { apiKey: '', model: DEFAULT_MODEL, autoSummary: false }
            });
        }
    });
});
