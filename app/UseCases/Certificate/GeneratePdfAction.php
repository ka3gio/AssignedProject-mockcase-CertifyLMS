<?php

declare(strict_types=1);

namespace App\UseCases\Certificate;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/**
 * 提供済み Blade テンプレートから修了証 PDF を生成し、private disk に保存する。
 */
final class GeneratePdfAction
{
    public function __invoke(Certificate $certificate): void
    {
        $certificate->loadMissing(['user', 'certification']);

        $pdf = new Mpdf([
            'mode' => 'ja',
            'format' => 'A4',
            'default_font' => 'sun-exta',
            'tempDir' => storage_path('app/mpdf'),
        ]);

        $pdf->WriteHTML(view('certificates.pdf', compact('certificate'))->render());

        $stored = Storage::disk('private')->put(
            $certificate->pdf_path,
            $pdf->Output('', Destination::STRING_RETURN),
        );

        if (! $stored) {
            throw new RuntimeException('修了証 PDF の保存に失敗しました。');
        }
    }
}
