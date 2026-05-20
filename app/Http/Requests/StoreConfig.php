<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConfig extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [];
        $premiumHomeMediaKeys = config('frontend.premium_home_media_keys', []);
        $seoMediaKeys = config('frontend.seo_media_keys', []);
        
        if ($this->type == 'general') {
            $rules['company_name'] = 'required';
            $rules['head_scripts'] = 'nullable|string|max:5000';
            $rules['head_styles'] = 'nullable|string|max:5000';
            $rules['body_scripts'] = 'nullable|string|max:5000';
            $rules['meta_tags'] = 'nullable|string|max:2000';
            $rules['book_a_demo_link'] = 'nullable|url';
            $rules['frontend_variant'] = ['nullable', Rule::in(['classic', 'premium'])];
        }

        if ($this->type == 'timezone') {
            $allowedTimezones = collect(config('formats.timezones', []))
                ->pluck('value')
                ->filter()
                ->values()
                ->all();

            $rules['timezone'] = ['required', Rule::in($allowedTimezones)];
            $rules['currency'] = ['required', Rule::in(['SAR'])];
            $rules['date_format'] = 'required';
            $rules['time_format'] = 'required';
        }

        if ($this->type == 'broadcast') {
            $rules['broadcast_driver'] = 'required';
            $rules['pusher_app_key'] = 'required';
            $rules['pusher_app_id'] = 'required';
            $rules['pusher_app_secret'] = 'required';
            $rules['pusher_app_cluster'] = 'required';
        }

        if ($this->type == 'socials') {
            if($this->allow_facebook_login){
                $rules['facebook_login.client_id'] = 'required';
                $rules['facebook_login.client_secret'] = 'required';
            }

            if($this->allow_google_login){
                $rules['google_login.client_id'] = 'required';
                $rules['google_login.client_secret'] = 'required';
            }
        }

        if ($this->type == 'subscription') {
            $rules['trial_period'] = 'required|numeric|gte:0';
            $rules['trial_limits.users'] = 'required|numeric|gte:-1';
            $rules['trial_limits.messages'] = 'required|numeric|gte:-1';
            $rules['trial_limits.campaigns'] = 'required|numeric|gte:-1';
            $rules['trial_limits.contacts'] = 'required|numeric|gte:-1';
            $rules['trial_limits.automated_replies'] = 'required|numeric|gte:-1';
            $rules['trial_addons'] = 'nullable|array';
            $rules['trial_addons.*'] = 'boolean';
        }

        if ($this->type == 'email') {
            $rules['mail_config.driver'] = 'required';
            $rules['mail_config.from_name'] = 'required';
            $rules['mail_config.from_address'] = 'required';
            $rules['mail_config.reply_to_name'] = 'required';
            $rules['mail_config.reply_to_address'] = 'required';

            if ($this->mail_config['driver'] === 'smtp') {
                $rules['mail_config.host'] = 'required';
                $rules['mail_config.port'] = 'required';
                $rules['mail_config.username'] = 'required';
                $rules['mail_config.password'] = 'required';
            } elseif ($this->mail_config['driver'] === 'resend') {
                $rules['mail_config.resend_api_key'] = 'required';
            } elseif ($this->mail_config['driver'] === 'mailgun') {
                $rules['mail_config.mg_domain'] = 'required';
                $rules['mail_config.mg_secret'] = 'required';
            } elseif ($this->mail_config['driver'] === 'ses') {
                $rules['mail_config.ses_key'] = 'required';
                $rules['mail_config.ses_secret'] = 'required';
                $rules['mail_config.ses_region'] = 'required';
            }
        }

        if ($this->type == 'storage') {
            if($this->storage_system === 'aws'){
                $rules['aws.access_key'] = 'required';
                $rules['aws.secret_key'] = 'required';
                $rules['aws.default_region'] = 'required';
                $rules['aws.bucket'] = 'required';
            }
        }

        if ($this->type == 'billing') {
            $rules['billing_name'] = 'required';
        }

        if ($this->type == 'embedded-signup') {
            $rules['is_embedded_signup_active'] = 'required|boolean';
            $rules['whatsapp_client_id'] = 'required_if:is_embedded_signup_active,1,true|nullable|string';
            $rules['whatsapp_client_secret'] = 'required_if:is_embedded_signup_active,1,true|nullable|string';
            $rules['whatsapp_config_id'] = 'required_if:is_embedded_signup_active,1,true|nullable|string';
            $rules['whatsapp_access_token'] = 'nullable|string';
        }

        if ($this->type == 'premium-home-media') {
            foreach ($premiumHomeMediaKeys as $key) {
                $rules[$key] = 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:5120';
                $rules['remove_'.$key] = 'nullable|boolean';
            }
        }

        if ($this->type == 'frontend-seo') {
            $rules['seo_site_name_ar'] = 'nullable|string|max:120';
            $rules['seo_site_name_en'] = 'nullable|string|max:120';
            $rules['seo_home_title_ar'] = 'nullable|string|max:180';
            $rules['seo_home_title_en'] = 'nullable|string|max:180';
            $rules['seo_default_title_ar'] = 'nullable|string|max:180';
            $rules['seo_default_title_en'] = 'nullable|string|max:180';
            $rules['seo_home_description_ar'] = 'nullable|string|max:320';
            $rules['seo_home_description_en'] = 'nullable|string|max:320';
            $rules['seo_default_description_ar'] = 'nullable|string|max:320';
            $rules['seo_default_description_en'] = 'nullable|string|max:320';
            $rules['seo_keywords_ar'] = 'nullable|string|max:500';
            $rules['seo_keywords_en'] = 'nullable|string|max:500';
            $rules['seo_og_title_ar'] = 'nullable|string|max:180';
            $rules['seo_og_title_en'] = 'nullable|string|max:180';
            $rules['seo_og_description_ar'] = 'nullable|string|max:320';
            $rules['seo_og_description_en'] = 'nullable|string|max:320';
            $rules['seo_twitter_card'] = ['nullable', Rule::in(['summary', 'summary_large_image'])];
            $rules['seo_twitter_site'] = 'nullable|string|max:80';
            $rules['seo_canonical_base_url'] = 'nullable|url|max:255';
            $rules['seo_google_verification'] = 'nullable|string|max:255';
            $rules['seo_bing_verification'] = 'nullable|string|max:255';
            $rules['seo_robots_index'] = 'nullable|boolean';
            $rules['seo_robots_follow'] = 'nullable|boolean';
            $rules['seo_robots_custom'] = 'nullable|string|max:4000';
            $rules['tracking_meta_pixel_id'] = ['nullable', 'string', 'max:64', 'regex:/^[0-9]{5,32}$/'];
            $rules['tracking_tiktok_pixel_id'] = ['nullable', 'string', 'max:64', 'regex:/^[A-Z0-9]{10,64}$/i'];
            $rules['head_scripts'] = 'nullable|string|max:5000';
            $rules['body_scripts'] = 'nullable|string|max:5000';
            $rules['meta_tags'] = 'nullable|string|max:2000';

            foreach ($seoMediaKeys as $key) {
                $rules[$key] = 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:5120';
                $rules['remove_'.$key] = 'nullable|boolean';
            }
        }

        if ($this->type == 'frontend-contact') {
            $rules['frontend_contact_phone_primary'] = 'nullable|string|max:40';
            $rules['frontend_contact_phone_secondary'] = 'nullable|string|max:40';
            $rules['frontend_contact_address_primary_ar'] = 'nullable|string|max:500';
            $rules['frontend_contact_address_primary_en'] = 'nullable|string|max:500';
            $rules['frontend_contact_address_secondary_ar'] = 'nullable|string|max:500';
            $rules['frontend_contact_address_secondary_en'] = 'nullable|string|max:500';
            $rules['frontend_contact_business_hours_primary_ar'] = 'nullable|string|max:255';
            $rules['frontend_contact_business_hours_primary_en'] = 'nullable|string|max:255';
            $rules['frontend_contact_business_hours_secondary_ar'] = 'nullable|string|max:255';
            $rules['frontend_contact_business_hours_secondary_en'] = 'nullable|string|max:255';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'required' => __('This field is required.'),
            'trial_period.gte' => __('This field must be greater than or equal to 0.'),
            'trial_limits.users.gte' => __('This field must be greater than or equal to -1.'),
            'trial_limits.messages.gte' => __('This field must be greater than or equal to -1.'),
            'trial_limits.campaigns.gte' => __('This field must be greater than or equal to -1.'),
            'trial_limits.contacts.gte' => __('This field must be greater than or equal to -1.'),
            'trial_limits.automated_replies.gte' => __('This field must be greater than or equal to -1.'),
            'seo_twitter_card.in' => __('Twitter card type must be summary or summary_large_image.'),
            'tracking_meta_pixel_id.regex' => __('Use a numeric Meta pixel ID.'),
            'tracking_tiktok_pixel_id.regex' => __('Use a valid TikTok pixel ID.'),
        ];
    }

}
