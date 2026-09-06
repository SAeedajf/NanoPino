# ممیزی NanoPino — ۲۰۲۶/۰۹/۰۶

## نتیجه

**NOT READY برای Stable 1.0؛ تأیید اجرای کامل محصول: NOT VERIFIED.**

این ممیزی از commit `0ccc700`، نسخه `0.23.27 / 2327 / RC11` شروع شد. ساختار محصول قابل توسعه است، اما نام RC11 و پاس‌شدن بررسی‌های سورس به معنی بسته‌شدن ۲۴ فاز نیست. اصلاحات این شاخه یک مجموعه قابل بازبینی برای صحت تعاملات ادمین است؛ انتشار نسخه جدید یا نصب روی سایت فعال محسوب نمی‌شود.

## دامنه و روش

- بررسی دو دستورالعمل ارسالی، metadata، ساختار backend، مسیر boot، قراردادهای API، نصب/بازیابی، تنظیمات، کنترل دسترسی، ابزار build و تست‌های موجود.
- چهار بررسی مستقل Mobile، Desktop، UI و UX؛ ادغام یافته‌های تکراری و بررسی متقابل ایرادهای Builder.
- مقایسه ZIP/PINXهای ارسالی با ریپو، بدون جایگزینی سورس جدید با خروجی قدیمی.
- تست رفتاری متدهای واقعی runtime با پاسخ‌های تأخیردار و شکست API؛ اجرای منطق واقعی script صفحه تنظیمات Vue در محیط ایزوله.
- build واقعی Vite و کنترل metadata/asset/source/runtime parity.
- Remote Desktop ابتدا آفلاین بود و سپس متصل شد. نسخه اصلی دستگاه در `/home/saeed/Projects/NanoPino-update` نیز commit `0ccc700` داشت. بررسی PHP روی همان سورس انجام شد؛ PHP این شاخه تغییر نکرده است.
- این کار ممیزی خط‌به‌خط تک‌تک ۷۸۲ فایل یا تست نفوذ کامل نیست. مسیرهای پُرخطر نمونه‌برداری شده‌اند؛ باقی دامنه‌ها در ماتریس زیر صریحاً باز هستند.

## موجودی و تطبیق مراجع

| مورد | نتیجه قابل اثبات |
|---|---|
| Backend | ۷۸۲ فایل PHP؛ ۱۸ migration |
| فرانت‌اند | Vue 3.5.41، Luma 0.4.12، PrimeVue 4.5.0، Vite 8.0.16 طبق lock/config؛ بدون ادعای latest |
| مرجع PINX | نسخه 0.23.1، تعداد ۷۸۶ entry؛ ۶۳۲ فایل یکسان، ۱۵۴ فایل متفاوت، صفر فایل مرجع مفقود در baseline ریپو |
| ZIP builder | ۴۶ entry، مربوط به 0.23.1-r3؛ مبنای جایگزینی RC11 نیست |
| ZIP dist | ۵۱ entry؛ خروجی آماده به‌تنهایی مدرک تطابق با سورس RC11 نیست |
| مستندات الزامی baseline | ۲۹ مسیر از ۳۰ مسیر `required_docs` موجود نبود؛ تمام ۲۲ machine contract موجود بود |
| تست‌های baseline | ۶۶/۶۶ PASS؛ عمدتاً source contract، نه E2E |
| تست‌های این اصلاح | ۷۹/۷۹ PASS شامل ۱۳ تست رفتاری جدید |
| build | Vite PASS؛ ۵۴ asset قابل دسترسی از manifest تأیید شد |
| PHP | lint تعداد ۷۸۲/۷۸۲ روی PHP 8.3، 8.4 و 8.5؛ type/integration correctness را ثابت نمی‌کند |

اثر انگشت مراجع دریافت‌شده:

| مرجع | SHA-256 |
|---|---|
| PINX 0.23.1 | `488aa8e3276ab2436371695758a3034ba866012430e3db0b27dc0a4d0e4495da` |
| builder r3 ZIP | `5e5ae47f29901ddb0f6f93d1efd7be943aa8cdd65c1f578c61cc861dc261eee0` |
| dist ZIP | `75e32b54d20b6b8f79434d994005a8e1dc55cb27fcd3625f951e2f4c613ccdf6` |

