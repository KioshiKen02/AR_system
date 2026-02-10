<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['app_name' => 'Bilar Breeder Local', 'base_url' => 'bilarbreeder'],
            ['app_name' => 'Bilar Breeder', 'base_url' => 'bilarbreeder'],
            ['app_name' => 'Gp Jagna', 'base_url' => 'gpjagna'],
            ['app_name' => 'Ice Plant', 'base_url' => 'iceplant'],
            ['app_name' => 'Peanut Kisses', 'base_url' => 'peanutkisses'],
            ['app_name' => 'Cortes Poultry', 'base_url' => 'cortespoultry'],
            ['app_name' => 'Cortes Piggery', 'base_url' => 'cortespiggery'],
            ['app_name' => 'Canhayupon Breeder', 'base_url' => 'canhayuponbreeder'],
            ['app_name' => 'Bilar Hatchery', 'base_url' => 'bilarhatchery'],
            ['app_name' => 'Lapsaon Breeder', 'base_url' => 'lapsaonbreeder'],
            ['app_name' => 'Rizal Breeder', 'base_url' => 'rizalbreeder'],
            ['app_name' => 'Feedmill', 'base_url' => 'feedmill'],
            ['app_name' => 'Growout', 'base_url' => 'growout'],
            ['app_name' => 'Cortes Fertilizer', 'base_url' => 'mficortesfertilizer'],
            ['app_name' => 'Ubay Fertilizer', 'base_url' => 'mfiubayfertilizer'],
            ['app_name' => 'Piggery Untaga', 'base_url' => 'piggeryuntaga'],
            ['app_name' => 'Demo Farm', 'base_url' => 'demofarm'],
            ['app_name' => 'Dressing Plant', 'base_url' => 'dressingplant'],
            ['app_name' => 'Farmers Market', 'base_url' => 'farmersmarket'],
            ['app_name' => 'Meat Processing', 'base_url' => 'meatprocessing'],
            ['app_name' => 'Rendering', 'base_url' => 'rendering'],
        ];

        foreach ($settings as $setting) {
            AppSetting::updateOrCreate(
                ['app_name' => $setting['app_name']],
                ['base_url' => $setting['base_url']]
            );
        }
    }
}
