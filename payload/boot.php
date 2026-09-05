<?php
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeBinder;
use Pinoox\Component\AppEvent\AppRegister;
return static function(AppRegister $register):void{(new CmsRuntimeBinder())->bind($register);};
