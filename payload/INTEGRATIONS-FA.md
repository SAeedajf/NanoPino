# راهنمای یکپارچه‌سازی NanoPino

پنل CMS فعلی NanoPino با Vue 3، Vite و Luma ساخته شده است. بنابراین Next.js و React را به هسته‌ی پنل اضافه نکرده‌ایم؛ استفاده‌ی هم‌زمان از دو runtime در یک پنل ادمین باعث حجم بیشتر و مسیرهای دوگانه می‌شود. Tailwind و Swiper در بخش‌های دیگر اکوسیستم Pinoox موجود هستند و در صورت ساخت پوسته‌ی عمومی می‌توانند در همان لایه استفاده شوند.

## Analytics و Observability

اتصال‌ها به‌صورت opt-in هستند و تا زمانی که هر دو متغیر زیر فعال نباشند، هیچ event یا خطایی ارسال نمی‌شود:

```dotenv
CMS_TELEMETRY_ENABLED=true
CMS_TELEMETRY_CONSENT=true
CMS_GA4_MEASUREMENT_ID=G-XXXXXXXXXX
CMS_GOOGLE_TAG_MANAGER_ID=GTM-XXXXXXX
CMS_MATOMO_URL=https://analytics.example.com/
CMS_MATOMO_SITE_ID=1
CMS_MATOMO_TAG_MANAGER_ID=abc123
CMS_SENTRY_DSN=https://public-key@example.ingest.sentry.io/123
```

آداپترها از `gtag`، `dataLayer`، `_paq`، `_mtm` و `Sentry` موجود در صفحه استفاده می‌کنند؛ خود پنل بدون رضایت صریح، اسکریپت شخص ثالث تزریق نمی‌کند. آدرس صفحه و context خطا scrub می‌شوند و query string، token و password ارسال نمی‌شوند. هر درخواست API نیز `X-Correlation-ID` دارد تا خطای قابل مشاهده در UI با لاگ سرور قابل تطبیق باشد.

## موارد مخصوص پوسته‌ی عمومی

Open Graph، eNamad، WhatsApp Business Chat، Tapsell و DoubleClick Floodlight به پوسته‌ی عمومی و سایت منتشرشده تعلق دارند، نه پنل مدیریت خصوصی. این موارد برای مرحله‌ی ساخت public theme آماده‌ی اتصال هستند و در admin shell فعلی بارگذاری نشده‌اند.

## Performance

پیش‌نمایش پوسته‌ها با `loading="lazy"`، `decoding="async"`، ابعاد رزروشده و `fetchpriority="low"` بارگذاری می‌شود تا layout shift و فشار شبکه‌ی اولیه کم شود. مسیرهای اصلی نیز با dynamic import در registry تقسیم شده‌اند.
