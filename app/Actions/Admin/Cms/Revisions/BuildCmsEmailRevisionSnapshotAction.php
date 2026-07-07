<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsEmail;

class BuildCmsEmailRevisionSnapshotAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(CmsEmail $email): array
    {
        return [
            'schema_version' => 1,
            'subject' => [
                'type' => 'cms_email',
                'id' => $email->id,
            ],
            'email' => [
                'cms_mail_template_id' => $email->cms_mail_template_id,
                'title' => $email->title,
                'locale' => $email->locale,
                'translation_key' => $email->translation_key,
                'email_type' => $email->email_type,
                'system_key' => $email->system_key,
                'context_key' => $email->context_key,
                'subject' => $email->subject,
                'preheader' => $email->preheader,
                'content_blocks' => $email->content_blocks ?? [],
                'plain_text' => $email->plain_text,
                'settings' => $email->settings ?? [],
                'is_active' => (bool) $email->is_active,
            ],
        ];
    }
}
