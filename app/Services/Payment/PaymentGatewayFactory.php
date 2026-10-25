<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;

class PaymentGatewayFactory
{
    public function __construct(
        private readonly MidtransGateway $midtrans,
        private readonly ManualTransferGateway $manual,
    ) {
    }

    public function midtrans(): PaymentGateway
    {
        return $this->midtrans;
    }

    public function manual(): PaymentGateway
    {
        return $this->manual;
    }

    /**
     * Gateway online yang aktif, atau null bila belum dikonfigurasi.
     */
    public function online(): ?PaymentGateway
    {
        return $this->midtrans->isConfigured() ? $this->midtrans : null;
    }

    public function onlineAvailable(): bool
    {
        return $this->online() !== null;
    }

    public function available(): array
    {
        $list = ['transfer_manual' => $this->manual];

        if ($this->midtrans->isConfigured()) {
            $list['midtrans'] = $this->midtrans;
        }

        return $list;
    }
}