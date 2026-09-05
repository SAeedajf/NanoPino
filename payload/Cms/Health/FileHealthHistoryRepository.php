<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Health;
final readonly class FileHealthHistoryRepository implements HealthHistoryRepositoryInterface {
 public function __construct(private string $file,private int $maxBytes=5_242_880){}
 public function append(HealthStatus $overall,array $results):void{
  $dir=dirname($this->file);if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new \RuntimeException('Unable to create health history directory.');
  $row=['recorded_at'=>microtime(true),'overall'=>$overall->value,'results'=>array_map(static fn(HealthResult $r):array=>$r->toArray(),$results)];
  $h=fopen($this->file,'c+');if($h===false)throw new \RuntimeException('Unable to open health history.');
  try{
   if(!flock($h,LOCK_EX))throw new \RuntimeException('Unable to lock health history.');
   fseek($h,0,SEEK_END);fwrite($h,json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");fflush($h);
   $size=ftell($h);if(is_int($size)&&$size>$this->maxBytes){rewind($h);$raw=stream_get_contents($h);$keep=is_string($raw)?substr($raw,-(int)($this->maxBytes*.7)):'';
    $nl=strpos($keep,"\n");if($nl!==false)$keep=substr($keep,$nl+1);ftruncate($h,0);rewind($h);fwrite($h,$keep);fflush($h);}
  }finally{@flock($h,LOCK_UN);fclose($h);}
 }
 public function recent(int $limit=20):array{
  if(!is_file($this->file))return[];$lines=file($this->file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);if(!is_array($lines))return[];
  $out=[];foreach(array_reverse($lines)as$line){try{$d=json_decode($line,true,512,JSON_THROW_ON_ERROR);if(is_array($d))$out[]=$d;}catch(\Throwable){}
   if(count($out)>=max(1,min(200,$limit)))break;}return$out;
 }
}
