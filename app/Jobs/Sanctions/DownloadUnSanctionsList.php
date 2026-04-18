<?php

namespace App\Jobs\Sanctions;

class DownloadUnSanctionsList extends BaseSanctionsDownloadJob
{
    protected function getSourceKey(): string
    {
        return 'un';
    }

    protected function getListType(): string
    {
        return 'UNSCR';
    }

    protected function getFormat(): string
    {
        return 'XML';
    }
}
