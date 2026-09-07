# Спецификация интеграции: EasyAppointments -> n8n -> MiniCRM (Nextcloud)

Данный документ описывает трёхэтапную архитектуру автоматизации обработки входящих заявок из EasyAppointments в MiniCRM (Nextcloud).

---

## Архитектура системы

`
[ EasyAppointments ]
        │  (Webhook: POST booking payload)
        ▼
[ n8n Automation Engine ]
        │
        ├── Этап 1: Нормализация данных (Email, Phone E.164)
        │           Дедупликация (GET /api/v1/clients/find)
        │
        ├── Этап 2: Маршрутизация
        │     ├── Ветка А (Новый клиент):
        │     │     ├─ MiniCRM: Создание профиля (customer_mini_crm_id)
        │     │     ├─ Files: /Users/{Name} - {CRM_ID}/{Event_ID} (FileDrop)
        │     │     ├─ Deck: Доска/карточка из шаблона {service_name}
        │     │     ├─ Contacts: CardDAV vCard (ФИО, Телефон, Email, Адрес, Ссылки)
        │     │     └─ Обогащение MiniCRM (вкладки Contact, Files, Actions)
        │     │
        │     └── Ветка Б (Существующий клиент):
        │           ├─ Получение существующих путей и ID
        │           ├─ Files: Подпапка {Event_ID} внутри существующей папки
        │           ├─ Deck: Карточка под новую услугу
        │           └─ MiniCRM: Добавление Activity в Actions/Timeline
        │
        └── Этап 3: Календарь и Email-коммуникации
              ├─ Nextcloud Calendar (contact@violatax.ca)
              ├─ Email 1: Мгновенное подтверждение со ссылкой FileDrop
              ├─ Email 2: Напоминание за 24 часа с чек-листом документов
              └─ Email 3: Follow-up через 2 часа с просьбой оставить Google Review
`

---

## Этап 1. Инициализация и подготовка данных

### 1.1. Входящий Webhook (EasyAppointments)
`json
{
  "appointment_id": 23,
  "create_datetime": "2026-09-07 18:46:41",
  "update_datetime": "2026-09-07 18:46:41",
  "book_datetime": "2026-09-07 18:46:41",
  "start_datetime": "2026-09-11 09:00:00",
  "end_datetime": "2026-09-11 10:00:00",
  "customer_id": 11,
  "customer_first_name": "Ivan",
  "customer_last_name": "Petrov",
  "customer_email": "Ivan.Petrov@gmail.com",
  "customer_phone": "1403-397-1000",
  "customer_address": "Keystone Grove West",
  "customer_city": "Lethbridge",
  "customer_zip_code": "T1J 5E2",
  "provider_first_name": "contact",
  "provider_last_name": "violatax.ca",
  "service_name": "Family tax return | Сімейна декларація",
  "service_duration": 60,
  "service_price": "140.00"
}
`

### 1.2. Нормализация (n8n Code Node)
* **Email**: `email.trim().toLowerCase()`
* **Телефон**: канадский/североамериканский E.164 (`1403-397-1000` -> `+14033971000`)
* **Event ID**: `{appointment_id}_{provider_first_name}_{create_datetime_formatted}`

### 1.3. Дедупликация (Запрос к MiniCRM)
* **Запрос**: `GET /apps/minicrm/api/v1/clients/find`
* **Параметры**:
  * `customer_id`: `11`
  * `customer_phone`: `+14033971000`
  * `customer_email`: `ivan.petrov@gmail.com`
* **Успешный ответ (Найден)**:
  `json
  {
    "found": true,
    "customer_mini_crm_id": 11,
    "client": {
      "id": 11,
      "full_name": "Ivan Petrov",
      "phone": "+14033971000",
      "email": "ivan.petrov@gmail.com",
      "folder_path": "/Users/Ivan Petrov - 11"
    },
    "customer_file_path": "/Users/Ivan Petrov - 11",
    "matched_by": "identity"
  }
  `
