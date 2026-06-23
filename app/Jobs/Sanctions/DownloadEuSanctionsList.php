<?php

namespace App\Jobs\Sanctions;

class DownloadEuSanctionsList extends BaseSanctionsDownloadJob
{
    protected function getSourceKey(): string
    {
        return 'eu';
    }

    protected function getListType(): string
    {
        return 'EU';
    }

    protected function getFormat(): string
    {
        return 'CSV';
    }
}
