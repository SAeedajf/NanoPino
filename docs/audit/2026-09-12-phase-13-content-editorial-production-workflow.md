# Phase 13 — Content و Editorial Production Workflow

## نتیجه

جریان production محتوا در source تکمیل شد. زمان‌بندی دیگر فقط یک timestamp
ذخیره‌شده در UI نیست: Pinoox Scheduler هر دقیقه task انتشار را اجرا می‌کند و
repository فقط رکوردی را که هنوز `scheduled` و واقعاً due است به‌صورت اتمیک
claim و publish می‌کند.

## تغییرات اجرایی

- `ContentRepositoryInterface` و هر دو repository محلی/دیتابیسی متد
  `publishDue()` دارند.
- implementation دیتابیسی از compare-and-set روی `status=scheduled` و
  `scheduled_at<=now` استفاده می‌کند؛ اجرای هم‌زمان دوباره همان رکورد را
  تغییر نمی‌دهد.
- `ContentService::publishDue()` برای هر انتشار خودکار، `Published` Revision و
  `content.scheduled_publish` Audit event ثبت می‌کند.
- `apps/com_pinoox_cms/schedule.php` با نام
  `cms.content.publish-due`، cadence هر دقیقه و `withoutOverlapping` در
  native Pinoox Scheduler ثبت شد.
- registryهای `app.php`، `manifest.json` و canonical service registry به
  سرویس‌های `content-scheduled-publisher` و `editorial-production-workflow`
  مجهز و همگام شدند.

## شواهد محلی

تست قرارداد اختصاصی frontend: **3/3 PASS**؛ suite کامل frontend: **203/203
PASS**؛ suite PHP CMS: **65/65 PASS**.

اجرای واقعی fixture در DevDB:

```bash
bash tools/live/verify-cms-editorial-scheduler.sh
```

نتیجه:

- fixture زمان‌رسیده: `1` مورد publish شد؛
- اجرای تکراری: `0` مورد دوباره publish شد؛
- fixture آینده: همچنان `scheduled` باقی ماند؛
- Revision و Audit در مسیر سرویس production ثبت شدند.

ثبت native Scheduler نیز با دستور زیر بررسی شد:

```bash
php pinoox schedule:list com_pinoox_cms
```

و task `cms.content.publish-due` با `* * * * *` و lock فعال مشاهده شد.

## مرزهای workflow

انتقال‌های draft، pending review، approved، scheduled، published، archived و
trash همچنان از `ContentWorkflow` و capabilityهای جداگانه عبور می‌کنند.
ویرایش محتوای pending/approved تصمیم review را invalid و به draft برمی‌گرداند؛
Revision checksum و Pre-Restore نیز حفظ شده‌اند. انتشار زمان‌بندی‌شده از مسیر
trusted scheduler انجام می‌شود و actor تعاملی جعلی ایجاد نمی‌کند.

## Target boundary

روی `https://test.boxpdf.ir` هیچ fixture، schedule، content mutation یا cron
اجرایی انجام نشد. target در Phase 10 فقط از نظر read-only health/API و در
Phase 11 از نظر private boundary بررسی شده است؛ اجرای authenticated editorial
workflow، cron واقعی، role-separated review/approval و public publish هنوز
**UNVERIFIED** است.

برای بستن این مرز لازم است:

1. target/staging با backup و fixture قابل‌بازگشت آماده شود؛
2. candidate روی همان runtime نصب/update شود؛
3. نقش author، reviewer و publisher با نشست‌های واقعی آزمایش شوند؛
4. scheduler/cron اجرا و status، revision، audit و public URL بررسی شود؛
5. failure/retry و rollback روی staging ثبت شود.

