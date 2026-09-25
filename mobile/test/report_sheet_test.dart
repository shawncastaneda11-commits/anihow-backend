import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/listing_detail_screen.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/report_sheet.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app({
  required Widget home,
  AuthController? auth,
}) {
  final resolvedAuth = auth ?? AuthController();
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => PreferencesController()),
      ChangeNotifierProvider.value(value: resolvedAuth),
      ChangeNotifierProvider(create: (_) => CartController(resolvedAuth)),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: home,
    ),
  );
}

AuthController _authWith({required int id}) {
  final auth = AuthController();
  auth.user = UserAccount(
    id: id,
    name: 'Maria Buyer',
    email: 'maria@example.com',
    roles: const ['buyer'],
  );
  return auth;
}

const _ownListing = ListingItem(
  id: 4,
  title: 'Morning crate',
  pricePerUnit: '30',
  quantityAvailable: '12',
  sellerId: 9,
  sellerName: 'Mang Tonyo',
);

void main() {
  testWidgets('report sheet requires a reason before submit', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    var submitted = false;
    await tester.pumpWidget(
      _app(
        home: Scaffold(
          body: ReportBottomSheet(
            targetType: 'listing',
            targetId: 4,
            submit: (_, _) async => submitted = true,
          ),
        ),
      ),
    );
    await tester.pump();

    final s = AppStrings(false);
    await tester.ensureVisible(find.byKey(const ValueKey('report-submit')));
    await tester.tap(find.byKey(const ValueKey('report-submit')));
    await tester.pump();

    expect(find.text(s.reportChooseReason), findsOneWidget);
    expect(submitted, isFalse);
  });

  testWidgets('report is hidden on the viewer own listing', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        auth: _authWith(id: 9),
        home: const ListingDetailScreen(listingId: 4, preview: _ownListing),
      ),
    );
    await tester.pump();

    expect(find.text(AppStrings(false).report), findsNothing);
    expect(find.byKey(const ValueKey('listing-report')), findsNothing);
  });

  testWidgets('successful report shows the thank-you snackbar', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: Scaffold(
          body: ReportBottomSheet(
            targetType: 'listing',
            targetId: 4,
            submit: (_, _) async {},
          ),
        ),
      ),
    );
    await tester.pump();

    final s = AppStrings(false);
    await tester.tap(find.byKey(const ValueKey('report-reason-wrong_or_misleading')));
    await tester.pump();
    await tester.ensureVisible(find.byKey(const ValueKey('report-submit')));
    await tester.tap(find.byKey(const ValueKey('report-submit')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text(s.reportSubmitted), findsOneWidget);
  });
}
