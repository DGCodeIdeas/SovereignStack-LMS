<?php

declare(strict_types=1);

namespace SovereignStack\Lms\Identity\Domain\Enum;

enum TokenPurpose: string
{
    case EMAIL_VERIFICATION = 'email_verification';
    case PASSWORD_RESET = 'password_reset';
}
