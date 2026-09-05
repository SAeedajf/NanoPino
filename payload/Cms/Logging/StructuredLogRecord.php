<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Logging;
final readonly class StructuredLogRecord {
 /** @param array<string,mixed> $context */
 public function __construct(
  public float $timestamp, public LogLevel $level, public string $message,
  public string $channel='cms', public ?string $correlationId=null, public array $context=[]
 ) {
  if($message===''||strlen($message)>8192||preg_match('/[\r\n]/',$channel)||$channel==='') throw new \InvalidArgumentException('Invalid log record.');
 }
 /** @return array<string,mixed> */
 public function toArray():array {
  return ['timestamp'=>$this->timestamp,'level'=>$this->level->value,'channel'=>$this->channel,'message'=>$this->message,'correlation_id'=>$this->correlationId,'context'=>$this->context];
 }
}
