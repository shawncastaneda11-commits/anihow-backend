import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../navigation/route_observer.dart';
import '../state/auth_controller.dart';

const orderStatusNoticeTypes = {
  'order_placed',
  'order_confirmed',
  'order_ready',
  'order_completed',
  'order_cancelled',
};

mixin OrderStatusPoll<T extends StatefulWidget>
    on State<T>, WidgetsBindingObserver, RouteAware {
  Timer? _orderPoll;
  bool _orderRouteCovered = false;
  int _seenOrderNoticeId = 0;
  bool _orderNoticesPrimed = false;

  bool get orderPollEnabled => true;

  bool get orderPollBlocked => false;

  Future<void> refreshPolledOrders();

  void startOrderStatusPoll() {
    WidgetsBinding.instance.addObserver(this);
    _orderPoll?.cancel();
    _orderPoll = Timer.periodic(const Duration(seconds: 10), (_) {
      unawaited(pollOrderStatus(fromTimer: true));
    });
  }

  void stopOrderStatusPoll() {
    _orderPoll?.cancel();
    _orderPoll = null;
    WidgetsBinding.instance.removeObserver(this);
    anihowRouteObserver.unsubscribe(this);
  }

  void bindOrderStatusRoute() {
    anihowRouteObserver.unsubscribe(this);
    final route = ModalRoute.of(context);
    if (route is PageRoute<dynamic>) {
      anihowRouteObserver.subscribe(this, route);
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      unawaited(pollOrderStatus(force: true));
    }
  }

  @override
  void didPushNext() {
    _orderRouteCovered = true;
  }

  @override
  void didPopNext() {
    _orderRouteCovered = false;
    unawaited(pollOrderStatus(force: true));
  }

  @override
  void didPush() {}

  @override
  void didPop() {}

  Future<void> pollOrderStatus({bool fromTimer = false, bool force = false}) async {
    if (!_canPollOrders) {
      return;
    }
    final noticed = await _newOrderNotice();
    if (!_canPollOrders) {
      return;
    }
    if (fromTimer || force || noticed) {
      await refreshPolledOrders();
    }
  }

  bool get _canPollOrders {
    if (!mounted || !orderPollEnabled || orderPollBlocked || _orderRouteCovered) {
      return false;
    }
    final route = ModalRoute.of(context);
    return route == null || route.isCurrent;
  }

  Future<bool> _newOrderNotice() async {
    try {
      final notices = await context.read<AuthController>().api.notifications();
      var newest = _seenOrderNoticeId;
      var fresh = false;
      for (final notice in notices) {
        if (!orderStatusNoticeTypes.contains(notice.type)) {
          continue;
        }
        if (notice.id > newest) {
          newest = notice.id;
        }
        if (_orderNoticesPrimed && notice.id > _seenOrderNoticeId) {
          fresh = true;
        }
      }
      _seenOrderNoticeId = newest;
      _orderNoticesPrimed = true;
      return fresh;
    } catch (_) {
      return false;
    }
  }
}
