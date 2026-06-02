<?php

namespace App\Enums;

enum ProductUnit: string
{
    case Piece   = 'piece';
    case Kg      = 'kg';
    case Gram    = 'g';
    case Liter   = 'liter';
    case Ml      = 'ml';
    case Box     = 'box';
    case Carton  = 'carton';
    case Dozen   = 'dozen';
    case Bag     = 'bag';
    case Bottle  = 'bottle';
    case Can     = 'can';
    case Packet  = 'packet';
    case Meter   = 'meter';

    public function label(): string
    {
        return match($this) {
            self::Piece   => 'قطعة',
            self::Kg      => 'كيلوجرام',
            self::Gram    => 'جرام',
            self::Liter   => 'لتر',
            self::Ml      => 'مليلتر',
            self::Box     => 'صندوق',
            self::Carton  => 'كرتون',
            self::Dozen   => 'دستة',
            self::Bag     => 'كيس',
            self::Bottle  => 'زجاجة',
            self::Can     => 'علبة',
            self::Packet  => 'باكيت',
            self::Meter   => 'متر',
        };
    }
}
