<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Create ticket')" :subtitle="$t('Have an issue? Describe it in detail below')">
                <template #actions>
                    <Link href="/admin/support" class="tkt-btn tkt-btn--ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" fill-rule="evenodd" d="M5.841 5.28a.75.75 0 0 0-1.06-1.06L1.53 7.47L1 8l.53.53l3.25 3.25a.75.75 0 0 0 1.061-1.06l-1.97-1.97H14.25a.75.75 0 0 0 0-1.5H3.871l1.97-1.97Z" clip-rule="evenodd"/></svg>
                        {{ $t('Back') }}
                    </Link>
                </template>
            </UiPageHeader>

            <UiSectionCard class="mt-6 md:w-2/3">
                <form @submit.prevent="submitForm()">
                    <div class="grid md:grid-cols-2 gap-x-8 gap-y-6">
                        <FormInput v-model="form.subject" :name="$t('Subject')" :type="'text'" :error="form.errors.subject" :class="'col-span-2'" :labelClass="'mb-0'"/>
                        <FormSelect v-model="form.category" :name="$t('Category')" :options="categoryOptions" :error="form.errors.category" :class="'col-span-2 md:col-span-1'" :placeholder="$t('Select Category')"/>
                        <FormSelect v-model="user" :name="$t('User')" :options="userOptions" :error="form.errors.user" :class="'col-span-2 md:col-span-1'" :placeholder="$t('Enter user email')"/>
                        <FormTextArea v-model="form.message" :name="$t('Description')" :type="'text'" :showLabel="true" :error="form.errors.message" :textAreaRows="5" :class="'col-span-2'"/>
                    </div>
                    <div class="flex justify-end pt-6">
                        <button type="submit" class="tkt-btn tkt-btn--solid">
                            {{ $t('Create ticket') }}
                        </button>
                    </div>
                </form>
            </UiSectionCard>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from './../Layout/App.vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import { ref, onMounted, watch } from 'vue';
    import { useI18n } from 'vue-i18n';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

    const { t, te } = useI18n();

    const props = defineProps(['title', 'categories']);
    const user = ref(null);
    const form = useForm({
        'subject' : null,
        'category' : null,
        'message' : null,
        'user' : null,
    });

    const categoryOptions = ref([]);
    const organizationOptions = ref([]);

    const userOptions = ref([]);
    
    onMounted(() => {
        fetch("/admin/users?search=", {
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(result => {
                userOptions.value = result.rows.map(user => ({
                    label: `${user.first_name} ${user.last_name} (${user.email})`,
                    value: user.id
                }));
            })
            .catch(error => {
                console.error("Error fetching users:", error);
            });
    });

    const transformCategories = (categories) => {
        return categories.map((category) => ({
            value: category.id,
            label: te(category.name) ? t(category.name) : category.name,
        }));
    };

    const submitForm = () => {
        form.post('/admin/support');
    }

    watch(user, (newValue) => {
        form.user = newValue;
    });

    onMounted(() => {
        categoryOptions.value = transformCategories(props.categories);
    });
</script>

<style scoped>
.tkt-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease, filter 160ms ease;
}

.tkt-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.tkt-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.tkt-btn--solid {
    border: 1px solid transparent;
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.tkt-btn--solid:hover {
    filter: brightness(1.05);
}
</style>

