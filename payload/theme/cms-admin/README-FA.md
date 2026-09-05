# Pinoox CMS Admin Builder R3

این نسخه مشکل `404` شدن Chunkهای Vue/Luma و فونت‌ها در Appهایی مثل `/qwe/` را رفع می‌کند.

## علت

Build قبلی URLهایی شبیه این تولید می‌کرد:

```text
/assets/vendor-vue-....js
/assets/vendor-luma-....js
/assets/Vazir-....woff2
```

بنابراین مرورگر از ریشه دامنه درخواست می‌کرد:

```text
https://example.com/assets/...
```

در حالی که Pinoox App ممکن است زیر مسیر زیر باشد:

```text
https://example.com/qwe/
```

R3 از:

```js
base: './'
```

استفاده می‌کند.

## Pop!_OS

```bash
cd ~/Downloads
unzip -o pinoox-cms-admin-shared-hosting-builder-0.23.1-r3.zip
cd pinoox-cms-admin-builder-r3
chmod +x build-linux.sh
./build-linux.sh
```

در پایان باید ببینی:

```text
SUCCESS R3
```

## سپس

پوشه قدیمی `dist` روی هاست را حذف/rename کن و `dist` جدید را کامل آپلود کن.

مسیر:

```text
/home/ix2ir/test/apps/com_pinoox_cms/theme/cms-admin/dist/
```

سپس Service Worker/Cache مرورگر را پاک کن و Hard Reload بزن.
