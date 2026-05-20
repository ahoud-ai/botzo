<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminEmailTemplateEditorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_update_email_template_with_table_html_and_placeholders(): void
    {
        $admin = $this->createAdminUser();
        $template = $this->createTemplate($admin->id);

        $body = '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;"><tr><td style="padding: 12px;">Hello {{FirstName}}, <a href="https://example.com">Open</a></td></tr></table>';

        $response = $this->actingAs($admin, 'admin')
            ->put('/admin/settings/email-template/'.$template->id, [
                'subject' => 'Updated subject',
                'body' => $body,
            ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/settings/email-templates');

        $template->refresh();
        $savedBody = (string) $template->body;

        $this->assertSame('Updated subject', $template->subject);
        $this->assertStringContainsString('<table', $savedBody);
        $this->assertStringContainsString('{{FirstName}}', $savedBody);
        $this->assertStringContainsString('https://example.com', $savedBody);
    }

    public function test_email_template_update_sanitizes_script_and_event_handlers(): void
    {
        $admin = $this->createAdminUser();
        $template = $this->createTemplate($admin->id);

        $body = '<div><script>alert("x")</script><img src="https://example.com/test.png" onerror="alert(1)"><a href="javascript:alert(1)" target="_blank">Click</a></div>';

        $response = $this->actingAs($admin, 'admin')
            ->put('/admin/settings/email-template/'.$template->id, [
                'subject' => 'Safe Subject',
                'body' => $body,
            ]);

        $response->assertStatus(302);

        $template->refresh();
        $savedBody = (string) $template->body;

        $this->assertStringNotContainsString('<script', strtolower($savedBody));
        $this->assertStringNotContainsString('onerror', strtolower($savedBody));
        $this->assertStringNotContainsString('javascript:', strtolower($savedBody));
        $this->assertStringContainsString('https://example.com/test.png', $savedBody);
    }

    public function test_email_template_update_validates_subject_and_body_limits(): void
    {
        $admin = $this->createAdminUser();
        $template = $this->createTemplate($admin->id);

        $response = $this->actingAs($admin, 'admin')
            ->put('/admin/settings/email-template/'.$template->id, [
                'subject' => '',
                'body' => '',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['subject', 'body']);

        $response = $this->actingAs($admin, 'admin')
            ->put('/admin/settings/email-template/'.$template->id, [
                'subject' => str_repeat('A', 256),
                'body' => str_repeat('A', 200001),
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['subject', 'body']);
    }

    private function createAdminUser(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'email' => 'admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);
    }

    private function createTemplate(int $adminId): EmailTemplate
    {
        return EmailTemplate::create([
            'name' => 'Unit Test Template '.Str::random(4),
            'subject' => 'Original Subject',
            'body' => '<p>Original body</p>',
            'updated_by' => $adminId,
            'updated_at' => now(),
        ]);
    }
}
