<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Contact;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contacts = [
            ['name' => 'Sarah Johnson', 'role' => 'Media Manager', 'org' => 'FIFA Media', 'phone' => '+33 6 12 34 56 78', 'on_shift' => true, 'disabled' => false],
            ['name' => 'Michael Chen', 'role' => 'Security Director', 'org' => 'Venue Security', 'phone' => '+33 6 23 45 67 89', 'on_shift' => true, 'disabled' => false],
            ['name' => 'Emma Williams', 'role' => 'Protocol Officer', 'org' => 'FIFA Protocol', 'phone' => '+33 6 34 56 78 90', 'on_shift' => false, 'disabled' => false],
            ['name' => 'David Martinez', 'role' => 'Operations Lead', 'org' => 'Stadium Operations', 'phone' => '+33 6 45 67 89 01', 'on_shift' => true, 'disabled' => false],
            ['name' => 'Lisa Anderson', 'role' => 'Medical Coordinator', 'org' => 'Medical Services', 'phone' => '+33 6 56 78 90 12', 'on_shift' => true, 'disabled' => false],
            ['name' => 'James Wilson', 'role' => 'Transport Manager', 'org' => 'Logistics Team', 'phone' => '+33 6 67 89 01 23', 'on_shift' => false, 'disabled' => false],
            ['name' => 'Sophie Dubois', 'role' => 'Liaison Officer', 'org' => 'FIFA Liaison', 'phone' => '+33 6 78 90 12 34', 'on_shift' => true, 'disabled' => false],
            ['name' => 'Robert Taylor', 'role' => 'Technical Director', 'org' => 'Broadcast Services', 'phone' => '+33 6 89 01 23 45', 'on_shift' => false, 'disabled' => false],
            ['name' => 'Maria Garcia', 'role' => 'VIP Coordinator', 'org' => 'Hospitality', 'phone' => '+33 6 90 12 34 56', 'on_shift' => true, 'disabled' => false],
            ['name' => 'Thomas Brown', 'role' => 'Press Officer', 'org' => 'Media Relations', 'phone' => '+33 6 01 23 45 67', 'on_shift' => false, 'disabled' => false],
            ['name' => 'Anna Schmidt', 'role' => 'Accreditation Manager', 'org' => 'FIFA Accreditation', 'phone' => '+33 6 12 34 56 79', 'on_shift' => true, 'disabled' => false],
            ['name' => 'Carlos Rodriguez', 'role' => 'Safety Manager', 'org' => 'Safety & Security', 'phone' => '+33 6 23 45 67 80', 'on_shift' => false, 'disabled' => false],
        ];

        foreach ($contacts as $contact) {
            Contact::create($contact);
        }
    }
}
