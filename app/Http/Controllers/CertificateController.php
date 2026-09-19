<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 発行済み修了証 PDF のダウンロード Controller。
 */
class CertificateController extends Controller
{
    public function download(Certificate $certificate): StreamedResponse
    {
        $this->authorize('download', $certificate);

        abort_unless(Storage::disk('private')->exists($certificate->pdf_path), 404);

        return Storage::disk('private')->download($certificate->pdf_path, 'certificate.pdf');
    }
}
