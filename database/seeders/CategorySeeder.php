<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        Category::create(['value' => 'Category 1']);
        Category::create(['value' => 'Category 2']);
        Category::create(['value' => 'Category 3']);
    }
}
