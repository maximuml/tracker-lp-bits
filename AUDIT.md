# Аудит кода `maximuml/tracker-lp-bits`

**Репозиторий:** NexusPHP-форк на Laravel 12 + Filament 5, ~108 600 строк PHP в `app/`, 815 файлов, 60 миграций+.

**Дата аудита:** 2026-08-20

## Краткое резюме

Кодbase находится в середине миграции с legacy-процедурного NexusPHP на современный Laravel. Новые подсистемы (Announce, DTO, ValueObjects, Filament-admin) написаны качественно, с типизацией и покрыты PHPStan level 8. Однако значительная часть legacy-контроллеров и `app/Services/Legacy/*_content.php` несёт технический долг и несколько серьёзных проблем безопасности.

---

## 🔴 Критические проблемы безопасности

### 1. CSRF-защита практически отключена
**Severity: Critical** · `app/Http/Middleware/VerifyCsrfToken.php`

`$except` содержит ~70 маршрутов — почти все POST-эндпоинты приложения, включая админские:
`modtask`, `takeconfirm`, `delacctadmin`, `deletedisabled`, `massmail`, `adduser`, `takeinvite`, `takeupdate`, `takeamountupload`, `maxlogin`, `reset`, `clearcache`, `takestaffmess`, `bans`, `ipsearch` и т.д.

Аутентификация использует cookie (`c_secure_*`, которые дополнительно исключены из шифрования в `EncryptCookies.php`). Это позволяет классическую CSRF-атаку: злоумышленник может заставить залогиненного админа выполнить `modtask` (редактирование пользователей), `massmail`, `delacctadmin` и т.п. через простую HTML-форму.

**Рекомендация:** Убрать массовые исключения. Для legacy-форм внедрить либо CSRF-токен в скрытом поле, либо одноразовый nonce. Оставить исключения только для webhook'ов (`tg-webhook/*`).

### 2. Публичный эндпоинт `/cron` без аутентификации
**Severity: High** · `routes/legacy/public.php:32` · `app/Http/Controllers/SystemController.php:427-435`

Маршрут `/cron` зарегистрирован в `routes/legacy/public.php` (группа без `auth` middleware) и запускает `CleanupService::triggerCron()`. Хотя есть Redis-блокировки и интервальная защита от параллелизма, любой внешний посетитель может дёргать очистку, что создаёт DoS-вектор и позволяет злоумышленнику принудительно запускать тяжёлые задачи (`disableInactiveUsers`, `manageUserClasses`, `cleanupDeadTorrentsAndIpLogs`).

**Рекомендация:** Защитить маршрутом с IP-ограничением (loopback/доверенный cron-хост) или секретным токеном в query-string.

### 3. Слабое хеширование паролей (не bcrypt/argon2)
**Severity: High** · `app/Services/WebAuthService.php:66-95` · `app/Services/RegistrationService.php:173-177`

Пароли хешируются как `sha256($secret . sha256($password))` — одна итерация быстрого хеша. Также поддерживается legacy `md5($secret . $password . $secret)`. Это не использует PHP `password_hash()` (bcrypt/argon2). При утечке БД хеши легко брутфорсятся на GPU (миллиарды sha256/сек). `rehashPasswordIfRequired()` в `NexusWebUserProvider` помечен TODO и не реализован.

**Рекомендация:** Мигрировать на `password_hash(PASSWORD_BCRYPT)`/`PASSWORD_ARGON2ID`, реализовать `rehashPasswordIfRequired()` для апгрейда legacy-хешей при следующем логине.

### 4. Passkey-login через GET-URL
**Severity: Medium** · `app/Http/Controllers/AuthenticateController.php:68-84`

При `loginType === 'passkey'` регистрируется `GET /{secretUri}/{passkey}` — логин по passkey в URL. Passkey попадает в access-логи прокси, Referer-заголовки, историю браузера. Passkey — это долгосрочный секрет для announce-эндпоинта; его компрометация = полный доступ к аккаунту + трекеру.

**Рекомендация:** Использовать POST или одноразовый токен обмена.

---

## 🟠 Проблемы среднего уровня

