<script setup>
import { Link } from "@inertiajs/vue3";

defineProps(["companyConfig", "pages"]);

const whatItIncludes = [
    "مراجعة بيانات النشاط قبل التقديم.",
    "تجهيز طلب التحقق ومتابعته.",
    "إبلاغ العميل بأي ملاحظات من Meta.",
    "إرشاد العميل لمعالجة الملاحظات ضمن نفس الطلب.",
];

const whatWeNeed = [
    "السجل التجاري أو مستند النشاط.",
    "بيانات التواصل الرسمية.",
    "رابط الموقع الإلكتروني إن وجد.",
    "صلاحيات حساب Business Meta عند الحاجة.",
];

const articles = [
    {
        title: "التعريفات",
        body: [
            "«Botzo»: يُقصد بها مؤسسة بوتوزو، السجل التجاري رقم 7022030105، ومقرها الرياض، حي العارض.",
            "«العميل»: الشخص أو الجهة الطالبة للخدمة الموضحة في هذه الاتفاقية.",
            "«الخدمة»: خدمة تجهيز ومتابعة طلب التحقق من الحساب التجاري (Business Verification) لدى منصة Meta.",
            "«Meta»: الشركة المالكة لمنصات WhatsApp وFacebook وInstagram، وهي الجهة صاحبة القرار النهائي في طلب التحقق.",
        ],
    },
    {
        title: "موضوع الاتفاقية",
        body: [
            "تلتزم Botzo بموجب هذه الاتفاقية بتقديم خدمة تجهيز طلب التحقق من الحساب التجاري للعميل لدى Meta، ومتابعته حتى صدور قرار نهائي بشأنه، وذلك مقابل الأجر المتفق عليه في المادة الخامسة.",
        ],
    },
    {
        title: "نطاق الخدمة",
        body: [
            "تشمل الخدمة:",
            "— مراجعة بيانات نشاط العميل التجاري قبل تقديم الطلب.",
            "— تجهيز طلب التحقق وتقديمه عبر القنوات الرسمية المتاحة لدى Meta.",
            "— متابعة الطلب وإبلاغ العميل بأي ملاحظات ترد من Meta.",
            "— إرشاد العميل لمعالجة الملاحظات ضمن نطاق نفس الطلب.",
        ],
    },
    {
        title: "التزامات العميل",
        body: [
            "يلتزم العميل بتزويد Botzo بما يلي: السجل التجاري أو ما يثبت مزاولة النشاط، بيانات التواصل الرسمية، رابط الموقع الإلكتروني الرسمي إن وُجد، وصلاحيات الوصول لحساب Meta Business عند الحاجة.",
            "كما يقر العميل بمسؤوليته الكاملة عن صحة ودقة البيانات والمستندات التي يقدمها، وأن اختلافها بين المصادر المختلفة (السجل التجاري، حساب Meta Business، الموقع الإلكتروني، وسائل التواصل الرسمية) قد يؤثر على نتيجة المراجعة لدى Meta.",
        ],
    },
    {
        title: "الأجر وطريقة السداد",
        body: [
            "تبلغ قيمة الخدمة (1,500) ريال سعودي، تُسدد دفعة واحدة مقدمًا قبل بدء التنفيذ، وهي مقابل مالي عن خدمة التجهيز والمتابعة المهنية، منفصلة تمامًا عن أي رسوم اشتراك في منصة Botzo.",
        ],
    },
    {
        title: "مدة التنفيذ",
        body: [
            "تتراوح مدة تنفيذ الخدمة بين (3) إلى (10) أيام عمل من تاريخ استلام كامل البيانات المطلوبة وتأكيد السداد، وقد تختلف هذه المدة تبعًا لسرعة استجابة Meta وطبيعة المراجعة.",
        ],
    },
    {
        title: "القرار النهائي وإخلاء المسؤولية",
        body: [
            "تقر الأطراف بأن القرار النهائي بقبول أو رفض طلب التحقق يعود حصريًا لـ Meta، ولا تملك Botzo أي سلطة أو تأثير على هذا القرار. تلتزم Botzo بمتابعة الطلب باحترافية وبذل العناية المعتادة، دون أن يشكل ذلك ضمانًا لقبول الطلب من عدمه.",
        ],
    },
    {
        title: "سياسة الاسترداد",
        body: [
            "قيمة الخدمة غير قابلة للاسترداد بعد بدء التنفيذ، نظرًا لأن العمل يبدأ فور تأكيد السداد بمراجعة فعلية للبيانات وتجهيز الطلب ومتابعته، بصرف النظر عن القرار الذي تصدره Meta لاحقًا.",
        ],
    },
    {
        title: "ما لا تشمله الخدمة",
        body: [
            "لا تشمل هذه الاتفاقية: تعديل أو تحديث السجل التجاري، إنشاء حساب Meta Business جديد، إدارة أو تشغيل الحملات الإعلانية، أو أي رسوم متعلقة باشتراك العميل في منصة Botzo.",
        ],
    },
    {
        title: "السرية وحماية البيانات",
        body: [
            "تلتزم Botzo بالحفاظ على سرية البيانات والمستندات التي يقدمها العميل، وعدم استخدامها إلا في الغرض المحدد بهذه الاتفاقية، وذلك وفق سياسة الخصوصية المعتمدة لدى Botzo.",
        ],
    },
    {
        title: "إنهاء الاتفاقية",
        body: [
            "يجوز لأي من الطرفين إنهاء هذه الاتفاقية إذا أخلّ الطرف الآخر بأحد التزاماته الجوهرية ولم يقم بتصحيحه خلال مدة معقولة بعد إخطاره كتابيًا، دون إخلال بحق Botzo في الاحتفاظ بالأجر المسدد وفق المادة الثامنة.",
        ],
    },
    {
        title: "القانون الواجب التطبيق وتسوية النزاعات",
        body: [
            "تخضع هذه الاتفاقية لأنظمة المملكة العربية السعودية، وفي حال نشوء أي نزاع يتعذر حله وديًا، تكون الجهة القضائية المختصة في المملكة العربية السعودية هي المرجع للفصل فيه.",
        ],
    },
    {
        title: "إقرار وموافقة العميل",
        body: [
            "يقر العميل بأنه اطّلع على كامل بنود هذه الاتفاقية وفهمها، وأنها خدمة مستقلة عن اشتراكه في منصة Botzo، وأن قرار قبول أو رفض طلب التحقق يعود حصريًا لـ Meta، وذلك بالتوقيع أدناه.",
        ],
    },
];
</script>

