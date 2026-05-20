<?php

namespace App\Http\Requests\AutomationFlows;

use App\Services\AutomationFlows\AutomationFlowWhatsappComplianceService;
use Illuminate\Foundation\Http\FormRequest;

class UploadAutomationFlowAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:102400'],
            'media_kind' => ['required', 'string', 'in:image,video,audio,document'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $file = $this->file('file');

            if (!$file) {
                return;
            }

            $compliance = app(AutomationFlowWhatsappComplianceService::class);
            $mediaKind = $this->input('media_kind');

            foreach ($compliance->validateUploadedAsset($mediaKind, $file) as $message) {
                $validator->errors()->add('file', $message);
            }
        });
    }
}
