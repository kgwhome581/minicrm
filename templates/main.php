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

            <!-- Canadian CRA Tax Lifecycle Stepper -->
            <div class="cra-tax-stepper-bar" id="cra-stepper-bar">
                <div class="stepper-step completed" data-step="1" title="Шаг 1: Intake (Запись) — Завершена">
                    <div class="step-indicator">✓</div>
                    <div class="step-label">1. Intake (Запись)</div>
                </div>
                <div class="stepper-divider completed" data-step-divider="1"></div>
                <div class="stepper-step active" data-step="2" title="Шаг 2: Сбор документов (В процессе)">
                    <div class="step-indicator">2</div>
                    <div class="step-label">2. Сбор документов</div>
                </div>
                <div class="stepper-divider" data-step-divider="2"></div>
                <div class="stepper-step" data-step="3" title="Шаг 3: Расчёт декларации (Tax Prep)">
                    <div class="step-indicator">3</div>
                    <div class="step-label">3. Расчёт (Tax Prep)</div>
                </div>
                <div class="stepper-divider" data-step-divider="3"></div>
                <div class="stepper-step" data-step="4" title="Шаг 4: Электронная подпись T183">
                    <div class="step-indicator">4</div>
                    <div class="step-label">4. T183 e-Sign</div>
                </div>
                <div class="stepper-divider" data-step-divider="4"></div>
                <div class="stepper-step" data-step="5" title="Шаг 5: Подача в CRA (EFILE)">
                    <div class="step-indicator">5</div>
                    <div class="step-label">5. CRA EFILE</div>
                </div>
            </div>

            <!-- 2-Column Workspace: Left Toolkit (CRA & PIPEDA) / Right (Timeline & Messaging) -->
            <div class="minicrm-workspace-grid">
                <!-- Left Column: Canadian Tax Clinic Toolkit -->
                <div class="minicrm-toolkit-panel">
                    <!-- CRA Tax Slips Checklist Card -->
                    <div class="toolkit-card tax-checklist-card">
                        <div class="card-header">
                            <div class="card-title">
                                <span class="card-icon">📑</span>
                                <h4>Чек-лист CRA для T1</h4>
                            </div>
                            <span class="card-counter" id="docs-count">0/5 готово</span>
                        </div>
                        <div class="checklist-progress-track">
                            <div class="checklist-progress-bar" id="checklist-progress-bar" style="width: 0%;"></div>
                        </div>
                        <ul class="tax-checklist-items" id="tax-checklist-items">
                            <li>
                                <label class="checklist-item-label">
                                    <input type="checkbox" data-doc="t4" class="checklist-checkbox" />
                                    <span class="item-text">T4 (Employment Income)</span>
                                </label>
                            </li>
                            <li>
                                <label class="checklist-item-label">
                                    <input type="checkbox" data-doc="id" class="checklist-checkbox" />
                                    <span class="item-text">ID / PR Card / Work Permit</span>
                                </label>
                            </li>
                            <li>
                                <label class="checklist-item-label">
                                    <input type="checkbox" data-doc="t5" class="checklist-checkbox" />
                                    <span class="item-text">T5 / Инвестиции (Interest)</span>
                                </label>
                            </li>
                            <li>
                                <label class="checklist-item-label">
                                    <input type="checkbox" data-doc="med" class="checklist-checkbox" />
                                    <span class="item-text">Medical Receipts (Медицина)</span>
                                </label>
                            </li>
                            <li>
                                <label class="checklist-item-label">
                                    <input type="checkbox" data-doc="noa" class="checklist-checkbox" />
                                    <span class="item-text">Notice of Assessment (NOA 2023)</span>
                                </label>
                            </li>
                        </ul>

                        <!-- FileDrop Quick-Share Link -->
                        <div class="filedrop-quick-box">
                            <div class="filedrop-label">Безопасная ссылка для досылки (FileDrop):</div>
                            <div class="filedrop-input-row">
                                <input type="text" readonly id="toolkit-filedrop-input" value="—" placeholder="Ссылка не сформирована" />
                                <button type="button" id="btn-copy-toolkit-filedrop" class="btn-copy-mini" title="Скопировать ссылку для клиента">📋</button>
                                <a id="link-open-toolkit-filedrop" href="#" target="_blank" class="btn-copy-mini" title="Открыть папку загрузки" style="display: none;">↗️</a>
                            </div>
                        </div>
                    </div>

                    <!-- Security & PIPEDA Compliance Card -->
                    <div class="toolkit-card pipeda-card">
                        <div class="card-header">
                            <div class="card-title">
                                <span class="card-icon">🔒</span>
                                <h4>Безопасность и PIPEDA</h4>
                            </div>
                            <span class="pipeda-badge">Compliant</span>
                        </div>
                        <div class="pipeda-details">
                            <div class="pipeda-row">
                                <span class="pipeda-label">SIN (Tax ID):</span>
                                <div class="pipeda-value-wrap">
                                    <span id="pipeda-sin-value" class="sin-masked">***-***-841</span>
                                    <button type="button" id="btn-toggle-sin" class="btn-toggle-sin" title="Показать/скрыть SIN">👁️</button>
                                </div>
                            </div>
                            <div class="pipeda-row">
                                <span class="pipeda-label">Провинция:</span>
                                <span id="pipeda-province-value" class="pipeda-val-strong">Alberta (AB)</span>
                            </div>
                            <div class="pipeda-row">
                                <span class="pipeda-label">CRA Status:</span>
                                <span id="pipeda-cra-status" class="pipeda-status-val">Awaiting T183 Sign</span>
                            </div>
                            <div class="pipeda-row">
                                <span class="pipeda-label">Приём / Клиника:</span>
                                <span id="pipeda-clinic-value" class="pipeda-val-strong">ViolaTax Lethbridge</span>
                            </div>
                        </div>
                    </div>

                    <!-- Appointment Details Card -->
                    <div class="toolkit-card appointment-card">
                        <div class="card-header">
                            <div class="card-title">
                                <span class="card-icon">🗓️</span>
                                <h4>Запись EasyAppointments</h4>
                            </div>
                        </div>
                        <div class="pipeda-details">
                            <div class="pipeda-row">
                                <span class="pipeda-label">Услуга:</span>
                                <span id="ea-service-name" class="pipeda-val-strong">—</span>
                            </div>
                            <div class="pipeda-row">
                                <span class="pipeda-label">Время приёма:</span>
                                <span id="ea-appointment-time" class="pipeda-val-strong">—</span>
                            </div>
                            <div class="pipeda-row">
                                <span class="pipeda-label">Специалист:</span>
                                <span id="ea-provider-name" class="pipeda-val-strong">contact violatax.ca</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Interactive Widgets Panel (Contact, Files, Deck & Actions) -->
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

                    <!-- WIDGET: Nextcloud Contacts Native Card View -->
                    <div id="widget-panel-contact" class="minicrm-widget-view active">
                        <div class="widget-contact-container">
                            <!-- Contact Widget Header / Toolbar -->
                            <div class="contact-widget-topbar">
                                <div class="contact-topbar-left">
                                    <span class="contact-topbar-icon">📇</span>
                                    <div class="contact-topbar-titles">
                                        <h3 id="contact-topbar-title">Nextcloud Contacts: <span id="contact-widget-fullname">—</span></h3>
                                        <span id="contact-sync-badge" class="sync-badge-ok">🟢 CardDAV Синхронизировано</span>
                                    </div>
                                </div>
                                <div class="contact-topbar-actions">
                                    <button type="button" id="btn-refresh-contact-card" class="button button-small" title="Перезагрузить карточку контакта">
                                        🔄 Обновить
                                    </button>
                                    <a id="link-external-nc-contact" href="#" target="_blank" class="button button-small" title="Открыть в приложении Nextcloud Contacts">
                                        ↗️ В приложении Contacts
                                    </a>
                                </div>
                            </div>

                            <!-- Native Nextcloud Contacts Card View (Image 2 Replica) -->
                            <div class="nc-contact-card-wrapper" id="nc-contact-card-view">
                                <div class="nc-contact-card-container">
                                    <!-- Header Row: Avatar, Name, Actions -->
                                    <div class="nc-contact-header-section">
                                        <div class="nc-contact-avatar-circle" id="nc-contact-avatar">PS</div>
                                        <div class="nc-contact-header-details">
                                            <div class="nc-contact-title-row">
                                                <h2 class="nc-contact-displayname" id="nc-card-displayname">—</h2>
                                                <div class="nc-contact-actions-bar">
                                                    <button type="button" id="btn-nc-card-edit" class="nc-action-btn" title="Редактировать карточку">
                                                        ✏️ Edit
                                                    </button>
                                                    <a id="link-nc-card-open-ext" href="#" target="_blank" class="nc-action-btn nc-action-icon-only" title="Открыть в приложении Nextcloud Contacts">
                                                        ↗️
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="nc-contact-quick-buttons">
                                                <a id="nc-card-quick-mail-btn" href="#" class="nc-quick-btn-icon" title="Отправить email">
                                                    ✉️
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Properties & Shared Items Layout -->
                                    <div class="nc-contact-body-layout">
                                        <div class="nc-contact-props-column">

                                            <!-- Email Prop Group -->
                                            <div class="nc-prop-group" id="nc-group-email">
                                                <div class="nc-prop-group-heading">
                                                    <span class="nc-prop-heading-icon">✉️</span> Email
                                                </div>
                                                <div class="nc-prop-entry-row">
                                                    <span class="nc-prop-type-label">Other</span>
                                                    <span class="nc-prop-value-text" id="nc-card-email-val">—</span>
                                                    <div class="nc-prop-actions-btns">
                                                        <button type="button" id="btn-nc-copy-email" class="nc-prop-btn" title="Скопировать email">📋</button>
                                                        <a id="link-nc-mail-to" href="#" class="nc-prop-btn" title="Написать письмо">↗️</a>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Website / Folder Link -->
                                            <div class="nc-prop-group" id="nc-group-website">
                                                <div class="nc-prop-group-heading">
                                                    <span class="nc-prop-heading-icon">🌐</span> Website
                                                </div>
                                                <div class="nc-prop-entry-row">
                                                    <span class="nc-prop-type-label">Website</span>
                                                    <a id="nc-card-website-link" class="nc-prop-value-link" href="#" target="_blank">
                                                        —
                                                    </a>
                                                </div>
                                            </div>

                                            <!-- Address Book -->
                                            <div class="nc-prop-group" id="nc-group-addressbook">
                                                <div class="nc-prop-group-heading">
                                                    <span class="nc-prop-heading-icon">📖</span> Address book
                                                </div>
                                                <div class="nc-prop-entry-row">
                                                    <span class="nc-prop-type-label">Address book</span>
                                                    <span class="nc-prop-value-text" id="nc-card-addressbook-val">Contacts</span>
                                                </div>
                                            </div>

                                            <!-- Contact Groups -->
                                            <div class="nc-prop-group" id="nc-group-categories">
                                                <div class="nc-prop-group-heading">
                                                    <span class="nc-prop-heading-icon">👥</span> Contact groups
                                                </div>
                                                <div class="nc-prop-entry-row">
                                                    <span class="nc-prop-type-label">Contact groups</span>
                                                    <div class="nc-tags-container" id="nc-card-groups-container">
                                                        <span class="nc-tag-chip">Clients</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Phone Prop Group -->
                                            <div class="nc-prop-group" id="nc-group-phone" style="display: none;">
                                                <div class="nc-prop-group-heading">
                                                    <span class="nc-prop-heading-icon">📞</span> Phone
                                                </div>
                                                <div class="nc-prop-entry-row">
                                                    <span class="nc-prop-type-label">Cell</span>
                                                    <span class="nc-prop-value-text" id="nc-card-phone-val">—</span>
                                                    <div class="nc-prop-actions-btns">
                                                        <button type="button" id="btn-nc-copy-phone" class="nc-prop-btn" title="Скопировать телефон">📋</button>
                                                        <a id="link-nc-call-to" href="#" class="nc-prop-btn" title="Позвонить">📞</a>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Address Prop Group -->
                                            <div class="nc-prop-group" id="nc-group-address" style="display: none;">
                                                <div class="nc-prop-group-heading">
                                                    <span class="nc-prop-heading-icon">🏠</span> Address
                                                </div>
                                                <div class="nc-prop-entry-row">
                                                    <span class="nc-prop-type-label">Home</span>
                                                    <span class="nc-prop-value-text" id="nc-card-address-val">—</span>
                                                </div>
                                            </div>

                                            <!-- Last Modified Stamp -->
                                            <div class="nc-card-last-modified" id="nc-card-last-modified">
                                                Last modified recently
                                            </div>
                                        </div>

                                        <!-- Right Side: Shared Items Placeholder (matching Image 2) -->
                                        <div class="nc-contact-shared-column">
                                            <div class="nc-shared-items-box">
                                                <div class="nc-shared-svg-icon">
                                                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                        <polyline points="21 15 16 10 5 21"></polyline>
                                                    </svg>
                                                </div>
                                                <div class="nc-shared-text-label">No shared items with this contact</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Placeholder when contact card does not exist -->
                                <div id="contact-card-placeholder" class="contact-iframe-placeholder" style="display: none;">
                                    <div class="empty-icon">👤</div>
                                    <h3>Контакт в Nextcloud Contacts ещё не создан</h3>
                                    <p>Нажмите кнопку ниже для автоматического создания и открытия карточки контакта в Nextcloud Contacts.</p>
                                    <button type="button" id="btn-widget-sync-contact" class="button primary">
                                        🔄 Создать контакт в Nextcloud Contacts
                                    </button>
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


