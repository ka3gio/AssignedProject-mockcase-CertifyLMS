import assert from 'node:assert/strict';
import test from 'node:test';

import {
    formatNotificationCount,
    setupNotificationPopover,
} from '../../resources/js/components/notification-popover.js';

class FakeClassList {
    constructor(classes = []) {
        this.classes = new Set(classes);
    }

    add(...classes) {
        classes.forEach((className) => this.classes.add(className));
    }

    remove(...classes) {
        classes.forEach((className) => this.classes.delete(className));
    }

    toggle(className, force) {
        const enabled = force ?? !this.classes.has(className);
        enabled ? this.classes.add(className) : this.classes.delete(className);

        return enabled;
    }

    contains(className) {
        return this.classes.has(className);
    }
}

class FakeElement {
    constructor({ dataset = {}, classes = [] } = {}) {
        this.dataset = { ...dataset };
        this.classList = new FakeClassList(classes);
        this.attributes = new Map();
        this.listeners = new Map();
        this.queries = new Map();
        this.queryLists = new Map();
        this.children = [];
        this.style = {};
        this.textContent = '';
        this.href = '';
        this.disabled = false;
    }

    querySelector(selector) {
        return this.queries.get(selector) ?? null;
    }

    querySelectorAll(selector) {
        return this.queryLists.get(selector) ?? [];
    }

    setAttribute(name, value) {
        this.attributes.set(name, String(value));
    }

    getAttribute(name) {
        return this.attributes.get(name) ?? null;
    }

    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) ?? [];
        listeners.push(listener);
        this.listeners.set(type, listeners);
    }

    async emit(type, overrides = {}) {
        const event = {
            target: this,
            currentTarget: this,
            preventDefault() {},
            stopPropagation() {},
            ...overrides,
        };

        for (const listener of this.listeners.get(type) ?? []) {
            await listener(event);
        }
    }

    replaceChildren() {
        this.children = [];
    }

    append(child) {
        this.children.push(child);
    }

    contains(target) {
        return target === this;
    }
}

function createRowFragment() {
    const fragment = new FakeElement();
    const row = new FakeElement();
    const dot = new FakeElement();
    const title = new FakeElement({ classes: ['font-semibold'] });
    const message = new FakeElement();
    const time = new FakeElement();

    fragment.queries.set('[data-notification-popover-row]', row);
    fragment.queries.set('[data-notification-popover-row-dot]', dot);
    fragment.queries.set('[data-notification-popover-row-title]', title);
    fragment.queries.set('[data-notification-popover-row-message]', message);
    fragment.queries.set('[data-notification-popover-row-time]', time);

    return fragment;
}

function createFixture() {
    const root = new FakeElement();
    const documentRef = new FakeElement();
    const trigger = new FakeElement();
    const badge = new FakeElement({ classes: ['hidden'] });
    const panel = new FakeElement({
        dataset: {
            notificationsUrl: '/api/v1/notifications',
            readAllUrl: '/api/v1/notifications/read-all',
        },
        classes: ['hidden', 'opacity-0', '-translate-y-1'],
    });
    const allTab = new FakeElement({ dataset: { notificationPopoverTab: 'all' } });
    const unreadTab = new FakeElement({ dataset: { notificationPopoverTab: 'unread' } });
    const unreadCount = new FakeElement();
    const markAll = new FakeElement();
    const loading = new FakeElement({ classes: ['hidden'] });
    const empty = new FakeElement({ classes: ['hidden'] });
    const items = new FakeElement();
    const template = new FakeElement();

    template.content = { cloneNode: () => createRowFragment() };
    trigger.setAttribute('aria-expanded', 'false');

    root.queries.set('[data-notification-popover-trigger]', trigger);
    root.queries.set('[data-notification-popover-badge]', badge);
    root.queries.set('[data-notification-popover-panel]', panel);
    panel.queryLists.set('[data-notification-popover-tab]', [allTab, unreadTab]);
    panel.queries.set('[data-notification-popover-unread-count]', unreadCount);
    panel.queries.set('[data-notification-popover-mark-all]', markAll);
    panel.queries.set('[data-notification-popover-loading]', loading);
    panel.queries.set('[data-notification-popover-empty]', empty);
    panel.queries.set('[data-notification-popover-items]', items);
    panel.queries.set('[data-notification-popover-row-template]', template);

    return {
        root,
        documentRef,
        trigger,
        badge,
        panel,
        allTab,
        unreadTab,
        unreadCount,
        markAll,
        loading,
        empty,
        items,
    };
}

function deferred() {
    let resolve;
    const promise = new Promise((resolvePromise) => {
        resolve = resolvePromise;
    });

    return { promise, resolve };
}

