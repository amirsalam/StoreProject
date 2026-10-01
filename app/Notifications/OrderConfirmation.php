<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Setting;
use App\Services\BrandingService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * The receipt a customer gets once their order is paid and fulfilled:
 * what they bought, what they paid, their license keys, their downloads,
 * and a link back to the order. Sent in the locale active when the
 * payment is confirmed (the buyer's, on confirm-on-return).
 */
class OrderConfirmation extends Notification
{
    public function __construct(public readonly Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing(['items.licenses', 'items.download']);
        $brand = app(BrandingService::class)->summary()['title'];
        $money = fn ($amount) => '$'.number_format((float) $amount, 2);
        $date = fn ($value) => Carbon::parse($value)->locale(app()->getLocale())->isoFormat('LL');
        $t = fn (string $key, array $replace = []) => __("messages.order_email.{$key}", $replace);

        $mail = (new MailMessage)
            // Sender name from Admin → Email if set, else the store's title.
            ->from((string) config('mail.from.address'), (string) (Setting::get('mail.from_name') ?: $brand))
            ->subject($t('subject', ['number' => $order->order_number]))
            ->greeting($t('greeting', ['name' => $order->billing_name]))
            ->line($t('intro', ['total' => $money($order->total), 'date' => $date($order->paid_at ?? now())]))
            ->line('**'.$t('summary').'** — '.$order->order_number);

        foreach ($order->items as $item) {
            $mail->line($t('item_line', [
                'title' => $item->product_title,
                'qty' => $item->quantity,
                'price' => $money($item->total_price),
            ]));
        }

        if ((float) $order->discount > 0) {
            $mail->line($t('discount', ['amount' => $money($order->discount)]));
        }
        $mail->line('**'.$t('total', ['amount' => $money($order->total)]).'**');

        $licensed = $order->items->filter(fn ($item) => $item->licenses->isNotEmpty());
        if ($licensed->isNotEmpty()) {
            $mail->line('**'.$t('licenses_heading').'**');
            foreach ($licensed as $item) {
                foreach ($item->licenses as $license) {
                    $line = $t('license_line', [
                        'title' => $item->product_title,
                        'key' => $license->license_key,
                        'limit' => $license->activation_limit,
                    ]);
                    if ($license->expires_at) {
                        $line .= ' '.$t('license_expires', ['date' => $date($license->expires_at)]);
                    }
                    $mail->line($line);
                }
            }
            $mail->line($t('licenses_help', ['url' => route('api-reference')]));
            $mail->line($t('licenses_saved', ['url' => route('purchases.index')]));
        }

        $downloads = $order->items->filter(fn ($item) => $item->download !== null);
        if ($downloads->isNotEmpty()) {
            $mail->line('**'.$t('downloads_heading').'**');
            foreach ($downloads as $item) {
                $limit = $item->download->max_downloads;
                $mail->line($t('download_line', [
                    'title' => $item->product_title,
                    'limit' => $limit ? $t('download_limit', ['count' => $limit]) : $t('download_unlimited'),
                ]));
            }
            $mail->line($t('downloads_help', ['email' => $order->billing_email, 'url' => route('purchases.index')]));
        }

        // Something to download or activate → the button leads to My
        // purchases (files + keys); otherwise to the order receipt.
        $delivers = $licensed->isNotEmpty() || $downloads->isNotEmpty();

        return $mail
            ->action(
                $delivers ? $t('action_purchases') : $t('action'),
                $delivers ? route('purchases.index') : route('checkout.confirmation', $order->order_number),
            )
            ->line($t('support', ['url' => route('contact')]))
            ->salutation($t('salutation', ['brand' => $brand]));
    }
}
