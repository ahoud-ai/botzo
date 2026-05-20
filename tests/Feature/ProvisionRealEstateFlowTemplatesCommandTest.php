<?php

namespace Tests\Feature;

use App\Models\AutomationFlow;
use App\Models\ContactField;
use App\Models\ContactGroup;
use App\Services\AutomationFlows\AutomationFlowBuilderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class ProvisionRealEstateFlowTemplatesCommandTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_command_creates_three_publish_ready_real_estate_templates(): void
    {
        Storage::fake('local');

        [$user, $organization] = $this->createOwnerContext([], true);
        $organization->update(['name' => 'نويت العقارية']);

        $exitCode = Artisan::call('flowbuilder:provision-real-estate-templates', [
            'organization' => $organization->id,
        ]);

        $this->assertSame(0, $exitCode);

        $flows = AutomationFlow::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $flows);
        $this->assertSame([
            'نويت | تأهيل مشتري عقار',
            'نويت | حجز معاينة مشروع',
            'نويت | استقبال مالك عقار',
        ], $flows->pluck('name')->all());
        $this->assertTrue($flows->every(fn (AutomationFlow $flow) => $flow->status === 'draft'));
        $this->assertTrue($flows->every(fn (AutomationFlow $flow) => $flow->assets()->count() === 1));

        $this->assertSame([
            'اهتمام استثماري',
            'جديده',
            'حجوزات المعاينة',
            'قائمة شراء',
            'ملاك العقارات',
            'يحتاج متابعة',
        ], ContactGroup::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->pluck('name')
            ->all());

        $this->assertSame([
            'الغرض من العقار',
            'المشروع أو الحي',
            'الميزانية',
            'تفاصيل الاحتياج',
            'تفاصيل العقار',
            'موعد المعاينة',
            'نوع الخدمة المطلوبة',
            'نوع العقار',
        ], ContactField::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->pluck('name')
            ->all());

        $builder = app(AutomationFlowBuilderService::class);

        foreach ($flows as $flow) {
            $report = $builder->validateDraft($flow->fresh(['assets']), $organization->id);
            $this->assertTrue($report['valid'], $flow->name.' should be publish-ready.');
        }
    }
}
