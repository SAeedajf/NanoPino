<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Logging;
final class NullCmsLogger implements CmsLoggerInterface {
 public function log(LogLevel $level,string $message,array $context=[],?CorrelationId $correlationId=null,string $channel='cms'):void {}
 public function tail(int $limit=100):array { return []; }
}
