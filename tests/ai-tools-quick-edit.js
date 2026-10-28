#!/usr/bin/env node
/**
 * Quick Edit guard in wp-plugin/omochix-core/admin/assets/admin.js.
 *
 * A number input holding "abc" or "1e" reports value "" with validity.badInput,
 * so it must not reach inlineEditPost.save() (the AJAX request) and delete the
 * stored rating. Loads admin.js in a VM with a minimal DOM stand-in.
 *
 * Run: node tests/ai-tools-quick-edit.js
 */
'use strict';

var fs = require('fs');
var path = require('path');
var vm = require('vm');

var source = fs.readFileSync(path.join(__dirname, '..', 'wp-plugin/omochix-core/admin/assets/admin.js'), 'utf8');
var pass = 0;
var fail = 0;
function check(name, ok) {
	if (ok) { pass++; console.log('PASS ' + name); } else { fail++; console.log('FAIL ' + name); }
}

function field(name, value, badInput) {
	var attrs = { 'data-omochix-error': name + ' error' };
	var label = { inserted: [], insertAdjacentElement: function (where, node) { this.inserted.push(node); node.parentNode = { removeChild: function () {} }; } };
	return {
		name: 'omochix_quick_meta[' + name + ']',
		value: value,
		validity: { badInput: !!badInput },
		focused: false,
		label: label,
		getAttribute: function (key) { return attrs[key] || null; },
		setAttribute: function (key, val) { attrs[key] = val; },
		removeAttribute: function (key) { delete attrs[key]; },
		hasAttr: function (key) { return key in attrs; },
		closest: function () { return label; },
		focus: function () { this.focused = true; }
	};
}

function load(confirmAnswer) {
	var saves = [];
	var confirms = [];
	var editRow = { fields: {}, querySelector: function (sel) {
		var m = /name="omochix_quick_meta\[(\w+)\]"/.exec(sel);
		return m ? this.fields[m[1]] || null : null;
	}, querySelectorAll: function () { return []; } };
	var window = {
		inlineEditPost: {
			edit: function () {},
			save: function (id) { saves.push(id); return 'sent'; },
			getId: function (el) { return el.postId; }
		},
		confirm: function (text) { confirms.push(text); return !!confirmAnswer; }
	};
	var document = {
		querySelector: function (sel) { return sel === '#edit-12' ? editRow : null; },
		createElement: function () { return { setAttribute: function (k, v) { this[k] = v; } }; }
	};
	vm.runInNewContext(source, { window: window, document: document, String: String, Number: Number, isFinite: isFinite, Array: Array });
	return { window: window, editRow: editRow, saves: saves, confirms: confirms };
}

// Pure value check.
var probe = load();
var valid = probe.window.omochixQuickEditValueIsValid;
check('validator is exposed', typeof valid === 'function');
if (typeof valid !== 'function') valid = function () { return null; }; // keep going so every check reports
[['', false, true], ['1', false, true], ['4.55', false, true], ['5', false, true], [' 3.5 ', false, true],
 ['0', false, false], ['0.9', false, false], ['5.01', false, false], ['7', false, false], ['-1', false, false],
 ['', true, false]].forEach(function (c) {
	check('rating "' + c[0] + '"' + (c[1] ? ' (badInput, e.g. "abc" / "1e")' : '') + ' is ' + (c[2] ? 'accepted' : 'rejected'), valid('rating_overall', c[0], c[1]) === c[2]);
});
[['0', false, true], ['12', false, true], ['', false, false], ['-3', false, false], ['12.5', false, false], ['1e2', false, false], ['', true, false]].forEach(function (c) {
	check('order "' + c[0] + '"' + (c[1] ? ' (badInput)' : '') + ' is ' + (c[2] ? 'accepted' : 'rejected'), valid('display_order', c[0], c[1]) === c[2]);
});

