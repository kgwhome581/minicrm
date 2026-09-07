# MiniCRM Nextcloud App (ViolaTax)

Кастомное приложение **MiniCRM** для экосистемы **Nextcloud**, разработанное специально для практики налогового и бухгалтерского учета (ViolaTax).

Приложение автоматизирует сквозной цикл работы с клиентами:
1. **Прием лидов из Easypoint через n8n** одним атомарным запросом к API.
2. **Nextcloud Files**: Создание персональных папок клиента и генерация публичной ссылки **File Drop (Upload Only)** для безопасной загрузки налоговых документов (T4, T5, чеков).
3. **Nextcloud Calendar**: Автоматическое бронирование слота встречи в календаре специалиста с учетом таймзоны `America/Edmonton`.
4. **Nextcloud Deck**: Создание карточки задачи на доске с привязкой контактов, папки документов и ссылки FileDrop.
5. **Мультиканальный лог сообщений**: Единый Timeline переписки (Email, Telegram, WhatsApp, Facebook) с поддержкой вложений и возможностью прямого ответа из интерфейса.
6. **Интеграция с TaxCycle**: Совместимая структура хранения файлов для прямой работы бухгалтеров через смонтированные сетевые папки.

---

## Структура приложения

```text
minicrm/
├── appinfo/
│   ├── info.xml                  # Манифест Nextcloud (id: minicrm, версия 1.0.0)
│   └── routes.php                # Маршруты Web UI и REST API
├── lib/
│   ├── AppInfo/
│   │   └── Application.php       # Регистрация DI-сервисов приложения
│   ├── Controller/
│   │   ├── ApiController.php     # REST API для n8n (ingest, messages, clients, timeline)
│   │   └── PageController.php    # Рендеринг интерфейса MiniCRM
│   ├── Db/
│   │   ├── Client.php & ClientMapper.php       # Таблица minicrm_clients
│   │   ├── Activity.php & ActivityMapper.php   # Таблица minicrm_activities
│   │   ├── Identity.php & IdentityMapper.php   # Таблица minicrm_client_identities
│   │   └── Message.php & MessageMapper.php     # Таблица minicrm_messages
│   ├── Migration/
│   │   └── Version1000Date20260906000000.php   # Автомиграция 4 таблиц БД
│   └── Service/
│       ├── PhoneNormalizer.php       # Нормализация номеров (E.164 для Канады/США)
│       ├── FolderService.php         # Создание папок и генерация File Drop ссылок
│       ├── CalendarBridgeService.php # Запись встреч в календарь (RFC 5545 CalDAV)
│       ├── DeckBridgeService.php     # Создание карточек в Nextcloud Deck
│       └── IngestionService.php      # Единый фасадный сервис обработки лидов
├── templates/
│   └── main.php                  # Шаблон интерфейса
├── css/
│   └── minicrm-style.css         # Стили с поддержкой тем Nextcloud
├── js/
│   └── minicrm-main.js           # Клиентский скрипт интерфейса
└── img/
    └── app.svg                   # Иконка навигации
```

---

## Установка и активация в Nextcloud

1. Скопируйте папку `minicrm` в директорию приложений вашего Nextcloud-сервера:
   ```bash
   cp -r minicrm /var/www/nextcloud/apps/
   # или смонтируйте как volume в Docker
   ```

2. Установите правильные права доступа (для пользователя веб-сервера `www-data` или `nginx`):
   ```bash
   chown -R www-data:www-data /var/www/nextcloud/apps/minicrm
   ```

3. Активируйте приложение через Nextcloud CLI (`occ`):
   ```bash
   sudo -u www-data php /var/www/nextcloud/occ app:enable minicrm
   ```
   *При активации автоматически выполнятся миграции и создадутся все 4 таблицы базы данных.*

4. Настройте секретный токен для API (для вызовов из n8n):
   ```bash
   sudo -u www-data php /var/www/nextcloud/occ config:app:set minicrm api_token --value="ВАШ_СЕКРЕТНЫЙ_ТОКЕН"
   ```

5. (Опционально) Настройте пользователя-владельца хранилища файлов:
   ```bash
   sudo -u www-data php /var/www/nextcloud/occ config:app:set minicrm storage_user --value="admin"
   ```

---

## Интеграция с n8n

### Главный фасадный вызов: Прием лида из Easypoint
При наступлении события в Easypoint n8n делает один `HTTP Request`:

- **Метод:** `POST`
- **URL:** `https://<ВАШ_NEXTCLOUD>/index.php/apps/minicrm/api/v1/leads/ingest`
- **Заголовки:**
  - `Content-Type: application/json`
  - `Authorization: Bearer ВАШ_СЕКРЕТНЫЙ_ТОКЕН`
- **Тело запроса (JSON):**
  ```json
  {
    "client_name": "Иван Петров",
    "phone": "780-123-4567",
    "email": "ivan@example.com",
    "meeting_datetime": "2026-09-15 14:00:00",
    "meeting_timezone": "America/Edmonton",
    "responsible_specialist": "admin",
    "source": "easypoint",
    "notes": "Подготовка персональной декларации T1 за 2024 год"
  }
  ```

- **Ответ MiniCRM:**
  ```json
  {
    "status": "success",
    "is_new_client": true,
    "client": {
      "id": 1,
      "uuid": "60a1d471-f925-4fc1-b0db-529a3a8ceb11",
      "full_name": "Иван Петров",
      "phone": "+17801234567",
      "email": "ivan@example.com"
    },
    "activity": {
      "id": 1,
      "activity_uuid": "04bfbfb6-b514-41d7-b892-2c6b328a6fcf",
      "status": "scheduled",
      "meeting_time": "2026-09-15T14:00:00-06:00"
    },
    "file_drop_url": "https://cloud.violatax.ca/s/d8fK9xL2sPq",
    "folder_path": "/ViolaTax_Clients/Иван Петров_60a1d471/04bfbfb6-b514-41d7-b892-2c6b328a6fcf",
    "deck_task_id": 42,
    "calendar_event_id": "c178bf71-55a2-4a0b-93bf-478627b0f021"
  }
  ```

После получения ответа n8n берет `file_drop_url` и отправляет клиенту красивое письмо/сообщение с ссылкой для загрузки документов!

---

### Запись сообщений из чатов (Telegram / WhatsApp / Email)
При получении входящего сообщения от клиента n8n вызывает:

- **Метод:** `POST`
- **URL:** `https://<ВАШ_NEXTCLOUD>/index.php/apps/minicrm/api/v1/messages`
- **Тело запроса:**
  ```json
  {
    "channel": "whatsapp",
    "direction": "inbound",
    "sender_recipient": "+17801234567",
    "content": "Здравствуйте! Загрузил чеки и T4 по ссылке.",
    "external_message_id": "wamid.HBgLMTc4MDEy...",
    "attachments": [
      "/ViolaTax_Clients/Иван Петров_60a1d471/04bfbfb6/T4_slip.pdf"
    ]
  }
  ```
  *Поле `external_message_id` автоматически защищает от дублирования при повторной отправке вебхуков.*
