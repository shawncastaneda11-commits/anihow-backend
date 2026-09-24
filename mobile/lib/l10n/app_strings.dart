import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/preferences_controller.dart';
import '../support/crop_language.dart';

class AppStrings {
  const AppStrings(this.filipino);

  final bool filipino;

  factory AppStrings.of(BuildContext context) {
    final language = Provider.of<PreferencesController>(context).language;
    return AppStrings(language == CropLanguage.filipino);
  }

  factory AppStrings.read(BuildContext context) {
    final language = context.read<PreferencesController>().language;
    return AppStrings(language == CropLanguage.filipino);
  }

  factory AppStrings.maybeOf(BuildContext context) {
    try {
      return AppStrings.of(context);
    } catch (_) {
      return const AppStrings(false);
    }
  }

  String t(String english, String tagalog) => filipino ? tagalog : english;

  String get settings => t('Settings', 'Mga setting');
  String get help => t('Help', 'Tulong');
  String get faq => t('FAQ', 'Mga tanong');
  String get appearance => t('Appearance', 'Itsura');
  String get light => t('Light', 'Maliwanag');
  String get dark => t('Dark', 'Madilim');
  String get system => t('System', 'Sistema');
  String get preferences => t('Preferences', 'Mga kagustuhan');
  String get notifications => t('Notifications', 'Mga abiso');
  String get language => t('Language', 'Wika');
  String get english => 'English';
  String get filipinoLabel => 'Filipino';
  String get account => t('Account', 'Account');
  String get editProfile => t('Edit profile', 'I-edit ang profile');
  String get changePassword => t('Change password', 'Palitan ang password');
  String get email => t('Email', 'Email');
  String get verified => t('Verified', 'Beripikado');
  String get unverified => t('Unverified', 'Hindi pa');
  String get about => t('About', 'Tungkol');
  String get misconfiguredBuildTitle => t(
        'This build is not connected to a server',
        'Hindi nakakonekta sa server ang build na ito',
      );
  String get misconfiguredBuildBody => t(
        'Please install the official AniHow app from your farm or the project team.',
        'I-install ang opisyal na AniHow app mula sa inyong bukid o sa project team.',
      );
  String get aboutAniHow => t('About AniHow', 'Tungkol sa AniHow');
  String get termsPrivacy => t('Terms & privacy', 'Mga tuntunin at privacy');
  String get logOut => t('Log out', 'Mag-log out');

  String get signIn => t('Sign in', 'Mag-sign in');
  String get welcomeBack => t('Welcome back', 'Maligayang pagbabalik');
  String get password => t('Password', 'Password');
  String get createBuyerAccount => t('Create a buyer account', 'Gumawa ng buyer account');

  String get verifyEmail => t('Verify Email', 'Beripikahin ang email');
  String get code => t('Code', 'Code');
  String get verify => t('Verify', 'Beripikahin');
  String get resendCode => t('Resend code', 'Ipadala ulit ang code');
  String resendCodeIn(int seconds) =>
      t('Resend code in ${seconds}s', 'Ipadala ulit sa loob ng ${seconds}s');
  String get verifyHintEmail => t(
        'Check your email inbox and Spam folder. Type the 6-digit code. It lasts 10 minutes.',
        'Tingnan ang inbox at Spam. I-type ang 6 na digit. May bisa ito ng 10 minuto.',
      );
  String get verifyHintLocal => t(
        'Gmail is not connected yet, so the code is shown here. It lasts 10 minutes.',
        'Hindi pa nakakonekta ang Gmail, kaya nandito ang code. May bisa ito ng 10 minuto.',
      );
  String get yourCode => t('Your code', 'Ang iyong code');

  String get currentPassword => t('Current password', 'Kasalukuyang password');
  String get newPassword => t('New password', 'Bagong password');
  String get confirmNewPassword => t('Confirm new password', 'Ulitin ang bagong password');
  String get savePassword => t('Save password', 'I-save ang password');
  String get changePasswordHint => t(
        'Enter your current password, then choose a new one.',
        'Ilagay ang kasalukuyang password, tapos pumili ng bago.',
      );

