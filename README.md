# Тестовое задание — Laravel + Vue Developer

Монорепозиторий содержит три проекта:

| Проект | Стек | Папка |
|---|---|---|
| **CakePHP UTM Stats** | CakePHP 2, MySQL, Apache | `/` (корень) |
| **Reviews App** | Laravel 11, Vue 3, MySQL, nginx | `reviews-app/` |
| **Elements App** | React, Express.js, nginx | `elements-app/` |

---

## 1. Ошибки при Docker-сборке — исправления

### Основное приложение (CakePHP)

```bash
docker compose up --build
```

Сборка проходит без ошибок. Приложение доступно на http://localhost:8080

### reviews-app — исправленные ошибки

**Проблема 1 — Backend Dockerfile:** отсутствовала директория `bootstrap/cache`,
из-за чего `php artisan key:generate` падал с ошибкой
`The /var/www/bootstrap/cache directory must be present and writable`.

**Исправление:** директория создаётся до запуска artisan; генерация ключа
перенесена в `docker-entrypoint.sh`, который выполняется при старте контейнера,
когда база данных уже доступна.

**Проблема 2 — Frontend Dockerfile:** команда `npm ci` требует `package-lock.json`,
которого не было в репозитории.

**Исправление:** заменено на `npm install`.

**Проблема 3 — отсутствовал `bootstrap/providers.php`:** файл обязателен для
Laravel 11 — без него не регистрируются сервис-провайдеры приложения.

**Исправление:** файл добавлен в репозиторий.

Запуск reviews-app:

```bash
cd reviews-app
docker compose up --build
```

- Frontend: http://localhost:5173
- Backend API: http://localhost:8000/api
- Логин: `user@example.com` / пароль: `password`

---

## 2. Миграции и сиды в Backend (reviews-app)

Миграции и сиды запускаются автоматически при старте контейнера через
`docker-entrypoint.sh`:

```sh
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
```

Сид создаёт тестового пользователя:

- email: `user@example.com`
- пароль: `password`

Схема базы данных:

```
users                   — id, name, email, password
organizations           — id, user_id, yandex_url, name, average_rating,
                          rating_count, review_count, last_parsed_at
reviews                 — id, organization_id, external_id, author,
                          rating, text, published_at
personal_access_tokens  — таблица токенов Sanctum
```

---

## 3. Парсер Яндекс Карт — как работает и почему может не работать

### Стратегия парсинга

Яндекс Карты используют внутренний REST-эндпоинт
`/maps/api/business/fetchReviews`, который браузер вызывает через XHR.
Парсер воспроизводит этот запрос:

1. Загружает страницу организации с browser-like заголовками для получения
   CSRF-токена и сессионных cookies (через Guzzle `CookieJar`).
2. Вызывает API-эндпоинт в цикле с пагинацией (`skip` / `limit` по 30 отзывов)
   до достижения лимита 600 отзывов или пока API не вернёт пустой ответ.
3. Между запросами выдерживается задержка 500 мс для снижения риска
   блокировки по rate limit.

### Почему парсер может не работать

Яндекс активно защищает свои API от ботов:

- **Bot-detection:** при подозрении в автоматизации Яндекс возвращает
  captcha-страницу вместо HTML с CSRF-токеном.
- **CSRF-токен:** структура HTML периодически меняется; парсер использует
  несколько регулярных выражений для извлечения токена.
- **Изменение эндпоинта:** задействованы два варианта URL эндпоинта
  (`/maps/api/...` и `/maps/-/api/...`).

### Что сделано для повышения надёжности

- Случайный выбор User-Agent из нескольких актуальных строк Chrome.
- Несколько паттернов для извлечения CSRF-токена.
- Два варианта API-эндпоинта с fallback.
- Детальное логирование каждого шага в Laravel Log.
- Graceful-прерывание при ошибке страницы (не ломает весь запрос).

### Альтернативы для production

| Метод | Плюсы | Минусы |
|---|---|---|
| Headless-браузер (Puppeteer/Playwright) | Обходит JS-рендеринг и часть bot-detection | Высокое потребление RAM, медленный старт |
| Rotating proxies | Снижает шанс IP-бана | Дополнительные расходы, сложность |
| Официальный API Яндекса | Стабильный, без bot-detection | Требует аккаунт организации в Яндекс Бизнес |

---

## 4. Архитектура reviews-app

### Backend (Laravel 11)

- **Аутентификация:** email/password → Laravel Sanctum bearer-token.
- **`OrganizationController`** — CRUD настроек организации + запуск парсера.
- **`ReviewController`** — пагинированный список отзывов (50/страница).
- **`YandexMapsParser`** — сервис парсинга, зарегистрирован как singleton.
- **`StoreOrganizationRequest`** — валидация URL (поддерживает форматы
  `yandex.ru`, `maps.yandex.ru`, `yandex.com`).
- Конфигурация CORS в `config/cors.php` (разрешён только `FRONTEND_URL`).

### Frontend (Vue 3 + Vite)

- Composition API, Vue Router, Axios.
- Три страницы: Login, Settings (ввод URL + статистика), Reviews (список с пагинацией).
- Bearer-токен хранится в `localStorage`.
- Interceptor Axios автоматически перенаправляет на `/login` при 401.

---

## 5. Запуск элементов-приложения (Elements App)

```bash
cd elements-app
docker compose up --build
```

- Frontend: http://localhost:3000
- Backend API: http://localhost:3001

---

## 6. Запуск CakePHP UTM Stats

```bash
docker compose up --build
```

- Приложение: http://localhost:8080

Статистика UTM-меток отображается в виде дерева
`Source → Medium → Campaign → Content → Term` с пагинацией.
