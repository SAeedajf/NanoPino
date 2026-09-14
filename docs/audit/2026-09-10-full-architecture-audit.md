# ممیزی معماری و بلوغ NanoPino — ۲۰۲۶/۰۹/۱۰

> این گزارش تاریخی است. شماره‌گذاری roadmap آن با Phase Matrix فعلی یکسان
> نیست و برای وضعیت فعلی فازها authoritative محسوب نمی‌شود.

## خلاصه اجرایی

وضعیت واقعی پروژه **Beta / Functional but immature** است؛ برای Stable 1.0 یا Production Ready شواهد کافی وجود ندارد. هسته و پنل عملاً قابل استفاده‌اند، API و چرخه‌های اصلی وجود دارند و در آخرین sweep زنده ۲۲ مسیر اصلی پنل بدون HTTP 500 یا صفحه خطای داخلی باز شدند. بااین‌حال، تست مرورگر/دیتابیس در CI کامل نیست، rollback کامل فایل+دیتابیس اثبات نشده و تست کامل repository به‌علت چند شکست مستقل در تست‌های Pincore سبز نیست. CSP اکنون در target با enforce و بدون violation زنده تأیید شده و rollback report-only فقط یک مسیر تشخیصی صریح است.

## A. Repository Map

ساختار واقعی checkout:

```text
Project
├── platform/                 Pinoox platform bootstrap/config
├── apps/
│   ├── com_pinoox_cms/        NanoPino CMS package
│   │   ├── Cms/               domain, contracts, registries, adapters
│   │   ├── Controller/        web and runtime API controllers
│   │   ├── Model/             persistence models
│   │   ├── database/migrations/ 20 migration files
│   │   ├── routes/             admin asset and wildcard routes
│   │   ├── theme/cms-admin/   Vue/Luma/Vite admin + runtime + tests
│   │   ├── resources/          API/schema/release contracts
│   │   └── docs/               architecture, security, release and audit docs
│   ├── com_pinoox_installer/
│   ├── com_pinoox_manager/
│   ├── com_pinoox_welcome/
│   └── com_possino_purchase_ledger/
├── storage/                  runtime state, logs, cache and recovery data
├── vendor/                   Composer dependencies and Pincore
├── tools/                    SDK generator and release helpers
├── .github/workflows/        platform-release.yml
├── composer.json / lock      PHP dependency and test entry points
├── package manifests         frontend dependency/build inputs
└── docker-compose.yml        local services
```

Observed inventory: ۷۹۷ PHP files in the CMS package, ۷۳۵ class files under `Cms/`, ۲۰ migrations، ۳۸ frontend source files، ۳۵ frontend test files و ۳۶ documentation files.

## B. Technology Stack

| لایه | واقعیت کد |
|---|---|
| Runtime | PHP `^8.2`، اجرای محلی فعلی PHP 8.3.33 |
| Platform | Pinoox/Pincore `^3.8`، `pinroll ^1.1`؛ manifest فعلی minimum native code `232` |
| Backend | PHP، Pinoox routes/controllers، Illuminate database models/query builder |
| Frontend | Vue، Luma، PrimeVue، Pinia، Vue Router، Vite |
| Data | Pinoox platform connection، logical CMS table names، InnoDB |
| Search | Database driver پیش‌فرض؛ Meilisearch/Typesense فقط با configuration و transport امن |
| Cache/Queue/Storage | Pinoox cache، file queue، Pinoox storage adapters |
| Package | PINX app/theme، manifest validator، package path/resource guard، native lifecycle |
| Optional integrations | تنظیمات telemetry برای GA4/Matomo/GTM/Sentry مستند شده؛ فعال‌بودن live آن‌ها اثبات نشده |
| Not adopted in CMS admin | Next.js/React/Tailwind/Swiper؛ کد صریحاً از دو runtime موازی پرهیز کرده است |

## C. Runtime Architecture

جریان واقعی backend:

```text
HTTP request
  → Pinoox app bootstrap / route match
  → com_pinoox_cms/boot.php
  → CmsRuntimeBinder
  → CmsKernel singleton + registries + API/CSRF/rate-limit bindings
  → route permission and request-integrity flows
  → controller
  → runtime service / API facade
  → domain service
  → repository / Pinoox adapter
  → JSON response or safe error envelope
```

`CmsRuntimeBinder` هنگام boot migration اجرا نمی‌کند. `CmsKernel` registryهای capability، content، taxonomy، block، theme، settings، extension و admin را compose می‌کند. `CmsRuntimeServices` factory/service locator است و چند dependency را به‌شکل static cache نگه می‌دارد؛ این کار runtime را ساده کرده ولی برای persistent worker و unit isolation بدهی معماری ایجاد می‌کند.

Lifecycle افزونه در `TransactionalInstallCoordinator` به‌ترتیب Snapshot → Stage → Migrate → Lifecycle → Cache → Health → Switch → Verify → Commit تعریف شده و در failure مسیر rollback/quarantine دارد. این قرارداد source-confirmed است؛ rollback کامل دیتابیس در target hosting هنوز end-to-end اثبات نشده است.

## D. Database Architecture

مهاجرت‌های canonical در `apps/com_pinoox_cms/database/migrations` قرار دارند. ۱۶ جدول runtime در manifest اعلام شده‌اند؛ ۳ migration دیگر repair/index/lifecycle هستند. حوزه‌ها:

`settings`, `audit_events`, `contents`, `content_fields`, `content_relations`, `terms`, `content_terms`, `content_revisions`, `media_assets`, `media_usages`, `media_variants`, `theme_previews`, `builder_documents`, `builder_revisions`, `global_blocks`, `search_documents`.

نقاط مثبت:

- connection و prefix از طریق `CmsDatabase` و Pinoox resolve می‌شوند؛ repositoryها SQL پراکنده‌ی خارج از data layer ندارند.
- unique/indexهای مهم، projection لیست، batch hydration و limitهای bounded وجود دارد.
- migrationهای create با `hasTable()` idempotent طراحی شده‌اند و repair migration برای قرارداد logical table وجود دارد.
- عملیات حساس content/settings در service/repository از transaction و version/concurrency checks استفاده می‌کنند.

شکاف تأییدشده از static inspection:

- در migrationهای baseline `foreign()` / `references()` / `onDelete()` دیده نشد. `CmsSchemaIntegrityProbe` اکنون orphan relation را به‌صورت read-only گزارش می‌کند و migration جداگانه `2026_09_10_180000_add_cms_relational_constraints.php` constraint adoption را فقط پس از schema/orphan preflight انجام می‌دهد. این migration روی MySQL محلی اجرا و با status `Done` و ۹ constraint نام‌گذاری‌شده تأیید شد؛ اجرای آن روی target هنوز انجام نشده است.
- جست‌وجوی content از `LIKE %term%` استفاده می‌کند؛ برای ۱۰۰هزار رکورد باید full-text/index strategy یا driver جست‌وجوی واقعی اضافه شود.
- full DB snapshot/restore عمومی وجود ندارد؛ compensation migration با snapshot کامل دیتابیس یکی نیست.

## E. API Architecture

API versioned با base `/api/v1/cms` و manifest machine-readable در `CmsRuntimeApiManifest` است. بیش از ۵۰ route contract در حوزه‌های content، taxonomy، media، users، settings، themes، builder، extensions، search، infrastructure، performance، update، recovery، health، logs و security تعریف شده‌اند. در این pass، controllerهای health/log/support نیز به envelope مرکزی و `Cache-Control: no-store` متصل شدند و manifest پیش از ثبت route از نظر action، capability، فیلتر پارامتر، تکرار مسیر و flow امنیتی validate می‌شود.

قرارداد پاسخ مرکزی:

```json
{"success": true, "data": {}}
```

و خطا:

```json
{"success": false, "error": {"code": "...", "message": "...", "details": {}}}
```

Mutationها CSRF و rate-limit flow دارند؛ controllerهای اصلی validation/authorization و safe error reporter دارند. خطاهای ۴۰۱/۴۰۳ غیر-enveloped در مسیر mount‌شدهٔ API نیز در response boundary به envelope مرکزی تبدیل می‌شوند. وضعیت باقی‌مانده: تست authenticated API با چند role واقعی، idempotency در تمام mutationهای شبکه‌ای و rate-limit روی target host هنوز کامل نیست.

## F. Module / Plugin Architecture

افزونه‌ها با `app.php`/`manifest.json` کشف می‌شوند و registryها از `OwnedDefinitionInterface` و owner collision checks استفاده می‌کنند. manifest شامل type، version، publisher، dependencies، conflicts، provides، permissions، services، hooks، admin/api/frontend و blocks است. نصب و update از native PINX عبور می‌کند و `PackageFilePlan`، مسیر canonical، symlink، case collision، file/directory collision و resource budget را بررسی می‌کند.

نتیجه: **Modular architecture موجود و قابل گسترش است، اما extension isolation و signed trust-chain در target runtime هنوز کامل اثبات نشده است.**

## G. Theme Architecture

Theme discovery، metadata، compatibility، native activation gateway، parent/child inheritance، template hierarchy، design tokens، responsive breakpoints، patterns و Builder integration در source وجود دارند. Theme business logic را از طریق gateway/serviceها جدا می‌کند؛ activation persistence و full-site rendering زنده هنوز gate باز است.

## H. Security Findings

کنترل‌های موجود: strict same-origin CSRF، permission backend، ownership checks، output/error sanitization، safe package paths، symlink rejection، resource budgets، upload policy، SSRF guard، structured diagnostic redaction و normalizer پاسخ‌های منع دسترسی API روی mount.

موارد باز با severity:

| ID | شدت | وضعیت |
|---|---|---|
| SEC-01 | P1 | CSP اکنون در target با enforce و بدون violation زنده تأیید شده؛ rollback صریح report-only باقی است. |
| SEC-02 | P1 | cutover انجام شده و `platform_super=false` است؛ فقط رگرسیون role coverage و session verification باید در release gate حفظ شود. |
| SEC-03 | P1 | signed PINX trust-chain و publisher continuity در target lifecycle تست نشده است. |
| SEC-04 | P1 | Safe Mode برای failure پیش از boot کامل در runtime واقعی اثبات نشده است. |
| SEC-05 | P2 | transport جست‌وجوی remote بدون configuration عمداً unbound/fail-closed است؛ fallback دیتابیس به‌صورت pass گزارش می‌شود و فعال‌شدن remote نیازمند تنظیم امن و probe واقعی است. |
| SEC-06 | P2 | foreign-key database enforcement روی target هنوز انجام نشده؛ local enforcement و orphan scan صفر شده و health probe read-only باقی است. |

در بررسی زندهٔ v0.23.63 صفحه Security، وضعیت قابل مشاهده `PASS 15 / WARNING 0 / FAIL 0` است؛ CSP enforce و fallback امن جست‌وجو pass هستند. cutover explicit-role با ۱ platform account، ۱ explicit super account و ۰ implicit-only account تکمیل‌شده گزارش می‌شود.
مسیر PermissionFlow نیز harden شد: تشخیص API اکنون mountهایی مانند `/nano/api/...` را می‌شناسد و پاسخ منع دسترسی را با envelope JSON CMS برمی‌گرداند.

## I. Performance Findings

- query probe و N+1 detector وجود دارند؛ در یک اجرای زنده query count برابر ۵، N+1 برابر ۰ و slow query برابر ۰ مشاهده شد.
- pagination و projection در content/media وجود دارد، اما content search و offset pagination برای داده بسیار بزرگ محدودیت دارند.
- vendor Luma حدود ۱.۴۷MB قبل از gzip گزارش شده؛ قبل از split کردن باید field data برای INP/LCP جمع شود.
- memory budget در اجرای زنده نوسان داشت: یک snapshot pass و یک snapshot fail با peak حدود 100663296 bytes در برابر limit 67108864. این مورد باید با چند اجرای ثابت و profiling target تفکیک شود؛ هنوز fix تأییدشده ندارد.

## J. Maturity Scorecard

