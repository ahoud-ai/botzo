<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Edit template')" :subtitle="$t('Modify whatsapp template')">
                <template #actions>
                    <Link href="/templates" class="wizard-btn wizard-btn--ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" fill-rule="evenodd" d="M5.841 5.28a.75.75 0 0 0-1.06-1.06L1.53 7.47L1 8l.53.53l3.25 3.25a.75.75 0 0 0 1.061-1.06l-1.97-1.97H14.25a.75.75 0 0 0 0-1.5H3.871l1.97-1.97Z" clip-rule="evenodd"/></svg>
                        {{ $t('Back') }}
                    </Link>
                    <button @click="submitForm()" type="button" class="wizard-btn wizard-btn--solid" :disabled="!isFormValid || isLoading">
                        <span v-if="!isLoading">{{ $t('Update template') }}</span>
                        <svg v-else class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".3"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"/></svg>
                    </button>
                </template>
            </UiPageHeader>

            <div class="wizard-layout">
                <div class="wizard-main">
                    <UiFormSection :title="$t('Basic information')">
                        <div class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <div class="sm:col-span-6">
                                <FormInput v-model="form.name" :name="$t('Name')" :disabled="true" :type="'text'" @input="handleNameInput" @keydown.space.prevent="addUnderscore" :class="''"/>
                                <span class="flex items-center gap-x-1 text-[11px] ui-danger-text mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3"/></svg>
                                    {{ $t('You are not allowed to edit this field') }}
                                </span>
                            </div>
                            <div class="sm:col-span-3">
                                <FormSelect v-model="form.category" :options="categoryOptions" :name="$t('Category')" :class="'sm:col-span-3'" :placeholder="$t('Select Category')"/>
                            </div>
                            <div class="sm:col-span-3">
                                <FormSelect v-model="form.language" :disabled="true" :options="langOptions" :name="$t('Language')" :class="'sm:col-span-3'" :placeholder="$t('Select Language')"/>
                                <span class="flex items-center gap-x-1 text-[11px] ui-danger-text mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3"/></svg>
                                    {{ $t('You are not allowed to edit this field') }}
                                </span>
                            </div>
                        </div>
                    </UiFormSection>

                    <template v-if="form.category === 'UTILITY' || form.category === 'MARKETING'">
                        <UiFormSection :title="`${$t('Header')} (${$t('Optional')})`" :description="$t('Add a title or choose which type of media you\'ll use for this header')">
                            <div class="wizard-segmented tpl-segmented-4">
                                <button type="button" @click="changeHeaderType('TEXT')" :class="{ 'is-active': form.header.format === 'TEXT' }">{{ $t('Text') }}</button>
                                <button type="button" @click="changeHeaderType('IMAGE')" :class="{ 'is-active': form.header.format === 'IMAGE' }">{{ $t('Image') }}</button>
                                <button type="button" @click="changeHeaderType('VIDEO')" :class="{ 'is-active': form.header.format === 'VIDEO' }">{{ $t('Video') }}</button>
                                <button type="button" @click="changeHeaderType('DOCUMENT')" :class="{ 'is-active': form.header.format === 'DOCUMENT' }">{{ $t('Document') }}</button>
                            </div>
                            <div class="mt-4">
                                <div v-show="form.header.format === 'TEXT'">
                                    <HeaderTextArea v-model="form.header.text" :customValues="form.header.example" @updateExamples="updateHeaderExamples"/>
                                </div>
                                <div v-if="form.header.format === 'IMAGE'">
                                    <div class="tpl-dropzone">
                                        <input
                                            type="file"
                                            class="sr-only"
                                            accept=".jpg, .png"
                                            ref="fileInput"
                                            id="file-upload"
                                            @change="handleFileUpload($event)"
                                        />
                                        <div class="text-center">
                                            <div>
                                                <div v-if="form.header.format === 'IMAGE' && form.header.example" class="flex justify-center items-center">
                                                    <div class="tpl-file-chip">
                                                        <div>
                                                            <svg class="tpl-file-icon mx-auto h-6 w-6 cursor-pointer" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M14 9a1.5 1.5 0 1 1 3 0a1.5 1.5 0 0 1-3 0Z"/><path fill="currentColor" fill-rule="evenodd" d="M7.268 4.658a54.647 54.647 0 0 1 9.465 0l1.51.132a3.138 3.138 0 0 1 2.831 2.66a30.604 30.604 0 0 1 0 9.1a3.138 3.138 0 0 1-2.831 2.66l-1.51.131c-3.15.274-6.316.274-9.465 0l-1.51-.131a3.138 3.138 0 0 1-2.832-2.66a30.601 30.601 0 0 1 0-9.1a3.138 3.138 0 0 1 2.831-2.66l1.51-.132Zm9.335 1.495a53.147 53.147 0 0 0-9.206 0l-1.51.131A1.638 1.638 0 0 0 4.41 7.672a29.101 29.101 0 0 0-.311 5.17L7.97 8.97a.75.75 0 0 1 1.09.032l3.672 4.13l2.53-.844a.75.75 0 0 1 .796.21l3.519 3.91a29.101 29.101 0 0 0 .014-8.736a1.638 1.638 0 0 0-1.478-1.388l-1.51-.131Zm2.017 11.435l-3.349-3.721l-2.534.844a.75.75 0 0 1-.798-.213l-3.471-3.905l-4.244 4.243c.049.498.11.996.185 1.491a1.638 1.638 0 0 0 1.478 1.389l1.51.131c3.063.266 6.143.266 9.206 0l1.51-.131c.178-.016.35-.06.507-.128Z" clip-rule="evenodd"/></svg>
                                                        </div>
                                                        <div class="flex items-center gap-x-2">
                                                            <span class="text-sm">{{ form.header.example.name }}</span>
                                                            <button type="button" @click="removeFile()" class="tpl-icon-btn">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M17.707 7.707a1 1 0 0 0-1.414-1.414L12 10.586L7.707 6.293a1 1 0 0 0-1.414 1.414L10.586 12l-4.293 4.293a1 1 0 1 0 1.414 1.414L12 13.414l4.293 4.293a1 1 0 1 0 1.414-1.414L13.414 12l4.293-4.293Z" clip-rule="evenodd"/></svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <label v-else for="file-upload">
                                                    <svg class="tpl-dropzone-icon mx-auto h-12 w-12 cursor-pointer" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M14 9a1.5 1.5 0 1 1 3 0a1.5 1.5 0 0 1-3 0Z"/><path fill="currentColor" fill-rule="evenodd" d="M7.268 4.658a54.647 54.647 0 0 1 9.465 0l1.51.132a3.138 3.138 0 0 1 2.831 2.66a30.604 30.604 0 0 1 0 9.1a3.138 3.138 0 0 1-2.831 2.66l-1.51.131c-3.15.274-6.316.274-9.465 0l-1.51-.131a3.138 3.138 0 0 1-2.832-2.66a30.601 30.601 0 0 1 0-9.1a3.138 3.138 0 0 1 2.831-2.66l1.51-.132Zm9.335 1.495a53.147 53.147 0 0 0-9.206 0l-1.51.131A1.638 1.638 0 0 0 4.41 7.672a29.101 29.101 0 0 0-.311 5.17L7.97 8.97a.75.75 0 0 1 1.09.032l3.672 4.13l2.53-.844a.75.75 0 0 1 .796.21l3.519 3.91a29.101 29.101 0 0 0 .014-8.736a1.638 1.638 0 0 0-1.478-1.388l-1.51-.131Zm2.017 11.435l-3.349-3.721l-2.534.844a.75.75 0 0 1-.798-.213l-3.471-3.905l-4.244 4.243c.049.498.11.996.185 1.491a1.638 1.638 0 0 0 1.478 1.389l1.51.131c3.063.266 6.143.266 9.206 0l1.51-.131c.178-.016.35-.06.507-.128Z" clip-rule="evenodd"/></svg>
                                                </label>
                                                <div class="flex text-sm ui-text-muted">
                                                    <label for="file-upload" class="tpl-dropzone-label">
                                                        <span>{{ $t('Provide examples of the variables or media in the header') }}</span>
                                                    </label>
                                                </div>
                                                <p class="text-xs ui-text-muted">{{ $t('PNG or JPG files only') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="form.header.format === 'VIDEO'">
                                    <div class="tpl-dropzone">
                                        <input
                                            type="file"
                                            class="sr-only"
                                            accept=".mp4"
                                            ref="fileInput"
                                            id="file-upload2"
                                            @change="handleFileUpload($event)"
                                        />
                                        <div class="text-center">
                                            <div>
                                                <div v-if="form.header.format === 'VIDEO' && form.header.example" class="flex justify-center items-center">
                                                    <div class="tpl-file-chip">
                                                        <div>
                                                            <svg class="tpl-file-icon mx-auto h-6 w-6 cursor-pointer" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.5" d="M2 11.5c0-3.287 0-4.931.908-6.038a4 4 0 0 1 .554-.554C4.57 4 6.212 4 9.5 4c3.287 0 4.931 0 6.038.908a4 4 0 0 1 .554.554C17 6.57 17 8.212 17 11.5v1c0 3.287 0 4.931-.908 6.038a4.001 4.001 0 0 1-.554.554C14.43 20 12.788 20 9.5 20c-3.287 0-4.931 0-6.038-.908a4 4 0 0 1-.554-.554C2 17.43 2 15.788 2 12.5v-1Zm15-2l.658-.329c1.946-.973 2.92-1.46 3.63-1.02c.712.44.712 1.528.712 3.703v.292c0 2.176 0 3.263-.711 3.703c-.712.44-1.685-.047-3.63-1.02L17 14.5v-5Z"/></svg>
                                                        </div>
                                                        <div class="flex items-center gap-x-2">
                                                            <span class="text-sm">{{ form.header.example.name }}</span>
                                                            <button type="button" @click="removeFile()" class="tpl-icon-btn">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M17.707 7.707a1 1 0 0 0-1.414-1.414L12 10.586L7.707 6.293a1 1 0 0 0-1.414 1.414L10.586 12l-4.293 4.293a1 1 0 1 0 1.414 1.414L12 13.414l4.293 4.293a1 1 0 1 0 1.414-1.414L13.414 12l4.293-4.293Z" clip-rule="evenodd"/></svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <label v-else for="file-upload2">
                                                    <svg class="tpl-dropzone-icon mx-auto h-12 w-12 cursor-pointer" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.5" d="M2 11.5c0-3.287 0-4.931.908-6.038a4 4 0 0 1 .554-.554C4.57 4 6.212 4 9.5 4c3.287 0 4.931 0 6.038.908a4 4 0 0 1 .554.554C17 6.57 17 8.212 17 11.5v1c0 3.287 0 4.931-.908 6.038a4.001 4.001 0 0 1-.554.554C14.43 20 12.788 20 9.5 20c-3.287 0-4.931 0-6.038-.908a4 4 0 0 1-.554-.554C2 17.43 2 15.788 2 12.5v-1Zm15-2l.658-.329c1.946-.973 2.92-1.46 3.63-1.02c.712.44.712 1.528.712 3.703v.292c0 2.176 0 3.263-.711 3.703c-.712.44-1.685-.047-3.63-1.02L17 14.5v-5Z"/></svg>
                                                </label>
                                                <div class="flex text-sm ui-text-muted">
                                                    <label for="file-upload2" class="tpl-dropzone-label">
                                                        <span>{{ $t('Provide examples of the variables or media in the header') }}</span>
                                                    </label>
                                                </div>
                                                <p class="text-xs ui-text-muted">{{ $t('MP4 files only') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="form.header.format === 'DOCUMENT'">
                                    <div class="tpl-dropzone">
                                        <input
                                            type="file"
                                            class="sr-only"
                                            accept=".pdf"
                                            ref="fileInput"
                                            id="file-upload3"
                                            @change="handleFileUpload($event)"
                                        />
                                        <div class="text-center">
                                            <div>
                                                <div v-if="form.header.format === 'DOCUMENT' && form.header.example" class="flex justify-center items-center">
                                                    <div class="tpl-file-chip">
                                                        <div>
                                                            <svg class="tpl-file-icon mx-auto h-6 w-6 cursor-pointer" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M18.53 9L13 3.47a.75.75 0 0 0-.53-.22H8A2.75 2.75 0 0 0 5.25 6v12A2.75 2.75 0 0 0 8 20.75h8A2.75 2.75 0 0 0 18.75 18V9.5a.75.75 0 0 0-.22-.5Zm-5.28-3.19l2.94 2.94h-2.94ZM16 19.25H8A1.25 1.25 0 0 1 6.75 18V6A1.25 1.25 0 0 1 8 4.75h3.75V9.5a.76.76 0 0 0 .75.75h4.75V18A1.25 1.25 0 0 1 16 19.25Z"/><path fill="currentColor" d="M13.49 14.85a3.15 3.15 0 0 1-1.31-1.66a4.44 4.44 0 0 0 .19-2a.8.8 0 0 0-1.52-.19a5 5 0 0 0 .25 2.4A29 29 0 0 1 9.83 16c-.71.4-1.68 1-1.83 1.69c-.12.56.93 2 2.72-1.12a18.58 18.58 0 0 1 2.44-.72a4.72 4.72 0 0 0 2 .61a.82.82 0 0 0 .62-1.38c-.42-.43-1.67-.31-2.29-.23Zm-4.78 3a4.32 4.32 0 0 1 1.09-1.24c-.68 1.08-1.09 1.27-1.09 1.25Zm2.92-6.81c.26 0 .24 1.15.06 1.46a3.07 3.07 0 0 1-.06-1.45Zm-.87 4.88a14.76 14.76 0 0 0 .88-1.92a3.88 3.88 0 0 0 1.08 1.26a12.35 12.35 0 0 0-1.96.67Zm4.7-.18s-.18.22-1.33-.28c1.25-.08 1.46.21 1.33.29Z"/></svg>
                                                        </div>
                                                        <div class="flex items-center gap-x-2">
                                                            <span class="text-sm">{{ form.header.example.name }}</span>
                                                            <button type="button" @click="removeFile()" class="tpl-icon-btn">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M17.707 7.707a1 1 0 0 0-1.414-1.414L12 10.586L7.707 6.293a1 1 0 0 0-1.414 1.414L10.586 12l-4.293 4.293a1 1 0 1 0 1.414 1.414L12 13.414l4.293 4.293a1 1 0 1 0 1.414-1.414L13.414 12l4.293-4.293Z" clip-rule="evenodd"/></svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <label v-else for="file-upload3">
                                                    <svg class="tpl-dropzone-icon mx-auto h-12 w-12 cursor-pointer" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M18.53 9L13 3.47a.75.75 0 0 0-.53-.22H8A2.75 2.75 0 0 0 5.25 6v12A2.75 2.75 0 0 0 8 20.75h8A2.75 2.75 0 0 0 18.75 18V9.5a.75.75 0 0 0-.22-.5Zm-5.28-3.19l2.94 2.94h-2.94ZM16 19.25H8A1.25 1.25 0 0 1 6.75 18V6A1.25 1.25 0 0 1 8 4.75h3.75V9.5a.76.76 0 0 0 .75.75h4.75V18A1.25 1.25 0 0 1 16 19.25Z"/><path fill="currentColor" d="M13.49 14.85a3.15 3.15 0 0 1-1.31-1.66a4.44 4.44 0 0 0 .19-2a.8.8 0 0 0-1.52-.19a5 5 0 0 0 .25 2.4A29 29 0 0 1 9.83 16c-.71.4-1.68 1-1.83 1.69c-.12.56.93 2 2.72-1.12a18.58 18.58 0 0 1 2.44-.72a4.72 4.72 0 0 0 2 .61a.82.82 0 0 0 .62-1.38c-.42-.43-1.67-.31-2.29-.23Zm-4.78 3a4.32 4.32 0 0 1 1.09-1.24c-.68 1.08-1.09 1.27-1.09 1.25Zm2.92-6.81c.26 0 .24 1.15.06 1.46a3.07 3.07 0 0 1-.06-1.45Zm-.87 4.88a14.76 14.76 0 0 0 .88-1.92a3.88 3.88 0 0 0 1.08 1.26a12.35 12.35 0 0 0-1.96.67Zm4.7-.18s-.18.22-1.33-.28c1.25-.08 1.46.21 1.33.29Z"/></svg>
                                                </label>
                                                <div class="flex text-sm ui-text-muted">
                                                    <label for="file-upload3" class="tpl-dropzone-label">
                                                        <span>{{ $t('Provide examples of the variables or media in the header') }}</span>
                                                    </label>
                                                </div>
                                                <p class="text-xs ui-text-muted">{{ $t('PDF files only') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </UiFormSection>

                        <UiFormSection :title="`${$t('Body')} (${$t('Required')})`" :description="$t('Enter the text for your message in the language that you\'ve selected')">
                            <BodyTextArea v-model="form.body.text" :customValues="form.body.example" @updateExamples="updateBodyExamples"/>
                        </UiFormSection>

                        <UiFormSection :title="`${$t('Footer description')} (${$t('Optional')})`" :description="$t('Add a short line of text to the bottom of your message template')">
                            <FormTextArea v-model="form.footer.text" @input="characterCount('footer')" :name="$t('Footer text')" :showLabel="false" :type="'text'" :textAreaRows="2" :class="'sm:col-span-6'"/>
                            <span class="text-xs ui-text-muted">{{ $t('Characters') }}: {{ footerCharacterCount }}/{{ footerCharacterLimit }}</span>
                        </UiFormSection>

                        <UiFormSection :title="`${$t('Buttons')} (${$t('Optional')})`" :description="$t('Create buttons that let customers respond to your message or take action')">
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <button type="button" @click="addButton('call')" class="tpl-add-btn">{{ $t('Call phone number (1)') }}</button>
                                <button type="button" @click="addButton('website')" class="tpl-add-btn">{{ $t('Visit website (2)') }}</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <button type="button" @click="addButton('offer')" class="tpl-add-btn">{{ $t('Copy offer code (1)') }}</button>
                                <button type="button" @click="addButton('custom')" class="tpl-add-btn">{{ $t('Custom button (6)') }}</button>
                            </div>
                            <div v-if="form?.buttons?.length > 0" class="mt-3">
                                <div v-for="(button, index) in form.buttons" :key="index" class="ui-bg-soft p-3 rounded-lg mb-3">
                                    <div class="flex items-center justify-between pb-1">
                                        <span class="text-sm">{{ formatText(button.type) }}</span>
                                        <button type="button" @click="removeButton(index)" class="tpl-icon-btn">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M17.707 7.707a1 1 0 0 0-1.414-1.414L12 10.586L7.707 6.293a1 1 0 0 0-1.414 1.414L10.586 12l-4.293 4.293a1 1 0 1 0 1.414 1.414L12 13.414l4.293 4.293a1 1 0 1 0 1.414-1.414L13.414 12l4.293-4.293Z" clip-rule="evenodd"/></svg>
                                        </button>
                                    </div>
                                    <div class="flex gap-x-1 pt-2 tpl-button-row">
                                        <FormInput v-model="button.text" :name="$t('Button text')" :type="'text'" :class="button.type === 'QUICK_REPLY' ? 'w-full' :'sm:col-span-2'" :labelClass="'mb-0'"/>
                                        <FormInput v-model="button.url" v-if="button.type === 'URL'" :name="$t('Website url')" :type="'text'" :class="'w-full'" :labelClass="'mb-0'"/>
                                        <FormInput v-model="button.country" v-if="button.type === 'PHONE_NUMBER'" :name="$t('Country')" :type="'text'" :class="'sm:col-span-2'" :labelClass="'mb-0'"/>
                                        <FormInput v-model="button.phone_number" v-if="button.type === 'PHONE_NUMBER'" :name="$t('Phone number')" :type="'text'" :class="'sm:col-span-2'" :labelClass="'mb-0'"/>
                                        <FormInput v-model="button.example" v-if="button.type === 'COPY_CODE'" :name="$t('Sample code')" :type="'text'" :class="'w-full'" :labelClass="'mb-0'"/>
                                    </div>
                                </div>
                            </div>
                        </UiFormSection>
                    </template>

                    <template v-if="form.category === 'AUTHENTICATION'">
                        <UiFormSection :title="$t('Code delivery setup')" :description="$t('Choose how customers send the code from WhatsApp to your app.')">
                            <div class="tpl-option-list">
                                <button
                                    type="button"
                                    v-for="option in codeDeliveryOptions"
                                    :key="option.value"
                                    class="tpl-option-card"
                                    :class="{ 'is-selected': form.authentication_button.otp_type === option.value }"
                                    @click="form.authentication_button.otp_type = option.value"
                                >
                                    <span class="tpl-option-radio">
                                        <span v-if="form.authentication_button.otp_type === option.value" class="tpl-option-radio-dot"></span>
                                    </span>
                                    <span>
                                        <span class="tpl-option-title">{{ $t(option.label) }}</span>
                                        <span class="tpl-option-desc">{{ $t(option.description) }}</span>
                                    </span>
                                </button>
                            </div>
                        </UiFormSection>

                        <UiFormSection v-if="form.authentication_button.otp_type != 'copy_code'" :title="$t('App setup')" :description="$t('You can add up to 5 apps.')">
                            <div v-for="(item, index) in form.authentication_button.supported_apps" :key="index" class="flex gap-x-2 rounded-lg mb-3">
                                <FormInput v-model="form.authentication_button.supported_apps[index].package_name" :name="$t('Package name')" :type="'text'" :class="'w-3/5'" :labelClass="'mb-0'"/>
                                <FormInput v-model="form.authentication_button.supported_apps[index].signature_hash" :name="$t('App signature hash')" :type="'text'" :class="'w-2/5'" :labelClass="'mb-0'"/>
                                <button v-if="form.authentication_button.supported_apps.length > 1" type="button" @click="removeSupportedApp(index)" class="tpl-icon-btn mt-7">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M7.404 7.404a.5.5 0 0 1 .707 0L12 11.293l3.89-3.89a.5.5 0 1 1 .706.708L12.707 12l3.89 3.89a.5.5 0 1 1-.708.706L12 12.707l-3.89 3.89a.5.5 0 1 1-.706-.708L11.293 12l-3.89-3.89a.5.5 0 0 1 0-.706"/></svg>
                                </button>
                            </div>

                            <div class="tpl-note">
                                {{ $t('It is recommended to only include different builds of the same app (which would not coexist on a production user\'s phone), rather than entirely different apps.') }}
                            </div>

                            <button v-if="form.authentication_button.supported_apps.length < 5" @click="addSupportedApp()" type="button" class="tpl-add-btn w-auto px-4">{{ $t('Add another app') }}</button>
                        </UiFormSection>

                        <UiFormSection :title="$t('Content')" :description="$t('Authentication message content follows WhatsApp rules. You can customize the preview text below.')">
                            <div class="grid gap-3 mb-4">
                                <FormInput
                                    v-model="form.body.verification_message_content"
                                    :name="$t('Verification Message Content')"
                                    :placeholder="$t('is your verification code')"
                                    :type="'text'"
                                    :class="'w-full'"
                                    :labelClass="'mb-0'"
                                />
                                <FormInput
                                    v-model="form.body.security_recommendation_text"
                                    :name="$t('Security Recommendation Text')"
                                    :placeholder="$t('For your security, do not share this code')"
                                    :type="'text'"
                                    :class="'w-full'"
                                    :labelClass="'mb-0'"
                                />
                            </div>
                            <FormCheckbox v-model="form.body.add_security_recommendation" :name="'add_security_recommendation'" :label="$t('Add security recommendation')"/>
                            <FormCheckbox v-model="form.code_expiration" :name="'code_expiration'" :label="$t('Add expiry time for the code')"/>
                            <p class="text-[11px] ui-text-muted ms-8 -mt-2 mb-2">{{ $t('After the code has expired, the auto-fill button will be disabled.') }}</p>
                            <div v-if="form.code_expiration" class="tpl-expiry-box">
                                <h2 class="ui-form-label mb-2">{{ $t('Expires In') }}</h2>
                                <div class="flex items-center gap-x-2 w-1/2">
                                    <FormInput v-model="form.footer.code_expiration_minutes" :type="'number'" :hideLabel="true" :class="'w-full'"/>
                                    <span class="text-sm ui-text-muted">{{ $t('Minutes') }}</span>
                                </div>
                            </div>
                        </UiFormSection>

                        <UiFormSection :title="$t('Buttons')" :description="$t('You can customise the button text for both auto-fill and copy code. Even when zero-tap is turned on, buttons are still needed for the backup code delivery method.')">
                            <FormInput v-if="form.authentication_button.otp_type == 'copy_code'" v-model="form.authentication_button.text" :name="$t('Copy code')" :type="'text'" :labelClass="'mb-0'"/>
                            <div v-else class="flex gap-x-2">
                                <FormInput v-model="form.authentication_button.autofill_text" :name="$t('Auto-fill')" :type="'text'" :class="'w-1/2'" :labelClass="'mb-0'"/>
                                <FormInput v-model="form.authentication_button.text" :name="$t('Copy code')" :type="'text'" :class="'w-1/2'" :labelClass="'mb-0'"/>
                            </div>
                        </UiFormSection>
                    </template>

                    <UiFormSection v-if="form.category == 'UTILITY' || form.category == 'AUTHENTICATION'" :title="$t('Message validity period')" :description="$t('It\'s recommended to set a custom validity period that your authentication message must be delivered by before it expires. If a message is not delivered within this time frame, you will not be charged and your customer will not see the message.')">
                        <div class="flex items-center justify-between gap-4 mb-3">
                            <p class="text-[11px] ui-text-muted flex-1">{{ $t('If you don\'t set a custom validity period, the standard 10 minutes WhatsApp message validity period will be applied.') }}</p>
                            <FormToggleSwitch v-model="form.customize_ttl" />
                        </div>
                        <FormSelect v-if="form.customize_ttl" v-model="form.message_send_ttl_seconds" :options="form.category == 'UTILITY' ? utilityTTLOptions : authTTLOptions" :name="$t('Validity period')" :class="'sm:col-span-3'" :placeholder="$t('Select validity period')"/>
                    </UiFormSection>
                </div>

                <div class="wizard-preview">
                    <div class="wizard-preview-frame">
                        <div class="wizard-preview-topbar">
                            <span class="wizard-preview-avatar">{{ orgInitial }}</span>
                            <div>
                                <div class="wizard-preview-title">{{ orgName }}</div>
                                <div class="wizard-preview-sub">{{ $t('Live preview') }}</div>
                            </div>
                        </div>
                        <div class="ui-workspace-main--chat wizard-preview-body">
                            <WhatsappTemplate :parameters="previewParameters" :visible="true"/>
                        </div>
                    </div>
                    <p class="wizard-preview-hint">{{ $t('This preview updates instantly as you edit the template.') }}</p>
                </div>
            </div>
        </div>
        <Modal :label="''" :isOpen="isModalOpen" :showHeader="false" @close="closeModal">
            <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
                <div class="text-center">
                    <div v-if="error != null" class="ui-chip-danger ui-danger-text rounded text-sm p-2 mb-4">
                        <div>{{ $t('Error') }}: </div>
                        <div>{{ error }}</div>
                        <button type="button" @click="closeModal" class="mt-4 inline-flex justify-center rounded-md border border-transparent ui-bg-soft ui-text-muted px-4 py-2 text-sm hover:bg-[var(--ui-border)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ui-secondary)] focus-visible:ring-offset-2 me-4">{{ $t('Close') }}</button>
                    </div>
                    <div v-else>
                        <h2 class="text-xl">{{ $t('Your template is being updated!') }}</h2>
                        <div class="flex justify-center mt-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-dasharray="15" stroke-dashoffset="15" stroke-linecap="round" stroke-width="2" d="M12 3C16.9706 3 21 7.02944 21 12"><animate fill="freeze" attributeName="stroke-dashoffset" dur="0.3s" values="15;0"/><animateTransform attributeName="transform" dur="1.5s" repeatCount="indefinite" type="rotate" values="0 12 12;360 12 12"/></path></svg>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
<script setup>
    import axios from "axios";
    import AppLayout from "./../Layout/App.vue";
    import BodyTextArea from '@/Components/Template/BodyTextArea.vue';
    import HeaderTextArea from '@/Components/Template/HeaderTextArea.vue';
    import FormCheckbox from '@/Components/FormCheckbox.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiFormSection from '@/Components/UI/UiFormSection.vue';
    import WhatsappTemplate from '@/Components/WhatsappTemplate.vue';
    import { ref, computed } from 'vue';
    import { localizeTemplateLanguageOptions } from '@/Utils/optionLocalizers';
    import { useI18n } from 'vue-i18n';
    import { Link } from "@inertiajs/vue3";
    import Modal from '@/Components/Modal.vue';
    import 'vue3-toastify/dist/index.css';
    import { router } from '@inertiajs/vue3';
    const { locale, t } = useI18n();

    const props = defineProps(['languages', 'template']);
    const headerCharacterLimit = ref('60');
    const headerCharacterCount = ref('0');
    const bodyCharacterLimit = ref('1098');
    const bodyCharacterCount = ref('0');
    const footerCharacterLimit = ref('60');
    const footerCharacterCount = ref('0');
    const isLoading = ref(false);
    const imageUrl = ref(null);
    const isModalOpen = ref(false);
    const error = ref(null);
    const metadata = ref(JSON.parse(props.template.metadata));
    const extractComponent = (type, key, fromButtons = false) => {
        const component = metadata.value.components.find(c => c.type === type);

        if (!component) return null;

        if (fromButtons && component.buttons && component.buttons.length > 0) {
            // Extract the first button (modify as needed for multiple buttons)
            const button = component.buttons[0];
            return button.hasOwnProperty(key) ? button[key] : null;
        }

        return component.hasOwnProperty(key) ? component[key] : null;
    };

    const extractButtonParam = (key) => {
        const buttonsComponent = metadata.value.components.find(component => component.type === 'BUTTONS');

        if (!buttonsComponent || !buttonsComponent.buttons || buttonsComponent.buttons.length === 0) {
            return null; // No BUTTONS component found
        }

        const button = buttonsComponent.buttons[0]; // Assuming only one button exists
        if (!button.url) return null; // No URL present

        const params = new URLSearchParams(new URL(button.url).search);
        return params.get(key) || null; // Return only the requested key
    };

    const form = ref({
        'name' : props.template.name,
        'category' : props.template.category,
        'language' : props.template.language,
        'message_send_ttl_seconds' : null,
        'customize_ttl': false,
        'header' : {
            'format' : extractComponent('HEADER', 'format') ?? 'TEXT',
            'text' : extractComponent('HEADER', 'text'),
            'example' : extractComponent('HEADER', 'example')?.header_text,
        },
        'body' : {
            'text' : extractComponent('BODY', 'text'),
            'variables' : null,
            'add_security_recommendation' : extractComponent('BODY', 'add_security_recommendation'),
            'verification_message_content': t('is your verification code'),
            'security_recommendation_text': t('For your security, do not share this code'),
            'example' : extractComponent('BODY', 'example')?.body_text?.[0],
        },
        'footer' : {
            'text' : extractComponent('FOOTER', 'text'),
            'code_expiration_minutes' : extractComponent('FOOTER', 'code_expiration_minutes'),
        },
        'code_expiration' : extractComponent('FOOTER', 'code_expiration_minutes') ? true : false,
        'set_custom_validity_period' : false,
        'buttons' : extractComponent('BUTTONS', 'buttons'),
        'authentication_button' : {
            'type' : 'OTP',
            "otp_type": extractButtonParam('otp_type') ? extractButtonParam('otp_type').toLowerCase() : null,
            'text' : extractComponent('BUTTONS', 'text', true) ?? t('Copy code'),
            "autofill_text": extractButtonParam('cta_display_name') ?? t('Auto-fill'),
            "zero_tap_terms_accepted": true,
            "supported_apps": [
                {
                    "package_name": null,
                    "signature_hash": null,
                },
            ]
        }
    });

    const orgName = props.template?.name ?? t('Template');
    const orgInitial = (orgName || '?').trim().charAt(0).toUpperCase();

    const headerType = ref('text');
    const langOptions = computed(() =>
        localizeTemplateLanguageOptions(props.languages ?? [], locale.value)
    );
    const categoryOptions = ref([
        { value: 'UTILITY', label: t('Utility') },
        { value: 'MARKETING', label: t('Marketing') },
        { value: 'AUTHENTICATION', label: t('Authentication') },
    ])

    const authenticationPreviewMessage = computed(() => {
        const verificationMessage = String(form.value.body.verification_message_content ?? '').trim() || t('is your verification code');
        const securityRecommendation = String(form.value.body.security_recommendation_text ?? '').trim() || t('For your security, do not share this code');
        const messageParts = [`{{1}} ${verificationMessage}`];

        if (form.value.body.add_security_recommendation === true) {
            messageParts.push(securityRecommendation);
        }

        return messageParts.join(' ');
    });

    const previewParameters = computed(() => {
        if (form.value.category === 'AUTHENTICATION') {
            const otpType = form.value.authentication_button.otp_type;
            const buttons = (otpType && otpType !== 'zero_tap')
                ? [{
                    type: otpType === 'copy_code' ? 'COPY_CODE' : 'QUICK_REPLY',
                    text: otpType === 'copy_code' ? form.value.authentication_button.text : form.value.authentication_button.autofill_text,
                }]
                : [];

            return {
                header: { format: null },
                body: { text: authenticationPreviewMessage.value },
                footer: {
                    text: form.value.code_expiration
                        ? `${t('This code expires in')} ${form.value.footer.code_expiration_minutes} ${t('minutes')}`
                        : '',
                },
                buttons,
            };
        }

        return {
            header: form.value.header,
            body: { text: form.value.body.text },
            footer: { text: form.value.footer.text },
            buttons: form.value.buttons ?? [],
        };
    });

    const previousExamples = ref({});

    const changeHeaderType = (value) => {
        const currentType = form.value.header.format;

        if (
            !(currentType in previousExamples.value) ||
            JSON.stringify(previousExamples.value[currentType]) !== JSON.stringify(form.value.header.example)
        ) {
            // Store the example if it has changed
            previousExamples.value[currentType] = form.value.header.example;
        }

        form.value.header.format = value;

        if (previousExamples.value[value] !== undefined) {
            // Restore the example if switching back
            form.value.header.example = previousExamples.value[value];
        } else {
            // Set it to null explicitly to avoid fallback issues
            form.value.header.example = null;
        }
    }

    const handleNameInput = (event) => {
        const value = event.target.value.toLowerCase();
        form.value.name = value.replace(/[^a-zA-Z0-9_]/g, '');
    };

    const handleFileUpload = (event) => {
        const file = event.target.files[0];
        const reader = new FileReader();
        reader.onload = (e) => {
            imageUrl.value = e.target.result;
            form.value.header.example = file; // Update header example with the uploaded image
        };
        reader.readAsDataURL(file);
    };

    const removeFile = () => {
        form.value.header.example = '';
    };

    const addUnderscore = (event) => {
        event.preventDefault();
        form.value.name += '_';
    };

    const characterCount = (type) => {
        let limit = 0;
        let count = 0;
        switch (type) {
            case 'header':
            limit = headerCharacterLimit.value;
            count = form.value.header.text.length;
            if (count <= limit) {
                headerCharacterCount.value = count;
            } else {
                form.value.header.text = form.value.header.text.slice(0, limit);
                headerCharacterCount.value = limit;
            }
            break;

            case 'body':
            limit = bodyCharacterLimit.value;
            count = form.value.body.text.length;
            if (count <= limit) {
                bodyCharacterCount.value = count;
            } else {
                form.value.body.text = form.value.body.text.slice(0, limit);
                bodyCharacterCount.value = limit;
            }
            break;

            case 'footer':
            limit = footerCharacterLimit.value;
            count = form.value.footer.text.length;
            if (count <= limit) {
                footerCharacterCount.value = count;
            } else {
                form.value.footer.text = form.value.footer.text.slice(0, limit);
                footerCharacterCount.value = limit;
            }
            break;
        }
    };

    const addButton = ($type) => {
        if($type === 'call'){
            form.value.buttons.push({
                'type' : 'PHONE_NUMBER',
                'country' : null,
                'text' : null,
                'phone_number' : null,
            });
        } else if($type === 'website'){
            form.value.buttons.push({
                'type' : 'URL',
                'text' : null,
                'url' : null,
            });
        } else if($type === 'custom'){
            form.value.buttons.push({
                'type' : 'QUICK_REPLY',
                'text' : null,
            });
        } else if($type === 'offer'){
            form.value.buttons.push({
                'type' : 'COPY_CODE',
                'example' : null,
            });
        }
    }

    const addSupportedApp = () => {
        const appsCount = form.value.authentication_button.supported_apps.length;

        if(appsCount < 5){
            form.value.authentication_button.supported_apps.push({
                'package_name' : null,
                'signature_hash' : null,
            });
        }
    }

    const removeSupportedApp = (index) => {
        form.value.authentication_button.supported_apps.splice(index, 1);
    }

    const removeButton = (index) => {
        if (index >= 0 && index < form.value.buttons.length) {
            form.value.buttons.splice(index, 1);
        }
    };

    const isFormValid = computed(() => {
        if (
            form.value.name === null ||
            form.value.name.trim() === "" ||
            form.value.language === null ||
            form.value.language.trim() === "" ||
            form.value.category === null ||
            form.value.category.trim() === "" ||
            Array.isArray(form.value.buttons) && form.value.buttons.some(button => {
                return (
                    (button.name === null || button.name === '') ||
                    (button.type === null || button.type === '') ||
                    (button.country === null || button.country === '') ||
                    (button.text === null || button.text === '') ||
                    (button.phone_number === null || button.phone_number === '')
                );
            })
        ) {
            return false;
        } else {
            if (form.value.body.text === null || form.value.body.text.trim() === "") {
                return false;
            }
        }

        return true;
    });

    const submitForm = () => {
        isLoading.value = true;
        isModalOpen.value = true;
        axios.post('/templates/' + props.template.uuid, form.value, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        })
        .then(response => {
            if(response.data.success === false){
                isLoading.value = false;
                error.value = response.data.data.error.message;
            } else {
                router.visit('/templates', {
                    method: 'get',
                });
            }
        })
        .catch(() => {
            // Submission errors are surfaced by the API response branch above when available.
        });
    }

    const codeDeliveryOptions = [
        {
            value: "zero_tap",
            label: t("Zero-tap auto-fill"),
            description: t("This is recommended as the easiest option for your customers. Zero-tap will automatically send code without requiring your customer to tap a button. An auto-fill or copy code message will be sent if zero-tap and auto-fill aren’t possible."),
        },
        {
            value: "one_tap",
            label: t("One-tap auto-fill"),
            description: t("The code sends to your app when customers tap the button. A copy code message will be sent if auto-fill isn’t possible."),
        },
        {
            value: "copy_code",
            label: t("Copy code"),
            description: t("Basic authentication with quick setup. Your customers copy and paste the code into your app."),
        },
    ];

    const authTTLOptions = ref([
        { value: '30', label: t('30 seconds') },
        { value: '60', label: t('1 minute') },
        { value: '120', label: t('2 minutes') },
        { value: '180', label: t('3 minutes') },
        { value: '300', label: t('5 minutes') },
        { value: '600', label: t('10 minutes') },
        { value: '800', label: t('15 minutes') },
    ]);

    const utilityTTLOptions = ref([
        { value: '30', label: t('30 seconds') },
        { value: '60', label: t('1 minute') },
        { value: '120', label: t('2 minutes') },
        { value: '300', label: t('5 minutes') },
        { value: '600', label: t('10 minutes') },
        { value: '800', label: t('15 minutes') },
        { value: '1600', label: t('30 minutes') },
        { value: '3200', label: t('1 hour') },
        { value: '9600', label: t('3 hours') },
        { value: '19200', label: t('6 hours') },
        { value: '38400', label: t('12 hours') },
    ]);

    const updateHeaderExamples = (value) => {
        form.value.header.example = value;
    }

    const updateBodyExamples = (value) => {
        form.value.body.example = value;
    }

    function formatText(text) {
        return text
            .toLowerCase()
            .replace(/_/g, ' ')
            .replace(/^\w/, (c) => c.toUpperCase());
    }

    const closeModal = () => {
        isModalOpen.value = false;

        setTimeout(() => {
            error.value = null;
        }, 500);
    }
</script>

<style scoped>
.wizard-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.7rem;
    padding: 0.65rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
}

.wizard-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    border: none;
    transition: filter 160ms ease;
}
.wizard-btn--solid:hover:not(:disabled) { filter: brightness(1.05); }
.wizard-btn--solid:disabled { opacity: 0.55; cursor: not-allowed; }

.wizard-btn--ghost {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
    border: 1px solid var(--ui-border);
}
.wizard-btn--ghost:hover { background: var(--ui-border); }

.wizard-layout { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(20rem, 24rem); gap: 1.5rem; align-items: start; }
.wizard-main { display: flex; flex-direction: column; gap: 1rem; }

.wizard-preview { position: sticky; top: 1.25rem; }
.wizard-preview-frame { border: 1px solid var(--ui-border); border-radius: 1.25rem; overflow: hidden; box-shadow: 0 22px 48px -20px rgba(15, 23, 42, 0.25); background: var(--ui-surface); }
.wizard-preview-topbar { display: flex; align-items: center; gap: 0.6rem; padding: 0.9rem 1.1rem; border-bottom: 1px solid var(--ui-border); }
.wizard-preview-avatar {
    width: 2.1rem; height: 2.1rem; border-radius: 50%;
    background: color-mix(in srgb, var(--ui-secondary) 20%, transparent); color: var(--ui-secondary);
    display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.8rem;
}
.wizard-preview-title { font-size: 0.82rem; font-weight: 700; }
.wizard-preview-sub { font-size: 0.7rem; color: var(--ui-muted); }
.wizard-preview-body { padding: 2rem 1.1rem; min-height: 22rem; display: flex; align-items: flex-end; }
.wizard-preview-hint { text-align: center; font-size: 0.76rem; color: var(--ui-muted); margin-top: 0.9rem; line-height: 1.6; }

.wizard-segmented { display: flex; border: 1px solid var(--ui-border); border-radius: 0.7rem; overflow: hidden; width: 100%; }
.wizard-segmented button {
    flex: 1;
    border: none; background: var(--ui-surface-soft); color: var(--ui-muted);
    font-size: 0.82rem; font-weight: 700; padding: 0.6rem 0.6rem; cursor: pointer;
}
.wizard-segmented button.is-active { background: var(--ui-secondary); color: #fff; }

.tpl-dropzone { display: flex; justify-content: center; padding: 1.6rem 1.2rem; border: 2px dashed var(--ui-border-strong); border-radius: 0.9rem; background: var(--ui-surface-soft); }
.tpl-dropzone-icon { color: var(--ui-muted); }
.tpl-dropzone-label { color: var(--ui-secondary); font-weight: 600; cursor: pointer; }
.tpl-file-chip { display: flex; align-items: center; gap: 0.6rem; padding: 0.4rem 0.7rem; border: 1px solid var(--ui-border); background: var(--ui-surface); border-radius: 0.6rem; width: fit-content; }
.tpl-file-icon { color: var(--ui-muted); }
.tpl-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 1.7rem; height: 1.7rem; border-radius: 999px; background: var(--ui-surface); border: 1px solid var(--ui-border); color: var(--ui-muted); flex: none; }
.tpl-icon-btn:hover { background: var(--ui-border); }

.tpl-add-btn { display: flex; align-items: center; justify-content: center; text-align: center; font-size: 0.82rem; font-weight: 600; color: var(--ui-text); background: var(--ui-surface-soft); border: 1px solid var(--ui-border); border-radius: 0.7rem; padding: 0.6rem 1rem; transition: background-color 160ms ease, border-color 160ms ease; }
.tpl-add-btn:hover { background: var(--ui-border); }

.tpl-button-row { border-top: 1px solid var(--ui-border); }

.tpl-option-list { display: flex; flex-direction: column; gap: 0.6rem; }
.tpl-option-card { display: flex; align-items: flex-start; gap: 0.75rem; border: 2px solid var(--ui-border); border-radius: 0.9rem; padding: 0.85rem 1rem; background: var(--ui-surface-soft); cursor: pointer; transition: all 180ms ease; text-align: start; width: 100%; }
.tpl-option-card:hover { border-color: var(--ui-border-strong); }
.tpl-option-card.is-selected { border-color: var(--ui-secondary); background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface)); }
.tpl-option-radio { width: 1.2rem; height: 1.2rem; border-radius: 50%; border: 2px solid var(--ui-border-strong); flex: none; margin-top: 0.1rem; display: inline-flex; align-items: center; justify-content: center; }
.tpl-option-card.is-selected .tpl-option-radio { border-color: var(--ui-secondary); }
.tpl-option-radio-dot { width: 0.5rem; height: 0.5rem; border-radius: 50%; background: var(--ui-secondary); }
.tpl-option-title { display: block; font-size: 0.85rem; font-weight: 700; color: var(--ui-text); }
.tpl-option-desc { display: block; font-size: 0.74rem; color: var(--ui-muted); line-height: 1.5; margin-top: 0.15rem; }

.tpl-note { font-size: 0.72rem; line-height: 1.5; color: var(--ui-muted); background: var(--ui-surface-soft); border: 1px solid var(--ui-border); border-radius: 0.6rem; padding: 0.7rem 0.8rem; margin-bottom: 1rem; }
.tpl-expiry-box { border: 1px solid var(--ui-border); border-radius: 0.7rem; padding: 0.8rem; background: var(--ui-surface-soft); margin-top: 0.5rem; }

@media (max-width: 980px) {
    .wizard-layout { grid-template-columns: 1fr; }
    .wizard-preview { position: static; }
}
</style>
