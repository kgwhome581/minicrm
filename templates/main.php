<?php

declare(strict_types=1);

script('minicrm', 'minicrm-main');
style('minicrm', 'minicrm-style');

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
            <!-- Top Header: Avatar, Name, Badges, Quick Info & Actions -->
            <div class="minicrm-client-header">
                <div class="client-header-main">
                    <div class="client-avatar" id="detail-client-avatar">👤</div>
                    <div class="client-info-block">
                        <div class="client-title-row">
                            <h1 id="detail-client-name">Клиент</h1>
                            <button type="button" id="btn-edit-client-name" class="btn-icon" title="Редактировать имя">✏️</button>
                            <span id="detail-client-id-badge" class="badge">ID: #0</span>
                            <span id="detail-service-badge" class="service-pill">🇨🇦 T1 Personal Return</span>
                            <span id="detail-activity-status-badge" class="status-pill">Booked</span>
                        </div>
                        <div class="client-meta-line" id="detail-client-meta">
                            <span id="detail-ea-id">EasyAppointments #—</span>
                            <span class="meta-dot">•</span>
                            <span id="detail-city-prov">Lethbridge, AB</span>
                            <span class="meta-dot">•</span>
                            <span id="detail-quick-phone">📞 —</span>
                            <span class="meta-dot">•</span>
                            <span id="detail-quick-email">✉️ —</span>
                        </div>
                    </div>

                    <!-- Header Quick Action Buttons (Widget Switchers) -->
                    <div class="client-header-actions">
                        <button type="button" id="tab-btn-contact" class="header-tab-btn active" title="Карточка контакта и синхронизация CardDAV">
                            👤 Contact
                        </button>
                        <button type="button" id="tab-btn-files" class="header-tab-btn" title="Файлы и документы клиента в Nextcloud">
                            📁 Files <span id="header-files-badge" class="badge-count"></span>
                        </button>
                        <button type="button" id="tab-btn-actions" class="header-tab-btn" title="Задачи Deck и активности">
                            🎯 Deck <span id="header-deck-id"></span>
                        </button>
                        <button type="button" id="btn-t183-esign" class="header-tab-btn btn-esign-highlight" title="Инициировать отправку формы CRA T183 на e-Sign">
                            ✍️ T183 e-Sign
                        </button>
                    </div>
                </div>

                <!-- Inline Name Editor -->
                <div id="client-name-edit-box" class="name-edit-box" style="display: none;">
                    <input type="text" id="input-edit-client-name" placeholder="Введите Фамилию и Имя" />
                    <button type="button" id="btn-save-client-name" class="primary">Сохранить</button>
                    <button type="button" id="btn-cancel-client-name">Отмена</button>
                </div>
            </div>

            <!-- Main Full-Width Workspace: Interactive Widgets Panel (Contact, Files, Deck & Actions) -->
            <div class="minicrm-workspace-single">
                <div class="minicrm-main-panel">

                    <!-- WIDGET 2: In-App Files & Documents Explorer -->
                    <div id="widget-panel-files" class="minicrm-widget-view" style="display: none;">
                        <div class="widget-files-container">
                            <!-- Files Action Bar -->
                            <div class="files-top-toolbar">
                                <div class="files-path-wrap">
                                    <span class="files-root-icon">📁</span>
                                    <span id="files-current-path" class="files-current-path">/Users/—</span>
                                </div>
                                <div class="files-toolbar-actions">
                                    <button type="button" id="btn-refresh-files" class="button button-small" title="Обновить список файлов">
                                        🔄 Обновить
                                    </button>
                                    <label class="button button-small primary file-upload-label" title="Загрузить документ в папку клиента">
                                        📤 Загрузить файл
                                        <input type="file" id="files-direct-file-input" multiple style="display: none;" />
                                    </label>
                                </div>
                            </div>

                            <!-- Subfolder Pills / Navigation -->
                            <div class="files-subfolders-bar" id="files-subfolders-bar">
                                <button type="button" class="subfolder-pill active" data-subfolder="">📂 Корень папки</button>
                                <!-- Dynamic subfolder pills like 23_contact_... -->
                            </div>

                            <!-- FileDrop Quick-Share Card -->
                            <div class="files-filedrop-banner">
                                <div class="filedrop-banner-left">
                                    <span class="filedrop-shield-icon">🔒</span>
                                    <div>
                                        <div class="filedrop-banner-title">Безопасный FileDrop портал для клиента (Upload-Only)</div>
                                        <div class="filedrop-banner-desc">Клиент может безопасно загрузить налоговые документы со смартфона или ПК без пароля</div>
                                    </div>
                                </div>
                                <div class="filedrop-banner-actions">
                                    <input type="text" readonly id="widget-filedrop-url" class="filedrop-url-input" value="—" />
                                    <button type="button" id="btn-copy-widget-filedrop" class="button button-small" title="Скопировать ссылку для отправки клиенту">
                                        📋 Копировать
                                    </button>
                                </div>
                            </div>

                            <!-- Drag-and-Drop Dropzone -->
                            <div id="files-dropzone" class="files-dropzone">
                                <div class="dropzone-inner">
                                    <span class="dropzone-icon">📥</span>
                                    <div class="dropzone-text">
                                        <strong>Перетащите файлы CRA сюда</strong> или нажмите для выбора с компьютера
                                    </div>
                                    <span class="dropzone-hint">Поддерживаются PDF, JPG, PNG, DOCX, XLSX (T4, T5, NOA, ID, квитанции)</span>
                                </div>
                            </div>

                            <!-- File List Table -->
                            <div class="files-table-wrapper">
                                <table class="files-table" id="files-table">
                                    <thead>
                                        <tr>
                                            <th>Имя файла</th>
                                            <th>Категория</th>
                                            <th>Размер</th>
                                            <th>Дата загрузки</th>
                                            <th class="table-actions-header">Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody id="files-table-body">
                                        <tr class="files-empty-row">
                                            <td colspan="5">Загрузка файлов...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- WIDGET 1: Nextcloud Contacts Native Card Widget (View & Edit Modes) -->
                    <div id="widget-panel-contact" class="minicrm-widget-view active">
                        <div class="nc-contact-wrapper">

                            <!-- 1. VIEW MODE (Matches Screenshot 2) -->
                            <div id="nc-contact-view" class="nc-contact-view-container">
                                <!-- Nextcloud Contacts Header Layout -->
                                <div class="nc-contact-header-layout">
                                    <div class="nc-contact-avatar-badge" id="nc-view-avatar">PS</div>
                                    <div class="nc-contact-title-group">
                                        <div class="nc-contact-name-row">
                                            <h1 id="nc-view-fullname" class="nc-contact-fullname">Клиент</h1>
                                            <div class="nc-contact-header-actions">
                                                <button type="button" id="nc-btn-trigger-edit" class="nc-btn-action" title="Редактировать контакт">
                                                    ✏️ Edit
                                                </button>
                                                <a id="nc-btn-open-contacts-app" href="#" target="_blank" class="nc-btn-action nc-btn-contacts-link" title="Открыть карточку в модуле Nextcloud Contacts">
                                                    ↗️ Open in Contacts
                                                </a>
                                                <button type="button" id="nc-btn-sync-carddav" class="nc-btn-action" title="Синхронизировать с CardDAV">
                                                    🔄 Sync CardDAV
                                                </button>
                                            </div>
                                        </div>
                                        <div class="nc-contact-quick-row">
                                            <span id="nc-view-title-company" class="nc-contact-subtext">—</span>
                                            <a id="nc-view-quick-mail" href="#" class="nc-quick-btn-mail" title="Отправить email">✉️</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="nc-contact-body-grid">
                                    <!-- Left Column: Contact Fields -->
                                    <div class="nc-contact-props-col">
                                        <!-- Email Group -->
                                        <div class="nc-field-group" id="nc-view-group-email">
                                            <div class="nc-field-heading">
                                                <span class="nc-field-icon">✉️</span> Email
                                            </div>
                                            <div class="nc-field-row">
                                                <span class="nc-field-label" id="nc-view-email-label">Other</span>
                                                <span id="nc-view-email-val" class="nc-field-text">—</span>
                                                <div class="nc-field-actions">
                                                    <button type="button" id="nc-btn-copy-email" class="nc-btn-field-mini" title="Скопировать email">📋</button>
                                                    <a id="nc-link-open-email" href="#" class="nc-btn-field-mini" title="Написать письмо">↗️</a>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Phone Group -->
                                        <div class="nc-field-group" id="nc-view-group-phone">
                                            <div class="nc-field-heading">
                                                <span class="nc-field-icon">📞</span> Phone
                                            </div>
                                            <div class="nc-field-row">
                                                <span class="nc-field-label" id="nc-view-phone-label">Cell</span>
                                                <span id="nc-view-phone-val" class="nc-field-text">—</span>
                                                <div class="nc-field-actions">
                                                    <button type="button" id="nc-btn-copy-phone" class="nc-btn-field-mini" title="Скопировать телефон">📋</button>
                                                    <a id="nc-link-call-phone" href="#" class="nc-btn-field-mini" title="Позвонить">📞</a>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Website Group -->
                                        <div class="nc-field-group" id="nc-view-group-website">
                                            <div class="nc-field-heading">
                                                <span class="nc-field-icon">🌐</span> Website
                                            </div>
                                            <div class="nc-field-row">
                                                <span class="nc-field-label">Website</span>
                                                <a id="nc-view-website-val" class="nc-field-link" href="#" target="_blank">—</a>
                                            </div>
                                        </div>

                                        <!-- Address Group -->
                                        <div class="nc-field-group" id="nc-view-group-address">
                                            <div class="nc-field-heading">
                                                <span class="nc-field-icon">🏠</span> Address
                                            </div>
                                            <div class="nc-field-row">
                                                <span class="nc-field-label">Home</span>
                                                <span id="nc-view-address-val" class="nc-field-text">—</span>
                                            </div>
                                        </div>

                                        <!-- Custom Dynamic Fields Container -->
                                        <div id="nc-view-custom-fields-container"></div>

                                        <!-- Notes Group -->
                                        <div class="nc-field-group" id="nc-view-group-notes">
                                            <div class="nc-field-heading">
                                                <span class="nc-field-icon">📝</span> Notes
                                            </div>
                                            <div class="nc-field-row">
                                                <span class="nc-field-label">Notes</span>
                                                <span id="nc-view-notes-val" class="nc-field-text nc-field-notes-text">—</span>
                                            </div>
                                        </div>

                                        <!-- Address Book Group -->
                                        <div class="nc-field-group" id="nc-view-group-addressbook">
                                            <div class="nc-field-heading">
                                                <span class="nc-field-icon">📖</span> Address book
                                            </div>
                                            <div class="nc-field-row">
                                                <span class="nc-field-label">Address book</span>
                                                <span class="nc-field-text" id="nc-view-addressbook-val">Contacts</span>
                                            </div>
                                        </div>

                                        <!-- Contact Groups -->
                                        <div class="nc-field-group" id="nc-view-group-groups">
                                            <div class="nc-field-heading">
                                                <span class="nc-field-icon">👥</span> Contact groups
                                            </div>
                                            <div class="nc-field-row">
                                                <span class="nc-field-label">Contact groups</span>
                                                <div class="nc-tags-list" id="nc-view-groups-list">
                                                    <span class="nc-tag-badge">Clients</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="nc-contact-lastmod" id="nc-view-lastmod">Last modified recently</div>
                                    </div>

                                    <!-- Right Column: Shared Items Placeholder (Matches Screenshot 2) -->
                                    <div class="nc-contact-shared-col">
                                        <div class="nc-shared-box">
                                            <div class="nc-shared-art">
                                                <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                    <polyline points="21 15 16 10 5 21"></polyline>
                                                </svg>
                                            </div>
                                            <span class="nc-shared-label">No shared items with this contact</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. EDIT MODE (Matches Screenshot 3) -->
                            <div id="nc-contact-edit" class="nc-contact-edit-container" style="display: none;">
                                <div class="nc-contact-header-layout">
                                    <div class="nc-contact-avatar-badge nc-avatar-editable" id="nc-edit-avatar">
                                        <span id="nc-edit-avatar-text">PS</span>
                                        <span class="nc-avatar-upload-icon">📷</span>
                                    </div>
                                    <div class="nc-contact-title-group">
                                        <div class="nc-edit-name-group">
                                            <div class="nc-edit-input-wrapper">
                                                <label class="nc-floating-label">Name</label>
                                                <input type="text" id="nc-input-fullname" class="nc-styled-input nc-input-name" placeholder="Full name" />
                                            </div>
                                            <div class="nc-edit-sub-row">
                                                <div class="nc-edit-input-wrapper">
                                                    <label class="nc-floating-label">Title</label>
                                                    <input type="text" id="nc-input-title" class="nc-styled-input" placeholder="Title" />
                                                </div>
                                                <div class="nc-edit-input-wrapper">
                                                    <label class="nc-floating-label">Company</label>
                                                    <input type="text" id="nc-input-company" class="nc-styled-input" placeholder="Company" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="nc-contact-header-actions">
                                            <button type="button" id="nc-btn-save-contact" class="button primary nc-btn-save">
                                                ✓ Save
                                            </button>
                                            <button type="button" id="nc-btn-cancel-contact" class="button nc-btn-cancel">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="nc-edit-form-body">
                                    <!-- Email Edit Group -->
                                    <div class="nc-edit-group">
                                        <div class="nc-edit-group-header">
                                            <div class="nc-edit-group-title">
                                                <span class="nc-field-icon">✉️</span>
                                                <strong>Email</strong>
                                            </div>
                                            <button type="button" id="nc-btn-add-email" class="nc-btn-row-add" title="Добавить email">+</button>
                                        </div>
                                        <div class="nc-edit-row" style="display: flex !important; align-items: center !important; gap: 10px !important; width: 100% !important;">
                                            <select id="nc-select-email-type" class="nc-styled-select" style="width: 130px !important; min-width: 130px !important; max-width: 130px !important; flex-shrink: 0 !important; flex-grow: 0 !important;">
                                                <option value="OTHER">Other</option>
                                                <option value="WORK">Work</option>
                                                <option value="HOME">Home</option>
                                            </select>
                                            <div class="nc-edit-input-wrapper nc-flex-1" style="flex: 1 1 auto !important; width: 100% !important; min-width: 0 !important;">
                                                <label class="nc-floating-label">Email</label>
                                                <input type="email" id="nc-input-email" class="nc-styled-input" placeholder="Email" />
                                            </div>
                                            <button type="button" id="nc-btn-clear-email" class="nc-btn-row-delete" title="Очистить">🗑️</button>
                                        </div>
                                    </div>

                                    <!-- Phone Edit Group -->
                                    <div class="nc-edit-group">
                                        <div class="nc-edit-group-header">
                                            <div class="nc-edit-group-title">
                                                <span class="nc-field-icon">📞</span>
                                                <strong>Phone</strong>
                                            </div>
                                            <button type="button" id="nc-btn-add-phone" class="nc-btn-row-add" title="Добавить телефон">+</button>
                                        </div>
                                        <div class="nc-edit-row" style="display: flex !important; align-items: center !important; gap: 10px !important; width: 100% !important;">
                                            <select id="nc-select-phone-type" class="nc-styled-select" style="width: 130px !important; min-width: 130px !important; max-width: 130px !important; flex-shrink: 0 !important; flex-grow: 0 !important;">
                                                <option value="CELL">Cell</option>
                                                <option value="WORK">Work</option>
                                                <option value="HOME">Home</option>
                                            </select>
                                            <div class="nc-edit-input-wrapper nc-flex-1" style="flex: 1 1 auto !important; width: 100% !important; min-width: 0 !important;">
                                                <label class="nc-floating-label">Phone</label>
                                                <input type="tel" id="nc-input-phone" class="nc-styled-input" placeholder="Phone" />
                                            </div>
                                            <button type="button" id="nc-btn-clear-phone" class="nc-btn-row-delete" title="Очистить">🗑️</button>
                                        </div>
                                    </div>

                                    <!-- Website Edit Group -->
                                    <div class="nc-edit-group">
                                        <div class="nc-edit-group-header">
                                            <div class="nc-edit-group-title">
                                                <span class="nc-field-icon">🌐</span>
                                                <strong>Website</strong>
                                            </div>
                                            <button type="button" id="nc-btn-add-website" class="nc-btn-row-add" title="Добавить сайт">+</button>
                                        </div>
                                        <div class="nc-edit-row">
                                            <div class="nc-edit-input-wrapper nc-flex-1">
                                                <label class="nc-floating-label">Website</label>
                                                <input type="text" id="nc-input-website" class="nc-styled-input" placeholder="https://..." />
                                            </div>
                                            <button type="button" id="nc-btn-clear-website" class="nc-btn-row-delete" title="Очистить">🗑️</button>
                                        </div>
                                    </div>

                                    <!-- Address Edit Group -->
                                    <div class="nc-edit-group">
                                        <div class="nc-edit-group-header">
                                            <div class="nc-edit-group-title">
                                                <span class="nc-field-icon">🏠</span>
                                                <strong>Address</strong>
                                            </div>
                                            <button type="button" id="nc-btn-add-address" class="nc-btn-row-add" title="Добавить адрес">+</button>
                                        </div>
                                        <div class="nc-edit-row">
                                            <div class="nc-edit-input-wrapper nc-flex-1">
                                                <label class="nc-floating-label">Home Address</label>
                                                <input type="text" id="nc-input-address" class="nc-styled-input" placeholder="Street, City, Province, Postal Code" />
                                            </div>
                                            <button type="button" id="nc-btn-clear-address" class="nc-btn-row-delete" title="Очистить">🗑️</button>
                                        </div>
                                    </div>

                                    <!-- Notes Edit Group -->
                                    <div class="nc-edit-group">
                                        <div class="nc-edit-group-header">
                                            <div class="nc-edit-group-title">
                                                <span class="nc-field-icon">📝</span>
                                                <strong>Notes</strong>
                                            </div>
                                            <button type="button" id="nc-btn-clear-notes" class="nc-btn-row-delete" title="Очистить">🗑️</button>
                                        </div>
                                        <div class="nc-edit-row">
                                            <div class="nc-edit-input-wrapper nc-flex-1">
                                                <label class="nc-floating-label">Notes</label>
                                                <textarea id="nc-input-notes" class="nc-styled-textarea" rows="3" placeholder="Notes"></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Address Book Edit Group -->
                                    <div class="nc-edit-group">
                                        <div class="nc-edit-group-header">
                                            <div class="nc-edit-group-title">
                                                <span class="nc-field-icon">📖</span>
                                                <strong>Address book</strong>
                                            </div>
                                        </div>
                                        <div class="nc-edit-row">
                                            <select id="nc-select-addressbook" class="nc-styled-select nc-w-full">
                                                <option value="contacts">Contacts</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Contact Groups Edit -->
                                    <div class="nc-edit-group">
                                        <div class="nc-edit-group-header">
                                            <div class="nc-edit-group-title">
                                                <span class="nc-field-icon">👥</span>
                                                <strong>Contact groups</strong>
                                            </div>
                                        </div>
                                        <div class="nc-edit-row nc-groups-picker-row">
                                            <div class="nc-tag-chip-editable">
                                                Clients <button type="button" class="nc-chip-del">✕</button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Dynamic Custom Fields Container -->
                                    <div id="nc-custom-fields-container" class="nc-edit-group"></div>

                                    <!-- Add More Info Button -->
                                    <div class="nc-add-more-wrap">
                                        <button type="button" id="nc-btn-add-more-info" class="button button-small nc-btn-add-info">
                                            + Add more info
                                        </button>
                                    </div>

                                    <div class="nc-contact-lastmod" id="nc-edit-lastmod">Last modified recently</div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- WIDGET 4: In-App Deck & Actions Management -->
                    <div id="widget-panel-actions" class="minicrm-widget-view" style="display: none;">
                        <div class="widget-actions-container">
                            <!-- Deck Card Main Widget -->
                            <div class="deck-widget-card">
                                <div class="deck-card-top">
                                    <div class="deck-card-title-block">
                                        <span class="deck-logo-badge">🎯 Deck</span>
                                        <h3 id="widget-deck-task-title">Задача #—: Подготовка декларации T1</h3>
                                    </div>
                                    <span id="widget-deck-stage-badge" class="status-pill">Scheduled</span>
                                </div>

                                <!-- Interactive Deck Stage Picker -->
                                <div class="deck-stages-interactive-box">
                                    <div class="stages-box-label">Этап карточки в Nextcloud Deck / CRA Workflow:</div>
                                    <div class="deck-stages-pills-row" id="deck-stages-selector">
                                        <button type="button" class="deck-stage-btn" data-status="scheduled">
                                            📥 1. Intake (Запись)
                                        </button>
                                        <button type="button" class="deck-stage-btn" data-status="docs_gathering">
                                            📑 2. Сбор документов
                                        </button>
                                        <button type="button" class="deck-stage-btn" data-status="tax_prep">
                                            🧮 3. Расчёт (Tax Prep)
                                        </button>
                                        <button type="button" class="deck-stage-btn" data-status="t183_review">
                                            ✍️ 4. Подпись T183
                                        </button>
                                        <button type="button" class="deck-stage-btn" data-status="efile_submitted">
                                            🚀 5. CRA EFILE
                                        </button>
                                        <button type="button" class="deck-stage-btn" data-status="completed">
                                            ✅ 6. Завершено
                                        </button>
                                    </div>
                                </div>

                                <!-- Deck Details Grid -->
                                <div class="deck-meta-grid">
                                    <div class="deck-meta-item">
                                        <span class="meta-item-label">📅 Время консультации / приёма:</span>
                                        <span id="widget-deck-meeting-time" class="meta-item-val">—</span>
                                    </div>
                                    <div class="deck-meta-item">
                                        <span class="meta-item-label">👤 Ответственный специалист:</span>
                                        <span id="widget-deck-provider" class="meta-item-val">contact violatax.ca</span>
                                    </div>
                                    <div class="deck-meta-item">
                                        <span class="meta-item-label">🏷️ Источник лида:</span>
                                        <span id="widget-deck-source" class="meta-item-val">EasyAppointments (#23)</span>
                                    </div>
                                    <div class="deck-meta-item">
                                        <span class="meta-item-label">📁 Папка документов:</span>
                                        <span id="widget-deck-folder" class="meta-item-val">/Users/...</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Action Logger -->
                            <div class="quick-action-log-card">
                                <h4 class="card-subtitle">⚡ Зафиксировать действие или контакт с клиентом</h4>
                                <div class="log-action-inputs">
                                    <select id="log-action-type-select">
                                        <option value="call">📞 Телефонный звонок</option>
                                        <option value="docs_request">📑 Запрос документов T4/T5</option>
                                        <option value="consultation">💬 Консультация по налогам</option>
                                        <option value="note">📝 Заметка бухгалтера</option>
                                    </select>
                                    <input type="text" id="log-action-desc-input" placeholder="Краткое описание действия..." />
                                    <button type="button" id="btn-log-action-submit" class="button primary">
                                        ➕ Добавить
                                    </button>
                                </div>
                            </div>

                            <!-- All Activities Stream -->
                            <div class="activities-history-block">
                                <h4 class="card-subtitle">📋 История всех активностей клиента</h4>
                                <div id="widget-activities-list" class="activities-cards-list">
                                    <!-- Dynamic activity items -->
                                </div>
                            </div>
                        </div>
                    </div>
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

    <!-- Modal: T183 e-Sign Flow -->
    <div id="modal-esign" class="minicrm-modal-backdrop" style="display: none;">
        <div class="minicrm-modal-dialog">
            <div class="minicrm-modal-header">
                <div class="modal-title-wrap">
                    <span class="modal-avatar">✍️</span>
                    <div>
                        <h2>CRA Form T183 — Электронная подпись</h2>
                        <span id="modal-esign-subtitle" class="modal-subtitle">Information Return for Electronic Filing of an Individual's Return</span>
                    </div>
                </div>
                <button type="button" class="btn-close-modal" id="btn-close-esign-modal" title="Закрыть">✕</button>
            </div>
            <div class="minicrm-modal-body">
                <div class="modal-info-list">
                    <div class="modal-info-row">
                        <span class="info-label">Клиент (Taxpayer)</span>
                        <div id="modal-esign-client-name" class="info-text" style="font-weight: 600;">—</div>
                    </div>
                    <div class="modal-info-row">
                        <span class="info-label">Email для e-Sign</span>
                        <div id="modal-esign-client-email" class="info-text">—</div>
                    </div>
                    <div class="modal-info-row">
                        <span class="info-label">Документ на подпись</span>
                        <div class="info-text">📄 T183_2024_Information_Return.pdf</div>
                    </div>
                    <div class="modal-info-row full-width">
                        <span class="info-label">Шлюз отправки (n8n Integration)</span>
                        <div class="info-text">SignNow / Nextcloud e-Sign webhook • Уведомление клиенту по Email и SMS</div>
                    </div>
                </div>
            </div>
            <div class="minicrm-modal-footer">
                <button type="button" id="btn-confirm-send-esign" class="button primary">
                    🚀 Отправить T183 на e-Sign
                </button>
                <button type="button" class="button" id="btn-cancel-esign-modal">Отмена</button>
            </div>
        </div>
    </div>
</div>

