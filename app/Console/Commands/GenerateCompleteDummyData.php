<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\User;
use App\Models\Team;
use App\Models\Chat;
use App\Models\ChatLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;
use Carbon\Carbon;

class GenerateCompleteDummyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:generate-complete 
                            {--organization_id=1 : Organization ID}
                            {--contacts=100 : Number of contacts to generate}
                            {--team_members=10 : Number of team members to generate}
                            {--chats=100 : Number of chats to generate (each contact will get at least 1)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate complete dummy data: contacts, team members, roles, and chats';

    /**
     * Country phone codes mapping
     */
    private $countryPhoneCodes = [
        'SA' => '+966',
    ];

    /**
     * Sample chat messages for inbound
     */
    private $inboundMessages = [
        'Hello, I need help with my order',
        'What are your business hours?',
        'Can you provide more information about your services?',
        'I have a question about my account',
        'Thank you for your assistance',
        'I would like to make a complaint',
        'How can I track my shipment?',
        'Do you offer refunds?',
        'I need to update my contact information',
        'What payment methods do you accept?',
        'Can I schedule an appointment?',
        'I received the wrong item',
        'How long does shipping take?',
        'Do you have a physical store?',
        'I want to cancel my subscription',
        'Can you help me reset my password?',
        'What is your return policy?',
        'I need technical support',
        'Are you open on weekends?',
        'I have a billing question',
    ];

    /**
     * Sample chat messages for outbound
     */
    private $outboundMessages = [
        'Hi! How can we help you today?',
        'Thank you for contacting us',
        'We have received your inquiry and will respond shortly',
        'Your order has been confirmed',
        'Your shipment is on the way',
        'We appreciate your feedback',
        'Is there anything else we can help you with?',
        'Your request has been processed',
        'We have updated your account information',
        'Thank you for being a valued customer',
        'We would like to follow up on your recent purchase',
        'Your subscription has been renewed',
        'We have a special offer for you',
        'Your payment has been received',
        'We are here to assist you',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $organizationId = (int) $this->option('organization_id');
        $contactCount = (int) $this->option('contacts');
        $teamMemberCount = (int) $this->option('team_members');
        $chatCount = (int) $this->option('chats');

        // Validate organization exists
        if (!DB::table('organizations')->where('id', $organizationId)->exists()) {
            $this->error("Organization with ID {$organizationId} does not exist.");
            return 1;
        }

        // Validate minimum contact count (at least 1)
        if ($contactCount < 1) {
            $this->error("Contact count must be at least 1. You specified {$contactCount}.");
            return 1;
        }

        $this->info("Generating complete dummy data for organization ID {$organizationId}...");
        $this->newLine();

        // Step 1: Get or create owner role
        $this->info("Step 1: Setting up roles...");
        $ownerRole = $this->getOrCreateOwnerRole();
        $role1 = $this->createOrganizationRole($organizationId, 'Manager', 'Manages team members and handles customer inquiries');
        $role2 = $this->createOrganizationRole($organizationId, 'Agent', 'Handles customer support and chat conversations');
        $this->info("✓ Created roles: Owner, Manager, Agent");
        $this->newLine();

        // Step 2: Get owner user
        $this->info("Step 2: Getting owner user...");
        $ownerUser = User::whereHas('teams', function($q) use ($organizationId, $ownerRole) {
                $q->where('organization_id', $organizationId)
                  ->where('organization_role_id', $ownerRole->id);
            })
            ->first();

        if (!$ownerUser) {
            // Try to find any user in the organization through teams
            $ownerUser = User::whereHas('teams', function($q) use ($organizationId) {
                $q->where('organization_id', $organizationId);
            })->first();
        }

        if (!$ownerUser) {
            $this->error("No owner user found for organization ID {$organizationId}. Please create an owner first.");
            return 1;
        }
        $this->info("✓ Owner user ID: {$ownerUser->id}");
        $this->newLine();

        // Step 3: Generate contacts
        $this->info("Step 3: Generating {$contactCount} contacts...");
        $contacts = $this->generateContacts($organizationId, $contactCount, $ownerUser->id);
        $this->info("✓ Generated {$contactCount} contacts");
        $this->newLine();

        // Step 4: Generate team members
        $this->info("Step 4: Generating {$teamMemberCount} team members...");
        $teamMembers = $this->generateTeamMembers($organizationId, $teamMemberCount, $ownerUser->id, [$role1->id, $role2->id]);
        $this->info("✓ Generated {$teamMemberCount} team members");
        $this->newLine();

        // Step 5: Generate chats
        $this->info("Step 5: Generating {$chatCount} chats (ensuring each contact has at least 1 chat)...");
        $allUserIds = array_merge([$ownerUser->id], array_column($teamMembers, 'user_id'));
        $this->generateChats($organizationId, $chatCount, $contacts, $allUserIds);
        $this->info("✓ Generated {$chatCount} chats (each contact has at least 1 chat)");
        $this->newLine();

        $this->info("✅ Complete! All dummy data has been generated successfully.");
        $this->info("   - Contacts: {$contactCount}");
        $this->info("   - Team Members: {$teamMemberCount}");
        $this->info("   - Roles: Manager, Agent");
        $this->info("   - Chats: {$chatCount}");

        return 0;
    }

    /**
     * Get or create owner role
     */
    private function getOrCreateOwnerRole()
    {
        $ownerRole = DB::table('organization_roles')
            ->where('name', 'Owner')
            ->whereNull('organization_id')
            ->first();

        if (!$ownerRole) {
            $id = DB::table('organization_roles')->insertGetId([
                'uuid' => Str::uuid()->toString(),
                'organization_id' => null,
                'name' => 'Owner',
                'description' => __('Organization owner with full access'),
                'permissions' => json_encode(['*']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ownerRole = (object) ['id' => $id];
        }

        return $ownerRole;
    }

    /**
     * Create organization role
     */
    private function createOrganizationRole($organizationId, $name, $description)
    {
        // Check if role already exists
        $existing = DB::table('organization_roles')
            ->where('organization_id', $organizationId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            return (object) ['id' => $existing->id];
        }

        // Default permissions for Manager and Agent
        $permissions = [
            'Manager' => [
                'contacts.view_all',
                'contacts.create',
                'contacts.edit',
                'contacts.delete',
                'chats.view_all',
                'chats.create',
                'chats.edit',
                'chats.delete',
            ],
            'Agent' => [
                'contacts.view_all',
                'chats.view_all',
                'chats.create',
                'chats.edit',
            ],
        ];

        $id = DB::table('organization_roles')->insertGetId([
            'uuid' => Str::uuid()->toString(),
            'organization_id' => $organizationId,
            'name' => $name,
            'description' => $description,
            'permissions' => json_encode($permissions[$name] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['id' => $id];
    }

    /**
     * Generate contacts
     */
    private function generateContacts($organizationId, $count, $createdBy)
    {
        $countries = array_keys($this->countryPhoneCodes);
        $contacts = [];
        $batchSize = 100;
        $contactIds = [];

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $country = $countries[array_rand($countries)];
            $faker = Faker::create($this->getLocaleForCountry($country));
            
            $phoneCode = $this->countryPhoneCodes[$country];
            $phoneNumber = $this->generatePhoneNumber($faker, $phoneCode);

            $contactData = [
                'uuid' => Str::uuid()->toString(),
                'organization_id' => $organizationId,
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'phone' => $phoneNumber,
                'email' => $faker->unique()->safeEmail(),
                'avatar' => null,
                'address' => $faker->address(),
                'is_favorite' => $faker->boolean(10) ? 1 : 0,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $contacts[] = $contactData;

            if (count($contacts) >= $batchSize) {
                DB::table('contacts')->insert($contacts);
                $contacts = [];
            }

            $bar->advance();
        }

        if (!empty($contacts)) {
            DB::table('contacts')->insert($contacts);
        }

        $bar->finish();
        $this->newLine();

        // Get all contact IDs
        return DB::table('contacts')
            ->where('organization_id', $organizationId)
            ->orderBy('id', 'desc')
            ->limit($count)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Generate team members
     */
    private function generateTeamMembers($organizationId, $count, $createdBy, $roleIds)
    {
        $faker = Faker::create();
        $teamMembers = [];
        $users = [];

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            // Create user
            $user = User::create([
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'email' => $faker->unique()->safeEmail(),
                'password' => bcrypt('password'), // Default password
                'role' => 'user',
                'status' => 1,
                'phone' => $faker->phoneNumber(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign random role (Manager or Agent)
            $roleId = $roleIds[array_rand($roleIds)];

            // Create team entry
            $team = Team::create([
                'uuid' => Str::uuid()->toString(),
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'organization_role_id' => $roleId,
                'status' => 'active',
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $teamMembers[] = [
                'user_id' => $user->id,
                'team_id' => $team->id,
                'role_id' => $roleId,
            ];

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $teamMembers;
    }

    /**
     * Generate chats
     */
    private function generateChats($organizationId, $count, $contactIds, $userIds)
    {
        $faker = Faker::create();
        $types = ['inbound', 'outbound'];

        // Ensure each contact gets at least one chat
        $totalContacts = count($contactIds);
        
        // If requested chat count is less than contact count, adjust to ensure each contact gets at least 1
        if ($count < $totalContacts) {
            $count = $totalContacts;
            $this->warn("Chat count adjusted to {$count} to ensure each contact has at least 1 chat.");
        }
        
        $remainingChats = $count - $totalContacts; // Remaining chats to distribute randomly
        
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        // Generate random timestamps over the last 6 months
        $startDate = now()->subMonths(6);
        $endDate = now();

        // Step 1: Give each contact at least one chat
        $contactIndex = 0;
        foreach ($contactIds as $contactId) {
            $type = $types[array_rand($types)];
            $userId = $type === 'outbound' ? $userIds[array_rand($userIds)] : null;

            // Generate random timestamp
            $createdAt = $faker->dateTimeBetween($startDate, $endDate);
            $createdAtCarbon = Carbon::parse($createdAt);

            // Get appropriate message
            $messages = $type === 'inbound' ? $this->inboundMessages : $this->outboundMessages;
            $message = $messages[array_rand($messages)];

            // Create metadata JSON based on type
            if ($type === 'inbound') {
                // Inbound format: {"from":"phone","id":"wamid...","timestamp":"unix","text":{"body":"message"},"type":"text"}
                $contact = DB::table('contacts')->where('id', $contactId)->first();
                $phoneNumber = $contact ? $contact->phone : $faker->numerify('254##########');
                // Remove + sign if present (inbound phone numbers don't have +)
                $phoneNumber = ltrim($phoneNumber, '+');
                
                // Generate realistic wam_id format: wamid.HBgM...base64...=
                // Example: wamid.HBgMMjU0NzIwMDU1ODE5FQIAEhgWM0VCMDZCOTU0MTlDNkJEMTFGNERFQwA=
                $randomBytes = random_bytes(32);
                $base64Part = base64_encode($randomBytes);
                // Remove padding and add it back at the end, uppercase the middle part
                $base64Part = rtrim($base64Part, '=');
                $wamId = 'wamid.' . strtoupper($base64Part) . '=';
                
                $metadata = json_encode([
                    'from' => $phoneNumber,
                    'id' => $wamId,
                    'timestamp' => (string)$createdAtCarbon->getTimestamp(),
                    'text' => [
                        'body' => $message
                    ],
                    'type' => 'text'
                ]);
            } else {
                // Outbound format: {"text":{"body":"message"},"type":"text"}
                $metadata = json_encode([
                    'text' => [
                        'body' => $message
                    ],
                    'type' => 'text'
                ]);
            }

            // Extract wam_id from metadata for inbound messages
            $wamId = null;
            if ($type === 'inbound') {
                $metadataArray = json_decode($metadata, true);
                $wamId = $metadataArray['id'] ?? null;
            }

            // Insert chat and get the ID
            $chatId = DB::table('chats')->insertGetId([
                'uuid' => Str::uuid()->toString(),
                'organization_id' => $organizationId,
                'wam_id' => $wamId,
                'contact_id' => $contactId,
                'user_id' => $userId,
                'type' => $type,
                'metadata' => $metadata,
                'media_id' => null,
                'status' => 'sent',
                'deleted_by' => null,
                'deleted_at' => null,
                'is_read' => $type === 'inbound' ? ($faker->boolean(70) ? 0 : 1) : 1, // 70% unread for inbound
                'created_at' => $createdAtCarbon->format('Y-m-d H:i:s'),
            ]);

            // Create corresponding ChatLog entry
            ChatLog::insert([
                'contact_id' => $contactId,
                'entity_type' => 'chat',
                'entity_id' => $chatId,
                'created_at' => $createdAtCarbon->utc()->format('Y-m-d H:i:s'),
            ]);

            $bar->advance();
            $contactIndex++;
        }

        // Step 2: Distribute remaining chats randomly across all contacts
        for ($i = 0; $i < $remainingChats; $i++) {
            $type = $types[array_rand($types)];
            $contactId = $contactIds[array_rand($contactIds)];
            $userId = $type === 'outbound' ? $userIds[array_rand($userIds)] : null;

            // Generate random timestamp
            $createdAt = $faker->dateTimeBetween($startDate, $endDate);
            $createdAtCarbon = Carbon::parse($createdAt);

            // Get appropriate message
            $messages = $type === 'inbound' ? $this->inboundMessages : $this->outboundMessages;
            $message = $messages[array_rand($messages)];

            // Create metadata JSON based on type
            if ($type === 'inbound') {
                // Inbound format: {"from":"phone","id":"wamid...","timestamp":"unix","text":{"body":"message"},"type":"text"}
                $contact = DB::table('contacts')->where('id', $contactId)->first();
                $phoneNumber = $contact ? $contact->phone : $faker->numerify('254##########');
                // Remove + sign if present (inbound phone numbers don't have +)
                $phoneNumber = ltrim($phoneNumber, '+');
                
                // Generate realistic wam_id format: wamid.HBgM...base64...=
                $randomBytes = random_bytes(32);
                $base64Part = base64_encode($randomBytes);
                $base64Part = rtrim($base64Part, '=');
                $wamId = 'wamid.' . strtoupper($base64Part) . '=';
                
                $metadata = json_encode([
                    'from' => $phoneNumber,
                    'id' => $wamId,
                    'timestamp' => (string)$createdAtCarbon->getTimestamp(),
                    'text' => [
                        'body' => $message
                    ],
                    'type' => 'text'
                ]);
            } else {
                // Outbound format: {"text":{"body":"message"},"type":"text"}
                $metadata = json_encode([
                    'text' => [
                        'body' => $message
                    ],
                    'type' => 'text'
                ]);
            }

            // Extract wam_id from metadata for inbound messages
            $wamId = null;
            if ($type === 'inbound') {
                $metadataArray = json_decode($metadata, true);
                $wamId = $metadataArray['id'] ?? null;
            }

            // Insert chat and get the ID
            $chatId = DB::table('chats')->insertGetId([
                'uuid' => Str::uuid()->toString(),
                'organization_id' => $organizationId,
                'wam_id' => $wamId,
                'contact_id' => $contactId,
                'user_id' => $userId,
                'type' => $type,
                'metadata' => $metadata,
                'media_id' => null,
                'status' => 'sent',
                'deleted_by' => null,
                'deleted_at' => null,
                'is_read' => $type === 'inbound' ? ($faker->boolean(70) ? 0 : 1) : 1,
                'created_at' => $createdAtCarbon->format('Y-m-d H:i:s'),
            ]);

            // Create corresponding ChatLog entry
            ChatLog::insert([
                'contact_id' => $contactId,
                'entity_type' => 'chat',
                'entity_id' => $chatId,
                'created_at' => $createdAtCarbon->utc()->format('Y-m-d H:i:s'),
            ]);

            $bar->advance();
        }

        // Update latest_chat_created_at for contacts
        $this->updateContactLatestChatDates($organizationId);

        $bar->finish();
        $this->newLine();
    }

    /**
     * Update latest_chat_created_at for contacts
     */
    private function updateContactLatestChatDates($organizationId)
    {
        DB::statement("
            UPDATE contacts c
            INNER JOIN (
                SELECT contact_id, MAX(created_at) as latest_chat
                FROM chats
                WHERE organization_id = ?
                GROUP BY contact_id
            ) latest ON c.id = latest.contact_id
            SET c.latest_chat_created_at = latest.latest_chat
            WHERE c.organization_id = ?
        ", [$organizationId, $organizationId]);
    }

    /**
     * Get Faker locale for country
     */
    private function getLocaleForCountry($countryCode)
    {
        $localeMap = [
            'SA' => 'ar_SA',
        ];

        return $localeMap[$countryCode] ?? 'ar_SA';
    }

    /**
     * Generate phone number with country code
     */
    private function generatePhoneNumber($faker, $phoneCode)
    {
        $code = ltrim($phoneCode, '+');
        $number = $faker->numerify('##########');
        return $phoneCode . $number;
    }
}
