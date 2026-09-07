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
                    <button type="button" id="btn-edit-client-name" class="btn-icon" title="Редактировать имя">✏️</button>
                    <span id="detail-client-id-badge" class="badge">ID: #0</span>
                    <span id="detail-activity-status-badge" class="status-pill">scheduled</span>
                </div>

                <!-- Inline Name Editor -->
                <div id="client-name-edit-box" class="name-edit-box" style="display: none;">
                    <input type="text" id="input-edit-client-name" placeholder="Введите Фамилию и Имя" />
                    <button type="button" id="btn-save-client-name" class="primary">Сохранить</button>
                    <button type="button" id="btn-cancel-client-name">Отмена</button>
                </div>

                <!-- Navigation Tabs under First & Last Name: Contact, Files, Actions -->
                <div class="client-header-tabs">
                    <button type="button" id="tab-btn-contact" class="header-tab-btn">
                        👤 Contact
                    </button>
                    <button type="button" id="tab-btn-files" class="header-tab-btn">
                        📁 Files
                    </button>
                    <button type="button" id="tab-btn-actions" class="header-tab-btn">
                        ⚡ Actions
                    </button>
                </div>

                <!-- Expandable Tab Panel Content -->
                <div id="header-tab-panel" class="header-tab-panel" style="display: none;">
                    <!-- 1. Contact Subpanel -->
                    <div id="panel-contact" class="tab-subpanel" style="display: none;">
                        <div class="subpanel-grid">
                            <div class="subpanel-item">
                                <span class="subpanel-label">📞 Телефон:</span>
                                <div class="value-row">
                                    <a id="detail-client-phone" href="#" class="value-link">—</a>
                                    <button type="button" id="btn-copy-phone" class="btn-copy-mini" title="Скопировать телефон">📋</button>
                                </div>
                            </div>
                            <div class="subpanel-item">
                                <span class="subpanel-label">✉️ Email:</span>
                                <div class="value-row">
                                    <a id="detail-client-email" href="#" class="value-link">—</a>
                                    <button type="button" id="btn-copy-email" class="btn-copy-mini" title="Скопировать email">📋</button>
                                </div>
                            </div>
                            <div class="subpanel-item full-width">
                                <span class="subpanel-label">📝 Заметки:</span>
                                <div id="detail-client-notes" class="text-notes">Нет заметок</div>
                            </div>
                        </div>
                    </div>

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
                                    <button type="button" id="btn-copy-filedrop" class="button primary">
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
        </div>
    </div>
</div>

