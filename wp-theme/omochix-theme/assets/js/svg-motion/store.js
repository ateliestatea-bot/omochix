/**
 * SVG Motion Studio: what stays in this browser between visits. IndexedDB holds
 * the normalized logo and the settings. Nothing here is ever sent anywhere, and
 * whatever comes back is treated as untrusted by the caller.
 */
(function (root) {
    'use strict';

    var DB_NAME = 'omx-svg-motion';
    var STORE = 'state';
    var KEY = 'current';
    var SCHEMA = 1;
    var LEGACY_KEY = 'omx-svg-motion-state'; // S1-B: localStorage, settings only

    function open() {
        return new Promise(function (resolve, reject) {
            if (!root.indexedDB) { reject(new Error('UNAVAILABLE')); return; }
            var request = root.indexedDB.open(DB_NAME, 1);
            request.onupgradeneeded = function () { request.result.createObjectStore(STORE); };
            request.onsuccess = function () { resolve(request.result); };
            request.onerror = function () { reject(request.error || new Error('UNAVAILABLE')); };
        });
    }

    function run(mode, action) {
        return open().then(function (db) {
            return new Promise(function (resolve, reject) {
                var transaction = db.transaction(STORE, mode);
                var request = action(transaction.objectStore(STORE));
                transaction.oncomplete = function () { db.close(); resolve(request.result); };
                transaction.onerror = transaction.onabort = function () { db.close(); reject(transaction.error || new Error('FAILED')); };
            });
        });
    }

    function legacy() {
        try {
            var saved = JSON.parse(root.localStorage.getItem(LEGACY_KEY) || 'null');
            return saved && typeof saved === 'object' ? { preset: saved.preset, sample: saved.sample, params: saved.params } : null;
        } catch (error) {
            return null;
        }
    }

    function clearLegacy() {
        try { root.localStorage.removeItem(LEGACY_KEY); } catch (error) { /* nothing to clear */ }
    }

    /**
     * @return {Promise<{status: string, record: object|null}>} status: 'none', 'ok', 'legacy',
     *     'discarded' (an unknown format was removed) or 'unavailable'.
     */
    function load() {
        return run('readonly', function (store) { return store.get(KEY); }).then(function (record) {
            if (!record) {
                var old = legacy();
                return old ? { status: 'legacy', record: { schema: SCHEMA, svg: null, settings: old } } : { status: 'none', record: null };
            }
            if (typeof record !== 'object' || record.schema !== SCHEMA) {
                return remove().then(function () { return { status: 'discarded', record: null }; });
            }
            return { status: 'ok', record: record };
        }, function () {
            var old = legacy();
            return { status: 'unavailable', record: old ? { schema: SCHEMA, svg: null, settings: old } : null };
        });
    }

    /** @param {string|null} svg Normalized SVG, never the original file. */
    function save(svg, settings) {
        return run('readwrite', function (store) {
            return store.put({ schema: SCHEMA, svg: svg, settings: settings }, KEY);
        }).then(function () { clearLegacy(); return true; }, function () { return false; });
    }

    function remove() {
        clearLegacy();
        return run('readwrite', function (store) { return store.delete(KEY); }).then(function () { return true; }, function () { return false; });
    }

    root.OmxSvgStore = { load: load, save: save, remove: remove, SCHEMA: SCHEMA };
}(window));
