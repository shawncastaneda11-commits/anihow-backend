import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../services/stall_chat_realtime.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/async_view.dart';
import '../../widgets/chat_composer.dart';
import '../../widgets/chat_message_bubble.dart';
import '../../widgets/profile_avatar_button.dart';

class OrderChatScreen extends StatefulWidget {
  const OrderChatScreen({super.key, required this.order});

  final OrderRecord order;

  @override
  State<OrderChatScreen> createState() => _OrderChatScreenState();
}

class _OrderChatScreenState extends State<OrderChatScreen> {
  final _scroll = ScrollController();
  final _anchor = GlobalKey();
  final List<OrderMessage> _messages = [];
  StallChat? _chat;
  StallChatRealtime? _realtime;
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
    _poll = Timer.periodic(const Duration(seconds: 8), (_) => _pollNewer());
  }

  Future<StallChat?> _findChat() async {
    final auth = context.read<AuthController>();
    final api = auth.api;
    final sellerId = widget.order.sellerId;
    final viewingAsSeller = auth.user?.isFarmerSeller == true;

    if (!viewingAsSeller && sellerId != null) {
      return api.openStallChat(sellerId);
    }

    final chats = await api.stallChats();
    final buyerId = widget.order.buyerId;
    for (final chat in chats) {
      final sameBuyer = buyerId != null && chat.buyerId == buyerId;
      final sameSeller = sellerId == null || chat.sellerId == sellerId;
      if (sameBuyer && sameSeller) {
        return chat;
      }
    }

    return null;
  }

  Future<void> _listen(StallChat chat) async {
    if (_realtime != null) {
      return;
    }
    try {
      final realtime = StallChatRealtime(
        api: context.read<AuthController>().api,
        conversationId: chat.id,
      );
      _realtime = realtime;
      _liveSub = realtime.messages.listen(_appendIfNew);
      await realtime.connect();
    } catch (_) {
      // A dead websocket must not block the thread. Polling still runs.
    }
  }

  Future<void> _reload() async {
    setState(() {
      _loading = _messages.isEmpty;
      _error = null;
    });

    if (widget.order.isWalkIn) {
      if (!mounted) {
        return;
      }
      setState(() {
        _messages.clear();
        _loading = false;
        _error = null;
      });
      return;
    }

    try {
      final api = context.read<AuthController>().api;
      final chat = _chat ?? await _findChat();
      final items = chat == null
          ? const <OrderMessage>[]
          : await api.stallMessages(chat.id);
      if (!mounted) {
        return;
      }
      if (!mounted) {
        return;
      }
      setState(() {
        _chat = chat;
        _messages
          ..clear()
          ..addAll(items);
        _loading = false;
        _error = null;
      });
      _scrollToOrder();
      if (chat != null) {
        unawaited(_listen(chat));
      }
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error;
      });
    }
  }

  Future<void> _pollNewer() async {
    if (!mounted || widget.order.isWalkIn) {
      return;
    }
    try {
      final api = context.read<AuthController>().api;
      var chat = _chat;
      if (chat == null) {
        chat = await _findChat();
        if (chat == null || !mounted) {
          return;
        }
        if (!mounted) {
          return;
        }
        setState(() => _chat = chat);
        unawaited(_listen(chat));
      }

      final newer = await api.stallMessages(
        chat.id,
        afterId: _messages.isEmpty ? null : _messages.last.id,
      );
      for (final message in newer) {
        _appendIfNew(message);
      }
    } catch (_) {
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
    if (message.orderId == widget.order.id) {
      _scrollToOrder();
      return;
    }
    _scrollToEnd();
  }

  void _scrollToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scroll.hasClients) {
        return;
      }
      _scroll.jumpTo(_scroll.position.maxScrollExtent);
    });
  }

  void _scrollToOrder() {
    WidgetsBinding.instance.addPostFrameCallback((_) => _stepTowardAnchor(0));
  }

  void _stepTowardAnchor(int step) {
    if (!mounted || !_scroll.hasClients) {
      return;
    }
    final index = _messages.indexWhere(
      (message) => message.orderId == widget.order.id,
    );
    if (index < 0) {
      _scroll.jumpTo(_scroll.position.maxScrollExtent);
      return;
    }
    final anchorContext = _anchor.currentContext;
    if (anchorContext != null) {
      Scrollable.ensureVisible(anchorContext, alignment: 0.2);
      return;
    }
    if (step > 40) {
      return;
    }
    final max = _scroll.position.maxScrollExtent;
    final next = (_scroll.offset + _scroll.position.viewportDimension * 0.8)
        .clamp(0.0, max);
    if (next <= _scroll.offset) {
      return;
    }
    _scroll.jumpTo(next);
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => _stepTowardAnchor(step + 1),
    );
  }

  Future<StallChat?> _chatFromOrderThread(ApiClient api) async {
    final id = await api.orderStallConversationId(widget.order.id);
    if (id == null) {
      return null;
    }
    return StallChat(
      id: id,
      sellerId: widget.order.sellerId ?? 0,
      shopName: widget.order.stallName,
      buyerName: widget.order.buyerName,
      buyerId: widget.order.buyerId,
    );
  }

  Future<void> _send(String body, String? attachmentPath) async {
    final hasFile = attachmentPath != null && attachmentPath.isNotEmpty;
    if (!_canSend || _sending || (body.isEmpty && !hasFile)) {
      return;
    }
    final attachmentFailed = AppStrings.of(context).attachmentSendFailed;
    setState(() => _sending = true);
    try {
      final api = context.read<AuthController>().api;
      var chat = _chat ?? await _findChat();
      if (chat == null && hasFile) {
        chat = await _chatFromOrderThread(api);
      }
      if (chat == null) {
        if (hasFile) {
          throw ApiException(attachmentFailed);
        }
        await api.sendOrderMessage(widget.order.id, body: body);
        if (!mounted) {
          return;
        }
        _chat = null;
        await _reload();
        return;
      }
      _chat = chat;
      unawaited(_listen(chat));
      final message = await api.sendStallMessage(
        chat.id,
        body: body,
        orderId: widget.order.id,
        attachmentPath: hasFile ? attachmentPath : null,
      );
      if (!mounted) {
        return;
      }
      _appendIfNew(message);
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
      rethrow;
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
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user;
    final userId = user?.id;
    final s = AppStrings.of(context);
    final title = widget.order.chatPeerTitle(
      viewingAsSeller: user?.isFarmerSeller == true,
    );

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            AniHowAvatar(
              name: title,
              imageUrl: widget.order.chatPeerAvatar(
                viewingAsSeller: user?.isFarmerSeller == true,
              ),
              radius: 16,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(s.chatTitle(title), overflow: TextOverflow.ellipsis),
            ),
          ],
        ),
      ),
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
                          ? s.t(
                              'Walk-in sales have no buyer chat.',
                              'Walang chat ang walk-in sale.',
                            )
                          : s.t(
                              'This order is cancelled. Chat is read-only.',
                              'Kinansela ang order. Basahin lang ang chat.',
                            ),
                      style: Theme.of(context).textTheme.bodyMedium,
                    )
                  : ChatComposer(busy: _sending, onSend: _send),
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
      return Center(
        child: Padding(
          padding: AniHowSpace.screenPadding,
          child: Text(
            AppStrings.of(context).noMessages,
            textAlign: TextAlign.center,
          ),
        ),
      );
    }

    var anchored = false;
    return ListView.builder(
      controller: _scroll,
      padding: AniHowSpace.screenPadding,
      itemCount: _messages.length,
      itemBuilder: (context, index) {
        final message = _messages[index];
        final mine = userId != null && message.authorId == userId;
        final isAnchor = !anchored && message.orderId == widget.order.id;
        if (isAnchor) {
          anchored = true;
        }
        return ChatMessageBubble(
          key: isAnchor ? _anchor : ValueKey('stall-message-${message.id}'),
          message: message,
          mine: mine,
          showOrderTag: showsOrderTag(_messages, index),
        );
      },
    );
  }
}
