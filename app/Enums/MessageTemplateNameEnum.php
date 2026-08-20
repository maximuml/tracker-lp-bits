<?php

namespace App\Enums;

use App\Support\Locale;

enum MessageTemplateNameEnum: string
{
    case REGISTER_WELCOME = 'register_welcome';

    public function label(): string
    {
        return match ($this) {
            self::REGISTER_WELCOME => Locale::trans('message-template.register_welcome', [], null),
            default => '',
        };
    }
}
