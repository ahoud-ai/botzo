<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SetupDemoOrgSeeder extends Seeder
{
    private int $orgId    = 2;
    private int $userId   = 3;
    private int $planId;

    public function run(): void
    {
        $now = now();
        $this->planId = DB::table('subscription_plans')
            ->where('name', 'الأعمال')->where('period', 'monthly')->value('id');

        // =============================================
        // 1. SUBSCRIPTION
        // =============================================
        $this->command->info('1. إنشاء الاشتراك...');

        DB::table('subscriptions')->insert([
            'uuid'            => (string) Str::uuid(),
            'organization_id' => $this->orgId,
            'plan_id'         => $this->planId,
            'payment_details' => json_encode(['method' => 'demo', 'note' => 'Demo account']),
            'start_date'      => $now,
            'valid_until'     => $now->copy()->addYear(),
            'status'          => 'active',
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        DB::table('users')->where('id', $this->userId)->update([
            'plan_id'    => $this->planId,
            'plan'       => 'الأعمال',
            'will_expire'=> $now->copy()->addYear(),
        ]);

        $this->command->line('  ✓ اشتراك نشط حتى ' . $now->copy()->addYear()->toDateString());

        // =============================================
        // 2. WHATSAPP CONFIGURATION
        // =============================================
        $this->command->info('2. تهيئة الواتساب...');

        $org = DB::table('organizations')->where('id', $this->orgId)->first();
        $meta = json_decode($org->metadata, true) ?? [];

        $meta['whatsapp'] = [
            'phone_number'            => '+966550000001',
            'phone_number_id'         => '100234567890123',
            'waba_id'                 => '200234567890123',
            'app_id'                  => '300234567890123',
            'access_token_encrypted'  => base64_encode('demo_access_token_for_testing_only'),
            'is_embedded_signup'      => 0,
            'token_source'            => 'manual',
            'display_name'            => 'مؤسسة محمود للتجارة',
            'quality_rating'          => 'GREEN',
            'verified_name'           => 'مؤسسة محمود للتجارة',
        ];
        $meta['timezone'] = 'Asia/Riyadh';

        DB::table('organizations')->where('id', $this->orgId)->update([
            'metadata'   => json_encode($meta),
            'updated_at' => $now,
        ]);

        $this->command->line('  ✓ رقم واتساب: +966550000001');

        // =============================================
        // 3. ORGANIZATION ROLES
        // =============================================
        $this->command->info('3. إنشاء الأدوار...');

        $roles = [
            ['name' => 'Executive Management',      'description' => 'Full access to all features'],
            ['name' => 'Operations Manager',         'description' => 'Manage day-to-day operations'],
            ['name' => 'Customer Support Supervisor','description' => 'Supervise support team'],
            ['name' => 'Reply Employee',             'description' => 'Handle customer replies'],
            ['name' => 'Marketing Specialist',       'description' => 'Manage campaigns'],
            ['name' => 'Developer Integrations',     'description' => 'API and integrations access'],
        ];

        $ownerRoleId = DB::table('organization_roles')->where('name', 'Owner')->whereNull('organization_id')->value('id') ?? 1;

        foreach ($roles as $role) {
            $exists = DB::table('organization_roles')
                ->where('organization_id', $this->orgId)
                ->where('name', $role['name'])
                ->exists();
            if (!$exists) {
                DB::table('organization_roles')->insert([
                    'uuid'            => (string) Str::uuid(),
                    'organization_id' => $this->orgId,
                    'name'            => $role['name'],
                    'description'     => $role['description'],
                    'permissions'     => json_encode([]),
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }

        // Team membership for user
        DB::table('teams')->insert([
            'uuid'                 => (string) Str::uuid(),
            'organization_id'      => $this->orgId,
            'user_id'              => $this->userId,
            'organization_role_id' => $ownerRoleId,
            'status'               => 'active',
            'created_by'           => $this->userId,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        $this->command->line('  ✓ ' . count($roles) . ' أدوار + عضوية الفريق');

        // =============================================
        // 4. CONTACT GROUPS
        // =============================================
        $this->command->info('4. إنشاء مجموعات جهات الاتصال...');

        $groups = ['عملاء VIP', 'عملاء جدد', 'عملاء محتملون', 'مشتركون نشطون', 'بحاجة متابعة'];
        $groupIds = [];

        foreach ($groups as $group) {
            $groupIds[] = DB::table('contact_groups')->insertGetId([
                'uuid'            => (string) Str::uuid(),
                'organization_id' => $this->orgId,
                'name'            => $group,
                'created_by'      => $this->userId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        $this->command->line('  ✓ ' . count($groups) . ' مجموعات');

        // =============================================
        // 5. MORE CONTACTS
        // =============================================
        $this->command->info('5. إضافة جهات اتصال إضافية...');

        $newContacts = [
            ['يوسف',   'الرشيدي',  '+966511111111', 0],
            ['منى',    'الصلوي',   '+966512222222', 1],
            ['تركي',   'الفيصل',   '+966513333333', 0],
            ['لجين',   'العنزي',   '+966514444444', 1],
            ['بندر',   'الملحم',   '+966515555555', 0],
            ['شيماء',  'البقمي',   '+966516666666', 1],
            ['ماجد',   'السلمي',   '+966517777777', 0],
            ['دلال',   'القرني',   '+966518888888', 1],
            ['وليد',   'الزيد',    '+966519999999', 0],
            ['أسماء',  'المنصور',  '+966521111111', 1],
            ['حسن',    'الغافري',  '+966522222222', 0],
            ['رنا',    'الشهراني', '+966523333333', 1],
            ['طارق',   'العساف',   '+966524444444', 0],
            ['سمر',    'الجهني',   '+966525555555', 1],
            ['فهد',    'الصقير',   '+966526666666', 0],
        ];

        $messages = [
            'استفسار عن الخدمة',
            'أريد معرفة المزيد',
            'كيف يمكنني الاشتراك؟',
            'ما هي المميزات؟',
            'هل يوجد نسخة تجريبية؟',
            'أريد ترقية باقتي',
            'لدي مشكلة في الحساب',
            'شكراً على الخدمة الممتازة',
        ];

        $replies = [
            'أهلاً! سنساعدك فوراً 😊',
            'مرحباً! يسعدنا خدمتك',
            'شكراً لتواصلك معنا',
            'سنرد عليك خلال دقائق',
            'تم استلام استفسارك',
        ];

        $newContactIds = [];
        foreach ($newContacts as $i => [$fn, $ln, $phone, $ai]) {
            $cid = DB::table('contacts')->insertGetId([
                'uuid'            => (string) Str::uuid(),
                'organization_id' => $this->orgId,
                'first_name'      => $fn,
                'last_name'       => $ln,
                'phone'           => $phone,
                'contact_group_id'=> $groupIds[$i % count($groupIds)],
                'ai_assistance_enabled' => $ai,
                'created_by'      => $this->userId,
                'created_at'      => $now->copy()->subDays(rand(1, 30)),
                'updated_at'      => $now,
            ]);
            $newContactIds[] = $cid;

            $msg = $messages[$i % count($messages)];
            $rep = $replies[$i % count($replies)];

            DB::table('chats')->insert([
                ['uuid' => (string)Str::uuid(), 'organization_id'=>$this->orgId, 'contact_id'=>$cid, 'user_id'=>$this->userId, 'type'=>'inbound',  'status'=>'read',      'is_read'=>1, 'metadata'=>json_encode(['message'=>$msg,'type'=>'text']), 'created_at'=>$now->copy()->subHours(rand(1,48))],
                ['uuid' => (string)Str::uuid(), 'organization_id'=>$this->orgId, 'contact_id'=>$cid, 'user_id'=>$this->userId, 'type'=>'outbound', 'status'=>'delivered', 'is_read'=>1, 'metadata'=>json_encode(['message'=>$rep,'type'=>'text']), 'created_at'=>$now->copy()->subHours(rand(0,2))],
            ]);
        }

        // Add groups to some existing contacts
        $existingContacts = DB::table('contacts')->where('organization_id', $this->orgId)->pluck('id')->take(10);
        foreach ($existingContacts as $i => $cid) {
            DB::table('contacts')->where('id', $cid)->update(['contact_group_id' => $groupIds[$i % count($groupIds)]]);
        }

        $total = DB::table('contacts')->where('organization_id', $this->orgId)->count();
        $this->command->line("  ✓ {$total} جهة اتصال إجمالية");

        // =============================================
        // 6. CAMPAIGNS
        // =============================================
        $this->command->info('6. إنشاء الحملات...');

        $campaigns = [
            ['name' => 'حملة رمضان 2026',      'status' => 'completed', 'days' => 15],
            ['name' => 'عروض العيد',            'status' => 'completed', 'days' => 8],
            ['name' => 'إطلاق منتج جديد',       'status' => 'active',    'days' => 2],
            ['name' => 'متابعة العملاء المهتمين','status' => 'scheduled', 'days' => -2],
            ['name' => 'استطلاع رضا Q2',        'status' => 'draft',     'days' => -5],
        ];

        foreach ($campaigns as $camp) {
            DB::table('campaigns')->insert([
                'uuid'             => (string) Str::uuid(),
                'organization_id'  => $this->orgId,
                'name'             => $camp['name'],
                'template_id'      => 0,
                'contact_group_id' => $groupIds[array_rand($groupIds)],
                'status'           => $camp['status'],
                'metadata'         => json_encode(['type' => 'whatsapp', 'estimated_recipients' => rand(50, 500)]),
                'scheduled_at'     => now()->addDays($camp['days']),
                'created_by'       => $this->userId,
                'created_at'       => $now->copy()->subDays(abs($camp['days']) + 1),
            ]);
        }

        $this->command->line('  ✓ ' . count($campaigns) . ' حملات');

        // =============================================
        // 7. BILLING RECORDS
        // =============================================
        $this->command->info('7. إنشاء سجلات الفواتير...');

        DB::table('billing_transactions')->insert([
            'uuid'            => (string) Str::uuid(),
            'organization_id' => $this->orgId,
            'entity_type'     => 'payment',
            'entity_id'       => 1,
            'amount'          => 999.00,
            'description'     => 'اشتراك باقة الأعمال - شهري',
            'created_by'      => $this->userId,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        $this->command->line("  ✓ فاتورة 999 SAR مسجلة");

        // =============================================
        // SUMMARY
        // =============================================
        $this->command->newLine();
        $this->command->info('=== تم الإعداد بنجاح ===');
        $this->command->line('✅ الاشتراك: باقة الأعمال (نشط حتى ' . now()->addYear()->toDateString() . ')');
        $this->command->line('✅ واتساب: +966550000001 (بيانات تجريبية)');
        $this->command->line('✅ الأدوار: 6 أدوار لمؤسسة محمود');
        $this->command->line('✅ المجموعات: ' . count($groups) . ' مجموعات جهات اتصال');
        $this->command->line('✅ الاتصالات: ' . DB::table('contacts')->where('organization_id', $this->orgId)->count() . ' جهة اتصال');
        $this->command->line('✅ المحادثات: ' . DB::table('chats')->where('organization_id', $this->orgId)->count() . ' رسالة');
        $this->command->line('✅ الحملات: ' . count($campaigns));
        $this->command->line('✅ الفواتير: سجل دفع 999 SAR');
    }
}
