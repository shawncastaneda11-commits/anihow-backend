import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../screens/buyer/buyer_order_detail_screen.dart';
import '../screens/buyer/listing_detail_screen.dart';
import '../screens/farmer/farmer_orders_screen.dart';
import '../state/auth_controller.dart';
import '../support/relative_time.dart';
import '../theme/anihow_space.dart';

bool showsOrderTag(List<OrderMessage> messages, int index) {
  final current = messages[index].orderId;
  if (current == null) {
    return false;
  }
  if (index == 0) {
    return true;
  }
  return messages[index - 1].orderId != current;
}

class ChatMessageBubble extends StatelessWidget {
  const ChatMessageBubble({
    super.key,
    required this.message,
    required this.mine,
    this.showOrderTag = true,
  });

  final OrderMessage message;
  final bool mine;
  final bool showOrderTag;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);

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
              mine ? s.you : message.authorName,
              style: Theme.of(context).textTheme.labelMedium,
            ),
            if (showOrderTag && message.orderId != null) ...[
              const SizedBox(height: 4),
              TextButton(
                key: ValueKey('order-tag-${message.id}'),
                style: TextButton.styleFrom(
                  minimumSize: const Size(48, 48),
                  padding: EdgeInsets.zero,
                  tapTargetSize: MaterialTapTargetSize.padded,
                  alignment: Alignment.centerLeft,
                ),
                onPressed: () => _openTaggedOrder(context, message.orderId!),
                child: Text(s.orderTag(message.orderNumber)),
              ),
            ],
            if (message.hasProductCard) ...[
              const SizedBox(height: 8),
              _ProductCard(message: message),
            ],
            if (message.attachment != null) ...[
              const SizedBox(height: 8),
              message.attachment!.isPhoto
                  ? _PhotoAttachment(message: message)
                  : _PdfAttachment(
                      messageId: message.id,
                      attachment: message.attachment!,
                    ),
            ],
            if (message.body.trim().isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(message.body),
            ],
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
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({required this.message});

  final OrderMessage message;

  @override
  Widget build(BuildContext context) {
    final linked = message.listingId != null;
    final card = ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 48),
      child: Row(
        children: [
          _Thumbnail(message: message),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(message.listingTitle ?? ''),
                Text(
                  [message.listingPrice, message.listingUnit]
                      .whereType<String>()
                      .where((part) => part.isNotEmpty)
                      .join(' / '),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ],
      ),
    );

    return KeyedSubtree(
      key: ValueKey('listing-card-${message.id}'),
      child: linked
          ? InkWell(
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) =>
                        ListingDetailScreen(listingId: message.listingId!),
                  ),
                );
              },
              child: card,
            )
          : card,
    );
  }
}

class _Thumbnail extends StatelessWidget {
  const _Thumbnail({required this.message});

  final OrderMessage message;

  @override
  Widget build(BuildContext context) {
    final url = message.listingThumbnailUrl;
    if (url == null || url.isEmpty) {
      return _placeholder(message.id);
    }

    return Image.network(
      url,
      width: 48,
      height: 48,
      fit: BoxFit.cover,
      errorBuilder: (_, _, _) => _placeholder(message.id),
    );
  }

  Widget _placeholder(int id) {
    return SizedBox(
      key: ValueKey('listing-placeholder-$id'),
      width: 48,
      height: 48,
      child: const Icon(Icons.image_outlined),
    );
  }
}

class _PhotoAttachment extends StatelessWidget {
  const _PhotoAttachment({required this.message});

  final OrderMessage message;

  @override
  Widget build(BuildContext context) {
    final attachment = message.attachment!;
    final preview = attachment.thumbnailUrl ?? attachment.url;

    return GestureDetector(
      key: ValueKey('chat-photo-${message.id}'),
      onTap: () {
        Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => _FullScreenPhoto(url: attachment.url),
          ),
        );
      },
      child: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: SizedBox(
          width: 160,
          height: 160,
          child: AuthorizedChatImage(url: preview),
        ),
      ),
    );
  }
}

class _FullScreenPhoto extends StatelessWidget {
  const _FullScreenPhoto({required this.url});

  final String url;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      key: const Key('chat-photo-viewer'),
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
      ),
      body: Center(
        child: AuthorizedChatImage(url: url, fit: BoxFit.contain),
      ),
    );
  }
}

class AuthorizedChatImage extends StatefulWidget {
  const AuthorizedChatImage({
    super.key,
    required this.url,
    this.fit = BoxFit.cover,
  });

  final String url;
  final BoxFit fit;

  @override
  State<AuthorizedChatImage> createState() => _AuthorizedChatImageState();
}

class _AuthorizedChatImageState extends State<AuthorizedChatImage> {
  Future<Uint8List?>? _bytes;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _bytes ??= _load();
  }

  Future<Uint8List?> _load() async {
    try {
      final bytes = await context.read<AuthController>().api.downloadAuthorized(
        widget.url,
      );
      if (bytes.isEmpty) {
        return null;
      }
      return Uint8List.fromList(bytes);
    } catch (_) {
      return null;
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Uint8List?>(
      future: _bytes,
      builder: (context, snapshot) {
        final data = snapshot.data;
        if (data == null) {
          return const Center(child: Icon(Icons.image_outlined));
        }
        return Image.memory(
          data,
          fit: widget.fit,
          width: double.infinity,
          height: double.infinity,
        );
      },
    );
  }
}

class _PdfAttachment extends StatelessWidget {
  const _PdfAttachment({required this.messageId, required this.attachment});

  final int messageId;
  final ChatAttachment attachment;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 48,
      child: InkWell(
        key: ValueKey('chat-pdf-$messageId'),
        onTap: () => _openPdf(context, attachment),
        child: Row(
          children: [
            const Icon(Icons.picture_as_pdf_outlined),
            const SizedBox(width: 8),
            Expanded(
              child: Text(attachment.name, overflow: TextOverflow.ellipsis),
            ),
            Text(formatChatAttachmentSize(attachment.size)),
          ],
        ),
      ),
    );
  }
}

void _openTaggedOrder(BuildContext context, int orderId) {
  final seller = context.read<AuthController>().user?.isFarmerSeller ?? false;
  Navigator.of(context).push(
    MaterialPageRoute<void>(
      builder: (_) => seller
          ? FarmerOrderDetailScreen(orderId: orderId)
          : BuyerOrderDetailScreen(orderId: orderId),
    ),
  );
}

String formatChatAttachmentSize(int bytes) {
  if (bytes < 1024) {
    return '$bytes B';
  }
  if (bytes < 1024 * 1024) {
    return '${(bytes / 1024).round()} KB';
  }
  return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
}

Future<void> _openPdf(BuildContext context, ChatAttachment attachment) async {
  final s = AppStrings.of(context);
  try {
    final bytes = await context.read<AuthController>().api.downloadAuthorized(
      attachment.url,
    );
    final safe = attachment.name.replaceAll(RegExp(r'[\\/:*?"<>|]'), '_');
    final file = File(
      '${Directory.systemTemp.path}${Platform.pathSeparator}$safe',
    );
    await file.writeAsBytes(bytes, flush: true);
    final opened = await launchUrl(
      Uri.file(file.path),
      mode: LaunchMode.externalApplication,
    );
    if (!opened && context.mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(s.attachmentOpenFailed)));
    }
  } catch (_) {
    if (context.mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(s.attachmentOpenFailed)));
    }
  }
}
