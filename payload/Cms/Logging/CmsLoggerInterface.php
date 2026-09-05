<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Logging;
interface CmsLoggerInterface {
 /** @param array<string,mixed> $context */
 public function log(LogLevel $level,string $message,array $context=[],?CorrelationId $correlationId=null,string $channel='cms'):void;
 /** @return list<StructuredLogRecord> */
 public function tail(int $limit=100):array;
}
