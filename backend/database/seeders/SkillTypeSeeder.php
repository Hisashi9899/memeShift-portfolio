<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SkillType;

class SkillTypeSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            ["name" => "\u30ea\u30fc\u30c0\u30fc", "category" => "management", "sort_order" => 1],
            ["name" => "\u8a18\u9332", "category" => "documentation", "sort_order" => 2],
            ["name" => "\u51e6\u7f6e", "category" => "medical", "sort_order" => 3],
            ["name" => "\u53d7\u4ed8", "category" => "administration", "sort_order" => 4],
            ["name" => "\u6307\u5c0e", "category" => "education", "sort_order" => 5],
        ];
        foreach ($skills as $s) {
            SkillType::firstOrCreate(["name" => $s["name"]], $s);
        }
    }
}
