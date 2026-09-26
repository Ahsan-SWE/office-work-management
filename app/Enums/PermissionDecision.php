<?php
namespace App\Enums;
enum PermissionDecision: string {
    case ALLOW = 'ALLOW';
    case DENY = 'DENY';
}
