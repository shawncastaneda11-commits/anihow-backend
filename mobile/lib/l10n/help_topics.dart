import 'app_strings.dart';

class HelpTopic {
  const HelpTopic(this.title, this.steps);

  final String title;
  final List<String> steps;
}

extension HelpTopics on AppStrings {
  List<HelpTopic> get buyerHelpTopics => [
    HelpTopic(t('Finding produce', 'Paghanap ng ani'), [
      t(
        'Open the marketplace and use Search produce.',
        'Buksan ang palengke at gamitin ang Maghanap ng ani.',
      ),
      t(
        'Tap Filter. Sort by Newest, Price low-high, Price high-low, Availability, or Nearest.',
        'Pindutin ang Salain. Ayusin ayon sa Pinakabago, Presyo: mababa hanggang mataas, Presyo: mataas hanggang mababa, May stock, o Pinakamalapit.',
      ),
      t(
        'Growing method is Any, Certified organic, or Naturally grown. Choose a Crop, then Apply. Clear resets the sheet.',
        'Ang Paraan ng pagtatanim ay Kahit ano, Sertipikadong organiko, o Likas na pinatubo. Pumili ng Ani, tapos Ilapat. I-clear ang ibinabalik sa dati.',
      ),
    ]),
    HelpTopic(t('Ordering and pick-up', 'Pag-order at pagsalo'), [
      t(
        'On a listing, tap Add to cart.',
        'Sa isang listing, pindutin ang Idagdag sa cart.',
      ),
      t(
        'Open Checkout, choose I pick up, then Place order.',
        'Buksan ang Checkout, piliin ang Ako ang susalo, tapos I-place ang order.',
      ),
      t(
        'The seller uses Confirm order, then Mark ready, when the produce can be picked up.',
        'Ginagamit ng seller ang Kumpirmahin ang order, tapos Markahang ready, kapag puwede nang saluhin ang ani.',
      ),
    ]),
    HelpTopic(t('Paying online', 'Pagbabayad online'), [
      t('Tap Pay now.', 'Pindutin ang Magbayad na.'),
      t(
        'Pay to the seller\'s QR in GCash, Maya or your bank app.',
        'Magbayad sa QR ng seller gamit ang GCash, Maya o app ng bangko mo.',
      ),
      t(
        'Enter the reference number (a screenshot is optional) and tap Submit.',
        'Ilagay ang reference number (puwedeng walang screenshot) at pindutin ang I-submit.',
      ),
      t(
        'The seller confirms the payment from their payments list.',
        'Kinukumpirma ng seller ang bayad mula sa listahan ng mga bayad.',
      ),
    ]),
    HelpTopic(t('Paying cash on handover', 'Pagbabayad ng cash sa handover'), [
      t(
        'Payment is cash on handover. It is recorded, not processed.',
        'Cash ang bayad sa handover. Naitatala lang, hindi ini-process.',
      ),
      t(
        'Meet the seller and hand over the cash. There is no Pay now on that order.',
        'Makipagkita sa seller at ibigay ang cash. Walang Magbayad na sa order na iyon.',
      ),
      t(
        'The seller records the amount when they tap Complete handover.',
        'Itinatala ng seller ang halaga kapag pinindot nila ang Tapusin ang handover.',
      ),
    ]),
    HelpTopic(t('Reservations', 'Mga reserbasyon'), [
      t(
        'On a listing, tap Reserve.',
        'Sa isang listing, pindutin ang Ireserba.',
      ),
      t(
        'The seller sees the reservation on the Reservations tab.',
        'Nakikita ng seller ang reserbasyon sa tab na Mga reserbasyon.',
      ),
      t(
        'Reservations are paid first, online only. Tap Pay now and send proof within the seller\'s Payment time limit, or the reservation is cancelled.',
        'Bayad muna ang reserbasyon, online lang. Pindutin ang Magbayad na at magpadala ng patunay sa loob ng Limit ng oras ng bayad ng seller, kung hindi ay kakanselahin ang reserbasyon.',
      ),
      t(
        'If the seller cancels or the harvest falls short after you paid, the seller sends a refund.',
        'Kung kinansela ng seller o kulang ang ani pagkatapos mong magbayad, magpapadala ang seller ng refund.',
      ),
    ]),
    HelpTopic(t('Tawad', 'Tawad'), [
      t(
        'A listing shows Tawad when the farm allows a peso discount.',
        'Nagpapakita ng Tawad ang listing kapag pinapayagan ng bukid ang bawas na piso.',
      ),
      t(
        'The amount is the rule the seller saved on that listing.',
        'Ang halaga ay ang rule na na-save ng seller sa listing na iyon.',
      ),
      t(
        'Chat is for the order, not for asking a different price.',
        'Ang chat ay para sa order, hindi para humingi ng ibang presyo.',
      ),
    ]),
    HelpTopic(t('Chat with a seller', 'Makipag-chat sa seller'), [
      t(
        'After you place an order, open it and tap Chat with stall.',
        'Pagkatapos mag-place ng order, buksan ito at pindutin ang Makipag-chat sa tindahan.',
      ),
      t(
        'From a shop, tap Chat with seller.',
        'Mula sa isang tindahan, pindutin ang Makipag-chat sa nagbebenta.',
      ),
    ]),
    HelpTopic(t('Reviews and reports', 'Mga review at ulat'), [
      t(
        'After handover, open the order and tap Submit review.',
        'Pagkatapos ng handover, buksan ang order at pindutin ang I-submit ang review.',
      ),
      t(
        'To report a problem, tap Report, then Submit.',
        'Para mag-ulat ng problema, pindutin ang I-ulat, tapos I-submit.',
      ),
    ]),
    HelpTopic(t('Notifications', 'Mga abiso'), [
      t(
        'Open Notifications to read what changed on your orders.',
        'Buksan ang Mga abiso para basahin ang nagbago sa mga order mo.',
      ),
      t(
        'In Settings, use Push notifications on this phone to choose which alerts arrive.',
        'Sa Mga setting, gamitin ang Mga push notification sa phone na ito para piliin kung aling abiso ang darating.',
      ),
    ]),
    HelpTopic(t('Your data and account', 'Ang datos at account mo'), [
      t(
        'Open Settings, then My data.',
        'Buksan ang Mga setting, tapos Aking datos.',
      ),
      t(
        'That screen shows the account details AniHow stores for you.',
        'Ipinapakita ng screen na iyon ang detalye ng account na iniimbak ng AniHow para sa iyo.',
      ),
      t(
        'From there you can download your data or ask to delete your account.',
        'Doon mo rin puwedeng i-download ang datos mo o humiling na burahin ang account mo.',
      ),
    ]),
  ];

