<?php
namespace App\Enums;
enum WorkOrderStatus: string {
    case PENDING='PENDING'; case IN_PROGRESS='IN_PROGRESS'; case QC_IN_PROGRESS='QC_IN_PROGRESS';
    case REWORK='REWORK'; case COMPLETED='COMPLETED'; case CANCELLED='CANCELLED'; case DUPLICATE='DUPLICATE';
}