  String get faqTitle => t('FAQ', 'Mga tanong');
  String get faqIntro => t(
        'Tap a question, or type one in your own words. For one order, open it and tap Chat.',
        'Pumili ng tanong, o mag-type ng sarili mong salita. Para sa isang order, buksan ito at i-tap ang Chat.',
      );
  String get ask => t('Ask', 'Tanong');
  String get askHint => t('Ask a how-to question', 'Magtanong kung paano');
  String get noHelpTopics => t('No help topics yet.', 'Wala pang paksa ng tulong.');
  String get showPassword => t('Show password', 'Ipakita ang password');
  String get hidePassword => t('Hide password', 'Itago ang password');
  String get emailVerified => t('Email verified.', 'Beripikado na ang email.');
  String get passwordUpdated => t('Password updated.', 'Na-update na ang password.');
  String get verificationSent => t(
        'Verification code sent to your email.',
        'Naipadala na ang verification code sa email mo.',
      );
  String get verificationLocal => t(
        'Email is not sending yet. Use the code on this screen.',
        'Hindi pa nakakapagpadala ng email. Gamitin ang code sa screen na ito.',
      );
  String get ok => 'OK';

  String get myListings => t('My listings', 'Aking mga listing');
  String get incomingOrders => t('Incoming orders', 'Mga papasok na order');
  String get cropCare => t('Crop care', 'Pangangalaga ng pananim');
  String get listings => t('Listings', 'Mga listing');
  String get orders => t('Orders', 'Mga order');
  String get marketplace => t('Marketplace', 'Palengke');
  String get favorites => t('Favorites', 'Mga paborito');
  String get profile => t('Profile', 'Profile');
  String get market => t('Market', 'Palengke');
  String get verifyBanner => t(
        'Verify email to order.',
        'Beripikahin ang email para makapag-order.',
      );
  String get verifyNow => t('Verify now', 'Beripikahin ngayon');

  String get shopProfile => t('Shop profile', 'Profile ng tindahan');
  String get roleBuyer => t('Buyer', 'Buyer');
  String get roleFarmer => t('Farmer-seller', 'Magsasaka-tindahan');
  String farmLine(String name) => t('Farm: $name', 'Bukid: $name');
  String get farm => t('Farm', 'Bukid');
  String get farmProfile => t('Farm profile', 'Profile ng bukid');
  String get farmNotFound => t('Farm not found.', 'Hindi nahanap ang bukid.');
  String get pickupPoint => t('Pickup point', 'Pickup point');
  String get farmPhotos => t('Photos', 'Mga larawan');
  String get farmStorefronts => t('Storefronts', 'Mga tindahan');
  String get noFarmPhotos => t('No photos yet', 'Wala pang larawan');
  String get noFarmStorefronts => t('No storefronts yet', 'Wala pang tindahan');
  String get farmContact => t('Contact person', 'Contact person');
  String get farmContactBuyerHint => t(
        'Message the seller through your order chat.',
        'I-message ang tindahan sa chat ng iyong order.',
      );
  String get announcements => t('Announcements', 'Mga anunsyo');
  String get viewAnnouncements => t('View announcements', 'Tingnan ang mga anunsyo');
  String get noAnnouncements => t('No announcements right now.', 'Walang anunsyo ngayon.');
  String get dismissAnnouncement => t('Dismiss', 'Isara');
  String get announcementPinned => t('Pinned', 'Naka-pin');
  String get activeListings => t('Active listings', 'Mga active na listing');
  String get noActiveListings => t('No active listings', 'Walang active na listing');
  String get noBioYet => t('No bio yet', 'Wala pang bio');
  String get noLocationYet => t('No location yet', 'Wala pang lokasyon');
  String get noContactYet => t('No contact yet', 'Wala pang contact');
  String get pickupOnly => t('Pickup only', 'Pickup lang');
  String get callToPickup => t(
        'Call to coordinate pickup at the stall.',
        'Tumawag para mag-usap tungkol sa pickup sa stall.',
      );
  String get reviews => t('Reviews', 'Mga review');
  String get orderHistory => t('Order history', 'Kasaysayan ng order');

