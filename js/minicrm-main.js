(function () {
    'use strict';

    let currentClientId = null;
    let clientsCache = [];

    const apiBase = OC.generateUrl('/apps/minicrm/api/v1');

    document.addEventListener('DOMContentLoaded', () => {
        initApp();
    });

    function initApp() {
        const searchInput = document.getElementById('client-search-input');
        const channelSelect = document.getElementById('reply-channel-select');
        const subjectInput = document.getElementById('reply-subject-input');
        const sendButton = document.getElementById('reply-send-button');

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

        // Client Name Editing
        const editNameBtn = document.getElementById('btn-edit-client-name');
        const saveNameBtn = document.getElementById('btn-save-client-name');
        const cancelNameBtn = document.getElementById('btn-cancel-client-name');
        const editNameBox = document.getElementById('client-name-edit-box');
        const editNameInput = document.getElementById('input-edit-client-name');
        const detailNameEl = document.getElementById('detail-client-name');

        if (editNameBtn) {
            editNameBtn.addEventListener('click', () => {
                if (!currentClientId) return;
                editNameInput.value = detailNameEl.textContent.trim();
                editNameBox.style.display = 'flex';
                editNameInput.focus();
            });
        }

        if (cancelNameBtn) {
            cancelNameBtn.addEventListener('click', () => {
                editNameBox.style.display = 'none';
            });
        }

        if (saveNameBtn) {
            saveNameBtn.addEventListener('click', handleSaveClientName);
        }

        if (editNameInput) {
            editNameInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') handleSaveClientName();
                if (e.key === 'Escape') editNameBox.style.display = 'none';
            });
        }

        // Tab buttons: Contact, Files, Actions
        const tabBtnContact = document.getElementById('tab-btn-contact');
        if (tabBtnContact) {
            tabBtnContact.addEventListener('click', toggleContactDrawer);
        }

        const tabBtnFiles = document.getElementById('tab-btn-files');
        if (tabBtnFiles) {
            tabBtnFiles.addEventListener('click', () => toggleHeaderTab('files'));
        }

        const tabBtnActions = document.getElementById('tab-btn-actions');
        if (tabBtnActions) {
            tabBtnActions.addEventListener('click', () => toggleHeaderTab('actions'));
        }

        const drawerCloseBtn = document.querySelector('.btn-drawer-close');
        if (drawerCloseBtn) {
            drawerCloseBtn.addEventListener('click', toggleContactDrawer);
        }

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

        try {
            const [clientRes, timelineRes] = await Promise.all([
                fetch(`${apiBase}/clients/${clientId}`, { headers: { 'requesttoken': OC.requestToken } }),
                fetch(`${apiBase}/clients/${clientId}/timeline`, { headers: { 'requesttoken': OC.requestToken } })
            ]);

            const clientData = await clientRes.json();
            const timelineData = await timelineRes.json();

            renderClientDetail(clientData);
            renderTimeline(timelineData.messages || []);
        } catch (err) {
            console.error('Error fetching client details:', err);
        }
    }

    function renderClientDetail(data) {
        const client = data.client;
        const activities = data.activities || [];
        const latestActivity = activities.length > 0 ? activities[0] : null;

        const editNameBox = document.getElementById('client-name-edit-box');
        if (editNameBox) editNameBox.style.display = 'none';

        document.getElementById('detail-client-name').textContent = client.full_name;
        document.getElementById('detail-client-id-badge').textContent = `ID: #${client.id}`;

        const phoneEl = document.getElementById('detail-client-phone');
        phoneEl.textContent = client.phone || '—';
        phoneEl.href = client.phone ? `tel:${client.phone}` : '#';

        const emailEl = document.getElementById('detail-client-email');
        emailEl.textContent = client.email || '—';
        emailEl.href = client.email ? `mailto:${client.email}` : '#';

        const folderEl = document.getElementById('detail-client-folder');
        if (client.folder_path) {
            folderEl.href = OC.generateUrl(`/apps/files/?dir=${encodeURIComponent(client.folder_path)}`);
            folderEl.style.display = 'inline';
        } else {
            folderEl.style.display = 'none';
        }

        const dropEl = document.getElementById('detail-client-filedrop');
        if (latestActivity && latestActivity.file_drop_url) {
            dropEl.href = latestActivity.file_drop_url;
            dropEl.style.display = 'inline';
        } else {
            dropEl.style.display = 'none';
        }

        // Notes
        const notesEl = document.getElementById('detail-client-notes');
        if (notesEl) {
            notesEl.textContent = client.notes || 'Нет заметок';
        }

        // Activity details
        if (latestActivity) {
            const meetingTimeFormatted = latestActivity.meeting_time ? new Date(latestActivity.meeting_time).toLocaleString() : 'Не назначена';
            const meetingEl = document.getElementById('detail-meeting-time');
            if (meetingEl) meetingEl.textContent = meetingTimeFormatted;

            const statusEl = document.getElementById('detail-activity-status');
            if (statusEl) statusEl.textContent = latestActivity.status;

            const headerStatusEl = document.getElementById('detail-activity-status-badge') || document.getElementById('detail-activity-status-header');
            if (headerStatusEl) headerStatusEl.textContent = latestActivity.status;

            const sourceEl = document.getElementById('detail-activity-source');
            if (sourceEl) sourceEl.textContent = latestActivity.source || 'Easypoint';

            const deckEl = document.getElementById('detail-deck-link');
            if (deckEl) {
                if (latestActivity.deck_task_id) {
                    deckEl.innerHTML = `<a href="${OC.generateUrl('/apps/deck/#/card/' + latestActivity.deck_task_id)}" target="_blank" class="button primary button-action-full">🎯 Открыть карточку в Deck #${latestActivity.deck_task_id}</a>`;
                } else {
                    deckEl.textContent = 'Карточка не привязана';
                }
            }
        }

        // Update Right Corner Contact Card
        updateContactCard(client, latestActivity);
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
                    updateContactCard(clientInCache);
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

    function updateContactCard(client, latestActivity) {
        if (!client) return;

        const cardNameEl = document.getElementById('contact-card-name');
        if (cardNameEl) cardNameEl.textContent = client.full_name || 'Клиент';

        // Avatar initials
        const avatarEl = document.getElementById('contact-card-avatar');
        if (avatarEl) {
            avatarEl.textContent = getInitials(client.full_name || '');
        }

        // Detailed Name
        const { first, last } = parseName(client.full_name || '');
        const firstNameEl = document.getElementById('contact-card-first-name');
        if (firstNameEl) firstNameEl.textContent = first;
        const lastNameEl = document.getElementById('contact-card-last-name');
        if (lastNameEl) lastNameEl.textContent = last;

        // Email
        const emailEl = document.getElementById('contact-card-email');
        const actionEmailEl = document.getElementById('contact-action-email');
        if (emailEl) {
            emailEl.textContent = client.email || '—';
            emailEl.href = client.email ? `mailto:${client.email}` : '#';
        }
        if (actionEmailEl) {
            actionEmailEl.href = client.email ? `mailto:${client.email}` : '#';
        }

        // Phone
        const phoneEl = document.getElementById('contact-card-phone');
        const actionCallEl = document.getElementById('contact-action-call');
        if (phoneEl) {
            phoneEl.textContent = client.phone || '—';
            phoneEl.href = client.phone ? `tel:${client.phone}` : '#';
        }
        if (actionCallEl) {
            actionCallEl.href = client.phone ? `tel:${client.phone}` : '#';
        }

        // Notes
        const notesEl = document.getElementById('contact-card-notes');
        if (notesEl) {
            notesEl.textContent = client.notes || 'Нет заметок';
        }

        // Cloud ID
        const cloudIdEl = document.getElementById('contact-card-cloud-id');
        if (cloudIdEl) {
            const prefix = client.email ? client.email.split('@')[0] : `client_${client.id}`;
            cloudIdEl.textContent = `${prefix}@office.violatax.ca`;
        }

        // Files folder link
        const folderBtn = document.getElementById('contact-card-folder-btn');
        const actionFiles = document.getElementById('contact-action-files');
        if (client.folder_path) {
            const folderUrl = OC.generateUrl(`/apps/files/?dir=${encodeURIComponent(client.folder_path)}`);
            if (folderBtn) folderBtn.href = folderUrl;
            if (actionFiles) actionFiles.href = folderUrl;
        }

        // Contacts App link
        const contactsUrl = OC.generateUrl('/apps/contacts/');
        const appLink = document.getElementById('contact-card-app-link');
        const appBtn = document.getElementById('contact-btn-open-app');
        if (appLink) appLink.href = contactsUrl;
        if (appBtn) appBtn.href = contactsUrl;
    }

    function getInitials(name) {
        if (!name) return '👤';
        const parts = name.trim().split(/\s+/);
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function parseName(fullName) {
        if (!fullName) return { first: '—', last: '—' };
        const parts = fullName.trim().split(/\s+/);
        if (parts.length === 1) return { first: parts[0], last: '—' };
        return { first: parts[0], last: parts.slice(1).join(' ') };
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    let currentActiveTab = null;

    function toggleContactDrawer() {
        const drawer = document.getElementById('contact-drawer');
        const btn = document.getElementById('tab-btn-contact');
        if (!drawer) return;

        const isOpen = drawer.classList.contains('open');
        if (isOpen) {
            drawer.classList.remove('open');
            if (btn) btn.classList.remove('active');
        } else {
            drawer.classList.add('open');
            if (btn) btn.classList.add('active');

            // Populate current client data into contact card
            if (currentClientId) {
                const currentClient = clientsCache.find(c => c.id === currentClientId);
                if (currentClient) {
                    updateContactCard(currentClient);
                }
            }
        }
    }

    function toggleHeaderTab(tabName) {
        if (tabName === 'contact') {
            toggleContactDrawer();
            return;
        }

        const panel = document.getElementById('header-tab-panel');
        const panelFiles = document.getElementById('panel-files');
        const panelActions = document.getElementById('panel-actions');

        const btnFiles = document.getElementById('tab-btn-files');
        const btnActions = document.getElementById('tab-btn-actions');

        // If clicking on already open tab -> toggle collapse
        if (currentActiveTab === tabName) {
            if (panel) panel.style.display = 'none';
            if (btnFiles) btnFiles.classList.remove('active');
            if (btnActions) btnActions.classList.remove('active');
            currentActiveTab = null;
            return;
        }

        // Open requested tab
        currentActiveTab = tabName;
        if (panel) panel.style.display = 'block';

        if (btnFiles) btnFiles.classList.toggle('active', tabName === 'files');
        if (btnActions) btnActions.classList.toggle('active', tabName === 'actions');

        if (panelFiles) panelFiles.style.display = tabName === 'files' ? 'block' : 'none';
        if (panelActions) panelActions.style.display = tabName === 'actions' ? 'block' : 'none';
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

    function copyContactSummary(btnEl) {
        const name = document.getElementById('contact-card-name')?.textContent || '';
        const phone = document.getElementById('contact-card-phone')?.textContent || '';
        const email = document.getElementById('contact-card-email')?.textContent || '';
        const notes = document.getElementById('contact-card-notes')?.textContent || '';
        const summary = `${name}\nТел: ${phone}\nEmail: ${email}\nЗаметки: ${notes}`;
        navigator.clipboard.writeText(summary).then(() => {
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

    // Expose helpers globally to window
    window.toggleContactDrawer = toggleContactDrawer;
    window.toggleHeaderTab = toggleHeaderTab;
    window.copyText = copyText;
    window.copyContactSummary = copyContactSummary;
    window.copyFileDropLink = copyFileDropLink;
    window.openClientNameEditor = () => {
        const nameEl = document.getElementById('detail-client-name');
        const box = document.getElementById('client-name-edit-box');
        const input = document.getElementById('input-edit-client-name');
        if (!nameEl || !box || !input) return;
        input.value = nameEl.textContent.trim();
        box.style.display = 'flex';
        input.focus();
        input.select();
    };
    window.closeClientNameEditor = () => {
        const box = document.getElementById('client-name-edit-box');
        if (box) box.style.display = 'none';
    };
    window.saveClientName = handleSaveClientName;

    function debounce(fn, delay) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), delay);
        };
    }
})();