  List<HelpTopic> get sellerHelpTopics => [
    HelpTopic(t('Listings and photos', 'Mga listing at larawan'), [
      t(
        'Open a listing and add a photo of the produce.',
        'Magbukas ng listing at magdagdag ng larawan ng ani.',
      ),
      t('Tap Save listing.', 'Pindutin ang I-save ang listing.'),
    ]),
    HelpTopic(t('Recording a harvest', 'Pagtala ng ani'), [
      t(
        'On your listings, tap Record harvest.',
        'Sa mga listing mo, pindutin ang Itala ang ani.',
      ),
      t(
        'Enter what you harvested and save it. The harvest also shows on My Sales under Harvest.',
        'Ilagay ang naani mo at i-save. Lumalabas din ang ani sa Aking Benta sa ilalim ng Ani.',
      ),
    ]),
    HelpTopic(t('Adding and removing stock', 'Pagdagdag at pagbawas ng stock'), [
      t(
        'On a listing, tap Add stock to put more up for sale.',
        'Sa isang listing, pindutin ang Dagdagan ang stock para magbenta ng mas marami.',
      ),
      t(
        'Tap Remove stock to take quantity off that listing.',
        'Pindutin ang Bawasan ang stock para alisin ang dami sa listing na iyon.',
      ),
    ]),
    HelpTopic(t('Orders', 'Mga order'), [
      t(
        'Open an order and tap Confirm order when you can fill it.',
        'Buksan ang isang order at pindutin ang Kumpirmahin ang order kapag kaya mo itong punan.',
      ),
      t(
        'Tap Mark ready when the buyer can pick it up.',
        'Pindutin ang Markahang ready kapag puwede na itong saluhin ng buyer.',
      ),
      t(
        'After you meet, tap Complete handover.',
        'Pagkatapos magkita, pindutin ang Tapusin ang handover.',
      ),
    ]),
    HelpTopic(t('Walk-in sales', 'Mga walk-in sale'), [
      t(
        'For a buyer who is not in the app, tap Record walk-in sale.',
        'Para sa buyer na wala sa app, pindutin ang Itala ang walk-in sale.',
      ),
      t(
        'A walk-in sale has no chat thread.',
        'Walang chat thread ang walk-in sale.',
      ),
    ]),
    HelpTopic(t('Online payments', 'Mga online na bayad'), [
      t(
        'On your profile, tap Add QR code and choose GCash, Maya, or Bank / QR Ph.',
        'Sa profile mo, pindutin ang Magdagdag ng QR code at piliin ang GCash, Maya, o Bank / QR Ph.',
      ),
      t(
        'Set Payment time limit. Buyers must send proof of payment within this time, or the order is cancelled.',
        'Itakda ang Limit ng oras ng bayad. Kailangang magpadala ng patunay ng bayad ang buyer sa loob ng oras na ito, kung hindi ay kakanselahin ang order.',
      ),
      t(
        'Delete QR removes a code you no longer use.',
        'Tinatanggal ng Burahin ang QR ang code na hindi mo na ginagamit.',
      ),
    ]),
    HelpTopic(t('Reservations', 'Mga reserbasyon'), [
      t(
        'Buyers tap Reserve on a listing. You see those on the Reservations tab.',
        'Pinipindot ng mga buyer ang Ireserba sa isang listing. Nakikita mo ang mga iyon sa tab na Mga reserbasyon.',
      ),
      t(
        'How long they have to pay is the Payment time limit on your profile, next to Add QR code.',
        'Ang tagal nila para magbayad ay ang Limit ng oras ng bayad sa profile mo, katabi ng Magdagdag ng QR code.',
      ),
    ]),
    HelpTopic(t('Tawad rules', 'Mga rule ng tawad'), [
      t(
        'On a listing, choose a Rule type: Flat peso off per order, or Peso off at a minimum quantity.',
        'Sa isang listing, pumili ng Uri ng rule: Flat na bawas bawat order, o Bawas kapag may minimum na dami.',
      ),
      t('Tap Save tawad.', 'Pindutin ang I-save ang tawad.'),
      t(
        'Saving replaces any current rule on this listing. Orders already confirmed keep the price they were confirmed at.',
        'Kapag nai-save, papalitan ang kasalukuyang tawad sa listing na ito. Ang na-confirm nang order ay nagtatago ng presyong nakumpirma.',
      ),
    ]),
    HelpTopic(t('My Sales', 'Aking Benta'), [
      t(
        'Open My Sales. The tabs are Sales and Harvest.',
        'Buksan ang Aking Benta. Ang mga tab ay Benta at Ani.',
      ),
      t(
        'Tap the range control. The sheet is titled Show, with This week, This month, This year, Yearly, and Custom dates (Up to 366 days).',
        'Pindutin ang kontrol ng saklaw. Ang sheet ay pinamagatang Ipakita, na may Ngayong linggo, Ngayong buwan, Ngayong taon, Taunan, at Pasadyang petsa (Hanggang 366 araw).',
      ),
      t(
        'Tap Show results. Product type (All, Fresh, Value-added) appears only when the farm sells value-added goods.',
        'Pindutin ang Ipakita ang resulta. Ang Uri ng produkto (Lahat, Sariwa, Prosesong produkto) ay lumalabas lang kapag may binebentang prosesong produkto ang bukid.',
      ),
    ]),
    HelpTopic(t('Crop care and chats', 'Pangangalaga ng pananim at mga chat'), [
      t(
        'Open Crop care for notes on a crop.',
        'Buksan ang Pangangalaga ng pananim para sa mga tala sa isang ani.',
      ),
      t(
        'Open Chats to answer a buyer.',
        'Buksan ang Mga chat para sumagot sa isang buyer.',
      ),
    ]),
  ];
}