| Area | Score /5 | شاهد |
|---|---:|---|
| Core Architecture | 3 | Binder/Kernel/registries وجود دارند؛ static service locator باقی است. |
| Modularity | 4 | owner-aware registries و contracts. |
| Plugin Architecture | 3 | manifest/lifecycle/validation موجود؛ signed/runtime isolation باز. |
| Theme Architecture | 3 | discovery/design/template/activation source موجود؛ native persistence باز. |
| Database | 3 | schema/transactions/index/repair؛ local FK migration و scan تأیید شده، target integration باز. |
| API | 3 | v1 manifest و envelope؛ authenticated integration ناقص. |
| Security | 3 | CSRF/RBAC/package/SSRF guard و explicit-role cutover؛ CSP/transport gate باز. |
| Authentication | 3 | Pinoox identity/session integration موجود؛ multi-role E2E باز. |
| Authorization | 3 | backend permissions/ownership؛ target role matrix باز. |
| Upgrade System | 3 | native versioned lifecycle/recovery contracts؛ target rollback باز. |
| Installer | 3 | preflight, package plan, native install؛ shared-host exact evidence باز. |
| Error Handling | 3 | typed error mapping and safe correlation. |
| Logging | 3 | structured file logger, audit and support bundle. |
| Performance | 2 | budgets/probes موجود؛ memory/large-data profiling باز. |
| Scalability | 2 | bounded queries؛ full-text/cursor-at-scale باز. |
| Testing | 2 | ۱۴۶ Node tests + ۱۶ PHP runtime contracts؛ DB/browser matrix ناقص. |
| Admin UX | 3 | ۱۹ صفحه live smoke و Builder واقعی؛ direct content entrypoint در این pass اصلاح شد، full interaction matrix باز. |
| Mobile | 2 | source mobile/RTL tests؛ viewport E2E کامل باز. |
| RTL | 3 | fa catalog، RTL shell و route validation. |
| Developer Experience | 3 | SDK catalog/generator و docs؛ examples/integration tests محدود. |
| Documentation | 3 | docs/ADR/release docs موجود؛ چند ادعا پیش از این با checkout sync نبود. |
| Production Readiness | 2 | usable beta؛ stable gateها باز. |

## K. Technical Debt Register

| ID | Priority | Root cause | Impact | اقدام |
|---|---|---|---|---|
| TD-TEST-01 | P1 | CI قبلی suite اجرایی PHP مورد ادعا را در checkout نداشت. | false confidence در release gate. | در این pass `composer test:cms` با ۱۶ تست واقعی و workflow gate اضافه شد. |
| TD-API-01 | P1 | PermissionFlow بومی روی mount مسیر API گاهی پاسخ غیر-enveloped می‌داد و manifest قبل از ثبت validate نمی‌شد. | پاسخ‌های API ناسازگار و خطای مبهم هنگام misconfiguration route. | `CmsPermissionFlow` با تشخیص mount-aware و envelope مرکزی در CMS ثبت شد؛ `PinooxSecurityResponseListener` همچنان fallback پاسخ ۴۰۱/۴۰۳ را normalize می‌کند و `CmsRuntimeApiManifest::validate()` قبل از register اجرا می‌شود. سرور محلی این مسیر را با HTTP 403 و JSON تأیید کرد؛ target E2E هنوز باز است. |
| TD-TEST-02 | P1 | `composer test:apps` هنوز ۲ تست Pincore را fail می‌کند. | repository gate قرمز؛ تشخیص CMS با platform مخلوط است. | project-level `autoload-dev` و Pest bootstrap اضافه شد و ۱۰ failure مربوط به bootstrap رفع شد؛ دو failure باقی‌مانده در `PlatformFileSelector` و `WebServerFixCache` upstream هستند و vendor دستکاری نمی‌شود. |
| TD-SEC-01 | P1 | CSP در baseline report-only بود. | cutover enforcement production قابل اثبات نبود. | با secure default، binding در API boundary و live browser sweep بسته شد؛ rollback report-only صریح و موقت باقی است. |
| TD-SEC-02 | P1 | readiness قبلی فقط حالت pre-cutover را می‌شناخت. | پس از cutover موفق، Security Center وضعیت هشدار گمراه‌کننده نشان می‌داد. | `cutover_complete` state و نمایش صریح terminal state اضافه شد؛ role coverage حفظ شود. |
| TD-DB-01 | P1 | FK/cascade در baseline نبود. | خطر orphan data پس از delete/restore. | integrity probe و migration non-destructive اضافه شد؛ روی local MySQL با orphan scan صفر و ۹ FK اجرا شد؛ اجرای target و backup/rollback evidence باقی است. |
| TD-DEPLOY-01 | P1 | target shared-hosting exact environment در CI نیست. | native/live success قابل تعمیم نیست. | lifecycle روی همان Pincore/DB/hosting با evidence مستقل. |
| TD-DX-01 | P1 | rule عمومی `/apps/*` کل `com_pinoox_cms` را از Git خارج کرده بود. | سورس CMS در GitHub/version history دیده نمی‌شد و release reproducibility آسیب می‌دید. | در این pass فقط whitelist محدود برای خود CMS به `.gitignore` اضافه شد؛ stage/commit باید جداگانه و با review انجام شود. |
| TD-ARCH-01 | P2 | static caches در `CmsRuntimeServices`. | test isolation و persistent worker reset دشوار. | facade را حفظ و composition boundary را مرحله‌ای injectable کن. |
| TD-PERF-01 | P2 | `%term%` search و offset pagination. | افت latency با رشد داده. | cursor/full-text strategy و benchmark قبل از تغییر. |
| TD-UI-01 | P2 | Vue و directly-served runtime behavior موازی دارند. | احتمال drift در bug fix و a11y. | قرارداد state machine مشترک و parity gate؛ این pass تغییر entrypoint در هر دو مسیر همسان شد. |
| TD-UI-03 | P1 | منوی `cms.content` با داشتن children فقط به شکل گروه expandable رندر می‌شد و route خودش قابل کلیک نبود؛ Builder نیز «ساخت محتوا» را از «ساخت طراحی» تفکیک نمی‌کرد. | کاربر نمی‌تواند از پنل مسیر ساخت صفحه را پیدا کند و ممکن است تصور کند Builder/صفحه‌سازی خراب است. | child menu مستقیم `cms.content.index`، CTA ساخت محتوای واقعی در Builder و copy دقیق؛ live sweep مسیرهای مرتبط pass شد. |
| TD-UI-02 | P2 | bundle بزرگ Luma و mobile rendering بدون field data. | initial load/INP نامشخص. | profiling و split/lazy loading مبتنی بر اندازه‌گیری. |

## L. Missing / Not Found in current codebase

- **Not found in current codebase:** authenticated PHP API/DB integration suite اختصاصی CMS با user/role واقعی.
- **Not found in current codebase:** browser E2E رسمی Playwright/Cypress با viewportهای 320 تا desktop و accessibility assertions.
- **Not found in current codebase:** full database snapshot/restore rollback برای migrationهای destructive.
- **Not found in current codebase:** signed PINX lifecycle test در checkout فعلی.
- **Not found in current codebase:** evidence قطعی برای exact target shared-hosting install/upgrade/fault recovery.
- وجود queue, health, cache, search remote interfaces در source دیده می‌شود؛ اجرای scheduler/worker/remote service روی production هنوز تأییدشده نیست.

## M. Priority Matrix

| Priority | موارد |
|---|---|
| P0 | در ۱۹ مسیر live بررسی‌شده، crash/500 قابل تکرار مشاهده نشد؛ بااین‌حال signed/fault recovery و data-loss path باید به‌عنوان release gate باقی بماند. |
| P1 | DB integrity/FK inventory، authenticated API+DB E2E، signed package و target lifecycle؛ CSP enforcement و explicit-role cutover live-verified هستند. |
| P2 | memory profiling، full-text/cursor search، service composition، mobile/browser matrix، bundle split، state parity. |
| P3 | polish، ترجمه literalهای باقی‌مانده، documentation link audit و visual refinement. |

## N. Development Phases

| Phase | Status | خروجی بعدی |
|---|---|---|
| 0 Recon + Safety | Completed for this pass | map، scorecard، debt register، PHP contract gate |
| 1 Runtime Stability | In progress | suite مستقل سبز؛ دو شکست upstream در runner اصلی باقی است |
| 2 Database Integrity | In progress | probe و migration امن اضافه شد؛ local scan/migration تأیید شد، target DB scan/backup و rollback evidence باقی است |
| 3 API/Auth/RBAC Integration | In progress | manifest/action gate، mounted denial normalization، mount-aware PermissionFlow، user-scope/RBAC regression؛ authenticated user/role واقعی و idempotency matrix باقی است |
| 4 Extension/Upgrade Trust | In progress | signed PINX، publisher continuity، injected rollback |
| 5 Security Cutover | Completed for current controls | explicit-role cutover و CSP enforce live-verified؛ SSRF remote transport عمداً بدون configuration غیرفعال و fallback دیتابیس fail-closed است |
| 6 Performance/Scale | Not Started | benchmark content/search/memory و cursor/full-text design |
| 7 Browser QA | In progress | همه routeها، mutationها، 320/390/768/1280، keyboard/WCAG؛ Builder live open/preview pass و navigation gap reproduced شد، source fix local pass است |
| 8 DX/Docs | In progress | SDK runnable examples، docs parity و release checklist |
| 9 Stable Gate | Blocked | فقط پس از بسته‌شدن تمام hard gateهای بالا |

## تغییر این فاز

### Added

- `composer test:cms` / `tests/php/run.php`: ۱۶ runtime contract test واقعی برای manifest، unsafe path، package planning، API envelope/facade normalization، عدم bypass پاسخ خام در controllerها، integrity response/policy، mounted denial normalization، mount-aware permission detection، manifest/action gate، capability registration، single-site/user-scope/policy authorization، relation inventory، جلوگیری از نشت exception پیش از Facade و boundary وضعیت امنیت.
- `apps/com_pinoox_cms/Cms/Runtime/CmsSchemaIntegrityProbe.php`: probe read-only برای relation inventory، orphan existence و foreign-key introspection.
- `apps/com_pinoox_cms/database/migrations/2026_09_10_180000_add_cms_relational_constraints.php`: migration داده‌محور و fail-closed برای FKهای داخلی، با cleanup معکوس در خطای میانی و rollback محدود.
- `tests/Pest.php`: اتصال Pest project-level به قراردادهای تست Pincore.

- `apps/com_pinoox_cms/Cms/Runtime/CmsRuntimeApiManifest.php`: validate اجرایی manifest قبل از ثبت route؛ action/controller، capability، method/path، duplicate route، placeholder filters و mutation flow را fail-fast بررسی می‌کند.
- `apps/com_pinoox_cms/Cms/Security/Http/PinooxSecurityResponseListener.php`: تبدیل پاسخ‌های native 401/403 غیر-enveloped در APIهای mounted به envelope امن CMS.
- `apps/com_pinoox_cms/Cms/Security/Http/CmsPermissionFlow.php`: جایگزین محدود PermissionFlow در scope CMS برای حفظ تصمیم native RBAC و اصلاح تشخیص JSON در مسیرهای mount‌شده.
- `apps/com_pinoox_cms/Cms/Runtime/CmsApiControllerResponder.php`: مرز مشترک controllerهای Facade برای گرفتن خطاهای ساخت سرویس/adapter و تبدیل آن‌ها به پاسخ امن ۵۰۰ با Error ID.
- `apps/com_pinoox_cms/Cms/Authorization/SingleSiteScopeGuard.php`: بستن user scope هم‌زمان به actor و subject.
- `apps/com_pinoox_cms/docs/adr/ADR-002-api-runtime-boundary.md` و `docs/adr/index.md`: ثبت تصمیم public API boundary و محدودیت‌های target E2E.

### Modified

