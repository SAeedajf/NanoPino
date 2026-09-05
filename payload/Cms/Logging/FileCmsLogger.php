<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Logging;
use App\com_pinoox_cms\Cms\Security\Secrets\SensitiveDataRedactor;
final readonly class FileCmsLogger implements CmsLoggerInterface {
 public function __construct(
  private string $file,
  private SensitiveDataRedactor $redactor=new SensitiveDataRedactor(),
  private int $maxBytes=10_485_760
 ) {}
 public function log(LogLevel $level,string $message,array $context=[],?CorrelationId $correlationId=null,string $channel='cms'):void {
  $message=$this->safeMessage($message);
  $safe=$this->redactor->redact($context);
  $record=new StructuredLogRecord(microtime(true),$level,$message,$this->safeChannel($channel),$correlationId?->value,is_array($safe)?$safe:[]);
  $dir=dirname($this->file);
  if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir)) throw new \RuntimeException('Unable to create CMS log directory.');
  $h=fopen($this->file,'c+'); if($h===false) throw new \RuntimeException('Unable to open CMS log.');
  try {
   if(!flock($h,LOCK_EX)) throw new \RuntimeException('Unable to lock CMS log.');
   fseek($h,0,SEEK_END);
   fwrite($h,json_encode($record->toArray(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n"); fflush($h);
   $size=ftell($h);
   if(is_int($size)&&$size>$this->maxBytes) {
    rewind($h);$raw=stream_get_contents($h);$keep=is_string($raw)?substr($raw,-(int)($this->maxBytes*.7)):'';
    $nl=strpos($keep,"\n");if($nl!==false)$keep=substr($keep,$nl+1);
    ftruncate($h,0);rewind($h);fwrite($h,$keep);fflush($h);
   }
  } finally {@flock($h,LOCK_UN);fclose($h);}
 }
 public function tail(int $limit=100):array {
  if(!is_file($this->file))return[];$lines=file($this->file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);if(!is_array($lines))return[];
  $rows=[];
  foreach(array_reverse($lines) as $line){
   try{$d=json_decode($line,true,512,JSON_THROW_ON_ERROR);if(!is_array($d))continue;
    $rows[]=new StructuredLogRecord((float)$d['timestamp'],LogLevel::from((string)$d['level']),(string)$d['message'],(string)$d['channel'],isset($d['correlation_id'])&&is_string($d['correlation_id'])?$d['correlation_id']:null,is_array($d['context']??null)?$d['context']:[]);
   }catch(\Throwable){continue;}
   if(count($rows)>=max(1,min(1000,$limit)))break;
  }return$rows;
 }
 private function safeMessage(string $message):string {
  $message=str_replace(["\0","\r"],'',$message);$message=str_replace("\n",' ',$message);
  $redacted=$this->redactor->redact($message);return substr(is_string($redacted)?$redacted:'[REDACTED]',0,8192);
 }
 private function safeChannel(string $channel):string {
  $channel=strtolower(trim($channel));
  return preg_match('/^[a-z][a-z0-9._-]{0,63}$/',$channel)===1?$channel:'cms';
 }
}
