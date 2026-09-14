<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriptionPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $plans = [
            [
                'name' => 'Starter',
                'name_ar' => 'الخطة الأساسية',
                'name_en' => 'Starter',
                'commercial_plan_key' => 'starter',
                'tier_rank' => 1,
                'monthly_price' => 299,
                'yearly_price' => 2990,
                'metadata' => [
                    'message_limit' => 1000,
                    'contacts_limit' => 2000,
                    'team_limit' => 1,
                    'canned_replies_limit' => 10,
                    'campaign_limit' => 4,
                    'branches_limit' => 1,
                    'addons' => [
                        'Embedded Signup' => true,
                        'AI Assistant' => false,
                        'Flow builder' => false,
                    ],
                    'flow_builder_advanced_enabled' => false,
                ],
            ],
            [
                'name' => 'Growth',
                'name_ar' => 'خطة النمو',
                'name_en' => 'Growth',
                'commercial_plan_key' => 'growth',
                'tier_rank' => 2,
                'monthly_price' => 799,
                'yearly_price' => 7990,
                'metadata' => [
                    'message_limit' => 5000,
                    'contacts_limit' => 10000,
                    'team_limit' => 5,
                    'canned_replies_limit' => 50,
                    'campaign_limit' => 20,
                    'branches_limit' => 2,
                    'addons' => [
                        'Embedded Signup' => true,
                        'AI Assistant' => true,
                        'Flow builder' => true,
                    ],
                    'flow_builder_advanced_enabled' => false,
                ],
            ],
            [
                'name' => 'Business',
                'name_ar' => 'خطة الأعمال',
                'name_en' => 'Business',
                'commercial_plan_key' => 'business',
                'tier_rank' => 3,
                'monthly_price' => 1499,
                'yearly_price' => 14990,
                'metadata' => [
                    'message_limit' => -1,
                    'contacts_limit' => -1,
                    'team_limit' => 20,
                    'canned_replies_limit' => -1,
                    'campaign_limit' => -1,
                    'branches_limit' => 10,
                    'addons' => [
                        'Embedded Signup' => true,
                        'AI Assistant' => true,
                        'Flow builder' => true,
                    ],
                    'flow_builder_advanced_enabled' => true,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            foreach (['monthly' => $plan['monthly_price'], 'yearly' => $plan['yearly_price']] as $period => $price) {
                $metadata = array_merge($plan['metadata'], [
                    'tier_rank' => $plan['tier_rank'],
                    'commercial_plan_key' => $plan['commercial_plan_key'],
                ]);

                SubscriptionPlan::firstOrCreate(
                    [
                        'name' => $plan['name'],
                        'period' => $period,
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'name_ar' => $plan['name_ar'],
                        'name_en' => $plan['name_en'],
                        'price' => $price,
                        'metadata' => json_encode($metadata),
                        'status' => 'active',
                    ]
                );
            }
        }
    }
}
