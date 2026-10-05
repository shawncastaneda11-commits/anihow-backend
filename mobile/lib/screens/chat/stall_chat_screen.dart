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

/// A conversation with a stall that stays after any order is finished.
class StallChatScreen extends StatefulWidget {
  const StallChatScreen({
    super.key,
    required this.chat,
    this.viewingAsSeller = false,
    this.attachListingId,
  });

  final StallChat chat;
  final bool viewingAsSeller;
  final int? attachListingId;

  @override
  State<StallChatScreen> createState() => _StallChatScreenState();
}

class _StallChatScreenState extends State<StallChatScreen> {
  final _scroll = ScrollController();
  final List<OrderMessage> _messages = [];
  StallChatRealtime? _realtime;
  StreamSubscription<OrderMessage>? _liveSub;
  Timer? _poll;
  bool _loading = true;
  Object? _error;
  bool _sending = false;
  int? _pendingListingId;

  @override
  void initState() {
    super.initState();
    _pendingListingId = widget.attachListingId;
    _reload();
    _startRealtime();
    _poll = Timer.periodic(const Duration(seconds: 8), (_) => _pollNewer());
  }

  Future<void> _startRealtime() async {
    final api = context.read<AuthController>().api;
    final realtime = StallChatRealtime(
      api: api,
      conversationId: widget.chat.id,
    );
    _realtime = realtime;
    _liveSub = realtime.messages.listen(_appendIfNew);
    await realtime.connect();
  }

  Future<void> _reload() async {
    setState(() {
      _loading = _messages.isEmpty;
      _error = null;
    });
    try {
      final items = await context.read<AuthController>().api.stallMessages(
        widget.chat.id,
      );
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

  Future<void> _pollNewer() async {
    if (!mounted) {
      return;
    }
    try {
      final newer = await context.read<AuthController>().api.stallMessages(
        widget.chat.id,
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
    if (!mounted || _messages.any((item) => item.id == message.id)) {
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

  Future<void> _send(String body, String? attachmentPath) async {
    final hasFile = attachmentPath != null && attachmentPath.isNotEmpty;
    if (_sending || (body.isEmpty && !hasFile)) {
      return;
    }
    setState(() => _sending = true);
    try {
      final message = await context.read<AuthController>().api.sendStallMessage(
        widget.chat.id,
        body: body,
        listingId: _pendingListingId,
        attachmentPath: hasFile ? attachmentPath : null,
      );
      if (!mounted) {
        return;
      }
      _pendingListingId = null;
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
    final userId = context.watch<AuthController>().user?.id;
    final s = AppStrings.of(context);
    final title = widget.chat.title(viewingAsSeller: widget.viewingAsSeller);

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            AniHowAvatar(
              name: title,
              imageUrl: widget.chat.avatarUrl(
                viewingAsSeller: widget.viewingAsSeller,
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
              child: ChatComposer(busy: _sending, onSend: _send),
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
    return ListView.builder(
      controller: _scroll,
      padding: AniHowSpace.screenPadding,
      itemCount: _messages.length,
      itemBuilder: (context, index) {
        final message = _messages[index];
        final mine = userId != null && message.authorId == userId;
        return ChatMessageBubble(
          message: message,
          mine: mine,
          showOrderTag: showsOrderTag(_messages, index),
        );
      },
    );
  }
}