* **Ответ если не найден**: `status: 404`, `{"found": false, "customer_mini_crm_id": null}`.

---

## Этап 2. Ветвление логики (Маршрутизация)

### Ветка А: Новый клиент (`found === false`)
1. **Создание в MiniCRM**:
   * Метод: `POST /apps/minicrm/api/v1/leads/ingest`
   * Создаётся карточка в таблице `oc_minicrm_clients`.
   * Присваивается `customer_mini_crm_id`.
   * В `oc_minicrm_identities` сохраняется `easyappointments:11`.
2. **Nextcloud Files**:
   * Корневая папка: `/Users/{first_name} {last_name} - {customer_mini_crm_id}`
   * Подпапка: `{appointment_id}_{provider_first_name}_{create_datetime}`
   * Создаётся публичная ссылка загрузки документов (FileDrop).
3. **Nextcloud Deck**:
   * Создаётся карточка/доска по названию услуги `{service_name}`.
   * Ссылка сохраняется в `customer_desk_id`.
4. **Nextcloud Contacts**:
   * Создаётся vCard 3.0 с адресом `Keystone Grove West, Lethbridge, AB, T1J 5E2, Canada`.
   * В заметках сохраняются ссылки на папку файлов и Deck.

### Ветка Б: Существующий клиент (`found === true`)
1. Из ответа дедупликации извлекаются `customer_mini_crm_id`, `customer_file_path`.
2. Генерируется **Event ID**: `{customer_mini_crm_id}_EasyAppointments_{create_datetime}`.
3. Внутри существующей папки `customer_file_path` создаётся подпапка `Event ID` с FileDrop.
4. В Deck создаётся новая карточка для текущей услуги.
5. В MiniCRM добавляется новая активность без дублирования клиента.

---

## Этап 3. Календарь и Email-коммуникации

### 3.1. Создание события в Nextcloud Calendar
* **Календарь**: `contact@violatax.ca`
* **Время**: `2026-09-11 09:00:00` -> `2026-09-11 10:00:00` (America/Edmonton)
* **Заголовок**: `Встреча: Ivan Petrov (+14033971000)`
* **Описание**:
  `	ext
  Услуга: Family tax return | Сімейна декларація (60 мин, .00)
  Специалист: contact violatax.ca
  Клиент: ID 11 (Ivan Petrov)
  Заявка: EasyAppointments #23
  Адрес: Keystone Grove West, Lethbridge, AB, T1J 5E2

  Папка документов: /Users/Ivan Petrov - 11
  Ссылка клиенту для загрузки файлов (FileDrop): [FileDrop URL]
  Карточка в Deck: [Deck URL]
  `

### 3.2. Автоматические Email-сообщения

#### 1. Подтверждение записи (Мгновенно)
* **Кому**: `{customer_email}`
* **Тема**: `Подтверждение бронирования: Family tax return - 11 сентября 2026, 09:00`
* **Текст**:
  * Подтверждение даты и времени консультации.
  * Индивидуальная ссылка **FileDrop** для предварительной безопасной загрузки документов.

#### 2. Напоминание о встрече (За 24 часа)
* **Триггер**: n8n Wait Node (`start_datetime - 24 hours`)
* **Кому**: `{customer_email}`
* **Тема**: `Напоминание о консультации - ViolaTax`
* **Текст**:
  * Время и локация офиса в Lethbridge с инструкцией по парковке.
  * Чек-лист необходимых оригиналов документов.

#### 3. Follow-up и отзыв (Через 2 часа после окончания)
* **Триггер**: n8n Wait Node (`end_datetime + 2 hours`)
* **Кому**: `{customer_email}`
* **Тема**: `Благодарим за визит в ViolaTax!`
* **Текст**:
  * Благодарность за сотрудничество.
  * Прямая ссылка на Google Business для отзыва.
