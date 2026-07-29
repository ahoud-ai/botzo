<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $reviews = [
            [
                'name' => 'أحمد الغامدي',
                'name_ar' => 'أحمد الغامدي',
                'name_en' => 'Ahmed Al-Ghamdi',
                'position' => 'مدير مبيعات · الرياض',
                'position_ar' => 'مدير مبيعات · الرياض',
                'position_en' => 'Sales Manager · Riyadh',
                'review' => 'وفّر علينا Botzo أكثر من ٢٠٠ ساعة شهرياً. الآن فريقنا يركّز على الإغلاق فقط.',
                'review_ar' => 'وفّر علينا Botzo أكثر من ٢٠٠ ساعة شهرياً. الآن فريقنا يركّز على الإغلاق فقط.',
                'review_en' => "Botzo saved us over 200 hours a month. Now our team focuses only on closing deals.",
                'rating' => 5,
                'status' => 1,
            ],
            [
                'name' => 'سارة العتيبي',
                'name_ar' => 'سارة العتيبي',
                'name_en' => 'Sarah Al-Otaibi',
                'position' => 'صاحبة متجر إلكتروني · جدة',
                'position_ar' => 'صاحبة متجر إلكتروني · جدة',
                'position_en' => 'E-commerce Store Owner · Jeddah',
                'review' => 'خلال أسبوع واحد بس، بوتزو رد على استفسارات العملاء أسرع من فريقنا بكتير. الطلبات زادت والعملاء مبسوطين.',
                'review_ar' => 'خلال أسبوع واحد بس، بوتزو رد على استفسارات العملاء أسرع من فريقنا بكتير. الطلبات زادت والعملاء مبسوطين.',
                'review_en' => 'In just one week, Botzo answered customer questions far faster than our team could. Orders went up and customers are happier.',
                'rating' => 5,
                'status' => 1,
            ],
            [
                'name' => 'خالد المطيري',
                'name_ar' => 'خالد المطيري',
                'name_en' => 'Khaled Al-Mutairi',
                'position' => 'مدير خدمة عملاء · الدمام',
                'position_ar' => 'مدير خدمة عملاء · الدمام',
                'position_en' => 'Customer Service Manager · Dammam',
                'review' => 'قبل بوتزو كنا نضيع رسايل كتير بالليل. دلوقتي كل استفسار له رد فوري، حتى لو الفريق نايم.',
                'review_ar' => 'قبل بوتزو كنا نضيع رسايل كتير بالليل. دلوقتي كل استفسار له رد فوري، حتى لو الفريق نايم.',
                'review_en' => 'Before Botzo, we lost a lot of messages overnight. Now every inquiry gets an instant reply, even while the team sleeps.',
                'rating' => 5,
                'status' => 1,
            ],
            [
                'name' => 'نورة القحطاني',
                'name_ar' => 'نورة القحطاني',
                'name_en' => 'Noura Al-Qahtani',
                'position' => 'مؤسسة متجر أزياء · مكة المكرمة',
                'position_ar' => 'مؤسسة متجر أزياء · مكة المكرمة',
                'position_en' => 'Fashion Store Founder · Makkah',
                'review' => 'المبيعات زادت ٤٠٪ في أول شهرين. بوتزو بيتابع كل عميل تلقائي وما بيسيب حد من غير رد.',
                'review_ar' => 'المبيعات زادت ٤٠٪ في أول شهرين. بوتزو بيتابع كل عميل تلقائي وما بيسيب حد من غير رد.',
                'review_en' => "Sales grew 40% in the first two months. Botzo follows up with every customer automatically and never leaves anyone without a reply.",
                'rating' => 5,
                'status' => 1,
            ],
            [
                'name' => 'فيصل الحربي',
                'name_ar' => 'فيصل الحربي',
                'name_en' => 'Faisal Al-Harbi',
                'position' => 'مدير تسويق · الرياض',
                'position_ar' => 'مدير تسويق · الرياض',
                'position_en' => 'Marketing Manager · Riyadh',
                'review' => 'أسهل أداة ربطناها بواتساب بيزنس. مافي أي تعقيد تقني، واشتغلت من أول يوم.',
                'review_ar' => 'أسهل أداة ربطناها بواتساب بيزنس. مافي أي تعقيد تقني، واشتغلت من أول يوم.',
                'review_en' => "The easiest tool we've connected to WhatsApp Business. No technical complexity at all — it worked from day one.",
                'rating' => 5,
                'status' => 1,
            ],
            [
                'name' => 'ريم الشهري',
                'name_ar' => 'ريم الشهري',
                'name_en' => 'Reem Al-Shahri',
                'position' => 'صاحبة مشروع صغير · جدة',
                'position_ar' => 'صاحبة مشروع صغير · جدة',
                'position_en' => 'Small Business Owner · Jeddah',
                'review' => 'كنت أرد على كل رسالة بنفسي وأضيع وقت كتير. بوتزو رجعلي وقتي وخلى مشروعي يشتغل حتى وأنا مش موجودة.',
                'review_ar' => 'كنت أرد على كل رسالة بنفسي وأضيع وقت كتير. بوتزو رجعلي وقتي وخلى مشروعي يشتغل حتى وأنا مش موجودة.',
                'review_en' => "I used to reply to every message myself and lose so much time. Botzo gave me my time back and keeps my business running even when I'm not around.",
                'rating' => 5,
                'status' => 1,
            ],
        ];

        foreach ($reviews as $review) {
            Review::firstOrCreate(
                ['name' => $review['name'], 'position' => $review['position']],
                $review
            );
        }
    }
}
