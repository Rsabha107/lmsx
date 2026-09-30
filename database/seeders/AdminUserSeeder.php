<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * SSO-only admins: the random password is never shared, so Microsoft login is the only way in.
     */
    public function run(): void
    {
        $admins = [
            'r.sabha@sc.qa'      => 'R Sabha',
            'a.shorukove@sc.qa'  => 'Aida Shorukove',
            'c.malusel@sc.qa'    => 'Carmen Malusel',
            'c.macedo@sc.qa'     => 'Cristiana Macedo',
            's.farooqui@sc.qa'   => 'Shahanwaz Farooqui',
            'g.raqib@sc.qa'      => 'Gzanfer Raqib',
        ];

        foreach ($admins as $email => $name) {
            User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Str::random(40)],
            )->assignRole('admin');
        }
    }
}
