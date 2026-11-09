<?php

namespace App\Enums;

enum ShareChannel: string
{
    case ShareSheet = 'share_sheet';
    case WhatsappLink = 'whatsapp_link';
    case Email = 'email';
    case CopyLink = 'copy_link';

    public function label(): string
    {
        return match ($this) {
            self::ShareSheet => 'Shared from phone',
            self::WhatsappLink => 'WhatsApp link',
            self::Email => 'Email',
            self::CopyLink => 'Link copied',
        };
    }
}
