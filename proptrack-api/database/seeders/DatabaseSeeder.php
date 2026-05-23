<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        // 1. Seed standard users
        // Admin User
        $admin = User::create([
            'name' => 'PropTrack Admin',
            'email' => 'admin@proptrack.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ]);
        $admin->assignRole('admin');

        // Property Owner User
        $owner = User::create([
            'name' => 'Property Owner',
            'email' => 'owner@proptrack.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ]);
        $owner->assignRole('owner');

        // Agent User
        $agent = User::create([
            'name' => 'Support Agent',
            'email' => 'agent@proptrack.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ]);
        $agent->assignRole('agent');

        // Tenant User 1
        $tenantUser1 = User::create([
            'name' => 'Tenant Resident',
            'email' => 'tenant@proptrack.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ]);
        $tenantUser1->assignRole('tenant');

        // Tenant User 2
        $tenantUser2 = User::create([
            'name' => 'Budi Santoso',
            'email' => 'tenant2@proptrack.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ]);
        $tenantUser2->assignRole('tenant');

        // Tenant User 3
        $tenantUser3 = User::create([
            'name' => 'Dewi Lestari',
            'email' => 'tenant3@proptrack.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ]);
        $tenantUser3->assignRole('tenant');

        // 2. Seed Tenant Profiles (in tenants table)
        $tenantProfile1 = Tenant::create([
            'name' => 'Tenant Resident',
            'email' => 'tenant@proptrack.com',
            'phone' => '081234567890',
            'id_card_number' => '3171123456789001',
            'emergency_contact_name' => 'Siti Aminah',
            'emergency_contact_phone' => '081234567899',
        ]);

        $tenantProfile2 = Tenant::create([
            'name' => 'Budi Santoso',
            'email' => 'tenant2@proptrack.com',
            'phone' => '081234567892',
            'id_card_number' => '3171123456789002',
            'emergency_contact_name' => 'Hari Santoso',
            'emergency_contact_phone' => '081234567898',
        ]);

        $tenantProfile3 = Tenant::create([
            'name' => 'Dewi Lestari',
            'email' => 'tenant3@proptrack.com',
            'phone' => '081234567893',
            'id_card_number' => '3171876543210987',
            'emergency_contact_name' => 'Hari Lestari',
            'emergency_contact_phone' => '081234567897',
        ]);

        // 3. Seed Properties
        $property1 = Property::create([
            'owner_id' => $owner->id,
            'name' => 'Griya Indah Menteng',
            'address' => 'Jl. Menteng Raya No. 42, Jakarta Pusat',
            'type' => 'apartment',
            'status' => 'occupied',
            'latitude' => -6.184722,
            'longitude' => 106.833611,
            'description' => 'Luxurious 2-bedroom apartment with skyline views in the heart of Menteng, fully furnished.',
            'monthly_price' => 12500000,
        ]);

        $property2 = Property::create([
            'owner_id' => $owner->id,
            'name' => 'Kuningan Heights Suite',
            'address' => 'Jl. HR Rasuna Said Blok X-5, Jakarta Selatan',
            'type' => 'apartment',
            'status' => 'occupied',
            'latitude' => -6.223889,
            'longitude' => 106.829444,
            'description' => 'Modern studio apartment walking distance to central business district, swimming pool access.',
            'monthly_price' => 8500000,
        ]);

        $property3 = Property::create([
            'owner_id' => $owner->id,
            'name' => 'Kosan Cozy Kebayoran',
            'address' => 'Jl. Radio Dalam Raya No. 12, Kebayoran Baru, Jakarta Selatan',
            'type' => 'kos',
            'status' => 'available',
            'latitude' => -6.257222,
            'longitude' => 106.796389,
            'description' => 'Cozy premium student room with en-suite bathroom, high-speed Wi-Fi, and AC.',
            'monthly_price' => 2800000,
        ]);

        $property4 = Property::create([
            'owner_id' => $owner->id,
            'name' => 'Ruko Niaga Kemang',
            'address' => 'Jl. Kemang Raya No. 89, Jakarta Selatan',
            'type' => 'ruko',
            'status' => 'maintenance',
            'latitude' => -6.273611,
            'longitude' => 106.815278,
            'description' => '3-story commercial shop house ideal for cafes or design studios, currently undergoing facade painting.',
            'monthly_price' => 22000000,
        ]);

        // 4. Seed Contracts
        $contract1 = Contract::create([
            'tenant_id' => $tenantProfile1->id,
            'property_id' => $property1->id,
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'monthly_rent' => 12500000,
            'deposit_amount' => 25000000,
            'billing_date' => 1,
            'status' => 'active',
        ]);

        $contract2 = Contract::create([
            'tenant_id' => $tenantProfile2->id,
            'property_id' => $property2->id,
            'start_date' => '2025-10-01',
            'end_date' => '2026-09-30',
            'monthly_rent' => 8500000,
            'deposit_amount' => 8500000,
            'billing_date' => 5,
            'status' => 'active',
        ]);

        $contract3 = Contract::create([
            'tenant_id' => $tenantProfile3->id,
            'property_id' => $property3->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'monthly_rent' => 2800000,
            'deposit_amount' => 2800000,
            'billing_date' => 10,
            'status' => 'expired',
        ]);

        // 5. Seed Invoices
        // Contract 1: 1 upcoming unpaid invoice
        Invoice::create([
            'contract_id' => $contract1->id,
            'tenant_id' => $tenantProfile1->id,
            'property_id' => $property1->id,
            'invoice_number' => 'INV-2026-0001',
            'status' => 'unpaid',
            'amount' => 12500000,
            'billing_month' => '2026-06',
            'due_date' => '2026-06-01',
        ]);

        // Contract 2: 1 paid invoice, 1 overdue invoice
        Invoice::create([
            'contract_id' => $contract2->id,
            'tenant_id' => $tenantProfile2->id,
            'property_id' => $property2->id,
            'invoice_number' => 'INV-2026-0002',
            'status' => 'paid',
            'amount' => 8500000,
            'billing_month' => '2026-05',
            'due_date' => '2026-05-05',
            'paid_at' => '2026-05-04 10:15:30',
            'payment_gateway' => 'midtrans',
            'payment_token' => 'snap-token-xyz789',
        ]);

        Invoice::create([
            'contract_id' => $contract2->id,
            'tenant_id' => $tenantProfile2->id,
            'property_id' => $property2->id,
            'invoice_number' => 'INV-2026-0003',
            'status' => 'overdue',
            'amount' => 8500000,
            'billing_month' => '2026-04',
            'due_date' => '2026-04-05',
        ]);

        // 6. Seed Maintenance Tickets & threaded comments
        $ticket1 = Ticket::create([
            'ticket_number' => 'TKT-2026-0001',
            'property_id' => $property1->id,
            'submitted_by_id' => $tenantUser1->id,
            'assigned_to_id' => $agent->id,
            'category' => 'maintenance',
            'priority' => 'high',
            'status' => 'in_progress',
            'title' => 'Leaking kitchen pipe under the sink',
            'description' => 'Water is pooling inside the kitchen cabinet below the sink since yesterday. It is starting to damage the wood and causing a bad odor.',
        ]);

        TicketComment::create([
            'ticket_id' => $ticket1->id,
            'user_id' => $tenantUser1->id,
            'content' => 'Can someone check this ASAP? The cabinet wood is getting completely warped.',
        ]);

        TicketComment::create([
            'ticket_id' => $ticket1->id,
            'user_id' => $agent->id,
            'content' => 'Hello, Resident. We have received your request and assigned a plumber. He will visit today around 2 PM to inspect and repair the kitchen pipe.',
        ]);

        $ticket2 = Ticket::create([
            'ticket_number' => 'TKT-2026-0002',
            'property_id' => $property2->id,
            'submitted_by_id' => $tenantUser2->id,
            'assigned_to_id' => null,
            'category' => 'maintenance',
            'priority' => 'medium',
            'status' => 'open',
            'title' => 'Main bedroom lights flickering',
            'description' => 'The lights in the main bedroom keep flickering whenever the air conditioner starts. This might be a power stability issue.',
        ]);

        // 7. Seed compact notifications feed (Laravel database notification simulation)
        \Illuminate\Support\Facades\DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TicketStatusChangedNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $tenantUser1->id,
            'data' => json_encode([
                'ticket_id' => $ticket1->id,
                'ticket_number' => $ticket1->ticket_number,
                'title' => $ticket1->title,
                'status' => 'in_progress',
                'message' => 'Tiket ' . $ticket1->ticket_number . ' Anda telah diubah statusnya menjadi IN PROGRESS',
            ]),
            'read_at' => null,
            'created_at' => now()->subHours(10),
            'updated_at' => now()->subHours(10),
        ]);
    }
}
