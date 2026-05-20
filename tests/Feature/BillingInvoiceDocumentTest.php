<?php

namespace Tests\Feature;

use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingTransaction;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\BillingInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillingInvoiceDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_owner_can_open_invoice_page_and_preview_document_without_gateway_branding(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization($user, ['name' => 'Botzo Parent']);
        $this->attachOwner($user, $organization);

        [$invoice] = $this->createInvoiceWithPayment($organization, 'Visa');

        $invoiceNumber = app(BillingInvoiceService::class)->invoiceNumber($invoice);

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get(route('user.billing.invoices.show', ['invoice' => $invoice->uuid]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Billing/InvoiceShow')
            ->where('invoice.invoice_number', $invoiceNumber)
            ->where('invoice.payment.method_label', 'Visa')
            ->where('invoice.subscription.plan_name', $invoice->plan?->name)
            ->where('invoice.subscription.period', 'Monthly')
            ->where('invoice.items.0.label', $invoice->plan?->name)
            ->where('invoice.items.0.description', fn ($value) => is_string($value)
                && str_contains($value, (string) $invoice->plan?->name)
                && str_contains($value, 'Monthly'))
            ->has('printUrl')
            ->has('downloadUrl')
        );

        $preview = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get(route('user.billing.invoices.preview', ['invoice' => $invoice->uuid]));

        $preview->assertOk();
        $preview->assertSee($invoiceNumber);
        $preview->assertSee('Visa');
        $preview->assertDontSee('Moyasar');
    }

    public function test_branch_can_open_parent_invoice_page(): void
    {
        $user = $this->createUser();
        $parent = $this->createOrganization($user, ['name' => 'Parent Company']);
        $branch = $this->createOrganization($user, [
            'name' => 'Parent Branch',
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $this->attachOwner($user, $parent);
        $this->attachOwner($user, $branch);

        [$invoice] = $this->createInvoiceWithPayment($parent, 'Apple Pay');

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $branch->id])
            ->get(route('user.billing.invoices.show', ['invoice' => $invoice->uuid]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('User/Billing/InvoiceShow')
            ->where('invoice.payment.method_label', 'Apple Pay')
            ->where('invoice.customer.name', 'Parent Company')
        );
    }

    public function test_admin_can_open_global_invoice_page_and_preview_document(): void
    {
        $user = $this->createUser([
            'role' => 'admin',
            'email' => 'admin+'.Str::random(8).'@example.com',
        ]);
        $organization = $this->createOrganization($user, ['name' => 'Admin Billing Company']);
        $this->attachOwner($user, $organization);

        [$invoice] = $this->createInvoiceWithPayment($organization, 'Apple Pay');

        $response = $this->actingAs($user, 'admin')
            ->get(route('payment-logs.invoices.show', ['invoice' => $invoice->uuid]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Billing/InvoiceShow')
            ->where('invoice.invoice_number', app(BillingInvoiceService::class)->invoiceNumber($invoice))
            ->where('invoice.payment.method_label', 'Apple Pay')
            ->has('printUrl')
            ->has('downloadUrl')
        );

        $preview = $this->actingAs($user, 'admin')
            ->get(route('payment-logs.invoices.preview', ['invoice' => $invoice->uuid]));

        $preview->assertOk();
        $preview->assertSee(app(BillingInvoiceService::class)->invoiceNumber($invoice));
        $preview->assertSee('Apple Pay');
        $preview->assertDontSee('Moyasar');
    }

    public function test_invoice_document_direction_follows_application_locale_and_ignores_query_override(): void
    {
        $user = $this->createUser([
            'role' => 'admin',
            'email' => 'direction-admin+'.Str::random(8).'@example.com',
        ]);
        $organization = $this->createOrganization($user, ['name' => 'Direction Company']);
        $this->attachOwner($user, $organization);

        [$invoice] = $this->createInvoiceWithPayment($organization, 'Visa');

        $response = $this->actingAs($user, 'admin')
            ->get(route('payment-logs.invoices.preview', ['invoice' => $invoice->uuid, 'dir' => 'rtl']));

        $response->assertOk();
        $response->assertSee('dir="ltr"', false);
        $response->assertDontSee('lang="en" dir="rtl"', false);
    }

    public function test_arabic_invoice_preview_and_pdf_routes_render_successfully(): void
    {
        $previousLocale = app()->getLocale();
        app()->setLocale('ar');
        config(['app.locale' => 'ar']);

        try {
            $user = $this->createUser([
                'role' => 'admin',
                'email' => 'arabic-invoice-admin+'.Str::random(8).'@example.com',
                'language' => 'ar',
            ]);
            $organization = $this->createOrganization($user, ['name' => 'شركة عربية']);
            $this->attachOwner($user, $organization);

            [$invoice] = $this->createInvoiceWithPayment($organization, 'Visa');

            $preview = $this->actingAs($user, 'admin')
                ->withSession(['locale' => 'ar'])
                ->get(route('payment-logs.invoices.preview', ['invoice' => $invoice->uuid]));

            $preview->assertOk();
            $preview->assertSee('lang="ar" dir="rtl"', false);

            $pdf = $this->actingAs($user, 'admin')
                ->withSession(['locale' => 'ar'])
                ->get(route('payment-logs.invoices.print', ['invoice' => $invoice->uuid]));

            $pdf->assertOk();
            $pdf->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdf->content());
            $this->assertGreaterThanOrEqual(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf->content()));
        } finally {
            app()->setLocale($previousLocale);
            config(['app.locale' => $previousLocale]);
        }
    }

    public function test_arabic_pdf_builder_keeps_invoice_within_reasonable_page_count(): void
    {
        $previousLocale = app()->getLocale();
        app()->setLocale('ar');
        config(['app.locale' => 'ar']);

        try {
            $user = $this->createUser([
                'role' => 'admin',
                'email' => 'arabic-pagecount-admin+'.Str::random(8).'@example.com',
                'language' => 'ar',
            ]);
            $organization = $this->createOrganization($user, ['name' => 'شركة صفحة واحدة']);
            $this->attachOwner($user, $organization);

            [$invoice] = $this->createInvoiceWithPayment($organization, 'Visa');
            $document = app(BillingInvoiceService::class)->documentForAdmin($invoice->uuid);

            $reflection = new \ReflectionClass(BillingInvoiceService::class);
            $engineMethod = $reflection->getMethod('makePdfEngine');
            $engineMethod->setAccessible(true);
            $htmlMethod = $reflection->getMethod('buildPdfHtmlDocument');
            $htmlMethod->setAccessible(true);

            $service = app(BillingInvoiceService::class);
            $mpdf = $engineMethod->invoke($service);
            $html = $htmlMethod->invoke($service, [
                'invoice' => $document,
                'title' => __('Invoice'),
            ]);

            $mpdf->WriteHTML($html);

            $this->assertLessThanOrEqual(3, $mpdf->page);
        } finally {
            app()->setLocale($previousLocale);
            config(['app.locale' => $previousLocale]);
        }
    }

    public function test_invoice_download_streams_document_without_gateway_branding(): void
    {
        $user = $this->createUser([
            'role' => 'admin',
            'email' => 'download-admin+'.Str::random(8).'@example.com',
        ]);
        $organization = $this->createOrganization($user, ['name' => 'Download Billing Company']);
        $this->attachOwner($user, $organization);

        [$invoice] = $this->createInvoiceWithPayment($organization, 'Visa');

        $response = $this->actingAs($user, 'admin')
            ->get(route('payment-logs.invoices.download', ['invoice' => $invoice->uuid]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->content());
        $this->assertGreaterThanOrEqual(1, preg_match_all('/\/Type\s*\/Page\b/', $response->content()));
    }

    public function test_invoice_print_route_returns_inline_pdf_document(): void
    {
        $user = $this->createUser([
            'role' => 'admin',
            'email' => 'print-admin+'.Str::random(8).'@example.com',
        ]);
        $organization = $this->createOrganization($user, ['name' => 'Print Billing Company']);
        $this->attachOwner($user, $organization);

        [$invoice] = $this->createInvoiceWithPayment($organization, 'Visa');

        $response = $this->actingAs($user, 'admin')
            ->get(route('payment-logs.invoices.print', ['invoice' => $invoice->uuid]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline;', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->content());
        $this->assertGreaterThanOrEqual(1, preg_match_all('/\/Type\s*\/Page\b/', $response->content()));
    }

    public function test_previous_admin_billing_route_renders_the_unified_payment_logs_hub(): void
    {
        $user = $this->createUser([
            'role' => 'admin',
            'email' => 'billing-hub-admin+'.Str::random(8).'@example.com',
        ]);

        $response = $this->actingAs($user, 'admin')->get('/admin/billing');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Payment/Index')
            ->where('title', 'Invoices')
            ->where('activeView', 'invoices')
            ->has('invoiceRows.data')
            ->where('billingActivity', null)
        );
    }

    public function test_previous_admin_payment_logs_underscore_route_renders_the_unified_payment_logs_hub(): void
    {
        $user = $this->createUser([
            'role' => 'admin',
            'email' => 'billing-hub-previous+'.Str::random(8).'@example.com',
        ]);

        $response = $this->actingAs($user, 'admin')->get('/admin/payment_logs');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Payment/Index')
            ->where('title', 'Invoices')
            ->where('activeView', 'invoices')
            ->has('invoiceRows.data')
            ->where('billingActivity', null)
        );
    }

    public function test_admin_payment_logs_hub_renders_the_new_invoice_documents_screen(): void
    {
        $user = $this->createUser([
            'role' => 'admin',
            'email' => 'payment-logs-admin+'.Str::random(8).'@example.com',
        ]);

        $response = $this->actingAs($user, 'admin')->get('/admin/payment-logs');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Payment/Index')
            ->where('title', 'Invoices')
            ->where('activeView', 'invoices')
            ->has('invoiceRows.data')
            ->where('billingActivity', null)
        );
    }

    public function test_admin_payment_logs_hub_filters_invoice_register_by_organization(): void
    {
        $admin = $this->createUser([
            'role' => 'admin',
            'email' => 'invoice-filter-admin+'.Str::random(8).'@example.com',
        ]);

        $alpha = $this->createOrganization($admin, ['name' => 'Alpha Billing']);
        $beta = $this->createOrganization($admin, ['name' => 'Beta Billing']);

        $this->createInvoiceWithPayment($alpha, 'Visa');
        $this->createInvoiceWithPayment($beta, 'Apple Pay');

        $response = $this->actingAs($admin, 'admin')->get('/admin/payment-logs?organization_uuid=' . urlencode((string) $alpha->uuid) . '&search=Alpha');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Payment/Index')
            ->where('activeView', 'invoices')
            ->where('filters.organization_uuid', (string) $alpha->uuid)
            ->where('filters.search', 'Alpha')
            ->has('invoiceRows.data', 1)
            ->where('invoiceRows.data.0.organization_name', 'Alpha Billing')
            ->where('invoiceRows.data.0.payment_method_label', 'Visa')
        );
    }

    public function test_admin_payment_logs_hub_filters_activity_view_by_organization_and_date(): void
    {
        $admin = $this->createUser([
            'role' => 'admin',
            'email' => 'activity-filter-admin+'.Str::random(8).'@example.com',
        ]);

        $alpha = $this->createOrganization($admin, ['name' => 'Alpha Ledger']);
        $beta = $this->createOrganization($admin, ['name' => 'Beta Ledger']);

        BillingTransaction::create([
            'organization_id' => $alpha->id,
            'entity_type' => 'credit',
            'entity_id' => 1,
            'description' => 'Recent Alpha entry',
            'amount' => 50,
            'created_by' => $admin->id,
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        BillingTransaction::create([
            'organization_id' => $alpha->id,
            'entity_type' => 'credit',
            'entity_id' => 2,
            'description' => 'Old Alpha entry',
            'amount' => 40,
            'created_by' => $admin->id,
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);

        BillingTransaction::create([
            'organization_id' => $beta->id,
            'entity_type' => 'credit',
            'entity_id' => 3,
            'description' => 'Recent Beta entry',
            'amount' => 75,
            'created_by' => $admin->id,
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(
            '/admin/payment-logs?view=activity'
            . '&organization_uuid=' . urlencode((string) $alpha->uuid)
            . '&date_from=' . now()->subDay()->toDateString()
            . '&date_to=' . now()->toDateString()
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Payment/Index')
            ->where('activeView', 'activity')
            ->where('filters.organization_uuid', (string) $alpha->uuid)
            ->has('billingActivity.data', 1)
            ->where('billingActivity.data.0.organization.name', 'Alpha Ledger')
            ->where('billingActivity.data.0.description', 'Recent Alpha entry')
            ->where('invoiceRows', null)
        );
    }

    public function test_unrelated_organization_cannot_view_invoice_document(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner, ['name' => 'Secured Company']);
        $this->attachOwner($owner, $organization);
        [$invoice] = $this->createInvoiceWithPayment($organization, 'Visa');

        $otherUser = $this->createUser();
        $otherOrganization = $this->createOrganization($otherUser, ['name' => 'Other Company']);
        $this->attachOwner($otherUser, $otherOrganization);

        $response = $this->actingAs($otherUser, 'user')
            ->withSession(['current_organization' => $otherOrganization->id])
            ->get(route('user.billing.invoices.show', ['invoice' => $invoice->uuid]));

        $response->assertNotFound();
    }

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Billing',
            'last_name' => 'Owner',
            'email' => 'billing+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function createOrganization(User $creator, array $attributes = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Organization '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $creator->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([
                'addons' => [
                    'embedded_signup_enabled' => true,
                ],
            ]),
        ], $attributes));
    }

    private function attachOwner(User $user, Organization $organization): void
    {
        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $this->ownerRole()->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);
    }

    /**
     * @return array{0: BillingInvoice, 1: BillingPayment}
     */
    private function createInvoiceWithPayment(Organization $organization, string $method): array
    {
        $plan = $this->createPlan();

        $invoice = BillingInvoice::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'subtotal' => 199.00,
            'coupon_id' => null,
            'coupon_amount' => 0,
            'tax' => 0,
            'tax_type' => 'exclusive',
            'total' => 199.00,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $payment = BillingPayment::create([
            'organization_id' => $organization->id,
            'invoice_id' => $invoice->id,
            'processor' => 'moyasar',
            'payment_method' => $method,
            'amount' => 199.00,
            'details' => 'PAY-'.Str::upper(Str::random(6)),
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        return [$invoice, $payment];
    }

    private function createPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Billing Plan '.Str::random(4),
            'price' => 199.00,
            'period' => 'monthly',
            'metadata' => json_encode([]),
            'status' => 'active',
        ]);
    }

    private function ownerRole(): OrganizationRole
    {
        return OrganizationRole::firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => 'Owner role for billing invoice tests',
                'permissions' => ['*'],
            ]
        );
    }
}
