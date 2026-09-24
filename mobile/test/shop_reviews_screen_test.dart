import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/shop_reviews_screen.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/produce_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app({required Widget home}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => PreferencesController()),
      ChangeNotifierProvider(create: (_) => AuthController()..restoring = false),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: home,
    ),
  );
}

const _reviews = PagedShopReviews(
  reviews: [
    ShopReview(
      id: 11,
      rating: 5,
      reviewerName: 'Maria Buyer',
      comment: 'Sariwa ang kamatis.',
      createdAt: '2026-01-02T08:00:00Z',
    ),
    ShopReview(
      id: 10,
      rating: 4,
      reviewerName: 'Other Buyer',
      comment: 'Okay.',
      createdAt: '2026-01-01T08:00:00Z',
    ),
  ],
  currentPage: 1,
  lastPage: 1,
  averageRating: '4.5',
  reviewsCount: 2,
);

void main() {
  testWidgets('farmer shop reviews list shows header figures and tiles', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(home: const ShopReviewsScreen(preview: _reviews)),
    );
    await tester.pump();

    expect(find.byType(RatingLabel), findsOneWidget);
    expect(find.text(AppStrings(false).reviewsCount(2)), findsOneWidget);
    expect(find.text('Maria Buyer'), findsOneWidget);
    expect(find.text('Sariwa ang kamatis.'), findsOneWidget);
    expect(find.text('Other Buyer'), findsOneWidget);
    expect(find.text('Okay.'), findsOneWidget);
  });

  testWidgets('farmer shop reviews empty state shows when there are none', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const ShopReviewsScreen(
          preview: PagedShopReviews(
            reviews: [],
            currentPage: 1,
            lastPage: 1,
            reviewsCount: 0,
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.text(AppStrings(false).noReviewsYet), findsOneWidget);
    expect(find.text('Maria Buyer'), findsNothing);
  });

  testWidgets('each review tile has a Report button that opens the report sheet', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(home: const ShopReviewsScreen(preview: _reviews)),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text(s.report), findsNWidgets(2));

    await tester.tap(find.byKey(const ValueKey('shop-review-report-11')));
    await tester.pumpAndSettle();

    expect(find.byKey(const ValueKey('report-reason-wrong_or_misleading')), findsOneWidget);
    expect(find.byKey(const ValueKey('report-submit')), findsOneWidget);
  });
}
