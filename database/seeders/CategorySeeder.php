<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('categories')->insert([
            [
                'name' => 'T-shirt',
                'slug' => 't-shirt',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mug',
                'slug' => 'mug',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
