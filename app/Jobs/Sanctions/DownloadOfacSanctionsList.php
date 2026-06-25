<?php

namespace App\Jobs\Sanctions;

class DownloadOfacSanctionsList extends BaseSanctionsDownloadJob
{
    protected function getSourceKey(): string
    {
        return 'ofac';
    }

    protected function getListType(): string
    {
        return 'OFAC';
    }

    protected function getFormat(): string
    {
        return 'XML';
    }
}
