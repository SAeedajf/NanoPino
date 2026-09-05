<?php
declare(strict_types=1);
namespace App\com_pinoox_cms\Cms\Security\Http;
use Pinoox\Portal\Session;
final class PinooxSessionCsrfTokenManager implements CsrfTokenVerifierInterface
{
    private const SESSION_KEY='cms.csrf.v1',TTL=7200;
    public function issue(?int $subjectId):string
    {
        if($subjectId===null||$subjectId<1)throw new \RuntimeException('Authenticated subject is required for CSRF token.');
        Session::start();$row=Session::get(self::SESSION_KEY);$now=time();
        if(is_array($row)&&($row['subject_id']??null)===$subjectId&&is_string($row['token']??null)&&preg_match('/^[a-f0-9]{64}$/',$row['token'])===1&&(int)($row['expires_at']??0)>$now+60)return$row['token'];
        $token=bin2hex(random_bytes(32));Session::set(self::SESSION_KEY,['subject_id'=>$subjectId,'token'=>$token,'issued_at'=>$now,'expires_at'=>$now+self::TTL]);return$token;
    }
    public function verify(?string $token,?int $subjectId):bool
    {
        if($token===null||$subjectId===null||preg_match('/^[a-f0-9]{64}$/',$token)!==1)return false;
        try{Session::start();$row=Session::get(self::SESSION_KEY);}catch(\Throwable){return false;}
        if(!is_array($row)||(int)($row['subject_id']??0)!==$subjectId||(int)($row['expires_at']??0)<=time())return false;
        $expected=$row['token']??null;return is_string($expected)&&hash_equals($expected,$token);
    }
}