## ایرادهای اصلاح‌شده در این شاخه

محل‌های جدول نسبت به `payload/theme/cms-admin/` هستند، مگر خلاف آن ذکر شود. VERIFIED در این بخش یعنی تست ایزوله رفتار موردنظر؛ به معنی تأیید مرورگر یا API واقعی نیست.

| ID / شدت | بررسی | محل/حالت | ریشه مشکل | اصلاح | شواهد و regression |
|---|---|---|---|---|---|
| F01 HIGH | Mobile/Desktop | `src/pages/builder/page-builder.vue`، drag/visibility | مدل reactive با نام document، DOM را پنهان کرده بود | استفاده از globalThis.document | SOURCE-CONFIRMED؛ build مجدد؛ تست لمسی مرورگر باز |
| F02 HIGH | Mobile | `runtime/builder.mjs` و Vue Builder، pointercancel | لغو درگ به handler پایان موفق وصل بود | cancel فقط state را پاک می‌کند | VERIFIED: لغو پس از انتخاب مقصد، بدون جابه‌جایی |
| F03 HIGH | UX/Desktop | Builder، save/publish pending | پاسخ سرور، ویرایش‌های جدید را پاک یا saved اعلام می‌کرد | snapshot مستقل، تطبیق پاسخ با سند ارسال‌شده، حفظ dirty | VERIFIED: ویرایش هنگام save و publish |
| F04 HIGH | UX | `runtime/settings.mjs` و Vue Settings | reload همه کارت‌ها بعد save/reset تمام draftها را بازنویسی می‌کرد | reconcile همان setting، حفظ draftهای دیگر و تغییرات حین درخواست | VERIFIED: save، reset و refresh |
| F05 HIGH | UX | Settings، partial saveAll | نسخه موفق‌ها به‌روز نمی‌شد؛ retry دوباره همان کارت را می‌فرستاد | acknowledge هر موفقیت بلافاصله؛ جلوگیری از submit تکراری | VERIFIED: شکست دومین درخواست و retry |
| F06 HIGH | UX | Runtime Settings، invalid JSON | fallback خاموش JSON نامعتبر را به {} تبدیل می‌کرد | validation قبل از ارسال؛ حفظ متن نامعتبر | VERIFIED: صفر درخواست برای batch نامعتبر |
| F07 HIGH | Desktop/UX | Content، bulk | نوع عملیات حین حلقه دوباره از state خوانده می‌شد | snapshot action/IDs؛ قفل کنترل‌ها؛ حذف موفق‌ها از retry | VERIFIED: تغییر action حین درخواست و شکست جزئی |
| F08 HIGH | UX | Content، create سپس publish | بعد از موفقیت create، شکست publish شناسه را از دست می‌داد | نگهداری شناسه قبل از publish | VERIFIED: retry از PUT همان رکورد استفاده می‌کند |
| F09 HIGH | UI/Desktop | Users، keyboard | انتخاب کاربر به click روی article وابسته بود | دکمه native مستقل از دکمه‌های مدیریتی؛ label فیلترها | VERIFIED: ساختار render و انتخاب بدون مجوز users.update؛ keyboard واقعی باز |
| F10 HIGH | UI | Runtime Settings، همه inputها | title/help به کنترل مربوط نبود | accessible name و aria-describedby | VERIFIED برای string/json/string_list/integer؛ سایر حالت‌ها source-reviewed |
| F11 MEDIUM | UI | Settings / Users | اعلان ذخیره بدون live region؛ selector سراسری small در runtime | role alert/status؛ محدودکردن selector به پنل کاربران | SOURCE-CONFIRMED |
| F12 HIGH | Release | `tools/release/verify-source.sh` | وجود metadata بررسی می‌شد، نه تطابق current source؛ runtime checker صرفاً evidence می‌نوشت | check فقط‌خواندنی runtime؛ اجرای source/runtime check در gate؛ اضافه‌شدن lockfile به fingerprint | build و check PASS؛ PHP_BIN یکسان در تمام lintها |

## موارد مهم باز

