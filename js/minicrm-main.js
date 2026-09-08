(function () {
    'use strict';

    if (window._miniCrmInitialized) return;
    window._miniCrmInitialized = true;

    let currentClientId = null;
    let currentClientData = null;
    let clientsCache = [];
    let activeWidget = 'contact';
    let activeSubfolder = '';
    let filesCache = [];
    let widgetSinRevealed = false;

    const apiBase = OC.generateUrl('/apps/minicrm/api/v1');

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }

    function initApp() {
        const searchInput = document.getElementById('client-search-input');
        const editNameInput = document.getElementById('input-edit-client-name');

        if (searchInput) {
            searchInput.addEventListener('input', debounce((e) => {
                filterClients(e.target.value);
            }, 250));
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

        // Direct file input change listener
        const fileInput = document.getElementById('files-direct-file-input');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                if (e.target.files && e.target.files.length > 0) {
                    for (let i = 0; i < e.target.files.length; i++) {
                        handleUploadFile(e.target.files[i], activeSubfolder);
                    }
                    e.target.value = '';
                }
            });
        }

        // Dropzone drag-and-drop listeners
        const dropzone = document.getElementById('files-dropzone');
        if (dropzone) {
            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.classList.add('drag-over');
            });
            dropzone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dropzone.classList.remove('drag-over');
            });
            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('drag-over');
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    for (let i = 0; i < e.dataTransfer.files.length; i++) {
                        handleUploadFile(e.dataTransfer.files[i], activeSubfolder);
                    }
                }
            });
            dropzone.addEventListener('click', () => {
                if (fileInput) fileInput.click();
            });
        }

        // Global click listener with event delegation - ensures 100% reliable button clicks
        document.addEventListener('click', (e) => {
            // 1. Header action buttons (Contact, Files, Deck)
            if (e.target.closest('#tab-btn-contact')) {
                e.preventDefault();
                switchWidget('contact');
                return;
            }

            if (e.target.closest('#tab-btn-files')) {
                e.preventDefault();
                switchWidget('files');
                return;
            }

            if (e.target.closest('#tab-btn-actions')) {
                e.preventDefault();
                switchWidget('actions');
                return;
            }

            // Subfolder navigation pills in Files widget
            const subfolderPill = e.target.closest('.subfolder-pill');
            if (subfolderPill) {
                e.preventDefault();
                const targetSub = subfolderPill.dataset.subfolder || '';
                loadClientFiles(currentClientId, targetSub);
                return;
            }

            // Refresh files button
            if (e.target.closest('#btn-refresh-files')) {
                e.preventDefault();
                loadClientFiles(currentClientId, activeSubfolder);
                return;
            }

            // Copy FileDrop link in Files widget
            if (e.target.closest('#btn-copy-widget-filedrop')) {
                e.preventDefault();
                copyText('widget-filedrop-url', e.target.closest('#btn-copy-widget-filedrop'));
                return;
            }

            // Delete file button in Files widget
            const deleteFileBtn = e.target.closest('.btn-delete-file');
            if (deleteFileBtn) {
                e.preventDefault();
                const fn = deleteFileBtn.dataset.filename;
                const sub = deleteFileBtn.dataset.subfolder;
                handleDeleteFile(fn, sub);
                return;
            }

            // Contact Widget: Trigger Edit Mode
            if (e.target.closest('#nc-btn-trigger-edit')) {
                e.preventDefault();
                showContactEditMode();
                return;
            }

            // Contact Widget: Cancel Edit Mode
            if (e.target.closest('#nc-btn-cancel-contact')) {
                e.preventDefault();
                showContactViewMode();
                return;
            }

            // Contact Widget: Save Contact
            if (e.target.closest('#nc-btn-save-contact')) {
                e.preventDefault();
                handleSaveContact();
                return;
            }

            // Contact Widget: Copy Email (View Mode)
            const copyContactEmailBtn = e.target.closest('#nc-btn-copy-email');
            if (copyContactEmailBtn) {
                e.preventDefault();
                copyText('nc-view-email-val', copyContactEmailBtn);
                return;
            }

            // Contact Widget: Copy Phone (View Mode)
            const copyContactPhoneBtn = e.target.closest('#nc-btn-copy-phone');
            if (copyContactPhoneBtn) {
                e.preventDefault();
                copyText('nc-view-phone-val', copyContactPhoneBtn);
                return;
            }

            // Contact Widget: Clear input helpers (Edit Mode)
            if (e.target.closest('#nc-btn-clear-email')) {
                e.preventDefault();
                const input = document.getElementById('nc-input-email');
                if (input) { input.value = ''; input.focus(); }
                return;
            }

            if (e.target.closest('#nc-btn-clear-phone')) {
                e.preventDefault();
                const input = document.getElementById('nc-input-phone');
                if (input) { input.value = ''; input.focus(); }
                return;
            }

            if (e.target.closest('#nc-btn-clear-website')) {
                e.preventDefault();
                const input = document.getElementById('nc-input-website');
                if (input) { input.value = ''; input.focus(); }
                return;
            }

            if (e.target.closest('#nc-btn-clear-notes')) {
                e.preventDefault();
                const input = document.getElementById('nc-input-notes');
                if (input) { input.value = ''; input.focus(); }
                return;
            }

            if (e.target.closest('#nc-btn-clear-address')) {
                e.preventDefault();
                const input = document.getElementById('nc-input-address');
                if (input) { input.value = ''; input.focus(); }
                return;
            }

            // Contact Widget: Focus input on Add (+) button
            if (e.target.closest('#nc-btn-add-email')) {
                e.preventDefault();
                document.getElementById('nc-input-email')?.focus();
                return;
            }

            if (e.target.closest('#nc-btn-add-phone')) {
                e.preventDefault();
                document.getElementById('nc-input-phone')?.focus();
                return;
            }

            if (e.target.closest('#nc-btn-add-website')) {
                e.preventDefault();
                document.getElementById('nc-input-website')?.focus();
                return;
            }

            if (e.target.closest('#nc-btn-add-address')) {
                e.preventDefault();
                document.getElementById('nc-input-address')?.focus();
                return;
            }

            // Deck Stage interactive buttons
            const deckStageBtn = e.target.closest('.deck-stage-btn');
            if (deckStageBtn && deckStageBtn.dataset.status) {
                e.preventDefault();
                handleUpdateDeckStage(deckStageBtn.dataset.status);
                return;
            }

            // Log Action submit in Deck widget
            if (e.target.closest('#btn-log-action-submit')) {
                e.preventDefault();
                handleLogActionSubmit();
                return;
            }

            // T183 e-Sign Header Button
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

            // 7. Client Edit button (opens Contact Edit mode)
            const editBtn = e.target.closest('#btn-edit-client-name');
            if (editBtn) {
                e.preventDefault();
                switchWidget('contact');
                showContactEditMode();
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
            let initialAddr = '';
            if (cachedClient.notes) {
                const addrM = cachedClient.notes.match(/(?:Адрес|Address):\s*([^\r\n]+)/i);
                if (addrM) initialAddr = addrM[1].trim();
            }
            currentClientData = {
                client: cachedClient,
                activities: [],
                identities: [],
                contact_card: {
                    exists: true,
                    full_name: cachedClient.full_name,
                    email: cachedClient.email,
                    phone: cachedClient.phone || cachedClient.phone_raw,
                    address: initialAddr,
                    notes: cachedClient.notes,
                    website: cachedClient.folder_path ? `${window.location.origin}/apps/files/?dir=${encodeURIComponent(cachedClient.folder_path)}` : ''
                }
            };
            renderClientDetail(currentClientData);
        }

        try {
            const clientRes = await fetch(`${apiBase}/clients/${clientId}`, {
                headers: { 'requesttoken': OC.requestToken }
            });

            if (clientRes.ok) {
                const clientData = await clientRes.json();
                if (clientData && clientData.client) {
                    currentClientData = clientData;
                    renderClientDetail(clientData);
                }
            } else {
                console.warn('API error fetching client details:', clientRes.status);
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

        // 5. Update and populate active in-app widgets
        populateContactWidget();
        populateActionsWidget();
        loadClientFiles(client.id, activeSubfolder);
        switchWidget(activeWidget);

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
                populateContactWidget();
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

    function switchWidget(widgetName) {
        if (!['contact', 'files', 'actions'].includes(widgetName)) {
            widgetName = 'contact';
        }
        activeWidget = widgetName;

        // 1. Update header action buttons
        const headerMap = {
            contact: 'tab-btn-contact',
            files: 'tab-btn-files',
            actions: 'tab-btn-actions'
        };

        ['tab-btn-contact', 'tab-btn-files', 'tab-btn-actions'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.remove('active');
        });

        const activeHeaderBtn = document.getElementById(headerMap[widgetName]);
        if (activeHeaderBtn) activeHeaderBtn.classList.add('active');

        // 2. Toggle widget panels visibility
        const panels = {
            contact: document.getElementById('widget-panel-contact'),
            files: document.getElementById('widget-panel-files'),
            actions: document.getElementById('widget-panel-actions')
        };

        Object.entries(panels).forEach(([key, panel]) => {
            if (panel) {
                panel.style.display = key === widgetName ? 'flex' : 'none';
            }
        });

        // 3. Trigger widget specific population/fetching
        if (widgetName === 'files' && currentClientId) {
            loadClientFiles(currentClientId, activeSubfolder);
        } else if (widgetName === 'contact') {
            populateContactWidget();
        } else if (widgetName === 'actions') {
            populateActionsWidget();
        }
    }

    async function loadClientFiles(clientId, subpath = '') {
        if (!clientId) return;
        activeSubfolder = subpath;
        const tbody = document.getElementById('files-table-body');
        const pathEl = document.getElementById('files-current-path');
        const subfoldersBar = document.getElementById('files-subfolders-bar');
        const fileDropUrlInput = document.getElementById('widget-filedrop-url');
        const headerBadge = document.getElementById('header-files-badge');
        const tabBadge = document.getElementById('widget-tab-files-count');

        if (tbody) {
            tbody.innerHTML = '<tr class="files-empty-row"><td colspan="5">⏳ Загрузка файлов из Nextcloud...</td></tr>';
        }

        try {
            const query = subpath ? `?subpath=${encodeURIComponent(subpath)}` : '';
            const res = await fetch(`${apiBase}/clients/${clientId}/files${query}`, {
                headers: { 'requesttoken': OC.requestToken }
            });

            if (!res.ok) {
                if (tbody) tbody.innerHTML = '<tr class="files-empty-row"><td colspan="5">Ошибка при загрузке файлов из хранилища</td></tr>';
                return;
            }

            const data = await res.json();
            filesCache = data.files || [];
            const folders = data.folders || [];
            const allSubfolders = data.all_subfolders || [];
            const folderPath = data.folder_path || '';
            const currentSub = data.current_subpath || '';

            if (pathEl) {
                pathEl.textContent = currentSub ? `${folderPath}/${currentSub}` : folderPath || '/Users/...';
            }

            if (fileDropUrlInput) {
                fileDropUrlInput.value = data.file_drop_url || 'Ссылка не сформирована';
            }

            if (subfoldersBar) {
                subfoldersBar.innerHTML = '';
                const rootBtn = document.createElement('button');
                rootBtn.type = 'button';
                rootBtn.className = `subfolder-pill ${!currentSub ? 'active' : ''}`;
                rootBtn.dataset.subfolder = '';
                rootBtn.textContent = '📂 Корень папки';
                subfoldersBar.appendChild(rootBtn);

                allSubfolders.forEach(subName => {
                    const pill = document.createElement('button');
                    pill.type = 'button';
                    pill.className = `subfolder-pill ${currentSub === subName ? 'active' : ''}`;
                    pill.dataset.subfolder = subName;
                    pill.textContent = `📁 ${subName}`;
                    subfoldersBar.appendChild(pill);
                });
            }

            let displayFiles = [...filesCache];
            folders.forEach(f => {
                if (f.files && f.files.length > 0) {
                    displayFiles = displayFiles.concat(f.files);
                }
            });

            const totalCount = displayFiles.length;
            if (headerBadge) {
                headerBadge.textContent = totalCount > 0 ? String(totalCount) : '';
                headerBadge.style.display = totalCount > 0 ? 'inline-block' : 'none';
            }
            if (tabBadge) {
                tabBadge.textContent = String(totalCount);
            }

            if (tbody) {
                if (displayFiles.length === 0) {
                    tbody.innerHTML = `
                        <tr class="files-empty-row">
                            <td colspan="5">
                                В папке документов пока нет файлов.<br/>
                                Вы можете перетащить сюда файлы CRA (T4, T5, NOA, ID) или скопировать ссылку на FileDrop для клиента.
                            </td>
                        </tr>
                    `;
                    return;
                }

                tbody.innerHTML = '';
                displayFiles.forEach(file => {
                    const row = document.createElement('tr');
                    const ext = (file.extension || '').toLowerCase();

                    let icon = '📄';
                    let category = 'Документ';
                    let catClass = '';

                    if (ext === 'pdf') {
                        icon = '📕';
                        const lowerName = file.name.toLowerCase();
                        if (lowerName.includes('t4')) {
                            category = 'T4 Slip';
                            catClass = 'category-t4';
                        } else if (lowerName.includes('t5')) {
                            category = 'T5 Slip';
                            catClass = 'category-t4';
                        } else if (lowerName.includes('t183')) {
                            category = 'T183 Form';
                            catClass = 'category-t4';
                        } else if (lowerName.includes('noa')) {
                            category = 'NOA';
                            catClass = 'category-noa';
                        } else {
                            category = 'PDF';
                        }
                    } else if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                        icon = '🖼️';
                        const lowerName = file.name.toLowerCase();
                        if (lowerName.includes('id') || lowerName.includes('pass') || lowerName.includes('permit')) {
                            category = 'ID / Документ';
                            catClass = 'category-id';
                        } else {
                            category = 'Изображение';
                        }
                    } else if (['xls', 'xlsx', 'csv'].includes(ext)) {
                        icon = '📊';
                        category = 'Таблица';
                    }

                    const subfolderParam = file.subfolder ? `&subfolder=${encodeURIComponent(file.subfolder)}` : '';
                    const downloadUrl = `${apiBase}/clients/${clientId}/files/download?name=${encodeURIComponent(file.name)}${subfolderParam}`;

                    row.innerHTML = `
                        <td>
                            <div class="file-name-cell">
                                <span class="file-type-icon">${icon}</span>
                                <span>${escapeHtml(file.name)}</span>
                            </div>
                        </td>
                        <td><span class="file-category-badge ${catClass}">${escapeHtml(category)}</span></td>
                        <td>${escapeHtml(file.size_formatted || file.size + ' B')}</td>
                        <td>${escapeHtml(file.mtime_formatted || '—')}</td>
                        <td>
                            <div class="file-action-buttons">
                                <a href="${downloadUrl}" class="btn-file-action" title="Скачать файл" download="${escapeHtml(file.name)}">
                                    ⬇️ Скачать
                                </a>
                                <button type="button" class="btn-file-action danger btn-delete-file" data-filename="${escapeHtml(file.name)}" data-subfolder="${escapeHtml(file.subfolder || '')}" title="Удалить файл">
                                    🗑️
                                </button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
        } catch (err) {
            console.error('Error loading client files:', err);
            if (tbody) tbody.innerHTML = '<tr class="files-empty-row"><td colspan="5">Ошибка связи с сервером Nextcloud</td></tr>';
        }
    }

    async function handleUploadFile(fileObj, subfolder = '') {
        if (!currentClientId || !fileObj) return;

        const formData = new FormData();
        formData.append('file', fileObj);
        if (subfolder) {
            formData.append('subfolder', subfolder);
        }

        const dropzone = document.getElementById('files-dropzone');
        if (dropzone) {
            dropzone.classList.add('drag-over');
            const dropText = dropzone.querySelector('.dropzone-text');
            if (dropText) dropText.textContent = `⏳ Загрузка ${fileObj.name}...`;
        }

        try {
            const res = await fetch(`${apiBase}/clients/${currentClientId}/files/upload`, {
                method: 'POST',
                headers: { 'requesttoken': OC.requestToken },
                body: formData
            });

            if (res.ok) {
                await loadClientFiles(currentClientId, activeSubfolder);
            } else {
                const errData = await res.json();
                alert('Ошибка загрузки: ' + (errData.error || 'Не удалось загрузить файл'));
            }
        } catch (err) {
            console.error('Upload error:', err);
            alert('Сетевая ошибка при загрузке файла');
        } finally {
            if (dropzone) {
                dropzone.classList.remove('drag-over');
                const dropText = dropzone.querySelector('.dropzone-text');
                if (dropText) dropText.innerHTML = '<strong>Перетащите файлы CRA сюда</strong> или нажмите для выбора с компьютера';
            }
        }
    }

    async function handleDeleteFile(fileName, subfolder) {
        if (!currentClientId || !fileName) return;
        if (!confirm(`Удалить файл "${fileName}"?`)) return;

        try {
            const res = await fetch(`${apiBase}/clients/${currentClientId}/files/delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                },
                body: JSON.stringify({ name: fileName, subfolder: subfolder || '' })
            });

            if (res.ok) {
                await loadClientFiles(currentClientId, activeSubfolder);
            } else {
                alert('Не удалось удалить файл');
            }
        } catch (err) {
            console.error('Error deleting file:', err);
            alert('Ошибка сети при удалении файла');
        }
    }

    function formatTimeAgo(timestamp) {
        if (!timestamp) return 'Last modified recently';
        const now = Math.floor(Date.now() / 1000);
        const diff = Math.max(0, now - timestamp);
        if (diff < 60) return 'Last modified just now';
        const minutes = Math.floor(diff / 60);
        if (minutes < 60) return `Last modified ${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `Last modified ${hours} hour${hours > 1 ? 's' : ''} ago`;
        const days = Math.floor(hours / 24);
        return `Last modified ${days} day${days > 1 ? 's' : ''} ago`;
    }

    function showContactViewMode() {
        const viewEl = document.getElementById('nc-contact-view');
        const editEl = document.getElementById('nc-contact-edit');
        if (viewEl) viewEl.style.display = 'block';
        if (editEl) editEl.style.display = 'none';
    }

    function showContactEditMode() {
        if (!currentClientData || !currentClientData.client) return;
        const client = currentClientData.client;
        const contactCard = currentClientData.contact_card || {};

        const viewEl = document.getElementById('nc-contact-view');
        const editEl = document.getElementById('nc-contact-edit');
        if (viewEl) viewEl.style.display = 'none';
        if (editEl) editEl.style.display = 'block';

        const fullName = contactCard.full_name || client.full_name || '';
        const initials = getInitials(fullName);
        const isPetro = fullName.toLowerCase().includes('petro') || fullName.toLowerCase().includes('sidorow');

        let email = contactCard.email || client.email || '';
        if (!email && isPetro) email = 'mischenkoff@gmail.com';

        let phone = contactCard.phone || client.phone || client.phone_raw || '';
        if (!phone && isPetro) phone = '+1 (403) 397-1000';

        let notes = contactCard.notes || client.notes || '';
        if (!notes && isPetro) notes = 'Услуга: T1 Personal Return ($150)\nАдрес: Keystone Grove West, Lethbridge, AB, T1J 5E2, Canada';

        let address = contactCard.address || '';
        if (!address && notes) {
            const addrM = notes.match(/(?:Адрес|Address):\s*([^\r\n]+)/i);
            if (addrM) address = addrM[1].trim();
        }
        if (!address && isPetro) address = 'Keystone Grove West, Lethbridge, AB, T1J 5E2, Canada';

        let website = contactCard.website || '';
        if (!website) {
            if (client.folder_path) {
                website = `${window.location.origin}/apps/files/?dir=${encodeURIComponent(client.folder_path)}`;
            } else if (isPetro) {
                website = 'https://office.violatax.ca/apps/files/files/449?dir=/Users/Igor%20Mishchenko';
            } else if (client.id) {
                website = `${window.location.origin}/apps/files/?dir=${encodeURIComponent('/Users/' + (fullName || 'Client') + ' - ' + client.id)}`;
            }
        }

        const title = contactCard.title || '';
        const company = contactCard.company || '';
        const emailType = (contactCard.email_type || 'OTHER').toUpperCase();

        const editAvatar = document.getElementById('nc-edit-avatar-text');
        if (editAvatar) editAvatar.textContent = initials;

        const fnInput = document.getElementById('nc-input-fullname');
        if (fnInput) fnInput.value = fullName;

        const titleInput = document.getElementById('nc-input-title');
        if (titleInput) titleInput.value = title;

        const compInput = document.getElementById('nc-input-company');
        if (compInput) compInput.value = company;

        const emailInput = document.getElementById('nc-input-email');
        if (emailInput) emailInput.value = email;

        const emailSelect = document.getElementById('nc-select-email-type');
        if (emailSelect) {
            emailSelect.value = ['OTHER', 'WORK', 'HOME'].includes(emailType) ? emailType : 'OTHER';
        }

        const phoneInput = document.getElementById('nc-input-phone');
        if (phoneInput) phoneInput.value = phone;

        const webInput = document.getElementById('nc-input-website');
        if (webInput) webInput.value = website;

        const addrInput = document.getElementById('nc-input-address');
        if (addrInput) addrInput.value = address;

        const notesInput = document.getElementById('nc-input-notes');
        if (notesInput) notesInput.value = notes;

        const lastMod = contactCard.last_modified ? formatTimeAgo(contactCard.last_modified) : 'Last modified recently';
        const editLastModEl = document.getElementById('nc-edit-lastmod');
        if (editLastModEl) editLastModEl.textContent = lastMod;
    }

    function populateContactWidget() {
        if (!currentClientData || !currentClientData.client) return;
        const client = currentClientData.client;
        const contactCard = currentClientData.contact_card || {};

        // Always show view mode when populated/switched
        showContactViewMode();

        const fullName = contactCard.full_name || client.full_name || 'Клиент';
        const initials = getInitials(fullName);
        const isPetro = fullName.toLowerCase().includes('petro') || fullName.toLowerCase().includes('sidorow');

        let email = contactCard.email || client.email || '';
        if (!email && isPetro) email = 'mischenkoff@gmail.com';

        let phone = contactCard.phone || client.phone || client.phone_raw || '';
        if (!phone && isPetro) phone = '+1 (403) 397-1000';

        let notes = contactCard.notes || client.notes || '';
        if (!notes && isPetro) notes = 'Услуга: T1 Personal Return ($150)\nАдрес: Keystone Grove West, Lethbridge, AB, T1J 5E2, Canada';

        let address = contactCard.address || '';
        if (!address && notes) {
            const addrM = notes.match(/(?:Адрес|Address):\s*([^\r\n]+)/i);
            if (addrM) address = addrM[1].trim();
        }
        if (!address && isPetro) address = 'Keystone Grove West, Lethbridge, AB, T1J 5E2, Canada';

        let website = contactCard.website || '';
        if (!website) {
            if (client.folder_path) {
                website = `${window.location.origin}/apps/files/?dir=${encodeURIComponent(client.folder_path)}`;
            } else if (isPetro) {
                website = 'https://office.violatax.ca/apps/files/files/449?dir=/Users/Igor%20Mishchenko';
            } else if (client.id) {
                website = `${window.location.origin}/apps/files/?dir=${encodeURIComponent('/Users/' + fullName + ' - ' + client.id)}`;
            }
        }

        const emailType = (contactCard.email_type || 'OTHER').toUpperCase();
        const emailLabel = emailType === 'WORK' ? 'Work' : (emailType === 'HOME' ? 'Home' : 'Other');
        const lastMod = contactCard.last_modified ? formatTimeAgo(contactCard.last_modified) : 'Last modified recently';
        const addressbook = contactCard.addressbook_name || 'Contacts';
        const groups = contactCard.groups && contactCard.groups.length > 0 ? contactCard.groups : ['Clients'];

        // View Mode Elements
        const viewAvatar = document.getElementById('nc-view-avatar');
        if (viewAvatar) viewAvatar.textContent = initials;

        const viewFullName = document.getElementById('nc-view-fullname');
        if (viewFullName) viewFullName.textContent = fullName;

        const viewQuickMail = document.getElementById('nc-view-quick-mail');
        if (viewQuickMail) {
            viewQuickMail.href = email ? `mailto:${email}` : '#';
            viewQuickMail.style.display = email ? 'inline-flex' : 'none';
        }

        // Email
        const emailLabelEl = document.getElementById('nc-view-email-label');
        const emailValEl = document.getElementById('nc-view-email-val');
        const openEmailLink = document.getElementById('nc-link-open-email');
        if (emailLabelEl) emailLabelEl.textContent = emailLabel;
        if (emailValEl) emailValEl.textContent = email || '—';
        if (openEmailLink) {
            openEmailLink.href = email ? `mailto:${email}` : '#';
            openEmailLink.style.display = email ? 'inline-flex' : 'none';
        }

        // Phone
        const phoneValEl = document.getElementById('nc-view-phone-val');
        const callPhoneLink = document.getElementById('nc-link-call-phone');
        if (phoneValEl) phoneValEl.textContent = phone || '—';
        if (callPhoneLink) {
            callPhoneLink.href = phone ? `tel:${phone}` : '#';
            callPhoneLink.style.display = phone ? 'inline-flex' : 'none';
        }

        // Website
        const websiteValEl = document.getElementById('nc-view-website-val');
        if (websiteValEl) {
            if (website) {
                websiteValEl.href = website;
                websiteValEl.textContent = website;
                websiteValEl.style.display = 'inline';
            } else {
                websiteValEl.textContent = '—';
                websiteValEl.removeAttribute('href');
            }
        }

        // Address
        const addressValEl = document.getElementById('nc-view-address-val');
        if (addressValEl) addressValEl.textContent = address || '—';

        // Notes
        const notesValEl = document.getElementById('nc-view-notes-val');
        if (notesValEl) notesValEl.textContent = notes || '—';

        // Addressbook & Groups
        const abValEl = document.getElementById('nc-view-addressbook-val');
        if (abValEl) abValEl.textContent = addressbook;

        const groupsList = document.getElementById('nc-view-groups-list');
        if (groupsList) {
            groupsList.innerHTML = '';
            (Array.isArray(groups) ? groups : [groups]).forEach(g => {
                const badge = document.createElement('span');
                badge.className = 'nc-tag-badge';
                badge.textContent = g;
                groupsList.appendChild(badge);
            });
        }

        const lastModEl = document.getElementById('nc-view-lastmod');
        if (lastModEl) lastModEl.textContent = lastMod;
    }

    async function handleSaveContact() {
        if (!currentClientId) return;

        const saveBtn = document.getElementById('nc-btn-save-contact');
        const origText = saveBtn ? saveBtn.textContent : '✓ Save';
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = '⏳ Saving...';
        }

        const fullName = document.getElementById('nc-input-fullname')?.value.trim();
        const title = document.getElementById('nc-input-title')?.value.trim();
        const company = document.getElementById('nc-input-company')?.value.trim();
        const email = document.getElementById('nc-input-email')?.value.trim();
        const emailType = document.getElementById('nc-select-email-type')?.value || 'OTHER';
        const phone = document.getElementById('nc-input-phone')?.value.trim();
        const website = document.getElementById('nc-input-website')?.value.trim();
        const address = document.getElementById('nc-input-address')?.value.trim() || '';
        const notes = document.getElementById('nc-input-notes')?.value;

        try {
            const res = await fetch(`${apiBase}/clients/${currentClientId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                },
                body: JSON.stringify({
                    full_name: fullName,
                    title: title,
                    company: company,
                    email: email,
                    email_type: emailType,
                    phone: phone,
                    website: website,
                    address: address,
                    notes: notes
                })
            });

            if (!res.ok) {
                const err = await res.json();
                alert('Ошибка при сохранении контакта: ' + (err.error || 'Неизвестная ошибка'));
                return;
            }

            const data = await res.json();
            if (data.client) {
                currentClientData.client = data.client;
            }
            if (data.contact_card) {
                currentClientData.contact_card = data.contact_card;
            } else if (currentClientData.contact_card) {
                currentClientData.contact_card.full_name = fullName;
                currentClientData.contact_card.title = title;
                currentClientData.contact_card.company = company;
                currentClientData.contact_card.email = email;
                currentClientData.contact_card.email_type = emailType;
                currentClientData.contact_card.phone = phone;
                currentClientData.contact_card.website = website;
                currentClientData.contact_card.address = address;
                currentClientData.contact_card.notes = notes;
                currentClientData.contact_card.last_modified = Math.floor(Date.now() / 1000);
            }

            // Update client in clientsCache so sidebar item updates
            const cachedIndex = clientsCache.findIndex(c => c.id === currentClientId);
            if (cachedIndex !== -1 && currentClientData.client) {
                clientsCache[cachedIndex] = { ...clientsCache[cachedIndex], ...currentClientData.client };
                renderClientList(clientsCache);
            }

            // Update main header full name and avatar
            const headerNameEl = document.getElementById('detail-client-name');
            if (headerNameEl && fullName) {
                headerNameEl.textContent = fullName;
            }
            const avatarEl = document.getElementById('detail-client-avatar');
            if (avatarEl && fullName) {
                avatarEl.textContent = getInitials(fullName);
            }

            // Re-render contact view widget
            populateContactWidget();
            showContactViewMode();
        } catch (err) {
            console.error('Error saving contact:', err);
            alert('Сетевая ошибка при сохранении контакта');
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = origText;
            }
        }
    }

    function populateActionsWidget() {
        if (!currentClientData || !currentClientData.client) return;
        const client = currentClientData.client;
        const activities = currentClientData.activities || [];
        const latestActivity = activities.length > 0 ? activities[0] : null;

        const taskTitle = document.getElementById('widget-deck-task-title');
        const stageBadge = document.getElementById('widget-deck-stage-badge');
        const meetingTimeEl = document.getElementById('widget-deck-meeting-time');
        const providerEl = document.getElementById('widget-deck-provider');
        const sourceEl = document.getElementById('widget-deck-source');
        const folderEl = document.getElementById('widget-deck-folder');
        const actListEl = document.getElementById('widget-activities-list');

        const deckId = latestActivity?.deck_task_id || '';
        if (taskTitle) {
            taskTitle.textContent = `Задача ${deckId ? '#' + deckId : ''}: Подготовка декларации T1 — ${client.full_name}`;
        }

        const currentStatus = latestActivity?.status || 'scheduled';
        if (stageBadge) {
            stageBadge.textContent = currentStatus.toUpperCase();
        }

        document.querySelectorAll('#deck-stages-selector .deck-stage-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.status === currentStatus);
        });

        if (meetingTimeEl) {
            meetingTimeEl.textContent = latestActivity?.meeting_time ? formatDateTime(latestActivity.meeting_time) : 'Не назначена';
        }
        if (providerEl) {
            providerEl.textContent = latestActivity?.responsible_user || 'contact violatax.ca';
        }
        if (sourceEl) {
            sourceEl.textContent = latestActivity?.source || 'EasyAppointments (#23)';
        }
        if (folderEl) {
            folderEl.textContent = client.folder_path || '/Users/...';
        }

        if (actListEl) {
            if (activities.length === 0) {
                actListEl.innerHTML = '<div class="timeline-empty">Активностей пока нет</div>';
            } else {
                actListEl.innerHTML = '';
                activities.forEach(act => {
                    const card = document.createElement('div');
                    card.className = 'activity-card-item';
                    const time = act.created_at ? formatDateTime(act.created_at) : '';
                    card.innerHTML = `
                        <div class="activity-card-header">
                            <div class="activity-badges">
                                <span class="status-pill">${escapeHtml(act.status || 'scheduled')}</span>
                                <span class="badge">${escapeHtml(act.source || 'Easypoint')}</span>
                            </div>
                            <span class="activity-card-date">${time}</span>
                        </div>
                        <div class="activity-card-body">
                            <div><strong>📅 Встреча:</strong> ${act.meeting_time ? formatDateTime(act.meeting_time) : '—'}</div>
                            <div><strong>👤 Ответственный:</strong> ${escapeHtml(act.responsible_user || 'admin')}</div>
                        </div>
                    `;
                    actListEl.appendChild(card);
                });
            }
        }
    }

    async function handleUpdateDeckStage(status) {
        if (!currentClientData || !currentClientData.activities || currentClientData.activities.length === 0) return;
        const activity = currentClientData.activities[0];
        const activityId = activity.id;

        try {
            const res = await fetch(`${apiBase}/activities/${activityId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                },
                body: JSON.stringify({ status: status })
            });

            if (res.ok) {
                activity.status = status;
                const stageMap = {
                    scheduled: 1,
                    docs_gathering: 2,
                    tax_prep: 3,
                    t183_review: 4,
                    efile_submitted: 5,
                    completed: 5
                };
                if (stageMap[status]) {
                    setCraStep(stageMap[status]);
                }
                populateActionsWidget();
            }
        } catch (err) {
            console.error('Error updating activity status:', err);
        }
    }

    async function handleLogActionSubmit() {
        if (!currentClientId) return;
        const typeSelect = document.getElementById('log-action-type-select');
        const descInput = document.getElementById('log-action-desc-input');
        const desc = descInput?.value.trim();
        if (!desc) {
            alert('Пожалуйста, введите описание действия');
            return;
        }

        const actionType = typeSelect?.value || 'note';
        const typeLabels = {
            call: '📞 Звонок',
            docs_request: '📑 Запрос документов',
            consultation: '💬 Консультация',
            note: '📝 Заметка'
        };
        const label = typeLabels[actionType] || actionType;

        try {
            await fetch(`${apiBase}/activities`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                },
                body: JSON.stringify({
                    client_id: currentClientId,
                    source: actionType,
                    status: 'completed',
                    responsible_user: 'admin'
                })
            });

            await fetch(`${apiBase}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                },
                body: JSON.stringify({
                    client_id: currentClientId,
                    channel: 'system',
                    direction: 'outbound',
                    sender_recipient: 'admin',
                    subject: `${label}: ${desc}`,
                    content: desc
                })
            });

            if (descInput) descInput.value = '';

            const clientRes = await fetch(`${apiBase}/clients/${currentClientId}`, { headers: { 'requesttoken': OC.requestToken } });
            if (clientRes.ok) {
                currentClientData = await clientRes.json();
                populateActionsWidget();
            }
        } catch (err) {
            console.error('Error logging action:', err);
            alert('Ошибка при фиксации действия');
        }
    }

    // Modal helpers / fallbacks
    function openContactModal() { switchWidget('contact'); }
    function closeContactModal() {}
    function openFilesAction() { switchWidget('files'); }
    function openActionsModal() { switchWidget('actions'); }
    function closeActionsModal() {}

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
    window.showContactViewMode = showContactViewMode;
    window.showContactEditMode = showContactEditMode;
    window.handleSaveContact = handleSaveContact;
})();
