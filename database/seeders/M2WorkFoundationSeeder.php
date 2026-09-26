<?php
namespace Database\Seeders;
use App\Models\SystemSetting;
use App\Models\CodeCounter;
use Illuminate\Database\Seeder;
class M2WorkFoundationSeeder extends Seeder {
    public function run(): void {
        foreach(['AW','SA','CJ'] as $key){
            CodeCounter::query()->firstOrCreate(['key'=>$key],['next_value'=>1]);
        }

        foreach([
            'workload_warning_threshold'=>'8',
            'poor_performance_threshold'=>'20',
            'timezone'=>'Asia/Dhaka',
            'max_qc_negative_per_review'=>'5',
            'max_qc_bonus_per_review'=>'5',
        ] as $key=>$value){
            SystemSetting::query()->firstOrCreate(['key'=>$key],['value'=>$value]);
        }
    }
}
