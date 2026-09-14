<?php

namespace App\Support;

class MetaVerificationAgreementContent
{
    public static function for(string $locale): array
    {
        return $locale === 'ar' ? self::arabic() : self::english();
    }

    private static function arabic(): array
    {
        return [
            'dir' => 'rtl',
            'headerBadge' => 'عرض خدمة مستقل',
            'offerTitle' => 'خدمة التحقق من الحساب التجاري عبر Meta',
            'offerDesc' => 'تساعد Botzo نشاطك التجاري في تجهيز ومتابعة طلب التحقق لدى Meta باستخدام بياناتك الرسمية والمستندات التي تقدمها.',
            'priceLabel' => 'قيمة الخدمة',
            'priceValue' => '1,500 ريال',
            'priceNote' => 'تُدفع مرة واحدة قبل بدء التنفيذ',
            'accreditationBadge' => 'اعتماد Meta',
            'accreditationTitle' => 'Botzo شريك خدمة معتمد لدى Meta',
            'accreditationDesc' => 'نساعدك في تجهيز ومتابعة طلب التحقق عبر المسارات الرسمية المتاحة.',
            'chips' => [
                ['label' => 'مدة التنفيذ', 'value' => '3 إلى 10 أيام عمل'],
                ['label' => 'نوع الخدمة', 'value' => 'منفصلة عن اشتراك Botzo'],
                ['label' => 'القرار النهائي', 'value' => 'يخضع لمراجعة Meta'],
            ],
            'whatItIncludesTitle' => 'ماذا تشمل؟',
            'whatItIncludes' => [
                'مراجعة بيانات النشاط قبل التقديم.',
                'تجهيز طلب التحقق ومتابعته.',
                'إبلاغ العميل بأي ملاحظات من Meta.',
                'إرشاد العميل لمعالجة الملاحظات ضمن نفس الطلب.',
            ],
            'whatWeNeedTitle' => 'ماذا نحتاج؟',
            'whatWeNeed' => [
                'السجل التجاري أو مستند النشاط.',
                'بيانات التواصل الرسمية.',
                'رابط الموقع الإلكتروني إن وجد.',
                'صلاحيات حساب Business Meta عند الحاجة.',
            ],
            'aboutTitle' => 'عن Botzo',
            'aboutDesc' => 'Botzo منصة سعودية لحلول WhatsApp Business وإدارة تواصل الشركات مع العملاء. تساعد على تنظيم المحادثات، إدارة العملاء، الحملات، الأتمتة، ومتابعة فرق العمل من مكان واحد.',
            'footerContacts' => ['+966579794477', 'support@botzo.net', 'https://botzo.net/'],
            'agreementBadge' => 'اتفاقية تقديم خدمة',
            'agreementTitle' => 'اتفاقية خدمة التحقق من الحساب التجاري عبر Meta',
            'agreementIntro' => 'اتفاقية مبرمة بين مؤسسة بوتوزو («Botzo») والعميل الموقّع أدناه، تحدد شروط تقديم الخدمة الموضحة في هذه الوثيقة.',
            'articleLabel' => 'المادة',
            'articles' => [
                [
                    'title' => 'التعريفات',
                    'body' => [
                        '«Botzo»: يُقصد بها مؤسسة بوتوزو، السجل التجاري رقم 7022030105، ومقرها الرياض، حي العارض.',
                        '«العميل»: الشخص أو الجهة الطالبة للخدمة الموضحة في هذه الاتفاقية.',
                        '«الخدمة»: خدمة تجهيز ومتابعة طلب التحقق من الحساب التجاري لدى منصة Meta (Business Verification).',
                        '«Meta»: الشركة المالكة لمنصات WhatsApp وFacebook وInstagram، وهي الجهة صاحبة القرار النهائي في طلب التحقق.',
                    ],
                ],
                [
                    'title' => 'موضوع الاتفاقية',
                    'body' => [
                        'تلتزم Botzo بموجب هذه الاتفاقية بتقديم خدمة تجهيز طلب التحقق من الحساب التجاري للعميل لدى Meta، ومتابعته حتى صدور قرار نهائي بشأنه، وذلك مقابل الأجر المتفق عليه في المادة الخامسة.',
                    ],
                ],
                [
                    'title' => 'نطاق الخدمة',
                    'body' => [
                        'تشمل الخدمة:',
                        '— مراجعة بيانات نشاط العميل التجاري قبل تقديم الطلب.',
                        '— تجهيز طلب التحقق وتقديمه عبر القنوات الرسمية المتاحة لدى Meta.',
                        '— متابعة الطلب وإبلاغ العميل بأي ملاحظات ترد من Meta.',
                        '— إرشاد العميل لمعالجة الملاحظات ضمن نطاق نفس الطلب.',
                    ],
                ],
                [
                    'title' => 'التزامات العميل',
                    'body' => [
                        'يلتزم العميل بتزويد Botzo بما يلي: السجل التجاري أو ما يثبت مزاولة النشاط، بيانات التواصل الرسمية، رابط الموقع الإلكتروني الرسمي إن وُجد، وصلاحيات الوصول لحساب Meta Business عند الحاجة.',
                        'كما يقر العميل بمسؤوليته الكاملة عن صحة ودقة البيانات والمستندات التي يقدمها، وأن اختلافها بين المصادر المختلفة مثل السجل التجاري وحساب Meta Business والموقع الإلكتروني ووسائل التواصل الرسمية قد يؤثر على نتيجة المراجعة لدى Meta.',
                    ],
                ],
                [
                    'title' => 'الأجر وطريقة السداد',
                    'body' => [
                        'تبلغ قيمة الخدمة (1,500) ريال سعودي، تُسدد دفعة واحدة مقدمًا قبل بدء التنفيذ، وهي مقابل مالي عن خدمة التجهيز والمتابعة المهنية، منفصلة تمامًا عن أي رسوم اشتراك في منصة Botzo.',
                    ],
                ],
                [
                    'title' => 'مدة التنفيذ',
                    'body' => [
                        'تتراوح مدة تنفيذ الخدمة بين (3) إلى (10) أيام عمل من تاريخ استلام كامل البيانات المطلوبة وتأكيد السداد، وقد تختلف هذه المدة تبعًا لسرعة استجابة Meta وطبيعة المراجعة.',
                    ],
                ],
                [
                    'title' => 'القرار النهائي وإخلاء المسؤولية',
                    'body' => [
                        'تقر الأطراف بأن القرار النهائي بقبول أو رفض طلب التحقق يعود حصريًا لـ Meta، ولا تملك Botzo أي سلطة أو تأثير على هذا القرار. تلتزم Botzo بمتابعة الطلب باحترافية وبذل العناية المعتادة، دون أن يشكل ذلك ضمانًا لقبول الطلب من عدمه.',
                    ],
                ],
                [
                    'title' => 'سياسة الاسترداد',
                    'body' => [
                        'قيمة الخدمة غير قابلة للاسترداد بعد بدء التنفيذ، نظرًا لأن العمل يبدأ فور تأكيد السداد بمراجعة فعلية للبيانات وتجهيز الطلب ومتابعته، بصرف النظر عن القرار الذي تصدره Meta لاحقًا.',
                    ],
                ],
                [
                    'title' => 'ما لا تشمله الخدمة',
                    'body' => [
                        'لا تشمل هذه الاتفاقية: تعديل أو تحديث السجل التجاري، إنشاء حساب Meta Business جديد، إدارة أو تشغيل الحملات الإعلانية، أو أي رسوم متعلقة باشتراك العميل في منصة Botzo.',
                    ],
                ],
                [
                    'title' => 'السرية وحماية البيانات',
                    'body' => [
                        'تلتزم Botzo بالحفاظ على سرية البيانات والمستندات التي يقدمها العميل، وعدم استخدامها إلا في الغرض المحدد بهذه الاتفاقية، وذلك وفق سياسة الخصوصية المعتمدة لدى Botzo.',
                    ],
                ],
                [
                    'title' => 'إنهاء الاتفاقية',
                    'body' => [
                        'يجوز لأي من الطرفين إنهاء هذه الاتفاقية إذا أخلّ الطرف الآخر بأحد التزاماته الجوهرية ولم يقم بتصحيحه خلال مدة معقولة بعد إخطاره كتابيًا، دون إخلال بحق Botzo في الاحتفاظ بالأجر المسدد وفق المادة الثامنة.',
                    ],
                ],
                [
                    'title' => 'القانون الواجب التطبيق وتسوية النزاعات',
                    'body' => [
                        'تخضع هذه الاتفاقية لأنظمة المملكة العربية السعودية، وفي حال نشوء أي نزاع يتعذر حله وديًا، تكون الجهة القضائية المختصة في المملكة العربية السعودية هي المرجع للفصل فيه.',
                    ],
                ],
                [
                    'title' => 'إقرار وموافقة العميل',
                    'body' => [
                        'يقر العميل بأنه اطّلع على كامل بنود هذه الاتفاقية وفهمها، وأنها خدمة مستقلة عن اشتراكه في منصة Botzo، وأن قرار قبول أو رفض طلب التحقق يعود حصريًا لـ Meta، وذلك بالتوقيع أدناه.',
                    ],
                ],
            ],
            'noticeTitle' => 'تنبيه مهم',
            'noticeBody' => 'يجب أن تكون بيانات النشاط متطابقة قدر الإمكان بين السجل التجاري وحساب Meta Business والموقع الإلكتروني ووسائل التواصل الرسمية. اختلاف البيانات قد يؤثر على نتيجة المراجعة.',
            'officialLabel' => 'المعلومات الرسمية',
            'officialName' => 'Botzo — مؤسسة سعودية مسجلة رسميًا',
            'officialFields' => [
                ['label' => 'الاسم التجاري', 'value' => 'مؤسسة بوتوزو'],
                ['label' => 'الرقم الوطني الموحد', 'value' => '7022030105'],
                ['label' => 'المقر', 'value' => 'الرياض، حي العارض'],
            ],
            'signFields' => [
                ['label' => 'اسم العميل'],
                ['label' => 'التوقيع'],
                ['label' => 'التاريخ'],
            ],
        ];
    }

