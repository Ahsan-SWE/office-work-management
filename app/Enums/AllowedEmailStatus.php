<?php
namespace App\Enums;
enum AllowedEmailStatus: string {
    case PENDING = 'PENDING';
    case REGISTERED = 'REGISTERED';
    case DISABLED = 'DISABLED';
}
