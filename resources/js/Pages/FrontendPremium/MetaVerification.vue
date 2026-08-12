<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        <div class="relative overflow-hidden bg-white dark:bg-[#0a0f17]">
            <div class="pointer-events-none absolute -right-10 top-[220px] h-[334px] w-[221px] opacity-70" aria-hidden="true">
                <div class="absolute h-[121px] w-[121px] rounded-full bg-[#7ed9ba] blur-[58px]" style="left: 24px; top: 212px"></div>
                <div class="absolute h-[121px] w-[121px] rounded-full bg-[#66e8bd] blur-[58px]" style="left: 24px; top: 121px"></div>
                <div class="absolute h-[121px] w-[121px] rounded-full bg-[#00ff51] blur-[58px]" style="left: 100px; top: 0px"></div>
                <div class="absolute h-[121px] w-[121px] rounded-full bg-[#009921] blur-[58px]" style="left: 24px; top: 47px"></div>
            </div>
            <div class="pointer-events-none absolute right-[8%] top-[233px] hidden h-[336px] w-[222px] opacity-70 lg:block" aria-hidden="true">
                <div class="absolute h-[122px] w-[122px] rounded-full bg-[#7ed9ba] blur-[59px]" style="left: 25px; top: 214px"></div>
                <div class="absolute h-[122px] w-[122px] rounded-full bg-[#66e8bd] blur-[59px]" style="left: 25px; top: 122px"></div>
                <div class="absolute h-[122px] w-[122px] rounded-full bg-[#00ff51] blur-[59px]" style="left: 100px; top: 0px"></div>
                <div class="absolute h-[122px] w-[122px] rounded-full bg-[#009921] blur-[59px]" style="left: 25px; top: 47px"></div>
            </div>
            <div class="pointer-events-none absolute -left-10 top-[220px] h-[334px] w-[221px] scale-x-[-1] opacity-70" aria-hidden="true">
                <div class="absolute h-[121px] w-[121px] rounded-full bg-[#7ed9ba] blur-[58px]" style="left: 24px; top: 212px"></div>
                <div class="absolute h-[121px] w-[121px] rounded-full bg-[#66e8bd] blur-[58px]" style="left: 24px; top: 121px"></div>
                <div class="absolute h-[121px] w-[121px] rounded-full bg-[#00ff51] blur-[58px]" style="left: 100px; top: 0px"></div>
                <div class="absolute h-[121px] w-[121px] rounded-full bg-[#009921] blur-[58px]" style="left: 24px; top: 47px"></div>
            </div>
            <div class="pointer-events-none absolute left-[8%] top-[233px] hidden h-[336px] w-[222px] scale-x-[-1] opacity-70 lg:block" aria-hidden="true">
                <div class="absolute h-[122px] w-[122px] rounded-full bg-[#7ed9ba] blur-[59px]" style="left: 25px; top: 214px"></div>
                <div class="absolute h-[122px] w-[122px] rounded-full bg-[#66e8bd] blur-[59px]" style="left: 25px; top: 122px"></div>
                <div class="absolute h-[122px] w-[122px] rounded-full bg-[#00ff51] blur-[59px]" style="left: 100px; top: 0px"></div>
                <div class="absolute h-[122px] w-[122px] rounded-full bg-[#009921] blur-[59px]" style="left: 25px; top: 47px"></div>
            </div>

            <div class="relative">
                <VerificationHero @request-service="showRequestModal = true" />
                <WhatItIncludes />
                <WhatWeNeed />
                <TermsAndConditions />
                <VerificationTimeline :company-config="props.companyConfig" @request-service="showRequestModal = true" />
            </div>
        </div>

        <RequestVerificationModal :is-open="showRequestModal" @close="showRequestModal = false" />
    </FrontendLayout>
</template>

<script setup>
import { ref } from "vue";
import FrontendLayout from "./FrontendLayout.vue";
import VerificationHero from "@/Components/MetaVerification/VerificationHero.vue";
import WhatItIncludes from "@/Components/MetaVerification/WhatItIncludes.vue";
import WhatWeNeed from "@/Components/MetaVerification/WhatWeNeed.vue";
import TermsAndConditions from "@/Components/MetaVerification/TermsAndConditions.vue";
import VerificationTimeline from "@/Components/MetaVerification/VerificationTimeline.vue";
import RequestVerificationModal from "@/Components/MetaVerification/RequestVerificationModal.vue";

const props = defineProps(["companyConfig", "pages"]);
const showRequestModal = ref(false);
</script>
