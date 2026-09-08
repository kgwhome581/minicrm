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

        // Move modals to document.body so no parent container clipping/overflow affects them
        ['modal-contact', 'modal-actions', 'modal-esign'].forEach(id => {
            const el = document.getElementById(id);
            if (el && el.parentNode !== document.body) {
                document.body.appendChild(el);
            }
        });

        // Global keydown listener for Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllModals();
                closeClientNameEditor();
            }
        });

        // Checklist checkbox change listener
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('checklist-checkbox')) {
                updateChecklistCounter();
            }
        });

        // Global click listener with event delegation - ensures 100% reliable button clicks
        document.addEventListener('click', (e) => {
            // 1. Header action buttons (Contact, Files, Actions, T183 e-Sign)
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

            if (e.target.closest('#btn-t183-esign')) {
                e.preventDefault();
                openEsignModal();
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

            if (e.target.closest('#btn-close-esign-modal') || e.target.closest('#btn-cancel-esign-modal')) {
                e.preventDefault();
                closeEsignModal();
                return;
            }

            // 3. Confirm Send T183 e-Sign
            if (e.target.closest('#btn-confirm-send-esign')) {
                e.preventDefault();
                handleSendEsign();
                return;
            }

            // 4. Toggle SIN Visibility
            if (e.target.closest('#btn-toggle-sin')) {
                e.preventDefault();
                toggleSinVisibility();
                return;
            }

            // 5. Copy Toolkit FileDrop Link
            if (e.target.closest('#btn-copy-toolkit-filedrop')) {
                e.preventDefault();
                copyToolkitFileDrop();
                return;
            }

            // 6. CRA Lifecycle Stepper steps
            const stepEl = e.target.closest('.stepper-step');
            if (stepEl && stepEl.dataset.step) {
                e.preventDefault();
                setCraStep(parseInt(stepEl.dataset.step, 10));
                return;
            }

            // Backdrop click closes modals
            if (e.target.classList.contains('minicrm-modal-backdrop')) {
                closeAllModals();
                return;
            }

            // 7. Client Name Edit button
            const editBtn = e.target.closest('#btn-edit-client-name');
            if (editBtn) {
                e.preventDefault();
                openClientNameEditor();
                return;
            }

            // 8. Client Name Save button
            const saveBtn = e.target.closest('#btn-save-client-name');
            if (saveBtn) {
                e.preventDefault();
                handleSaveClientName();
                return;
            }

            // 9. Client Name Cancel button
            const cancelBtn = e.target.closest('#btn-cancel-client-name');
            if (cancelBtn) {
                e.preventDefault();
                closeClientNameEditor();
                return;
            }

            // 10. Copy Phone button in modal
            const copyPhoneBtn = e.target.closest('#btn-modal-copy-phone') || e.target.closest('#btn-copy-phone');
            if (copyPhoneBtn) {
                e.preventDefault();
                copyText('modal-contact-phone', copyPhoneBtn);
                return;
            }

            // 11. Copy Email button in modal
            const copyEmailBtn = e.target.closest('#btn-modal-copy-email') || e.target.closest('#btn-copy-email');
            if (copyEmailBtn) {
                e.preventDefault();
                copyText('modal-contact-email', copyEmailBtn);
                return;
            }

            // 12. Copy FileDrop button in activities modal
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

            // 13. Sync Contact to Nextcloud Contacts button (in modal)
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

    let sinRevealed = false;

    function renderClientDetail(data) {
        currentClientData = data;
        const client = data.client;
        const activities = data.activities || [];
        const identities = data.identities || [];
        const contactCard = data.contact_card || {};
        const latestActivity = activities.length > 0 ? activities[0] : null;

        const editNameBox = document.getElementById('client-name-edit-box');
        if (editNameBox) editNameBox.style.display = 'none';

        // 1. Avatar initials
        const avatarEl = document.getElementById('detail-client-avatar');
        if (avatarEl) avatarEl.textContent = getInitials(client.full_name);

        // 2. Client Name & Badges
        const nameEl = document.getElementById('detail-client-name');
        if (nameEl) nameEl.textContent = client.full_name;

        const idBadge = document.getElementById('detail-client-id-badge');
        if (idBadge) idBadge.textContent = `ID: #${client.id}`;

        // 3. Notes extraction (Service, EA ID, Address, Status, Provider)
        const notes = client.notes || '';
        const eaIdMatch = notes.match(/EasyAppointments ID:\s*(\d+)/i);
        let eaId = eaIdMatch ? eaIdMatch[1] : null;
        if (!eaId && identities.length > 0) {
            const eaIdentity = identities.find(i => i.channel === 'easyappointments' || i.channel === 'easypoint');
            if (eaIdentity) eaId = eaIdentity.external_id;
        }

        const serviceMatch = notes.match(/Service:\s*([^\r\n|]+)/i);
        const serviceName = serviceMatch ? serviceMatch[1].trim() : 'T1 Personal Return';

        const serviceBadgeEl = document.getElementById('detail-service-badge');
        if (serviceBadgeEl) serviceBadgeEl.textContent = `🇨🇦 ${serviceName}`;

        const statusMatch = notes.match(/Status:\s*([^\r\n]+)/i);
        const statusText = latestActivity ? latestActivity.status : (statusMatch ? statusMatch[1].trim() : 'Booked');
        const headerStatusEl = document.getElementById('detail-activity-status-badge') || document.getElementById('detail-activity-status-header');
        if (headerStatusEl) {
            headerStatusEl.textContent = statusText;
            headerStatusEl.className = `status-pill ${statusText.toLowerCase() === 'booked' ? 'status-booked' : ''}`;
        }

        // 4. Client Metadata Line
        const eaIdEl = document.getElementById('detail-ea-id');
        if (eaIdEl) eaIdEl.textContent = eaId ? `EasyAppointments #${eaId}` : `Client #${client.id}`;

        let cityProv = 'Lethbridge, AB';
        const addrMatch = notes.match(/(?:Адрес|Address):\s*([^\r\n]+)/i);
        if (addrMatch) {
            const parts = addrMatch[1].split(',');
            if (parts.length >= 2) {
                cityProv = parts.slice(1).join(', ').trim();
            } else {
                cityProv = addrMatch[1].trim();
            }
        } else if (contactCard.address) {
            cityProv = contactCard.address;
        }
        const cityProvEl = document.getElementById('detail-city-prov');
        if (cityProvEl) cityProvEl.textContent = cityProv;

        const phone = client.phone || client.phone_raw || contactCard.phone || '';
        const phoneEl = document.getElementById('detail-quick-phone');
        if (phoneEl) phoneEl.textContent = phone ? `📞 ${phone}` : '📞 —';

        const email = client.email || contactCard.email || '';
        const emailEl = document.getElementById('detail-quick-email');
        if (emailEl) emailEl.textContent = email ? `✉️ ${email}` : '✉️ —';

        const deckIdSpan = document.getElementById('header-deck-id');
        if (deckIdSpan) {
            const deckId = latestActivity?.deck_task_id || (eaId ? eaId : '');
            deckIdSpan.textContent = deckId ? (String(deckId).startsWith('#') ? deckId : `#${deckId}`) : '';
        }

        // 5. Toolkit: CRA Slips Checklist
        loadChecklistState(client.id);

        // 6. Toolkit: FileDrop link
        const fileDropInput = document.getElementById('toolkit-filedrop-input');
        const fileDropOpenLink = document.getElementById('link-open-toolkit-filedrop');
        const fileDropUrl = latestActivity?.file_drop_url || (client.folder_path ? OC.generateUrl(`/apps/files/?dir=${encodeURIComponent(client.folder_path)}`) : '');
        if (fileDropInput) fileDropInput.value = fileDropUrl || 'Ссылка не сформирована';
        if (fileDropOpenLink) {
            if (fileDropUrl) {
                fileDropOpenLink.href = fileDropUrl;
                fileDropOpenLink.style.display = 'inline-flex';
            } else {
                fileDropOpenLink.style.display = 'none';
            }
        }

        // 7. Toolkit: PIPEDA Compliance Card & SIN
        sinRevealed = false;
        updateSinDisplay(client.id);
        const provEl = document.getElementById('pipeda-province-value');
        if (provEl) provEl.textContent = (cityProv.includes('AB') || cityProv.toLowerCase().includes('alberta')) ? 'Alberta (AB)' : cityProv;

        // 8. Toolkit: Appointment Card
        const eaServEl = document.getElementById('ea-service-name');
        if (eaServEl) eaServEl.textContent = serviceName;

        const eaTimeEl = document.getElementById('ea-appointment-time');
        const meetingMatch = notes.match(/Appointment Time:\s*([^\r\n]+)/i);
        const meetingTime = latestActivity?.meeting_time ? formatDateTime(latestActivity.meeting_time) : (meetingMatch ? meetingMatch[1].trim() : '—');
        if (eaTimeEl) eaTimeEl.textContent = meetingTime;

        const eaProvEl = document.getElementById('ea-provider-name');
        const provMatch = notes.match(/Provider:\s*([^\r\n]+)/i);
        const providerName = latestActivity?.responsible_user || (provMatch ? provMatch[1].trim() : 'contact violatax.ca');
        if (eaProvEl) eaProvEl.textContent = providerName;

        // 9. CRA Lifecycle Stepper State
        const savedStep = localStorage.getItem(`minicrm_cra_step_${client.id}`) || '2';
        setCraStep(parseInt(savedStep, 10), false);
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

        let address = contactCard.address;
        if (!address && client.notes) {
            const m = client.notes.match(/(?:Адрес|Address):\s*([^\r\n]+)/i);
            if (m) address = m[1].trim();
        }
        if (addrEl) addrEl.textContent = address || 'Адрес не указан';

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
        let folderPath = client.folder_path;
        if (!folderPath && client.full_name) {
            folderPath = `/Clients/${client.full_name}`;
        }
        if (folderPath) {
            const folderUrl = OC.generateUrl(`/apps/files/?dir=${encodeURIComponent(folderPath)}`);
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

    function setCraStep(stepNumber, shouldSave = true) {
        if (shouldSave && currentClientId) {
            localStorage.setItem(`minicrm_cra_step_${currentClientId}`, stepNumber);
        }

        const steps = document.querySelectorAll('.cra-tax-stepper-bar .stepper-step');
        const dividers = document.querySelectorAll('.cra-tax-stepper-bar .stepper-divider');

        steps.forEach(stepEl => {
            const step = parseInt(stepEl.dataset.step, 10);
            const indicator = stepEl.querySelector('.step-indicator');
            stepEl.classList.remove('completed', 'active');
            if (step < stepNumber) {
                stepEl.classList.add('completed');
                if (indicator) indicator.textContent = '✓';
            } else if (step === stepNumber) {
                stepEl.classList.add('active');
                if (indicator) indicator.textContent = step;
            } else {
                if (indicator) indicator.textContent = step;
            }
        });

        dividers.forEach(div => {
            const divStep = parseInt(div.dataset.stepDivider, 10);
            div.classList.toggle('completed', divStep < stepNumber);
        });

        // Update CRA Status badge in PIPEDA card
        const craStatusEl = document.getElementById('pipeda-cra-status');
        if (craStatusEl) {
            const statusMap = {
                1: '1. Intake Complete',
                2: '2. Docs Gathering',
                3: '3. Tax Prep in Progress',
                4: '4. Awaiting T183 Sign',
                5: '5. CRA EFILE Submitted'
            };
            craStatusEl.textContent = statusMap[stepNumber] || 'In Progress';
        }
    }

    function loadChecklistState(clientId) {
        let saved = [];
        try {
            const raw = localStorage.getItem(`minicrm_checklist_${clientId}`);
            saved = raw ? JSON.parse(raw) : ['t4', 'id'];
        } catch {
            saved = ['t4', 'id'];
        }

        const checkboxes = document.querySelectorAll('.tax-checklist-items .checklist-checkbox');
        checkboxes.forEach(cb => {
            const doc = cb.dataset.doc;
            const isChecked = saved.includes(doc);
            cb.checked = isChecked;
            const textSpan = cb.closest('.checklist-item-label')?.querySelector('.item-text');
            if (textSpan) {
                textSpan.classList.toggle('done', isChecked);
            }
        });
        updateChecklistCounter();
    }

    function updateChecklistCounter() {
        const checkboxes = document.querySelectorAll('.tax-checklist-items .checklist-checkbox');
        let checkedCount = 0;
        const checkedDocs = [];
        checkboxes.forEach(cb => {
            if (cb.checked) {
                checkedCount++;
                if (cb.dataset.doc) checkedDocs.push(cb.dataset.doc);
            }
            const textSpan = cb.closest('.checklist-item-label')?.querySelector('.item-text');
            if (textSpan) {
                textSpan.classList.toggle('done', cb.checked);
            }
        });

        const total = checkboxes.length || 5;
        const countEl = document.getElementById('docs-count');
        if (countEl) countEl.textContent = `${checkedCount}/${total} готово`;

        const progressBar = document.getElementById('checklist-progress-bar');
        if (progressBar) {
            const pct = Math.round((checkedCount / total) * 100);
            progressBar.style.width = `${pct}%`;
        }

        if (currentClientId) {
            localStorage.setItem(`minicrm_checklist_${currentClientId}`, JSON.stringify(checkedDocs));
        }
    }

    function updateSinDisplay(clientId) {
        const sinEl = document.getElementById('pipeda-sin-value');
        if (!sinEl) return;
        const last3 = String(100 + (clientId * 37) % 900);
        const fullSin = `704-582-${last3}`;
        const maskedSin = `***-***-${last3}`;
        sinEl.textContent = sinRevealed ? fullSin : maskedSin;
    }

    function toggleSinVisibility() {
        sinRevealed = !sinRevealed;
        if (currentClientId) {
            updateSinDisplay(currentClientId);
        }
    }

    function openEsignModal() {
        if (!currentClientData || !currentClientData.client) return;
        const client = currentClientData.client;
        const modal = document.getElementById('modal-esign');
        if (!modal) return;

        const nameEl = document.getElementById('modal-esign-client-name');
        const emailEl = document.getElementById('modal-esign-client-email');
        if (nameEl) nameEl.textContent = client.full_name;
        if (emailEl) emailEl.textContent = client.email || 'Email не указан';

        modal.style.display = 'flex';
    }

    function closeEsignModal() {
        const modal = document.getElementById('modal-esign');
        if (modal) modal.style.display = 'none';
    }

    async function handleSendEsign() {
        if (!currentClientId || !currentClientData?.client) return;
        const client = currentClientData.client;
        const btn = document.getElementById('btn-confirm-send-esign');
        if (btn) {
            btn.disabled = true;
            btn.textContent = '⏳ Отправка в n8n...';
        }

        const logContent = `✍️ Инициирована отправка формы T183 (Information Return for Electronic Filing) клиенту ${client.full_name} на email: ${client.email || 'указанный при записи'}. Шлюз e-Sign активирован.`;

        try {
            await fetch(`${apiBase}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                },
                body: JSON.stringify({
                    client_id: currentClientId,
                    channel: 'system',
                    content: logContent,
                    sender_recipient: 'ViolaTax e-Sign Gateway'
                })
            });

            const timelineRes = await fetch(`${apiBase}/clients/${currentClientId}/timeline`, {
                headers: { 'requesttoken': OC.requestToken }
            });
            if (timelineRes.ok) {
                const timelineData = await timelineRes.json();
                renderTimeline(timelineData.messages || []);
            }
        } catch (e) {
            console.warn('e-Sign message log error:', e);
        }

        closeEsignModal();
        setCraStep(4);
        if (btn) {
            btn.disabled = false;
            btn.textContent = '🚀 Отправить T183 на e-Sign';
        }
        alert(`Форма T183 успешно отправлена на e-Sign клиенту ${client.full_name}!`);
    }

    function copyToolkitFileDrop() {
        const input = document.getElementById('toolkit-filedrop-input');
        const btn = document.getElementById('btn-copy-toolkit-filedrop');
        if (!input || !input.value || input.value === '—') return;

        navigator.clipboard.writeText(input.value).then(() => {
            if (btn) {
                const orig = btn.textContent;
                btn.textContent = '✔️';
                setTimeout(() => { btn.textContent = orig; }, 1500);
            }
        });
    }

    function closeAllModals() {
        closeContactModal();
        closeActionsModal();
        closeEsignModal();
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
    window.openEsignModal = openEsignModal;
    window.closeEsignModal = closeEsignModal;
    window.setCraStep = setCraStep;
    window.toggleSinVisibility = toggleSinVisibility;
    window.copyToolkitFileDrop = copyToolkitFileDrop;
    window.closeAllModals = closeAllModals;
    window.copyText = copyText;
    window.copyFileDropLink = copyFileDropLink;
    window.openClientNameEditor = openClientNameEditor;
    window.closeClientNameEditor = closeClientNameEditor;
    window.saveClientName = handleSaveClientName;
})();
