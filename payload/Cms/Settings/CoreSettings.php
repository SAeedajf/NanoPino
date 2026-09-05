<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Settings;

use App\com_pinoox_cms\Cms\Authorization\ScopeType;

final class CoreSettings
{
    public const OWNER = 'cms.core';

    public static function register(SettingsRegistry $registry): void
    {
        foreach ([
            new SettingDefinition(
                'site.name', self::OWNER, SettingType::String, 'Pinoox Site',
                [ScopeType::Global, ScopeType::Site],
                group: 'general', label: 'نام سایت',
                validator: static fn (mixed $value): bool|string =>
                    self::length((string)$value) <= 190 ?: 'Site name may not exceed 190 characters.',
                ui: self::ui('text', 10, 'نام اصلی سایت که در هویت مدیریتی و خروجی‌های سایت استفاده می‌شود.', ['site','identity']),
            ),
            new SettingDefinition(
                'site.tagline', self::OWNER, SettingType::String, '',
                [ScopeType::Global, ScopeType::Site],
                group: 'general', label: 'معرفی کوتاه',
                validator: static fn (mixed $value): bool|string =>
                    self::length((string)$value) <= 500 ?: 'Tagline may not exceed 500 characters.',
                ui: self::ui('textarea', 20, 'توضیح کوتاه درباره سایت؛ حداکثر ۵۰۰ نویسه.', ['tagline','description'], ['rows'=>3]),
            ),
            new SettingDefinition(
                'site.locale', self::OWNER, SettingType::String, 'fa',
                [ScopeType::Global, ScopeType::Site, ScopeType::User],
                group: 'localization', label: 'زبان پیش‌فرض',
                validator: static fn (mixed $value): bool|string =>
                    preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', (string)$value) === 1 ?: 'Invalid locale.',
                ui: self::ui('select', 10, 'زبان پیش‌فرض CMS و محتوای جدید.', ['language','locale'], [
                    'options'=>[
                        ['value'=>'fa','label'=>'فارسی'],
                        ['value'=>'en','label'=>'English'],
                        ['value'=>'ar','label'=>'العربية'],
                        ['value'=>'de','label'=>'Deutsch'],
                    ],
                ]),
            ),
            new SettingDefinition(
                'site.timezone', self::OWNER, SettingType::String, 'UTC',
                [ScopeType::Global, ScopeType::Site, ScopeType::User],
                group: 'localization', label: 'منطقه زمانی',
                validator: static fn (mixed $value): bool|string =>
                    in_array((string)$value, timezone_identifiers_list(), true) ?: 'Invalid timezone.',
                ui: self::ui('timezone', 20, 'برای زمان‌بندی انتشار، گزارش‌ها و نمایش تاریخ استفاده می‌شود.', ['timezone','time']),
            ),
            new SettingDefinition(
                'site.date_format', self::OWNER, SettingType::String, 'Y/m/d',
                [ScopeType::Global, ScopeType::Site, ScopeType::User],
                group: 'localization', label: 'فرمت تاریخ',
                validator: static fn (mixed $value): bool|string =>
                    in_array((string)$value, ['Y/m/d','Y-m-d','d/m/Y','d M Y'], true) ?: 'Unsupported date format.',
                ui: self::ui('select', 30, 'فرمت پایه نمایش تاریخ در رابط مدیریتی.', ['date','format'], [
                    'options'=>[
                        ['value'=>'Y/m/d','label'=>'۱۴۰۵/۰۶/۱۲ · Y/m/d'],
                        ['value'=>'Y-m-d','label'=>'2026-09-03 · Y-m-d'],
                        ['value'=>'d/m/Y','label'=>'03/09/2026 · d/m/Y'],
                        ['value'=>'d M Y','label'=>'03 Sep 2026 · d M Y'],
                    ],
                ]),
            ),
            new SettingDefinition(
                'site.time_format', self::OWNER, SettingType::String, 'H:i',
                [ScopeType::Global, ScopeType::Site, ScopeType::User],
                group: 'localization', label: 'فرمت ساعت',
                validator: static fn (mixed $value): bool|string =>
                    in_array((string)$value, ['H:i','H:i:s','h:i A'], true) ?: 'Unsupported time format.',
                ui: self::ui('select', 40, 'فرمت پایه نمایش ساعت در رابط مدیریتی.', ['time','format'], [
                    'options'=>[
                        ['value'=>'H:i','label'=>'24 ساعته · 23:45'],
                        ['value'=>'H:i:s','label'=>'24 ساعته با ثانیه · 23:45:12'],
                        ['value'=>'h:i A','label'=>'12 ساعته · 11:45 PM'],
                    ],
                ]),
            ),
            new SettingDefinition(
                'admin.items_per_page', self::OWNER, SettingType::Integer, 20,
                [ScopeType::Global, ScopeType::User],
                group: 'admin', label: 'تعداد ردیف در هر صفحه',
                validator: static fn (mixed $value): bool|string =>
                    ((int)$value >= 10 && (int)$value <= 200) ?: 'Items per page must be between 10 and 200.',
                ui: self::ui('number', 10, 'تعداد پیش‌فرض آیتم‌ها در فهرست‌های مدیریتی.', ['pagination','rows'], ['min'=>10,'max'=>200,'step'=>10,'prefer_scope'=>'user']),
            ),
            new SettingDefinition(
                'diagnostics.logs_page_size', self::OWNER, SettingType::Integer, 50,
                [ScopeType::Global, ScopeType::User],
                readPermission: 'system.logs.view', writePermission: 'settings.manage',
                group: 'diagnostics', label: 'تعداد گزارش در هر بار نمایش',
                validator: static fn (mixed $value): bool|string =>
                    ((int)$value >= 20 && (int)$value <= 200) ?: 'Logs page size must be between 20 and 200.',
                ui: self::ui('number', 10, 'تعداد رکوردهای Structured Log که در مرکز سلامت بارگیری می‌شود.', ['logs','diagnostics'], ['min'=>20,'max'=>200,'step'=>10,'prefer_scope'=>'user']),
            ),
            new SettingDefinition(
                'diagnostics.active_error_window_minutes', self::OWNER, SettingType::Integer, 15,
                [ScopeType::Global, ScopeType::User],
                readPermission: 'system.logs.view', writePermission: 'settings.manage',
                group: 'diagnostics', label: 'بازه خطای فعال',
                validator: static fn (mixed $value): bool|string =>
                    ((int)$value >= 5 && (int)$value <= 1440) ?: 'Active error window must be between 5 and 1440 minutes.',
                ui: self::ui('number', 20, 'خطاهای جدیدتر از این بازه در گزارش سیستم «فعال/اخیر» محسوب می‌شوند.', ['active','error','window'], ['min'=>5,'max'=>1440,'step'=>5,'suffix'=>'دقیقه','prefer_scope'=>'user']),
            ),
            new SettingDefinition(
                'diagnostics.show_technical_details', self::OWNER, SettingType::Boolean, false,
                [ScopeType::Global, ScopeType::User],
                readPermission: 'system.logs.view', writePermission: 'settings.manage',
                group: 'diagnostics', label: 'نمایش جزئیات فنی به‌صورت پیش‌فرض',
                ui: self::ui('boolean', 30, 'Exception class، فایل، خط و Diagnostic Context را در نمای Log باز نگه می‌دارد. Secrets همچنان Redact می‌شوند.', ['technical','details'], ['prefer_scope'=>'user']),
            ),
            new SettingDefinition(
                'api.default_page_size', self::OWNER, SettingType::Integer, 50,
                [ScopeType::Global],
                group: 'api', label: 'Page Size پیش‌فرض API',
                validator: static fn (mixed $value): bool|string =>
                    ((int)$value >= 10 && (int)$value <= 100) ?: 'API default page size must be between 10 and 100.',
                ui: self::ui('number', 10, 'مقدار پیشنهادی برای pagination در APIهای CMS. سقف امنیتی endpointها همچنان مستقل باقی می‌ماند.', ['api','pagination'], ['min'=>10,'max'=>100,'step'=>10]),
            ),
            new SettingDefinition(
                'theme.design.overrides', self::OWNER, SettingType::Json, [],
                [ScopeType::Site, ScopeType::Theme],
                readPermission: 'themes.read', writePermission: 'themes.customize',
                group: 'appearance', label: 'Global Design Overrides',
                validator: static function (mixed $value): bool|string {
                    if (!is_array($value)) return 'Global Design Overrides must be an object.';
                    try {
                        (new \App\com_pinoox_cms\Cms\Theme\Design\DesignSchemaValidator())
                            ->validate(['schema' => 1, 'tokens' => $value]);
                        $json = json_encode($value, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                        return strlen($json) <= 131072 ?: 'Global Design Overrides may not exceed 128 KiB.';
                    } catch (\Throwable $error) { return $error->getMessage(); }
                },
                ui: self::ui('design-tokens', 10, 'این مقدار از Appearance/Global Design مدیریت می‌شود؛ ویرایش JSON فقط در حالت پیشرفته است.', ['theme','design'], ['advanced'=>true]),
            ),
        ] as $definition) {
            $registry->register($definition);
        }
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    /** @param list<string> $keywords @param array<string,mixed> $extra */
    private static function ui(string $component, int $order, string $description, array $keywords = [], array $extra = []): array
    {
        return ['component'=>$component,'order'=>$order,'description'=>$description,'keywords'=>$keywords] + $extra;
    }
}
