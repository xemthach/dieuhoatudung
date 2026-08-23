<?php

namespace App\Enums;

enum EquipmentType: string
{
    case UNSURE = 'unsure';
    case WALL_MOUNTED = 'wall_mounted';
    case CASSETTE = 'cassette';
    case DUCTED = 'ducted';
    case CEILING_EXPOSED = 'ceiling_exposed';
    case FLOOR_STANDING = 'floor_standing';

    public function label(): string
    {
        return (string) config("hvac_equipment_types.types.{$this->value}.label", $this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
