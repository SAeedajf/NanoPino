<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Logging;
enum LogLevel:string {
 case Debug='debug'; case Info='info'; case Notice='notice'; case Warning='warning';
 case Error='error'; case Critical='critical'; case Alert='alert'; case Emergency='emergency';
}
