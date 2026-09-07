<?php

declare(strict_types=1);

/** @var array $_ */
?>
<div id="minicrm-app" class="minicrm-container">
    <!-- Left Navigation: Search & Client List -->
    <div id="app-navigation" class="minicrm-navigation">
        <div class="minicrm-search-bar">
            <input type="text" id="client-search-input" placeholder="Поиск (Имя, Телефон, Email)..." />
        </div>
        <ul id="client-list" class="minicrm-client-list">
            <li class="loading-placeholder">Загрузка клиентов...</li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div id="app-content" class="minicrm-content">
        <!-- Empty State -->
        <div id="client-view-empty" class="minicrm-empty-state">
            <div class="empty-icon">📁</div>
            <h2>Выберите клиента из списка</h2>
            <p>Здесь отобразится информация о клиенте, файлы, задачи из Deck и мультиканальная переписка.</p>
        </div>

        <!-- Client View Detail -->
        <div id="client-view-detail" class="minicrm-detail" style="display: none;">
            <!-- Top Header with Name & Tabs underneath -->
            <div class="minicrm-client-header">
                <div class="client-title-row">
                    <h1 id="detail-client-name">Клиент</h1>
                    <button type="button" id="btn-edit-client-name" class="btn-icon" title="Редактировать имя" onclick="openClientNameEditor()">✏️</button>
                    <span id="detail-client-id-badge" class="badge">ID: #0</span>
                    <span id="detail-activity-status-badge" class="status-pill">scheduled</span>
                </div>

                <!-- Inline Name Editor -->
                <div id="client-name-edit-box" class="name-edit-box" style="display: none;">
                    <input type="text" id="input-edit-client-name" placeholder="Введите Фамилию и Имя" onkeydown="if(event.key==='Enter')saveClientName();if(event.key==='Escape')closeClientNameEditor();" />
                    <button type="button" id="btn-save-client-name" class="primary" onclick="saveClientName()">Сохранить</button>
                    <button type="button" id="btn-cancel-client-name" onclick="closeClientNameEditor()">Отмена</button>
                </div>

                <!-- Navigation Tabs under First & Last Name: Contact, Files, Actions -->
                <div class="client-header-tabs">
                    <button type="button" id="tab-btn-contact" class="header-tab-btn" onclick="toggleContactDrawer()">
                        👤 Contact
                    </button>
                    <button type="button" id="tab-btn-files" class="header-tab-btn" onclick="toggleHeaderTab('files')">
                        📁 Files
                    </button>
                    <button type="button" id="tab-btn-actions" class="header-tab-btn" onclick="toggleHeaderTab('actions')">
                        ⚡ Actions
                    </button>
                </div>

                <!-- Expandable Tab Panel Content (for Files and Actions) -->
                <div id="header-tab-panel" class="header-tab-panel" style="display: none;">

                    <!-- 2. Files Subpanel -->
                    <div id="panel-files" class="tab-subpanel" style="display: none;">
                        <div class="subpanel-grid">
                            <div class="subpanel-item">
                                <span class="subpanel-label">📂 Папка документов в Nextcloud:</span>
                                <a id="detail-client-folder" href="#" target="_blank" class="button primary-outline">
                                    📁 Открыть в Files
                                </a>
                            </div>
                            <div class="subpanel-item">
                                <span class="subpanel-label">📤 Публичная ссылка клиенту (FileDrop):</span>
                                <div class="filedrop-buttons-row">
                                    <a id="detail-client-filedrop" href="#" target="_blank" class="button">
                                        🔗 Страница загрузки
                                    </a>
                                    <button type="button" class="button primary" onclick="copyFileDropLink(this)">
                                        📋 Скопировать ссылку для клиента
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Actions Subpanel -->
                    <div id="panel-actions" class="tab-subpanel" style="display: none;">
                        <div class="subpanel-grid">
                            <div class="subpanel-item">
                                <span class="subpanel-label">📅 Дата и время встречи:</span>
                                <strong id="detail-meeting-time" class="value-highlight">—</strong>
                            </div>
                            <div class="subpanel-item">
                                <span class="subpanel-label">📌 Статус заявки:</span>
                                <span id="detail-activity-status" class="status-pill">—</span>
                            </div>
                            <div class="subpanel-item">
                                <span class="subpanel-label">🎯 Задача в Nextcloud Deck:</span>
                                <div id="detail-deck-link">—</div>
                            </div>
                            <div class="subpanel-item">
                                <span class="subpanel-label">🏷️ Источник:</span>
                                <span id="detail-activity-source" class="value-plain">Easypoint</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Full-width Multichannel Timeline Area -->
            <div class="minicrm-timeline-container">
                <div class="timeline-header-bar">
                    <h3>Мультиканальная история коммуникаций (Timeline)</h3>
                    <span class="timeline-meta-hint">Все каналы: Telegram, WhatsApp, Email, Deck</span>
                </div>
                <div id="detail-timeline-stream" class="timeline-stream">
                    <!-- Dynamic message bubbles -->
                </div>
            </div>

            <!-- Outbound Reply Box -->
            <div class="minicrm-reply-box">
                <div class="reply-controls">
                    <label for="reply-channel-select">Канал отправки:</label>
                    <select id="reply-channel-select">
                        <option value="whatsapp">WhatsApp</option>
                        <option value="telegram">Telegram</option>
                        <option value="email">Email</option>
                    </select>
                    <input type="text" id="reply-subject-input" placeholder="Тема письма (для Email)" style="display: none;" />
                </div>
                <div class="reply-textarea-container">
                    <textarea id="reply-message-text" rows="3" placeholder="Введите текст сообщения клиенту..."></textarea>
                    <button id="reply-send-button" class="primary">Отправить через n8n</button>
                </div>
            </div>

            <!-- Nextcloud Contact View (Drawer in Right Corner) -->
            <div id="contact-drawer" class="minicrm-contact-drawer" style="display: none;">
                <div class="contact-drawer-header">
                    <div class="contact-drawer-title">
                        <span class="nc-contacts-icon">👥</span>
                        <span>Nextcloud Contact</span>
                    </div>
                    <div class="contact-drawer-actions">
                        <a id="contact-card-app-link" href="#" target="_blank" class="btn-drawer-action" title="Открыть в приложении Contacts">↗️ Contacts</a>
                        <button type="button" class="btn-drawer-close" onclick="toggleContactDrawer()" title="Закрыть карточку">✕</button>
                    </div>
                </div>

                <div class="contact-drawer-body">
                    <!-- Profile / Hero Section -->
                    <div class="contact-card-hero">
                        <div class="contact-avatar-wrapper">
                            <div id="contact-card-avatar" class="contact-avatar">👤</div>
                            <span class="avatar-status-dot" title="Активен"></span>
                        </div>
                        <div class="contact-hero-info">
                            <h2 id="contact-card-name">Имя Клиента</h2>
                            <div class="contact-card-subtitle" id="contact-card-role">Клиент ViolaTax & Bookkeeping Services</div>
                            <div class="contact-quick-actions">
                                <a id="contact-action-call" href="#" class="quick-action-btn" title="Позвонить">📞</a>
                                <a id="contact-action-email" href="#" class="quick-action-btn" title="Написать Email">✉️</a>
                                <button type="button" class="quick-action-btn" onclick="copyContactSummary(this)" title="Скопировать данные контакта">📋</button>
                                <a id="contact-action-files" href="#" target="_blank" class="quick-action-btn" title="Открыть папку клиента в Files">📂</a>
                            </div>
                        </div>
                    </div>

                    <div class="contact-drawer-divider"></div>

                    <!-- Email Section -->
                    <div class="contact-property-group">
                        <div class="property-header">
                            <span class="property-icon">✉️</span>
                            <span class="property-title">Email</span>
                        </div>
                        <div class="property-row">
                            <span class="property-type">Other</span>
                            <a id="contact-card-email" href="#" class="property-value">—</a>
                            <button type="button" class="btn-copy-mini" onclick="copyText('contact-card-email', this)" title="Скопировать Email">📋</button>
                        </div>
                    </div>

                    <!-- Phone Section -->
                    <div class="contact-property-group">
                        <div class="property-header">
                            <span class="property-icon">📞</span>
                            <span class="property-title">Телефон</span>
                        </div>
                        <div class="property-row">
                            <span class="property-type">Mobile</span>
                            <a id="contact-card-phone" href="#" class="property-value">—</a>
                            <button type="button" class="btn-copy-mini" onclick="copyText('contact-card-phone', this)" title="Скопировать Телефон">📋</button>
                        </div>
                    </div>

                    <!-- Detailed Name Section -->
                    <div class="contact-property-group">
                        <div class="property-header">
                            <span class="property-icon">📇</span>
                            <span class="property-title">Detailed name</span>
                        </div>
                        <div class="property-row">
                            <span class="property-type">First name</span>
                            <span id="contact-card-first-name" class="property-value-text">—</span>
                        </div>
                        <div class="property-row">
                            <span class="property-type">Last name</span>
                            <span id="contact-card-last-name" class="property-value-text">—</span>
                        </div>
                    </div>

                    <!-- Notes Section -->
                    <div class="contact-property-group">
                        <div class="property-header">
                            <span class="property-icon">📝</span>
                            <span class="property-title">Заметки (Notes)</span>
                        </div>
                        <div class="property-notes-box">
                            <div id="contact-card-notes" class="property-notes-text">Нет заметок</div>
                        </div>
                    </div>

                    <!-- Federated Cloud ID Section -->
                    <div class="contact-property-group">
                        <div class="property-header">
                            <span class="property-icon">☁️</span>
                            <span class="property-title">Federated Cloud ID</span>
                        </div>
                        <div class="property-row">
                            <span class="property-type">Nextcloud</span>
                            <span id="contact-card-cloud-id" class="property-value-text">client@office.violatax.ca</span>
                        </div>
                    </div>

                    <!-- Nextcloud Files Section -->
                    <div class="contact-property-group">
                        <div class="property-header">
                            <span class="property-icon">📂</span>
                            <span class="property-title">Документы клиента</span>
                        </div>
                        <div class="property-row">
                            <a id="contact-card-folder-btn" href="#" target="_blank" class="button primary-outline button-action-full">
                                📁 Открыть папку в Nextcloud Files
                            </a>
                        </div>
                    </div>

                    <!-- Open in Contacts button -->
                    <div class="contact-drawer-footer">
                        <a id="contact-btn-open-app" href="#" target="_blank" class="button primary button-action-full">
                            👥 Открыть в приложении Nextcloud Contacts
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/* Contact Drawer in Right Corner */
function toggleContactDrawer() {
    const drawer = document.getElementById('contact-drawer');
    const btn = document.getElementById('tab-btn-contact');
    if (!drawer) return;

    const isHidden = (drawer.style.display === 'none' || !drawer.classList.contains('open'));

    if (isHidden) {
        drawer.style.display = 'flex';
        void drawer.offsetWidth;
        drawer.classList.add('open');
        if (btn) btn.classList.add('active');
    } else {
        drawer.classList.remove('open');
        if (btn) btn.classList.remove('active');
        setTimeout(() => {
            if (!drawer.classList.contains('open')) {
                drawer.style.display = 'none';
            }
        }, 250);
    }
}

