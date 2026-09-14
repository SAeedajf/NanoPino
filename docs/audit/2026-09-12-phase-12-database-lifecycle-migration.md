# Phase 12 — Database Lifecycle و Migration واقعی

## نتیجه

چرخه‌ی واقعی schema در source checkout با یک DevDB ایزوله اجرا و تأیید شد:
fresh install، update بدون migration جدید، حفظ داده‌ی sentinel و reset/rollback
همگی PASS شدند. در این فاز هیچ migration، update یا تغییر داده‌ای روی
`test.boxpdf.ir` اجرا نشد؛ target به backup تأییدشده و مجوز mutation نیاز دارد.

## اصلاحات source

- binding دیتابیس بسته همچنان `platform` است و در MySQL/MariaDB از `InnoDB`
  استفاده می‌کند؛ override موتور فقط وقتی اعمال می‌شود که `DB_CONNECTION`
  صراحتاً MySQL یا MariaDB باشد. بنابراین DevDB/SQLite محلی به‌اشتباه به
  MySQL driver وابسته نمی‌شود.
- `PinooxInstallabilityProbe` برای SQLite فقط در محیط local/development/test
  و با `PINOOX_TESTING` اجازه‌ی probe می‌دهد. محیط production همچنان
  non-MySQL را با blocker صریح رد می‌کند.
- migrationهای repair idempotent هستند، نام‌های legacy با double-prefix را
  با rename داده‌محور اصلاح می‌کنند و `down()` آن‌ها non-destructive است.
- uninstall از Migrator با transaction استفاده می‌کند و تا زمانی که جدول‌های
  owned باقی مانده باشند، حذف فایل‌های برنامه را متوقف می‌کند.

## اجرای واقعی محلی

اسکریپت قابل تکرار:

```bash
bash tools/live/verify-cms-database-lifecycle.sh
```

خروجی gate:

- اتصال migration: `sqlite` با prefix `cms_` در DevDB ایزوله؛
- fresh install: **PASS**، شامل ۱۶ جدول owned CMS در کنار جدول‌های native
  پلتفرم؛
- no-op update: **PASS** و sentinel با مقدار `{"preserve":true}` حفظ شد؛
- reset/rollback: **PASS** و تعداد جدول‌های owned باقی‌مانده `0` شد؛ جدول‌های
  native پلتفرم حذف نشدند.

تست قرارداد migration نیز **۳/۳ PASS** است. این تست‌ها ترتیب preflight،
canonical schema، repair legacy، foreign key policy، transaction rollback و
uninstall guard را کنترل می‌کنند.

## Target boundary

target زنده‌ی `https://test.boxpdf.ir` در Phase 10 از نظر read-only Health و
اتصال دیتابیس قابل مشاهده بود، اما در این فاز migration روی آن اجرا نشد.
دلیل، نبودن مجوز صریح برای mutation/backup و ناتمام‌بودن مسیر browser session
برای انجام update است. بنابراین target schema parity، privilege validation،
post-migration data counts و live rollback هنوز **UNVERIFIED** هستند.

## Gate

| لایه | وضعیت |
|---|---|
| Source DB binding و installability | Verified |
| Fresh/update/reset در DevDB ایزوله | Verified |
| Uninstall guard | Verified by source contract و lifecycle design |
| Target database migration | Pending / not executed |
| Stable / production-ready | Blocked |

گام‌های لازم برای بستن مرز target:

1. گرفتن و تأیید backup/snapshot و maintenance window؛
2. نصب یا update کنترل‌شده‌ی PINX candidate روی target؛
3. بررسی schema، history، row counts و Health بعد از update؛
4. اجرای rollback فقط روی fixture یا محیط staging قابل‌بازگشت؛
5. ثبت evidence مستقل برای signed trust chain و deployment health.

