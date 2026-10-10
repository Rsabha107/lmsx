<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** The roles offered for contacts and field supervisors. */
class ContactRoles
{
    public const ALL = [
        'Airport Lead',
        'VSA Lead',
        'Hotel Lead',
        'Stadium Lead',
        'Training Site Lead',
        'Field Supervisor',
        'Operations Lead',
        'Transport Manager',
        'Liaison Officer',
        'Media Manager',
        'Press Officer',
        'Protocol Officer',
        'VIP Coordinator',
        'Accreditation Manager',
        'Security Director',
        'Safety Manager',
        'Medical Coordinator',
        'Technical Director',
    ];

    /** Only listed roles, plus whatever an existing record already holds so old data can still be saved. */
    public static function rule(?string ...$keep): In
    {
        return Rule::in([...self::ALL, ...array_filter($keep)]);
    }
}
