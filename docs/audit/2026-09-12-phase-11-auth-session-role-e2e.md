# Phase 11 — Auth، Session و Role E2E

## نتیجه

قرارداد فاز ۱۱ در source checkout یکپارچه و قابل آزمون شد. چهار لایه‌ی
هویت، نشست/CSRF، نقش/قابلیت و route metadata با تست اختصاصی بررسی شدند و
**۴/۴ تست PASS** است.

این نتیجه به‌معنای تکمیل E2E احراز هویت روی target نیست؛ چون نشست مدیریتی
قبلی در مرورگر زنده قابل استفاده‌ی مجدد نشد و transport انتخاب فایل مرورگر
قطع شده است. هیچ user، role، session یا داده‌ای تغییر نکرد.

## Source contract

- `RuntimeActor` فقط شناسه‌ی مثبت را از native `Auth` قبول می‌کند و خطای boot
  را fail-closed به anonymous تبدیل می‌کند.
- `AdminController`، User API و provider سمت Vue از یک identity envelope و
  effective abilities استفاده می‌کنند؛ UI برای anonymous و identity ناقص
  affordance privileged را نشان نمی‌دهد.
- `PinooxSessionCsrfTokenManager` توکن ۶۴ رقمی، subject-bound و TTL دو ساعته
  صادر می‌کند و با `hash_equals` اعتبارسنجی می‌کند.
- `CmsRequestIntegrityFlow` و `RequestIntegrityPolicy` برای mutation، نشست،
  same-origin، CSRF معتبر و content type مجاز را الزام می‌کنند؛ mutation
  ناشناس رد می‌شود.
- همه‌ی routeهای mutation در manifest، capability صریح و `cms_csrf` دارند؛
  نقش‌های `cms_editor`، `cms_author`، `cms_media_manager` و `cms_site_manager`
  فقط provision می‌شوند و auto-assignment انجام نمی‌شود.
- `AuthorizationManager` ابتدا registry capability، سپس native Access، scope
  سایت/کاربر و policy را بررسی می‌کند.

## Target live boundary

با درخواست‌های بدون credential به `https://test.boxpdf.ir`:

| مسیر | نتیجه‌ی مشاهده‌شده |
|---|---|
| `/qwe` با Accept JSON | HTTP 403، `ACCESS_DENIED`، permission=`cms.admin` |
| `/qwe/api/v1/cms/system/health` | HTTP 403، `ACCESS_DENIED`، permission=`system.health.view` |
| `/qwe/api/v1/cms/users` | HTTP 403، `ACCESS_DENIED`، permission=`users.read` |

مرز خصوصی از نظر عدم دسترسی برقرار است، اما mapping دقیق anonymous به
`401 AUTHENTICATION_REQUIRED` در target تأیید نشد. Source فعلی این تفکیک را
الزام می‌کند؛ target نسخه‌ی قدیمی `0.23.68` دارد و پاسخ خام HTML در مسیر
dashboard نیز هدر `X-CMS-Auth-State` مورد انتظار source را نشان نداد. بنابراین
علت دقیق (اختلاف نسخه، binding قدیمی یا وضعیت auth سمت hosting) هنوز
**UNVERIFIED** است و نباید آن را به‌عنوان bug قطعی source اعلام کرد.

در Phase 10، Manager و dashboard با نشست معتبر و APIهای health/extensions/content
با HTTP 200 دیده شده بودند. در این فاز role switching، revoke session و mutation
با CSRF معتبر روی نشست واقعی تکرار نشد، چون browser transport فعلی قابل
بازیابی نبود.

## آزمون و gate

تست اختصاصی:

```bash
node --test tests/auth-session-role-e2e.test.js
```

Machine-readable evidence در
`resources/release/auth-session-role-e2e-v1.json` ثبت شده است. این سند
credential، cookie، token، نام database یا داده‌ی حساس ذخیره نمی‌کند.

## باقی‌مانده‌ی لازم برای بستن فاز

1. بازیابی نشست معتبر Manager یا یک حساب تست صریح روی target، بدون حدس‌زدن
   credential.
2. تکرار E2E با دو نقش کم‌اختیار/پُراختیار: boot identity، users list،
   capability visibility، role assignment و denial واقعی.
3. گرفتن CSRF از boot، اجرای یک mutation برگشت‌پذیر روی fixture، سپس تست
   same-origin و CSRF نامعتبر.
4. revoke session برای fixture و تأیید invalid شدن نشست هدف.
5. مشخص‌کردن علت اختلاف 403/401 روی نسخه‌ی target و سپس update کنترل‌شده به
   candidate `0.23.72`.

تا انجام این موارد، فاز از نظر source **Verified** و از نظر authenticated live
E2E **Pending** است؛ Stable یا production-ready اعلام نمی‌شود.
