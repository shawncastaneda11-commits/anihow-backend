import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/preferences_controller.dart';
import '../support/crop_language.dart';

class AppStrings {
  const AppStrings(this.filipino);

  final bool filipino;

  factory AppStrings.of(BuildContext context) {
    // Listen only while building. Callbacks/async handlers must not register
    // a provider dependency or Provider throws and the tap looks like a no-op.
    final listen = context is Element && context.debugDoingBuild;
    try {
      final language = Provider.of<PreferencesController>(
        context,
        listen: listen,
      ).language;
      return AppStrings(language == CropLanguage.filipino);
    } catch (_) {
      try {
        return AppStrings.read(context);
      } catch (_) {
        return const AppStrings(false);
      }
    }
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
  String get rememberMe => t('Remember me', 'Tandaan ako');
  String get forgotPassword =>
      t('Forgot password?', 'Nakalimutan ang password?');
  String get sendCode => t('Send code', 'Ipadala ang code');
  String get resetPassword => t('Reset password', 'I-reset ang password');
  String get passwordResetDone => t(
    'Password reset. Log in with your new password.',
    'Na-reset na ang password. Mag-log in gamit ang bagong password.',
  );
  String get forgotPasswordHint => t(
    'Enter the email on your account. We will send a 6-digit code. It lasts 10 minutes.',
    'Ilagay ang email ng account mo. Magpapadala kami ng 6 na digit. May bisa ito ng 10 minuto.',
  );
  String get resetPasswordHint => t(
    'Enter the 6-digit code and choose a new password.',
    'Ilagay ang 6 na digit na code at pumili ng bagong password.',
  );
  String get passwordsDoNotMatch =>
      t('Passwords do not match.', 'Hindi magkatugma ang mga password.');
  String get sessionEnded => t(
    'Your session has ended. Please log in again.',
    'Natapos na ang iyong session. Mag-log in muli.',
  );
  String get welcomeBack => t('Welcome back', 'Maligayang pagbabalik');
  String get password => t('Password', 'Password');
  String get createBuyerAccount =>
      t('Create a buyer account', 'Gumawa ng buyer account');

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
  String get confirmNewPassword =>
      t('Confirm new password', 'Ulitin ang bagong password');
  String get savePassword => t('Save password', 'I-save ang password');
  String get changePasswordHint => t(
    'Enter your current password, then choose a new one.',
    'Ilagay ang kasalukuyang password, tapos pumili ng bago.',
  );
  String get setYourOwnPassword =>
      t('Set your own password', 'Itakda ang sarili mong password');
  String get temporaryPasswordExplanation => t(
    'You signed in with a temporary password from the administrator. Choose a new one to continue.',
    'Nag-sign in ka gamit ang pansamantalang password mula sa administrator. Pumili ng bago para magpatuloy.',
  );
  String get signOut => t('Sign out', 'Mag-sign out');
  String get myData => t('My data', 'Aking datos');
  String get saveProfile => t('Save profile', 'I-save ang profile');
  String get profileUpdated =>
      t('Profile updated.', 'Na-update na ang profile.');
  String get emailNotEditable => t(
    'Email cannot be changed here. It is your verified login.',
    'Hindi mababago ang email dito. Ito ang beripikadong login mo.',
  );
  String get shopEditedSeparately => t(
    'Shop name and storefront are edited from Edit profile.',
    'Ang pangalan ng tindahan ay dine-edit sa I-edit ang profile.',
  );
  String get downloadMyData =>
      t('Download my data', 'I-download ang aking datos');
  String get dataSavedTo => t('Saved to', 'Nai-save sa');
  String get requestAccountDeletion =>
      t('Request account deletion', 'Humiling ng pagbura ng account');
  String get deletionKeptHint => t(
    'Order records stay, shown as “Deleted user”, so the farm ledger stays accurate. Your profile, cart, favorites and notifications are removed.',
    'Nanatili ang mga order, ipapakita bilang “Deleted user”, para manatiling tama ang ledger ng bukid. Mabubura ang profile, cart, paborito at mga abiso.',
  );
  String get confirmDeletionTitle =>
      t('Delete your account?', 'Burahin ang account mo?');
  String get confirmDeletionBody => t(
    "You can't request deletion while you have orders in progress (placed, confirmed, or ready). Finished or cancelled orders don't block it.",
    'Hindi ka maaaring humiling ng pagbura habang may order na isinasagawa (placed, confirmed, o ready). Hindi ito hinaharangan ng mga tapos o kinanselang order.',
  );
  String get confirmDeletion => t('Send request', 'Ipadala ang kahilingan');
  String get deletionRequested => t(
    'Deletion requested. Waiting for a Super Admin.',
    'Nahiling na ang pagbura. Hinihintay ang Super Admin.',
  );
  String get cancelDeletionRequest =>
      t('Cancel request', 'Kanselahin ang kahilingan');
  String get deletionCancelled =>
      t('Request cancelled.', 'Kinansela ang kahilingan.');
  String get deletionRejected =>
      t('Request rejected', 'Tinanggihan ang kahilingan');
  String get deletionBlockedTitle =>
      t('Cannot request deletion', 'Hindi maaaring humiling ng pagbura');

  String get faqTitle => t('FAQ', 'Mga tanong');
  String get faqIntro => t(
    'Tap a question, or type one in your own words. For one order, open it and tap Chat.',
    'Pumili ng tanong, o mag-type ng sarili mong salita. Para sa isang order, buksan ito at i-tap ang Chat.',
  );
  String get ask => t('Ask', 'Tanong');
  String get askHint => t('Ask a how-to question', 'Magtanong kung paano');
  String get noHelpTopics =>
      t('No help topics yet.', 'Wala pang paksa ng tulong.');
  String get showPassword => t('Show password', 'Ipakita ang password');
  String get hidePassword => t('Hide password', 'Itago ang password');
  String get emailVerified => t('Email verified.', 'Beripikado na ang email.');
  String get passwordUpdated =>
      t('Password updated.', 'Na-update na ang password.');
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
  String get favoriteCrops => t('Crops', 'Mga ani');
  String get favoriteStores => t('Stores', 'Mga tindahan');
  String get favoriteFarms => t('Farms', 'Mga bukid');
  String get noFavoriteFarms =>
      t('No farms yet.', 'Wala pang sinusundang bukid.');
  String get farmProductsTab => t('Products', 'Mga produkto');
  String get farmShopsTab => t('Shops', 'Mga tindahan');
  String get followFarm => t('Follow', 'Sundan');
  String get followingFarm => t('Following', 'Sinusundan');
  String get directions => t('Directions', 'Direksyon');
  String get farmUpdates => t('Updates', 'Mga update');
  String get noUpdatesYet => t('No updates yet', 'Wala pang update');
  String shopsCount(int count) => t('$count shops', '$count tindahan');
  String get followers => t('Followers', 'Mga tagasunod');
  String get farmNoReviews => t('No reviews yet', 'Wala pang review');
  String farmReviewCount(int count) => count == 1
      ? t('1 review', '1 review')
      : t('$count reviews', '$count review');
  String productsFromFarm(int count) => count == 1
      ? t('1 product from this farm', '1 produkto mula sa bukid na ito')
      : t(
          '$count products from this farm',
          '$count produkto mula sa bukid na ito',
        );
  String get noProduceListed =>
      t('No produce listed right now', 'Walang produktong nakalista ngayon');
  String get noDescriptionYet =>
      t('No description yet', 'Wala pang paglalarawan');
  String get organicCertified =>
      t('Organic certified', 'Sertipikadong organiko');
  String validUntil(String date) =>
      t('Valid until $date', 'Balido hanggang $date');
  String timeAgo(DateTime when, {DateTime? now}) {
    final clock = now ?? DateTime.now();
    final delta = clock.difference(when);
    if (delta.inMinutes < 1) {
      return t('Just now', 'Ngayon lang');
    }
    if (delta.inMinutes < 60) {
      final minutes = delta.inMinutes;
      return minutes == 1
          ? t('1 minute ago', '1 minutong nakalipas')
          : t('$minutes minutes ago', '$minutes minutong nakalipas');
    }
    if (delta.inHours < 24) {
      final hours = delta.inHours;
      return hours == 1
          ? t('1 hour ago', '1 oras na ang nakalipas')
          : t('$hours hours ago', '$hours oras na ang nakalipas');
    }
    if (delta.inDays < 7) {
      final days = delta.inDays;
      return days == 1
          ? t('1 day ago', '1 araw na ang nakalipas')
          : t('$days days ago', '$days araw na ang nakalipas');
    }
    final weeks = delta.inDays ~/ 7;
    return weeks == 1
        ? t('1 week ago', '1 linggo na ang nakalipas')
        : t('$weeks weeks ago', '$weeks linggo na ang nakalipas');
  }

  String get farmRating => t('Farm rating', 'Rating ng bukid');
  String get noFavoriteStores =>
      t('No favorite stores yet.', 'Wala pang paboritong tindahan.');
  String get addStoreToFavorites =>
      t('Favorite store', 'I-favorite ang tindahan');
  String get removeStoreFromFavorites =>
      t('Unfavorite store', 'Alisin sa paborito');
  String get storeSavedToFavorites =>
      t('Store added to favorites.', 'Nadagdag ang tindahan sa mga paborito.');
  String get profile => t('Profile', 'Profile');
  String get market => t('Market', 'Palengke');
  String get verifyBanner =>
      t('Verify email to order.', 'Beripikahin ang email para makapag-order.');
  String get verifyNow => t('Verify now', 'Beripikahin ngayon');
  String emailVerifiedNotice(String email) => t(
    'Your email $email is verified.',
    'Beripikado na ang email mong $email.',
  );
  String get chatOpenFailed => t(
    "Couldn't open the chat. Check your connection and try again.",
    'Hindi mabuksan ang chat. Tingnan ang koneksyon at subukan ulit.',
  );

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
  String get viewAnnouncements =>
      t('View announcements', 'Tingnan ang mga anunsyo');
  String get noAnnouncements =>
      t('No announcements right now.', 'Walang anunsyo ngayon.');
  String get dismissAnnouncement => t('Dismiss', 'Isara');
  String get announcementPinned => t('Pinned', 'Naka-pin');
  String get updatesFromFarms =>
      t('Updates from farms', 'Mga update mula sa mga bukid');
  String updatesFromFarmsCount(int count) =>
      t('$count updates from farms', '$count update mula sa mga bukid');
  String listingsCount(int count) => t('$count listings', '$count na produkto');
  String get filter => t('Filter', 'Salain');
  String get apply => t('Apply', 'Ilapat');
  String get clear => t('Clear', 'I-clear');
  String get sort => t('Sort', 'Ayos');
  String get growingMethod => t('Growing method', 'Paraan ng pagtatanim');
  String get any => t('Any', 'Kahit ano');
  String get certifiedOrganicFilter =>
      t('Certified organic', 'Sertipikadong organiko');
  String get hideUpdates => t('Hide updates', 'Itago ang mga update');
  String get seeAll => t('See all', 'Tingnan lahat');
  String get allFarms => t('All farms', 'Lahat ng bukid');
  String get farmsIFollow => t('Farms I follow', 'Mga bukid na sinusundan ko');
  String get readMore => t('Read more', 'Basahin pa');
  String get noFarmUpdates =>
      t('No updates from farms yet.', 'Wala pang update mula sa mga bukid.');
  String get activeListings => t('Active listings', 'Mga active na listing');
  String get noActiveListings =>
      t('No active listings', 'Walang active na listing');
  String get noBioYet => t('No bio yet', 'Wala pang bio');
  String get noLocationYet => t('No location yet', 'Wala pang lokasyon');
  String get noContactYet => t('No contact yet', 'Wala pang contact');
  String get pickupOnly => t('Pickup only', 'Pickup lang');
  String get callToPickup => t(
    'Message the stall in the app to coordinate pickup.',
    'Mag-message sa stall sa app para ayusin ang pickup.',
  );
  String get changePhoto => t('Change photo', 'Palitan ang larawan');
  String get removePhoto => t('Remove photo', 'Alisin ang larawan');
  String get changeCover => t('Change cover', 'Palitan ang cover');
  String get removeCover => t('Remove cover', 'Alisin ang cover');
  String get reviews => t('Reviews', 'Mga review');
  String get noReviewsYet => t('No reviews yet.', 'Wala pang review.');
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
  String get shops => t('Farms', 'Mga bukid');
  String get saveFarm => t('Save farm', 'I-save ang bukid');
  String get removeFarm => t('Remove farm', 'Alisin ang bukid');
  String get farmSavedToFavorites =>
      t('Farm saved to favorites.', 'Nasave ang bukid sa mga paborito.');
  String get searchShops => t('Search shops', 'Maghanap ng tindahan');
  String shopsAtThisFarm(int count) => count == 1
      ? t('1 shop at this farm', '1 tindahan sa bukid na ito')
      : t('$count shops at this farm', '$count tindahan sa bukid na ito');
  String get noShopsAtFarm =>
      t('No shops at this farm yet.', 'Wala pang tindahan sa bukid na ito.');
  String get searchFarms => t('Search farms', 'Maghanap ng bukid');
  String get noFarmsYet => t('No farms yet.', 'Wala pang bukid.');
  String get noFarmsMatch =>
      t('No farms match that search.', 'Walang bukid na tumugma sa hinanap.');
  String sellersCount(int count) => count == 1
      ? t('1 seller', '1 tindahan')
      : t('$count sellers', '$count na tindahan');
  String get noShopsYet => t('No shops yet.', 'Wala pang tindahan.');
  String get noShopsMatch =>
      t('No shops match that search.', 'Walang tindahang tumugma sa hinanap.');
  String get shop => t('Shop', 'Tindahan');
  String get all => t('All', 'Lahat');
  String get freshProduce => t('Fresh Produce', 'Sariwang Ani');
  String get valueAdded => t('Value-Added', 'Prosesong Produkto');
  String get howWasItGrown => t('How was it grown?', 'Paano ito pinalaki?');
  String get notStated => t('Not stated', 'Hindi sinabi');
  String get naturallyGrown => t('Naturally grown', 'Likas na pinatubo');
  String get certifiedOrganic =>
      t('Certified Organic', 'Sertipikadong Organiko');
  String get naturallyGrownDeclared => t(
    'Naturally grown (self-declared)',
    'Likas na pinatubo (sariling pahayag)',
  );
  String certifiedBy(String name) =>
      t('Certified by $name', 'Sertipikado ng $name');
  String get searchProduce => t('Search produce', 'Maghanap ng ani');
  String get freshest => t('Freshest', 'Pinakabago');
  String get sortNewest => t('Newest', 'Pinakabago');
  String get nearest => t('Nearest', 'Pinakamalapit');
  String get findingYourLocation =>
      t('Finding your location…', 'Hinahanap ang lokasyon mo…');
  String get sortedByDistance =>
      t('Sorted by distance from you', 'Nakaayos ayon sa layo mula sa iyo');
  String get noMapPinYet => t('No map pin yet', 'Wala pang pin sa mapa');
  String get farmsMissingMapPins => t(
    'Farms have not added their map location yet.',
    'Hindi pa idinadagdag ng mga bukid ang lokasyon nila sa mapa.',
  );
  String get locationUnavailable => t(
    'Location is off. Showing the usual order.',
    'Naka-off ang lokasyon. Ipinapakita ang karaniwang ayos.',
  );
  String get locationNoFix => t(
    "Couldn't get your location. Try again outside or check that location is on.",
    'Hindi makuha ang lokasyon mo. Subukan ulit sa labas o tingnan kung naka-on ang lokasyon.',
  );
  String get openInGoogleMaps =>
      t('Open in Google Maps', 'Buksan sa Google Maps');
  String get openStreetMapCredit =>
      t('© OpenStreetMap contributors', '© OpenStreetMap contributors');
  String kilometersAway(double kilometers) {
    final label = kilometers.toStringAsFixed(1);
    return t('$label km away', '$label km ang layo');
  }

  String get priceLowHigh =>
      t('Price low-high', 'Presyo: mababa hanggang mataas');
  String get priceHighLow =>
      t('Price high-low', 'Presyo: mataas hanggang mababa');
  String get inStockFirst => t('Availability', 'May stock');
  String get emptyCart => t('Your cart is empty.', 'Walang laman ang cart mo.');
  String get orderSummary => t('Order Summary', 'Buod ng order');
  String get listed => t('Listed', 'Nakalista');
  String get tawad => t('Tawad', 'Tawad');
  String get total => t('Total', 'Kabuuan');
  String get noOrders => t('No orders yet.', 'Wala pang order.');
  String get noFavorites =>
      t('No favorite stores yet.', 'Wala pang paboritong tindahan.');
  String get noListingsFound =>
      t('No listings found.', 'Walang nahanap na listing.');
  String get nothingHere => t('Nothing here yet.', 'Wala pa rito.');
  String get somethingWentWrong =>
      t('Something went wrong.', 'May nangyaring mali.');
  String get retry => t('Retry', 'Subukan ulit');
  String get farmStall => t('Farm stall', 'Tindahan');
  String listingNumber(int id) => t('Listing #$id', 'Listing #$id');
  String chatTitle(String title) => t('Chat · $title', 'Chat · $title');
  String reviewsCount(int count) => count == 1
      ? t('(1 review)', '(1 review)')
      : t('($count reviews)', '($count review)');
  String get chats => t('Chats', 'Mga chat');
  String get noOpenChats => t('No chats yet', 'Wala pang chat');
  String get noChatsYet => t(
    'Chats with a stall stay here, including finished orders.',
    'Nandito ang chat sa tindahan, pati ang tapos nang order.',
  );
  String get openOrders => t('Open orders', 'Buksan ang mga order');
  String get chatWithStall => t('Chat with stall', 'Makipag-chat sa tindahan');
  String get removeChat => t('Remove chat', 'Alisin ang chat');
  String get removeChatTitle =>
      t('Remove this chat?', 'Alisin ang chat na ito?');
  String removeChatBody(String name) => t(
    'This removes the chat for you only. $name can still see it. If either of you sends a new message, the chat comes back. Order messages stay on the order\'s page.',
    'Sa iyo lang aalisin ang chat na ito. Makikita pa rin ito ni $name. Kapag may nagpadala ng bagong mensahe, babalik ang chat. Nananatili ang mga mensahe ng order sa pahina ng order.',
  );
  String get chatRemoved => t('Chat removed', 'Naalis ang chat');
  String get messageSeller => t('Message seller', 'Magmensahe sa tindero');
  String orderTag(String? number) {
    final value = number?.trim() ?? '';
    if (value.isEmpty) {
      return t('Order', 'Order');
    }
    return t('Order $value', 'Order $value');
  }

  String get stepPending => t('Pending', 'Nakabinbin');
  String get stepPendingHint => t(
    "Waiting for the farmer's approval",
    'Hinihintay ang apruba ng magsasaka',
  );
  String get stepConfirmed => t('Confirmed', 'Kumpirmado');
  String get stepConfirmedHint => t(
    'The farmer approved your order',
    'Inaprubahan ng magsasaka ang iyong order',
  );
  String get readyForPickup => t('Ready for pickup', 'Handa nang kunin');
  String get outForDelivery => t('Out for delivery', 'Inihahatid na');
  String get orderComplete => t('Order complete', 'Tapos na ang order');
  String get orderCompleteHint =>
      t('Your order is fulfilled', 'Natupad na ang iyong order');
  String get cropFilter => t('Crop', 'Ani');
  String get chatWithBuyer => t('Chat with buyer', 'Makipag-chat sa buyer');
  String get order => t('Order', 'Order');
  String get you => t('You', 'Ikaw');
  String get sendMessageHint =>
      t('Message about this order', 'Mensahe tungkol sa order na ito');
  String get attachFile => t('Attach a file', 'Maglakip ng file');
  String get attachPhoto => t('Photo', 'Larawan');
  String get attachCamera => t('Camera', 'Camera');
  String get attachPdf => t('PDF', 'PDF');
  String get attachmentTooLarge =>
      t('This file is over 5 MB.', 'Lampas sa 5 MB ang file na ito.');
  String get attachmentOpenFailed =>
      t('Could not open that file.', 'Hindi mabuksan ang file.');
  String get attachmentSendFailed => t(
    'That file could not be sent. Try again.',
    'Hindi maipadala ang file. Subukan ulit.',
  );
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
  String get report => t('Report', 'I-ulat');
  String get reportDetails => t('Details (optional)', 'Detalye (opsyonal)');
  String get reportChooseReason => t('Choose a reason.', 'Pumili ng dahilan.');
  String get reportSubmitted => t(
    'Thank you. Your report was sent to the administrator.',
    'Salamat. Naipadala na ang iyong ulat sa administrator.',
  );
  String get submitReport => t('Submit', 'I-submit');

  List<({String value, String label})> get reportReasons => [
    (
      value: 'wrong_or_misleading',
      label: t(
        'Wrong or misleading information',
        'Mali o mapanlinlang na impormasyon',
      ),
    ),
    (
      value: 'prohibited_item',
      label: t('Item not allowed to be sold', 'Bawal ibenta ang item na ito'),
    ),
    (
      value: 'offensive_content',
      label: t(
        'Offensive or abusive content',
        'Masakit o mapang-abusong nilalaman',
      ),
    ),
    (value: 'spam_or_fake', label: t('Spam or fake', 'Spam o peke')),
    (value: 'other', label: t('Other', 'Iba pa')),
  ];

  String get addToCart => t('Add to cart', 'Idagdag sa cart');

  String get reserve => t('Reserve', 'Ireserba');

  String get lockedPrice => t('Locked price', 'Naka-lock na presyo');

  String get estimatedTotal => t('Estimated total', 'Tantyang kabuuan');

  String get payCashOnHandover => t(
    'Pay cash on handover after harvest',
    'Magbayad ng cash sa pagkuha pagkatapos ng ani',
  );

  String reservedHarvest(String quantity, String unit, int count) => t(
    '$quantity $unit reserved ($count)',
    '$quantity $unit ang naka-reserba ($count)',
  );

  String get ordersTab => t('Orders', 'Mga order');

  String get reservationsTab => t('Reservations', 'Mga reserbasyon');

  String get noReservations =>
      t('No reservations yet', 'Wala pang reserbasyon');

  String get activeReservations => t('Active', 'Aktibo');

  String get pastReservations => t('History', 'Nakaraan');

  String get cancelReservation =>
      t('Cancel reservation', 'Kanselahin ang reserbasyon');

  String get openNow => t('Open now', 'Buksan ngayon');

  String get openNowBody => t(
    'Opening this listing turns its reservations into orders now.',
    'Bubuksan nito ang listing at gagawing order ang mga reserbasyon ngayon.',
  );

  String get reservationReason => t('Reason', 'Dahilan');
  String get addedToCart => t('Added to cart.', 'Nadagdag sa cart.');
  String get viewCart => t('View cart', 'Tingnan ang cart');
  String get enterQuantity => t('Enter a quantity.', 'Maglagay ng dami.');
  String get quantity => t('Quantity', 'Dami');
  String get inStock => t('In stock', 'May stock');
  String get lowStock => t('Low stock', 'Kulang ang stock');
  String get takenDown => t('Taken down', 'Tinanggal');
  String get listingActive => t('Active', 'Active');
  String get listingInactive => t('Inactive', 'Inactive');
  String get listingTakenDownHint => t(
    'An administrator removed this listing from the marketplace.',
    'Tinanggal ng administrator ang listing na ito sa marketplace.',
  );
  String get outOfStock => t('Out', 'Wala');
  String get placed => t('Placed', 'Placed');
  String get confirmed => t('Confirmed', 'Confirmed');
  String get ready => t('Ready', 'Ready');
  String get completed => t('Completed', 'Completed');
  String get cancelled => t('Cancelled', 'Cancelled');
  String get fulfillment => t('Fulfillment', 'Pagsalo');
  String get buyerPicksUp => t('Buyer picks up', 'Buyer ang susalo');
  String get sellerDelivers =>
      t('Farmer-seller delivers', 'Seller ang maghahatid');
  String get agreedTimePlace =>
      t('Agreed time and place', 'Napagkasunduang oras at lugar');
  String get timePlaceHint =>
      t('Saturday 7am at the barangay hall', 'Sabado 7am sa barangay hall');
  String get cashOnHandover => t(
    'Payment is cash on handover. It is recorded, not processed.',
    'Cash ang bayad sa handover. Naitatala lang, hindi ini-process.',
  );
  String get payCashTitle =>
      t('Pay cash when you meet', 'Cash ang bayad sa pagkikita');
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
  String placeOrders(int count) =>
      t('Place $count orders', 'I-place ang $count order');
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
  String listedUnit(String peso) =>
      t('Listed unit $peso', 'Presyo bawat yunit $peso');
  String get newListing => t('New listing', 'Bagong listing');
  String get editListing => t('Edit listing', 'I-edit ang listing');
  String get saveListing => t('Save listing', 'I-save ang listing');
  String get titleLabel => t('Title', 'Pamagat');
  String get cropType => t('Crop type', 'Uri ng pananim');
  String get unit => t('Unit', 'Yunit');
  String get minimumOrder => t('Minimum order', 'Pinakamababang order');
  String get orderStep => t('Step', 'Hakbang');
  String buyersCanOrder(String amounts, String unit) => t(
    'Buyers can order $amounts $unit…',
    'Puwedeng umorder ang mga buyer ng $amounts $unit…',
  );
  String quantityStepHint(
    String min,
    String step,
    String unit, {
    String? minEquivalent,
    String? stepEquivalent,
  }) {
    if (minEquivalent == null || stepEquivalent == null) {
      return t(
        'Min $min $unit · steps of $step $unit',
        'Min $min $unit · hakbang na $step $unit',
      );
    }
    return t(
      'Min $min $unit ($minEquivalent) · steps of $step $unit ($stepEquivalent)',
      'Min $min $unit ($minEquivalent) · hakbang na $step $unit ($stepEquivalent)',
    );
  }

  String quantitySmallUnitHint(String equivalent) =>
      t('= $equivalent', '= $equivalent');
  String soldWholeStep(String name) => t(
    '$name are sold whole. Use a step of 1 or more.',
    'Buong $name lang ang binebenta. Gumamit ng hakbang na 1 o higit pa.',
  );
  String soldWholeMinimum(String name) => t(
    '$name are sold whole. Use a minimum order of 1 or more.',
    'Buong $name lang ang binebenta. Gumamit ng pinakamababang order na 1 o higit pa.',
  );
  String get minimumAtLeastStep => t(
    'The minimum order must be at least the step.',
    'Ang pinakamababang order ay dapat kahit isang hakbang.',
  );
  String get orderAmountPositive => t(
    'Use a minimum and a step greater than 0.',
    'Gumamit ng pinakamababang order at hakbang na higit sa 0.',
  );
  String get orderAmountDecimals =>
      t('Use at most 2 decimal places.', 'Hanggang 2 decimal places lang.');
  String get orderAmountMax => t(
    'The minimum order cannot be more than 99999.99.',
    'Ang pinakamababang order ay hindi puwedeng higit sa 99999.99.',
  );
  String unitName(String code) => switch (code) {
    'kg' => t('Kilogram (kg)', 'Kilo (kg)'),
    'g' => t('Gram (g)', 'Gramo (g)'),
    'ml' => t('Millilitre (mL)', 'Mililitro (mL)'),
    'liter' => t('Litre (L)', 'Litro (L)'),
    'piece' => t('Piece', 'Piraso'),
    'dozen' => t('Dozen', 'Dosena'),
    'bundle' => t('Bundle', 'Tali'),
    'sack' => t('Sack', 'Sako'),
    'tray' => t('Tray', 'Bandeha'),
    'pack' => t('Pack', 'Pakete'),
    'bottle' => t('Bottle', 'Bote'),
    _ => code,
  };
  String unitFamily(String family) => switch (family) {
    'weight' => t('Weight', 'Timbang'),
    'volume' => t('Volume', 'Volume'),
    'count' => t('Count', 'Bilang'),
    'package' => t('Package', 'Pakete'),
    _ => family,
  };
  String get price => t('Price', 'Presyo');
  String get availableFromLabel => t('Available from', 'Available simula');
  String get availableUntilLabel => t('Available until', 'Available hanggang');
  String get harvestedOnLabel => t('Harvested on', 'Araw ng ani');
  String get harvestSection => t('Harvest', 'Ani');
  String get harvestDate => t('Harvest date', 'Araw ng ani');
  String get dateMade => t('Date made', 'Araw na ginawa');
  String get harvestedQuantity => t('Harvested quantity', 'Dami ng ani');
  String get quantityMade => t('Quantity made', 'Dami na ginawa');
  String get expectedQuantity => t('Expected quantity', 'Inaasahang dami');
  String get expectedQuantityHint => t(
    "You'll record the actual harvest before it opens.",
    'Itatala mo ang tunay na ani bago ito magbukas.',
  );
  String get rejectedQuantity => t('Rejected', 'Tinanggihan');
  String get defectiveQuantity => t('Defective', 'Depektibo');
  String get rejectionReason => t('Reason', 'Dahilan');
  String get rejectionNote => t('Note', 'Tala');
  String goodToSell(String quantity, String unit) =>
      t('Good to sell: $quantity $unit', 'Mabebenta: $quantity $unit');
  String get productionCost =>
      t('Production cost (optional)', 'Gastos sa produksyon (opsyonal)');
  String get costHint => t(
    "Leave blank if you don't track costs.",
    'Iwanang blangko kung hindi mo tinututukan ang gastos.',
  );
  String get breakDownCosts => t('Break it down', 'Hatiin ang gastos');
  String get costTotal => t('Total', 'Kabuuan');
  String get addStock => t('Add stock', 'Dagdagan ang stock');
  String get removeStock => t('Remove stock', 'Bawasan ang stock');
  String get recordActualHarvest =>
      t('Record actual harvest', 'Itala ang tunay na ani');
  String get recordHarvest => t('Record harvest', 'Itala ang ani');
  String removeStockLimit(String sellable, String held, String unit) => t(
    'Up to $sellable $unit ($held held by orders)',
    'Hanggang $sellable $unit ($held ang hawak ng mga order)',
  );
  String leftAfterExpiry(String quantity, String unit) =>
      t('$quantity $unit left', 'May natitirang $quantity $unit');
  String get removeAsSpoiled => t('Remove as spoiled', 'Alisin bilang sira');
  String get extendListing => t('Extend', 'Palawigin');
  String get stockHistory => t('Stock history', 'Kasaysayan ng stock');
  String trackedSince(String date) =>
      t('Tracked since $date', 'Sinusubaybayan mula $date');
  String get openingStock => t('Opening stock', 'Panimulang stock');
  String get estimatedHarvest => t('Estimated', 'Tantya');
  String get noCostRecorded => t('No cost recorded', 'Walang naitalang gastos');
  String get loadMore => t('Load more', 'Magpakita pa');
  String get stockHistoryEmpty =>
      t('No stock records yet.', 'Wala pang tala ng stock.');
  String get confirmShortfallTitle =>
      t('Reserved quantity is higher', 'Mas mataas ang nakareserba');
  String confirmShortfallBody(String reserved, String unit) => t(
    '$reserved $unit is already reserved. Recording less cancels the shortfall when the listing opens.',
    '$reserved $unit ang nakareserba na. Kapag mas maliit ang itinala, kakanselahin ang kulang pagbukas ng listing.',
  );
  String get confirmHarvest => t('Record harvest', 'Itala ang ani');
  String stockSummary({
    required String harvested,
    required String good,
    required String sold,
    required String left,
    required String removed,
  }) => t(
    'Harvested $harvested · Good $good · Sold $sold · Left $left · Removed $removed',
    'Ani $harvested · Mabuti $good · Nabenta $sold · Natira $left · Inalis $removed',
  );
  String costCategory(String value) => switch (value) {
    'seeds' => t('Seeds', 'Buto'),
    'fertilizer' => t('Fertilizer', 'Pataba'),
    'pesticide' => t('Pesticide', 'Pestisidyo'),
    'labor' => t('Labor', 'Lakas-paggawa'),
    'transport' => t('Transport', 'Transportasyon'),
    'packaging' => t('Packaging', 'Packaging'),
    _ => t('Other', 'Iba pa'),
  };
  String rejectionReasonLabel(String value) => switch (value) {
    'pests' => t('Pests', 'Peste'),
    'bruised_damaged' => t('Bruised or damaged', 'Bugbog o sira'),
    'undersized' => t('Undersized', 'Maliit'),
    'spoiled' => t('Spoiled', 'Sira'),
    'defective' => t('Defective', 'Depektibo'),
    'packaging_damaged' => t('Packaging damaged', 'Sira ang packaging'),
    'sold_outside' => t('Sold outside the app', 'Nabenta sa labas ng app'),
    'damaged' => t('Damaged', 'Nasira'),
    'correction' => t('Correction', 'Korekson'),
    _ => t('Other', 'Iba pa'),
  };
  String get dateNotSet => t('Not set', 'Hindi nakatakda');
  String get clearDate => t('Clear', 'Alisin');
  String harvestedLine(String date) =>
      t('Harvested $date', 'Inani noong $date');
  String availableFromBadge(String date) =>
      t('Available from $date', 'Available simula $date');
  String get availableNow => t('Available now', 'Mabibili na ngayon');
  String reserveFrom(String date) =>
      t('Reserve · from $date', 'I-reserve · mula $date');
  String get reserveOnly => t('Reserve', 'I-reserve');
  String get comingSoon => t('Coming soon', 'Malapit na');
  String get discountPaused => t('Paused', 'Naka-pause');
  String get availabilityAvailable => t('Available', 'Nabibili');
  String get availabilityUpcoming => t('Upcoming', 'Paparating');
  String get availabilityExpired => t('Expired', 'Paso na');

  String shortDate(DateTime date) {
    const english = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    const filipino = [
      'Ene',
      'Peb',
      'Mar',
      'Abr',
      'May',
      'Hun',
      'Hul',
      'Ago',
      'Set',
      'Okt',
      'Nob',
      'Dis',
    ];
    final months = this.filipino ? filipino : english;
    return '${months[date.month - 1]} ${date.day}';
  }

  String availabilityChip(String? state) => switch (state) {
    'upcoming' => availabilityUpcoming,
    'expired' => availabilityExpired,
    _ => availabilityAvailable,
  };
  String get description => t('Description', 'Deskripsyon');
  String get setTawad => t('Set tawad', 'Magtakda ng tawad');
  String get replaceTawad => t('Replace tawad', 'Palitan ang tawad');
  String get endTawad => t('End tawad', 'Tapusin ang tawad');
  String get noTawad =>
      t('No tawad on this listing.', 'Walang tawad sa listing na ito.');
  String get recordWalkIn => t('Record walk-in sale', 'Itala ang walk-in sale');
  String get editShop => t('Edit shop', 'I-edit ang tindahan');
  String get editShopProfile =>
      t('Edit shop profile', 'I-edit ang profile ng tindahan');
  String get saveShopProfile =>
      t('Save shop profile', 'I-save ang profile ng tindahan');
  String get shopName => t('Shop name', 'Pangalan ng tindahan');
  String get bio => t('Bio', 'Bio');
  String get location => t('Location', 'Lokasyon');
  String get contact => t('Contact', 'Contact');
  String get shopNotFound =>
      t('Shop not found.', 'Hindi nahanap ang tindahan.');
  String get couldNotOpenLink =>
      t('Could not open the link.', 'Hindi mabuksan ang link.');
  String get couldNotOpenPhone =>
      t('Could not open the phone app.', 'Hindi mabuksan ang phone app.');
  String get readyToReview => t('Ready to review', 'Puwede nang i-review');
  String get writeReview => t('Write a review', 'Sumulat ng review');
  String get reviewComment => t('Comment (optional)', 'Komento (opsyonal)');
  String get submitReview => t('Submit review', 'I-submit ang review');
  String get chooseARating => t('Choose a star rating.', 'Pumili ng rating.');
  String get reviewSaved => t('Review saved.', 'Nasave ang review.');
  String youRated(Object rating) =>
      t('You rated $rating', 'Ni-rate mo: $rating');
  String get items => t('Items', 'Mga item');
  String get cashAtMeetup => t('Cash at meetup', 'Cash sa pagkikita');
  String get payOnHandover => t('Cash on handover', 'Cash sa pag-abot');
  String get onlinePayment =>
      t("Online payment (seller's QR)", 'Online na bayad (QR ng nagbebenta)');
  String get cashPill => t('Cash', 'Cash');
  String get onlinePaymentPill => t('Online payment', 'Online na bayad');
  String paymentMethodLabel(String? method) =>
      method == 'online_transfer' ? onlinePayment : payOnHandover;
  String get acceptOnlinePayment => t(
    'Accept online payment (send your GCash/Maya QR in chat)',
    'Tumanggap ng online na bayad (ipadala ang GCash/Maya QR sa chat)',
  );
  String get sellerCashOnly => t(
    'This seller accepts cash only',
    'Cash lang ang tinatanggap ng nagbebenta na ito',
  );
  String get onlinePaymentHint => t(
    "After you place the order, we'll message the seller to send their QR. Pay in the app you use (GCash/Maya); AniHow does not process payments.",
    'Pagkatapos mong mag-order, magme-message kami sa nagbebenta para ipadala ang QR. Magbayad sa app na gamit mo (GCash/Maya); hindi nagpoproseso ng bayad ang AniHow.',
  );
  String get amountReceivedOnline =>
      t('Amount received (online)', 'Halagang natanggap (online)');
  String tawadMinus(String peso) => t('Tawad −$peso', 'Tawad −$peso');
  String get discountTawad => t('Discount (tawad)', 'Diskwento (tawad)');
  String discountTawadMinus(String peso) =>
      t('Discount (tawad) −$peso', 'Diskwento (tawad) −$peso');
  String get discountOptionalTitle =>
      t('Discount (optional)', 'Diskwento (opsyonal)');
  String get discountOptionalHelp => t(
    'Give buyers a fixed peso discount (tawad).',
    'Bigyan ang mga buyer ng takdang bawas na piso (tawad).',
  );
  String get discountChip => t('Discount', 'Diskwento');
  String get editDiscount => t('Edit', 'I-edit');
  String get endDiscount => t('End', 'Tapusin');
  String promoFlatOff(String peso) => t('$peso off', '$peso bawas');
  String promoMinOff(String peso, String quantity, String unit) {
    final amount = unit.trim().isEmpty ? quantity : '$quantity $unit';
    return t(
      '$peso off when you buy $amount or more',
      '$peso bawas kapag bumili ka ng $amount o higit',
    );
  }

  String sellerDiscountFlat(String peso) =>
      t('$peso off every order', '$peso bawas bawat order');
  String sellerDiscountMin(String peso, String quantity, String unit) {
    final amount = unit.trim().isEmpty ? quantity : '$quantity $unit';
    return t(
      '$peso off when buyers order $amount or more',
      '$peso bawas kapag umorder ang buyer ng $amount o higit',
    );
  }

  String cartDiscountNudge(String missing, String unit, String peso) {
    final amount = unit.trim().isEmpty ? missing : '$missing $unit';
    return t(
      'Add $amount more to get $peso off',
      'Magdagdag ng $amount pa para makakuha ng $peso bawas',
    );
  }

  String tawadOffThisOrder(String peso) =>
      t('$peso off this order', '$peso bawas sa order na ito');
  String tawadOffAtMin(String peso, String quantity) =>
      t('$peso off at $quantity and above', '$peso bawas sa $quantity pataas');
  String get walkIn => t('Walk-in', 'Walk-in');
  String get confirmOrder => t('Confirm order', 'Kumpirmahin ang order');
  String get markReady => t('Mark ready', 'Markahang ready');
  String get completeHandover => t('Complete handover', 'Tapusin ang handover');
  String get cancelOrder => t('Cancel order', 'Kanselahin ang order');
  String get cancelThisOrder =>
      t('Cancel this order?', 'Kanselahin ang order na ito?');
  String get cancelBeforeConfirm => t(
    'The seller has not confirmed it yet. You can add a short note for the seller.',
    'Hindi pa ito kinukumpirma ng tindahan. Puwede kang maglagay ng maikling tala para sa kanila.',
  );
  String get keepOrder => t('Keep order', 'Panatilihin ang order');
  String get orderCancelled => t('Order cancelled.', 'Kinansela ang order.');
  String get orderAlreadyConfirmed => t(
    "The seller already confirmed this order, so it can't be cancelled anymore.",
    'Kinumpirma na ng tindahan ang order na ito, kaya hindi na ito makakansela.',
  );
  String get pleaseWait => t('Please wait…', 'Sandali…');
  String get cashReceived => t('Cash received', 'Cash na natanggap');
  String cashReceivedLine(String peso) =>
      t('Cash received $peso', 'Cash na natanggap $peso');
  String orderTotalHint(String peso) =>
      t('Order total $peso', 'Kabuuan ng order $peso');
  String get enterCashReceived =>
      t('Enter the cash amount received.', 'Ilagay ang cash na natanggap.');
  String get record => t('Record', 'Itala');
  String get cancelReasonHint => t(
    'A no-show is a cancellation reason, not a separate status.',
    'Ang no-show ay rason ng kanselasyon, hindi hiwalay na status.',
  );
  String get chooseCancelReason =>
      t('Choose a cancellation reason.', 'Pumili ng rason ng kanselasyon.');
  String get sellerDeclined =>
      t('Declined by farmer-seller', 'Tinanggihan ng seller');
  String get noShowHandover =>
      t('No-show at handover', 'Hindi dumating sa handover');
  String get sellerUnresponsive =>
      t('Seller unresponsive', 'Hindi tumugon ang seller');
  String get otherReason => t('Other', 'Iba');
  String get cancelledByBuyer => t('Cancelled by buyer', 'Kinansela ng buyer');
  String get reviewUnlocked =>
      t('Review unlocked for the buyer', 'Puwede nang mag-review ang buyer');
  String buyerRated(Object rating) =>
      t('Buyer rated $rating', 'Rating ng buyer: $rating');
  String get orderNotFound => t('Order not found.', 'Hindi nahanap ang order.');
  String get noPlacedOrders =>
      t('No placed orders.', 'Walang naka-place na order.');
  String get noConfirmedOrders =>
      t('No confirmed orders.', 'Walang kumpirmadong order.');
  String get noReadyOrders => t(
    'No orders waiting for handover.',
    'Walang order na hinihintay sa handover.',
  );
  String get noCompletedOrders =>
      t('No completed orders.', 'Walang tapos na order.');
  String get noCancelledOrders =>
      t('No cancelled orders.', 'Walang kinanselang order.');

  String sellerCancelReason(String value) => switch (value) {
    'seller_declined' => sellerDeclined,
    'no_show' => noShowHandover,
    'other' => otherReason,
    _ => value,
  };

  String cancellationReasonText(String? value, {String? fallback}) =>
      switch (value) {
        'buyer_cancelled' => cancelledByBuyer,
        'seller_declined' => sellerDeclined,
        'no_show' => noShowHandover,
        'seller_unresponsive' => sellerUnresponsive,
        'other' => otherReason,
        _ => fallback ?? value ?? '',
      };
  String get showMore => t('Show more', 'Magpakita pa');
  String get loading => t('Loading…', 'Naglo-load…');
  String get superAdmin => t('Super admin', 'Super admin');
  String get chooseCrop =>
      t('Choose a crop type.', 'Pumili ng uri ng pananim.');
  String get nameProduce => t('Name this produce', 'Pangalanan ang ani');
  String get shortNote => t('Add a short note', 'Magdagay ng maikling tala');
  String get deleteListing => t('Delete listing', 'Tanggalin ang listing');
  String get deleteListingAsk =>
      t('Delete this listing?', 'Tanggalin ang listing na ito?');
  String cancelReservationsTitle(int count) =>
      t('Cancel $count reservation(s)?', 'Kanselahin ang $count reservation?');
  String cancelReservationsBody(String quantity, String unit, int count) => t(
    '$quantity $unit is reserved by $count buyer(s). Turning this listing off (or deleting it) cancels those reservations and notifies the buyers.',
    '$quantity $unit ang nakareserba ng $count buyer. Kapag pinatay o tinanggal ang listing, makakansela ang mga reservation at maaabisuhan ang mga buyer.',
  );
  String get keepListing => t('Keep listing', 'Panatilihin ang listing');
  String get turnOffAndCancel =>
      t('Turn off and cancel', 'Patayin at kanselahin');
  String get deleteAndCancel =>
      t('Delete and cancel', 'Tanggalin at kanselahin');
  String get endTawadAsk => t('End this tawad?', 'Tapusin ang tawad na ito?');
  String get addPhoto => t('Add photo', 'Magdagdag ng larawan');
  String get listingPhotoHint => t(
    'A clear photo helps buyers pick your produce.',
    'Mas madaling piliin ng buyer kung may malinaw na larawan.',
  );
  String get listingDetails => t('Listing details', 'Detalye ng listing');
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
  String get tawadFlat =>
      t('Flat peso off per order', 'Flat na bawas bawat order');
  String get tawadMinQty =>
      t('Peso off at a minimum quantity', 'Bawas kapag may minimum na dami');
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
  String get walkInRecorded =>
      t('Walk-in sale recorded', 'Naitala na ang walk-in sale');
  String get noWalkInListings => t(
    'No listings available for a walk-in sale.',
    'Walang listing para sa walk-in sale.',
  );
  String get listingLabel => t('Listing', 'Listing');
  String get amountReceived => t('Amount received', 'Halagang natanggap');
  String amountReceivedLine(String peso) =>
      t('Amount received $peso', 'Halagang natanggap $peso');
  String get guestName =>
      t('Guest name (optional)', 'Pangalan ng bisita (opsyonal)');
  String get guestNameHint =>
      t('For your reference only', 'Para sa tala mo lang');
  String get noteOptional => t('Note (optional)', 'Tala (opsyonal)');
  String get recordSale => t('Record sale', 'Itala ang benta');
  String get walkInCashHint => t(
    'Count the cash, then type the amount. AniHow only records it.',
    'Bilangin ang cash, tapos i-type ang halaga. Nagtatala lang ang AniHow.',
  );
  String pricePerUnit(String peso) =>
      t('Price per unit $peso', 'Presyo bawat yunit $peso');
  String availableQty(String qty, String? unit) => unit == null || unit.isEmpty
      ? t('Available $qty', 'Available $qty')
      : t('Available $qty $unit', 'Available $qty $unit');
  String get sales => t('Sales', 'Benta');
  String get mySales => t('My Sales', 'Aking Benta');
  String get mySalesEmpty => t(
    'No completed sales yet. Completed orders and walk-ins will show here.',
    'Wala pang tapos na benta. Dito lalabas ang completed na order at walk-in.',
  );
  String get completedOrders => t('Completed orders', 'Tapos na order');
  String get unitsSold => t('Units sold', 'Nabentang yunit');
  String get grossSales => t('Gross sales', 'Kabuuang benta');
  String get averageTawad => t('Average tawad', 'Karaniwang tawad');
  String get salesThisPeriod => t('Sales this period', 'Benta sa panahong ito');
  String get topCrops => t('Top crops', 'Pinakamabentang pananim');
  String get bestSellers => t('Best sellers', 'Pinakamabenta');
  String get walkInShare => t('Walk-in vs app', 'Walk-in laban sa app');
  String get walkInSales => t('Walk-in sales', 'Benta sa walk-in');
  String get appSales => t('App sales', 'Benta sa app');
  String get periodWeek => t('Week', 'Linggo');
  String get periodMonth => t('Month', 'Buwan');
  String salesWindow(String startIso, String endIso) {
    final start = DateTime.tryParse(startIso);
    final end = DateTime.tryParse(endIso);
    if (start == null || end == null) {
      return t('$startIso to $endIso', '$startIso hanggang $endIso');
    }

    return t(
      '${_shortMonth(start, english: true)} ${start.day} to ${_shortMonth(end, english: true)} ${end.day}',
      '${_shortMonth(start, english: false)} ${start.day} hanggang ${_shortMonth(end, english: false)} ${end.day}',
    );
  }

  String _shortMonth(DateTime date, {required bool english}) {
    const en = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    const fil = [
      'Ene',
      'Peb',
      'Mar',
      'Abr',
      'May',
      'Hun',
      'Hul',
      'Ago',
      'Set',
      'Okt',
      'Nob',
      'Dis',
    ];

    return (english ? en : fil)[date.month - 1];
  }

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
    'order_awaiting_confirmation' => t(
      'Order awaiting confirmation',
      'May order na hinihintay ang kumpirmasyon',
    ),
    'listing_low_stock' => t(
      'Listing low on stock',
      'Kulang na ang stock ng listing',
    ),
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
    'faq_entry_moderated' => t(
      'FAQ answer moderated',
      'May binago sa sagot sa FAQ',
    ),
    'account_deletion_requested' => t(
      'Account deletion requested',
      'May humiling ng pagbura ng account',
    ),
    'account_deletion_rejected' => t(
      'Account deletion request rejected',
      'Tinanggihan ang kahilingan sa pagbura ng account',
    ),
    'report_submitted' => t('New report submitted', 'May bagong ulat'),
    'report_resolved' => t('Report resolved', 'Naaksyunan ang ulat'),
    'report_dismissed' => t('Report dismissed', 'Tinanggihan ang ulat'),
    'reservation_made' => t('Reservation made', 'May reserbasyon'),
    'reservation_converted' => t(
      'Reservation converted',
      'Naging order ang reserbasyon',
    ),
    'reservation_cancelled' => t(
      'Reservation cancelled',
      'Kinansela ang reserbasyon',
    ),
    'harvest_reminder' => t(
      'Record the actual harvest',
      'Itala ang tunay na ani',
    ),
    'expired_stock_left' => t(
      'Stock left after the listing ended',
      'May natirang stock pagkatapos magtapos ang listing',
    ),
    _ => fallback,
  };