| ID / شدت | محل | شاهد | اثر و اقدام لازم |
|---|---|---|---|
| O01 BLOCKER | `payload/resources/docs/documentation-manifest-v1.json` | ۲۹ سند از ۳۰ سند baseline غایب؛ source-audit-evidence وضعیت not_available | SDK را نمی‌توان فقط با مستندات فعلی مصرف کرد. ابتدا اسناد اصلی و مثال اجرایی؛ این گزارش جایگزین آن ۳۰ سند نیست |
| O02 HIGH | `payload/Cms/ExtensionCenter/Operation/PinooxExtensionOperationExecutor.php::installOrUpdate` | مسیر rollback بعد از result ناموفق است؛ throw از installer یا rebuild به آن مسیر نمی‌رسد | fault injection برای exception در install/rebuild و تضمین compensation؛ اجرای واقعی native لازم |
| O03 HIGH | همان فایل `rollback/recoveryFor` | recoveryFor فقط FilesystemSnapshotProvider دارد؛ rollback دستی تنها فایل را برمی‌گرداند | rollback بعد از migration الزاماً DB را به نسخه قبل نمی‌برد؛ طراحی recovery فایل+DB و تست تغییر schema/data لازم |
| O04 HIGH | `payload/app.php` و Pincore `Component/Access/Manager.php::isPlatformSuperUser` | platform_super=true؛ سورس native روی دستگاه، platform-scoped account را super می‌شناسد | جداسازی نقش admin/editor و تست API با حساب واقعی لازم؛ خاموش‌کردن بدون migration نقش‌ها احتمال lockout دارد |
| O05 HIGH | `payload/resources/release/security-hardening-r5-v1.json` | Safe Mode pre-boot integration باز، CSP report-only، SSRF transport unbound | وجود کلاس SafeMode تضمین نجات از crash پیش از boot نیست؛ این کنترل‌ها کامل اعلام نشوند |
| O06 HIGH | تست‌ها و CI | PHP unit/integration suite اختصاصی CMS در ریپو نبود؛ lint تنها syntax است | افزودن تست Domain/DB/API/permission و signed PINX lifecycle روی runtime مشخص |
| O07 MEDIUM | Vue و runtime جدا | چند رفتار و دسترس‌پذیری در دو پیاده‌سازی متفاوت بود | استخراج state machine مشترک در مرحله بعد؛ sync اجباری هر دو مصرف‌کننده تا آن زمان |
| O08 MEDIUM | `runtime/common.mjs::ui.grid`، Builder rails، `main.twig` | min-width ثابت، موبایل با پنل‌های پشت‌سرهم، viewport-fit=cover بدون safe-area در سورس ادمین | احتمال overflow/canvas کوچک/برخورد با ناحیه امن؛ INFERRED تا رندر 320px، landscape، zoom و iPhone |
| O09 MEDIUM | Vite build | vendor-luma حدود 1.47 MB بدون gzip؛ هشدار chunk بزرگ | پروفایل بارگذاری/INP روی دستگاه واقعی پیش از تغییر bundling؛ بدون نتیجه CWV قطعی |
| O10 MEDIUM | metadata/doc releases | فایل‌های evidence متعددی هنوز release 0.23.24 دارند، README RC11 و DEVELOPMENT RC10 بود | DEVELOPMENT اصلاح شد؛ evidence تاریخی نباید با تغییر شماره، به‌عنوان تست جدید بازنویسی شود |
| O11 LOW | `src/pages/system/page-security.vue` | heading/status انگلیسی literal در کنار catalog | تکمیل i18n و بازبینی RTL؛ این ممیزی علت هنگ گزارش‌شده در پروژه‌های دیگر را ثابت نمی‌کند |

## معماری: نقاط مثبت و مرز ادعا

- هسته از Contract، Registry و Domainهای مستقل استفاده می‌کند؛ business logic داخل vendor این ریپو قرار ندارد.
- `CmsRuntimeBinder` از AppRegister، Flow، API manifest، rate limiter و response listener پینوکس استفاده می‌کند؛ boot به‌صراحت migration اجرا نمی‌کند.
- Repositoryهای CMS به `CmsDatabase` و connection پینوکس متکی هستند؛ نام‌های منطقی جدول و migration repair موجودند.
- نصب extension به Pinx native واگذار می‌شود و preflight مسیر، اندازه و symlink دارد. این وجود، اثبات کامل امنیت زنجیره تأمین یا rollback نیست.
- NativeAppGateway قرارداد action/listen/schedule/when/onRoute/onModel/onTheme دارد؛ test harness داخلی فقط ثبت in-memory را ثابت می‌کند.
- Content/Builder سند ساختاریافته و versioned دارند؛ کنترل expected_version در بخش‌هایی موجود است. تنظیمات اولیه بدون نسخه هنوز به تست concurrency واقعی DB نیاز دارند.
- اتصال Search، Cache، Queue و Health موجود است، اما remote driver transport، fallback واقعی هاست و معیارهای performance اجرای هدف جداگانه می‌خواهند.
- Singletonهای CmsKernel/CmsRuntimeServices و تعداد زیادی static factory وابستگی به request lifecycle ایجاد می‌کنند؛ محیط persistent-worker نیازمند بررسی reset است.

