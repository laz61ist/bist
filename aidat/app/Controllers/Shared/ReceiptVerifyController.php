<?php

declare(strict_types=1);

namespace Aidat\Controllers\Shared;

use Aidat\Core\Controller;
use Aidat\Core\Response;
use Aidat\Services\PaymentService;
use Aidat\Services\ReceiptService;

/** Makbuz üzerindeki QR ile doğrulama: kişisel veri göstermez; makbuz no, tarih, tutar ve durum. */
final class ReceiptVerifyController extends Controller
{
    public function show(string $code): Response
    {
        $decoded = (new ReceiptService($this->app))->decodeVerifyCode($code);
        $data = null;
        if ($decoded !== null) {
            $data = (new PaymentService($this->app))->receiptData($decoded['payment_id'], $decoded['building_id']);
        }
        return Response::html($this->app->view()->render('shared.receipt-verify', ['title' => 'Makbuz doğrulama', 'data' => $data, 'valid' => $data !== null], 'layouts.print'));
    }
}