  List<({String title, String body})> get aboutSections => filipino
      ? const [
          (
            title: 'Ano ang AniHow',
            body: 'Ang AniHow ay digital na palengke para sa mga bukid sa General Trias, Cavite. Ang buyer ay tumitingin ng listing, nagre-reserve, at sumasalo ng ani. Ang farmer-seller ay naglalagay ng stock, tumatanggap ng order, at nagtatala ng benta sa cash.',
          ),
          (
            title: 'Paano bumili',
            body: 'Walang delivery fleet at walang bayad sa app. Magkikita kayo ng seller, magbabayad ng cash, at kukuha ng ani. Ang chat ay unahan lang ng oras at lugar ng salo, hindi para tumawad.',
          ),
          (
            title: 'Sino ang gumagamit',
            body: 'Ang buyer ay puwedeng magpalista. Ang farmer-seller account ay ginagawa ng admin ng AniHow. Ang super admin at content editor ay nasa web panel, hindi sa app na ito.',
          ),
          (title: 'Bersyon', body: 'Ang Android app na ito ay AniHow v1.0.0.'),
        ]
      : const [
          (
            title: 'What AniHow is',
            body: 'AniHow is a digital market hub for farms in General Trias, Cavite. Buyers browse listings, reserve produce, and pick it up. Farmer-sellers post stock, accept orders, and record cash sales.',
          ),
          (
            title: 'How buying works',
            body: 'There is no delivery fleet and no in-app payment. You meet the seller, pay cash, and take the produce. Order chat is only for pickup time and place, not for bargaining.',
          ),
          (
            title: 'Who uses the app',
            body: 'Buyers can create their own account. Farmer-seller accounts are created by the AniHow admin. Super admins and content editors use the web panel, not this app.',
          ),
          (title: 'Version', body: 'This Android app is AniHow v1.0.0.'),
        ];