- `.github/workflows/platform-release.yml`: اجرای PHP runtime suite قبل از platform archive.
- `apps/com_pinoox_cms/docs/testing/strategy.md`: اصلاح ادعای coverage و تعیین مرز تست فعلی.
- `apps/com_pinoox_cms/Controller/Api/{System,Search,Performance,Recovery,Infrastructure,Update,Builder}RuntimeApiController.php`: نرمال‌سازی پاسخ‌های runtime با envelope مرکزی و `Cache-Control: no-store`؛ controllerهای API دیگر مستقیماً `JsonResponse` خام نمی‌سازند و مرز مشترک Facade خطاهای پیش از اجرای سرویس را نیز به Error ID امن تبدیل می‌کند.
- `apps/com_pinoox_cms/Cms/Security/Http/CmsRequestIntegrityFlow.php`: پاسخ رد درخواست CSRF/origin نیز از envelope مرکزی استفاده می‌کند تا پاسخ‌های خطا و سیاست cache همسان بمانند؛ endpoint قراردادی `__cms/health/frontend` به‌دلیل سازگاری کلاینت، با هدرهای ضدکش اختصاصی خود باقی مانده است.
- `apps/com_pinoox_cms/Cms/Health/SystemHealthRegistrar.php`: ثبت probe جدید `system.cms_integrity` در System Health.
- `apps/com_pinoox_cms/docs/api/index.md`: ثبت قرارداد واحد پاسخ برای همه controllerهای runtime.
- `apps/com_pinoox_cms/docs/UPDATE-WORKFLOW.md` و `docs/deployment/installability.md`: علامت‌گذاری صریح اسکریپت‌های release که در checkout فعلی موجود نیستند.
- `.gitignore`: خارج‌کردن محدود `apps/com_pinoox_cms` از ignore عمومی تا سورس قابل versioning باشد.
- `.github/workflows/nanopino-quality.yml`: quality gate مستقل برای push/PR، lint، PHP contracts، frontend tests و build.
- `composer.json`: ثبت `autoload-dev` برای کلاس‌های تست Pincore تا suite dependency در runner root قابل load باشد.
- `start-local.sh`: forwarding آرگومان‌های CLI به `pinoox serve` تا اجرای app-specific مانند `--app=com_pinoox_cms` واقعاً اعمال شود.
- `apps/com_pinoox_cms/Cms/Admin/CoreContentAdminDefinitions.php`: افزودن child menu مستقیم برای دسترسی قابل‌کلیک به Content center در کنار Revision/Media.
- `apps/com_pinoox_cms/lang/{fa,en}/admin.lang.php`: اصلاح copy مسیر ساخت صفحه و تفکیک محتوای واقعی از طراحی Builder.
- `apps/com_pinoox_cms/theme/cms-admin/src/pages/builder/page-builder.vue` و `runtime/builder.mjs`: افزودن CTA مدیریت/ساخت محتوای واقعی و نام‌گذاری دقیق‌تر Override طراحی در هر دو runtime.
- `apps/com_pinoox_cms/theme/cms-admin/tests/content-builder-entrypoint.test.js`: regression test برای ناوبری مستقیم Content و مسیر ساخت Builder.
- این گزارش audit.

### Removed / Migrated

- هیچ فایل production یا داده‌ای حذف نشد؛ فقط migration محلیِ افزایشی برای ۹ constraint اجرا شد و rollback روی schema موقت نیز آزموده شد.

## Validation این فاز

- PHP lint روی همه PHPهای CMS: **PASS / exit 0**.
- `composer test:cms`: **PASS — 16 passed, 0 failed**؛ شامل contract رابطه‌های داخلی، envelope یکپارچه، integrity policy/response، mounted API denial normalization، mount-aware permission detection، manifest executable contract، authorization، جلوگیری از نشت exception پیش از Facade و boundary وضعیت امنیت.
- migration relational constraints پس از hardening: **PASS**؛ lint، اجرای local، status `Done`، شمارش ۹ FK و مسیر cleanup خطای میانی در کد بررسی شدند.
- `composer test:apps`: **FAIL — 1616 passed, 2 failed, 4 skipped**؛ پس از اصلاح root test bootstrap، شکست‌های `KernelSystemTest` و helper `withViteHmrEnv` رفع شدند. دو شکست باقی‌مانده در تست‌های vendor/Pincore شامل `PlatformBuildTest` و `AppRouterSystemTest` هستند؛ این وضعیت هنوز release green محسوب نمی‌شود و vendor عمداً دستکاری نشده است.
- Frontend contract/regression suite این pass: **146/146 PASS**؛ شامل regression برای direct content entrypoint و تفکیک ساخت محتوا از طراحی Builder.
- API response normalization برای تمام runtime API controllerها، مسیر integrity و native mounted denials با lint و suite runtime بررسی شد؛ PermissionFlow mount-aware با سرور محلی (`/nano/api/v1/cms/system/health` → HTTP 403 JSON) نیز **PASS** شد. تست authenticated browser و target lifecycle هنوز **Not executed** است.
- Facade controller boundary برای مسیرهای runtime با PHP lint و suite محلی بررسی شد؛ failure injection شبکه‌ای برای هر endpoint و اجرای authenticated آن‌ها هنوز **Not executed** است.
- local unauthenticated API smoke برای هر ۷۴ route با placeholder معتبر اجرا شد؛ هر ۷۴ مسیر HTTP `401/403` با `application/json` برگرداندند و پاسخ HTML/۵۰۰ خام مشاهده نشد. این تست فقط request بدون session است و جایگزین ماتریس مجاز/غیرمجاز authenticated نیست.
- `composer check-platform`: **PASS**؛ PHP `8.3.33` با constraint پروژه سازگار است.
- مستندات release با filesystem checkout تطبیق داده شد؛ سه helper build/version و verifier جست‌وجوشده **Not found in current codebase** هستند و ادعای اجرایی برای آن‌ها ثبت نشده است.
- با فرمان native `php pinoox pinx:build` artifact مستقل v14 برای `0.23.42 #2342` ساخته شد؛ `pinx:info` و archive listing برای artifact نهایی بررسی شدند و SHA-256 خارج از archive ثبت شد تا self-reference ایجاد نشود. مسیر artifact نهایی: `/home/saeed/Downloads/crm/POSSINO/هسته/codex/NanoPino-0.23.42-content-builder-entrypoint-v14.pinx` و checksum آن در فایل `.sha256` کنار artifact ثبت شده است. این build بدون امضاست و نصب/upgrade/rollback روی target هنوز **Not executed** است.
- archive manifest parity، نبود `node_modules`/`.env`/`.git`، وجود تغییرات این فاز در archive و غیرخالی‌بودن artifact v14: **PASS**؛ build با `920/920` فایل، `pinx:info` با `921` entry و package identity/version مورد انتظار را نشان داد. SHA-256 هر دو کپی artifact برابر `cfd247b67f1be81a6b60e366c0643c3061493906e715c84ddd0bb5e99fef9b0e` است و از فایل checksum قابل بررسی است.
- اجرای مستقیم integrity probe در محیط محلی: **Not completed**؛ اتصال تنظیم‌شده MySQL است اما PHP CLI این checkout `pdo_mysql` ندارد (`could not find driver`). این محدودیت برای target DB باید جداگانه برطرف و دوباره اجرا شود.
- در MySQL محلی موجود، هر ۱۶ جدول CMS حاضر و InnoDB/`utf8mb4_bin` هستند؛ orphan scan برای هر ۹ رابطه صفر بود و FK موجود پیش از migration صفر گزارش شد. migration روی دیتابیس محلی اجرا شد، status آن `Done` است و information_schema وجود هر ۹ constraint نام‌گذاری‌شده را تأیید می‌کند. `php pinoox doctor --json --skip-frontend --no-fixes com_pinoox_cms` نیز `healthy: true`، امتیاز ۱۰۰، `26 pass / 0 warn / 0 fail` و `20 file(s), 0 pending` گزارش کرد. DDL migration همچنین روی schema موقت MySQL با ۹ constraint اعمال و شمارش شد؛ schema موقت پس از تست حذف شد. این evidence فقط local container است، نه target hosting.
- local runtime با `php8.4 pinoox serve --app=com_pinoox_cms` روی mount `/nano` بالا آمد؛ درخواست بدون session به `/nano/api/v1/cms/system/health`، HTTP `403` با `Content-Type: application/json` و envelope `ACCESS_DENIED` داد و root به `302` رسید؛ `500` مشاهده نشد. این نتیجه با `CmsPermissionFlow` mount-aware به‌دست آمد. dispatch مستقل response-listener در این dev server همچنان gate جداگانه است و authenticated local به‌علت نبودن login session/credential در این checkout **Not executed** است.
- Production Vite build همین pass: **PASS**؛ source/dist parity، ۵۷ asset و نبود root-domain `/assets/` در JS/CSS تأیید شد.
- Live admin smoke قبلی: **۱۹ مسیر اصلی، بدون HTTP 500 یا internal error**؛ Builder سند واقعی چهاربلکی را باز/preview کرد و horizontal overflow نداشت.

## Validation تکمیلی Builder و مسیر ساخت صفحه

- مرورگر داخلی، target `https://test.boxpdf.ir/qwe/builder?...` پس از reload: **PASS** برای بارگذاری بدون error، بازکردن سند موجود، نمایش سند چهاربلکی و Preview inline با `iframe about:srcdoc`؛ در این اجرای مشخص HTTP 500 یا پیام integrity قابل تکرار مشاهده نشد.
- همان مرورگر: **FAIL / live UX finding**؛ در `manager/control/apps` منوی «محتوا» فقط به‌صورت expandable group بود و route مستقیم Content center را نمایش نمی‌داد. این رفتار با `CoreContentAdminDefinitions` و `CoreMediaRevisionAdminDefinitions` در source هم‌خوان بود و root cause مشخص شد.
- اصلاح source: child menu مستقیم `cms.content.index` با route موجود `cms.content` اضافه شد؛ Builder برای target نوع content دکمه «ساخت صفحه محتوا» دارد که به `/content` می‌رود، و «ایجاد طراحی صفحه» را جدا نگه می‌دارد. مستندات و runtime fallback نیز همسان شدند.
- Validation source بعد از اصلاح: **PASS**؛ ۱۴۶ تست Node، build Vite، lint PHP، `composer test:cms` با ۱۶ تست و `pinoox doctor` با `healthy: true` و `26 pass / 0 warn / 0 fail`.
- Live deployment نسخه‌ی v14 و تست مجدد همین اصلاح روی target: **Not executed**؛ بنابراین رفع live منوی Content هنوز اعلام نمی‌شود. نسخه‌ی آنلاین مشاهده‌شده قبل از deployment همچنان ممکن است همان رفتار قبلی را نشان دهد.

این گزارش stable/production readiness را اعلام نمی‌کند؛ نتیجه فعلی یک Beta قابل توسعه با release gateهای صریح است.

## Validation تکمیلی — URL عمومی و مشاهده به‌عنوان کاربر

- Root cause مسیر نمایش عمومی تأیید شد: قبل از این تغییر `routes/web.php` فقط routeهای مدیریتی و wildcard ادمین داشت و برای محتوای منتشرشده route عمومی وجود نداشت؛ بنابراین Preview داخل Builder معادل صفحه‌ی قابل مشاهده در وب نبود.
- routeهای عمومی اضافه شدند: `GET /page/{slug}` و `GET /post/{slug}`؛ در mount فعلی نمونه، صفحه در آدرس `https://test.boxpdf.ir/qwe/page/{slug}` قرار می‌گیرد. لینک واقعی با mount فعال Pinoox ساخته می‌شود و hard-code دامنه ندارد.
- فقط رکوردهای `published` با `site_id=1` و `locale=fa` از repository خوانده می‌شوند؛ draft، scheduled و trash از route عمومی 404 می‌گیرند. خروجی با `StrictRichTextSanitizer` ساخته می‌شود و canonical، RTL، meta description، عنوان، excerpt، زمان انتشار و متن کامل را دارد.
- API و پنل Content اکنون برای هر `page`/`post` منتشرشده فیلد `public_url` و اقدام «مشاهده صفحه عمومی» را نشان می‌دهند؛ تا پیش از انتشار لینک عمومی نمایش داده نمی‌شود.
- Validation source بعد از این تغییر: **PASS**؛ PHP lint روی کل CMS، ۱۴۹ تست Node، `npm run build` با source/dist parity و ۵۷ asset، و `composer test:cms` با ۱۶ تست پاس شدند. تست‌های route، وضعیت published-only، sanitizer و لینک عمومی به regression suite اضافه شدند.
- بسته‌ی قابل نصب v15 ساخته و در هر دو مسیر نگه‌داری شد: `/home/saeed/Downloads/crm/POSSINO/هسته/codex/NanoPino-0.23.42-public-page-route-v15.pinx` و `/home/saeed/Projects/pinoox-local/NanoPino-0.23.42-public-page-route-v15.pinx`. `pinx:info`: `com_pinoox_cms`, `0.23.42 #2342`, `923` payload files؛ SHA-256 هر دو کپی: `df829f396a63a570332a8da5ac4cd7c907d2adda899b5a3f10ac10011895bb97`.
- استقرار v15 روی `test.boxpdf.ir` و ساخت/انتشار صفحه‌ی نمونه در آن target در این نوبت **Not executed** است؛ بنابراین هنوز نمی‌توانم ادعا کنم URL عمومی روی سرور زنده قابل مشاهده است. مشاهده‌ی قبلی مرورگر فقط Preview داخلی نسخه‌ی قبل از route عمومی را تأیید کرده بود.

## Validation نهایی این نوبت — نصب زنده، ساخت صفحه و مشاهده‌ی عمومی

