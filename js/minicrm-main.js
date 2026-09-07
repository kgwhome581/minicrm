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

        // Activity banner
        if (latestActivity) {
            document.getElementById('detail-meeting-time').textContent = latestActivity.meeting_time ? new Date(latestActivity.meeting_time).toLocaleString() : 'Не назначена';
            document.getElementById('detail-activity-status').textContent = latestActivity.status;
            
            const deckEl = document.getElementById('detail-deck-link');
            if (latestActivity.deck_task_id) {
                deckEl.innerHTML = `<a href="${OC.generateUrl('/apps/deck/#/card/' + latestActivity.deck_task_id)}" target="_blank">Открыть карточку #${latestActivity.deck_task_id}</a>`;
            } else {
                deckEl.textContent = '—';
            }
        }
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
})();