  List<({String title, String body})> get termsSections => filipino
      ? const [
          (
            title: 'Paggamit ng AniHow',
            body: 'Sa paggamit ng app, sumasang-ayon ka sa mga tuntuning ito. Ang AniHow ay pantala ng reserbasyon at benta sa pickup market. Hindi ito mismo ang nagbebenta, at hindi ito tumatanggap ng card, GCash, o bangko.',
          ),
          (
            title: 'Mga account',
            body: 'Itago ang email at password. Kailangang beripikahin ng buyer ang email bago mag-order o mag-save ng paborito. Ang farmer-seller account ay nakatali sa farm na ibinigay ng admin. Huwag ipamahagi ang login.',
          ),
          (
            title: 'Order at pera',
            body: 'Ang Placed order ay reserbasyon, hindi pa bayad. Kinukumpirma ng seller ang stock, minamarkahan itong ready, at nagtatala ng cash sa handover. Puwedeng i-cancel ng buyer habang Placed pa. Ang walk-in ay para sa buyer na wala sa app at walang chat.',
          ),
          (
            title: 'Presyo at tawad',
            body: 'Ang nakalistang presyo at tawad ay itinakda ng seller. Hindi puwedeng bumaba ang tawad sa floor price ng farm. Hindi pantawad ang chat.',
          ),
          (
            title: 'Listing at review',
            body: 'Dapat totoo ang deskripsyon ng stock. Puwedeng tanggalin ng admin ang listing na lumalabag sa rule ng farm. Puwedeng mag-review ang buyer pagkatapos ng completed handover.',
          ),
          (
            title: 'Ano ang iniimbak namin',
            body: 'Iniimbak ng AniHow ang pangalan, email, at optional na phone; mga order, chat tungkol sa order, paborito, at teksto ng shop profile. Iniimbak din ang larawan ng listing. Ginagamit ito para patakbuhin ang palengke, magpadala ng verification code, at magpakita ng abiso.',
          ),
          (
            title: 'Ano ang hindi namin ginagawa',
            body: 'Hindi namin ibinebenta ang data mo. Hindi kami tumatanggap ng card o e-wallet. Hindi namin sinusubaybayan ang live location. Wala pang push notification sa bersyong ito.',
          ),
          (
            title: 'Mga tanong',
            body: 'Para sa tulong, buksan ang FAQ sa Settings. Para sa isang reserbasyon, buksan ang order at gamitin ang Chat.',
          ),
        ]
      : const [
          (
            title: 'Using AniHow',
            body: 'By using this app you agree to these rules. AniHow is a reservation and record tool for a pickup market. It does not sell produce itself and it does not take card, GCash, or bank payments.',
          ),
          (
            title: 'Accounts',
            body: 'Keep your email and password private. Buyers must verify email before they can order or save favorites. Farmer-seller accounts stay under the farm that the admin assigned. Do not share a login.',
          ),
          (
            title: 'Orders and cash',
            body: 'A Placed order is a reservation, not a paid sale. The seller confirms stock, marks it ready, and records cash at handover. A buyer may cancel only while the order is still Placed. Walk-in sales are for buyers who are not in the app and have no chat thread.',
          ),
          (
            title: 'Prices and tawad',
            body: 'The listed price and any tawad (peso discount) are set by the seller. Tawad cannot go below the farm floor price. Chat is not for negotiating a new price.',
          ),
          (
            title: 'Listings and reviews',
            body: 'Sellers must describe stock honestly. The admin may take down a listing that breaks farm rules. Buyers may review a seller only after a completed handover.',
          ),
          (
            title: 'What we store',
            body: 'AniHow stores your name, email, and optional phone; orders, chat about those orders, favorites, and shop profile text. Listing photos you upload are stored so buyers can see the produce. We use this data to run the market hub, send a verification code, and show in-app notices.',
          ),
          (
            title: 'What we do not do',
            body: 'We do not sell your data. We do not process cards or e-wallets. We do not track your live location. Push notifications are not in this version.',
          ),
          (
            title: 'Questions',
            body: 'For how-to help, open FAQ in Settings. For one reservation, open that order and use Chat.',
          ),
        ];
}