- علت ردشدن v15 در Inspect زنده با شواهد کد و تست تفکیکی مشخص شد: manifest ریشه‌ی تولیدشده توسط native `PinxBuilder` پروفایل `cms` نداشت؛ بسته‌ی کامل همچنین یک chunk به نام `vendor-luma` با حجم حدود ۱.۴۶MB داشت که preflight نسخه‌ی target آن را رد می‌کرد. بسته‌ی حداقلی بدون این chunk وارد Review شد و root cause دوم را تأیید کرد.
- `tools/release/ensure-pinx-cms-profile.php` اضافه شد. این bridge فقط روی artifact بدون امضا اجرا می‌شود، هویت package را کنترل می‌کند و در صورت وجود `signature.json` عمداً متوقف می‌شود تا امضا invalid نشود.
- Vite با manual chunking بازتنظیم شد؛ بزرگ‌ترین chunkهای JS نهایی حدود `676KB`، `525KB` و `361KB` هستند و chunk قبلی `vendor-luma` با حجم `1.46MB` دیگر تولید نمی‌شود.
- تست source نهایی: `npm test` برابر **149/149 PASS**، `npm run build` برابر **PASS** با source/dist parity و ۶۰ asset، PHP lint کل CMS برابر **PASS** و `composer test:cms` برابر **16 passed / 0 failed**.
- artifact نهایی v16 ساخته و در هر دو مسیر نگه‌داری شد: `/home/saeed/Downloads/crm/POSSINO/هسته/codex/NanoPino-0.23.43-public-page-route-v16.pinx` و `/home/saeed/Projects/pinoox-local/NanoPino-0.23.43-public-page-route-v16.pinx`. `pinx:info` آن `com_pinoox_cms`, نسخه `0.23.43 #2343`, تعداد `927` entry، بدون امضا را تأیید کرد؛ checksum هر دو کپی `82ec210a902854b08a34b6fe6176d354ad3d70ebb8f0fd8cfa0875072cf64063` است.
- Inspect زنده v16: **PASS**؛ Review با حالت `Update` باز شد. پس از تأیید صریح در پنل، Update زنده: **PASS**؛ تب Extensions نسخه‌ی فعال `0.23.43` و وضعیت `فعال` را نشان داد.
- مسیر ساخت واقعی: از `https://test.boxpdf.ir/qwe/content` برگه ساخته شد، عنوان «راهنمای کامل نانوپینو»، خلاصه، متن کامل و slug صریح `nanopino-public-proof-20260910` ذخیره شد و «ذخیره و انتشار» موفق بود. فهرست Content وضعیت `منتشرشده` و لینک `/qwe/page/nanopino-public-proof-20260910` را نشان داد.
- مشاهده مثل کاربر وب: تب مستقل `https://test.boxpdf.ir/qwe/page/nanopino-public-proof-20260910` با title «راهنمای کامل نانوپینو | NanoPino»، heading، خلاصه، زمان انتشار و متن کامل باز شد؛ درخواست بدون نشست با `curl` نیز **HTTP 200**، `text/html; charset=UTF-8`، `X-Content-Type-Options: nosniff` و `X-Frame-Options: DENY` برگرداند و محتوای title/slug/body در HTML وجود داشت.
- وضعیت باقی‌مانده: route عمومی فعلی renderer امن و canonical است؛ نمایش layoutهای visual Builder در public renderer هنوز به‌صورت مستقل پیاده نشده و برای آن باید فاز جداگانه‌ی template-to-public rendering طراحی و تست شود. این مورد مانع مسیر پایه‌ی ساخت/انتشار/مشاهده نیست.

## Validation این نوبت — جداسازی داشبورد از سایت عمومی

- مشکل UX تأیید شد: `/qwe/` عمداً محیط کنترل‌پلین است و ورود مستقیم به آن نباید به‌عنوان «سایت بازدیدکننده» تعبیر شود.
- مسیر عمومی پایدار اضافه شد: `GET /site`؛ با mount فعلی آدرس دقیق سایت `https://test.boxpdf.ir/qwe/site` است. این مسیر به wildcard ادمین وابسته نیست و بدون نشست نیز HTML عمومی می‌دهد.
- صفحه‌ی عمومی سایت محتوای منتشرشده را با Query محدود و projection فهرست می‌خواند، برای هر محتوای قابل‌نمایش لینک canonical می‌سازد و وضعیت خالی واقعی دارد. خطای خواندن دیتابیس نیز به پاسخ کاربرپسند `503 Service unavailable` محدود می‌شود.
- داشبورد اکنون لینک قابل‌دسترس «نمایش سایت» را با مقصد واقعی و mount-aware (`/qwe/site`) در header نشان می‌دهد؛ لینک در تب جدید باز می‌شود و به `/qwe/` یا مسیر مدیریتی route نمی‌شود.
- تست مرورگر داخلی: داشبورد نسخه‌ی فعال `0.23.44` لینک `نمایش سایت — /qwe/site` را نشان داد؛ کلیک روی آن تب مستقل `https://test.boxpdf.ir/qwe/site` را باز کرد. DOM تب عمومی شامل عنوان «محتوای شما، در یک سایت واقعی»، «آخرین محتوا»، محتوای منتشرشده و لینک‌های `/qwe/page/...` بود.
- تست HTTP مستقل: `GET https://test.boxpdf.ir/qwe/site` برابر **HTTP 200** با `text/html; charset=UTF-8`، هدرهای `X-Content-Type-Options: nosniff`، `X-Frame-Options: DENY` و CSP report-only برگشت؛ `/qwe/` همچنان داشبورد کنترل‌پلین باقی ماند.
- نسخه‌ی تحویلی این تغییر `0.23.44 #2344` است. artifact v17 در این دو مسیر موجود است: `/home/saeed/Downloads/crm/POSSINO/هسته/codex/NanoPino-0.23.44-dashboard-public-site-v17.pinx` و `/home/saeed/Projects/pinoox-local/NanoPino-0.23.44-dashboard-public-site-v17.pinx`. SHA-256 هر دو کپی: `cecc3513c99e86483431ad5416119ec66b0bdedcb44d7371a4527bc298eb9536`.
- تست‌های این نوبت: `npm test` برابر **151/151 PASS**، `composer test:cms` برابر **16 passed / 0 failed**، PHP lint فایل‌های جدید **PASS**، Vite build و source/dist parity **PASS**، Inspect و Update زنده **PASS**.

## Validation تکمیلی — اتصال Builder منتشرشده به صفحه عمومی

- root cause شکاف باقی‌مانده مشخص شد: صفحه عمومی فقط `document.content` محتوای پایه را می‌خواند، در حالی‌که سند Builder در جداول مستقل ذخیره می‌شود و هدف محتوایی آن با کلید `content:{type}:{id}` ثبت می‌شود.
- `PublicPageRenderer` اکنون آخرین revision با وضعیت `Published` را برای همان `site/type/id/locale` resolve می‌کند، سند را با `BlockDocumentLoader` اعتبارسنجی و migration می‌کند، Global Blockها را expand می‌کند و از همان `BlockDocumentRenderer` رسمی Builder خروجی می‌گیرد.
- برای سازگاری و data safety، اگر سند Builder موجود نباشد، خالی باشد یا در validation/render خطا داشته باشد، صفحه به محتوای rich-text امن ذخیره‌شده برمی‌گردد و exception داخلی به بازدیدکننده نشت نمی‌کند.
- استایل‌های پایه‌ی بلوک‌های عمومی شامل section، button، hover و focus-visible اضافه شد تا خروجی Builder در موبایل و صفحه عمومی قابل استفاده باشد.
- regression test جدید اضافه شد: رندر heading داخل section از سند بلوکی، عدم بازگشت به fallback هنگام سند معتبر و حفظ قرارداد رندر رسمی. `composer test:cms`: **17 passed / 0 failed**.
- PHP lint فایل‌های تغییرکرده: **PASS**. نسخه source و manifest به `0.23.46 #2346` ارتقا یافت.
- بسته native بدون امضا با `pinx:build` ساخته شد، پروفایل CMS با bridge رسمی enrich شد و `pinx:info` هویت `com_pinoox_cms`، نسخه `0.23.46 #2346` و `929` entry را تأیید کرد.
- artifact v19 در این دو مسیر کپی و با SHA-256 یکسان نگه‌داری شد: `/home/saeed/Downloads/crm/POSSINO/هسته/codex/NanoPino-0.23.46-builder-public-render-v19.pinx` و `/home/saeed/Projects/pinoox-local/NanoPino-0.23.46-builder-public-render-v19.pinx`; checksum: `909abfc74d31fa3a77311a5ab256c67aa750343ea997eb1ec757c96c8fae1a45`.
- نصب و تست live نسخه v19 روی target، ساخت یک صفحه جدید در Builder و مشاهده همان صفحه با طراحی بلوکی در مرورگر هنوز **Not executed** است؛ بنابراین live Builder-to-public rendering را تأییدشده اعلام نمی‌کنم.

## Validation این نوبت — تشخیص و رگرسیون انتشار Builder

- در target، انتشار سند Builder نسخه v19 به‌صورت تکرارشونده با پیام `Request integrity validation failed.` رد شد، در حالی که ذخیره‌ی همان سند موفق بود. این مشاهده به‌عنوان bug واقعی ثبت شد و صرفاً با تغییر UI پوشانده نشد.
- در `CmsRequestIntegrityFlow` تشخیص امن اضافه شد: reason تصمیم، method/path، subject، وضعیت same-origin، وجود توکن، Fetch Metadata و content type در channel امنیتی ثبت می‌شوند؛ مقدار CSRF، Origin، Referer، Authorization و محتوای سند هرگز log نمی‌شود. پاسخ کاربر همچنان عمومی و غیرحساس باقی می‌ماند.
- نسخه‌ی v20 با source version `0.23.47 #2347` ساخته شد، `pinx:info` تعداد `929` entry و هویت `com_pinoox_cms` را تأیید کرد. artifact در این دو مسیر کپی شد و checksum هر دو برابر `0e0cbfd0b8a42e6d61710231efac6093a96e68c54e77943c9f51a6ef9c749528` است: `/home/saeed/Downloads/crm/POSSINO/هسته/codex/NanoPino-0.23.47-integrity-diagnostics-v20.pinx` و `/home/saeed/Projects/pinoox-local/NanoPino-0.23.47-integrity-diagnostics-v20.pinx`.
- نصب/Update زنده v20 در manager با پیام `App "com_pinoox_cms" updated successfully` و سپس `نصب با موفقیت انجام شد` مشاهده شد؛ بسته unsigned است و signing انجام نشده است.
- پس از reload Builder و بازکردن همان سند واقعی `zx`، دکمه انتشار موفق شد: toast `منتشر شد.`, وضعیت `published`، نسخه `3` و revision منتشرشده `#16` در تاریخچه دیده شد. این مسیر دیگر پیام integrity نشان نداد.
- مشاهده به‌عنوان کاربر در تب مستقل `https://test.boxpdf.ir/qwe/page/zx` **PASS** شد؛ DOM عمومی شامل heading «صفحه واقعی من با Builder»، متن واقعی Builder و لینک «مشاهده صفحه» بود. این نتیجه ثابت می‌کند revision منتشرشده از Builder به public renderer وصل شده است.
- رگرسیون صفحه‌به‌صفحه با مرورگر داخلی روی ۲۰ مسیر اصلی شامل Dashboard، Content، Revisions، Media، Builder، Appearance، Site Editor، Blocks، Extensions، Updates، Users، Settings، System، Recovery، Audit، Infrastructure، Security، Performance، Logs و Developer SDK اجرا شد؛ در هیچ‌یک `Internal Server Error`، `API error (5xx)` یا پیام integrity مشاهده نشد. وضعیت‌های خالی مثل `۰ رسانه` و `۰ تنظیم` به‌عنوان empty state واقعی باقی ماندند.
- صفحه‌ی Logs پس از تست، `خطاهای فعال/اخیر: 0` و `Warning: 0` داشت؛ ۸ رکورد موجود فقط historical errorهای قدیمی media/content/settings از تاریخ‌های قبل هستند و خطای جدید Builder در آن ثبت نشده است.
- Validation source این نوبت: PHP lint فایل flow **PASS**، `composer test:cms` **17 passed / 0 failed**، archive checksum و package identity **PASS**. کل suite repository همچنان به‌دلیل دو شکست مستقل vendor/Pincore در `PlatformBuildTest` و `AppRouterSystemTest` release-green نیست.
- root cause دقیق failure اولیه v19 از روی لاگ تاریخی قابل اثبات نشد؛ v20 علاوه بر ثبت تشخیص امن، با reload/update مسیر را پایدار کرد. بنابراین وضعیت این finding: **live regression passed once after v20; continued monitoring recommended**، نه ادعای رفع قطعی همه حالت‌های proxy/session.

## Validation این نوبت — اتصال probeهای واقعی Runtime Health

