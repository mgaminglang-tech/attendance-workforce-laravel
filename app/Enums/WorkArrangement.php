<?php

namespace App\Enums;

enum WorkArrangement: string
{
    case WorkFromHome = 'work_from_home';
    case OfficeBased = 'office_based';
    case FieldBased = 'field_based';

    public function label(): string
    {
        return match ($this) {
            self::WorkFromHome => 'Work From Home',
            self::OfficeBased => 'Office-Based',
            self::FieldBased => 'Field-Based',
        };
    }

    public function dtrLabel(): string
    {
        return match ($this) {
            self::WorkFromHome => 'WFH',
            self::OfficeBased => 'OFFICE-BASED',
            self::FieldBased => 'FIELD-BASED',
        };
    }
}
