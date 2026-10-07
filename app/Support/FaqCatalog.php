<?php

namespace App\Support;

/**
 * Seed source for system-wide FAQ rows. Runtime matching reads FaqEntry
 * through FaqResponder — do not call this class from the app path.
 */
class FaqCatalog
{
    /**
     * @return list<array{id: string, roles: list<string>, label: string, label_fil: string, keywords: list<string>, answer: string, answer_fil: string}>
     */
    public static function intents(): array
    {
        return [
            [
                'id' => 'order_status',
                'roles' => ['buyer'],
                'label' => 'Where is my order?',
                'label_fil' => 'Saan ang order ko?',
                'keywords' => ['order status', 'where is my order', 'track order', 'order update', 'status', 'asaan', 'saan ang order', 'order ko'],
                'answer' => "Open Orders. You will see one of these:\n\n• Placed — the seller has not accepted yet\n• Confirmed — the seller is holding your items\n• Ready — you can pick up now\n• Completed — you already paid and received the food\n• Cancelled — the order stopped\n\nThe seller changes this status. Need a new time? Open that order and tap Chat with stall.",
                'answer_fil' => "Buksan ang Orders. Makikita mo ang isa dito:\n\n• Placed — hindi pa tinanggap ng seller\n• Confirmed — hawak na ng seller ang items mo\n• Ready — puwede mo nang saluhin\n• Completed — nabayaran at nakuha mo na\n• Cancelled — tumigil ang order\n\nAng seller ang nagpapalit ng status. Kailangan ng bagong oras? Buksan ang order at i-tap ang Chat with stall.",
            ],
            [
                'id' => 'tawad_buyer',
                'roles' => ['buyer'],
                'label' => 'What is tawad?',
                'label_fil' => 'Ano ang tawad?',
                'keywords' => ['tawad', 'discount', 'cheaper', 'bargain', 'negotiate', 'diskwento', 'mura', 'tawaran'],
                'answer' => 'Tawad is a peso discount the seller already set on a listing. The app applies it at checkout. You cannot bargain or change the price in chat.',
                'answer_fil' => 'Ang tawad ay diskwentong piso na itinakda na ng seller sa listing. Inilalapat ito ng app sa checkout. Hindi ka puwedeng tumawad o magpalit ng presyo sa chat.',
            ],
            [
                'id' => 'cash_on_handover',
                'roles' => ['buyer', 'farmer_seller'],
                'label' => 'How do I pay?',
                'label_fil' => 'Paano ako magbabayad?',
                'keywords' => ['pay', 'payment', 'cash', 'handover', 'gcash', 'money', 'bayad', 'magbayad', 'pera'],
                'answer' => 'Pay cash when you meet for pickup. Give the money to the seller in person. AniHow does not take cards, GCash, or any online payment.',
                'answer_fil' => 'Magbayad ng cash kapag nagkita kayo sa pickup. Ibigay ang pera sa seller nang personal. Hindi tumatanggap ang AniHow ng card, GCash, o anumang online na bayad.',
            ],
            [
                'id' => 'otp_verify',
                'roles' => ['buyer'],
                'label' => 'How do I verify my email?',
                'label_fil' => 'Paano beripikahin ang email?',
                'keywords' => ['otp', 'verify', 'verification', 'email code', 'code', 'beripika', 'beripikahin', 'code sa email'],
                'answer' => 'After you register, AniHow emails a 6-digit code to your Gmail. Open the app, type that code, then tap Verify. The code lasts 10 minutes. You must do this before you can order.',
                'answer_fil' => 'Pagkatapos magpalista, mag-eemail ang AniHow ng 6 na digit sa Gmail mo. Buksan ang app, i-type ang code, tapos i-tap ang Beripikahin. May bisa ang code ng 10 minuto. Kailangan ito bago ka makapag-order.',
            ],
            [
                'id' => 'cancel_order',
                'roles' => ['buyer'],
                'label' => 'Can I cancel an order?',
                'label_fil' => 'Puwede ba akong mag-cancel?',
                'keywords' => ['cancel', 'cancellation', 'refund', 'kansela', 'i-cancel'],
                'answer' => 'You can cancel only while the order still says Placed. After the seller taps Confirm, you cannot cancel in the app. Open the order and tap Chat with stall to talk about pickup.',
                'answer_fil' => 'Puwede kang mag-cancel habang Placed pa ang order. Kapag ni-tap na ng seller ang Confirm, hindi mo na ito makakansela sa app. Buksan ang order at i-tap ang Chat with stall para pag-usapan ang pickup.',
            ],
            [
                'id' => 'pickup',
                'roles' => ['buyer'],
                'label' => 'How does pickup work?',
                'label_fil' => 'Paano ang pickup?',
                'keywords' => ['pickup', 'pick up', 'delivery', 'fulfillment', 'meet', 'barangay', 'salo', 'hatid'],
                'answer' => 'At checkout, choose who will travel: you pick up, or the seller brings it. Write a short note (place and time). When the order says Ready, meet as you agreed and pay cash.',
                'answer_fil' => 'Sa checkout, piliin kung sino ang maglalakbay: ikaw ang susalo, o hatid ng seller. Maglagay ng maikling tala (lugar at oras). Kapag Ready na, magkita kayo ayon sa usapan at magbayad ng cash.',
            ],
            [
                'id' => 'seller_advance',
                'roles' => ['farmer_seller'],
                'label' => 'How do I confirm an order?',
                'label_fil' => 'Paano ko kumpirmahin ang order?',
                'keywords' => ['confirm', 'ready', 'complete', 'advance', 'incoming order', 'kumpirma', 'kumpirmahin'],
                'answer' => "Open Orders, then open the order.\n\n1. Tap Confirm when you can fill it. This holds the stock.\n2. Tap Mark ready when the produce is packed.\n3. Tap Complete after the buyer paid cash.\n\nIf the buyer needs a new time, tap Chat with buyer.",
                'answer_fil' => "Buksan ang Orders, tapos buksan ang order.\n\n1. I-tap ang Confirm kapag kaya mong ihanda. Nahahold ang stock.\n2. I-tap ang Mark ready kapag nakabalot na ang ani.\n3. I-tap ang Complete pagkatapos magbayad ng cash ang buyer.\n\nKung kailangan ng buyer ng bagong oras, i-tap ang Chat with buyer.",
            ],
            [
                'id' => 'walk_in',
                'roles' => ['farmer_seller'],
                'label' => 'What is a walk-in sale?',
                'label_fil' => 'Ano ang walk-in sale?',
                'keywords' => ['walk-in', 'walk in', 'walkin', 'in person', 'no account', 'walk in sale', 'sukis'],
                'answer' => 'A walk-in is a buyer who is not using the app. After you sell to them in person, tap Record walk-in sale on Orders. Walk-ins are saved as Completed. There is no chat, because that buyer has no account.',
                'answer_fil' => 'Ang walk-in ay buyer na hindi gumagamit ng app. Pagkatapos mong magbenta nang personal, i-tap ang Record walk-in sale sa Orders. Naka-save ito bilang Completed. Walang chat, dahil walang account ang buyer na iyon.',
            ],
            [
                'id' => 'floor_price',
                'roles' => ['farmer_seller'],
                'label' => 'What is the floor price?',
                'label_fil' => 'Ano ang floor price?',
                'keywords' => ['floor', 'floor price', 'minimum price', 'lowest price', 'pinakamababang presyo'],
                'answer' => 'The floor price is the lowest peso amount your farm allows for that crop. You cannot list or discount below it. After you pick a crop on a new listing, the form shows that number.',
                'answer_fil' => 'Ang floor price ay ang pinakamababang piso na pinapayagan ng farm mo para sa pananim na iyon. Hindi ka puwedeng maglista o mag-tawad pababa rito. Pagkatapos mong pumili ng pananim sa bagong listing, lalabas ang numero sa form.',
            ],
            [
                'id' => 'tawad_seller',
                'roles' => ['farmer_seller'],
                'label' => 'How do tawad rules work?',
                'label_fil' => 'Paano gumagana ang tawad?',
                'keywords' => ['tawad', 'discount', 'tawad limit', 'maximum discount', 'diskwento'],
                'answer' => 'Tawad is a peso discount you set on one listing. You can have only one active tawad per listing. The price after tawad must stay at or above your farm floor. Checkout applies it by itself when the rule matches.',
                'answer_fil' => 'Ang tawad ay diskwentong piso na itinakda mo sa isang listing. Isa lang ang puwedeng active na tawad bawat listing. Ang presyo pagkatapos ng tawad ay hindi puwedeng bumaba sa floor ng farm. Inilalapat ito ng checkout kapag tumugma ang rule.',
            ],
            [
                'id' => 'crop_care_pointer',
                'roles' => ['farmer_seller'],
                'label' => 'Where is crop care?',
                'label_fil' => 'Saan ang pangangalaga ng pananim?',
                'keywords' => ['crop care', 'crop-care', 'pest', 'article', 'guide', 'eco', 'pangangalaga', 'pananim'],
                'answer' => 'Tap the Crop care tab at the bottom of the app. Read the guides there. This FAQ does not copy those articles.',
                'answer_fil' => 'I-tap ang tab na Pangangalaga ng pananim sa ilalim ng app. Basahin doon ang mga gabay. Hindi kinokopya ng FAQ ang mga artikulong iyon.',
            ],
        ];
    }
}
