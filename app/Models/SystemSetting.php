<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SystemSetting extends Model {
    protected $primaryKey='key';
    public $incrementing=false;
    protected $keyType='string';
    protected $fillable=['key','value'];
    public static function integer(string $key,int $default): int {
        $value=static::query()->whereKey($key)->value('value');
        return is_numeric($value)?(int)$value:$default;
    }
}
