<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Health;
final readonly class HealthResult {
 /** @param array<string,mixed> $details */
 public function __construct(
  public string $id, public HealthStatus $status, public string $message,
  public float $durationMs, public float $checkedAt, public array $details=[]
 ) {}
 /** @return array<string,mixed> */
 public function toArray():array {return[
  'id'=>$this->id,'status'=>$this->status->value,'message'=>$this->message,
  'duration_ms'=>$this->durationMs,'checked_at'=>$this->checkedAt,'details'=>$this->details
 ];}
}
