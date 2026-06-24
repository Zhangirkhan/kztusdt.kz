# Регистрация сервиса в Aitu Passport — данные для консоли

Значения для заполнения формы «Создание сервиса Партнёра» в клиентской консоли
Aitu Passport (прод: `https://clients.passport.aitu.io`, тест:
`https://clients.passport.test.supreme-team.tech`).

Продакшен-домен приложения: **`https://kztusdt.kz`**.

> Связанная реализация в коде: `config/aitu.php`, `app/Services/AituPassportService.php`,
> `app/Http/Controllers/AituPassportController.php`, маршруты `auth/aitu/*` в `routes/web.php`.

---

## 1. Данные сервиса

| Поле | Значение |
|---|---|
| Название сервиса | `kztusdt.kz` |
| Название сервиса латиницей RU | `kztusdt.kz` |
| Название сервиса латиницей KK | `kztusdt.kz` |
| Название сервиса латиницей EN | `kztusdt.kz` |
| Email | `admin@esl.kz` |
| Логотип (светлая тема) | загрузить PNG/JPEG, квадрат (например 82×82 px), прозрачный фон |
| Логотип (тёмная тема) | загрузить аналогично (опционально) |

---

## 2. Ссылки на юридические документы

Обе страницы публичны (доступны без авторизации).

| Поле | Значение |
|---|---|
| Ссылка на политику конфиденциальности | `https://kztusdt.kz/legal/privacy` |
| Ссылка на пользовательское соглашение | `https://kztusdt.kz/legal/terms` |

---

## 3. Номера телефонов

| Поле | Значение |
|---|---|
| Номер телефона | `+77476644108` |
| Комментарий | — |

---

## 4. Ссылки (URI)

| Поле в консоли | Значение |
|---|---|
| **Redirect URI** | `https://kztusdt.kz/auth/aitu/callback` |
| **Post Logout Redirect URI** | `https://kztusdt.kz/auth/aitu/logout/callback` |
| **Logout callback URI** | `https://kztusdt.kz/api/auth/aitu/logout` |
| **Phone Change Redirect URI** | `https://kztusdt.kz/auth/aitu/phone-changed` |

> **Важно:** по документации Aitu значение **Post Logout Redirect URI выбирается из
> списка Redirect URI**. Поэтому в поле **Redirect URI** добавьте оба URL:
> - `https://kztusdt.kz/auth/aitu/callback`
> - `https://kztusdt.kz/auth/aitu/logout/callback`
>
> (при необходимости добавьте и `https://kztusdt.kz/auth/aitu/phone-changed`),
> затем выберите logout-URL в поле Post Logout Redirect URI.

---

## 5. Данные для oauth-параметра `iin_signature` (опционально)

Нужно только если требуется запретить пользователю менять ИИН.
Подпись формируется алгоритмом **SHA256withRSA**, передаётся в **base64url**.

Сгенерировать пару ключей:

```bash
php artisan aitu:generate-iin-keys          # 2048 бит
php artisan aitu:generate-iin-keys --bits=1024   # если Aitu требует 1024
```

- **Публичный RSA ключ** → вставить в поле консоли.
- **Приватный ключ** → в `.env` как `AITU_IIN_PRIVATE_KEY="..."` (НЕ коммитить).

Публичный ключ (соответствует приватному ключу `AITU_IIN_PRIVATE_KEY` в `.env`):

```
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtoWLJ9PWkDUt9a1SAtKO
Dz4QNiZevwSMnQexZWYwkQ6v2R8orEpbZgGX7k3G5gnBqDlUj7APx+mBu7/A0N5J
VOLmcKYzaDfDyTMFz+ZBO2+FF1rzEjVh292Cc7qw3HAHpbvvwYnynFkJY03gKaI5
QHeUZDh2eHHv1RnbE8UDWikCFnuVS2+fWjxksBZRjJX8sPKwamZjVeg/QEk9CSgW
aIKBF75axHnlMIH/t3bNTmmGNWcnOUeNQL5yKowMPwBYAs9uTg5rAi2aEp1dcAXT
2dGe6M74JKDWYlPsVUiqUElJ6n6o04Rbf4hvfN5uhMWC5sOWIYe2DzKhZfNvnIJH
uwIDAQAB
-----END PUBLIC KEY-----
```

> Этот публичный ключ нужно вставить в поле «Публичный RSA ключ» консоли Aitu.
> Получить его из приватного ключа в `.env` можно командой:
> `openssl rsa -in private.pem -pubout` (или сгенерировать новую пару
> `php artisan aitu:generate-iin-keys`).

---

## 6. Доступы по подписке (опционально)

Подключаются по заявке через проектного менеджера Aitu Passport (указать `client_id`
и нужные scope):

- Удостоверение личности
- Фото лица
- `INTERNATIONAL_PASSPORT_MANUAL_3D_VERIFICATION`
- `CONFIDENCE_LEVEL`

---

## 7. Валидация на стороне клиента

| Поле | Значение |
|---|---|
| URL | `https://kztusdt.kz/api/auth/aitu/validate` |
| Тип авторизации | `Basic` |
| Secret | `15b5b036333895aad7e33d65899d144ae563d48e37530a00` |
| БИН | `260340021560` (БИН организации, см. `config/company.php` → `bin`) |
| Validator ID | `<выдаётся Aitu Passport / уточнить у менеджера>` |

Aitu Passport вызывает этот URL с **Basic-авторизацией**:
`Authorization: Basic base64(validator_id:secret)`. Те же значения нужно прописать
на стороне приложения в `.env`:

```dotenv
AITU_VALIDATOR_ID=<тот же Validator ID, что в консоли>
AITU_CLIENT_VALIDATION_SECRET=15b5b036333895aad7e33d65899d144ae563d48e37530a00
```

> Endpoint реализован и проверяет Basic-авторизацию: `GET` — проверка доступности
> (200 OK), `POST` — сверка логина/пароля с `AITU_VALIDATOR_ID` / `secret`,
> логирование payload и ответ `{"valid": true}`. Если `secret` пуст — проверка
> авторизации отключена. Validator ID сверяется только если задан в `.env`.
> Точный контракт тела запроса/ответа в публичной документации Aitu не описан.

---

## После создания сервиса

Aitu Passport выдаст `client_id` и `client_secret`. Пропишите в `.env`:

```dotenv
AITU_CLIENT_ID=<выданный client_id>
AITU_CLIENT_SECRET=<выданный client_secret>
AITU_BASE_URL=https://passport.aitu.io
AITU_SCOPE="openid phone"
# Опционально, если используется защита ИИН:
AITU_IIN_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"
```

Для **тестовой** площадки задайте тестовый `AITU_BASE_URL` (и при необходимости пути
эндпоинтов `AITU_AUTHORIZE_PATH` / `AITU_TOKEN_PATH` / `AITU_LOGOUT_PATH`).

Старт авторизации: `https://kztusdt.kz/auth/aitu/redirect`.