function copyContactSummary(btnEl) {
    const name = document.getElementById('contact-card-name')?.textContent || '';
    const phone = document.getElementById('contact-card-phone')?.textContent || '';
    const email = document.getElementById('contact-card-email')?.textContent || '';
    const notes = document.getElementById('contact-card-notes')?.textContent || '';
    const summary = `${name}\nТел: ${phone}\nEmail: ${email}\nЗаметки: ${notes}`;
    navigator.clipboard.writeText(summary).then(() => {
        const orig = btnEl.textContent;
        btnEl.textContent = '✔️';
        setTimeout(() => { btnEl.textContent = orig; }, 1500);
    });
}

/* Header Tabs Switcher: files, actions */
let currentActiveTab = null;

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
        panel.style.display = 'none';
        if (btnFiles) btnFiles.classList.remove('active');
        if (btnActions) btnActions.classList.remove('active');
        currentActiveTab = null;
        return;
    }

    // Open requested tab
    currentActiveTab = tabName;
    panel.style.display = 'block';

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
        const orig = btnEl.textContent;
        btnEl.textContent = '✔️';
        setTimeout(() => { btnEl.textContent = orig; }, 1500);
    });
}

function copyFileDropLink(btnEl) {
    const dropLink = document.getElementById('detail-client-filedrop');
    if (!dropLink || !dropLink.href) return;

    navigator.clipboard.writeText(dropLink.href).then(() => {
        const orig = btnEl.textContent;
        btnEl.textContent = '✔️ Скопировано!';
        setTimeout(() => { btnEl.textContent = orig; }, 2000);
    });
}

/* Client Name Editor */
function openClientNameEditor() {
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

async function saveClientName() {
    const input = document.getElementById('input-edit-client-name');
    const nameEl = document.getElementById('detail-client-name');
    const box = document.getElementById('client-name-edit-box');
    const badge = document.getElementById('detail-client-id-badge');

    const newName = input.value.trim();
    if (!newName) {
        alert('Имя не может быть пустым');
        return;
    }

    const clientId = badge ? badge.textContent.replace(/[^0-9]/g, '') : null;
    if (!clientId) {
        alert('Клиент не выбран');
        return;
    }

    try {
        const url = OC.generateUrl('/apps/minicrm/api/v1/clients/' + clientId);
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': OC.requestToken
            },
            body: JSON.stringify({ full_name: newName })
        });

        if (res.ok) {
            const data = await res.json();
            nameEl.textContent = data.full_name;
            box.style.display = 'none';

            // Update item in left sidebar
            const activeItem = document.querySelector('.minicrm-client-item.active .client-item-name');
            if (activeItem) {
                activeItem.textContent = data.full_name;
            }
        } else {
            alert('Не удалось сохранить изменения');
        }
    } catch (e) {
        alert('Ошибка при сохранении: ' + e.message);
    }
}
</script>
