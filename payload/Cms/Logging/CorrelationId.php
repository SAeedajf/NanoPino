<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Logging;
final readonly class CorrelationId {
 public function __construct(public string $value) {
  if(preg_match('/^[A-Za-z0-9_-]{12,96}$/',$value)!==1) throw new \InvalidArgumentException('Invalid correlation id.');
 }
 public static function generate():self {
  return new self(rtrim(strtr(base64_encode(random_bytes(18)),'+/','-_'),'='));
 }
 public function __toString():string { return $this->value; }
}
