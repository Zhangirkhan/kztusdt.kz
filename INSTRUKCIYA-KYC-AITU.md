# KYC через Aitu Passport — инструкция

## Что это

Раньше проверку личности (KYC) делали вручную или через Sumsub. Теперь добавлен
третий вариант — **Aitu Passport**: Aitu сам проверяет личность пользователя и
присылает в наш сервис только результат «пройдено / не пройдено».

При успешной верификации статус KYC становится `approved` **автоматически**, и
кошелёк создаётся так же, как при ручном одобрении (через Telegram приходит
уведомление). Ручной KYC и Sumsub остаются в коде и доступны как запасной вариант.

## Как включить

В `/var/www/crypto-exchange/backend/.env`:

```
KYC_PROVIDER=aitu
AITU_SCOPE="openid phone CONFIDENCE_LEVEL"
```

После правки `.env`:

```bash
cd /var/www/crypto-exchange/backend
php artisan config:cache
systemctl restart php8.3-fpm crypto-queue
```

> Если вход через Aitu начнёт выдавать ошибку scope — значит доступ на верификацию
> ещё не подключён к вашему `client_id`. Временно верните `AITU_SCOPE="openid phone"`
> и запросите доступ (раздел «Доступы по подписке», например `CONFIDENCE_LEVEL`) у
> менеджера Aitu Passport.

## Как это работает для клиента

1. Клиент входит через Aitu Passport (кнопка «Войти через Aitu»).
2. Aitu проводит верификацию личности на своей стороне.
3. В ответе (`id_token`) приходит результат проверки.
4. **Пройдено** → KYC `approved`, кошелёк создаётся автоматически.
   **Не пройдено** → KYC `rejected`, клиент видит причину и может повторить на `/kyc`.
5. Если клиент вошёл по телефону (не через Aitu), на странице `/kyc` он увидит
   кнопку «Пройти верификацию через Aitu» — она запускает ту же проверку.

## Какой claim несёт результат (важно)

Aitu может называть поле результата по-разному. По умолчанию мы проверяем такие
ключи `id_token`: `kyc_verified, identity_verified, verified, verification_status,
confidence_level, confidenceLevel`.

«Пройдено»: `true, 1, yes, high, verified, passed, success, full, approved, green, medium`.
«Не пройдено»: `false, 0, no, low, failed, rejected, declined, red, none`.

**Как узнать реальное имя claim:** после первого входа с `KYC_PROVIDER=aitu`
посмотрите лог `storage/logs/auth-YYYY-MM-DD.log` — событие `kyc.aitu.no_verdict`
содержит `claim_keys` (список полей, которые реально прислал Aitu, без значений).
Найдите там нужное поле и при необходимости задайте его в `.env`:

```
AITU_VERIFY_CLAIMS=имя_поля_из_лога
AITU_VERIFY_PASSED_VALUES=пройдено_значение1,пройдено_значение2
AITU_VERIFY_FAILED_VALUES=непройдено_значение1
```

Пустые значения = берутся значения по умолчанию (см. выше). После правки —
`php artisan config:cache`.

## Что меняется в коде

- `app/Services/AituKycService.php` — читает вердикт из claims, авто-одобряет/отклоняет
  KYC, ставит задачу создания кошелька, шлёт уведомление, пишет в audit log
  (`kyc.aitu.approved` / `kyc.aitu.rejected`).
- `AituPassportController@callback` — после входа применяет вердикт (если провайдер `aitu`).
- `KycController@show` + `User::kycMeta()` — провайдер `aitu` + фоллбэк на ручной KYC,
  если Aitu не настроен.
- `resources/js/Pages/Kyc.vue` — кнопка «Пройти верификацию через Aitu».
- `config/aitu.php` — секция `verification` (claims / passed / failed).

## Откат

Вернуть прежний провайдер: в `.env` поставить `KYC_PROVIDER=sumsub` (или `manual`),
затем `php artisan config:cache`. Код Sumsub и ручного KYC не удалялся.
