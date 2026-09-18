import 'package:flutter_test/flutter_test.dart';
import 'package:anihow/config/api_config.dart';
import 'package:anihow/theme/anihow_space.dart';
import 'package:anihow/theme/anihow_theme.dart';

void main() {
  test('Android emulator API host is 10.0.2.2', () {
    expect(ApiConfig.emulatorHost, 'http://10.0.2.2:8000');
    expect(ApiConfig.baseUrl.endsWith('/api'), isTrue);
  });

  test('brand green is #1D9E75', () {
    expect(AniHowColors.brand.toARGB32(), 0xFF1D9E75);
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
}