  String get back => t('Back', 'Bumalik');
  String get cancel => t('Cancel', 'Kansela');
  String get delete => t('Delete', 'Tanggalin');
  String get save => t('Save', 'I-save');
  String get send => t('Send', 'Ipadala');
  String get done => t('Done', 'Tapos');
  String get update => t('Update', 'I-update');
  String get remove => t('Remove', 'Alisin');
  String get cart => t('Cart', 'Cart');
  String get checkout => t('Checkout', 'Checkout');
  String get shops => t('Shops', 'Mga tindahan');
  String get shop => t('Shop', 'Tindahan');
  String get all => t('All', 'Lahat');
  String get searchProduce => t('Search produce', 'Maghanap ng ani');
  String get freshest => t('Freshest', 'Pinakabago');
  String get priceLowHigh => t('Price: low to high', 'Presyo: mababa hanggang mataas');
  String get priceHighLow => t('Price: high to low', 'Presyo: mataas hanggang mababa');
  String get inStockFirst => t('In stock first', 'May stock muna');
  String get emptyCart => t('Your cart is empty.', 'Walang laman ang cart mo.');
  String get orderSummary => t('Order Summary', 'Buod ng order');
  String get listed => t('Listed', 'Nakalista');
  String get tawad => t('Tawad', 'Tawad');
  String get total => t('Total', 'Kabuuan');
  String get noOrders => t('No orders yet.', 'Wala pang order.');
  String get noFavorites => t('No favorites yet.', 'Wala pang paborito.');
  String get noListingsFound => t('No listings found.', 'Walang nahanap na listing.');
  String get nothingHere => t('Nothing here yet.', 'Wala pa rito.');
  String get somethingWentWrong => t('Something went wrong.', 'May nangyaring mali.');
  String get retry => t('Retry', 'Subukan ulit');
  String get farmStall => t('Farm stall', 'Tindahan');
  String listingNumber(int id) => t('Listing #$id', 'Listing #$id');
  String chatTitle(String title) => t('Chat · $title', 'Chat · $title');
  String reviewsCount(int count) => count == 1
      ? t('(1 review)', '(1 review)')
      : t('($count reviews)', '($count review)');
  String get chatWithStall => t('Chat with stall', 'Makipag-chat sa tindahan');
  String get chatWithBuyer => t('Chat with buyer', 'Makipag-chat sa buyer');
  String get order => t('Order', 'Order');
  String get you => t('You', 'Ikaw');
  String get sendMessageHint => t('Message about this order', 'Mensahe tungkol sa order na ito');
  String get noMessages => t(
        'No messages yet. Say hello about the handover.',
        'Wala pang mensahe. Mag-hello tungkol sa handover.',
      );
  String get markAllRead => t('Mark all read', 'Markahan lahat na nabasa');
  String get createAccount => t('Create account', 'Gumawa ng account');
  String get fullName => t('Full name', 'Buong pangalan');
  String get phoneOptional => t('Phone (optional)', 'Telepono (opsyonal)');
  String get confirmPassword => t('Confirm password', 'Ulitin ang password');
  String get register => t('Register', 'Magpalista');
  String get addToCart => t('Add to cart', 'Idagdag sa cart');
  String get addedToCart => t('Added to cart.', 'Nadagdag sa cart.');
  String get viewCart => t('View cart', 'Tingnan ang cart');
  String get enterQuantity => t('Enter a quantity.', 'Maglagay ng dami.');
  String get quantity => t('Quantity', 'Dami');
  String get inStock => t('In stock', 'May stock');
  String get lowStock => t('Low stock', 'Kulang ang stock');
  String get outOfStock => t('Out', 'Wala');
  String get placed => t('Placed', 'Placed');
  String get confirmed => t('Confirmed', 'Confirmed');
  String get ready => t('Ready', 'Ready');
  String get completed => t('Completed', 'Completed');
  String get cancelled => t('Cancelled', 'Cancelled');
  String get fulfillment => t('Fulfillment', 'Pagsalo');
  String get buyerPicksUp => t('Buyer picks up', 'Buyer ang susalo');
  String get sellerDelivers => t('Farmer-seller delivers', 'Seller ang maghahatid');
  String get agreedTimePlace => t('Agreed time and place', 'Napagkasunduang oras at lugar');
  String get timePlaceHint => t('Saturday 7am at the barangay hall', 'Sabado 7am sa barangay hall');
  String get cashOnHandover => t(
        'Payment is cash on handover. It is recorded, not processed.',
        'Cash ang bayad sa handover. Naitatala lang, hindi ini-process.',
      );
  String get payCashTitle => t('Pay cash when you meet', 'Cash ang bayad sa pagkikita');
  String get payCashBody => t(
        'Hand the money to the seller. They type the amount after handover. No GCash or cards.',
        'Ibigay ang pera sa seller. Itatala nila ang halaga pagkatapos magkita. Walang GCash o card.',
      );
  String get chatAfterPlace => t(
        'Chat opens after you place the order. Open Orders, then Chat with stall.',
        'Pagkatapos mag-place, buksan ang Orders, tapos Chat with stall.',
      );
  String get howYouMeet => t('How you meet', 'Paano kayo magkikita');
  String get pickupShort => t('I pick up', 'Ako ang susalo');
  String get deliverShort => t('Seller brings it', 'Hatid ng seller');
  String get orderPlacedTitle => t('Order reserved', 'Na-reserve na ang order');
  String get placeOrder => t('Place order', 'I-place ang order');
  String placeOrders(int count) => t('Place $count orders', 'I-place ang $count order');
  String get ordersPlaced => t('Orders placed', 'Na-place na ang order');
  String get orderPlaced => t('Order placed.', 'Na-place na ang order.');
  String cartSplit(int count) => t(
        'Your cart was split into $count orders, one per seller.',
        'Hinati ang cart mo sa $count order, isa bawat seller.',
      );
  String checkoutSplit(int count) => t(
        'Confirming places $count orders. The split happens now, not after.',
        'Kapag kumpirmado, $count order ang malilikha ngayon, hindi mamaya.',
      );
  String get fulfillmentHint => t(
        'A text arrangement for time and place. No courier, fee, or tracking.',
        'Usapan lang sa text para sa oras at lugar. Walang courier, bayad, o tracking.',
      );
  String listedUnit(String peso) => t('Listed unit $peso', 'Presyo bawat yunit $peso');
  String get newListing => t('New listing', 'Bagong listing');
  String get editListing => t('Edit listing', 'I-edit ang listing');
  String get saveListing => t('Save listing', 'I-save ang listing');
  String get titleLabel => t('Title', 'Pamagat');
  String get cropType => t('Crop type', 'Uri ng pananim');
  String get price => t('Price', 'Presyo');
  String get description => t('Description', 'Deskripsyon');
  String get setTawad => t('Set tawad', 'Magtakda ng tawad');
  String get replaceTawad => t('Replace tawad', 'Palitan ang tawad');
  String get endTawad => t('End tawad', 'Tapusin ang tawad');
  String get noTawad => t('No tawad on this listing.', 'Walang tawad sa listing na ito.');
  String get recordWalkIn => t('Record walk-in sale', 'Itala ang walk-in sale');
  String get editShop => t('Edit shop', 'I-edit ang tindahan');
  String get editShopProfile => t('Edit shop profile', 'I-edit ang profile ng tindahan');
  String get saveShopProfile => t('Save shop profile', 'I-save ang profile ng tindahan');
  String get shopName => t('Shop name', 'Pangalan ng tindahan');
  String get bio => t('Bio', 'Bio');
  String get location => t('Location', 'Lokasyon');
  String get contact => t('Contact', 'Contact');
  String get shopNotFound => t('Shop not found.', 'Hindi nahanap ang tindahan.');
  String get couldNotOpenPhone => t('Could not open the phone app.', 'Hindi mabuksan ang phone app.');
  String get readyToReview => t('Ready to review', 'Puwede nang i-review');
  String get items => t('Items', 'Mga item');
  String get cashAtMeetup => t('Cash at meetup', 'Cash sa pagkikita');
  String tawadMinus(String peso) => t('Tawad −$peso', 'Tawad −$peso');
  String get walkIn => t('Walk-in', 'Walk-in');
  String get confirmOrder => t('Confirm order', 'Kumpirmahin ang order');
  String get markReady => t('Mark ready', 'Markahang ready');
  String get completeHandover => t('Complete handover', 'Tapusin ang handover');
  String get cancelOrder => t('Cancel order', 'Kanselahin ang order');
  String get pleaseWait => t('Please wait…', 'Sandali…');
  String get cashReceived => t('Cash received', 'Cash na natanggap');
  String cashReceivedLine(String peso) => t('Cash received $peso', 'Cash na natanggap $peso');
  String orderTotalHint(String peso) => t('Order total $peso', 'Kabuuan ng order $peso');
  String get enterCashReceived => t(
        'Enter the cash amount received.',
        'Ilagay ang cash na natanggap.',
      );
  String get record => t('Record', 'Itala');
  String get cancelReasonHint => t(
        'A no-show is a cancellation reason, not a separate status.',
        'Ang no-show ay rason ng kanselasyon, hindi hiwalay na status.',
      );
  String get chooseCancelReason => t(
        'Choose a cancellation reason.',
        'Pumili ng rason ng kanselasyon.',
      );
  String get sellerDeclined => t('Declined by farmer-seller', 'Tinanggihan ng seller');
  String get noShowHandover => t('No-show at handover', 'Hindi dumating sa handover');
  String get otherReason => t('Other', 'Iba');
  String get reviewUnlocked => t(
        'Review unlocked for the buyer',
        'Puwede nang mag-review ang buyer',
      );
  String buyerRated(Object rating) => t('Buyer rated $rating', 'Rating ng buyer: $rating');
  String get orderNotFound => t('Order not found.', 'Hindi nahanap ang order.');
  String get noPlacedOrders => t('No placed orders.', 'Walang naka-place na order.');
  String get noConfirmedOrders => t('No confirmed orders.', 'Walang kumpirmadong order.');
  String get noReadyOrders => t(
        'No orders waiting for handover.',
        'Walang order na hinihintay sa handover.',
      );
  String get noCompletedOrders => t('No completed orders.', 'Walang tapos na order.');
  String get noCancelledOrders => t('No cancelled orders.', 'Walang kinanselang order.');

