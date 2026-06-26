<?php

namespace App\Models;

use App\Enums\ProspectFeedbackAction;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectFeedback extends Model
{
    use BelongsToCompany;

    protected $table = 'prospect_feedback';

    protected $fillable = [
        'company_id',
        'customer_prospect_id',
        'prospect_search_run_id',
        'action',
        'reason',
        'metadata',
        'grok_document_id',
    ];

    protected function casts(): array
    {
        return [
            'action' => ProspectFeedbackAction::class,
            'metadata' => 'array',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(CustomerProspect::class, 'customer_prospect_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ProspectSearchRun::class, 'prospect_search_run_id');
    }
}