## ماتریس رسمی فازها

درصد محصول از شمار فایل یا شماره RC قابل محاسبه نیست؛ Progress عمداً به‌صورت سطح شاهد گزارش شده است. هیچ فازی بدون acceptance اصلی Done نشده است.

| Phase | Status | Progress | Completed / evidence | Remaining | Tests | Bugs / Debt | Docs | Next |
|---|---|---|---|---|---|---|---|---|
| 0 Audit/Freeze | جاری | ممیزی این snapshot | موجودی و gap | freeze قرارداد و ADRهای اصلی | source | O01 | گزارش حاضر | معماری |
| 1 Kernel | پیاده‌سازی موجود | source | registry/boot | extension واقعی از cold boot | E2E باز | singleton lifecycle | ناقص | integration |
| 2 Extensions | ناقص | source | discovery/native executor | throw rollback، signed lifecycle | باز | O02/O03 | ناقص | failure injection |
| 3 Content | پیاده‌سازی موجود | source/API contract | مدل/فیلد/تاکسونومی | DB/API roles | contract | coverage | ناقص | integration |
| 4 Revision | نیازمند تأیید | source | revision/workflow | conflict/restore/schedule واقعی | باز | concurrency | ناقص | DB |
| 5 Media | نیازمند تأیید | source | upload/usage/variants | validation فایل واقعی و image processing | contract | native compatibility | ناقص | upload matrix |
| 6 Users | اصلاح جزئی | رفتار ایزوله | keyboard selection | role isolation/API | render logic | O04 | ناقص | حساب‌های مستقل |
| 7 Settings | اصلاح جزئی | رفتار ایزوله | draft/reset/partial save | concurrent DB، extension scope | ۶ تست جدید مرتبط | backend conflict | ناقص | integration |
| 8 Luma Shell | نیازمند تأیید | build | build/registry/runtime | browser/login/navigation | build | O07 | ناقص | browser |
| 9 Content UX | اصلاح جزئی | رفتار ایزوله | bulk/create retry | touch/focus/real API | ۲ تست جدید | full E2E | ناقص | browser |
| 10 Theme | نیازمند تأیید | source | native gateway/compatibility | activation persistence | contract | native binding | ناقص | theme sample |
| 11 Blocks | نیازمند تأیید | source | manifest/render/migrations | third-party block install | contract | acceptance باز | ناقص | example |
| 12 Builder | اصلاح جزئی | رفتار ایزوله | snapshot/cancel/DOM lookup | صفحه کامل و touch/browser | ۳ تست جدید | O08 | ناقص | browser |
| 13 Styles | نیازمند تأیید | source | token/responsive classes | global runtime effect | باز | mobile panels | ناقص | render |
| 14 Site Editor | نیازمند تأیید | source | template inventory | header/footer/archive end-to-end | contract | acceptance باز | ناقص | native theme |
| 15 Extension Center | نیازمند تأیید | source | review/upload/operation UI | permission prompts/real package | contract | O02 | ناقص | signed example |
| 16 Update | مسدود برای Stable | تاریخی+source | RC10→11 گزارش تاریخی | exception و DB rollback | اجرای مجدد باز | O02/O03 | محدود | recovery |
| 17 Infrastructure | نیازمند تأیید | source | driver interfaces | queue fallback/remote transports | باز | binding gaps | ناقص | integration |
| 18 Security | مسدود برای Stable | source review | CSRF/preflight/ownership | نقش‌ها/CSP/SafeMode/pentest | contract | O04/O05 | ناقص | threat model |
| 19 Performance | باز | build warning | budgets/query probe | latency/memory/INP field data | اجرا نشده | O09 | ناقص | profiling |
| 20 Health/Recovery | باز | source | health/log/recovery API | pre-boot safe mode واقعی | باز | O05 | ناقص | boot failure |
| 21 SDK | ناقص | contracts موجود | registration harness | seven executable examples | in-memory only | developer acceptance | ناقص | quickstart |
| 22 Docs Freeze | مسدود | gap مشخص | audit و ADR افزوده | required docs + link audit | gap scan | O01 | ناتمام | documentation |
| 23 RC QA | جاری | ۷۹ تست / lint / build | اصلاحات این شاخه | authenticated E2E/PINX/DB | محدود | blockers بالا | گزارش حاضر | runtime matrix |
| 24 Stable 1.0 | مسدود | قابل اعلام نیست | — | تمام hard gateها | ناتمام | موارد باز | ناتمام | پس از closure |

