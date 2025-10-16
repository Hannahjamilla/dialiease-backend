<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DialysateOption;
use App\Models\DwellTimeOption;

class TreatmentOptionsSeeder extends Seeder
{
    public function run()
    {
        // Clear existing data
        DialysateOption::truncate();
        DwellTimeOption::truncate();

        // Dialysate options
        DialysateOption::insert([
            [
                'percentage' => 1.5,
                'color' => 'green',
                'description' => '1.5% Dextrose (Low Glucose)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'percentage' => 2.5,
                'color' => 'yellow',
                'description' => '2.5% Dextrose (Medium Glucose)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'percentage' => 4.25,
                'color' => 'red',
                'description' => '4.25% Dextrose (High Glucose)',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Dwell time options
        DwellTimeOption::insert([
            [
                'hours' => 6,
                'description' => '6 hours (4 exchanges per day)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'hours' => 8,
                'description' => '8 hours (3 exchanges per day)',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}