<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Authorization;
final readonly class SingleSiteScopeGuard implements ScopeGuardInterface
{
    public function __construct(private int $siteId=1,private ?int $currentSubjectId=null){if($siteId<1)throw new \InvalidArgumentException('siteId must be positive.');}
    public function allows(AuthorizationRequest $request):bool
    {
        return match($request->scopeType){
            ScopeType::Global=>true,
            ScopeType::Site=>(string)$request->scopeId===(string)$this->siteId,
            ScopeType::User=>$this->currentSubjectId!==null&&(string)$request->scopeId===(string)$this->currentSubjectId,
            ScopeType::Extension=>$this->valid($request->scopeId,'/^com_[a-z0-9][a-z0-9_]{1,126}$/'),
            ScopeType::Theme=>$this->valid($request->scopeId,'/^[a-z0-9][a-z0-9._-]{1,126}$/'),
        };
    }
    private function valid(string|int|null $value,string $pattern):bool{return is_string($value)&&preg_match($pattern,$value)===1;}
}
