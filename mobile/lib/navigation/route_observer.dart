import 'package:flutter/material.dart';

final GlobalKey<ScaffoldMessengerState> anihowScaffoldMessengerKey =
    GlobalKey<ScaffoldMessengerState>();

final AniHowRouteObserver anihowRouteObserver = AniHowRouteObserver();

void dismissAniHowSnackBars() {
  anihowScaffoldMessengerKey.currentState?.clearSnackBars();
}

class AniHowRouteObserver extends RouteObserver<Route<dynamic>> {
  @override
  void didPush(Route<dynamic> route, Route<dynamic>? previousRoute) {
    dismissAniHowSnackBars();
    super.didPush(route, previousRoute);
  }

  @override
  void didPop(Route<dynamic> route, Route<dynamic>? previousRoute) {
    dismissAniHowSnackBars();
    super.didPop(route, previousRoute);
  }

  @override
  void didRemove(Route<dynamic> route, Route<dynamic>? previousRoute) {
    dismissAniHowSnackBars();
    super.didRemove(route, previousRoute);
  }

  @override
  void didReplace({Route<dynamic>? newRoute, Route<dynamic>? oldRoute}) {
    dismissAniHowSnackBars();
    super.didReplace(newRoute: newRoute, oldRoute: oldRoute);
  }
}
