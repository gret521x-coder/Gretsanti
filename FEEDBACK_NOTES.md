# ✉ Feedback — the private inbox

The card lives in two places and they share one inbox:

| page | build | where the pill sits |
|---|---|---|
| `pindutan.html` — the hub (now **Alpha**) | `m14` | upper right of the header, level with the wordmark |
| `labs/pindutan-v21.html` — the arena | `v30` | upper right of the header, beside the wordmark |

## The thing that changed, and why

The first cut filled a `mailto:` addressed to the maker, which meant **the address was printed on the
page** — visible to anyone who opened the feedback card, and to anyone who viewed the source.

It is not printed any more. The pages know only a URL:

```
https://aidekupal.unaux.com/api-feedback.php
```

That script knows where the mail goes. The address lives on the server, in a text file you upload,
and nowhere in this repository, in the feed, or on either page.

## What to upload (one file, once)

1. **`api-feedback.php`** → the same folder as `api-leaderboard.php` on `aidekupal.unaux.com`.
2. **`feedback-to.txt`** → same folder. One line, the address the reports go to.
   ```
   you@example.com
   ```
   Without it the script still works: every report is written to the log, it is just never mailed.
3. *(optional)* **`feedback-key.txt`** → one line, a long random word. It unlocks `?recent=`.

Neither `.txt` file is in the repo, and neither should ever be committed.

If the host cannot send mail, nothing is lost — the log below is the safety net, and the page tells
the player the truth and offers **GitHub ↗** and **Copy report** instead of pretending.

## Reading what arrived

```
https://aidekupal.unaux.com/api-feedback.php?count=1
https://aidekupal.unaux.com/api-feedback.php?recent=YOUR-KEY
```

`count` is a pulse (how many reports have ever landed). `recent` returns the last twenty, newest
first, as JSON — each one already carries `kind`, `name`, `build`, `page`, `room`, `where`, `screen`,
`browser` and `when`.

`data/feedback.jsonl` holds every report, one JSON object per line; the script fences the folder off
with a `.htaccess` and an empty `index.html` as it creates it.

## What the page sends

```json
{
  "subject": "[Bug] the fortress chair is blank",
  "body": "the words…\n\n---\nkind: Bug\nfrom: KDL\nbuild: hub m14\n…",
  "kind": "Bug", "text": "the words…", "name": "KDL",
  "build": "hub m14", "page": "https://…/pindutan.html", "where": "the password gate",
  "screen": "390x844", "browser": "Mozilla/5.0 …", "when": "2026-11-24T10:00:00.000Z"
}
```

- At least **8 characters** of real text, or the button stays shut.
- **6 reports per hour per IP**; the seventh is turned away with `429`.
- Text is trimmed to 1200 characters, names to 40, the browser string to 180.
- An unknown `kind` is stored as `Bug`; a made-up address in `feedback-to.txt` is not mailed to.

## Moving the inbox

`labs/pindutan-build.json` has one field for it:

```json
"feedback": "https://aidekupal.unaux.com/api-feedback.php"
```

Point it at another script and both pages follow on their next open. A value that is not `https`
is ignored, and the built-in constant is used.

## Fallbacks, in order

1. **Send** → posts to the inbox.
2. If the inbox cannot be reached → the card says so, and **GitHub ↗** opens the same report as a new
   issue in `gret521x-coder/Gretsanti`.
3. **Copy report** → the whole thing (title + body) on the clipboard, always.

## Tests

`test_feedback_php.js` runs the real script under PHP 8.3 and checks 30 things: no address in the
script that ships, a report accepted and logged with every field, short and oversized text handled,
the hourly limit, `?count` and `?recent` (refused without the key, refused with the wrong one),
a made-up address never mailed to, and a bare `GET` that never leaks where the mail goes.