- finding فاز قبل تأیید شد: صفحه‌ی System در target با وجود سلامت PHP، دیتابیس و schema، وضعیت کلی را `unknown` نشان می‌داد؛ علت مستقیم، placeholder بودن probeهای Cache، Storage و Queue بود، نه خرابی سرویس‌ها.
- `SystemHealthRegistrar` اصلاح شد تا به‌جای مقدار ثابت، از adapterهای واقعی `PinooxCacheStore`، `PinooxStorageDriver` و `FileQueueRepository` استفاده کند. probeها عملیات محدود read/write/delete یا scan امن انجام می‌دهند و داده‌ی موقت را پاک می‌کنند.
- regression test جدید برای binding این سه adapter اضافه شد؛ `composer test:cms`: **18 passed / 0 failed**. PHP lint فایل‌های تغییرکرده نیز **PASS** است.
- نسخه‌ی v21 با source/manifest `0.23.48 #2348` ساخته شد. `pinx:info` هویت `com_pinoox_cms`، نسخه‌ی مورد انتظار و `929` entry را تأیید کرد؛ archive test، parity و SHA-256 هر دو کپی **PASS** است.
- artifact در این دو مسیر موجود و همسان است: `/home/saeed/Downloads/crm/POSSINO/هسته/codex/NanoPino-0.23.48-health-probes-v21.pinx` و `/home/saeed/Projects/pinoox-local/NanoPino-0.23.48-health-probes-v21.pinx`. SHA-256: `fc8c104d116ecd938f9aedd09c68ea32772e7fb7b7eac82ecec55d7ef5d34cb7`.
- نصب/Update زنده v21 در manager با موفقیت انجام شد و پیام موفقیت بروزرسانی مشاهده شد. صفحه‌ی `https://test.boxpdf.ir/qwe/system` پس از reload نسخه‌ی `CMS 0.23.48` و `Health ok` را نشان داد؛ `Database`, `Cache`, `Storage`, `Queue`, `Extensions`, `system.cms_schema` و `system.cms_integrity` همگی `ok` بودند. وضعیت عملیاتی زنده نیز `0` خطای فعال/اخیر دارد.
- رگرسیون مشاهده‌ی کاربر نیز بررسی شد: `https://test.boxpdf.ir/qwe/page/zx` با عنوان، heading «صفحه واقعی من با Builder»، متن بلوکی منتشرشده و لینک مشاهده باز شد؛ داشبورد نیز لینک `نمایش سایت — /qwe/site` و نسخه‌ی فعال `0.23.48` را نشان داد.
- تاریخچه‌ی سلامت هنوز رکوردهای `unknown` قبلی را نگه می‌دارد؛ این داده‌ی تاریخی است و با probeهای جدید تناقض ندارد. رکوردهای بعدی باید پس از اجرای health refresh با وضعیت `ok` ثبت شوند.
- remaining risk: این تغییر availability و repository health را ثابت می‌کند؛ اجرای worker صف، اجرای cron واقعی روی هاست و signed release هنوز در این target به‌صورت مستقل اثبات نشده‌اند. CMS را به‌دلیل این موارد `Production Ready` اعلام نمی‌کنم.

## Validation این نوبت — سخت‌سازی Queue و بازیابی jobهای رهاشده

- یک failure mode عملیاتی در `QueueWorker` پیدا شد: اگر job type حذف یا غیرفعال شده باشد، lookup رجیستری پیش از مرز `try/catch` انجام می‌شد و رکورد پس از reserve در وضعیت `processing` باقی می‌ماند.
- `QueueWorker` اکنون job type ناشناخته را به‌صورت safe dead-letter می‌کند، خطای داخلی را بدون افشای path ذخیره می‌کند و اجرای batch را متوقف نمی‌کند.
- برای جلوگیری از گیرکردن job پس از crash یا timeout، قابلیت concrete و backward-compatible `recoverStaleProcessing` به adapterهای file/in-memory اضافه شد. Worker پیش از drain، leaseهای پردازشی قدیمی‌تر از یک ساعت را به `failed` و قابل retry برمی‌گرداند؛ interface عمومی موجود برای extensionهای قدیمی شکسته نشد.
- دو regression test جدید اضافه شد: dead-letter شدن job ناشناخته و بازیابی lease پردازشی stale. نتیجه‌ی `composer test:cms`: **20 passed / 0 failed**؛ PHP lint هر چهار فایل تغییرکرده **PASS** است.
- نسخه‌ی v22 با source/manifest `0.23.49 #2349` ساخته شد. `pinx:info` هویت `com_pinoox_cms`، نسخه‌ی مورد انتظار و `929` entry را تأیید کرد؛ archive test و parity **PASS** است. artifact در این دو مسیر موجود و همسان است: `/home/saeed/Downloads/crm/POSSINO/هسته/codex/NanoPino-0.23.49-queue-hardening-v22.pinx` و `/home/saeed/Projects/pinoox-local/NanoPino-0.23.49-queue-hardening-v22.pinx`. SHA-256: `219656298c332a55d71d7be2e6f74cb541236a8e9ca50cb616e27b646a9ef443`.
- Update زنده v22 در manager موفق بود. صفحه‌ی `https://test.boxpdf.ir/qwe/system` پس از reload، `CMS 0.23.49`، `Health ok` و وضعیت `ok` برای Database، Cache، Storage، Queue، Scheduler، Extensions و integrity را نشان داد؛ دو snapshot جدید history نیز `ok` ثبت شدند و خطاهای active/recent برابر `0` بود.
- صفحه‌ی زنده‌ی `https://test.boxpdf.ir/qwe/system/infrastructure` نیز Queue را `Live`، `Failed Jobs: 0`، `Queue is empty` و adapterهای واقعی Cache/Storage را `ok` نمایش داد. این اثبات live برای repository/health است؛ به‌دلیل خالی‌بودن صف، اجرای یک job business واقعی در target در این pass **Not executed** باقی ماند.

## Validation این نوبت — بستن مسیر خطای خام Global Block

- در بازبینی API مشخص شد `GlobalBlockRuntimeApiController::update` برای `RuntimeException`های غیرِ `not found`، exception را دوباره به لایه‌ی بالاتر پرتاب می‌کرد؛ این مسیر می‌توانست پاسخ خام ۵۰۰ و نشت جزئیات داخلی ایجاد کند.
- exception اکنون از `CmsRuntimeErrorReporter` عبور می‌کند و پاسخ عمومی ثابت، `X-CMS-Error-ID` و ثبت داخلی کنترل‌شده دارد؛ مسیر 404 مربوط به Global Block همچنان رفتار قبلی را حفظ می‌کند.
- regression test سراسری اضافه شد تا هیچ API controller دارای `catch`، exception را خارج از مرز مرکزی دوباره پرتاب نکند. نتیجه‌ی `composer test:cms`: **21 passed / 0 failed** و PHP lint **PASS** است.
- نسخه‌ی v23 با source/manifest `0.23.50 #2350` ساخته و آماده‌ی تحویل شد. بسته‌ی نهایی unsigned است و signing production هنوز انجام نشده است.
- پس از Update زنده، `https://test.boxpdf.ir/qwe/system` نسخه‌ی `CMS 0.23.50`، `Health ok`، `Queue ok` و `0` خطای active/recent را نشان داد. صفحه‌ی `https://test.boxpdf.ir/qwe/system/infrastructure` نیز `Queue Live`، `Failed Jobs: 0` و `Queue is empty` را نشان داد؛ هیچ پاسخ خام ۵۰۰ در این بررسی مشاهده نشد.
- بسته‌ی تحویلی در `/home/saeed/Downloads/crm/POSSINO/هسته/codex/NanoPino-0.23.50-api-boundary-v23.pinx` و کپی پروژه در `/home/saeed/Projects/pinoox-local/NanoPino-0.23.50-api-boundary-v23.pinx` قرار گرفت؛ `pinx:info` هویت package، نسخه و `929` entry را تأیید کرد و archive test **PASS** شد.

## Validation تکمیلی این فاز — رگرسیون کامل مسیرها

- با مرورگر داخلی، ۲۲ مسیر شامل Dashboard، Content، Revisions، Media، Builder، Appearance، Site Editor، Blocks، Extensions، Updates، Users، Settings، تمام صفحات System، `/qwe/site` و `/qwe/page/zx` به‌صورت واقعی باز شدند.
- نتیجه: **22/22 PASS**؛ در هیچ مسیر `Internal Server Error`، `API error (5xx)`، `Request integrity validation failed` یا redirect ناخواسته مشاهده نشد. خروجی Content، لینک‌های public و صفحه‌ی Builder منتشرشده نیز قابل مشاهده بودند.
- این pass فقط رگرسیون ناوبری و پاسخ قابل‌نمایش را اثبات می‌کند؛ تست authenticated mutation برای هر endpoint، اجرای job تجاری واقعی در Queue و release signing همچنان gateهای جداگانه هستند.

## Validation این نوبت — جلوگیری از لینک عمومی برای Draft

- در `PublicContentUrl` مشخص شد هر رکورد `page`/`post`، حتی با وضعیت `draft` یا `trash`، مسیر عمومی دریافت می‌کرد؛ این با قرارداد route عمومی که فقط `published` را می‌پذیرد سازگار نبود و می‌توانست لینک ظاهراً معتبر اما 404 تولید کند.
- URL عمومی اکنون فقط برای `ContentStatus::Published` ساخته می‌شود؛ draft، scheduled و trash همچنان URL عمومی ندارند. مسیر published و mount-aware بودن URL حفظ شده است.
- regression test برای هر دو حالت draft و published اضافه شد. نتیجه‌ی `composer test:cms`: **22 passed / 0 failed** و PHP lint فایل‌های تغییرکرده **PASS** است.
- نسخه‌ی v24 با source/manifest `0.23.51 #2351` ساخته شد و Update زنده‌ی آن با پیام موفقیت انجام شد. Content پس از پایان loading، `3` رکورد شامل `2` published و `1` draft را نشان داد؛ draft هیچ `مشاهده صفحه عمومی` ندارد و هر دو published لینک mount-aware صحیح دارند. صفحه‌ی عمومی `zx` نیز بدون خطا باز شد.
- بسته‌ی تحویلی در `/home/saeed/Downloads/crm/POSSINO/هسته/codex/NanoPino-0.23.51-public-url-v24.pinx` و کپی پروژه در `/home/saeed/Projects/pinoox-local/NanoPino-0.23.51-public-url-v24.pinx` قرار گرفت؛ `pinx:info` هویت package، نسخه و `929` entry را تأیید کرد و archive test **PASS** شد.

## Validation این نوبت — محدودیت طول slug در برخورد با collision

- در بازبینی مسیر ساخت Content و Taxonomy مشخص شد `Slugger` ابتدا slug را تا ۱۶۰ کاراکتر کوتاه می‌کند، اما هنگام collision suffix را بدون کوتاه‌سازی دوباره به آن اضافه می‌کرد؛ در نتیجه slug تکراریِ ۱۶۰ کاراکتری می‌توانست از محدودیت ستون `varchar(160)` عبور کند و به خطای ذخیره یا پاسخ خام ۵۰۰ منجر شود.
- `Slugger` اکنون suffix را با حفظ حداکثر طول ۱۶۰ تولید می‌کند؛ رفتار برای Content و Taxonomy یکسان است و نام پایه تا اندازه‌ی لازم کوتاه می‌شود. این تغییر schema یا داده‌ی موجود را دستکاری نمی‌کند.
- regression test برای collisionهای متوالی با slug ۱۶۰ کاراکتری اضافه شد و خروجی `composer test:cms` برابر **23 passed / 0 failed** است؛ PHP lint و `git diff --check` نیز **PASS** هستند.
- نسخه‌ی source و manifest به `0.23.52 #2352` ارتقا یافت. بسته‌ی نهایی unsigned با `pinx:info`، archive test و SHA-256 دوکپی بررسی شد.
- Update زنده‌ی v25 در manager با پیام موفقیت انجام شد. پس از بارگذاری داده‌ها، Content نسخه‌ی `0.23.52` را با `۳` رکورد (`۲` منتشرشده و `۱` پیش‌نویس) نشان داد؛ رکورد draft فاقد لینک عمومی بود و هر دو published لینک mount-aware صحیح داشتند.
- صفحه‌ی System روی target نسخه‌ی `0.23.52`، وضعیت `Health ok` و `13 checks` سالم را نشان داد. `/qwe/site`، `/qwe/page/zx` و تمام ۲۲ مسیر اصلی پنل نیز بعد از Update با مرورگر داخلی بررسی شدند: **22/22 PASS** و بدون `Internal Server Error`، `API error (5xx)` یا `Request integrity validation failed`.
- محدودیت باقی‌مانده: این اصلاح race condition هم‌زمانی بین چند درخواست را حل نمی‌کند؛ یکتایی نهایی همچنان توسط unique index دیتابیس محافظت می‌شود و برای تشخیص رفتار خطای هم‌زمانی در target، تست بار هم‌زمان اجرا نشده است.