// save() wrapper: invalid input never reaches the original save (no AJAX).
function attempt(rating, ratingBad, order, orderBad, initialRating, confirmAnswer) {
	var env = load(confirmAnswer);
	env.editRow.fields.rating_overall = field('rating_overall', rating, ratingBad);
	env.editRow.fields.display_order = field('display_order', order, orderBad);
	// What inlineEditPost.edit() stores when Quick Edit opens, plus the PHP-translated prompt.
	env.editRow.fields.rating_overall.setAttribute('data-omochix-initial', initialRating || '');
	env.editRow.fields.rating_overall.setAttribute('data-omochix-confirm', 'delete rating?');
	var result = env.window.inlineEditPost.save(12);
	return { env: env, result: result, r: env.editRow.fields.rating_overall, o: env.editRow.fields.display_order };
}

var a = attempt('', true, '7', false);
check('save: rating "abc" (badInput) is blocked, no AJAX, error shown next to the field', a.result === false && a.env.saves.length === 0 && a.r.label.inserted.length === 1 && a.r.hasAttr('aria-invalid') && a.r.focused);
var notice = a.r.label.inserted[0] || {};
check('save: the error text comes from the field (translated in PHP) and is an alert', notice.textContent === 'rating_overall error' && notice.role === 'alert');
a = attempt('4.2', false, '-3', false);
check('save: order "-3" is blocked, no AJAX', a.result === false && a.env.saves.length === 0 && a.o.label.inserted.length === 1 && a.r.label.inserted.length === 0);
a = attempt('9', false, '', true);
check('save: both invalid shows two errors and focuses the rating', a.result === false && a.env.saves.length === 0 && a.r.label.inserted.length === 1 && a.o.label.inserted.length === 1 && a.r.focused && !a.o.focused);
a = attempt('', false, '7', false);
check('save: an intentionally blank rating is sent (deletes the rating)', a.result === 'sent' && a.env.saves.length === 1);
a = attempt('4.55', false, '0', false);
check('save: valid rating and order are sent', a.result === 'sent' && a.env.saves.length === 1 && a.r.label.inserted.length === 0);
var other = load();
check('save: rows without OmochiX fields are passed through', other.window.inlineEditPost.save(99) === 'sent' && other.saves.length === 1);

// Clearing a rating the tool had when Quick Edit opened asks first.
a = attempt('', false, '7', false, '4.2', true);
check('confirm OK: clearing an existing rating asks with the translated text, then sends (deletes)', a.env.confirms.length === 1 && a.env.confirms[0] === 'delete rating?' && a.result === 'sent' && a.env.saves.length === 1);
a = attempt('', false, '7', false, '4.2', false);
check('confirm Cancel: nothing is sent and Quick Edit stays open (save returns false)', a.env.confirms.length === 1 && a.result === false && a.env.saves.length === 0 && a.r.label.inserted.length === 0);
a = attempt('  ', false, '7', false, '4.2', false);
check('confirm Cancel: a whitespace-only rating counts as blank and is also confirmed', a.env.confirms.length === 1 && a.env.saves.length === 0);
a = attempt('', false, '7', false, '', false);
check('no confirm: a tool that was unrated stays blank without asking', a.env.confirms.length === 0 && a.result === 'sent' && a.env.saves.length === 1);
a = attempt('3.5', false, '7', false, '4.2', false);
check('no confirm: changing the rating to another valid value does not ask', a.env.confirms.length === 0 && a.result === 'sent');
a = attempt('', false, '-3', false, '4.2', true);
check('invalid input is reported before the confirm: no prompt, no AJAX', a.env.confirms.length === 0 && a.result === false && a.env.saves.length === 0 && a.o.label.inserted.length === 1);
a = attempt('', true, '7', false, '4.2', true);
check('rating "1e" (badInput) on a rated tool shows the error, not the confirm', a.env.confirms.length === 0 && a.env.saves.length === 0 && a.r.label.inserted.length === 1);

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
