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

                    <!-- Header Quick Action Buttons -->
                    <div class="client-header-actions">
                        <button type="button" id="tab-btn-files" class="header-tab-btn" title="Открыть персональную директорию в проводнике Files">
                            📁 Files
                        </button>
                        <button type="button" id="tab-btn-contact" class="header-tab-btn" title="Подробная карточка контакта в Nextcloud Contacts">
                            👤 Contact
                        </button>
                        <button type="button" id="tab-btn-actions" class="header-tab-btn" title="Задачи и активности в Nextcloud Deck">
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

                <!-- Right Column: Multichannel Timeline & Messaging -->
                <div class="minicrm-main-panel">
                    <div class="minicrm-timeline-container">
                        <div class="timeline-header-bar">
                            <div class="timeline-title-wrap">
                                <h3>Мультиканальная история коммуникаций</h3>
                                <span class="timeline-sync-badge">⚡ n8n Sync</span>
                            </div>
                            <span class="timeline-meta-hint">Telegram, WhatsApp, Email, Deck</span>
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
                                <option value="email">✉️ Email</option>
                                <option value="whatsapp">💬 WhatsApp</option>
                                <option value="telegram">✈️ Telegram</option>
                                <option value="sms">📱 SMS</option>
                            </select>
                            <input type="text" id="reply-subject-input" placeholder="Тема письма..." />
                        </div>
                        <div class="reply-textarea-container">
                            <textarea id="reply-message-text" rows="2" placeholder="Введите сообщение клиенту (запрос форм T4/T5, ссылка на FileDrop, статус CRA)..."></textarea>
                            <button id="reply-send-button" class="primary">Отправить через n8n</button>
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