  String sellerCancelReason(String value) => switch (value) {
        'seller_declined' => sellerDeclined,
        'no_show' => noShowHandover,
        'other' => otherReason,
        _ => value,
      };
  String get showMore => t('Show more', 'Magpakita pa');
  String get loading => t('Loading…', 'Naglo-load…');
  String get superAdmin => t('Super admin', 'Super admin');
  String get chooseCrop => t('Choose a crop type.', 'Pumili ng uri ng pananim.');
  String get nameProduce => t('Name this produce', 'Pangalanan ang ani');
  String get shortNote => t('Add a short note', 'Magdagay ng maikling tala');
  String get deleteListing => t('Delete listing', 'Tanggalin ang listing');
  String get deleteListingAsk => t('Delete this listing?', 'Tanggalin ang listing na ito?');
  String get endTawadAsk => t('End this tawad?', 'Tapusin ang tawad na ito?');
  String get addPhoto => t('Add photo', 'Magdagdag ng larawan');
  String get listingPhotoHint => t(
        'A clear photo helps buyers pick your produce.',
        'Mas madaling piliin ng buyer kung may malinaw na larawan.',
      );
  String get listingDetails => t('Listing details', 'Detalye ng listing');
  String unitLine(String unit) => t('Unit: $unit', 'Yunit: $unit');
  String floorPriceFor(String crop, String peso) => t(
        'Floor price for $crop: $peso (set by your farm)',
        'Floor price para sa $crop: $peso (itinakda ng farm)',
      );
  String get tawadHint => t(
        'Tawad is a peso discount on the order.',
        'Peso-diskwento ang tawad sa order.',
      );
  String get tawadKeepPrice => t(
        'Orders already confirmed keep the price they were confirmed at.',
        'Ang na-confirm nang order ay nagtatago ng presyong nakumpirma.',
      );
  String get tawadReplaceNote => t(
        'Saving replaces any current rule on this listing.',
        'Kapag nai-save, papalitan ang kasalukuyang tawad sa listing na ito.',
      );
  String get ruleType => t('Rule type', 'Uri ng rule');
  String get tawadFlat => t('Flat peso off per order', 'Flat na bawas bawat order');
  String get tawadMinQty => t('Peso off at a minimum quantity', 'Bawas kapag may minimum na dami');
  String get pesoOff => t('Peso amount off', 'Halagang ibabawas');
  String get minQuantity => t('Minimum quantity', 'Minimum na dami');
  String get saveTawad => t('Save tawad', 'I-save ang tawad');
  String get enterPesoOff => t(
        'Enter a peso amount greater than zero.',
        'Maglagay ng halagang higit sa zero.',
      );
  String get enterMinQty => t(
        'Enter the minimum quantity for this tawad.',
        'Ilagay ang minimum na dami para sa tawad na ito.',
      );
  String maxTawadFor(String crop, String peso) => t(
        'Maximum tawad for $crop is $peso.',
        'Pinakamataas na tawad para sa $crop ay $peso.',
      );
  String unitFloor(String peso) => t(
        'Unit price cannot fall below $peso.',
        'Hindi puwedeng bumaba ang presyo sa $peso.',
      );
  String get chooseListing => t('Choose a listing.', 'Pumili ng listing.');
  String get walkInRecorded => t('Walk-in sale recorded', 'Naitala na ang walk-in sale');
  String get noWalkInListings => t(
        'No listings available for a walk-in sale.',
        'Walang listing para sa walk-in sale.',
      );
  String get listingLabel => t('Listing', 'Listing');
  String get amountReceived => t('Amount received', 'Halagang natanggap');
  String amountReceivedLine(String peso) => t('Amount received $peso', 'Halagang natanggap $peso');
  String get guestName => t('Guest name (optional)', 'Pangalan ng bisita (opsyonal)');
  String get guestNameHint => t('For your reference only', 'Para sa tala mo lang');
  String get noteOptional => t('Note (optional)', 'Tala (opsyonal)');
  String get recordSale => t('Record sale', 'Itala ang benta');
  String get walkInCashHint => t(
        'Count the cash, then type the amount. AniHow only records it.',
        'Bilangin ang cash, tapos i-type ang halaga. Nagtatala lang ang AniHow.',
      );
  String pricePerUnit(String peso) => t('Price per unit $peso', 'Presyo bawat yunit $peso');
  String availableQty(String qty, String? unit) => unit == null || unit.isEmpty
      ? t('Available $qty', 'Available $qty')
      : t('Available $qty $unit', 'Available $qty $unit');
  String get sales => t('Sales', 'Benta');
  String get rating => t('Rating', 'Rating');