## کیفیت و پوشش رابط کاربری

امتیاز موقت **baseline از روی سورس: حداکثر 49/100** به‌علت نقص مسیرهای اصلی و خطر از دست‌رفتن ویرایش؛ امتیاز بصری نسخه اصلاح‌شده بدون رندر صادر نشده است. این سقف، امتیاز اندازه‌گیری‌شده محصول نیست. پوشش رندر مرورگر در این ممیزی **0%**؛ اعتماد به یافته‌های کد/تست بالا و به کیفیت بصری پایین است. معیار انتشار UI: **NOT VERIFIED**.

Regression مرورگر: فارسی/انگلیسی، RTL/LTR، 320/390/768/1366/1440px، 200% zoom، keyboard-only، pointer/touch/cancel، slow/failing network، save/publish overlap، light/dark، reduced-motion، صفحه کاربران با مجوز مدیریت نقش بدون ویرایش کاربر. پذیرش: صفر lost draft، صفر create تکراری، هیچ reorder در cancel، نام دسترس‌پذیر کنترل‌ها و عملیات موفق فقط پس از پاسخ سرور.

## اجرای مجدد

```bash
cd payload/theme/cms-admin
npm ci --ignore-scripts --no-audit --no-fund
npm test
npm run build
node runtime-fingerprint.mjs --check
cd ../../..
PHP_BIN=php8.4 tools/release/verify-source.sh
```

برای PINX فقط build native در محیط جداگانه و تست fresh-install/update/rollback با DB آزمایشی؛ گزارش تاریخی RC11 جای اجرای این شاخه را نمی‌گیرد. تغییرات فاقد DB migration و تغییر قرارداد backend هستند؛ شماره نسخه RC11 عمداً به‌عنوان baseline حفظ شده و شاخه، release جدید نیست.


## Continuation status after the baseline audit

The original tables above preserve the 2026-09-06 baseline and should not be read as the current branch status.

Subsequent merged hardening closed or materially reduced several original items:
- **O01 structural documentation gap:** required documentation paths are now 30/30 and CI checks completeness.
- **O02 extension failure path:** install/update/uninstall exceptions now converge on recovery and fail-closed quarantine.
- **O03 migration rollback risk:** unsafe migration-file-count rollback was replaced with bounded package migration-state compensation. Full database snapshot/restore is still open.
- **O04 platform_super:** not disabled; R12 adds a real account/role readiness audit so cutover cannot be claimed before explicit roles are proven.
- **O05 SSRF/CSP:** guarded Remote Search transport is bound when explicitly configured; admin shell is nonce-ready. CSP still remains report-only until browser E2E.
- **O06 PHP tests:** R13 adds executable PHP domain tests and a PHP 8.2–8.5 CI matrix. Controlled Pinoox/database/API integration remains open.

Stable 1.0 remains blocked by the target-runtime and browser/database evidence documented in the release gate.


### R14 continuation
R14 adds a pinned, ephemeral Pinoox + MySQL integration gate to repository CI. It uses the native CLI installer and PINX build/install/uninstall pipeline rather than source emulation. Fresh install must create at least the manifest-declared CMS table count, force-update must not change that count, and uninstall must remove the NanoPino app directory and CMS tables.

This upgrades the evidence for the original O06/PINX lifecycle gap, but does not claim signed-package verification, browser E2E or injected failure recovery.
