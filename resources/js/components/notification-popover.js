import { getJson, postJson } from '../utils/fetch-json.js';

export function formatNotificationCount(count) {
    return count > 99 ? '99+' : String(count);
}

export function setupNotificationPopover(root, options = {}) {
    const documentRef = options.documentRef ?? document;
    const get = options.get ?? getJson;
    const post = options.post ?? postJson;
    const navigate = options.navigate ?? ((url) => window.location.assign(url));
    const trigger = root.querySelector('[data-notification-popover-trigger]');
    const badge = root.querySelector('[data-notification-popover-badge]');
    const panel = root.querySelector('[data-notification-popover-panel]');

    if (! trigger || ! badge || ! panel) {
        return null;
    }

    const tabs = [...panel.querySelectorAll('[data-notification-popover-tab]')];
    const unreadCount = panel.querySelector('[data-notification-popover-unread-count]');
    const markAllButton = panel.querySelector('[data-notification-popover-mark-all]');
    const loading = panel.querySelector('[data-notification-popover-loading]');
    const empty = panel.querySelector('[data-notification-popover-empty]');
    const items = panel.querySelector('[data-notification-popover-items]');
    const rowTemplate = panel.querySelector('[data-notification-popover-row-template]');
    let activeTab = 'all';
    let currentUnreadCount = 0;
    let requestSequence = 0;

    function setUnreadCount(count) {
        currentUnreadCount = Math.max(0, Number(count) || 0);
        badge.textContent = formatNotificationCount(currentUnreadCount);
        badge.classList.toggle('hidden', currentUnreadCount === 0);
        unreadCount.textContent = String(currentUnreadCount);
        markAllButton.disabled = currentUnreadCount === 0;
        trigger.setAttribute('aria-label', `通知 (${currentUnreadCount} 件未読)`);
    }

    function setLoading(isLoading) {
        loading.classList.toggle('hidden', ! isLoading);
        items.classList.toggle('hidden', isLoading);

        if (isLoading) {
            empty.classList.add('hidden');
        }
    }

    function showError() {
        items.replaceChildren();
        empty.textContent = '通知を読み込めませんでした。もう一度お試しください。';
        empty.classList.remove('hidden');
    }

    function renderNotifications(notifications) {
        items.replaceChildren();
        empty.textContent = '通知はありません。';

        if (notifications.length === 0) {
            empty.classList.remove('hidden');

            return;
        }

        empty.classList.add('hidden');

        notifications.forEach((notification) => {
            const fragment = rowTemplate.content.cloneNode(true);
            const row = fragment.querySelector('[data-notification-popover-row]');
            const dot = fragment.querySelector('[data-notification-popover-row-dot]');
            const title = fragment.querySelector('[data-notification-popover-row-title]');
            const message = fragment.querySelector('[data-notification-popover-row-message]');
            const time = fragment.querySelector('[data-notification-popover-row-time]');

            row.href = notification.url;
            row.dataset.unread = notification.is_unread ? 'true' : 'false';
            dot.classList.toggle('hidden', ! notification.is_unread);
            title.classList.toggle('font-semibold', notification.is_unread);
            title.textContent = notification.title;
            message.textContent = notification.message;
            time.textContent = notification.created_at_human;

            row.addEventListener('click', async (event) => {
                event.preventDefault();

                try {
                    const result = await post(
                        `${panel.dataset.notificationsUrl}/${encodeURIComponent(notification.id)}/read`,
                    );
                    setUnreadCount(result.unread_count);
                    navigate(result.redirect_url ?? notification.url);
                } catch (error) {
                    showError();
                }
            });

            items.append(fragment);
        });
    }

    async function loadNotifications(tab = activeTab) {
        activeTab = tab === 'unread' ? 'unread' : 'all';
        const requestId = ++requestSequence;
        tabs.forEach((tabButton) => {
            tabButton.setAttribute(
                'aria-selected',
                tabButton.dataset.notificationPopoverTab === activeTab ? 'true' : 'false',
            );
        });
        setLoading(true);

        try {
            const separator = panel.dataset.notificationsUrl.includes('?') ? '&' : '?';
            const result = await get(
                `${panel.dataset.notificationsUrl}${separator}tab=${encodeURIComponent(activeTab)}`,
            );

            if (requestId !== requestSequence) {
                return;
            }

            setUnreadCount(result.unread_count);
            renderNotifications(result.notifications);
        } catch (error) {
            if (requestId === requestSequence) {
                showError();
            }
        } finally {
            if (requestId === requestSequence) {
                setLoading(false);
            }
        }
    }

    async function open() {
        panel.style.display = 'flex';
        panel.classList.remove('hidden', 'opacity-0', '-translate-y-1');
        trigger.setAttribute('aria-expanded', 'true');

        await loadNotifications(activeTab);
    }

    function close() {
        panel.classList.add('hidden', 'opacity-0', '-translate-y-1');
        panel.style.display = 'none';
        trigger.setAttribute('aria-expanded', 'false');
    }

    async function markAllAsRead() {
        markAllButton.disabled = true;

        try {
            const result = await post(panel.dataset.readAllUrl);
            setUnreadCount(result.unread_count);
            await loadNotifications(activeTab);
        } catch (error) {
            showError();
            markAllButton.disabled = currentUnreadCount === 0;
        }
    }

    trigger.addEventListener('click', async (event) => {
        event.stopPropagation();

        if (trigger.getAttribute('aria-expanded') === 'true') {
            close();
        } else {
            await open();
        }
    });

    tabs.forEach((tabButton) => {
        tabButton.addEventListener('click', async () => {
            await loadNotifications(tabButton.dataset.notificationPopoverTab);
        });
    });

    markAllButton.addEventListener('click', markAllAsRead);

    documentRef.addEventListener('click', (event) => {
        if (trigger.getAttribute('aria-expanded') === 'true' && ! root.contains(event.target)) {
            close();
        }
    });

    documentRef.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
            close();
        }
    });

    return { close, loadNotifications, markAllAsRead, open };
}

export function initNotificationPopovers(options = {}) {
    const documentRef = options.documentRef ?? document;

    return [...documentRef.querySelectorAll('[data-notification-popover-root]')]
        .map((root) => setupNotificationPopover(root, { ...options, documentRef }))
        .filter(Boolean);
}
