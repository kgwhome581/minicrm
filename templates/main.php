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

                <!-- Navigation Action Buttons: Contact, Files, Actions -->
                <div class="client-header-tabs">
                    <button type="button" id="tab-btn-contact" class="header-tab-btn" title="Подробная информация о контакте">
                        👤 Contact
                    </button>
                    <button type="button" id="tab-btn-files" class="header-tab-btn" title="Открыть персональную директорию в проводнике Files">
                        📁 Files
                    </button>
                    <button type="button" id="tab-btn-actions" class="header-tab-btn" title="Структурированный список активностей и задачи в Deck">
                        ⚡ Actions
                    </button>
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

    <!-- Modal: Contact Details -->
    <div id="modal-contact" class="minicrm-modal-backdrop" style="display: none;">
        <div class="minicrm-modal-dialog">
            <div class="minicrm-modal-header">
                <div class="modal-title-wrap">
                    <span class="modal-avatar" id="modal-contact-avatar">👤</span>
                    <div>
                        <h2 id="modal-contact-name">Карточка контакта</h2>
                        <span id="modal-contact-id" class="modal-subtitle">ID: #0</span>
                    </div>
                </div>
                <button type="button" class="btn-close-modal" id="btn-close-contact-modal" title="Закрыть">✕</button>
            </div>
            <div class="minicrm-modal-body">
                <div class="modal-info-list">
                    <div class="modal-info-row">
                        <span class="info-label">📞 Телефон</span>
                        <div class="info-value-group">
                            <a id="modal-contact-phone" href="#" class="info-link">—</a>
                            <button type="button" id="btn-modal-copy-phone" class="btn-copy-mini" title="Скопировать телефон">📋</button>
                        </div>
                    </div>
                    <div class="modal-info-row">
                        <span class="info-label">✉️ Email</span>
                        <div class="info-value-group">
                            <a id="modal-contact-email" href="#" class="info-link">—</a>
                            <button type="button" id="btn-modal-copy-email" class="btn-copy-mini" title="Скопировать email">📋</button>
                        </div>
                    </div>
                    <div class="modal-info-row full-width">
                        <span class="info-label">📍 Адрес (Nextcloud Contacts)</span>
                        <div id="modal-contact-address" class="info-text">Адрес не указан</div>
                    </div>
                    <div class="modal-info-row full-width">
                        <span class="info-label">📝 Заметки</span>
                        <div id="modal-contact-notes" class="info-text">Нет заметок</div>
                    </div>
                </div>
            </div>
            <div class="minicrm-modal-footer">
                <a id="modal-contact-app-link" href="#" target="_blank" class="button primary" style="display: none;">
                    ↗️ Открыть в Contacts
                </a>
                <button type="button" id="btn-modal-sync-contact" class="button primary-outline">
                    🔄 Создать в Contacts
                </button>
                <button type="button" class="button" id="btn-cancel-contact-modal">Закрыть</button>
            </div>
        </div>
    </div>

    <!-- Modal: Actions & Deck -->
    <div id="modal-actions" class="minicrm-modal-backdrop" style="display: none;">
        <div class="minicrm-modal-dialog">
            <div class="minicrm-modal-header">
                <div>
                    <h2>⚡ Активности клиента</h2>
                    <span id="modal-actions-client-subtitle" class="modal-subtitle">История заявок, встреч и задач Deck</span>
                </div>
                <button type="button" class="btn-close-modal" id="btn-close-actions-modal" title="Закрыть">✕</button>
            </div>
            <div class="minicrm-modal-body">
                <div id="modal-activities-list" class="activities-cards-list">
                    <!-- Populated dynamically -->
                </div>
            </div>
            <div class="minicrm-modal-footer">
                <button type="button" class="button" id="btn-cancel-actions-modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>