<template>
    <div class="agreement-wrapper min-h-screen bg-[#f7f9fb]" dir="rtl">
        <!-- Toolbar (hidden when printing) -->
        <div class="print:hidden sticky top-0 z-10 flex items-center justify-between border-b border-[#cfd8e3] bg-white px-5 py-4 md:px-10">
            <Link href="/meta-verification" class="flex items-center gap-2 text-sm font-medium text-[#445566] hover:text-black">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>رجوع لصفحة الخدمة</span>
            </Link>
            <a
                href="/meta-verification/agreement/pdf"
                class="flex h-11 items-center justify-center gap-2 rounded-xl px-6 text-sm font-semibold text-[#04130a] transition-transform duration-200 hover:scale-[1.03]"
                style="background-image: linear-gradient(135deg, #25d366 0%, #1db954 100%)"
            >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6v-8Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>تحميل PDF</span>
            </a>
        </div>

        <!-- Page 1: offer overview -->
        <section class="agreement-page mx-auto flex max-w-[800px] flex-col gap-5 bg-white p-8 md:p-10">
            <div class="agreement-block flex w-full items-center justify-between">
                <div class="relative shrink-0" style="width: 60px; height: 55px">
                    <img src="/images/nav/nav-icon-light.svg" alt="" class="absolute max-w-none" style="left: 14.31px; top: 0; width: 31.52px; height: 30.74px;">
                    <img src="/images/nav/nav-wordmark-light.svg" alt="Botzo" class="absolute max-w-none" style="left: 0; top: 58.93%; width: 100%; height: 27.78%;">
                </div>
                <span class="text-xs font-medium text-[#8899aa]">عرض خدمة مستقل</span>
            </div>

            <div class="agreement-block flex w-full flex-col gap-3 rounded-3xl bg-[rgba(37,211,102,0.12)] p-5">
                <div class="flex w-full flex-col items-end gap-1.5 text-right">
                    <h1 class="w-full text-xl font-bold text-black md:text-[26px]">خدمة التحقق من الحساب التجاري عبر Meta</h1>
                    <p class="w-full text-sm leading-6 text-[#445566]">
                        تساعد Botzo نشاطك التجاري في تجهيز ومتابعة طلب التحقق لدى Meta باستخدام بياناتك الرسمية والمستندات التي تقدمها.
                    </p>
                </div>
                <div class="flex w-full flex-col items-end gap-1 rounded-2xl bg-white p-4 text-right">
                    <span class="w-full text-xs text-[#8899aa]">قيمة الخدمة</span>
                    <span class="w-full text-2xl font-bold text-[#1db954] md:text-3xl">1,500 ريال</span>
                    <span class="w-full text-xs text-[#8899aa]">تُدفع مرة واحدة قبل بدء التنفيذ</span>
                </div>
            </div>

            <div class="agreement-block flex w-full flex-col items-end gap-1 rounded-2xl border border-[#cfd8e3] p-4 text-right">
                <span class="w-full text-xs font-semibold text-[#1db954]">اعتماد Meta</span>
                <p class="w-full text-sm font-semibold text-black">Botzo شريك خدمة معتمد لدى Meta</p>
                <p class="w-full text-xs text-[#445566]">نساعدك في تجهيز ومتابعة طلب التحقق عبر المسارات الرسمية المتاحة.</p>
            </div>

            <div class="agreement-block grid w-full grid-cols-1 gap-2.5 sm:grid-cols-3">
                <div class="flex w-full flex-col items-end gap-1 rounded-2xl border border-[#cfd8e3] p-3 text-right">
                    <span class="w-full text-xs text-[#8899aa]">مدة التنفيذ</span>
                    <span class="w-full text-sm font-semibold text-black">3 إلى 10 أيام عمل</span>
                </div>
                <div class="flex w-full flex-col items-end gap-1 rounded-2xl border border-[#cfd8e3] p-3 text-right">
                    <span class="w-full text-xs text-[#8899aa]">نوع الخدمة</span>
                    <span class="w-full text-sm font-semibold text-black">منفصلة عن اشتراك Botzo</span>
                </div>
                <div class="flex w-full flex-col items-end gap-1 rounded-2xl border border-[#cfd8e3] p-3 text-right">
                    <span class="w-full text-xs text-[#8899aa]">القرار النهائي</span>
                    <span class="w-full text-sm font-semibold text-black">يخضع لمراجعة Meta</span>
                </div>
            </div>

            <div class="agreement-block grid w-full grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="flex w-full flex-col items-end gap-2 rounded-2xl border border-[#cfd8e3] p-4 text-right">
                    <h3 class="w-full text-sm font-semibold text-black">ماذا تشمل؟</h3>
                    <ul class="flex w-full flex-col items-end gap-1.5">
                        <li v-for="item in whatItIncludes" :key="item" class="w-full text-xs leading-5 text-[#445566]">{{ item }}</li>
                    </ul>
                </div>
                <div class="flex w-full flex-col items-end gap-2 rounded-2xl border border-[#cfd8e3] p-4 text-right">
                    <h3 class="w-full text-sm font-semibold text-black">ماذا نحتاج؟</h3>
                    <ul class="flex w-full flex-col items-end gap-1.5">
                        <li v-for="item in whatWeNeed" :key="item" class="w-full text-xs leading-5 text-[#445566]">{{ item }}</li>
                    </ul>
                </div>
            </div>

            <div class="agreement-block flex w-full flex-col items-end gap-1.5 rounded-2xl border-r-[3px] border-[#25d366] bg-[rgba(37,211,102,0.06)] p-4 text-right">
                <h3 class="w-full text-sm font-semibold text-black">عن Botzo</h3>
                <p class="w-full text-xs leading-5 text-[#445566]">
                    Botzo منصة سعودية لحلول WhatsApp Business وإدارة تواصل الشركات مع العملاء. تساعد على تنظيم المحادثات، إدارة العملاء، الحملات، الأتمتة، ومتابعة فرق العمل من مكان واحد.
                </p>
            </div>

            <div class="agreement-block flex w-full flex-col items-center gap-2 border-t border-[#cfd8e3] pt-4 text-center sm:flex-row sm:justify-between">
                <span class="text-xs text-[#445566]">+966579794477</span>
                <span class="text-xs text-[#445566]">support@botzo.net</span>
                <span class="text-xs text-[#445566]">https://botzo.net/</span>
            </div>
        </section>

        <!-- Agreement: full numbered articles -->
        <section class="agreement-page agreement-page--break mx-auto flex max-w-[800px] flex-col gap-5 bg-white p-8 md:p-10">
            <div class="agreement-block flex w-full flex-col items-end gap-1.5 text-right">
                <span class="w-full text-xs font-semibold text-[#1db954]">اتفاقية تقديم خدمة</span>
                <h2 class="w-full text-xl font-bold text-black md:text-2xl">اتفاقية خدمة التحقق من الحساب التجاري عبر Meta</h2>
                <p class="w-full text-xs leading-5 text-[#8899aa]">
                    اتفاقية مبرمة بين مؤسسة بوتوزو («Botzo») والعميل الموقّع أدناه، تحدد شروط تقديم الخدمة الموضحة في هذه الوثيقة.
                </p>
            </div>

            <div class="flex w-full flex-col gap-3">
                <div
                    v-for="(article, index) in articles"
                    :key="article.title"
                    class="agreement-article flex w-full items-start gap-3 rounded-2xl border border-[#cfd8e3] p-4 text-right"
                >
                    <div
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-[#04130a]"
                        style="background-image: linear-gradient(135deg, #25d366 0%, #1db954 100%)"
                    >
                        {{ index + 1 }}
                    </div>
                    <div class="flex w-full flex-1 flex-col items-end gap-1.5">
                        <h3 class="w-full text-sm font-semibold text-black">المادة {{ index + 1 }} — {{ article.title }}</h3>
                        <p v-for="line in article.body" :key="line" class="w-full text-xs leading-5 text-[#445566]">{{ line }}</p>
                    </div>
                </div>
            </div>

            <div class="agreement-block flex w-full flex-col items-end gap-1.5 rounded-2xl border border-[#f0c419] bg-[#fdf6e3] p-4 text-right">
                <h3 class="w-full text-sm font-semibold text-black">تنبيه مهم</h3>
                <p class="w-full text-xs leading-5 text-[#6b5a1a]">
                    يجب أن تكون بيانات النشاط متطابقة قدر الإمكان بين السجل التجاري، حساب Business Meta، الموقع الإلكتروني، ووسائل التواصل الرسمية. اختلاف البيانات قد يؤثر على نتيجة المراجعة.
                </p>
            </div>

            <div class="agreement-block flex w-full flex-col gap-1 rounded-2xl p-5 text-right text-white" style="background-image: linear-gradient(135deg, #1db954 0%, #0e6b35 100%)">
                <span class="w-full text-xs opacity-80">المعلومات الرسمية</span>
                <span class="w-full text-base font-semibold">Botzo — مؤسسة سعودية مسجلة رسميًا</span>
                <div class="mt-2 grid w-full grid-cols-1 gap-2 sm:grid-cols-3">
                    <div class="flex w-full flex-col items-end">
                        <span class="w-full text-xs opacity-70">الاسم التجاري</span>
                        <span class="w-full text-sm font-medium">مؤسسة بوتوزو</span>
                    </div>
                    <div class="flex w-full flex-col items-end">
                        <span class="w-full text-xs opacity-70">الرقم الوطني الموحد</span>
                        <span class="w-full text-sm font-medium">7022030105</span>
                    </div>
                    <div class="flex w-full flex-col items-end">
                        <span class="w-full text-xs opacity-70">المقر</span>
                        <span class="w-full text-sm font-medium">الرياض، حي العارض</span>
                    </div>
                </div>
            </div>

            <div class="agreement-block grid w-full grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="flex w-full flex-col items-end gap-1 rounded-2xl border border-[#cfd8e3] p-3 text-right">
                    <span class="w-full text-xs text-[#8899aa]">اسم العميل</span>
                    <div class="mt-4 h-6 w-full border-b border-[#cfd8e3]"></div>
                </div>
                <div class="flex w-full flex-col items-end gap-1 rounded-2xl border border-[#cfd8e3] p-3 text-right">
                    <span class="w-full text-xs text-[#8899aa]">التوقيع</span>
                    <div class="mt-4 h-6 w-full border-b border-[#cfd8e3]"></div>
                </div>
                <div class="flex w-full flex-col items-end gap-1 rounded-2xl border border-[#cfd8e3] p-3 text-right">
                    <span class="w-full text-xs text-[#8899aa]">التاريخ</span>
                    <div class="mt-4 h-6 w-full border-b border-[#cfd8e3]"></div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.agreement-page {
    font-family: 'IBM Plex Sans Arabic', sans-serif;
}

.agreement-page + .agreement-page {
    margin-top: 24px;
}

@media print {
    .agreement-wrapper {
        background: #fff;
    }

    .agreement-page {
        max-width: none;
        padding: 12mm;
        box-shadow: none;
    }

    .agreement-page--break {
        page-break-before: always;
        margin-top: 0;
    }

    .agreement-article,
    .agreement-block {
        break-inside: avoid;
    }
}

@media screen {
    .agreement-page {
        box-shadow: 0 8px 24px -16px rgba(10, 15, 23, 0.15);
        border-radius: 24px;
        margin-top: 24px;
    }
}

@page {
    size: A4;
    margin: 0;
}
</style>