    private static function english(): array
    {
        return [
            'dir' => 'ltr',
            'headerBadge' => 'Independent Service Offer',
            'offerTitle' => 'Meta Business Account Verification Service',
            'offerDesc' => 'Botzo helps your business prepare and follow up on the verification request with Meta using your official data and the documents you provide.',
            'priceLabel' => 'Service Fee',
            'priceValue' => 'SAR 1,500',
            'priceNote' => 'Paid once before work begins',
            'accreditationBadge' => 'Meta Accreditation',
            'accreditationTitle' => 'Botzo is an accredited service partner with Meta',
            'accreditationDesc' => 'We help you prepare and follow up on the verification request through the official channels available.',
            'chips' => [
                ['label' => 'Turnaround Time', 'value' => '3 to 10 business days'],
                ['label' => 'Service Type', 'value' => 'Separate from Botzo subscription'],
                ['label' => 'Final Decision', 'value' => "Subject to Meta's review"],
            ],
            'whatItIncludesTitle' => "What's Included?",
            'whatItIncludes' => [
                'Reviewing business activity data before submission.',
                'Preparing and following up on the verification request.',
                'Notifying the client of any feedback from Meta.',
                'Guiding the client to address feedback within the same request.',
            ],
            'whatWeNeedTitle' => 'What We Need?',
            'whatWeNeed' => [
                'Commercial registration or proof of business activity.',
                'Official contact information.',
                'Website link, if available.',
                'Meta Business account access, when needed.',
            ],
            'aboutTitle' => 'About Botzo',
            'aboutDesc' => 'Botzo is a Saudi platform for WhatsApp Business solutions and business-customer communication management. It helps organize conversations, manage customers, campaigns, automation, and team follow-up from one place.',
            'footerContacts' => ['+966579794477', 'support@botzo.net', 'https://botzo.net/'],
            'agreementBadge' => 'Service Agreement',
            'agreementTitle' => 'Meta Business Account Verification Service Agreement',
            'agreementIntro' => 'An agreement made between Botzo Establishment ("Botzo") and the client signing below, setting out the terms for providing the service described in this document.',
            'articleLabel' => 'Article',
            'articles' => [
                [
                    'title' => 'Definitions',
                    'body' => [
                        '"Botzo": refers to Botzo Establishment, commercial registration No. 7022030105, headquartered in Riyadh, Al-Arid district.',
                        '"Client": the person or entity requesting the service described in this agreement.',
                        '"Service": the service of preparing and following up on the Business Verification request with the Meta platform.',
                        '"Meta": the company that owns the WhatsApp, Facebook, and Instagram platforms, and is the party with final authority over the verification request.',
                    ],
                ],
                [
                    'title' => 'Subject of the Agreement',
                    'body' => [
                        'Under this agreement, Botzo undertakes to provide the client with the service of preparing the business account verification request with Meta and following up on it until a final decision is issued, in return for the fee agreed upon in Article 5.',
                    ],
                ],
                [
                    'title' => 'Scope of the Service',
                    'body' => [
                        'The service includes:',
                        "— Reviewing the client's business activity data before submitting the request.",
                        "— Preparing and submitting the verification request through Meta's official available channels.",
                        '— Following up on the request and notifying the client of any feedback received from Meta.',
                        '— Guiding the client to address feedback within the scope of the same request.',
                    ],
                ],
                [
                    'title' => "Client's Obligations",
                    'body' => [
                        'The client undertakes to provide Botzo with the following: commercial registration or proof of business activity, official contact information, the official website link if available, and access to the Meta Business account when needed.',
                        'The client also acknowledges full responsibility for the accuracy of the data and documents provided, and that discrepancies between the different sources (commercial registration, Meta Business account, website, official communication channels) may affect the outcome of Meta\'s review.',
                    ],
                ],
                [
                    'title' => 'Fee and Payment Method',
                    'body' => [
                        'The service fee is (1,500) Saudi Riyals, paid in full in advance before work begins. This fee covers the professional preparation and follow-up service and is entirely separate from any Botzo platform subscription fees.',
                    ],
                ],
                [
                    'title' => 'Turnaround Time',
                    'body' => [
                        "The service turnaround time ranges between (3) and (10) business days from the date of receiving all required data and confirming payment. This period may vary depending on Meta's response time and the nature of the review.",
                    ],
                ],
                [
                    'title' => 'Final Decision and Disclaimer',
                    'body' => [
                        "The parties acknowledge that the final decision to approve or reject the verification request rests exclusively with Meta, and Botzo has no authority or influence over this decision. Botzo undertakes to follow up on the request professionally and with reasonable care, without this constituting any guarantee of the request's approval or rejection.",
                    ],
                ],
                [
                    'title' => 'Refund Policy',
                    'body' => [
                        'The service fee is non-refundable once work has begun, since work starts immediately upon payment confirmation with an actual review of the data, preparation, and follow-up of the request, regardless of the decision Meta subsequently issues.',
                    ],
                ],
                [
                    'title' => 'What the Service Does Not Include',
                    'body' => [
                        "This agreement does not include: amending or updating the commercial registration, creating a new Meta Business account, managing or running advertising campaigns, or any fees related to the client's subscription to the Botzo platform.",
                    ],
                ],
                [
                    'title' => 'Confidentiality and Data Protection',
                    'body' => [
                        "Botzo undertakes to maintain the confidentiality of the data and documents provided by the client and not to use them except for the purpose specified in this agreement, in accordance with Botzo's approved privacy policy.",
                    ],
                ],
                [
                    'title' => 'Termination of the Agreement',
                    'body' => [
                        "Either party may terminate this agreement if the other party breaches one of its material obligations and fails to remedy it within a reasonable period after being notified in writing, without prejudice to Botzo's right to retain the paid fee in accordance with Article 8.",
                    ],
                ],
                [
                    'title' => 'Governing Law and Dispute Resolution',
                    'body' => [
                        'This agreement is governed by the laws of the Kingdom of Saudi Arabia. In the event of any dispute that cannot be resolved amicably, the competent judicial authority in the Kingdom of Saudi Arabia shall have jurisdiction to settle it.',
                    ],
                ],
                [
                    'title' => "Client's Acknowledgment and Consent",
                    'body' => [
                        'The client acknowledges having read and understood all the terms of this agreement, that it is a service independent of their Botzo platform subscription, and that the decision to approve or reject the verification request rests exclusively with Meta, as confirmed by signing below.',
                    ],
                ],
            ],
            'noticeTitle' => 'Important Notice',
            'noticeBody' => "Business activity data should match as closely as possible across the commercial registration, Meta Business account, website, and official communication channels. Discrepancies in data may affect the review outcome.",
            'officialLabel' => 'Official Information',
            'officialName' => 'Botzo — an officially registered Saudi establishment',
            'officialFields' => [
                ['label' => 'Trade Name', 'value' => 'Botzo Establishment'],
                ['label' => 'Unified National Number', 'value' => '7022030105'],
                ['label' => 'Headquarters', 'value' => 'Riyadh, Al-Arid District'],
            ],
            'signFields' => [
                ['label' => 'Client Name'],
                ['label' => 'Signature'],
                ['label' => 'Date'],
            ],
        ];
    }
}
