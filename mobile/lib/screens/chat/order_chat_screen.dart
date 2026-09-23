import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../services/order_chat_realtime.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/async_view.dart';
import '../../widgets/primary_button.dart';

class OrderChatScreen extends StatefulWidget {
  const OrderChatScreen({super.key, required this.order});

  final OrderRecord order;

  @override
  State<OrderChatScreen> createState() => _OrderChatScreenState();
}

class _OrderChatScreenState extends State<OrderChatScreen> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  final List<OrderMessage> _messages = [];
  OrderChatRealtime? _realtime;
  StreamSubscription<OrderMessage>? _liveSub;
  Timer? _poll;
  bool _loading = true;
  Object? _error;
  bool _sending = false;
  bool _canSend = true;

  @override
  void initState() {
    super.initState();
    _canSend = !widget.order.isCancelled && !widget.order.isWalkIn;
    _reload();
    _startRealtime();
    _poll = Timer.periodic(const Duration(seconds: 8), (_) => _pollNewer());
  }

  Future<void> _reload() async {
    setState(() {
      _loading = _messages.isEmpty;
      _error = null;
    });
    try {
      final items = await context.read<AuthController>().api.orderMessages(widget.order.id);
      if (!mounted) {
        return;
      }
      setState(() {
        _messages
          ..clear()
          ..addAll(items);
        _loading = false;
        _error = null;
      });
      _scrollToEnd();
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error;
      });
    }
  }

  Future<void> _startRealtime() async {
    final api = context.read<AuthController>().api;
    final realtime = OrderChatRealtime(api: api, orderId: widget.order.id);
    _realtime = realtime;
    _liveSub = realtime.messages.listen(_appendIfNew);
    await realtime.connect();
  }

  Future<void> _pollNewer() async {
    if (!mounted) {
      return;
    }
    try {
      final newer = await context.read<AuthController>().api.orderMessages(
            widget.order.id,
            afterId: _messages.isEmpty ? null : _messages.last.id,
          );
      for (final message in newer) {
        _appendIfNew(message);
      }
    } on ApiException {
      // Quiet fallback; the composer stays usable.
    }
  }

  void _appendIfNew(OrderMessage message) {
    if (!mounted) {
      return;
    }
    if (_messages.any((item) => item.id == message.id)) {
      return;
    }
    setState(() => _messages.add(message));
    _scrollToEnd();
  }

  void _scrollToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scroll.hasClients) {
        return;
      }
      _scroll.animateTo(
        _scroll.position.maxScrollExtent + 80,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    });
  }

  Future<void> _send() async {
    final body = _input.text.trim();
    if (!_canSend || _sending || body.isEmpty) {
      return;
    }
    setState(() => _sending = true);
    try {
      final message = await context.read<AuthController>().api.sendOrderMessage(
            widget.order.id,
            body: body,
          );
      if (!mounted) {
        return;
      }
      _input.clear();
      _appendIfNew(message);
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _sending = false);
      }
    }
  }

  @override
  void dispose() {
    _poll?.cancel();
    _liveSub?.cancel();
    unawaited(_realtime?.dispose() ?? Future<void>.value());
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final userId = context.watch<AuthController>().user?.id;
    final title = widget.order.orderNumber ?? 'Order #${widget.order.id}';

    return Scaffold(
      appBar: AppBar(title: Text('Chat · $title')),
      body: Column(
        children: [
          Expanded(child: _buildThread(userId)),
          SafeArea(
            top: false,
            child: Padding(
              padding: AniHowSpace.screenPadding,
              child: !_canSend
                  ? Text(
                      widget.order.isWalkIn
                          ? 'Walk-in sales have no buyer chat.'
                          : 'This order is cancelled. Chat is read-only.',
                      style: Theme.of(context).textTheme.bodyMedium,
                    )
                  : Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _input,
                            minLines: 1,
                            maxLines: 4,
                            maxLength: 1000,
                            decoration: const InputDecoration(
                              hintText: 'Message about this order',
                              counterText: '',
                            ),
                            textInputAction: TextInputAction.send,
                            onSubmitted: (_) => _send(),
                          ),
                        ),
                        const SizedBox(width: AniHowSpace.cardGap),
                        PrimaryButton(
                          label: 'Send',
                          busy: _sending,
                          onPressed: _send,
                        ),
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildThread(int? userId) {
    if (_loading && _messages.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _messages.isEmpty) {
      return AsyncViewError(onRetry: _reload);
    }
    if (_messages.isEmpty) {
      return const Center(
        child: Padding(
          padding: AniHowSpace.screenPadding,
          child: Text(
            'No messages yet. Say hello about the handover.',
            textAlign: TextAlign.center,
          ),
        ),
      );
    }
    return ListView.builder(
      controller: _scroll,
      padding: AniHowSpace.screenPadding,
      itemCount: _messages.length,
      itemBuilder: (context, index) {
        final message = _messages[index];
        final mine = userId != null && message.authorId == userId;
        return Align(
          alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
          child: Container(
            margin: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
            padding: AniHowSpace.cardPadding,
            constraints: BoxConstraints(
              maxWidth: MediaQuery.sizeOf(context).width * 0.8,
            ),
            decoration: BoxDecoration(
              color: mine
                  ? Theme.of(context).colorScheme.primaryContainer
                  : Theme.of(context).colorScheme.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(AniHowSpace.radius),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  mine ? 'You' : message.authorName,
                  style: Theme.of(context).textTheme.labelMedium,
                ),
                const SizedBox(height: 4),
                Text(message.body),
                if (message.createdAt != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    relativeTime(message.createdAt),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}