  String orderStatus(String status) => switch (status.toLowerCase()) {
        'placed' => placed,
        'confirmed' => confirmed,
        'ready' => ready,
        'completed' => completed,
        'cancelled' => cancelled,
        _ => status,
      };

  String stockLabel({required bool out, required bool low}) {
    if (out) {
      return outOfStock;
    }
    return low ? lowStock : inStock;
  }

  String notificationTitle(String? type, String fallback) => switch (type) {
        'order_confirmed' => t('Order confirmed', 'Kumpirmado na ang order'),
        'order_ready' => t('Order ready', 'Ready na ang order'),
        'order_completed' => t('Order completed', 'Tapos na ang order'),
        'order_cancelled' => t('Order cancelled', 'Kinansela ang order'),
        'order_message' => t('New order message', 'Bagong mensahe sa order'),
        'order_placed' => t('New order placed', 'May bagong order'),
        'listing_low_stock' => t('Listing low on stock', 'Kulang na ang stock ng listing'),
        'listing_taken_down' => t('Listing taken down', 'Tinanggal ang listing'),
        'listing_restored' => t('Listing restored', 'Naibalik ang listing'),
        'account_approved' => t('Account approved', 'Aprubado na ang account'),
        'floor_price_raised' => t(
            'Floor price raised above your listing',
            'Tumataas ang floor price kaysa sa listing mo',
          ),
        'tawad_ceiling_lowered' => t(
            'Tawad limit lowered below your discount',
            'Bumaba ang limit ng tawad kaysa sa diskwento mo',
          ),
        'farm_announcement' => t('Farm announcement', 'Anunsyo ng bukid'),
        _ => fallback,
      };

