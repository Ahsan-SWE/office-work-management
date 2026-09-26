<?php
namespace App\Enums;
enum UserStatus: string {
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case LEFT_COMPANY = 'LEFT_COMPANY';
}
