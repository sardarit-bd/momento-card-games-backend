<?php

namespace Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('products')->insert([
            [
                'name' => 'Premium White T-shirt',
                'slug' => Str::slug('Premium White T-shirt'),
                'image' => 'images/products/tshirt1.png',
                'short_description' => 'High quality cotton T-shirt',
                'type' => 'simple',
                'description' => 'A comfortable premium cotton T-shirt.',
                'price' => 20.00,
                'offer_price' => 15.00,
                'status' => 'active',
                'category_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Custom Printed Mug',
                'slug' => Str::slug('Custom Printed Mug'),
                'image' => 'images/products/mug1.png',
                'short_description' => 'Customizable ceramic mug',
                'type' => 'customizable',
                'description' => 'Upload your design and get a personalized mug.',
                'price' => 12.00,
                'offer_price' => 9.00,
                'status' => 'active',
                'category_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