  List<({String title, String body})> get aboutSections => filipino
      ? const [
          (
            title: 'Ano ang AniHow',
            body:
                'Ang AniHow ay digital na palengke para sa mga bukid sa General Trias, Cavite. Ang buyer ay tumitingin ng listing, nagre-reserve, at sumasalo ng ani. Ang farmer-seller ay naglalagay ng stock, tumatanggap ng order, at nagtatala ng benta sa cash.',
          ),
          (
            title: 'Paano bumili',
            body:
                'Walang delivery fleet at walang bayad sa app. Magkikita kayo ng seller, magbabayad ng cash, at kukuha ng ani. Ang chat ay unahan lang ng oras at lugar ng salo, hindi para tumawad.',
          ),
          (
            title: 'Sino ang gumagamit',
            body:
                'Ang buyer ay puwedeng magpalista. Ang farmer-seller account ay ginagawa ng admin ng AniHow. Ang super admin at content editor ay nasa web panel, hindi sa app na ito.',
          ),
          (
            title: 'Bersyon',
            body: 'Ang Android app na ito ay AniHow v1.0.0.',
          ),
        ]
      : const [
          (
            title: 'What AniHow is',
            body:
                'AniHow is a digital market hub for farms in General Trias, Cavite. Buyers browse listings, reserve produce, and pick it up. Farmer-sellers post stock, accept orders, and record cash sales.',
          ),
          (
            title: 'How buying works',
            body:
                'There is no delivery fleet and no in-app payment. You meet the seller, pay cash, and take the produce. Order chat is only for pickup time and place, not for bargaining.',
          ),
          (
            title: 'Who uses the app',
            body:
                'Buyers can create their own account. Farmer-seller accounts are created by the AniHow admin. Super admins and content editors use the web panel, not this app.',
          ),
          (
            title: 'Version',
            body: 'This Android app is AniHow v1.0.0.',
          ),
        ];

