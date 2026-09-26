<?php
namespace App\Enums;
enum WorkType: string {
    case ASSETS='ASSETS'; case SOCIAL='SOCIAL'; case CUSTOM='CUSTOM';
    public function codePrefix(): string { return match($this){self::ASSETS=>'AW',self::SOCIAL=>'SA',self::CUSTOM=>'CJ'}; }
    public function label(): string { return match($this){self::ASSETS=>'Team Assets',self::SOCIAL=>'Team Social',self::CUSTOM=>'Custom Job'}; }
}
