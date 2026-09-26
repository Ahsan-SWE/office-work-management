<?php
namespace App\Enums;
enum RoleName: string {
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case TEAM_LEADER = 'TEAM_LEADER';
    case EMPLOYEE = 'EMPLOYEE';
    case QC = 'QC';
}
