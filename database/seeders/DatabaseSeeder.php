<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Illustration;
use App\Models\User;
use App\Models\Illustrator;
use App\Models\Admin;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Category
        $categories = [
            ['name' => 'Realism'],
            ['name' => 'Abstract'],
            ['name' => 'Cartoon'],
            ['name' => 'Pop Art'],
            ['name' => 'Minimalism'],
        ];
        Category::insert($categories);

        // User
        $users = [
            [
                'name' => 'Customer',
                'email' => 'customer@gmail.com',
                'password' => Hash::make('password'),
                'bio' => 'Art lover',
                'profile_picture' => 'assets/customer.png',
            ],
            [
                'name' => 'Illustrator',
                'email' => 'illustrator@gmail.com',
                'password' => Hash::make('password'),
                'bio' => 'Passionate artist',
                'profile_picture' => 'assets/illustrator2.png',
            ],
        ];
        User::insert($users);

        // Customer
        $customers = [
            [
                'user_id' => 1,
            ],
        ];
        Customer::insert($customers);

        // Illustrators
        $illustrators = [
            [
                'user_id' => 2,
                'experience_years' => 5,
                'portofolio_link' => 'https://canva.com',
                'is_open_commision' => 1,
            ],
        ];
        Illustrator::insert($illustrators);

        // Illustrations
        $illustrations = [
            [
                'title' => 'Realism #1',
                'description' => 'This is realism #1 description',
                'price' => 1100000,
                'image_path' => 'assets/art/realism1.jpg',
                'date_issued' => '2024-01-03',
                'illustrator_id' => 1,
                'category_id' => 1,
            ],
            [
                'title' => 'Realism #2',
                'description' => 'This is realism #2 description',
                'price' => 1200000,
                'image_path' => 'assets/art/realism2.jpg',
                'date_issued' => '2024-01-07',
                'illustrator_id' => 1,
                'category_id' => 1,
            ],
            [
                'title' => 'Abstract #1',
                'description' => 'This is abstract #1 description',
                'price' => 2100000,
                'image_path' => 'assets/art/abstract1.jpg',
                'date_issued' => '2024-02-04',
                'illustrator_id' => 1,
                'category_id' => 2,
            ],
            [
                'title' => 'Cartoon #1',
                'description' => 'This is cartoon #1 description',
                'price' => 3100000,
                'image_path' => 'assets/art/cartoon1.png',
                'date_issued' => '2024-03-03',
                'illustrator_id' => 1,
                'category_id' => 3,
            ],
            [
                'title' => 'Pop Art #1',
                'description' => 'This is pop art #1 description',
                'price' => 4100000,
                'image_path' => 'assets/art/popart1.jpg',
                'date_issued' => '2024-04-16',
                'illustrator_id' => 1,
                'category_id' => 4,
            ],
            [
                'title' => 'Minimalist #1',
                'description' => 'This is minimalist #1 description',
                'price' => 5100000,
                'image_path' => 'assets/art/minimalist1.jpg',
                'date_issued' => '2024-05-25',
                'illustrator_id' => 1,
                'category_id' => 5,
            ],
            [
                'title' => 'Minimalist #2',
                'description' => 'This is minimalist #2 description',
                'price' => 5200000,
                'image_path' => 'assets/art/minimalist2.jpg',
                'date_issued' => '2024-05-26',
                'illustrator_id' => 1,
                'category_id' => 5,
            ],
        ];
        Illustration::insert($illustrations);

        // Admins
        $admins = [
            [
                'email' => 'c14230082@john.petra.ac.id',
            ],
            [
                'email' => 'c14230151@john.petra.ac.id',
            ],
            [
                'email' => 'c14230189@john.petra.ac.id',
            ],
            [
                'email' => 'c14230079@john.petra.ac.id',
            ],
            [
                'email' => 'c14230211@john.petra.ac.id',
            ],
        ];
        Admin::insert($admins);
    }
}