## Validation این نوبت — تبدیل collision دیتابیس به Conflict کنترل‌شده

- مسیر ایجاد/ویرایش Content در شرایط رقابت بررسی شد: کنترل اولیه‌ی `slugExists` برای درخواست‌های هم‌زمان کافی نیست و unique index نهایی می‌تواند exception دیتابیس تولید کند.
- `ContentConflictException` اضافه شد تا فقط violation شناخته‌شده‌ی `cms_content_slug_unique` را به خطای دامنه تبدیل کند؛ repositoryهای create/update این مرز را حفظ می‌کنند و API آن را با کد `CONTENT_SLUG_CONFLICT` و HTTP `409` برمی‌گرداند.
- خطاهای عمومی دیتابیس همچنان از reporter مرکزی و پاسخ کنترل‌شده‌ی ۵۰۰ عبور می‌کنند. هیچ پیام SQL خام به کاربر برنمی‌گردد.
- regression test تشخیص duplicate key، رد خطای عمومی و وجود envelope `409` را پوشش داد؛ `composer test:cms` در این فاز **24 passed / 0 failed** است.
- نسخه‌ی source و manifest به `0.23.53 #2353` ارتقا یافت. بسته‌ی نهایی unsigned با `pinx:info`، archive test و SHA-256 دوکپی ساخته و بررسی شد.
- Update زنده‌ی v26 در manager با پیام موفقیت انجام شد؛ گزینه‌ی route assignment بدون تغییر باقی ماند تا mount فعلی `/qwe` حفظ شود.
- پس از Update، Content با نسخه‌ی `0.23.53` و ۳ رکورد (۲ published و ۱ draft) باز شد؛ لینک‌های public رکوردهای منتشرشده صحیح بودند و draft لینک عمومی نداشت.
- System پس از reload نسخه‌ی `CMS 0.23.53`، وضعیت `Health ok`، تعداد `13 checks` و `0` خطای active/recent را نشان داد.
- `/qwe/site` و `/qwe/page/zx` به‌عنوان کاربر عمومی باز شدند؛ صفحه‌ی `zx` شامل heading و محتوای بلوکی واقعی بود.
- رگرسیون کامل ۲۲ مسیر اصلی با مرورگر داخلی پس از Update اجرا شد: **22/22 PASS**؛ در هیچ مسیر `Internal Server Error`، `API error (5xx)`، `Request integrity validation failed` یا `HTTP 500` مشاهده نشد.
- Validation source این نوبت: `composer test:cms` برابر **24 passed / 0 failed**، PHP lint، `git diff --check`، metadata و archive integrity همگی **PASS** هستند.
- remaining risk: تست هم‌زمانی واقعی تحت بار در target اجرا نشده و signing production همچنان انجام نشده است؛ بنابراین این فاز رفع مسیر conflict را تأیید می‌کند، اما CMS را `Production Ready` اعلام نمی‌کند.

## Validation این نوبت — اتمیک‌سازی ایندکس جست‌وجو

- مسیر index در `PinooxDatabaseSearchDriver` دارای الگوی check-then-insert بود؛ دو درخواست هم‌زمان برای یک سند می‌توانستند هر دو نبود رکورد را ببینند و یکی با duplicate-key شکست بخورد. این failure mode برای indexing قابل retry بود، اما از بیرون می‌توانست به خطای خام یا وضعیت degraded منجر شود.
- عملیات index اکنون با `upsert` اتمیک روی کلید یکتای `site_id, document_type, document_id, locale` انجام می‌شود. زمان ایجاد فقط برای insert ثبت می‌شود و update، payload قابل تغییر را به‌روزرسانی می‌کند؛ جدول و داده‌ی موجود destructive تغییر نکرده‌اند.
- regression test وجود upsert، کلید identity مطابق migration و حذف مسیر exists-then-insert را پوشش داد. نتیجه‌ی `composer test:cms`: **25 passed / 0 failed**؛ PHP lint و `git diff --check` نیز **PASS** هستند.
- نسخه‌ی source و manifest به `0.23.54 #2354` ارتقا یافت. بسته‌ی نهایی unsigned با `pinx:info`، archive test و SHA-256 دوکپی ساخته و بررسی شد.
- ریسک باقی‌مانده: اجرای بار هم‌زمان واقعی روی target انجام نشده و signing production هنوز انجام نشده است؛ این فاز atomic write path را از روی کد و تست رگرسیون تأیید می‌کند، اما CMS همچنان `Production Ready` اعلام نمی‌شود.
- نسخه‌ی v27 با `0.23.54 #2354` روی target نصب شد؛ manager پیام `بروزرسانی اپلیکیشن با موفقیت انجام شد` و `نصب با موفقیت انجام شد.` را نشان داد و تخصیص مسیر موجود `/qwe` دست‌نخورده باقی ماند.
- پس از Update، System با reload نسخه‌ی `CMS 0.23.54`، `Health ok`، ۱۳ check سالم و `0` خطای active/recent را تأیید کرد. سایت عمومی نیز با ۲ محتوای منتشرشده، لینک `/qwe/page/zx` و بدون خطای ۵۰۰ باز شد.
- sweep نهایی ۲۲ مسیر اصلی پس از Update v27 اجرا شد: **22/22 PASS**؛ هیچ `Internal Server Error`، `API error (5xx)`، `Request integrity validation failed` یا `HTTP 500` دیده نشد.
- artifact نهایی unsigned با هویت `com_pinoox_cms`، نسخه‌ی `0.23.54 #2354` و ۹۳۰ entry در دو مسیر پروژه و خروجی تحویل کپی شد؛ checksum نهایی هر دو کپی: `23e942f9dd414007823fe689efe8f56d0ca9471e7a0215be6b1a1532eaebeb83`.

## Validation این نوبت — کنترل خطای حذف رسانه‌ی stale

- در `MediaApiController::delete`، `MediaValidationException` در catch اختصاصی وجود نداشت؛ حذف دوباره یا حذف شناسه‌ی ناموجود بنابراین به مسیر `MEDIA_DELETE_FAILED` و HTTP 500 می‌رسید.
- اکنون همان وضعیت با envelope پایدار `MEDIA_NOT_FOUND` و HTTP 404 پاسخ داده می‌شود؛ مسیرهای `403`، `409 MEDIA_IN_USE` و خطای داخلی 500 بدون تغییر حفظ شده‌اند.
- regression test وجود catch اختصاصی و status code 404 را بررسی کرد. نتیجه‌ی `composer test:cms`: **26 passed / 0 failed**؛ PHP lint و `git diff --check` نیز **PASS** هستند.
- نسخه‌ی source و manifest به `0.23.55 #2355` ارتقا یافت. بسته‌ی نهایی unsigned با `pinx:info`، archive test و checksum دوکپی ساخته و بررسی شد.
- Update زنده‌ی v28 روی target انجام شد؛ manager پیام موفقیت نصب و Update را نشان داد و mount فعلی `/qwe` حفظ شد.
- پس از reload، System نسخه‌ی `0.23.55`، `Health ok`، ۱۳ check سالم و `0` خطای active/recent را نشان داد. سایت عمومی نیز ۲ محتوای منتشرشده را در `/qwe/site` و صفحه‌ی `/qwe/page/zx` را بدون خطای ۵۰۰ نمایش داد.
- sweep نهایی ۲۲ مسیر اصلی پس از Update v28 اجرا شد: **22/22 PASS**؛ هیچ `Internal Server Error`، `API error (5xx)`، `Request integrity validation failed` یا `HTTP 500` مشاهده نشد.
- artifact نهایی unsigned با هویت `com_pinoox_cms`، نسخه‌ی `0.23.55 #2355` و ۹۳۰ entry در دو مسیر پروژه و خروجی تحویل کپی شد؛ checksum نهایی هر دو کپی: `c1e877da105a24788e7b8ebfd426821e28c61377f4b312072cab6c802739dce1`.
- ریسک باقی‌مانده: این اصلاح مسیر stale-delete را از نظر source و regression پوشش می‌دهد؛ اجرای delete واقعی روی رسانه‌ی target به‌دلیل جلوگیری از تغییر داده‌ی موجود انجام نشد. تست هم‌زمانی واقعی و signing production همچنان انجام نشده‌اند.

## Validation این نوبت — سازگاری User API با هاست اشتراکی

- در `UserRuntimeApiController`، اعتبارسنجی طول query، username و password مستقیماً به `mb_strlen` وابسته بود؛ در PHPهایی که mbstring نصب نیست، این مسیر می‌توانست قبل از envelope خطا با `Call to undefined function` به ۵۰۰ برسد.
- طول‌سنجی اکنون از helper داخلی استفاده می‌کند: `mb_strlen` در صورت وجود و `strlen` به‌عنوان fallback. رفتار محدودیت‌های فعلی تغییر نکرده و فقط portability بهتر شده است.
- regression test استفاده‌ی همه‌ی نقاط اعتبارسنجی از helper و وجود fallback را پوشش داد. نتیجه‌ی `composer test:cms`: **27 passed / 0 failed**؛ PHP lint و `git diff --check` نیز **PASS** هستند.
- نسخه‌ی source و manifest به `0.23.56 #2356` ارتقا یافت. بسته‌ی نهایی unsigned با `pinx:info`، archive test و checksum دوکپی ساخته و بررسی شد.
- بسته‌ی نهایی در `/home/saeed/Downloads/crm/POSSINO/هسته/codex/NanoPino-0.23.56-user-portability-v29.pinx` و کپی پروژه قرار گرفت؛ checksum هر دو کپی: `b24c0c40436cd17ea031a0fc683634528d6908dbb3ce6c2141ef05a8f7106ed2`.
- Update زنده‌ی v29 با پیام موفقیت انجام شد و mount فعلی `/qwe` حفظ شد. صفحه‌ی System پس از بازخوانی نسخه‌ی `CMS 0.23.56`، `Health ok`، ۱۳ check سالم و `0` خطای active/recent را نشان داد.
- صفحه‌ی Users روی runtime واقعی PHP `8.3.33` باز شد و کاربر جاری و Roleهای فعال نمایش داده شدند؛ fallback بدون mbstring در target مستقیماً اجرا نشد، چون mbstring در runtime موجود است و regression source آن را پوشش می‌دهد.
- سایت عمومی `/qwe/site` با عنوان صحیح، ۲ محتوای منتشرشده و لینک‌های public قابل مشاهده بود. sweep کامل ۲۲ مسیر اصلی با مرورگر داخلی **22/22 PASS** شد؛ هیچ `Internal Server Error`، `API error (5xx)`، `Request integrity validation failed` یا `HTTP 500` مشاهده نشد.
- در بازبینی نهایی، `composer test:cms` همچنان **27 passed / 0 failed** و console scan همه‌ی ۲۲ مسیر **22/22 clean** بود. `composer test:apps` نیز اجرا شد اما دو failure موجود در تست‌های upstream/Pincore باقی ماند: `PlatformBuildTest` در fixture مسیر `storage/local/.../.gitkeep` و `AppRouterSystemTest` در انتظار routing fixture `com_test_manager` به‌جای `com_test_welcome`; هیچ‌کدام به تست یا کد NanoPino نسبت داده نشده و در این فاز vendor دستکاری نشد.
- این فاز portability مسیر User API و رگرسیون ناوبری/سلامت زنده را تأیید می‌کند؛ تست runtime روی PHP بدون mbstring، تست بار هم‌زمان و signing production همچنان انجام نشده‌اند.

## Validation این نوبت — CSP با cutover کنترل‌شده و حذف super ضمنی

