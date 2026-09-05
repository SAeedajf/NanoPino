<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Support\OwnerIdentifier;

final readonly class AdminComponentDefinition implements OwnedDefinitionInterface
{
    public function __construct(
        private string $identifier,
        private string $owner,
        public string $moduleUrl,
        public string $exportName='createComponent',
        public array $i18n=[],
    ) {
        new OwnerIdentifier($owner);

        if (str_starts_with($identifier, 'core:') && $owner !== 'cms.core') {
            throw new \InvalidArgumentException('The core:* Admin component namespace is reserved for cms.core.');
        }

        if (preg_match('#^[a-z0-9][a-z0-9._:/-]{1,190}$#',$identifier)!==1) {
            throw new \InvalidArgumentException('Invalid Admin component identifier.');
        }

        $url=str_replace('\\','/',$moduleUrl);
        if (
            $url==='' ||
            !str_starts_with($url,'/') ||
            str_starts_with($url,'//') ||
            str_contains($url,"\0") ||
            in_array('..',explode('/',trim($url,'/')),true) ||
            preg_match('#^[a-z][a-z0-9+.-]*://#i',$url)===1 ||
            !preg_match('/\.m?js(?:\?[A-Za-z0-9._=&%-]+)?$/',$url)
        ) {
            throw new \InvalidArgumentException(
                'Admin component module URL must be a safe same-origin absolute JS path.'
            );
        }

        if (!in_array($exportName,['default','createComponent'],true) && preg_match('/^[A-Za-z_$][A-Za-z0-9_$]{0,100}$/',$exportName)!==1) {
            throw new \InvalidArgumentException('Invalid Admin component export name.');
        }

        $this->validateI18n($i18n);
    }

    public function identifier():string{return$this->identifier;}
    public function owner():string{return$this->owner;}

    /** @return array<string,mixed> */
    public function toArray():array
    {
        return [
            'id'=>$this->identifier,
            'owner'=>$this->owner,
            'moduleUrl'=>$this->moduleUrl,
            'exportName'=>$this->exportName,
            'i18n'=>$this->i18n,
        ];
    }

    /** @param array<string,mixed> $catalogs */
    private function validateI18n(array $catalogs): void
    {
        $encoded=json_encode($catalogs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if ($encoded===false || strlen($encoded)>65536) {
            throw new \InvalidArgumentException('Admin component i18n catalog exceeds the 64 KiB limit.');
        }
        foreach ($catalogs as $locale=>$messages) {
            if (!is_string($locale) || preg_match('/^[A-Za-z]{2,8}(?:[-_][A-Za-z0-9]{2,8})?$/',$locale)!==1 || !is_array($messages)) {
                throw new \InvalidArgumentException('Invalid Admin component i18n locale catalog.');
            }
            $this->validateI18nNode($messages, 0);
        }
    }

    /** @param array<string,mixed> $node */
    private function validateI18nNode(array $node, int $depth): void
    {
        if ($depth>8) throw new \InvalidArgumentException('Admin component i18n catalog nesting is too deep.');
        foreach ($node as $key=>$value) {
            if (!is_string($key) || preg_match('/^[A-Za-z0-9_.-]{1,120}$/',$key)!==1) {
                throw new \InvalidArgumentException('Invalid Admin component i18n message key.');
            }
            if (is_array($value)) { $this->validateI18nNode($value,$depth+1); continue; }
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                throw new \InvalidArgumentException('Admin component i18n values must be scalar translation lines.');
            }
            if (strlen((string)$value)>4000) {
                throw new \InvalidArgumentException('Admin component i18n translation line is too large.');
            }
        }
    }
}
