(function () {
    'use strict';

    let currentClientId = null;
    let currentClientData = null;
    let clientsCache = [];

    const apiBase = OC.generateUrl('/apps/minicrm/api/v1');

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }

    function initApp() {
        const searchInput = document.getElementById('client-search-input');
        const channelSelect = document.getElementById('reply-channel-select');
        const subjectInput = document.getElementById('reply-subject-input');
        const sendButton = document.getElementById('reply-send-button');
        const editNameInput = document.getElementById('input-edit-client-name');

        if (searchInput) {
            searchInput.addEventListener('input', debounce((e) => {
                filterClients(e.target.value);
            }, 250));
        }

        if (channelSelect) {
            channelSelect.addEventListener('change', (e) => {
                if (subjectInput) {
                    subjectInput.style.display = e.target.value === 'email' ? 'inline-block' : 'none';
                }
            });
        }

        if (sendButton) {
            sendButton.addEventListener('click', handleSendMessage);
        }

        if (editNameInput) {
            editNameInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') handleSaveClientName();
                if (e.key === 'Escape') closeClientNameEditor();
            });
        }

        // Global keydown listener for Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllModals();
                closeClientNameEditor();
            }
        });

        // Global click listener with event delegation - ensures 100% reliable button clicks
        document.addEventListener('click', (e) => {
            // 1. Header action buttons (Contact, Files, Actions)
            if (e.target.closest('#tab-btn-contact')) {
                e.preventDefault();
                openContactModal();
                return;
            }

            if (e.target.closest('#tab-btn-files')) {
                e.preventDefault();
                openFilesAction();
                return;
            }

            if (e.target.closest('#tab-btn-actions')) {
                e.preventDefault();
                openActionsModal();
                return;
            }

            // 2. Modals close buttons
            if (e.target.closest('#btn-close-contact-modal') || e.target.closest('#btn-cancel-contact-modal')) {
                e.preventDefault();
                closeContactModal();
                return;
            }

            if (e.target.closest('#btn-close-actions-modal') || e.target.closest('#btn-cancel-actions-modal')) {
                e.preventDefault();
                closeActionsModal();
                return;
            }

            // Backdrop click closes modals
            if (e.target.classList.contains('minicrm-modal-backdrop')) {
                closeAllModals();
                return;
            }

            // 3. Client Name Edit button
            const editBtn = e.target.closest('#btn-edit-client-name');
            if (editBtn) {
                e.preventDefault();
                openClientNameEditor();
                return;
            }

            // 4. Client Name Save button
            const saveBtn = e.target.closest('#btn-save-client-name');
            if (saveBtn) {
                e.preventDefault();
                handleSaveClientName();
                return;
            }

            // 5. Client Name Cancel button
            const cancelBtn = e.target.closest('#btn-cancel-client-name');
            if (cancelBtn) {
                e.preventDefault();
                closeClientNameEditor();
                return;
            }

            // 6. Copy Phone button in modal
            const copyPhoneBtn = e.target.closest('#btn-modal-copy-phone') || e.target.closest('#btn-copy-phone');
            if (copyPhoneBtn) {
                e.preventDefault();
                copyText('modal-contact-phone', copyPhoneBtn);
                return;
            }

            // 7. Copy Email button in modal
            const copyEmailBtn = e.target.closest('#btn-modal-copy-email') || e.target.closest('#btn-copy-email');
            if (copyEmailBtn) {
                e.preventDefault();
                copyText('modal-contact-email', copyEmailBtn);
                return;
            }

            // 8. Copy FileDrop button in activities modal
            const copyActFiledrop = e.target.closest('.btn-copy-activity-filedrop');
            if (copyActFiledrop) {
                e.preventDefault();
                const url = copyActFiledrop.dataset.url;
                if (url) {
                    navigator.clipboard.writeText(url).then(() => {
                        const orig = copyActFiledrop.textContent;
                        copyActFiledrop.textContent = '✔️';
                        setTimeout(() => { copyActFiledrop.textContent = orig; }, 1500);
                    });
                }
                return;
            }

            // 9. Sync Contact to Nextcloud Contacts button (in modal)
            const syncContactBtn = e.target.closest('#btn-modal-sync-contact') || e.target.closest('#btn-sync-contact');
            if (syncContactBtn) {
                e.preventDefault();
                handleSyncContact(syncContactBtn);
                return;
            }
        });

        loadClients();
    }

    async function loadClients() {
        const clientList = document.getElementById('client-list');
        try {
            const response = await fetch(`${apiBase}/clients`, {
                headers: { 'requesttoken': OC.requestToken }
            });
            const data = await response.json();
            clientsCache = data.clients || [];
            renderClientList(clientsCache);

            // Auto-select first client on load if none selected
            if (clientsCache.length > 0 && !currentClientId) {
                selectClient(clientsCache[0].id);
            }
        } catch (err) {
            if (clientList) {
                clientList.innerHTML = '<li class="loading-placeholder">Ошибка загрузки клиентов</li>';
            }
        }
    }

    function renderClientList(clients) {
        const listEl = document.getElementById('client-list');
        if (!listEl) return;

        if (clients.length === 0) {
            listEl.innerHTML = '<li class="loading-placeholder">Клиенты не найдены</li>';
            return;
        }

        listEl.innerHTML = '';
        clients.forEach(client => {
            const li = document.createElement('li');
            li.className = `minicrm-client-item ${client.id === currentClientId ? 'active' : ''}`;
            li.dataset.clientId = client.id;
            li.innerHTML = `
                <div class="client-item-name">${escapeHtml(client.full_name)}</div>
                <div class="client-item-contact">${escapeHtml(client.phone || client.email || 'Нет контактов')}</div>
            `;
            li.addEventListener('click', () => selectClient(client.id));
            listEl.appendChild(li);
        });
    }

    function filterClients(query) {
        const q = (query || '').toLowerCase();
        const filtered = clientsCache.filter(c => 
            (c.full_name && c.full_name.toLowerCase().includes(q)) ||
            (c.phone && c.phone.includes(q)) ||
            (c.email && c.email.toLowerCase().includes(q))
        );
        renderClientList(filtered);
    }

    async function selectClient(clientId) {
        currentClientId = clientId;
        document.querySelectorAll('.minicrm-client-item').forEach(el => {
            el.classList.toggle('active', parseInt(el.dataset.clientId, 10) === clientId);
        });

        const emptyView = document.getElementById('client-view-empty');
        const detailView = document.getElementById('client-view-detail');
        if (emptyView) emptyView.style.display = 'none';
        if (detailView) detailView.style.display = 'flex';

        // Pre-populate immediately from local cache so buttons work instantly
        const cachedClient = clientsCache.find(c => c.id === clientId);
        if (cachedClient) {
            currentClientData = {
                client: cachedClient,
                activities: [],
                identities: [],
                contact_card: { exists: false }
            };
            renderClientDetail(currentClientData);
        }

        try {
            const [clientRes, timelineRes] = await Promise.all([
                fetch(`${apiBase}/clients/${clientId}`, { headers: { 'requesttoken': OC.requestToken } }),
                fetch(`${apiBase}/clients/${clientId}/timeline`, { headers: { 'requesttoken': OC.requestToken } })
            ]);

            if (clientRes.ok) {
                const clientData = await clientRes.json();
                if (clientData && clientData.client) {
                    currentClientData = clientData;
                    renderClientDetail(clientData);
                }
            } else {
                console.warn('API error fetching client details:', clientRes.status);
            }

            if (timelineRes.ok) {
                const timelineData = await timelineRes.json();
                renderTimeline(timelineData.messages || []);
            }
        } catch (err) {
            console.error('Error fetching client details:', err);
        }
    }

    function renderClientDetail(data) {
        currentClientData = data;
        const client = data.client;
        const activities = data.activities || [];
        const latestActivity = activities.length > 0 ? activities[0] : null;

        const editNameBox = document.getElementById('client-name-edit-box');
        if (editNameBox) editNameBox.style.display = 'none';

        const nameEl = document.getElementById('detail-client-name');
        if (nameEl) nameEl.textContent = client.full_name;

        const idBadge = document.getElementById('detail-client-id-badge');
        if (idBadge) idBadge.textContent = `ID: #${client.id}`;

        const headerStatusEl = document.getElementById('detail-activity-status-badge') || document.getElementById('detail-activity-status-header');
        if (headerStatusEl) headerStatusEl.textContent = latestActivity ? latestActivity.status : 'active';
    }

    function renderTimeline(messages) {
        const stream = document.getElementById('detail-timeline-stream');
        if (!stream) return;

        if (messages.length === 0) {
            stream.innerHTML = '<div class="timeline-empty">Сообщений пока нет</div>';
            return;
        }

        stream.innerHTML = '';
        messages.forEach(msg => {
            const bubble = document.createElement('div');
            const direction = msg.direction || 'inbound';
            const channel = msg.channel || 'email';

            bubble.className = `timeline-bubble ${direction} ${channel === 'system' ? 'system' : ''}`;

            const channelIcons = {
                telegram: '✈️ Telegram',
                whatsapp: '💬 WhatsApp',
                email: '✉️ Email',
                facebook: '📘 Facebook',
                system: '⚙️ Система'
            };

            const icon = channelIcons[channel] || channel;
            const time = msg.created_at ? new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';

            bubble.innerHTML = `
                <div class="bubble-header">
                    <span>${icon} • ${escapeHtml(msg.sender_recipient || '')}</span>
                    <span>${time}</span>
                </div>
                ${msg.subject ? `<div class="bubble-subject"><strong>${escapeHtml(msg.subject)}</strong></div>` : ''}
                <div class="bubble-content">${escapeHtml(msg.content || '')}</div>
                ${msg.attachments && msg.attachments.length > 0 ? `
                    <div class="bubble-attachments">
                        📎 Вложения: ${msg.attachments.map(a => `<a href="${OC.generateUrl('/apps/files/?dir=' + encodeURIComponent(a))}" target="_blank">${escapeHtml(a.split('/').pop())}</a>`).join(', ')}
                    </div>
                ` : ''}
            `;
            stream.appendChild(bubble);
        });

        stream.scrollTop = stream.scrollHeight;
    }

    async function handleSendMessage() {
        if (!currentClientId) return;

        const channel = document.getElementById('reply-channel-select').value;
        const subjectInput = document.getElementById('reply-subject-input');
        const textInput = document.getElementById('reply-message-text');
        const content = textInput.value.trim();

        if (!content) return;

        const currentClient = clientsCache.find(c => c.id === currentClientId);
        const recipient = channel === 'email' ? currentClient?.email : currentClient?.phone;

        try {
            const res = await fetch(`${apiBase}/messages/send`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                },
                body: JSON.stringify({
                    client_id: currentClientId,
                    channel: channel,
                    recipient: recipient,
                    subject: subjectInput ? subjectInput.value : '',
                    content: content
                })
            });

            if (res.ok) {
                textInput.value = '';
                // Refresh timeline
                const timelineRes = await fetch(`${apiBase}/clients/${currentClientId}/timeline`, {
                    headers: { 'requesttoken': OC.requestToken }
                });
                const timelineData = await timelineRes.json();
                renderTimeline(timelineData.messages || []);
            }
        } catch (err) {
            console.error('Failed to send message:', err);
        }
    }

    async function handleSaveClientName() {
        if (!currentClientId) return;

        const editNameInput = document.getElementById('input-edit-client-name');
        const editNameBox = document.getElementById('client-name-edit-box');
        const detailNameEl = document.getElementById('detail-client-name');
        const newName = editNameInput.value.trim();

        if (!newName) {
            alert('Имя не может быть пустым');
            return;
        }

        try {
            const res = await fetch(`${apiBase}/clients/${currentClientId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                },
                body: JSON.stringify({
                    full_name: newName
                })
            });

            if (res.ok) {
                const updated = await res.json();
                detailNameEl.textContent = updated.full_name;
                editNameBox.style.display = 'none';

                // Update cache and sidebar item
                const clientInCache = clientsCache.find(c => c.id === currentClientId);
                if (clientInCache) {
                    clientInCache.full_name = updated.full_name;
                }
                const activeItem = document.querySelector(`.minicrm-client-item[data-client-id="${currentClientId}"] .client-item-name`);
                if (activeItem) {
                    activeItem.textContent = updated.full_name;
                }
            } else {
                alert('Не удалось сохранить имя');
            }
        } catch (err) {
            console.error('Failed to update client name:', err);
            alert('Ошибка сети при обновлении имени');
        }
    }

    async function handleSyncContact(btnEl) {
        if (!currentClientId) return;
        if (btnEl) {
            btnEl.disabled = true;
            btnEl.textContent = '⏳ Синхронизация...';
        }

        try {
            const res = await fetch(`${apiBase}/clients/${currentClientId}/sync-contact`, {
                method: 'POST',
                headers: { 'requesttoken': OC.requestToken }
            });
            const result = await res.json();
            if (result.status === 'success' && result.contact) {
                if (currentClientData) {
                    currentClientData.contact_card = {
                        exists: true,
                        app_url: result.contact.app_url,
                        ...result.contact
                    };
                }
                const contactLinkEl = document.getElementById('modal-contact-app-link') || document.getElementById('detail-contact-app-link');
                if (contactLinkEl) {
                    contactLinkEl.href = OC.generateUrl(result.contact.app_url);
                    contactLinkEl.style.display = 'inline-flex';
                }
                if (btnEl) {
                    btnEl.textContent = '✔️ Синхронизировано!';
                    setTimeout(() => {
                        btnEl.textContent = '🔄 Обновить в Contacts';
                        btnEl.disabled = false;
                    }, 1500);
                }
            } else {
                alert('Ошибка синхронизации: ' + (result.error || 'Неизвестная ошибка'));
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.textContent = '🔄 Создать в Contacts';
                }
            }
        } catch (err) {
            console.error('Failed to sync contact:', err);
            alert('Ошибка сети при синхронизации контакта');
            if (btnEl) {
                btnEl.disabled = false;
                btnEl.textContent = '🔄 Создать в Contacts';
            }
        }
    }

    function getInitials(name) {
        if (!name) return '👤';
        const parts = name.trim().split(/\s+/);
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '';
        try {
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleString([], { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
        } catch {
            return dateStr;
        }
    }

    function openContactModal() {
        if (!currentClientData || !currentClientData.client) {
            if (currentClientId) {
                const found = clientsCache.find(c => c.id === currentClientId);
                if (found) {
                    currentClientData = { client: found, activities: [], contact_card: { exists: false } };
                }
            }
            if (!currentClientData || !currentClientData.client) {
                console.warn('openContactModal: No client selected');
                return;
            }
        }

        const client = currentClientData.client;
        const contactCard = currentClientData.contact_card || {};

        const modal = document.getElementById('modal-contact');
        if (!modal) {
            console.error('modal-contact element not found in DOM');
            return;
        }

        const nameEl = document.getElementById('modal-contact-name');
        const idEl = document.getElementById('modal-contact-id');
        const avatarEl = document.getElementById('modal-contact-avatar');
        const phoneEl = document.getElementById('modal-contact-phone');
        const emailEl = document.getElementById('modal-contact-email');
        const addrEl = document.getElementById('modal-contact-address');
        const notesEl = document.getElementById('modal-contact-notes');
        const appLink = document.getElementById('modal-contact-app-link');
        const syncBtn = document.getElementById('btn-modal-sync-contact');

        if (nameEl) nameEl.textContent = client.full_name || 'Клиент';
        if (idEl) idEl.textContent = `ID: #${client.id}`;
        if (avatarEl) avatarEl.textContent = getInitials(client.full_name);

        const phone = client.phone || contactCard.phone || '';
        if (phoneEl) {
            phoneEl.textContent = phone || '—';
            phoneEl.href = phone ? `tel:${phone}` : '#';
        }

        const email = client.email || contactCard.email || '';
        if (emailEl) {
            emailEl.textContent = email || '—';
            emailEl.href = email ? `mailto:${email}` : '#';
        }

        const address = contactCard.address || 'Адрес не указан';
        if (addrEl) addrEl.textContent = address;

        const notes = client.notes || contactCard.notes || 'Нет заметок';
        if (notesEl) notesEl.textContent = notes;

        if (contactCard.exists && contactCard.app_url) {
            if (appLink) {
                appLink.href = OC.generateUrl(contactCard.app_url);
                appLink.style.display = 'inline-flex';
            }
            if (syncBtn) {
                syncBtn.style.display = 'inline-flex';
                syncBtn.textContent = '🔄 Обновить в Contacts';
                syncBtn.disabled = false;
            }
        } else {
            if (appLink) appLink.style.display = 'none';
            if (syncBtn) {
                syncBtn.style.display = 'inline-flex';
                syncBtn.textContent = '🔄 Создать в Contacts';
                syncBtn.disabled = false;
            }
        }

        modal.style.display = 'flex';
    }

    function closeContactModal() {
        const modal = document.getElementById('modal-contact');
        if (modal) modal.style.display = 'none';
    }

    function openFilesAction() {
        if (!currentClientData || !currentClientData.client) {
            if (currentClientId) {
                const found = clientsCache.find(c => c.id === currentClientId);
                if (found) {
                    currentClientData = { client: found, activities: [], contact_card: { exists: false } };
                }
            }
            if (!currentClientData || !currentClientData.client) {
                console.warn('openFilesAction: No client selected');
                return;
            }
        }

        const client = currentClientData.client;
        if (client.folder_path) {
            const folderUrl = OC.generateUrl(`/apps/files/?dir=${encodeURIComponent(client.folder_path)}`);
            window.open(folderUrl, '_blank');
        } else {
            if (typeof OC.dialogs !== 'undefined' && OC.dialogs.info) {
                OC.dialogs.info('Папка документов для этого клиента еще не создана.', 'Files');
            } else {
                alert('Папка документов для этого клиента еще не создана.');
            }
        }
    }

    function openActionsModal() {
        if (!currentClientData || !currentClientData.client) {
            if (currentClientId) {
                const found = clientsCache.find(c => c.id === currentClientId);
                if (found) {
                    currentClientData = { client: found, activities: [], contact_card: { exists: false } };
                }
            }
            if (!currentClientData || !currentClientData.client) {
                console.warn('openActionsModal: No client selected');
                return;
            }
        }

        const client = currentClientData.client;
        const activities = currentClientData.activities || [];

        const modal = document.getElementById('modal-actions');
        if (!modal) {
            console.error('modal-actions element not found in DOM');
            return;
        }

        const subtitleEl = document.getElementById('modal-actions-client-subtitle');
        if (subtitleEl) {
            subtitleEl.textContent = `Клиент: ${client.full_name} (ID: #${client.id})`;
        }

        const listEl = document.getElementById('modal-activities-list');
        if (listEl) {
            if (activities.length === 0) {
                listEl.innerHTML = '<div class="timeline-empty">У клиента пока нет активностей</div>';
            } else {
                listEl.innerHTML = '';
                activities.forEach(act => {
                    const item = document.createElement('div');
                    item.className = 'activity-card-item';

                    const meetingTime = act.meeting_time ? formatDateTime(act.meeting_time) : 'Не назначена';
                    const createdAt = act.created_at ? formatDateTime(act.created_at) : '';

                    item.innerHTML = `
                        <div class="activity-card-header">
                            <div class="activity-badges">
                                <span class="status-pill">${escapeHtml(act.status || 'scheduled')}</span>
                                <span class="badge">${escapeHtml(act.source || 'Easypoint')}</span>
                            </div>
                            <span class="activity-card-date">${createdAt}</span>
                        </div>
                        <div class="activity-card-body">
                            <div><strong>📅 Время встречи:</strong> ${meetingTime}</div>
                            ${act.responsible_user ? `<div><strong>👤 Ответственный:</strong> ${escapeHtml(act.responsible_user)}</div>` : ''}
                        </div>
                        <div class="activity-card-actions">
                            ${act.deck_task_id ? `
                                <a href="${OC.generateUrl('/apps/deck/#/card/' + act.deck_task_id)}" target="_blank" class="button primary">
                                    🎯 Открыть карточку в Deck #${act.deck_task_id}
                                </a>
                            ` : '<span class="value-plain" style="color:var(--color-text-maxcontrast);">Карточка Deck не привязана</span>'}
                            ${act.file_drop_url ? `
                                <a href="${escapeHtml(act.file_drop_url)}" target="_blank" class="button primary-outline">
                                    📤 FileDrop
                                </a>
                                <button type="button" class="btn-copy-mini btn-copy-activity-filedrop" data-url="${escapeHtml(act.file_drop_url)}" title="Скопировать ссылку для клиента">📋</button>
                            ` : ''}
                        </div>
                    `;
                    listEl.appendChild(item);
                });
            }
        }

        modal.style.display = 'flex';
    }

    function closeActionsModal() {
        const modal = document.getElementById('modal-actions');
        if (modal) modal.style.display = 'none';
    }

    function closeAllModals() {
        closeContactModal();
        closeActionsModal();
    }

    function openClientNameEditor() {
        if (!currentClientId) return;
        const nameEl = document.getElementById('detail-client-name');
        const box = document.getElementById('client-name-edit-box');
        const input = document.getElementById('input-edit-client-name');
        if (!nameEl || !box || !input) return;
        input.value = nameEl.textContent.trim();
        box.style.display = 'flex';
        input.focus();
        input.select();
    }

    function closeClientNameEditor() {
        const box = document.getElementById('client-name-edit-box');
        if (box) box.style.display = 'none';
    }

    function copyText(elementId, btnEl) {
        const el = document.getElementById(elementId);
        if (!el) return;
        const text = el.textContent.trim();
        if (!text || text === '—') return;

        navigator.clipboard.writeText(text).then(() => {
            if (!btnEl) return;
            const orig = btnEl.textContent;
            btnEl.textContent = '✔️';
            setTimeout(() => { btnEl.textContent = orig; }, 1500);
        });
    }

    function copyFileDropLink(btnEl) {
        const dropLink = document.getElementById('detail-client-filedrop');
        if (!dropLink || !dropLink.href) return;

        navigator.clipboard.writeText(dropLink.href).then(() => {
            if (!btnEl) return;
            const orig = btnEl.textContent;
            btnEl.textContent = '✔️ Скопировано!';
            setTimeout(() => { btnEl.textContent = orig; }, 2000);
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function debounce(fn, delay) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    // Expose helpers globally to window so HTML inline onclick works if needed
    window.openContactModal = openContactModal;
    window.closeContactModal = closeContactModal;
    window.openFilesAction = openFilesAction;
    window.openActionsModal = openActionsModal;
    window.closeActionsModal = closeActionsModal;
    window.closeAllModals = closeAllModals;
    window.copyText = copyText;
    window.copyFileDropLink = copyFileDropLink;
    window.openClientNameEditor = openClientNameEditor;
    window.closeClientNameEditor = closeClientNameEditor;
    window.saveClientName = handleSaveClientName;
})();