### 5. Динамический диспетчер `AjaxService::{$action}()` на публичном маршруте
**Severity: Medium** · `app/Http/Controllers/UtilityController.php:58-90`

`/ajax` (в `public.php`, только `throttle:ajax`) вызывает `AjaxService::{$action}($params)` где `$action` — пользовательский ввод. Проверяется только `method_exists`. Большинство методов делегируют permission-чеки в репозитории (`checkPermission`, `Permission::assertCan`), но паттерн опасен: **любой новый публичный метод `AjaxService` автоматически становится доступным без явной регистрации маршрута**. Если разработчик добавит метод без внутренней проверки прав — получится IDOR/privilege escalation.

**Рекомендация:** Явный whitelist допустимых `$action` или регистрация каждого действия отдельным маршрутом.

### 6. `confirmemail` — смена email без валидации значения
**Severity: Medium** · `app/Http/Controllers/UtilityController.php:366-395`

Email берётся из URL-пути и записывается в БД без проверки формата/длины. Зная `editsecret` (одноразовый секрет), можно установить любой мусор в `email`, что сломает восстановление пароля и может эксплуатироваться в дальнейших контекстах (отправка писем, отображение).

**Рекомендация:** `validator(['email' => $email], ['email' => 'required|email|max:255'])`.

### 7. `Hooks::applyFilter('role_query_conditions', ...)` → `whereRaw`
**Severity: Medium (plugin trust boundary)** · `app/Http/Controllers/StaffController.php:695-710`

`$conditions` (массив SQL-фрагментов) склеивается через `OR` и вставляется в `whereRaw("($whereStr)")`. Базовые `class IN (...)` безопасны (`intval`), но фильтр `role_query_conditions` позволяет плагинам добавить произвольный SQL. Это дизайн-решение, но при установке вредоносного плагина = SQL injection.

**Рекомендация:** Документировать как границу доверия; в идеале — параметризовать условия вместо сырых строк.

### 8. `DB::raw` с конкатенацией (безопасно, но хрупко)
**Severity: Low** · `app/Services/AnnounceService.php:447-449`

`NexusDB::raw('uploaded + ' . $this->uploadedIncrementForUser)` — значение из типизированного `int`-свойства, безопасно. Но аналогичный паттерн в `Cleanup/Tasks.php:769` (`'invites + ' . $addInvite`) и `PeerLifecycle.php:367-368` зависит от того, что источники — int. Хрупко при будущих изменениях.

### 9. `SET sql_mode=''` в DBPdo
**Severity: Low (качество данных)** · `app/Nexus/Database/DBPdo.php:29-32`

Отключает все strict-режимы MySQL → молчаливое обрезание дат (`0000-00-00`), потеря данных при переполнении. В коде видно workaround'ы (миграция `2025_06_04_153154_update_invalid_datetime_value.php`).

**Рекомендация:** Убрать `SET sql_mode=''`; мигрировать схему под `STRICT_TRANS_TABLES,NO_ZERO_DATE`.

---

## 🟡 Качество кода

### 10. PHPStan: высокий уровень, но baseline пустой — реальное покрытие неполное
`phpstan.neon`

CI прогоняет level 0 (весь `app/`), затем level 5/6/7/8 по нарастающей. Level 8 применяется к `app/` **кроме** `app/Services/Legacy/partials` и `app/Services/Legacy/*_content.php` — то есть самая legacy-часть исключена из стат. анализа. `phpstan-baseline-8.neon` пустой (`ignoreErrors: []`), что хорошо, но означает, что legacy partials вообще не анализируются.

### 11. Тестовое покрытие: 61 unit + 7 feature, но legacy-контроллеры не покрыты
`AdminController` (1003 строки), `InfoController` (965), `TorrentActionController` (915), `StaffController` (911), `ModerationController` (752), `SystemController` (735) — крупнейшие контроллеры — не имеют feature-тестов. `CriticalPathTest` требует внешнего Docker-окружения (`CRITICAL_PATH_BASE_URL`) и skipped в обычном CI. Покрыты: Announce, DTO, ValueObjects, Cleanup, Support-утилиты.

### 12. Логические баги в legacy
`app/Services/Legacy/offers_content.php:27-28`

