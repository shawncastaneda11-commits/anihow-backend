<?php

namespace App\Support;

/**
 * Scripted FAQ intents. Keyword matching only. No LLM, no crop-care article
 * bodies. Role gates keep buyer answers separate from farmer-seller answers.
 */
class FaqCatalog
{
    /**
     * @return list<array{id: string, roles: list<string>, label: string, keywords: list<string>, answer: string}>
     */
    public static function intents(): array
    {
        return [
            [
                'id' => 'order_status',
                'roles' => ['buyer'],
                'label' => 'Where is my order?',
                'keywords' => ['order status', 'where is my order', 'track order', 'order update', 'status'],
                'answer' => 'Open Orders to see Placed, Confirmed, Ready, Completed, or Cancelled. The seller updates status when they confirm, mark ready, and complete the handover. For a specific pickup time, open that order and use Chat with the stall.',
            ],
            [
                'id' => 'tawad_buyer',
                'roles' => ['buyer'],
                'label' => 'What is tawad?',
                'keywords' => ['tawad', 'discount', 'cheaper', 'bargain', 'negotiate'],
                'answer' => 'Tawad is a peso discount the seller publishes on a listing. It applies automatically at checkout when the rule matches. Prices are not negotiated in chat.',
            ],
            [
                'id' => 'cash_on_handover',
                'roles' => ['buyer', 'farmer_seller'],
                'label' => 'How do I pay?',
                'keywords' => ['pay', 'payment', 'cash', 'handover', 'gcash', 'money'],
                'answer' => 'AniHow uses cash on handover. Pay the seller in person when you pick up or receive the produce. The app records the sale; it does not process cards or e-wallets.',
            ],
            [
                'id' => 'otp_verify',
                'roles' => ['buyer'],
                'label' => 'How do I verify my email?',
                'keywords' => ['otp', 'verify', 'verification', 'email code', 'code'],
                'answer' => 'After you register, AniHow emails a 6-digit code that expires in 10 minutes. Enter it on the verification screen. You need a verified email before you can place an order.',
            ],
            [
                'id' => 'cancel_order',
                'roles' => ['buyer'],
                'label' => 'Can I cancel an order?',
                'keywords' => ['cancel', 'cancellation', 'refund'],
                'answer' => 'A buyer may cancel only while the order is still Placed, before the seller confirms. After that, contact the seller through Chat if you need to rearrange the handover.',
            ],
            [
                'id' => 'pickup',
                'roles' => ['buyer'],
                'label' => 'How does pickup work?',
                'keywords' => ['pickup', 'pick up', 'delivery', 'fulfillment', 'meet', 'barangay'],
                'answer' => 'At checkout you choose buyer pickup or seller delivers and leave an arrangement note. Watch the order status. When it is Ready, meet as arranged and pay cash on handover.',
            ],
            [
                'id' => 'seller_advance',
                'roles' => ['farmer_seller'],
                'label' => 'How do I confirm an order?',
                'keywords' => ['confirm', 'ready', 'complete', 'advance', 'incoming order'],
                'answer' => 'Open Incoming orders. Confirm holds the stock, Ready means the produce is prepared, and Complete records cash received at handover. Use Chat on the order if the buyer needs a time change.',
            ],
            [
                'id' => 'walk_in',
                'roles' => ['farmer_seller'],
                'label' => 'What is a walk-in sale?',
                'keywords' => ['walk-in', 'walk in', 'walkin', 'in person', 'no account'],
                'answer' => 'A walk-in sale is an in-person sale to someone without the app. Record it after handover from Incoming orders. Walk-ins land at Completed and have no buyer chat thread.',
            ],
            [
                'id' => 'floor_price',
                'roles' => ['farmer_seller'],
                'label' => 'What is the floor price?',
                'keywords' => ['floor', 'floor price', 'minimum price', 'lowest price'],
                'answer' => 'Your farm sets an effective floor for each crop. Listing prices and tawad discounts cannot go below it. The new-listing form shows that floor after you pick a crop.',
            ],
            [
                'id' => 'tawad_seller',
                'roles' => ['farmer_seller'],
                'label' => 'How do tawad rules work?',
                'keywords' => ['tawad', 'discount', 'tawad limit', 'maximum discount'],
                'answer' => 'Publish one active peso tawad rule per listing. The discount must keep the unit price at or above your farm floor and within the farm tawad limit. Checkout applies the rule automatically when its condition is met.',
            ],
            [
                'id' => 'crop_care_pointer',
                'roles' => ['farmer_seller'],
                'label' => 'Where is crop care?',
                'keywords' => ['crop care', 'crop-care', 'pest', 'article', 'guide', 'eco'],
                'answer' => 'Open the Crop care tab in the app to read published reference articles from every partner farm. This help bot does not repeat article text.',
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function chipsForRole(string $role): array
    {
        return collect(self::intents())
            ->filter(fn (array $intent): bool => in_array($role, $intent['roles'], true))
            ->map(fn (array $intent): array => [
                'id' => $intent['id'],
                'label' => $intent['label'],
            ])
            ->values()
            ->all();
    }
}
