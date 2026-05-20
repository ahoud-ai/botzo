<?php

namespace App\Services\AutomationFlows;

use App\Models\AutomationFlow;
use App\Models\ContactField;
use App\Models\ContactGroup;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AutomationFlowRealEstateTemplateProvisioner
{
    public function __construct(
        private readonly AutomationFlowBuilderService $builder,
        private readonly AutomationFlowAssetService $assets,
        private readonly AutomationFlowBuilderPolicyService $builderPolicy,
    ) {
    }

    public function provision(
        Organization $organization,
        ?int $userId = null,
        bool $replace = false,
        bool $publish = false,
    ): array {
        $resolvedUserId = $userId ?: $this->resolveActorUserId($organization);
        $support = $this->ensureSupportData($organization, $resolvedUserId);

        $report = [
            'organization' => [
                'id' => $organization->id,
                'uuid' => $organization->uuid,
                'name' => $organization->name,
            ],
            'fields' => $support['fields']->values()->map(fn (ContactField $field) => [
                'uuid' => $field->uuid,
                'name' => $field->name,
            ])->all(),
            'groups' => $support['groups']->values()->map(fn (ContactGroup $group) => [
                'uuid' => $group->uuid,
                'name' => $group->name,
            ])->all(),
            'created' => [],
            'skipped' => [],
        ];

        foreach ($this->templateDefinitions() as $definition) {
            $existing = AutomationFlow::query()
                ->where('organization_id', $organization->id)
                ->where('name', $definition['name'])
                ->latest('id')
                ->get();

            if ($existing->isNotEmpty() && !$replace) {
                $report['skipped'][] = [
                    'name' => $definition['name'],
                    'reason' => 'already_exists',
                    'existing_flow_uuid' => $existing->first()->uuid,
                ];
                continue;
            }

            if ($replace && $existing->isNotEmpty()) {
                $existing->each->delete();
            }

            $flow = $this->builder->create($organization->id, $resolvedUserId, [
                'name' => $definition['name'],
                'description' => $definition['description'],
                'goal_preset' => $definition['goal_preset'],
            ]);

            try {
                $assetUuid = $this->provisionAsset($flow, $definition['asset_path'], $resolvedUserId);
                $graph = $this->{$definition['graph_method']}($support, $assetUuid);
                $graph = $this->adaptGraphForBuilderPolicy($graph);
                $uiJson = $this->uiStateForTemplate($flow, $graph);

                $this->builder->update($flow, $organization->id, $resolvedUserId, [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'graph_json' => $graph,
                    'ui_json' => $uiJson,
                ]);

                $validation = $this->builder->validateDraft($flow->fresh(['assets']), $organization->id);

                if (!($validation['valid'] ?? false)) {
                    throw new RuntimeException(implode(' | ', $validation['errors'] ?? []));
                }

                if ($publish) {
                    $this->builder->publish($flow, $organization->id, $resolvedUserId);
                }

                $flow->refresh();

                $report['created'][] = [
                    'name' => $flow->name,
                    'uuid' => $flow->uuid,
                    'status' => $flow->status,
                    'asset_uuid' => $assetUuid,
                    'publish_ready' => true,
                ];
            } catch (\Throwable $exception) {
                $this->cleanupFlow($flow);

                throw $exception;
            }
        }

        return $report;
    }

    private function templateDefinitions(): array
    {
        return [
            [
                'name' => 'نويت | تأهيل مشتري عقار',
                'description' => 'قالب جاهز لتأهيل المشتري عبر صورة + قائمة + أزرار + حفظ الميزانية + تصنيف تلقائي.',
                'goal_preset' => 'sales_qualification',
                'asset_path' => public_path('images/hero/dashboard3.png'),
                'graph_method' => 'buyerQualificationGraph',
            ],
            [
                'name' => 'نويت | حجز معاينة مشروع',
                'description' => 'قالب حجز معاينة يلتقط المشروع والوقت المناسب ويضيف العميل للمجموعة المناسبة مع متابعة منظمة.',
                'goal_preset' => 'appointment_booking',
                'asset_path' => public_path('images/hero/dashboard.png'),
                'graph_method' => 'viewingBookingGraph',
            ],
            [
                'name' => 'نويت | استقبال مالك عقار',
                'description' => 'قالب مخصص لاستقبال الملاك وجمع نوع العقار والمنطقة والتفاصيل قبل التصنيف والمتابعة.',
                'goal_preset' => 'seller_intake',
                'asset_path' => public_path('images/hero/half-dash.png'),
                'graph_method' => 'ownerListingGraph',
            ],
        ];
    }

    private function resolveActorUserId(Organization $organization): int
    {
        $team = Team::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->first();

        if (!$team?->user_id) {
            throw new RuntimeException(__('No organization member found to own starter templates.'));
        }

        return (int) $team->user_id;
    }

    private function ensureSupportData(Organization $organization, int $userId): array
    {
        $fields = collect([
            'نوع الخدمة المطلوبة',
            'نوع العقار',
            'الميزانية',
            'المشروع أو الحي',
            'موعد المعاينة',
            'تفاصيل الاحتياج',
            'تفاصيل العقار',
            'الغرض من العقار',
        ])->mapWithKeys(function (string $name, int $index) use ($organization) {
            $field = ContactField::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $name,
                ],
                [
                    'position' => $index + 1,
                    'type' => 'text',
                    'value' => null,
                    'required' => 0,
                ]
            );

            return [$name => $field];
        });

        $groups = collect([
            'جديده',
            'قائمة شراء',
            'اهتمام استثماري',
            'حجوزات المعاينة',
            'ملاك العقارات',
            'يحتاج متابعة',
        ])->mapWithKeys(function (string $name) use ($organization, $userId) {
            $group = ContactGroup::query()->withTrashed()->firstOrNew([
                'organization_id' => $organization->id,
                'name' => $name,
            ]);

            if (!$group->exists) {
                $group->created_by = $userId;
            }

            if ($group->trashed()) {
                $group->deleted_at = null;
            }

            $group->save();

            return [$name => $group->fresh()];
        });

        return [
            'fields' => $fields,
            'groups' => $groups,
        ];
    }

    private function provisionAsset(AutomationFlow $flow, string $sourcePath, int $userId): string
    {
        if (!is_file($sourcePath)) {
            throw new RuntimeException("Template image not found: {$sourcePath}");
        }

        $uploadedFile = new UploadedFile(
            $sourcePath,
            basename($sourcePath),
            mime_content_type($sourcePath) ?: 'image/png',
            null,
            true
        );

        return $this->assets->store($flow, $uploadedFile, $userId, 'image')->uuid;
    }

    private function cleanupFlow(AutomationFlow $flow): void
    {
        $flow->loadMissing('assets');

        foreach ($flow->assets as $asset) {
            if ($asset->disk && $asset->path && Storage::disk($asset->disk)->exists($asset->path)) {
                Storage::disk($asset->disk)->delete($asset->path);
            }
            $asset->delete();
        }

        $flow->nodeSecrets()->delete();
        $flow->versions()->delete();
        $flow->delete();
    }

    private function uiStateForTemplate(AutomationFlow $flow, array $graph): array
    {
        $uiJson = $flow->ui_json ?? [];

        Arr::set($uiJson, 'selection.active_node_id', Arr::get($graph, 'start_node_id', 'trigger-1'));
        Arr::set($uiJson, 'canvas.expanded_node_id', Arr::get($graph, 'start_node_id', 'trigger-1'));
        Arr::set($uiJson, 'preview.selected_scenario', config('automation_flows.preview_default_scenario', 'main'));
        Arr::set($uiJson, 'preview.mode', 'whatsapp');
        Arr::set($uiJson, 'preview.collapsed', false);

        return $uiJson;
    }

    private function buyerQualificationGraph(array $support, string $assetUuid): array
    {
        $serviceField = $support['fields']->get('نوع الخدمة المطلوبة');
        $propertyField = $support['fields']->get('نوع العقار');
        $budgetField = $support['fields']->get('الميزانية');
        $buyersGroup = $support['groups']->get('قائمة شراء');
        $investGroup = $support['groups']->get('اهتمام استثماري');

        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                $this->triggerNode('trigger-1', 80, 160, 'any_incoming'),
                $this->mediaNode('media-hero', 360, 160, $assetUuid, 'مرحبًا بك في نويت العقارية'),
                $this->textNode('welcome-text', 680, 160, 'مرحبًا بك في نويت العقارية. اختر المسار المناسب وسنجهز لك المتابعة مباشرة.'),
                $this->listNode('service-list', 1020, 160, 'الخدمات المتاحة', 'اختر المسار المناسب للعميل.', 'يمكنك اختيار مسار واحد فقط', 'اختيار المسار', [
                    [
                        'title' => 'المسارات الرئيسية',
                        'rows' => [
                            ['id' => 'buy_home', 'title' => 'شراء سكني', 'description' => 'شقق وفلل للسكن'],
                            ['id' => 'invest', 'title' => 'استثمار عقاري', 'description' => 'فرص ذات عائد استثماري'],
                            ['id' => 'project_visit', 'title' => 'معاينة مشروع', 'description' => 'اختيار مشروع مناسب'],
                        ],
                    ],
                ]),
                $this->updateFieldNode('set-service-buy', 1360, 20, $serviceField->uuid, 'static', 'شراء سكني'),
                $this->updateFieldNode('set-service-invest', 1360, 160, $serviceField->uuid, 'static', 'استثمار عقاري'),
                $this->updateFieldNode('set-service-visit', 1360, 300, $serviceField->uuid, 'static', 'معاينة مشروع'),
                $this->buttonsNode('property-buttons', 1700, 160, 'نوع العقار', 'ما نوع العقار الأقرب لطلبك؟', 'اختر خيارًا واحدًا للمتابعة', [
                    ['id' => 'apartment', 'title' => 'شقة'],
                    ['id' => 'villa', 'title' => 'فيلا'],
                    ['id' => 'land', 'title' => 'أرض'],
                ]),
                $this->updateFieldNode('set-property-apartment', 2040, 20, $propertyField->uuid, 'static', 'شقة'),
                $this->updateFieldNode('set-property-villa', 2040, 160, $propertyField->uuid, 'static', 'فيلا'),
                $this->updateFieldNode('set-property-land', 2040, 300, $propertyField->uuid, 'static', 'أرض'),
                $this->textNode('ask-budget', 2380, 160, 'اكتب الميزانية التقريبية أو نطاق السعر حتى نرشح لك الخيارات الأنسب.'),
                $this->saveReplyNode('save-budget', 2700, 160, $budgetField->uuid),
                $this->conditionNode('service-condition', 3020, 160, 'contact_field', 'equals', 'استثمار عقاري', $serviceField->uuid),
                $this->addGroupNode('add-invest-group', 3360, 40, $investGroup->uuid),
                $this->addGroupNode('add-buyers-group', 3360, 260, $buyersGroup->uuid),
                $this->delayNode('delay-followup', 3700, 160, 5),
                $this->textNode('final-followup', 4020, 160, 'تم تسجيل طلبك بنجاح، وسيصلك ترشيح مناسب من فريق نويت خلال وقت قصير.'),
                $this->endNode('end-1', 4340, 160),
            ],
            'edges' => [
                $this->edge('edge-trigger-media', 'trigger-1', 'media-hero'),
                $this->edge('edge-media-welcome', 'media-hero', 'welcome-text'),
                $this->edge('edge-welcome-list', 'welcome-text', 'service-list'),
                $this->edge('edge-list-buy', 'service-list', 'set-service-buy', 'buy_home'),
                $this->edge('edge-list-invest', 'service-list', 'set-service-invest', 'invest'),
                $this->edge('edge-list-visit', 'service-list', 'set-service-visit', 'project_visit'),
                $this->edge('edge-buy-buttons', 'set-service-buy', 'property-buttons'),
                $this->edge('edge-invest-buttons', 'set-service-invest', 'property-buttons'),
                $this->edge('edge-visit-buttons', 'set-service-visit', 'property-buttons'),
                $this->edge('edge-buttons-apartment', 'property-buttons', 'set-property-apartment', 'apartment'),
                $this->edge('edge-buttons-villa', 'property-buttons', 'set-property-villa', 'villa'),
                $this->edge('edge-buttons-land', 'property-buttons', 'set-property-land', 'land'),
                $this->edge('edge-apartment-budget', 'set-property-apartment', 'ask-budget'),
                $this->edge('edge-villa-budget', 'set-property-villa', 'ask-budget'),
                $this->edge('edge-land-budget', 'set-property-land', 'ask-budget'),
                $this->edge('edge-budget-save', 'ask-budget', 'save-budget'),
                $this->edge('edge-save-condition', 'save-budget', 'service-condition'),
                $this->edge('edge-condition-invest', 'service-condition', 'add-invest-group', 'matched'),
                $this->edge('edge-condition-buy', 'service-condition', 'add-buyers-group', 'unmatched'),
                $this->edge('edge-invest-delay', 'add-invest-group', 'delay-followup'),
                $this->edge('edge-buy-delay', 'add-buyers-group', 'delay-followup'),
                $this->edge('edge-delay-final', 'delay-followup', 'final-followup'),
                $this->edge('edge-final-end', 'final-followup', 'end-1'),
            ],
        ];
    }

    private function viewingBookingGraph(array $support, string $assetUuid): array
    {
        $projectField = $support['fields']->get('المشروع أو الحي');
        $timeField = $support['fields']->get('موعد المعاينة');
        $detailsField = $support['fields']->get('تفاصيل الاحتياج');
        $newGroup = $support['groups']->get('جديده');
        $bookingGroup = $support['groups']->get('حجوزات المعاينة');
        $followupGroup = $support['groups']->get('يحتاج متابعة');

        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                $this->triggerNode('trigger-1', 80, 160, 'keyword_match', ['معاينة', 'زيارة', 'حجز']),
                $this->mediaNode('media-preview', 360, 160, $assetUuid, 'مسار حجز المعاينة - نويت العقارية'),
                $this->textNode('booking-intro', 680, 160, 'سنرتب لك معاينة بسرعة. اختر المشروع أولًا ثم الوقت المناسب.'),
                $this->listNode('project-list', 1020, 160, 'المشاريع المتاحة', 'اختر المشروع أو المسار المناسب.', 'سيتم تأكيد الحجز بعد اختيار الوقت', 'اختيار المشروع', [
                    [
                        'title' => 'المشاريع',
                        'rows' => [
                            ['id' => 'rose', 'title' => 'مشروع روز', 'description' => 'وحدات جاهزة للمعاينة'],
                            ['id' => 'lavender', 'title' => 'مشروع لافندر', 'description' => 'فلل وشقق متنوعة'],
                            ['id' => 'advisor', 'title' => 'مستشار مباشر', 'description' => 'احتياج خاص أو سريع'],
                        ],
                    ],
                ]),
                $this->updateFieldNode('set-project-rose', 1360, 20, $projectField->uuid, 'static', 'مشروع روز'),
                $this->updateFieldNode('set-project-lavender', 1360, 160, $projectField->uuid, 'static', 'مشروع لافندر'),
                $this->updateFieldNode('set-project-advisor', 1360, 300, $projectField->uuid, 'static', 'مستشار مباشر'),
                $this->buttonsNode('time-buttons', 1700, 160, 'وقت المعاينة', 'ما الوقت الأنسب للمعاينة؟', 'يمكن تعديل الموعد لاحقًا', [
                    ['id' => 'morning', 'title' => 'صباحًا'],
                    ['id' => 'evening', 'title' => 'مساءً'],
                ]),
                $this->updateFieldNode('set-time-morning', 2040, 80, $timeField->uuid, 'static', 'صباحًا'),
                $this->updateFieldNode('set-time-evening', 2040, 240, $timeField->uuid, 'static', 'مساءً'),
                $this->textNode('ask-booking-details', 2380, 160, 'اكتب اليوم المناسب أو أي ملاحظة مهمة للمعاينة مثل عدد الزوار أو نوع الوحدة.'),
                $this->saveReplyNode('save-booking-details', 2700, 160, $detailsField->uuid),
                $this->conditionNode('details-filled-condition', 3020, 160, 'contact_field', 'filled', '', $detailsField->uuid),
                $this->removeGroupNode('remove-new-group', 3360, 20, $newGroup->uuid),
                $this->addGroupNode('add-booking-group', 3680, 20, $bookingGroup->uuid),
                $this->textNode('booking-confirmed', 4000, 20, 'تم تسجيل طلب المعاينة بنجاح، وسيؤكد الفريق الموعد النهائي معك قريبًا.'),
                $this->delayNode('booking-reminder-delay', 4320, 20, 10),
                $this->textNode('booking-reminder', 4640, 20, 'تذكير: إذا رغبت في تعديل الموعد، رد بكلمة معاينة في أي وقت.'),
                $this->addGroupNode('add-followup-group', 3360, 300, $followupGroup->uuid),
                $this->textNode('booking-missing-details', 3680, 300, 'نحتاج تفاصيل أكثر لتأكيد المعاينة. سيصلك تواصل من المستشار لمتابعة الطلب.'),
                $this->endNode('end-1', 4960, 20),
                $this->endNode('end-2', 4000, 300),
            ],
            'edges' => [
                $this->edge('edge-trigger-media', 'trigger-1', 'media-preview'),
                $this->edge('edge-media-intro', 'media-preview', 'booking-intro'),
                $this->edge('edge-intro-list', 'booking-intro', 'project-list'),
                $this->edge('edge-list-rose', 'project-list', 'set-project-rose', 'rose'),
                $this->edge('edge-list-lavender', 'project-list', 'set-project-lavender', 'lavender'),
                $this->edge('edge-list-advisor', 'project-list', 'set-project-advisor', 'advisor'),
                $this->edge('edge-rose-time', 'set-project-rose', 'time-buttons'),
                $this->edge('edge-lavender-time', 'set-project-lavender', 'time-buttons'),
                $this->edge('edge-advisor-time', 'set-project-advisor', 'time-buttons'),
                $this->edge('edge-time-morning', 'time-buttons', 'set-time-morning', 'morning'),
                $this->edge('edge-time-evening', 'time-buttons', 'set-time-evening', 'evening'),
                $this->edge('edge-morning-details', 'set-time-morning', 'ask-booking-details'),
                $this->edge('edge-evening-details', 'set-time-evening', 'ask-booking-details'),
                $this->edge('edge-details-save', 'ask-booking-details', 'save-booking-details'),
                $this->edge('edge-save-condition', 'save-booking-details', 'details-filled-condition'),
                $this->edge('edge-condition-matched', 'details-filled-condition', 'remove-new-group', 'matched'),
                $this->edge('edge-condition-unmatched', 'details-filled-condition', 'add-followup-group', 'unmatched'),
                $this->edge('edge-remove-add-booking', 'remove-new-group', 'add-booking-group'),
                $this->edge('edge-booking-confirm', 'add-booking-group', 'booking-confirmed'),
                $this->edge('edge-confirm-delay', 'booking-confirmed', 'booking-reminder-delay'),
                $this->edge('edge-delay-reminder', 'booking-reminder-delay', 'booking-reminder'),
                $this->edge('edge-reminder-end', 'booking-reminder', 'end-1'),
                $this->edge('edge-followup-text', 'add-followup-group', 'booking-missing-details'),
                $this->edge('edge-followup-end', 'booking-missing-details', 'end-2'),
            ],
        ];
    }

    private function ownerListingGraph(array $support, string $assetUuid): array
    {
        $purposeField = $support['fields']->get('الغرض من العقار');
        $propertyField = $support['fields']->get('نوع العقار');
        $locationField = $support['fields']->get('المشروع أو الحي');
        $detailsField = $support['fields']->get('تفاصيل العقار');
        $ownersGroup = $support['groups']->get('ملاك العقارات');
        $followupGroup = $support['groups']->get('يحتاج متابعة');

        return [
            'start_node_id' => 'trigger-1',
            'nodes' => [
                $this->triggerNode('trigger-1', 80, 160, 'keyword_match', ['اعرض', 'أعرض', 'أبيع', 'تسويق']),
                $this->mediaNode('media-owner', 360, 160, $assetUuid, 'استقبل طلبات الملاك بسهولة عبر نويت العقارية'),
                $this->textNode('owner-intro', 680, 160, 'إذا كنت ترغب في عرض عقارك معنا، اختر الغرض أولًا ثم أكمل البيانات الأساسية.'),
                $this->buttonsNode('purpose-buttons', 1020, 160, 'غرض العقار', 'هل ترغب في بيع العقار أم تأجيره؟', 'اختر الغرض المناسب', [
                    ['id' => 'sell', 'title' => 'بيع'],
                    ['id' => 'rent', 'title' => 'تأجير'],
                ]),
                $this->updateFieldNode('set-purpose-sell', 1360, 80, $purposeField->uuid, 'static', 'بيع'),
                $this->updateFieldNode('set-purpose-rent', 1360, 240, $purposeField->uuid, 'static', 'تأجير'),
                $this->listNode('property-list', 1700, 160, 'نوع العقار', 'اختر نوع العقار الذي ترغب في عرضه.', 'سنتابع معك بناءً على النوع', 'اختيار النوع', [
                    [
                        'title' => 'الأنواع',
                        'rows' => [
                            ['id' => 'owner_apartment', 'title' => 'شقة', 'description' => 'وحدة سكنية أو استثمارية'],
                            ['id' => 'owner_villa', 'title' => 'فيلا', 'description' => 'فيلا مستقلة أو متصلة'],
                            ['id' => 'owner_land', 'title' => 'أرض', 'description' => 'أرض سكنية أو استثمارية'],
                        ],
                    ],
                ]),
                $this->updateFieldNode('set-owner-type-apartment', 2040, 20, $propertyField->uuid, 'static', 'شقة'),
                $this->updateFieldNode('set-owner-type-villa', 2040, 160, $propertyField->uuid, 'static', 'فيلا'),
                $this->updateFieldNode('set-owner-type-land', 2040, 300, $propertyField->uuid, 'static', 'أرض'),
                $this->textNode('ask-owner-location', 2380, 160, 'اكتب الحي أو اسم المشروع أو المدينة الخاصة بالعقار.'),
                $this->saveReplyNode('save-owner-location', 2700, 160, $locationField->uuid),
                $this->textNode('ask-owner-details', 3020, 160, 'اكتب أهم التفاصيل مثل المساحة والسعر المطلوب وعدد الغرف إن وُجد.'),
                $this->saveReplyNode('save-owner-details', 3340, 160, $detailsField->uuid),
                $this->conditionNode('owner-details-condition', 3660, 160, 'contact_field', 'filled', '', $detailsField->uuid),
                $this->addGroupNode('add-owner-group', 4000, 60, $ownersGroup->uuid),
                $this->textNode('owner-success', 4320, 60, 'تم استلام طلبك بنجاح، وسيقوم فريق نويت بمراجعة العرض والتواصل معك.'),
                $this->addGroupNode('owner-followup-group', 4000, 260, $followupGroup->uuid),
                $this->textNode('owner-missing-details', 4320, 260, 'وصلنا طلبك لكننا نحتاج تفاصيل أكثر عن العقار حتى نتمكن من التسويق له بشكل صحيح.'),
                $this->endNode('end-1', 4640, 60),
                $this->endNode('end-2', 4640, 260),
            ],
            'edges' => [
                $this->edge('edge-trigger-media', 'trigger-1', 'media-owner'),
                $this->edge('edge-media-intro', 'media-owner', 'owner-intro'),
                $this->edge('edge-intro-buttons', 'owner-intro', 'purpose-buttons'),
                $this->edge('edge-purpose-sell', 'purpose-buttons', 'set-purpose-sell', 'sell'),
                $this->edge('edge-purpose-rent', 'purpose-buttons', 'set-purpose-rent', 'rent'),
                $this->edge('edge-sell-list', 'set-purpose-sell', 'property-list'),
                $this->edge('edge-rent-list', 'set-purpose-rent', 'property-list'),
                $this->edge('edge-list-apartment', 'property-list', 'set-owner-type-apartment', 'owner_apartment'),
                $this->edge('edge-list-villa', 'property-list', 'set-owner-type-villa', 'owner_villa'),
                $this->edge('edge-list-land', 'property-list', 'set-owner-type-land', 'owner_land'),
                $this->edge('edge-type-location-apartment', 'set-owner-type-apartment', 'ask-owner-location'),
                $this->edge('edge-type-location-villa', 'set-owner-type-villa', 'ask-owner-location'),
                $this->edge('edge-type-location-land', 'set-owner-type-land', 'ask-owner-location'),
                $this->edge('edge-location-save', 'ask-owner-location', 'save-owner-location'),
                $this->edge('edge-save-ask-details', 'save-owner-location', 'ask-owner-details'),
                $this->edge('edge-details-save', 'ask-owner-details', 'save-owner-details'),
                $this->edge('edge-details-condition', 'save-owner-details', 'owner-details-condition'),
                $this->edge('edge-condition-owner', 'owner-details-condition', 'add-owner-group', 'matched'),
                $this->edge('edge-condition-followup', 'owner-details-condition', 'owner-followup-group', 'unmatched'),
                $this->edge('edge-owner-success', 'add-owner-group', 'owner-success'),
                $this->edge('edge-followup-text', 'owner-followup-group', 'owner-missing-details'),
                $this->edge('edge-owner-end', 'owner-success', 'end-1'),
                $this->edge('edge-followup-end', 'owner-missing-details', 'end-2'),
            ],
        ];
    }

    private function adaptGraphForBuilderPolicy(array $graph): array
    {
        $nodes = collect(Arr::get($graph, 'nodes', []));
        $edges = collect(Arr::get($graph, 'edges', []));

        $blockedNodeIds = $nodes
            ->filter(fn (array $node) => !$this->builderPolicy->allowsNodeType((string) Arr::get($node, 'type', '')))
            ->pluck('id')
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (string) $id)
            ->values();

        if ($blockedNodeIds->isEmpty()) {
            return $graph;
        }

        $blockedLookup = $blockedNodeIds->flip();
        $bypassEdges = collect();

        foreach ($blockedNodeIds as $blockedNodeId) {
            $incomingEdges = $edges
                ->filter(fn (array $edge) => (string) Arr::get($edge, 'target_id', '') === $blockedNodeId)
                ->values();
            $outgoingEdges = $edges
                ->filter(fn (array $edge) => (string) Arr::get($edge, 'source_id', '') === $blockedNodeId)
                ->values();

            foreach ($incomingEdges as $incomingEdge) {
                foreach ($outgoingEdges as $outgoingEdge) {
                    $sourceId = (string) Arr::get($incomingEdge, 'source_id', '');
                    $targetId = (string) Arr::get($outgoingEdge, 'target_id', '');

                    if ($sourceId === '' || $targetId === '' || $blockedLookup->has($sourceId) || $blockedLookup->has($targetId)) {
                        continue;
                    }

                    $branch = (string) Arr::get($incomingEdge, 'branch', 'default');
                    if ($branch === 'default') {
                        $branch = (string) Arr::get($outgoingEdge, 'branch', 'default');
                    }

                    $bypassEdges->push([
                        'id' => sprintf('edge-bypass-%s-%s-%d', $sourceId, $targetId, $bypassEdges->count() + 1),
                        'source_id' => $sourceId,
                        'target_id' => $targetId,
                        'branch' => $branch === '' ? 'default' : $branch,
                    ]);
                }
            }
        }

        $sanitizedNodes = $nodes
            ->reject(fn (array $node) => $blockedLookup->has((string) Arr::get($node, 'id', '')))
            ->values();
        $sanitizedEdges = $edges
            ->reject(fn (array $edge) => $blockedLookup->has((string) Arr::get($edge, 'source_id', '')) || $blockedLookup->has((string) Arr::get($edge, 'target_id', '')))
            ->values()
            ->concat($bypassEdges)
            ->unique(fn (array $edge) => sprintf('%s|%s|%s', Arr::get($edge, 'source_id', ''), Arr::get($edge, 'target_id', ''), Arr::get($edge, 'branch', 'default')))
            ->values()
            ->map(function (array $edge, int $index): array {
                return [
                    'id' => (string) Arr::get($edge, 'id', 'edge-'.$index),
                    'source_id' => (string) Arr::get($edge, 'source_id', ''),
                    'target_id' => (string) Arr::get($edge, 'target_id', ''),
                    'branch' => (string) Arr::get($edge, 'branch', 'default'),
                ];
            })
            ->all();

        $startNodeId = (string) Arr::get($graph, 'start_node_id', '');
        if ($blockedLookup->has($startNodeId)) {
            $startNodeId = (string) Arr::get($sanitizedNodes->firstWhere('type', 'trigger'), 'id', '');

            if ($startNodeId === '') {
                $startNodeId = (string) Arr::get($sanitizedNodes->first(), 'id', '');
            }
        }

        return array_merge($graph, [
            'start_node_id' => $startNodeId,
            'nodes' => $sanitizedNodes->all(),
            'edges' => $sanitizedEdges,
        ]);
    }

    private function triggerNode(string $id, int $x, int $y, string $matchMode, array $keywords = []): array
    {
        return [
            'id' => $id,
            'type' => 'trigger',
            'position' => ['x' => $x, 'y' => $y],
            'config' => [
                'match_mode' => $matchMode,
                'keywords' => $keywords,
            ],
            'ui' => ['expanded' => true],
        ];
    }

    private function mediaNode(string $id, int $x, int $y, string $assetUuid, string $caption): array
    {
        return [
            'id' => $id,
            'type' => 'send_media',
            'position' => ['x' => $x, 'y' => $y],
            'config' => [
                'media_type' => 'image',
                'asset_id' => $assetUuid,
                'caption' => $caption,
            ],
            'ui' => ['expanded' => false],
        ];
    }

    private function textNode(string $id, int $x, int $y, string $text): array
    {
        return [
            'id' => $id,
            'type' => 'send_text',
            'position' => ['x' => $x, 'y' => $y],
            'config' => [
                'text' => $text,
            ],
            'ui' => ['expanded' => false],
        ];
    }

    private function buttonsNode(string $id, int $x, int $y, string $header, string $body, string $footer, array $buttons): array
    {
        return [
            'id' => $id,
            'type' => 'send_buttons',
            'position' => ['x' => $x, 'y' => $y],
            'config' => [
                'header' => $header,
                'body' => $body,
                'footer' => $footer,
                'buttons' => $buttons,
            ],
            'ui' => ['expanded' => false],
        ];
    }

    private function listNode(
        string $id,
        int $x,
        int $y,
        string $header,
        string $body,
        string $footer,
        string $buttonLabel,
        array $sections,
    ): array {
        return [
            'id' => $id,
            'type' => 'send_list',
            'position' => ['x' => $x, 'y' => $y],
            'config' => [
                'header' => $header,
                'body' => $body,
                'footer' => $footer,
                'button_label' => $buttonLabel,
                'sections' => $sections,
            ],
            'ui' => ['expanded' => false],
        ];
    }

    private function saveReplyNode(string $id, int $x, int $y, string $fieldUuid): array
    {
        return [
            'id' => $id,
            'type' => 'save_reply_to_field',
            'position' => ['x' => $x, 'y' => $y],
            'config' => [
                'field_uuid' => $fieldUuid,
            ],
            'ui' => ['expanded' => false],
        ];
    }

    private function updateFieldNode(string $id, int $x, int $y, string $fieldUuid, string $mode, string $value): array
    {
        return [
            'id' => $id,
            'type' => 'update_contact_field',
            'position' => ['x' => $x, 'y' => $y],
            'config' => [
                'field_uuid' => $fieldUuid,
                'mode' => $mode,
                'value' => $value,
            ],
            'ui' => ['expanded' => false],
        ];
    }

    private function conditionNode(
        string $id,
        int $x,
        int $y,
        string $source,
        string $operator,
        string $value,
        ?string $fieldUuid = null,
    ): array {
        return [
            'id' => $id,
            'type' => 'condition',
            'position' => ['x' => $x, 'y' => $y],
            'config' => array_filter([
                'source' => $source,
                'operator' => $operator,
                'value' => $value,
                'field_uuid' => $fieldUuid,
            ], static fn ($item) => $item !== null),
            'ui' => ['expanded' => false],
        ];
    }

    private function addGroupNode(string $id, int $x, int $y, string $groupUuid): array
    {
        return [
            'id' => $id,
            'type' => 'add_to_group',
            'position' => ['x' => $x, 'y' => $y],
            'config' => [
                'group_uuid' => $groupUuid,
            ],
            'ui' => ['expanded' => false],
        ];
    }

    private function removeGroupNode(string $id, int $x, int $y, string $groupUuid): array
    {
        return [
            'id' => $id,
            'type' => 'remove_from_group',
            'position' => ['x' => $x, 'y' => $y],
            'config' => [
                'group_uuid' => $groupUuid,
            ],
            'ui' => ['expanded' => false],
        ];
    }

    private function delayNode(string $id, int $x, int $y, int $minutes): array
    {
        return [
            'id' => $id,
            'type' => 'delay',
            'position' => ['x' => $x, 'y' => $y],
            'config' => [
                'minutes' => $minutes,
            ],
            'ui' => ['expanded' => false],
        ];
    }

    private function endNode(string $id, int $x, int $y): array
    {
        return [
            'id' => $id,
            'type' => 'end',
            'position' => ['x' => $x, 'y' => $y],
            'config' => [],
            'ui' => ['expanded' => false],
        ];
    }

    private function edge(string $id, string $sourceId, string $targetId, string $branch = 'default'): array
    {
        return [
            'id' => $id,
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'branch' => $branch,
        ];
    }
}
