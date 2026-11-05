/**
 * OmochiX Labs: token-count ESTIMATE for the API cost calculator.
 *
 * Real counts depend on each model's tokenizer, which this tool does not
 * ship. Instead text is split into character classes and each class gets a
 * typical tokens-per-unit rate plus a low / high band; the page always
 * labels the result as an estimate (推定値). The rates are documented on the
 * tool page and in tests/labs/core.js.
 */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module && module.exports) module.exports = api;
    else root.OmochixLabsTokens = api;
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    /** Tokens per unit: [typical, low, high]. */
    var RATES = {
        cjk: [1.0, 0.7, 1.4],        // per character (kanji, kana, full-width)
        word: [1.3, 1.0, 1.7],       // per Latin word (letters)
        digits: [0.34, 0.25, 1.0],   // per digit (tokenizers group 1-3 digits)
        symbol: [1.0, 0.6, 1.2],     // per punctuation / symbol character
        newline: [0.5, 0.2, 1.0],    // per line break
        other: [2.0, 1.0, 3.0]       // per emoji or other character
    };

    var PATTERN = /([぀-ヿ㐀-䶿一-鿿豈-﫿＀-￯　-〿])|([A-Za-zÀ-ɏ]+)|([0-9]+)|(\n)|([ \t\r\f\v]+)|([!-/:-@[-`{-~‐-⁯])|([\s\S])/gu;

    /**
     * @return {{chars: number, tokens: number, low: number, high: number, counts: object}}
     */
    function estimate(text) {
        var s = String(text || '');
        var counts = { cjk: 0, word: 0, digits: 0, symbol: 0, newline: 0, other: 0, space: 0 };
        var m;
        PATTERN.lastIndex = 0;
        while ((m = PATTERN.exec(s)) !== null) {
            if (m[1]) counts.cjk++;
            else if (m[2]) counts.word += Math.max(1, Math.ceil(m[2].length / 6)); // long words split into pieces
            else if (m[3]) counts.digits += m[3].length;
            else if (m[4]) counts.newline++;
            else if (m[5]) counts.space++;
            else if (m[6]) counts.symbol++;
            else counts.other++;
        }
        var typical = 0, low = 0, high = 0;
        Object.keys(RATES).forEach(function (key) {
            typical += counts[key] * RATES[key][0];
            low += counts[key] * RATES[key][1];
            high += counts[key] * RATES[key][2];
        });
        return {
            chars: Array.from(s).length,
            tokens: Math.ceil(typical),
            low: Math.floor(low),
            high: Math.ceil(high),
            counts: counts
        };
    }

    return { RATES: RATES, estimate: estimate };
}));
