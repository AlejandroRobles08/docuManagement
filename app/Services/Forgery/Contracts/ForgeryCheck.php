<?php

namespace App\Services\Forgery\Contracts;

use App\Services\Forgery\ForgeryFinding;
use Illuminate\Http\UploadedFile;

interface ForgeryCheck
{
    /**
     * @param  array{document_type: string, document_number: string, file_hash: string}  $context
     */
    public function evaluate(UploadedFile $file, array $context): ForgeryFinding;
}
