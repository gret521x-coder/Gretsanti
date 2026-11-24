# Call Spider

A browser extension that reads the live transcript already on your screen, keeps every new line, floats a
panel over the page, and turns the whole call into notes when you press **summarize**.

It does not listen to audio. It does not touch your microphone. The transcript site already did the hard
part — this reads the words it put on the page.

## What you need

- Chrome or Edge on your PC (any Chromium browser)
- The transcript site open in a normal tab

## Install (once, two minutes)

1. Open `chrome://extensions` (or `edge://extensions`).
2. Turn on **Developer mode** (top right).
3. Press **Load unpacked** and pick this folder — `labs/transcript-spider`.
4. Pin the spider icon to the toolbar.

## Use it on a call

1. Open the transcript site in a tab.
2. Click the spider icon → **Open panel**. A small dark panel appears on the page; drag it by its top bar
   anywhere you like.
3. Press **pick**, then click the box on the page that shows the live transcript. The spider remembers it
   for that site, so it is a one-time job.
4. Press **start**. Every new line is captured with a clock time. The counter in the bar tells you how
   many lines you have.
5. Press **stop** when the call ends, then **summarize**.
6. **copy** puts the whole transcript, plus the summary, on your clipboard as plain text.

The panel keeps watching while you scroll, and if the site re-renders its own page the spider re-attaches
itself instead of going quiet.

## The summary

Two engines, and the panel never stops working because of a missing key.

| | **Offline** (no key) | **OpenRouter** (key set) |
|---|---|---|
| How it works | Word frequency + action-word patterns, all local | Sends the transcript to the model you choose |
| Output | Summary, Decisions, Action items | `## Summary`, `## Decisions`, `## Action items`, `## Open questions` |
| Leaves the PC? | Never | The transcript of that call, nothing else |
| Cost | Free | Whatever your key's model costs |

Set it up: spider icon → right-click → **Options** (or the **Key / model** button in the popup). Paste an
OpenRouter key, pick a model (`openai/gpt-4o-mini` is the default), tick **summarize when I press stop**
if you want it automatic. **Forget key** wipes it.

The key lives in `chrome.storage.local` — this browser profile only, never in the page, never in the
repo. Long calls are trimmed to the last 60,000 characters before sending, because the end of a call
matters more than the opening small talk.

## If the transcript is not in a browser tab

This reads the page, so it cannot see a desktop app (Teams, Zoom, Discord client) or a second monitor.
If yours is a desktop app, the honest options are OCR polling of a screen region or system-audio capture
with local speech-to-text — both are a different build, not a setting. Say so and I will scope it.

## Files

| File | What it does |
|---|---|
| `manifest.json` | MV3 wiring: content scripts, service worker, popup, options, only `openrouter.ai` as a host permission |
| `src/lib/harvest.js` | The spider itself — line diffing, sentence splitting, the offline summarizer and the prompt. Pure functions, no DOM |
| `src/content.js` | The panel, the element picker, the observer, the clock |
| `src/background.js` | Picks the engine, calls OpenRouter, falls back to offline on any error |
| `src/popup.html` | Toolbar popup: status, open, pick, start, stop, summarize |
| `src/options.html` | Key, model, auto-summary, the privacy note |
| `icons/` | 16/48/128 spider |

## Checks

`node --check` on all five scripts, plus a jsdom suite of **62 checks** covering the line diff under
append, repeat, re-render and virtualised scrolling; the offline summarizer's sections; prompt
truncation; the panel rendering in a shadow root; start/stop; summarize through the worker stub; copy;
and the manifest's permissions, content-script order and every referenced file.

## Not built yet

- Saving each call to a file or a small history list
- A true always-on-top desktop window (today the panel is always-on-top *within* the page)
- Speaker labels when the transcript provides them