- CSP پیش‌تر به‌صورت hard-coded روی report-only بود؛ بنابراین حتی پس از nonce-ready شدن shell، فعال‌سازی enforcement بدون rebuild یا تغییر کد قابل مدیریت نبود.
- `CspPolicy::fromEnvironment()` اضافه شد. مقدار پیش‌فرض همچنان report-only است؛ فقط مقدار صریح `PINOOX_CMS_CSP_MODE=enforce` هدر `Content-Security-Policy` را فعال می‌کند. Admin controller، response listener و Security Headers از همین resolver مشترک استفاده می‌کنند تا mode گزارش‌شده و mode واقعی از هم جدا نشوند.
- regression test هر دو حالت default و enforce، نام header و حفظ report-only پیش‌فرض را پوشش داد. نتیجه‌ی suite CMS: **28 passed / 0 failed**؛ lint و `git diff --check` نیز **PASS** هستند.
- readiness زنده‌ی target نشان داد ۱ platform account، ۱ explicit super account و ۰ implicit-only account وجود دارد؛ بنابراین `platform_super` در config NanoPino به `false` تغییر کرد و دسترسی implicit حذف شد. نقش explicit حفظ شده و هیچ role یا داده‌ای حذف/تغییر داده نشد.
- نسخه‌ی source و manifest به `0.23.58 #2358` ارتقا یافت. بسته‌ی unsigned v31 پس از `pinx:info` و archive test ساخته و در دو مسیر تحویل کپی شد.
- Update زنده‌ی v31 با پیام موفقیت انجام شد. پس از غیرفعال‌شدن `platform_super`، همان حساب explicit role توانست System، Security Center و مسیرهای محافظت‌شده را باز کند؛ lockout یا خطای ۵۰۰ مشاهده نشد. Security Center اکنون برای platform super وضعیت explicit-role cutover را نشان می‌دهد و فقط warningهای SSRF/CSP باقی هستند.
- sweep کامل ۲۲ مسیر اصلی پس از v31 **22/22 PASS** و console scan همه‌ی مسیرها بدون error بود. سایت عمومی همچنان با ۲ محتوای منتشرشده قابل مشاهده است. CSP target بدون env flag همچنان report-only است و enforcement production هنوز **Not executed** باقی می‌ماند.

## Validation این نوبت — تثبیت وضعیت post-cutover در Security Center

- ریشهٔ ناسازگاری پیدا شد: `PlatformSuperTransitionReadiness::inspect()` مقدار `ready` را فقط برای حالت قبل از خاموش‌سازی محاسبه می‌کرد؛ بنابراین پس از cutover واقعی، با وجود ۱ explicit super و ۰ implicit-only، پنل عبارت گمراه‌کنندهٔ «Not safe to disable» را نشان می‌داد.
- مدل readiness اکنون دو حالت مستقل دارد: `ready` فقط به معنی آماده‌بودن برای cutover در زمانی است که `platform_super` فعال است؛ `cutover_complete` به معنی تکمیل cutover با پوشش explicit نقش‌ها پس از خاموش‌شدن آن است. داده یا role موجود حذف/تغییر داده نشد.
- صفحهٔ Security به‌جای هشدار، در حالت واقعی عبارت `Explicit-role cutover complete` و توضیح post-cutover را نمایش می‌دهد. regression source test، PHP runtime suite (**28 passed / 0 failed**)، JS test (**3 passed / 0 failed**)، PHP lint و `git diff --check` پیش از release pass شدند.
- فرانت‌اند Vue/Luma با build production تازه ساخته شد؛ source/dist parity و بررسی ۶۰ asset تولیدی pass شدند. هشدار اندازهٔ chunkهای vendor موجود است و به‌عنوان بدهی performance باز باقی می‌ماند.
- source و manifest به `0.23.59 #2359` ارتقا یافتند. بستهٔ unsigned v32 پس از build، `pinx:info`، archive test و checksum در دو مسیر تحویل ساخته و کپی شد.
- update زندهٔ v32 با پیام موفقیت انجام شد و mount `/qwe` حفظ شد. System روی target نسخهٔ `0.23.59`، health `ok`، ۱۳ check سالم و ۰ active/recent error را نشان داد. Security Center مقدار `PASS 13 / WARNING 2 / FAIL 0` و explicit-role cutover complete را نشان داد؛ lockout یا HTTP 500 مشاهده نشد.
- sweep کامل ۲۲ مسیر اصلی پس از v32 **22/22 PASS** و console scan همهٔ مسیرها **22/22 clean** شد. این تأیید شامل `/qwe/users`، `/qwe/system/security`، Builder، appearance، extensions، settings، public `/qwe/site` و `/qwe/page/zx` است.
- CSP روی target تا این نوبت report-only بود؛ در این فاز با حفظ امکان rollback صریح، پیش‌فرض به enforce منتقل می‌شود. signed PINX، تست هم‌زمانی/بار، backup/rollback واقعی و دو شکست upstream در `composer test:apps` همچنان release gateهای باز هستند.

## Phase بعدی — تشخیص دقیق وضعیت outbound search

- `CmsRuntimeServices::searchDriver()` اکنون وضعیت transport فعال را از وضعیت fallback دیتابیس جدا می‌کند. وقتی remote search پیکربندی نشده است، هیچ outbound transport فعالی وجود ندارد و fallback دیتابیس fail-closed به‌عنوان وضعیت امن گزارش می‌شود.
- وقتی remote search پیکربندی شده باشد، `GuardedRemoteSearchTransport` همچنان باید `SsrfGuard`، allowlist host/port، DNS/IP validation، HTTPS، cURL `RESOLVE` و عدم redirect را طی کند؛ در این حالت نبود guard به‌صورت warning باقی می‌ماند.
- Security Center اکنون علاوه بر `ssrf_transport`، مقدار `ssrf_guard_ready` را نشان می‌دهد و در target فعلی باید به‌جای `Unbound` عبارت `Inactive (database fallback)` را نمایش دهد. این تغییر semantics است و مسیر outbound جدیدی فعال نمی‌کند.
- نسخهٔ source و manifest به `0.23.60 #2360` ارتقا یافت. بستهٔ unsigned v33 پس از `pinx:info`، archive test و checksum ساخته و در دو مسیر تحویل کپی شد؛ checksum نهایی در گزارش تحویل ثبت شده است.
- update زندهٔ v33 با پیام موفقیت انجام شد. Security Center روی target مقدار `PASS 14 / WARNING 1 / FAIL 0`، `Outbound SSRF guard: pass` با توضیح `database search ... fail-closed fallback` و runtime gate `SSRF Transport: Inactive (database fallback)` را نشان داد. explicit-role cutover نیز همچنان complete و بدون lockout باقی ماند.
- sweep مجدد ۲۲ مسیر اصلی پس از v33 **22/22 PASS** و console scan همهٔ مسیرها **22/22 clean** شد. warning باقیمانده فقط CSP report-only است؛ signed PINX، rollback واقعی و تست بار همچنان انجام نشده‌اند.

## Phase بعدی — CSP enforcement با rollback صریح

- ریشهٔ warning CSP مشخص شد: resolver قبلی در نبود env مقدار report-only را انتخاب می‌کرد، درحالی‌که shell nonce-ready و مسیرهای browser قبلاً بدون خطای console بررسی شده بودند.
- resolver اکنون به‌صورت secure-by-default enforce می‌کند. فقط مقدار صریح `PINOOX_CMS_CSP_MODE=report-only` برای rollback موقت diagnostics حالت report-only را فعال می‌کند؛ مقدارهای enforce/نامشخص دیگر مسیر ناامنِ پیش‌فرض ایجاد نمی‌کنند.
- regression test حالت پیش‌فرض enforce، header `Content-Security-Policy` و rollback report-only را پوشش می‌دهد. suite PHP با enforce environment **28/28 PASS**، JS test **3/3 PASS**، lint و `git diff --check` سبز شدند.
- نسخهٔ source و manifest به `0.23.61 #2361` ارتقا یافت. بستهٔ unsigned v34 پس از build، profile enrichment، `pinx:info` و archive test در مسیر پروژه و خروجی تحویل ساخته شد.
- پیش از اعلام completion، update زنده باید با session واقعی بررسی شود: نسخهٔ target، header enforce، اجرای shell/Vite/Luma، Security Center، System، public site، sweep ۲۲ مسیر و console scan. در صورت هر CSP violation، env rollback به report-only و بازگشت به مرحلهٔ اصلاح انجام می‌شود.

## Phase بعدی — اصلاح وضعیت CSP در API security boundary

- بررسی زنده نشان داد shell با enforce بالا می‌آید، اما Security Center هنوز warning CSP نمایش می‌دهد. ریشهٔ مشکل این بود که `RuntimeBindingState` در request مستقل API دوباره مقداردهی نشده بود و مقدار static request قبلی قابل اتکا نبود.
- `SecurityRuntimeApiController` اکنون `CspPolicy::fromEnvironment()` را در همان request resolve می‌کند و `RuntimeBindingState::setCsp()` را قبل از ساخت `SecurityRuntimeState` اجرا می‌کند؛ بنابراین header mode و posture mode از یک منبع واقعی می‌آیند.
- regression source test این binding را پوشش داد و suite PHP **28/28 PASS**، JS **3/3 PASS**، lint و `git diff --check` سبز شدند.
- نسخهٔ source و manifest به `0.23.62 #2362` ارتقا یافت. بستهٔ unsigned v35 پس از build، profile enrichment، `pinx:info`، archive test و checksum در مسیر پروژه و خروجی تحویل ساخته و کپی شد.
- update زندهٔ v35 با پیام موفقیت انجام شد. System روی target نسخهٔ `0.23.62`، health `ok`، ۱۳ check سالم و ۰ active/recent error را نشان داد. Security Center مقدار `PASS 15 / WARNING 0 / FAIL 0` و `CSP is enforced` را نمایش داد.
- sweep نهایی با CSP enforce روی هر ۲۲ مسیر **22/22 PASS** شد؛ هیچ `HTTP 500`، خطای API، integrity error یا CSP blocking دیده نشد و console scan نیز **22/22 clean** بود. این نتیجه شامل داشبورد، content، media، Builder، appearance، extensions، users، settings، recovery، audit، infrastructure، security، performance، logs، SDK، سایت عمومی و صفحهٔ `zx` است.
- rollback صریح همچنان با `PINOOX_CMS_CSP_MODE=report-only` در کد و suite تست‌شده باقی است. بسته unsigned است؛ signing PINX، backup/rollback واقعی، تست بار و دو failure upstream در `composer test:apps` هنوز release gateهای مستقل هستند.

## Phase بعدی — تصحیح پیام وضعیت Security Posture

- پس از سبزشدن ۱۵ کنترل امنیتی، یک ناسازگاری UI پیدا شد: متن توضیح posture هنوز پیام قدیمی warning را نمایش می‌داد.
- پیام صفحهٔ Security اکنون بر اساس state تغییر می‌کند و برای حالت pass عبارت `All runtime security controls are active and reporting healthy.` را نشان می‌دهد؛ حالت‌های warning/fail همچنان پیام تشخیصی مناسب خود را حفظ می‌کنند.
- build production فرانت‌اند و source/dist parity برای ۶۰ asset pass شد و regression test مربوط به پیام جدید نیز **3/3 PASS** شد.
- source و manifest به `0.23.63 #2363` ارتقا یافتند. بستهٔ unsigned v36 پس از build، profile enrichment، `pinx:info`، archive test و checksum ساخته و در مسیر پروژه و خروجی تحویل کپی شد.
- update زندهٔ v36 با پیام موفقیت انجام شد. Security Center روی target مقدار `PASS 15 / WARNING 0 / FAIL 0`، `CSP is enforced`، `Explicit-role cutover complete` و متن `All runtime security controls are active and reporting healthy.` را نمایش داد. System نسخهٔ `0.23.63`، health `ok`، ۱۳ check سالم و ۰ active/recent error را نشان داد.
- sweep نهایی v36 با CSP enforce روی هر ۲۲ مسیر **22/22 PASS** و console scan **22/22 clean** شد؛ هیچ HTTP 500، API error، integrity error یا CSP blocking مشاهده نشد.

## Phase بعدی — همگام‌سازی metadata افزونه پس از upgrade

- بازبینی داشبورد زنده یک ناسازگاری قابل مشاهده پیدا کرد: نسخهٔ runtime روی System به‌روز بود، اما ردیف `com_pinoox_cms` در داشبورد نسخهٔ قدیمی نشان می‌داد.
- ریشهٔ مشکل در `PinooxInstalledExtensionDiscovery::syncRegistry()` بود؛ وجود شناسهٔ قبلی باعث می‌شد manifest جدید بدون مقایسه یا refresh نادیده گرفته شود.
- همگام‌سازی اکنون برای owner یکسان، تغییرات package/type/version/publisher را با `replace=true` به‌صورت کنترل‌شده refresh می‌کند و owner متفاوت هرگز overwrite نمی‌شود. تست regression برای stale-version refresh اضافه شد.
- source و manifest به `0.23.64 #2364` ارتقا یافتند. بستهٔ unsigned v37 پس از build production، profile enrichment، `pinx:info`، archive test و checksum در مسیر پروژه و خروجی تحویل ساخته شد.
- live update v37 با پیام موفقیت انجام شد؛ داشبورد اکنون `com_pinoox_cms 0.23.64` و System نیز `CMS 0.23.64` را نشان می‌دهد. این دو مقدار که پیش‌تر drift داشتند، پس از refresh همسان شدند.
- System روی target `Health ok`، ۱۳ check سالم و `0` active/recent error را نشان داد. sweep پس از v37 روی هر ۲۲ مسیر **22/22 PASS** و console scan **0 error** شد؛ هیچ HTTP 500، API error، integrity error یا CSP blocking مشاهده نشد.