`preg_match('/^[0-9]+$/', !$id)` — `!$id` это **boolean**, не строка. Валидация бессмысленна (всегда проходит для `true`→`"1"`). То же на строке 362. Должно быть `$id`.

### 13. Дублирование систем аутентификации
В `composer.json` одновременно `laravel/passport` и `laravel/sanctum`. API-routes используют `auth:sanctum`, но `OauthController::userInfo` использует `auth:api` (Passport). Две параллельные системы токенов — источник путаницы и потенциальных дыр.

### 14. Дублирование админ-панелей
Filament (`app/Filament/Resources/`) и legacy `AdminController`/`StaffController`/`SettingController` работают параллельно. `routes/legacy/auth.php` содержит десятки админских `.php`-маршрутов. Неясно, какая система канонична.

### 15. Три поисковых/аналитических движка одновременно
`meilisearch`, `elasticsearch`, `clickhouse` — все в `composer.json` и `.env.example`. README упоминает только MeiliSearch. Вероятно legacy/неиспользуемые зависимости — увеличивают поверхность атаки и сложность деплоя.

### 16. Секреты в `.env.example`
`.env.example:4-5`

`APP_KEY=base64:WUbN2wa2kl3E1VDW4iKaH3RBHw3hKY7BK0hWEkBZmGg=` — реальный ключ в примере (должен быть пустым/плейсхолдером). `MEILISEARCH_MASTER_KEY=nexusphp_default_key` — дефолтный "секрет". `APP_DEBUG=true` по умолчанию.

### 17. `@`-suppression (26 случаев) и пустые catch
`app/Repositories/UserPasskeyRepository.php:165` и др. — `catch (Exception $e) {}` без логирования. `NexusWebUserProvider::rehashPasswordIfRequired` — TODO-заглушка.

---

## 🟢 Положительные находки

- **Announce-пайплайн** (`AnnounceService`, `AnnounceRequestDto`, ValueObjects) — хорошо структурирован, типизирован, с rate-limiting, cheater-detection, Redis-транзакциями. IP-резолвер `Network::clientIp()` корректно обходит trusted-proxy chain справа налево (не позволяет инжектить левый IP).
- **Torrent download** использует JWT (HS256 + HKDF-ключ из passkey) для `downhash` с TTL 3600с, `hash_equals` для сравнения, `Gate::authorize('download')`.
- **Загрузки** — `BitbucketUploadController` проверяет `basename === filename` (anti-traversal), валидирует через `getimagesize()`. `AttachmentLegacyService` имеет banlist расширений + `isDangerousMimeType()` через `finfo`.
- **Mass assignment** — модели используют `$fillable` (User, Torrent, Peer, Message, Comment проверены).
- **SQL injection** в основных путях — не найдена. `whereRaw` с параметрами использует `?`-плейсхолдеры. Конкатенации в `NexusDB::raw` идут от int-источников.
- **XSS** — `Comment::format()` по умолчанию `htmlspecialchars($s)`. Shoutbox рендерит через `Comment::format($text, true, ...)`. Однако 77 `{!! !!}` в blade-шаблонах — стоит ревьюнить каждый (многие безопасны: пагинация, username-рендер, но `[raw]` BBCode-тег обходит escape).
- **PHPStan level 8** в CI для основной части `app/`.
- **`composer audit` + `npm audit`** в CI.

---

## Приоритеты исправления

| # | Проблема | Срочность |
|---|----------|-----------|
| 1 | CSRF-исключения (VerifyCsrfToken) | Немедленно |
| 2 | Публичный `/cron` | Немедленно |
| 3 | Хеширование паролей (sha256→bcrypt) | Высокая |
| 4 | Passkey-login через GET | Высокая |
| 5 | `confirmemail` без валидации email | Средняя |
| 6 | Whitelist для `ajax`-диспетчера | Средняя |
| 7 | Секреты в `.env.example` | Средняя |
| 8 | Баги `preg_match(..., !$id)` | Низкая |
| 9 | `SET sql_mode=''` | Низкая |
| 10 | Убрать неиспользуемые поисковые движки / одну из auth-систем | Рефакторинг |
