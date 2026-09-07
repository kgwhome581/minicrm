<?php

declare(strict_types=1);

/** @var array $_ */
?>
<div id="minicrm-app" class="minicrm-container">
    <div id="app-navigation" class="minicrm-navigation">
        <div class="minicrm-search-bar">
            <input type="text" id="client-search-input" placeholder="Поиск (Имя, Телефон, Email)..." />
        </div>
        <ul id="client-list" class="minicrm-client-list">
            <li class="loading-placeholder">Загрузка клиентов...</li>
        </ul>
    </div>

    <div id="app-content" class="minicrm-content">
        <div id="client-view-empty" class="minicrm-empty-state">
            <div class="empty-icon">📁</div>
            <h2>Выберите клиента из списка</h2>
            <p>Здесь отобразится информация о клиенте, файлы, задачи из Deck и мультиканальная переписка.</p>
        </div>

        <div id="client-view-detail" class="minicrm-detail" style="display: none;">
            <!-- Header Card -->
            <div class="minicrm-client-header">
                <div class="client-title-row">
                    <h1 id="detail-client-name">ФИО Клиента</h1>
                    <button type="button" id="btn-edit-client-name" class="btn-icon" title="Редактировать имя" onclick="openClientNameEditor()">✏️</button>
                    <span id="detail-client-id-badge" class="badge">ID: #0</span>
                </div>
                <div id="client-name-edit-box" class="name-edit-box" style="display: none; margin: 10px 0; gap: 8px;">
                    <input type="text" id="input-edit-client-name" placeholder="Введите Фамилию и Имя" style="padding: 6px 12px; font-size: 15px; min-width: 260px;" onkeydown="if(event.key==='Enter')saveClientName();if(event.key==='Escape')closeClientNameEditor();" />
                    <button type="button" id="btn-save-client-name" class="primary" onclick="saveClientName()">Сохранить</button>
                    <button type="button" id="btn-cancel-client-name" onclick="closeClientNameEditor()">Отмена</button>
                </div>
                <div class="client-meta-row">
                    <div class="meta-item">
                        <span class="meta-label">📞 Телефон:</span>
                        <a id="detail-client-phone" href="#">—</a>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">✉️ Email:</span>
                        <a id="detail-client-email" href="#">—</a>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">📂 Папка:</span>
                        <a id="detail-client-folder" href="#" target="_blank">Открыть в Files</a>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">📤 Ссылка клиенту (FileDrop):</span>
                        <a id="detail-client-filedrop" href="#" target="_blank">Загрузка документов</a>
                    </div>
                </div>
            </div>

            <!-- Activity / Meeting Info Bar -->
            <div id="detail-activity-card" class="minicrm-activity-banner">
                <div class="banner-item">
                    <strong>📅 Встреча:</strong> <span id="detail-meeting-time">—</span>
                </div>
                <div class="banner-item">
                    <strong>📌 Статус:</strong> <span id="detail-activity-status" class="status-pill">—</span>
                </div>
                <div class="banner-item">
                    <strong>🎯 Задача в Deck:</strong> <span id="detail-deck-link">—</span>
                </div>
            </div>

            <!-- Multichannel Timeline -->
            <div class="minicrm-timeline-container">
                <h3>Мультиканальная история коммуникаций (Timeline)</h3>
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

<script>
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

            // Update item in sidebar
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
