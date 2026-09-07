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
            <!-- Top Header Bar -->
            <div class="minicrm-client-header">
                <div class="header-left">
                    <div class="client-title-row">
                        <h1 id="detail-client-name">ФИО Клиента</h1>
                        <button type="button" id="btn-edit-client-name" class="btn-icon" title="Редактировать имя" onclick="openClientNameEditor()">✏️</button>
                        <span id="detail-client-id-badge" class="badge">ID: #0</span>
                        <span id="detail-activity-status-header" class="status-pill">scheduled</span>
                    </div>
                    <div id="client-name-edit-box" class="name-edit-box" style="display: none;">
                        <input type="text" id="input-edit-client-name" placeholder="Введите Фамилию и Имя" onkeydown="if(event.key==='Enter')saveClientName();if(event.key==='Escape')closeClientNameEditor();" />
                        <button type="button" id="btn-save-client-name" class="primary" onclick="saveClientName()">Сохранить</button>
                        <button type="button" id="btn-cancel-client-name" onclick="closeClientNameEditor()">Отмена</button>
                    </div>
                </div>

                <div class="header-right">
                    <button type="button" id="btn-toggle-sidebar" class="btn-toggle-contact" onclick="toggleContactSidebar()" title="Открыть карточку контакта">
                        👤 Контакт & Сделка
                    </button>
                </div>
            </div>

            <!-- Body Layout: Timeline (left/center) + Contact Sidebar (right) -->
            <div class="minicrm-body-layout">
                <!-- Timeline & Reply Stream -->
                <div class="minicrm-timeline-area">
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
                </div>

                <!-- Right Side Panel: Contact & Deal Tabs -->
                <div id="minicrm-contact-sidebar" class="minicrm-sidebar-panel">
                    <div class="sidebar-header-row">
                        <h3>Сведения о контакте</h3>
                        <button type="button" class="btn-icon" onclick="toggleContactSidebar()" title="Скрыть панель">✖</button>
                    </div>

                    <!-- Navigation Tabs -->
                    <div class="sidebar-tabs-nav">
                        <button type="button" id="tab-btn-contact" class="sidebar-tab active" onclick="switchSidebarTab('contact')">
                            👤 Контакт
                        </button>
                        <button type="button" id="tab-btn-deal" class="sidebar-tab" onclick="switchSidebarTab('deal')">
                            📅 Встреча & Deck
                        </button>
                    </div>

                    <!-- Tab 1 Content: Contact info, phone, email, files, filedrop -->
                    <div id="tab-pane-contact" class="sidebar-tab-pane active">
                        <div class="sidebar-card-section">
                            <label class="section-label">📞 Телефон:</label>
                            <div class="field-with-copy">
                                <a id="detail-client-phone" href="#" class="field-value-link">—</a>
                                <button type="button" class="btn-copy-mini" onclick="copyTextFromElement('detail-client-phone', this)" title="Скопировать телефон">📋</button>
                            </div>
                        </div>

                        <div class="sidebar-card-section">
                            <label class="section-label">✉️ Email:</label>
                            <div class="field-with-copy">
                                <a id="detail-client-email" href="#" class="field-value-link">—</a>
                                <button type="button" class="btn-copy-mini" onclick="copyTextFromElement('detail-client-email', this)" title="Скопировать email">📋</button>
                            </div>
                        </div>

                        <div class="sidebar-card-section">
                            <label class="section-label">📂 Папка документов:</label>
                            <a id="detail-client-folder" href="#" target="_blank" class="button button-action-full">
                                📁 Открыть папку в Files
                            </a>
                        </div>

                        <div class="sidebar-card-section">
                            <label class="section-label">📤 Ссылка клиенту (FileDrop):</label>
                            <a id="detail-client-filedrop" href="#" target="_blank" class="button button-action-full">
                                🔗 Открыть страницу загрузки
                            </a>
                            <button type="button" class="button primary button-action-full" style="margin-top: 6px;" onclick="copyFileDropUrl(this)">
                                📋 Скопировать ссылку для клиента
                            </button>
                        </div>

                        <div class="sidebar-card-section">
                            <label class="section-label">📝 Примечания:</label>
                            <div id="detail-client-notes" class="notes-display-box">Нет заметок</div>
                        </div>
                    </div>

                    <!-- Tab 2 Content: Meeting, status, deck card -->
                    <div id="tab-pane-deal" class="sidebar-tab-pane" style="display: none;">
                        <div class="sidebar-card-section">
                            <label class="section-label">📅 Дата и время встречи:</label>
                            <div id="detail-meeting-time" class="highlight-value">—</div>
                        </div>

                        <div class="sidebar-card-section">
                            <label class="section-label">📌 Статус заявки:</label>
                            <div>
                                <span id="detail-activity-status" class="status-pill">—</span>
                            </div>
                        </div>

                        <div class="sidebar-card-section">
                            <label class="section-label">🎯 Задача в Nextcloud Deck:</label>
                            <div id="detail-deck-link">
                                —
                            </div>
                        </div>

                        <div class="sidebar-card-section">
                            <label class="section-label">🏷️ Источник:</label>
                            <div id="detail-activity-source" class="plain-value">Easypoint</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/* Sidebar toggle and tabs management */
function toggleContactSidebar() {
    const sidebar = document.getElementById('minicrm-contact-sidebar');
    const toggleBtn = document.getElementById('btn-toggle-sidebar');
    if (!sidebar) return;

    if (sidebar.style.display === 'none' || getComputedStyle(sidebar).display === 'none') {
        sidebar.style.display = 'flex';
        if (toggleBtn) toggleBtn.classList.add('active');
    } else {
        sidebar.style.display = 'none';
        if (toggleBtn) toggleBtn.classList.remove('active');
    }
}

function switchSidebarTab(tabName) {
    const tabContact = document.getElementById('tab-pane-contact');
    const tabDeal = document.getElementById('tab-pane-deal');
    const btnContact = document.getElementById('tab-btn-contact');
    const btnDeal = document.getElementById('tab-btn-deal');

    if (tabName === 'contact') {
        if (tabContact) tabContact.style.display = 'block';
        if (tabDeal) tabDeal.style.display = 'none';
        if (btnContact) btnContact.classList.add('active');
        if (btnDeal) btnDeal.classList.remove('active');
    } else {
        if (tabContact) tabContact.style.display = 'none';
        if (tabDeal) tabDeal.style.display = 'block';
        if (btnContact) btnContact.classList.remove('active');
        if (btnDeal) btnDeal.classList.add('active');
    }
}

function copyTextFromElement(elId, btnEl) {
    const el = document.getElementById(elId);
    if (!el) return;
    const text = el.textContent.trim();
    if (!text || text === '—') return;

    navigator.clipboard.writeText(text).then(() => {
        const orig = btnEl.textContent;
        btnEl.textContent = '✔️';
        setTimeout(() => { btnEl.textContent = orig; }, 1500);
    });
}

function copyFileDropUrl(btnEl) {
    const dropLink = document.getElementById('detail-client-filedrop');
    if (!dropLink || !dropLink.href) return;

    navigator.clipboard.writeText(dropLink.href).then(() => {
        const orig = btnEl.textContent;
        btnEl.textContent = '✔️ Ссылка скопирована!';
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

            // Update sidebar item
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
