import 'package:flutter_test/flutter_test.dart';
import 'package:anihow/config/api_config.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/theme/anihow_space.dart';
import 'package:anihow/theme/anihow_theme.dart';

void main() {
  test('Android emulator API host is 10.0.2.2', () {
    expect(ApiConfig.emulatorHost, 'http://10.0.2.2:8000');
    expect(ApiConfig.baseUrl.endsWith('/api'), isTrue);
  });

  test('warm agricultural palette tokens', () {
    expect(AniHowColors.brand.toARGB32(), 0xFF1F5A3E);
    expect(AniHowColors.cream.toARGB32(), 0xFFF8F6F0);
    expect(AniHowColors.card.toARGB32(), 0xFFFFFFFF);
    expect(AniHowColors.text.toARGB32(), 0xFF1E2421);
    expect(AniHowColors.muted.toARGB32(), 0xFF6C757D);
    expect(AniHowColors.badge.toARGB32(), 0xFFE63946);
    expect(AniHowColors.inStockAvatar.toARGB32(), 0xFF7CD96C);
    expect(AniHowColors.inStockBg.toARGB32(), 0xFFD9F5DF);
    expect(AniHowColors.inStock.toARGB32(), 0xFF1C5635);
    expect(AniHowColors.lowStockAvatar.toARGB32(), 0xFFE88A83);
    expect(AniHowColors.lowStockBg.toARGB32(), 0xFFFCE3DE);
    expect(AniHowColors.lowStock.toARGB32(), 0xFFB94A3E);
    expect(AniHowColors.switchOn.toARGB32(), 0xFF2E8B57);
    expect(AniHowColors.switchOff.toARGB32(), 0xFFD6D1C7);
    expect(AniHowColors.navActive.toARGB32(), 0xFFD7EADF);
    expect(AniHowColors.navInactive.toARGB32(), 0xFF6F7872);
    expect(AniHowColors.navBar.toARGB32(), 0xFFFDFCFA);
  });

  test('spacing and type scale are locked', () {
    expect(AniHowSpace.screen, 16);
    expect(AniHowSpace.cardGap, 12);
    expect(AniHowSpace.section, 20);
    expect(AniHowSpace.cardPad, 12);
    expect(AniHowSpace.radius, 12);
    expect(AniHowSpace.header, 20);
    expect(AniHowSpace.headline, 20);
    expect(AniHowSpace.title, 16);
    expect(AniHowSpace.name, 16);
    expect(AniHowSpace.body, 14);
    expect(AniHowSpace.meta, 14);
    expect(AniHowSpace.label, 12);
    expect(AniHowSpace.tab, 15);
    expect(AniHowSpace.nav, 13);
    expect(AniHowSpace.tabHeight, 60);
    expect(AniHowSpace.thumb, 64);
    expect(AniHowMoney.peso(65), '₱65.00');
    expect(AniHowMoney.rating(4), '4.0');
    expect(AniHowMoney.rating('4.50'), '4.5');
  });

  test('notifications with read_at are not unread', () {
    final unread = AppNotification.fromJson({
      'id': 1,
      'title': 'Low stock',
      'body': 'hotdog is down to 2.00 kg.',
      'read_at': null,
    });
    final read = unread.copyWith(readAt: '2026-09-19T07:11:56.000000Z');

    expect(unread.isUnread, isTrue);
    expect(read.isUnread, isFalse);
  });
}
