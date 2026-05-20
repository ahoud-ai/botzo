<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $row) {
            Page::updateOrCreate(
                ['name' => $row['name']],
                $row
            );
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function pages(): array
    {
        $privacyEn = '<h2>Privacy Policy</h2><p>Botzo processes account, workspace, contact, billing, and WhatsApp communication data only to operate the service, secure access, provide support, and improve reliability.</p><p>Organization administrators control their workspace data. Operational access is limited to authorized team members and service providers needed for hosting, payment, email, storage, analytics, and support.</p><p>Customers may request access, correction, export, or removal of personal data according to the agreement that applies to their workspace.</p>';
        $privacyAr = '<h2>سياسة الخصوصية</h2><p>يعالج Botzo بيانات الحساب ومساحة العمل وجهات الاتصال والفوترة وتواصل واتساب فقط لتشغيل الخدمة وتأمين الوصول وتقديم الدعم وتحسين الاعتمادية.</p><p>يتحكم مسؤولو المؤسسة في بيانات مساحة العمل، ويقتصر الوصول التشغيلي على أعضاء الفريق المخولين ومزودي الخدمة اللازمين للاستضافة والدفع والبريد والتخزين والتحليلات والدعم.</p><p>يمكن للعملاء طلب الوصول إلى البيانات الشخصية أو تصحيحها أو تصديرها أو إزالتها وفق الاتفاقية المطبقة على مساحة العمل.</p>';
        $termsEn = '<h2>Terms of Service</h2><p>Botzo provides a hosted workspace for WhatsApp customer communication, subscriptions, billing, contacts, campaigns, and approved automation flows.</p><p>Each organization is responsible for its users, message content, consent records, WhatsApp Business compliance, and payment obligations.</p><p>Service access may be limited when billing, security, or platform rules require action from the organization administrator.</p>';
        $termsAr = '<h2>شروط الاستخدام</h2><p>يوفر Botzo مساحة عمل لإدارة تواصل العملاء عبر واتساب والاشتراكات والفوترة وجهات الاتصال والحملات وتدفقات الأتمتة المعتمدة.</p><p>تتحمل كل مؤسسة مسؤولية مستخدميها ومحتوى الرسائل وسجلات الموافقة والالتزام بسياسات WhatsApp Business والتزامات الدفع.</p><p>قد يتم تقييد الوصول للخدمة عند وجود متطلبات فوترة أو أمان أو قواعد منصة تحتاج إلى إجراء من مسؤول المؤسسة.</p>';

        return [
            [
                'name' => 'Privacy Policy',
                'name_ar' => 'سياسة الخصوصية',
                'name_en' => 'Privacy Policy',
                'content' => $privacyEn,
                'content_ar' => $privacyAr,
                'content_en' => $privacyEn,
            ],
            [
                'name' => 'Terms of Service',
                'name_ar' => 'شروط الاستخدام',
                'name_en' => 'Terms of Service',
                'content' => $termsEn,
                'content_ar' => $termsAr,
                'content_en' => $termsEn,
            ],
        ];
    }
}
