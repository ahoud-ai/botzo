<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-xl rounded-2xl border border-slate-800 bg-slate-900/80 shadow-2xl p-8 text-center">
            <p class="text-sm uppercase tracking-[0.24em] text-slate-400 mb-3">{{ status }}</p>
            <h1 class="text-2xl md:text-3xl font-semibold mb-3">{{ resolvedTitle }}</h1>
            <p class="text-sm md:text-base text-slate-300 leading-7">
                {{ resolvedDescription }}
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <button type="button" class="rounded-md border border-slate-600 px-4 py-2 text-sm hover:bg-slate-800 transition" @click="goBack">
                    {{ $t('Back') }}
                </button>
                <a :href="homeHref" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-500 transition">
                    {{ $t('Go to dashboard') }}
                </a>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { computed } from 'vue';

    const props = defineProps({
        status: {
            type: Number,
            required: true,
        },
        message: {
            type: String,
            default: '',
        },
    });

    const homeHref = computed(() => (
        window?.location?.pathname?.startsWith('/admin') ? '/admin/dashboard' : '/dashboard'
    ));

    const resolvedTitle = computed(() => {
        if (props.status === 403) return 'Access denied';
        if (props.status === 404) return 'Page not found';
        if (props.status === 500) return 'Unexpected error';
        return 'Something went wrong';
    });

    const resolvedDescription = computed(() => {
        if (props.message && props.message.trim() !== '') {
            return props.message;
        }

        if (props.status === 403) {
            return 'You do not have permission to access this page or perform this action.';
        }

        if (props.status === 404) {
            return 'The page you are trying to reach does not exist or was moved.';
        }

        if (props.status === 500) {
            return 'A server error occurred. Please try again in a moment.';
        }

        return 'Please return to the dashboard or try again later.';
    });

    const goBack = () => {
        window.history.back();
    };
</script>
