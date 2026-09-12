<?php

declare(strict_types=1);

namespace App\Support;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RobThree\Auth\Providers\Qr\IQRCodeProvider;

/**
 * Local QR code generation via chillerlan/php-qrcode.
 *
 * Replaces the external Google Charts endpoint (chart.googleapis.com, long
 * shut down) so the TOTP secret never leaves the server.
 */
final class LocalQrCodeProvider implements IQRCodeProvider
{
    public function getQRCodeImage(string $qrText, int $size): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'imageBase64' => false,
            'scale' => max(1, (int) round($size / 37)),
        ]);

        return (new QRCode($options))->render($qrText);
    }

    public function getMimeType(): string
    {
        return 'image/png';
    }

    /**
     * Data-URI variant for direct <img src="..."> embedding
     * (img-src 'self' data: permits it).
     */
    public static function dataUri(string $qrText, int $size): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'imageBase64' => true,
            'scale' => max(1, (int) round($size / 37)),
        ]);

        return (new QRCode($options))->render($qrText);
    }
}
