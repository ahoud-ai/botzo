<?php

namespace App\Services;

use App\Http\Resources\EmailLogsResource;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Support\EmailTemplateCatalog;
use Illuminate\Validation\ValidationException;

class EmailService
{
    /**
     * Get all emails based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get(object $request)
    {
        $emails = (new EmailLog)->listAll($request->query('search'));

        return EmailLogsResource::collection($emails);
    }

    /**
     * Get all email templates based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function getTemplates(object $request)
    {
        $templates = (new EmailTemplate)->listAll($request->query('search'));

        return EmailLogsResource::collection($templates);
    }

    /**
     * Retrieve an email template by its ID.
     *
     * @param string $id
     * @return \App\Models\EmailTemplate
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getTemplateByID($request, $id)
    {
        $template = EmailTemplate::where('id', $id)->first();

        return $template;
    }

    /**
     * Update email template.
     *
     * @param Request $request
     * @param number $id
     * @return \App\Models\EmailTemplate
     */
    public function updateTemplate($request, $id)
    {
        $template = EmailTemplate::where('id', $id)->firstOrFail();
        $subject = clean($request->input('subject'));
        $body = clean($request->input('body'), 'email_template');

        $subjectUnsupportedPlaceholders = EmailTemplateCatalog::unsupportedPlaceholdersFor($template->name, $subject);
        $bodyUnsupportedPlaceholders = EmailTemplateCatalog::unsupportedPlaceholdersFor($template->name, $body);

        if ($subjectUnsupportedPlaceholders !== [] || $bodyUnsupportedPlaceholders !== []) {
            $errors = [];

            if ($subjectUnsupportedPlaceholders !== []) {
                $errors['subject'] = __(
                    'Unsupported placeholders for this template: :placeholders',
                    ['placeholders' => implode(', ', $subjectUnsupportedPlaceholders)]
                );
            }

            if ($bodyUnsupportedPlaceholders !== []) {
                $errors['body'] = __(
                    'Unsupported placeholders for this template: :placeholders',
                    ['placeholders' => implode(', ', $bodyUnsupportedPlaceholders)]
                );
            }

            throw ValidationException::withMessages($errors);
        }

        $template->update([
            'subject' => $subject,
            'body' => $body,
        ]);

        return $template;
    }
}