  List<({String title, String body})> get termsSections => filipino
      ? const [
          (
            title: 'Paggamit ng AniHow',
            body:
                'Sa paggamit ng app, sumasang-ayon ka sa mga tuntuning ito. Ang AniHow ay pantala ng reserbasyon at benta sa pickup market. Hindi ito mismo ang nagbebenta, at hindi ito tumatanggap ng card, GCash, o bangko.',
          ),
          (
            title: 'Mga account',
            body:
                'Itago ang email at password. Kailangang beripikahin ng buyer ang email bago mag-order o mag-save ng paborito. Ang farmer-seller account ay nakatali sa farm na ibinigay ng admin. Huwag ipamahagi ang login.',
          ),
          (
            title: 'Order at pera',
            body:
                'Ang Placed order ay reserbasyon, hindi pa bayad. Kinukumpirma ng seller ang stock, minamarkahan itong ready, at nagtatala ng cash sa handover. Puwedeng i-cancel ng buyer habang Placed pa. Ang walk-in ay para sa buyer na wala sa app at walang chat.',
          ),
          (
            title: 'Presyo at tawad',
            body:
                'Ang nakalistang presyo at tawad ay itinakda ng seller. Hindi puwedeng bumaba ang tawad sa floor price ng farm. Hindi pantawad ang chat.',
          ),
          (
            title: 'Listing at review',
            body:
                'Dapat totoo ang deskripsyon ng stock. Puwedeng tanggalin ng admin ang listing na lumalabag sa rule ng farm. Puwedeng mag-review ang buyer pagkatapos ng completed handover.',
          ),
          (
            title: 'Ano ang iniimbak namin',
            body:
                'Iniimbak ng AniHow ang pangalan, email, at optional na phone; mga order, chat tungkol sa order, paborito, at teksto ng shop profile. Iniimbak din ang larawan ng listing. Ginagamit ito para patakbuhin ang palengke, magpadala ng verification code, at magpakita ng abiso.',
          ),
          (
            title: 'Ano ang hindi namin ginagawa',
            body:
                'Hindi namin ibinebenta ang data mo. Hindi kami tumatanggap ng card o e-wallet. Hindi namin sinusubaybayan ang live location. Wala pang push notification sa bersyong ito.',
          ),
          (
            title: 'Mga tanong',
            body:
                'Para sa tulong, buksan ang FAQ sa Settings. Para sa isang reserbasyon, buksan ang order at gamitin ang Chat.',
          ),
        ]
      : const [
          (
            title: 'Using AniHow',
            body:
                'By using this app you agree to these rules. AniHow is a reservation and record tool for a pickup market. It does not sell produce itself and it does not take card, GCash, or bank payments.',
          ),
          (
            title: 'Accounts',
            body:
                'Keep your email and password private. Buyers must verify email before they can order or save favorites. Farmer-seller accounts stay under the farm that the admin assigned. Do not share a login.',
          ),
          (
            title: 'Orders and cash',
            body:
                'A Placed order is a reservation, not a paid sale. The seller confirms stock, marks it ready, and records cash at handover. A buyer may cancel only while the order is still Placed. Walk-in sales are for buyers who are not in the app and have no chat thread.',
          ),
          (
            title: 'Prices and tawad',
            body:
                'The listed price and any tawad (peso discount) are set by the seller. Tawad cannot go below the farm floor price. Chat is not for negotiating a new price.',
          ),
          (
            title: 'Listings and reviews',
            body:
                'Sellers must describe stock honestly. The admin may take down a listing that breaks farm rules. Buyers may review a seller only after a completed handover.',
          ),
          (
            title: 'What we store',
            body:
                'AniHow stores your name, email, and optional phone; orders, chat about those orders, favorites, and shop profile text. Listing photos you upload are stored so buyers can see the produce. We use this data to run the market hub, send a verification code, and show in-app notices.',
          ),
          (
            title: 'What we do not do',
            body:
                'We do not sell your data. We do not process cards or e-wallets. We do not track your live location. Push notifications are not in this version.',
          ),
          (
            title: 'Questions',
            body:
                'For how-to help, open FAQ in Settings. For one reservation, open that order and use Chat.',
          ),
        ];
}
