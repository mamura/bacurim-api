<?php

namespace App\Modules\Financial\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class FinancialCategoryRecord extends Model
{
    protected $table = 'financial_categories';

    protected $guarded = ['*'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.uP';
    }
}
