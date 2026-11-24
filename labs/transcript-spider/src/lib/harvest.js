/* Spider harvest helpers — pure functions, no DOM, no chrome APIs.
   Loaded by the content script, the service worker (importScripts) and the test suite. */
(function (root) {
    'use strict';

    var STOPWORDS = ('a about above after again against all am an and any are as at be because been before being ' +
        'below between both but by can cannot could did do does doing down during each few for from further had has ' +
        'have having he her here hers herself him himself his how i if in into is it its itself just me more most my ' +
        'myself no nor not now of off on once only or other our ours ourselves out over own same she should so some ' +
        'such than that the their theirs them themselves then there these they this those through to too under until ' +
        'up very was we were what when where which while who whom why will with would you your yours yourself ' +
        'yourselves ok okay yeah yes right like going gonna um uh hmm well really thing things know think mean ' +
        'got get also going just said say says').split(' ');

    var STOP = {};
    STOPWORDS.forEach(function (w) { STOP[w] = true; });

    var ACTION = /\b(will|i'll|we'll|you'll|need to|needs to|have to|has to|should|must|make sure|follow up|follow-up|send|schedule|by (mon|tues|wednes|thurs|fri|satur|sun)day|by (tomorrow|tonight|eod|end of (day|week))|by \d|deadline|todo|to-do|action item|next step|take care of|look into|check with)\b/i;
    var DECISION = /\b(decided|decision|agreed|we('| a)re going with|we'll go with|final(i[sz]ed)?|approved|sign(ed)? off|locked in|conclusion)\b/i;

    function normalize(line) {
        return String(line == null ? '' : line).replace(/\s+/g, ' ').trim();
    }

    function splitLines(text) {
        return String(text == null ? '' : text)
            .split(/\r?\n/)
            .map(normalize)
            .filter(function (l) { return l.length > 0; });
    }

    /**
     * The core of the spider: given everything the transcript box says now, and a running
     * count of every line already harvested, return the lines that are genuinely new.
     *
     * Counting, not set membership, so a line that legitimately repeats ("Yeah") is captured
     * the second time it is said but never twice from the same snapshot.
     */
    function extractNewLines(text, alreadyHarvested) {
        var previous = alreadyHarvested || {};
        var seen = {};
        var fresh = [];

        splitLines(text).forEach(function (line) {
            seen[line] = (seen[line] || 0) + 1;
            if (seen[line] > (previous[line] || 0)) fresh.push(line);
        });

        return { fresh: fresh, counts: seen };
    }

    function splitSentences(text) {
        return String(text == null ? '' : text)
            .replace(/\s+/g, ' ')
            .split(/(?<=[.!?])\s+(?=[A-Z0-9"'(])/)
            .map(normalize)
            .filter(function (s) { return s.length > 24; });
    }

    function wordFrequency(text) {
        var freq = {};
        String(text == null ? '' : text).toLowerCase()
            .replace(/[^a-z0-9'\s]/g, ' ')
            .split(/\s+/)
            .forEach(function (w) {
                if (w.length < 4 || STOP[w] || /^\d+$/.test(w)) return;
                freq[w] = (freq[w] || 0) + 1;
            });
        return freq;
    }

    function scoreSentence(sentence, freq) {
        var words = sentence.toLowerCase().replace(/[^a-z0-9'\s]/g, ' ').split(/\s+/);
        var score = 0;
        for (var i = 0; i < words.length; i++) {
            if (freq[words[i]]) score += freq[words[i]];
        }
        return words.length ? score / Math.sqrt(words.length) : 0;
    }

    function bulletsFrom(matches, sentences, max) {
        var out = [];
        for (var i = 0; i < sentences.length && out.length < max; i++) {
            if (matches(sentences[i])) out.push('- ' + sentences[i]);
        }
        return out;
    }

    /**
     * No key, no network: a readable summary built from the transcript itself.
     * Keyword frequency picks the sentences that carry the call, and two pattern lists
     * pull out the parts people actually act on.
     */
    function offlineSummary(transcript, options) {
        options = options || {};
        var maxBullets = options.bullets || 6;
        var sentences = splitSentences(transcript);
        if (!sentences.length) return '_Not enough transcript to summarize yet._';

        var freq = wordFrequency(transcript);
        var ranked = sentences.map(function (s, i) {
            return { text: s, index: i, score: scoreSentence(s, freq) };
        }).sort(function (a, b) { return b.score - a.score; });

        var picked = ranked.slice(0, maxBullets)
            .sort(function (a, b) { return a.index - b.index; })
            .map(function (r) { return '- ' + r.text; });

        var actions = bulletsFrom(function (s) { return ACTION.test(s); }, sentences, 6);
        var decisions = bulletsFrom(function (s) { return DECISION.test(s); }, sentences, 4);

        var parts = ['**Summary**'].concat(picked);
        if (decisions.length) parts = parts.concat(['', '**Decisions**']).concat(decisions);
        if (actions.length) parts = parts.concat(['', '**Action items**']).concat(actions);
        parts = parts.concat(['', '_Offline summary — no key set, nothing left this computer._']);
        return parts.join('\n');
    }

    function buildPrompt(transcript, maxChars) {
        var text = String(transcript == null ? '' : transcript);
        var limit = maxChars || 60000;
        var truncated = false;
        if (text.length > limit) {
            text = text.slice(text.length - limit);      /* the end of a call matters most */
            truncated = true;
        }
        return {
            system: 'You are a minute-taker. From the meeting transcript, reply in markdown with exactly ' +
                    'these sections, each a short bullet list: "## Summary" (3-6 bullets), "## Decisions", ' +
                    '"## Action items" (name an owner when the transcript says one), "## Open questions". ' +
                    'Write only what the transcript supports. No preamble, no closing remarks. ' +
                    'If a section has nothing, write "- none".',
            user: (truncated ? '(earlier part of the call was dropped — this is the tail)\n\n' : '') +
                  'Transcript:\n' + text
        };
    }

    function clockOf(ms) {
        var d = new Date(ms);
        function p(n) { return String(n).padStart(2, '0'); }
        return p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
    }

    function transcriptText(lines) {
        return (lines || []).map(function (l) { return l.text; }).join('\n');
    }

    root.SpiderHarvest = {
        normalize: normalize,
        splitLines: splitLines,
        splitSentences: splitSentences,
        extractNewLines: extractNewLines,
        offlineSummary: offlineSummary,
        buildPrompt: buildPrompt,
        clockOf: clockOf,
        transcriptText: transcriptText,
        ACTION: ACTION,
        DECISION: DECISION
    };
})(typeof self !== 'undefined' ? self : this);
