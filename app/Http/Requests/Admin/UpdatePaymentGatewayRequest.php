<?php

namespace App\Http\Requests\Admin;

/**
 * Same shape + rules as create. On update, blank credential / webhook
 * fields mean "leave the stored secret unchanged" — handled in
 * PaymentGatewayService::update(), so the validation rules (all
 * credentials nullable) are identical and inherited here.
 */
class UpdatePaymentGatewayRequest extends StorePaymentGatewayRequest {}
