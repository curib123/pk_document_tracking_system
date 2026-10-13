/* Checks the actual POST feedback lifecycle without a browser dependency. */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function submitter(attributes = {}) {
    return {formMethod: attributes.formmethod || 'get', formTarget: attributes.formtarget || '',
        hasAttribute(name) {return Object.hasOwn(attributes, name);}};
}

function setup() {
    const documentListeners = {}, windowListeners = {}, timers = new Map();
    let time = 0, nextTimer = 0;
    function node() {
        return {hidden: true, textContent: '', listeners: {},
            addEventListener(name, fn) { this.listeners[name] = fn; }};
    }
    const panel = node(), title = node(), message = node(), dismiss = node();
    const nodes = {appActionFeedback: panel, actionFeedbackTitle: title,
        actionFeedbackMessage: message, actionFeedbackDismiss: dismiss};
    const document = {
        getElementById(id) { return nodes[id] || null; },
        addEventListener(name, fn) {
            (documentListeners[name] ||= []).push(fn);
        }
    };
    const window = {
        setTimeout(fn, delay) {
            const id = ++nextTimer;
            timers.set(id, {fn, at: time + delay});
            return id;
        },
        clearTimeout(id) { timers.delete(id); },
        addEventListener(name, fn) { windowListeners[name] = fn; }
    };
    const script = 'public/assets/js/action-feedback.js';
    if (fs.existsSync(script)) vm.runInNewContext(fs.readFileSync(script, 'utf8'),
        {window, document}, {filename: script});
    function advance(amount) {
        time += amount;
        for (const [id, task] of [...timers]) {
            if (task.at > time) continue;
            timers.delete(id);
            task.fn();
        }
    }
    const form = {
        method: 'post', target: '', attrs: {},
        getAttribute(name) { return this.attrs[name] ?? null; },
        setAttribute(name, value) { this.attrs[name] = value; },
        removeAttribute(name) { delete this.attrs[name]; }
    };
    const dispatch = (name, event) => (documentListeners[name] || []).forEach(fn => fn(event));
    return {panel, title, message, dismiss, form, timers, windowListeners, advance, dispatch};
}

test('accepted POST submission shows one busy notification while the result loads', () => {
    const x = setup();
    x.dispatch('submit', {defaultPrevented: false, target: x.form});
    assert.equal(x.panel.hidden, true, 'quick responses should avoid a loader flash');
    x.advance(500);
    assert.equal(x.panel.hidden, false);
    assert.equal(x.form.attrs['aria-busy'], 'true');
    assert.match(x.message.textContent, /result/i);
    x.dispatch('submit', {defaultPrevented: false, target: x.form});
    assert.equal(x.timers.size, 1, 'another submit must not create another recovery timer');
});

test('canceled confirmation and later prevented submissions never become busy', () => {
    const x = setup();
    x.dispatch('submit', {defaultPrevented: true, target: x.form});
    x.advance(500);
    assert.equal(x.panel.hidden, true);
    const event = {defaultPrevented: false, target: x.form};
    x.dispatch('submit', event);
    event.defaultPrevented = true;
    x.advance(500);
    assert.equal(x.panel.hidden, true);
    assert.equal(x.form.attrs['aria-busy'], undefined);
});

test('filter forms and submissions to a separate tab do not show action feedback', () => {
    const x = setup();
    x.dispatch('submit', {defaultPrevented: false, target: {...x.form, method: 'get'}});
    x.dispatch('submit', {defaultPrevented: false, target: {...x.form, target: '_blank'}});
    x.dispatch('submit', {defaultPrevented: false, target: x.form,
        submitter: submitter({formtarget: '_blank', formmethod: 'post'})});
    x.dispatch('submit', {defaultPrevented: false, target: x.form,
        submitter: submitter({formmethod: 'get'})});
    x.advance(500);
    assert.equal(x.panel.hidden, true);
    assert.equal(x.timers.size, 0);
});

test('only explicit submit-button attributes override the form method and target', () => {
    const x = setup();
    x.dispatch('submit', {defaultPrevented: false, target: x.form, submitter: submitter()});
    x.advance(500);
    assert.equal(x.panel.hidden, false, 'an absent button override must preserve the POST method');
    x.windowListeners.pageshow?.();
    x.dispatch('submit', {defaultPrevented: false, target: {...x.form, target: '_blank'},
        submitter: submitter({formtarget: ''})});
    x.advance(500);
    assert.equal(x.panel.hidden, false, 'an explicit empty formtarget opens the current page');
});

test('a long wait is honest and dismissing it restores the existing busy state', () => {
    const x = setup();
    x.form.attrs['aria-busy'] = 'false';
    x.dispatch('submit', {defaultPrevented: false, target: x.form});
    x.advance(500);
    x.advance(30000);
    assert.equal(x.dismiss.hidden, false);
    assert.match(x.message.textContent, /still/i);
    assert.doesNotMatch(x.message.textContent, /success|failed|completed/i);
    x.dismiss.listeners.click?.();
    assert.equal(x.panel.hidden, true);
    assert.equal(x.form.attrs['aria-busy'], 'false');
    assert.equal(x.timers.size, 0);
});

test('returning through browser history clears both pending and visible feedback', () => {
    const x = setup();
    x.dispatch('submit', {defaultPrevented: false, target: x.form});
    x.advance(500);
    x.windowListeners.pageshow?.();
    assert.equal(x.panel.hidden, true);
    assert.equal(x.form.attrs['aria-busy'], undefined);
    x.dispatch('submit', {defaultPrevented: false, target: x.form});
    x.windowListeners.pageshow?.();
    x.advance(500);
    assert.equal(x.panel.hidden, true);
    assert.equal(x.timers.size, 0);
});

test('Escape dismisses feedback without interfering with the submitted form', () => {
    const x = setup();
    x.dispatch('submit', {defaultPrevented: false, target: x.form});
    x.advance(500);
    x.dispatch('keydown', {key: 'Escape'});
    assert.equal(x.panel.hidden, true);
    assert.equal(x.form.attrs['aria-busy'], undefined);
});
