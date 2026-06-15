<?php

namespace App\Models\Traits;

trait HasReferenceNumber
{
    protected string $referenceNumberColumn = 'reference_number';

    protected string $referenceNumberPrefix = 'REF';

    protected int $referenceNumberLength = 8;

    public static function bootHasReferenceNumber(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->referenceNumberColumn})) {
                $model->{$model->referenceNumberColumn} = $model->generateReferenceNumber();
            }
        });
    }

    protected function generateReferenceNumber(): string
    {
        $last = static::query()
            ->where($this->referenceNumberColumn, 'like', $this->referenceNumberPrefix.'%')
            ->orderByDesc('id')
            ->value($this->referenceNumberColumn);

        $next = 1;

        if ($last) {
            $next = ((int) substr($last, strlen($this->referenceNumberPrefix))) + 1;
        }

        return $this->referenceNumberPrefix.str_pad((string) $next, $this->referenceNumberLength, '0', STR_PAD_LEFT);
    }
}
