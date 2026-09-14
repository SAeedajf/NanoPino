# Phase 10 — ساخت محیط واقعی Pinoox برای تست

## نتیجه فعلی

محیط واقعی هدف روی `https://test.boxpdf.ir` از طریق مرورگر زنده بررسی شد. این
محیط از قبل وجود دارد و با یک نشست مدیریتی معتبر قابل دسترسی است؛ بنابراین
Phase 10 در این نوبت به‌عنوان **live baseline ساخته و ثبت شد**، اما Upgrade
کاندیدای جدید هنوز روی target اجرا نشده است.

## مسیرها و دسترسی

| سطح | مسیر | وضعیت |
|---|---|---|
| Manager | `https://test.boxpdf.ir/manager/` | نشست مدیریتی معتبر، پنل واقعی Pinoox |
| NanoPino dashboard | `https://test.boxpdf.ir/qwe/` | Mount واقعی، داشبورد NanoPino باز شد |
| Public site | `https://test.boxpdf.ir/qwe/site` | HTTP 200 بدون نشست |
| API health | `/qwe/api/v1/cms/system/health` | HTTP 200 در نشست معتبر |
| API extensions | `/qwe/api/v1/cms/extensions` | HTTP 200 در نشست معتبر |

Manager اپلیکیشن `NanoPino` با شناسه‌ی `com_pinoox_cms` را در وضعیت active و
با route فعلی `/qwe` نشان داد. نسخه‌ی نصب‌شده‌ی target **0.23.68** است؛ در
حالی‌که نسخه‌ی کاندیدای source **0.23.72 / code 2372** است.

## شواهد Runtime واقعی

پاسخ Health در نشست معتبر `overall=ok` و ۱۳ check داشت:

- PHP `8.3.33` روی LiteSpeed و Pinoox `3.14.4 / code 236`.
- Schema با ۱۶ جدول لازم، بدون جدول مفقود.
- Relational integrity بدون orphan relation و با foreign-key introspection.
- اتصال واقعی MySQL، Cache، Storage، Queue و Scheduler سالم.
- سایت عمومی با HTTP 200 و هدرهای `nosniff` و `DENY` پاسخ داد.

یک ناهماهنگی واقعی نیز ثبت شد: API افزونه‌ها دو آیتم active (`com_pinoox_cms`
و `com_example_plugin`) برگرداند، اما Health مقدار `registered_extensions=0`
گزارش کرد. علت در source پیدا شد: Health قبل از sync رجیستری installed
extensions اجرا می‌شد. این مرز در `CmsRuntimeServices::healthRunner()` و مسیر
Manager اصلاح و برای آن تست Phase 10 اضافه شد؛ پس از Upgrade باید روی target
دوباره تأیید شود و مقدار مورد انتظار ۲ باشد.

## وضعیت Upgrade کاندیدا

فایل محلی کاندیدا معتبر و آماده‌ی نصب است:

- `releases/NanoPino-0.23.72-phase10-live-baseline.pinx`
- SHA-256: `be54dc1b569ef3a3a800ae4a7828c07a77fc6f139e78aaaa2acf02f5595f4c37`

Installer واقعی Manager باز شد و مرحله‌ی انتخاب بسته آغاز شد، اما File Chooser
مرورگر در همین نوبت قطع شد؛ بنابراین فایل آپلود نشد، هیچ نصب/Update/حذف یا
تغییر داده‌ای روی target انجام نشد و نسخه‌ی target همچنان 0.23.68 باقی است.

## اصلاحات source این فاز

- رجیستری افزونه‌های نصب‌شده پیش از binding شدن Health sync می‌شود.
- تست قراردادی `live-centers-r10.test.js` تضمین می‌کند Health و Manager از
  همان registry استفاده کنند.
- مشخصات و شواهد target در
  `resources/release/live-test-environment-v1.json` ثبت شد؛ هیچ credential،
  token یا نام پایگاه‌داده در این evidence ذخیره نشده است.

## گیت‌های باقی‌مانده

1. انتقال امن PINX `0.23.72` به Installer و اجرای native update.
2. تأیید پیام موفقیت، نسخه‌ی `0.23.72`، حفظ route `/qwe` و حفظ داده‌ها.
3. Reload و تأیید Health، مخصوصاً `registered_extensions=2`.
4. Sweep مرورگری dashboard، Content، Builder، Public site و مسیرهای System.
5. اجرای signed PINX lifecycle و rollback؛ این مورد بدون trust chain هنوز
   قابل ادعا نیست.

این سند «baseline زنده» را ثبت می‌کند و به‌تنهایی ادعای Stable یا production
ready بودن محصول نیست.

## Probe قابل تکرار

برای بررسی مرز عمومی/خصوصی بدون ارسال credential:

```bash
bash tools/live/verify-nanopino-target.sh
```

این probe باید Manager و Public site را `200`، مسیرهای خصوصی بدون نشست را
`403` و هدرهای امنیتی Public site را پیدا کند. تأیید APIهای authenticated و
نسخه‌ی نصب‌شده همچنان نیازمند نشست مدیریتی است.