## Phase بعدی — جلوگیری از false green در performance health

- صفحهٔ کارایی نشان داد بخشی از budgetها هنوز `unmeasured` هستند؛ در این حالت health قبلاً به‌اشتباه `ok` گزارش می‌شد.
- `PerformanceHealthRegistrar` اکنون در صورت وجود هر budget اندازه‌گیری‌نشده، وضعیت `warning` و تعداد دقیق `unmeasured` را برمی‌گرداند؛ `fail` همچنان `error` و budgetهای اندازه‌گیری‌شده طبق قبل ارزیابی می‌شوند.
- regression test این رفتار را با recorder و query probe واقعیِ test double پوشش می‌دهد. نتیجهٔ suite CMS: **30/30 PASS** و lint/`git diff --check` سبز است.
- source و manifest به `0.23.65 #2365` ارتقا یافتند. بستهٔ unsigned v38 پس از build، profile enrichment، `pinx:info`، archive test و checksum ساخته و برای update target آماده شد.

## Phase بعدی — ثبت telemetry واقعی برای APIهای facade-backed

- performance runtime در حالت عادی `Recent Samples: 0` نشان می‌داد، چون APIهای facade-backed زمان پاسخ خود را ثبت نمی‌کردند.
- مرز مشترک `CmsApiControllerResponder` اکنون زمان اجرای موفق و خطادار را در metric `api_ms` با operation tag ثبت می‌کند. recorder در `CmsRuntimeServices` cache می‌شود و خطای نوشتن telemetry عمداً fail-open است.
- این تغییر مسیر پاسخ، امنیت یا وابستگی به سرویس خارجی را تغییر نمی‌دهد و فقط دادهٔ تشخیصی حداقلی تولید می‌کند. regression source test و suite CMS **31/31 PASS**، JS test **3/3 PASS**، lint و `git diff --check` سبز هستند.
- source و manifest به `0.23.66 #2366` ارتقا یافتند. بستهٔ unsigned v39 ساخته، profile-enrich، archive-test و در مسیر پروژه و خروجی تحویل کپی شد.
- update زندهٔ v39 با پیام موفقیت انجام شد و mount فعلی `/qwe` حفظ شد. داشبورد نسخهٔ `com_pinoox_cms 0.23.66` و System نسخهٔ `CMS 0.23.66`، health `ok`، ۱۳ check سالم و `0` active/recent error را نشان دادند.
- صفحهٔ Performance پس از چند درخواست واقعی، `Recent Samples: 3` را نشان داد؛ سه sample از نوع `api.facade_response` با metric `api_ms` برای `system.health`، `system.logs` و `system.health.history` ثبت شد. نمونهٔ system.health با مقدار حدود `119.94ms` در وضعیت `pass` بود.
- sweep کامل مسیرهای اصلی پس از v39 **22/22 PASS** و console scan **0 error** شد؛ هیچ HTTP 500، API error، integrity error یا CSP blocking مشاهده نشد.
- پس از ثبت نتیجهٔ live، بسته برای همگام‌سازی مستندات rebuild شد؛ چون منطق runtime تغییر نکرد، redeploy مجدد لازم نبود. signing PINX، تست بار/هم‌زمانی، backup/rollback واقعی، budgetهای performance اندازه‌گیری‌نشده و دو failure upstream در `composer test:apps` همچنان release gateهای باز هستند.

## Phase بعدی — اندازه‌گیری واقعی Search budget

- پس از ثبت telemetry عمومی API، بودجهٔ `search_ms` هنوز `unmeasured` بود؛ درحالی‌که Search API مرز پاسخ مشخص و fail-safe دارد.
- `SearchRuntimeApiController` اکنون زمان کل درخواست جست‌وجو را در `finally` ثبت می‌کند و `CmsRuntimeServices::recordSearchTiming()` آن را با metric `search_ms` و نام `search.response` در همان recorder اشتراکی می‌نویسد. خطای recorder مانند API telemetry fail-open است و قرارداد پاسخ تغییر نکرده است.
- regression source test، suite CMS و lint با موفقیت اجرا شدند. نسخهٔ source و manifest به `0.23.67 #2367` ارتقا یافتند؛ بستهٔ unsigned v40 ساخته و برای live validation آماده شد.
- update زندهٔ v40 با پیام موفقیت انجام شد و mount `/qwe` حفظ شد. System نسخهٔ `CMS 0.23.67`، `Health ok`، ۱۳ check سالم و `0` active/recent error را نشان داد.
- پس از درخواست واقعی Search از مسیرهای مدیریت، Performance مقدار `Search request / search_ms / pass / 28.863167` را نشان داد و sample `search.response` با operation `search.index` در Recent Samples ثبت شد؛ بنابراین این budget دیگر unmeasured نیست.
- sweep مسیرهای اصلی پس از v40 **22/22 PASS** شد؛ هیچ HTTP 500، API error، integrity error یا CSP blocking مشاهده نشد. برخی صفحات در لحظهٔ اول loading نمایش می‌دهند و پس از تکمیل درخواست‌ها با بازخوانی پایدار بررسی شدند.
- بستهٔ تحویل نهایی v40 در دو مسیر با checksum یکسان `c532ccbcf18435a39d073b4796703bf972148295204b5f1cce6a01efb6431bf1` قرار دارد. signing PINX، تست بار/هم‌زمانی، backup/rollback واقعی، budgetهای boot/extension/builder/queue/cache و دو failure upstream در `composer test:apps` همچنان release gateهای باز هستند.

## Phase بعدی — اجباری‌کردن approval و حفظ publisher در چرخهٔ ارتقا

- در مسیر `ExtensionCenterService::executePackageOperation()` یک نقص امنیتی پیدا شد: نتیجهٔ `ExtensionReviewTicketService::consume()` نادیده گرفته می‌شد و مقدار `approved` هنگام اجرای عملیات همیشه `true` ارسال می‌شد؛ بنابراین reviewهای `ApprovalRequired` می‌توانستند بدون تأیید واقعی اجرا شوند.
- اجرای عملیات اکنون فقط از `ticket->approved` مصرف‌شده استفاده می‌کند؛ توکن معتبر به‌تنهایی approval محسوب نمی‌شود و مسیر approval اجباری بدون تأیید صریح قابل اجرا نیست.
- `InstalledExtension` اکنون publisher را در کاتالوگ نگه می‌دارد. هنگام update، تغییر publisher نسبت به نسخهٔ نصب‌شده به‌عنوان `ApprovalRequired` علامت‌گذاری می‌شود و warning قابل مشاهده در review تولید می‌کند.
- regression test برای هر دو مسیر اضافه شد: تغییر publisher بدون approval رد می‌شود و منبع اجرای package دیگر approval را hard-code نمی‌کند. suite PHP این فاز **42/42 PASS** و PHP lint موفق است.
- نسخهٔ source و manifest به `0.23.69 #2369` ارتقا یافت. artifact نهایی `NanoPino-0.23.69-phase5-lifecycle-validation.pinx` با profile enrichment، `pinx:info` و archive test ساخته شد؛ preflight تعداد ۲ warning کم‌خطر و `high_risk=false` گزارش کرد. SHA-256: `4be969cf23c20503f7115ed19265417a531116506161cba607afe6476641c69b`.
- signing PINX، اجرای E2E واقعی روی target، backup/rollback واقعی و دو failure مستقل upstream در `composer test:apps` همچنان release gateهای باز هستند؛ این فاز بدون live deployment تأیید شده است.

## Validation ادامهٔ فاز lifecycle و backward compatibility

- تست کامل native Pinoox اجرا شد: **1616 passed / 2 failed / 4 skipped**. دو شکست فقط در fixtureهای upstream Pincore رخ دادند: نبود `storage/local/com_demo/.gitkeep` در `PlatformBuildTest` و تفاوت fixture routing (`com_test_manager` در برابر `com_test_welcome`) در `AppRouterSystemTest`. کد vendor و NanoPino برای سبزسازی مصنوعی تغییر داده نشد.
- مدل `InstalledExtension` برای نگه‌داری publisher به‌صورت backward-compatible اصلاح شد؛ پارامترهای positional قبلی جابه‌جا نشدند و hydration manifest با named arguments انجام می‌شود.
- suite داخلی پس از این اصلاح همچنان **42/42 PASS**، Frontend **162/162 PASS**، Composer validation و `git diff --check` سبز هستند.
- این مرحله نشان می‌دهد lifecycle کنترل‌شدهٔ repository قابل اجراست، اما signed PINX، fault-injected target recovery و shared-hosting E2E هنوز اثبات نشده‌اند.

## Phase بعدی — fault-injection و قرارداد بازیابی تراکنش

- برای مسیر به‌روزرسانی، دو سناریوی failure با `CallableUpdateStep` و `CallableSnapshotProvider` بازسازی شد: شکست وسط update با rollback کامل، و شکست در خود rollback.
- در rollback کامل، stepها به‌ترتیب معکوس اجرا شدند، receipt بازیابی database به وضعیت پایدار برگشت، recovery point به `restored` رسید و lifecycle به state اولیهٔ `active` بازگردانده شد؛ Safe Mode فعال نشد.
- در rollback ناقص، نتیجهٔ تراکنش به‌صورت ناموفق باقی ماند، extension به `failed` رفت، جزئیات خطا حفظ شد، Safe Mode به‌صورت atomic ذخیره شد و recovery point/extension در state قرنطینه ثبت شدند. core همچنان اجازهٔ boot دارد و extension قرنطینه‌شده اجازهٔ boot ندارد.
- regressionهای این دو قرارداد در suite داخلی اضافه شدند. نتیجهٔ واقعی: **44/44 PASS**. این تست‌ها test-double سطح repository هستند و جایگزین fault-injection روی filesystem/database و boot واقعی target نمی‌شوند.
- هیچ migration یا حذف داده‌ای در این مرحله انجام نشد. signed PINX، shared-hosting E2E، recovery واقعی filesystem/database و دو failure مستقل upstream در `composer test:apps` همچنان release gateهای باز هستند.

## Phase بعدی — اتصال Safe Mode به مرز ثبت extensionهای SDK

- بازبینی مسیر boot نشان داد `RecoveryBootGuard` وجود داشت اما در `ExtensionSdk::boot()` مصرف نمی‌شد؛ بنابراین extensionهای مبتنی بر SDK می‌توانستند پس از قرنطینه شدن، دوباره definitionهای خود را register کنند.
- `ExtensionSdk` اکنون پیش از ساخت context و اجرای `register()`، برای packageهای غیرهسته `assertMayBoot()` را اجرا می‌کند. در حالت Safe Mode، package قرنطینه‌شده با پیام پایدار و بدون افشای جزئیات داخلی متوقف می‌شود؛ package هسته همچنان مجاز است.
- این اصلاح به boot اصلی Pinoox دست نمی‌زند و فقط extensionهایی را enforce می‌کند که قرارداد SDK NanoPino را استفاده می‌کنند. loaderهای native خارج از این SDK هنوز نیازمند hook رسمی Pinoox برای اثبات boot-order واقعی هستند.
- regressionهای Safe Mode و SDK contract اضافه شد. نتیجهٔ واقعی suite داخلی: **46/46 PASS**، به‌علاوه PHP lint و `git diff --check` موفق.
- source و manifest به `0.23.70 #2370` ارتقا یافتند و artifact فاز با preflight کم‌ریسک ساخته شد. signed PINX، native boot-order، recovery واقعی filesystem/database و shared-hosting E2E همچنان release gateهای باز هستند.

## Phase بعدی — سخت‌گیری در filesystem recovery و persistence

- `FilesystemSnapshotProvider` پیش از restore، source symbolic link را رد می‌کند تا مسیر بازیابی به مقصد غیرقابل اعتماد منحرف نشود.
- حذف پوشه‌ها و فایل‌های snapshot دیگر خطا را silently swallow نمی‌کند؛ شکست `unlink` یا `rmdir` اکنون recovery را fail می‌کند تا rollback ناقص به‌عنوان موفق گزارش نشود.
- تست integration با فایل‌ها و پوشه‌های واقعی، restore کامل snapshot، حذف فایل خارج از snapshot و رد symbolic link را پوشش می‌دهد. persistence repository نیز با ساخت instance دوم و reload recovery point بررسی شد.
- نتیجهٔ واقعی suite داخلی: **48/48 PASS**، PHP lint، Composer validation و `git diff --check` موفق. نسخهٔ source و manifest به `0.23.71 #2371` رسید.
- این تست‌ها local filesystem هستند؛ recovery واقعی database، boot-order native Pinoox، signed PINX و shared-hosting E2E هنوز release gate هستند.