test('opening the popover fetches all notifications and renders escaped text state', async () => {
    const fixture = createFixture();
    const getCalls = [];
    const get = async (url) => {
        getCalls.push(url);

        return {
            notifications: [{
                id: 'notification-1',
                title: '<b>タイトル</b>',
                message: '本文',
                url: '/dashboard',
                is_unread: true,
                created_at: '2026-09-16T12:00:00+09:00',
                created_at_human: '1分前',
            }],
            unread_count: 120,
        };
    };

    const popover = setupNotificationPopover(fixture.root, {
        documentRef: fixture.documentRef,
        get,
        post: async () => ({}),
        navigate() {},
    });

    await popover.open();

    assert.deepEqual(getCalls, ['/api/v1/notifications?tab=all']);
    assert.equal(fixture.trigger.getAttribute('aria-expanded'), 'true');
    assert.equal(fixture.panel.style.display, 'flex');
    assert.equal(fixture.badge.textContent, '99+');
    assert.equal(fixture.unreadCount.textContent, '120');
    assert.equal(fixture.items.children.length, 1);

    const fragment = fixture.items.children[0];
    assert.equal(fragment.querySelector('[data-notification-popover-row-title]').textContent, '<b>タイトル</b>');
    assert.equal(fragment.querySelector('[data-notification-popover-row]').dataset.unread, 'true');
});

test('unread tab fetches the server-side unread collection and mark-all refreshes it', async () => {
    const fixture = createFixture();
    const getCalls = [];
    const postCalls = [];
    const get = async (url) => {
        getCalls.push(url);

        return { notifications: [], unread_count: 0 };
    };
    const post = async (url) => {
        postCalls.push(url);

        return { unread_count: 0 };
    };

    setupNotificationPopover(fixture.root, {
        documentRef: fixture.documentRef,
        get,
        post,
        navigate() {},
    });

    await fixture.unreadTab.emit('click');
    await fixture.markAll.emit('click');

    assert.deepEqual(getCalls, [
        '/api/v1/notifications?tab=unread',
        '/api/v1/notifications?tab=unread',
    ]);
    assert.deepEqual(postCalls, ['/api/v1/notifications/read-all']);
    assert.equal(fixture.unreadTab.getAttribute('aria-selected'), 'true');
    assert.equal(fixture.markAll.disabled, true);
});

test('clicking an unread row marks it as read before navigating', async () => {
    const fixture = createFixture();
    const postCalls = [];
    const destinations = [];
    const get = async () => ({
        notifications: [{
            id: 'notification/1',
            title: '通知',
            message: '本文',
            url: '/dashboard',
            is_unread: true,
            created_at: '2026-09-16T12:00:00+09:00',
            created_at_human: '1分前',
        }],
        unread_count: 1,
    });
    const post = async (url) => {
        postCalls.push(url);

        return { redirect_url: '/dashboard', unread_count: 0 };
    };

    const popover = setupNotificationPopover(fixture.root, {
        documentRef: fixture.documentRef,
        get,
        post,
        navigate: (url) => destinations.push(url),
    });

    await popover.open();
    const row = fixture.items.children[0].querySelector('[data-notification-popover-row]');
    await row.emit('click');

    assert.deepEqual(postCalls, ['/api/v1/notifications/notification%2F1/read']);
    assert.deepEqual(destinations, ['/dashboard']);
    assert.equal(fixture.badge.classList.contains('hidden'), true);
});

test('notification count display follows the existing topbar 99-plus format', () => {
    assert.equal(formatNotificationCount(0), '0');
    assert.equal(formatNotificationCount(99), '99');
    assert.equal(formatNotificationCount(100), '99+');
});

test('empty state remains visible after loading an empty tab', async () => {
    const fixture = createFixture();
    const popover = setupNotificationPopover(fixture.root, {
        documentRef: fixture.documentRef,
        get: async () => ({ notifications: [], unread_count: 0 }),
        post: async () => ({}),
        navigate() {},
    });

    await popover.open();

    assert.equal(fixture.loading.classList.contains('hidden'), true);
    assert.equal(fixture.empty.classList.contains('hidden'), false);
    assert.equal(fixture.empty.textContent, '通知はありません。');
});

test('a stale request cannot overwrite the active tab response', async () => {
    const fixture = createFixture();
    const allResponse = deferred();
    const unreadResponse = deferred();
    const get = (url) => url.endsWith('tab=all') ? allResponse.promise : unreadResponse.promise;
    const popover = setupNotificationPopover(fixture.root, {
        documentRef: fixture.documentRef,
        get,
        post: async () => ({}),
        navigate() {},
    });

    const allRequest = popover.loadNotifications('all');
    const unreadRequest = popover.loadNotifications('unread');

    unreadResponse.resolve({
        notifications: [{
            id: 'unread',
            title: '未読タブの通知',
            message: '',
            url: '/dashboard',
            is_unread: true,
            created_at: '2026-09-16T12:00:00+09:00',
            created_at_human: '1分前',
        }],
        unread_count: 1,
    });
    await unreadRequest;

    allResponse.resolve({
        notifications: [{
            id: 'read',
            title: '遅れて返った全件通知',
            message: '',
            url: '/dashboard',
            is_unread: false,
            created_at: '2026-09-16T11:00:00+09:00',
            created_at_human: '1時間前',
        }],
        unread_count: 1,
    });
    await allRequest;

    const title = fixture.items.children[0]
        .querySelector('[data-notification-popover-row-title]')
        .textContent;
    assert.equal(title, '未読タブの通知');
    assert.equal(fixture.unreadTab.getAttribute('aria-selected'), 'true');
});
