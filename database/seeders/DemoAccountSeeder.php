<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // =============================================
        // 1. SUBSCRIPTION PLANS
        // =============================================
        $this->command->info('إنشاء الباقات...');

        $plans = [
            // Monthly
            [
                'uuid' => (string) Str::uuid(), 'name' => 'الأساسية', 'name_ar' => 'الأساسية', 'name_en' => 'Basic',
                'price' => 299.00, 'period' => 'monthly', 'status' => 'active',
                'metadata' => json_encode([
                    'tier_rank' => 1, 'campaign_limit' => 5, 'message_limit' => 3000,
                    'contacts_limit' => 1000, 'canned_replies_limit' => 30, 'team_limit' => 2,
                    'ai_text_response_limit' => 0, 'ai_audio_response_limit' => -1,
                    'ai_organization_key_enabled' => false, 'branches_limit' => 1,
                    'ai_system_key_monthly_quota' => 0,
                    'flow_builder_active_flows_limit' => 0, 'flow_builder_nodes_per_flow_limit' => 0,
                    'flow_builder_monthly_runs_limit' => 0, 'flow_builder_advanced_enabled' => false,
                    'receive_messages_after_expiration' => false,
                    'addons' => ['Embedded Signup' => true, 'AI Assistant' => false, 'Flow builder' => false],
                    'custom_features' => [],
                ]),
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'uuid' => (string) Str::uuid(), 'name' => 'النمو', 'name_ar' => 'النمو', 'name_en' => 'Growth',
                'price' => 599.00, 'period' => 'monthly', 'status' => 'active',
                'metadata' => json_encode([
                    'tier_rank' => 2, 'campaign_limit' => 20, 'message_limit' => 10000,
                    'contacts_limit' => 5000, 'canned_replies_limit' => 100, 'team_limit' => 5,
                    'ai_text_response_limit' => 500, 'ai_audio_response_limit' => -1,
                    'ai_organization_key_enabled' => false, 'branches_limit' => 1,
                    'ai_system_key_monthly_quota' => 500,
                    'flow_builder_active_flows_limit' => 10, 'flow_builder_nodes_per_flow_limit' => 20,
                    'flow_builder_monthly_runs_limit' => 5000, 'flow_builder_advanced_enabled' => false,
                    'receive_messages_after_expiration' => false,
                    'addons' => ['Embedded Signup' => true, 'AI Assistant' => true, 'Flow builder' => true],
                    'custom_features' => [],
                ]),
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'uuid' => (string) Str::uuid(), 'name' => 'الأعمال', 'name_ar' => 'الأعمال', 'name_en' => 'Business',
                'price' => 999.00, 'period' => 'monthly', 'status' => 'active',
                'metadata' => json_encode([
                    'tier_rank' => 3, 'campaign_limit' => 60, 'message_limit' => 30000,
                    'contacts_limit' => 25000, 'canned_replies_limit' => 300, 'team_limit' => 10,
                    'ai_text_response_limit' => 2000, 'ai_audio_response_limit' => -1,
                    'ai_organization_key_enabled' => true, 'branches_limit' => 3,
                    'ai_system_key_monthly_quota' => 2000,
                    'flow_builder_active_flows_limit' => 30, 'flow_builder_nodes_per_flow_limit' => 50,
                    'flow_builder_monthly_runs_limit' => 20000, 'flow_builder_advanced_enabled' => true,
                    'receive_messages_after_expiration' => true,
                    'addons' => ['Embedded Signup' => true, 'AI Assistant' => true, 'Flow builder' => true],
                    'custom_features' => [],
                ]),
                'created_at' => $now, 'updated_at' => $now,
            ],
            // Yearly (20% discount)
            [
                'uuid' => (string) Str::uuid(), 'name' => 'الأساسية', 'name_ar' => 'الأساسية', 'name_en' => 'Basic',
                'price' => 2870.00, 'period' => 'yearly', 'status' => 'active',
                'metadata' => json_encode([
                    'tier_rank' => 1, 'campaign_limit' => 5, 'message_limit' => 3000,
                    'contacts_limit' => 1000, 'canned_replies_limit' => 30, 'team_limit' => 2,
                    'ai_text_response_limit' => 0, 'ai_audio_response_limit' => -1,
                    'ai_organization_key_enabled' => false, 'branches_limit' => 1,
                    'ai_system_key_monthly_quota' => 0,
                    'flow_builder_active_flows_limit' => 0, 'flow_builder_nodes_per_flow_limit' => 0,
                    'flow_builder_monthly_runs_limit' => 0, 'flow_builder_advanced_enabled' => false,
                    'receive_messages_after_expiration' => false,
                    'addons' => ['Embedded Signup' => true, 'AI Assistant' => false, 'Flow builder' => false],
                    'custom_features' => [],
                ]),
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'uuid' => (string) Str::uuid(), 'name' => 'النمو', 'name_ar' => 'النمو', 'name_en' => 'Growth',
                'price' => 5750.00, 'period' => 'yearly', 'status' => 'active',
                'metadata' => json_encode([
                    'tier_rank' => 2, 'campaign_limit' => 20, 'message_limit' => 10000,
                    'contacts_limit' => 5000, 'canned_replies_limit' => 100, 'team_limit' => 5,
                    'ai_text_response_limit' => 500, 'ai_audio_response_limit' => -1,
                    'ai_organization_key_enabled' => false, 'branches_limit' => 1,
                    'ai_system_key_monthly_quota' => 500,
                    'flow_builder_active_flows_limit' => 10, 'flow_builder_nodes_per_flow_limit' => 20,
                    'flow_builder_monthly_runs_limit' => 5000, 'flow_builder_advanced_enabled' => false,
                    'receive_messages_after_expiration' => false,
                    'addons' => ['Embedded Signup' => true, 'AI Assistant' => true, 'Flow builder' => true],
                    'custom_features' => [],
                ]),
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'uuid' => (string) Str::uuid(), 'name' => 'الأعمال', 'name_ar' => 'الأعمال', 'name_en' => 'Business',
                'price' => 9590.00, 'period' => 'yearly', 'status' => 'active',
                'metadata' => json_encode([
                    'tier_rank' => 3, 'campaign_limit' => 60, 'message_limit' => 30000,
                    'contacts_limit' => 25000, 'canned_replies_limit' => 300, 'team_limit' => 10,
                    'ai_text_response_limit' => 2000, 'ai_audio_response_limit' => -1,
                    'ai_organization_key_enabled' => true, 'branches_limit' => 3,
                    'ai_system_key_monthly_quota' => 2000,
                    'flow_builder_active_flows_limit' => 30, 'flow_builder_nodes_per_flow_limit' => 50,
                    'flow_builder_monthly_runs_limit' => 20000, 'flow_builder_advanced_enabled' => true,
                    'receive_messages_after_expiration' => true,
                    'addons' => ['Embedded Signup' => true, 'AI Assistant' => true, 'Flow builder' => true],
                    'custom_features' => [],
                ]),
                'created_at' => $now, 'updated_at' => $now,
            ],
        ];

        DB::table('subscription_plans')->insert($plans);
        $businessPlan = DB::table('subscription_plans')->where('name', 'الأعمال')->where('period', 'monthly')->first();
        $this->command->line("  ✓ تم إنشاء 6 باقات | باقة الأعمال ID: {$businessPlan->id}");

        // =============================================
        // 2. USER
        // =============================================
        $this->command->info('إنشاء الحساب...');

        $userId = DB::table('users')->insertGetId([
            'first_name'          => 'محمود',
            'last_name'           => 'حامد',
            'email'               => 'mahmoud.hamed.shenawy@gmail.com',
            'password'            => Hash::make('Password@2026'),
            'role'                => 'admin',
            'is_system_owner'     => 0,
            'status'              => 1,
            'language'            => 'ar',
            'plan'                => 'الأعمال',
            'plan_id'             => $businessPlan->id,
            'will_expire'         => now()->addYear(),
            'email_verified_at'   => now(),
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
        $this->command->line("  ✓ تم إنشاء المستخدم ID: {$userId}");

        // =============================================
        // 3. ORGANIZATION (Main)
        // =============================================
        $this->command->info('إنشاء المنظمة الرئيسية والفروع...');

        $mainOrgId = DB::table('organizations')->insertGetId([
            'uuid'              => (string) Str::uuid(),
            'identifier'        => now()->format('YmdHis') . Str::random(6),
            'name'              => 'مؤسسة محمود للتجارة',
            'organization_type' => 'main',
            'metadata'          => json_encode([
                'addons' => ['embedded_signup_enabled' => true],
                'system' => ['default_role_seed_version' => 1],
            ]),
            'created_by'  => $userId,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $this->command->line("  ✓ المنظمة الرئيسية ID: {$mainOrgId}");

        // Employee record for the user
        DB::table('organization_employees')->insert([
            'uuid'                 => (string) Str::uuid(),
            'main_organization_id' => $mainOrgId,
            'user_id'              => $userId,
            'email'                => 'mahmoud.hamed.shenawy@gmail.com',
            'first_name'           => 'محمود',
            'last_name'            => 'حامد',
            'status'               => 'active',
            'accepted_at'          => $now,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        // Branches with sub-branches
        $branches = [
            ['name' => 'فرع الرياض', 'subs' => ['مركز خدمة العملاء - الرياض', 'قسم المبيعات - الرياض']],
            ['name' => 'فرع جدة',    'subs' => ['مركز الدعم الفني - جدة', 'قسم التسويق - جدة']],
            ['name' => 'فرع الدمام', 'subs' => ['خدمة ما بعد البيع - الدمام', 'قسم الشراكات - الدمام']],
            ['name' => 'فرع مكة',    'subs' => ['مركز العمليات - مكة', 'قسم الإدارة - مكة']],
        ];

        foreach ($branches as $branch) {
            $branchId = DB::table('organizations')->insertGetId([
                'uuid'                    => (string) Str::uuid(),
                'identifier'              => now()->format('YmdHis') . Str::random(6),
                'name'                    => $branch['name'],
                'organization_type'       => 'branch',
                'parent_organization_id'  => $mainOrgId,
                'metadata'                => json_encode(['addons' => ['embedded_signup_enabled' => true]]),
                'created_by'  => $userId,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $this->command->line("    ✓ {$branch['name']} ID: {$branchId}");

            foreach ($branch['subs'] as $subName) {
                DB::table('organizations')->insert([
                    'uuid'                    => (string) Str::uuid(),
                    'identifier'              => now()->format('YmdHis') . Str::random(6),
                    'name'                    => $subName,
                    'organization_type'       => 'branch',
                    'parent_organization_id'  => $branchId,
                    'metadata'                => json_encode(['addons' => ['embedded_signup_enabled' => true]]),
                    'created_by'  => $userId,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
                $this->command->line("      ✓ {$subName}");
            }
        }

        // =============================================
        // 4. CONTACTS
        // =============================================
        $this->command->info('إنشاء جهات الاتصال والمحادثات...');

        $contactsData = [
            ['first_name' => 'أحمد',   'last_name' => 'العمري',    'phone' => '+966501111111'],
            ['first_name' => 'فاطمة',  'last_name' => 'الزهراني',  'phone' => '+966502222222'],
            ['first_name' => 'خالد',   'last_name' => 'الشمري',    'phone' => '+966503333333'],
            ['first_name' => 'سارة',   'last_name' => 'القحطاني',  'phone' => '+966504444444'],
            ['first_name' => 'محمد',   'last_name' => 'الغامدي',   'phone' => '+966505555555'],
            ['first_name' => 'نورة',   'last_name' => 'الدوسري',   'phone' => '+966506666666'],
            ['first_name' => 'عبدالله','last_name' => 'المطيري',   'phone' => '+966507777777'],
            ['first_name' => 'ريم',    'last_name' => 'السبيعي',   'phone' => '+966508888888'],
            ['first_name' => 'عمر',    'last_name' => 'الحربي',    'phone' => '+966509999999'],
            ['first_name' => 'هند',    'last_name' => 'العتيبي',   'phone' => '+966501234567'],
        ];

        $chatMessages = [
            ['السلام عليكم، كيف يمكنني مساعدتكم؟', 'وعليكم السلام، أريد الاستفسار عن خدماتكم'],
            ['أهلاً وسهلاً، نحن هنا لخدمتكم', 'شكراً، لدي سؤال عن الأسعار'],
            ['مرحباً! كيف نقدر نساعدك؟', 'أريد معرفة المزيد عن برنامجكم'],
            ['أهلاً! ما الذي تحتاج إليه؟', 'هل لديكم عروض خاصة؟'],
            ['مساء الخير! كيف يمكنني خدمتك؟', 'أريد الاشتراك في الخطة الأعمال'],
        ];

        foreach ($contactsData as $i => $contact) {
            $contactId = DB::table('contacts')->insertGetId([
                'uuid'            => (string) Str::uuid(),
                'organization_id' => $mainOrgId,
                'first_name'      => $contact['first_name'],
                'last_name'       => $contact['last_name'],
                'phone'           => $contact['phone'],
                'ai_assistance_enabled' => 1,
                'created_by'      => $userId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            $msgs = $chatMessages[$i % count($chatMessages)];

            // Inbound chat
            DB::table('chats')->insert([
                'uuid'            => (string) Str::uuid(),
                'organization_id' => $mainOrgId,
                'contact_id'      => $contactId,
                'user_id'         => $userId,
                'type'            => 'inbound',
                'status'          => 'read',
                'is_read'         => 1,
                'metadata'        => json_encode(['message' => $msgs[1], 'type' => 'text']),
                'created_at'      => $now,
            ]);

            // Outbound chat (reply)
            DB::table('chats')->insert([
                'uuid'            => (string) Str::uuid(),
                'organization_id' => $mainOrgId,
                'contact_id'      => $contactId,
                'user_id'         => $userId,
                'type'            => 'outbound',
                'status'          => 'delivered',
                'is_read'         => 1,
                'metadata'        => json_encode(['message' => $msgs[0], 'type' => 'text']),
                'created_at'      => $now,
            ]);
        }
        $this->command->line("  ✓ تم إنشاء " . count($contactsData) . " جهة اتصال مع محادثاتها");

        // =============================================
        // 5. AUTO REPLIES (AI Assistant)
        // =============================================
        $this->command->info('إنشاء ردود المساعد الذكي...');

        $autoReplies = [
            [
                'name'    => 'الترحيب بالعملاء الجدد',
                'trigger' => json_encode(['keywords' => ['مرحبا', 'السلام', 'هلا', 'اهلا', 'كيف حالك']]),
                'match_criteria' => 'contains_any',
                'metadata' => json_encode([
                    'type' => 'ai',
                    'response' => 'أهلاً وسهلاً! 👋 نحن سعداء بتواصلك معنا. كيف يمكنني مساعدتك اليوم؟',
                    'ai_enabled' => true,
                ]),
            ],
            [
                'name'    => 'استفسار الأسعار',
                'trigger' => json_encode(['keywords' => ['سعر', 'أسعار', 'تكلفة', 'كم', 'رسوم']]),
                'match_criteria' => 'contains_any',
                'metadata' => json_encode([
                    'type' => 'ai',
                    'response' => 'لدينا 3 باقات تناسب احتياجاتك: الأساسية 299 ر.س، النمو 599 ر.س، الأعمال 999 ر.س شهرياً. أي باقة تناسبك أكثر؟',
                    'ai_enabled' => true,
                ]),
            ],
            [
                'name'    => 'دعم فني',
                'trigger' => json_encode(['keywords' => ['مشكلة', 'خطأ', 'لا يعمل', 'مساعدة', 'دعم']]),
                'match_criteria' => 'contains_any',
                'metadata' => json_encode([
                    'type' => 'ai',
                    'response' => 'نأسف لسماع ذلك! فريق الدعم الفني جاهز لمساعدتك. يرجى وصف المشكلة بالتفصيل وسنحلها فوراً. 🛠️',
                    'ai_enabled' => true,
                ]),
            ],
            [
                'name'    => 'ساعات العمل',
                'trigger' => json_encode(['keywords' => ['ساعات', 'متى', 'وقت', 'دوام', 'العمل']]),
                'match_criteria' => 'contains_any',
                'metadata' => json_encode([
                    'type' => 'ai',
                    'response' => 'ساعات عملنا من الأحد إلى الخميس 9 صباحاً - 6 مساءً. نحن دائماً متاحون عبر الواتساب! ⏰',
                    'ai_enabled' => true,
                ]),
            ],
            [
                'name'    => 'شكر العملاء',
                'trigger' => json_encode(['keywords' => ['شكرا', 'ممتاز', 'رائع', 'جيد', 'تمام']]),
                'match_criteria' => 'contains_any',
                'metadata' => json_encode([
                    'type' => 'ai',
                    'response' => 'شكراً جزيلاً لك! 😊 رضاك هو هدفنا الأول. لا تتردد في التواصل معنا في أي وقت.',
                    'ai_enabled' => true,
                ]),
            ],
        ];

        foreach ($autoReplies as $reply) {
            DB::table('auto_replies')->insert([
                'uuid'            => (string) Str::uuid(),
                'organization_id' => $mainOrgId,
                'name'            => $reply['name'],
                'trigger'         => $reply['trigger'],
                'match_criteria'  => $reply['match_criteria'],
                'metadata'        => $reply['metadata'],
                'created_by'      => $userId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
        $this->command->line("  ✓ تم إنشاء " . count($autoReplies) . " رد تلقائي للمساعد الذكي");

        // =============================================
        // 6. AUTOMATION FLOWS
        // =============================================
        $this->command->info('إنشاء نماذج Flow Builder...');

        $flows = [
            [
                'name'        => 'استقبال العملاء الجدد',
                'description' => 'ترحيب تلقائي بالعملاء عند أول تواصل',
                'goal_preset' => 'customer_onboarding',
                'trigger_type'=> 'new_contact',
                'graph_json'  => json_encode([
                    'nodes' => [
                        ['id' => 'start', 'type' => 'trigger', 'data' => ['label' => 'بداية']],
                        ['id' => 'msg1',  'type' => 'send_message', 'data' => ['message' => 'أهلاً! شكراً لتواصلك معنا 👋']],
                        ['id' => 'cond1', 'type' => 'condition', 'data' => ['condition' => 'هل أنت عميل جديد؟']],
                        ['id' => 'new',   'type' => 'send_message', 'data' => ['message' => 'مرحباً بك كعميل جديد! إليك عروضنا الحصرية 🎉']],
                        ['id' => 'exist', 'type' => 'send_message', 'data' => ['message' => 'أهلاً بعودتك! كيف يمكننا مساعدتك اليوم؟']],
                        ['id' => 'end',   'type' => 'end', 'data' => ['label' => 'نهاية']],
                    ],
                    'edges' => [
                        ['source' => 'start', 'target' => 'msg1'],
                        ['source' => 'msg1',  'target' => 'cond1'],
                        ['source' => 'cond1', 'target' => 'new',   'label' => 'نعم'],
                        ['source' => 'cond1', 'target' => 'exist',  'label' => 'لا'],
                        ['source' => 'new',   'target' => 'end'],
                        ['source' => 'exist', 'target' => 'end'],
                    ],
                ]),
            ],
            [
                'name'        => 'تأهيل العملاء المحتملين',
                'description' => 'تصنيف العملاء حسب اهتمامهم وميزانيتهم',
                'goal_preset' => 'lead_qualification',
                'trigger_type'=> 'keyword',
                'graph_json'  => json_encode([
                    'nodes' => [
                        ['id' => 'start',  'type' => 'trigger', 'data' => ['label' => 'بداية - كلمة مفتاحية']],
                        ['id' => 'q1',     'type' => 'question', 'data' => ['message' => 'ما هو مجال عملك؟', 'options' => ['تجارة إلكترونية', 'خدمات', 'مطاعم', 'أخرى']]],
                        ['id' => 'q2',     'type' => 'question', 'data' => ['message' => 'كم عدد موظفيك؟', 'options' => ['1-5', '6-20', '21-50', 'أكثر من 50']]],
                        ['id' => 'cond1',  'type' => 'condition', 'data' => ['condition' => 'فريق كبير؟']],
                        ['id' => 'large',  'type' => 'send_message', 'data' => ['message' => 'ننصحك بباقة الأعمال 999 ر.س/شهر لفريقك الكبير! 🚀']],
                        ['id' => 'cond2',  'type' => 'condition', 'data' => ['condition' => 'فريق متوسط؟']],
                        ['id' => 'medium', 'type' => 'send_message', 'data' => ['message' => 'باقة النمو 599 ر.س/شهر مثالية لك! 📈']],
                        ['id' => 'small',  'type' => 'send_message', 'data' => ['message' => 'ابدأ رحلتك مع الباقة الأساسية 299 ر.س/شهر! ✨']],
                        ['id' => 'assign', 'type' => 'assign_agent', 'data' => ['message' => 'تحويل لمستشار المبيعات']],
                        ['id' => 'end',    'type' => 'end', 'data' => ['label' => 'نهاية']],
                    ],
                    'edges' => [
                        ['source' => 'start',  'target' => 'q1'],
                        ['source' => 'q1',     'target' => 'q2'],
                        ['source' => 'q2',     'target' => 'cond1'],
                        ['source' => 'cond1',  'target' => 'large',  'label' => 'أكثر من 20'],
                        ['source' => 'cond1',  'target' => 'cond2',  'label' => 'أقل'],
                        ['source' => 'cond2',  'target' => 'medium', 'label' => '6-20'],
                        ['source' => 'cond2',  'target' => 'small',  'label' => '1-5'],
                        ['source' => 'large',  'target' => 'assign'],
                        ['source' => 'medium', 'target' => 'assign'],
                        ['source' => 'small',  'target' => 'assign'],
                        ['source' => 'assign', 'target' => 'end'],
                    ],
                ]),
            ],
            [
                'name'        => 'دعم ما بعد البيع',
                'description' => 'متابعة العملاء بعد الشراء وحل المشاكل',
                'goal_preset' => 'customer_support',
                'trigger_type'=> 'keyword',
                'graph_json'  => json_encode([
                    'nodes' => [
                        ['id' => 'start',   'type' => 'trigger', 'data' => ['label' => 'بداية - طلب دعم']],
                        ['id' => 'greet',   'type' => 'send_message', 'data' => ['message' => 'مرحباً! فريق الدعم جاهز لمساعدتك 🛠️']],
                        ['id' => 'type',    'type' => 'question', 'data' => ['message' => 'ما نوع المشكلة؟', 'options' => ['مشكلة تقنية', 'استفسار عن فاتورة', 'طلب إلغاء', 'مشكلة أخرى']]],
                        ['id' => 'tech',    'type' => 'condition', 'data' => ['condition' => 'مشكلة تقنية؟']],
                        ['id' => 'billing', 'type' => 'condition', 'data' => ['condition' => 'استفسار فاتورة؟']],
                        ['id' => 'tech_r',  'type' => 'send_message', 'data' => ['message' => 'سيتواصل معك فريقنا التقني خلال ساعة ⚙️']],
                        ['id' => 'bill_r',  'type' => 'send_message', 'data' => ['message' => 'سنراجع فاتورتك ونرسل لك تفاصيل كاملة 📄']],
                        ['id' => 'cancel',  'type' => 'send_message', 'data' => ['message' => 'نأسف لسماع ذلك. هل يمكننا تقديم عرض خاص للإبقاء على خدمتك؟ 💙']],
                        ['id' => 'other',   'type' => 'assign_agent', 'data' => ['message' => 'تحويل لوكيل متخصص']],
                        ['id' => 'rate',    'type' => 'question', 'data' => ['message' => 'كيف تقيّم تجربة الدعم؟', 'options' => ['⭐⭐⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐', '⭐⭐']]],
                        ['id' => 'end',     'type' => 'end', 'data' => ['label' => 'نهاية']],
                    ],
                    'edges' => [
                        ['source' => 'start',   'target' => 'greet'],
                        ['source' => 'greet',   'target' => 'type'],
                        ['source' => 'type',    'target' => 'tech'],
                        ['source' => 'tech',    'target' => 'tech_r',  'label' => 'نعم'],
                        ['source' => 'tech',    'target' => 'billing', 'label' => 'لا'],
                        ['source' => 'billing', 'target' => 'bill_r',  'label' => 'نعم'],
                        ['source' => 'billing', 'target' => 'cancel',  'label' => 'إلغاء'],
                        ['source' => 'billing', 'target' => 'other',   'label' => 'أخرى'],
                        ['source' => 'tech_r',  'target' => 'rate'],
                        ['source' => 'bill_r',  'target' => 'rate'],
                        ['source' => 'cancel',  'target' => 'rate'],
                        ['source' => 'other',   'target' => 'rate'],
                        ['source' => 'rate',    'target' => 'end'],
                    ],
                ]),
            ],
            [
                'name'        => 'حجز مواعيد',
                'description' => 'نظام حجز المواعيد التلقائي',
                'goal_preset' => 'appointment_booking',
                'trigger_type'=> 'keyword',
                'graph_json'  => json_encode([
                    'nodes' => [
                        ['id' => 'start',   'type' => 'trigger', 'data' => ['label' => 'بداية - طلب حجز']],
                        ['id' => 'intro',   'type' => 'send_message', 'data' => ['message' => 'يسعدنا حجز موعدك! 📅']],
                        ['id' => 'service', 'type' => 'question', 'data' => ['message' => 'ما الخدمة التي تريد؟', 'options' => ['استشارة', 'عرض تجريبي', 'تدريب', 'دعم فني']]],
                        ['id' => 'day',     'type' => 'question', 'data' => ['message' => 'أي يوم يناسبك؟', 'options' => ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس']]],
                        ['id' => 'time',    'type' => 'question', 'data' => ['message' => 'أي وقت يناسبك؟', 'options' => ['9 صباحاً', '11 صباحاً', '2 ظهراً', '4 مساءً']]],
                        ['id' => 'confirm', 'type' => 'send_message', 'data' => ['message' => 'تم تأكيد حجزك! ✅ سنرسل لك تذكيراً قبل الموعد بساعة.']],
                        ['id' => 'end',     'type' => 'end', 'data' => ['label' => 'نهاية']],
                    ],
                    'edges' => [
                        ['source' => 'start',   'target' => 'intro'],
                        ['source' => 'intro',   'target' => 'service'],
                        ['source' => 'service', 'target' => 'day'],
                        ['source' => 'day',     'target' => 'time'],
                        ['source' => 'time',    'target' => 'confirm'],
                        ['source' => 'confirm', 'target' => 'end'],
                    ],
                ]),
            ],
            [
                'name'        => 'استطلاع رضا العملاء',
                'description' => 'قياس رضا العملاء بعد كل تفاعل',
                'goal_preset' => 'customer_satisfaction',
                'trigger_type'=> 'post_conversation',
                'graph_json'  => json_encode([
                    'nodes' => [
                        ['id' => 'start',    'type' => 'trigger', 'data' => ['label' => 'بداية - بعد المحادثة']],
                        ['id' => 'intro',    'type' => 'send_message', 'data' => ['message' => 'شكراً لتواصلك معنا! نود سماع رأيك 🙏']],
                        ['id' => 'rate',     'type' => 'question', 'data' => ['message' => 'كيف تقيّم تجربتك معنا؟', 'options' => ['ممتازة 🌟', 'جيدة 👍', 'مقبولة 😐', 'سيئة 👎']]],
                        ['id' => 'cond',     'type' => 'condition', 'data' => ['condition' => 'تقييم عالٍ؟']],
                        ['id' => 'positive', 'type' => 'send_message', 'data' => ['message' => 'رائع! يسعدنا رضاك. هل تود مشاركة تقييمك على Google؟ ⭐']],
                        ['id' => 'negative', 'type' => 'question', 'data' => ['message' => 'نأسف لتجربتك. ما الذي يمكن تحسينه؟', 'options' => ['سرعة الاستجابة', 'جودة الخدمة', 'الأسعار', 'سهولة الاستخدام']]],
                        ['id' => 'improve',  'type' => 'send_message', 'data' => ['message' => 'شكراً لملاحظاتك القيّمة! سنعمل على تحسين تجربتك 💪']],
                        ['id' => 'end',      'type' => 'end', 'data' => ['label' => 'نهاية']],
                    ],
                    'edges' => [
                        ['source' => 'start',    'target' => 'intro'],
                        ['source' => 'intro',    'target' => 'rate'],
                        ['source' => 'rate',     'target' => 'cond'],
                        ['source' => 'cond',     'target' => 'positive', 'label' => 'ممتازة/جيدة'],
                        ['source' => 'cond',     'target' => 'negative', 'label' => 'مقبولة/سيئة'],
                        ['source' => 'positive', 'target' => 'end'],
                        ['source' => 'negative', 'target' => 'improve'],
                        ['source' => 'improve',  'target' => 'end'],
                    ],
                ]),
            ],
        ];

        foreach ($flows as $flow) {
            DB::table('automation_flows')->insert([
                'uuid'            => (string) Str::uuid(),
                'organization_id' => $mainOrgId,
                'name'            => $flow['name'],
                'description'     => $flow['description'],
                'goal_preset'     => $flow['goal_preset'],
                'channel'         => 'whatsapp',
                'trigger_type'    => $flow['trigger_type'],
                'status'          => 'active',
                'graph_json'      => $flow['graph_json'],
                'ui_json'         => '{}',
                'has_unpublished_changes' => 0,
                'runs_count'      => rand(10, 500),
                'created_by'      => $userId,
                'updated_by'      => $userId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
            $this->command->line("  ✓ Flow: {$flow['name']}");
        }

        // =============================================
        // SUMMARY
        // =============================================
        $this->command->newLine();
        $this->command->info('=== ملخص ما تم إنشاؤه ===');
        $this->command->line('📦 الباقات: 6 (3 شهري + 3 سنوي)');
        $this->command->line('👤 الحساب: mahmoud.hamed.shenawy@gmail.com | كلمة المرور: Password@2026');
        $this->command->line('🏢 المنظمة الرئيسية: مؤسسة محمود للتجارة');
        $this->command->line('🏬 الفروع: 4 فروع رئيسية + 8 فروع فرعية');
        $this->command->line('👥 جهات الاتصال: ' . count($contactsData) . ' مع محادثاتهم');
        $this->command->line('🤖 ردود المساعد الذكي: ' . count($autoReplies));
        $this->command->line('🔀 نماذج Flow Builder: ' . count($flows));
    }
}
