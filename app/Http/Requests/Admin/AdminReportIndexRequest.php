<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;

class AdminReportIndexRequest extends AdminListRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(ReportStatus::cases(), 'value'))],
            'reason' => ['nullable', 'string', 'in:'.implode(',', array_column(ReportReason::cases(), 'value'))],
            'target_type' => ['nullable', 'string', 'in:user,business,post,comment,reel'],
        ];
    }
}
