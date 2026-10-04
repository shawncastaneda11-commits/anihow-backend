import 'dart:io';

import 'package:anihow/models/models.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/chat_composer.dart';
import 'package:anihow/widgets/chat_message_bubble.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Widget _app(Widget child) {
  return MaterialApp(
    theme: AniHowTheme.light(),
    home: Scaffold(body: child),
  );
}

OrderMessage _message({
  required int id,
  String body = '',
  ChatAttachment? attachment,
}) {
  return OrderMessage(
    id: id,
    body: body,
    authorId: 8,
    authorName: 'Nena',
    attachment: attachment,
  );
}

void main() {
  testWidgets('a photo bubble opens the full image', (tester) async {
    await tester.pumpWidget(
      _app(
        ChatMessageBubble(
          mine: false,
          message: _message(
            id: 4,
            attachment: const ChatAttachment(
              name: 'tomato.jpg',
              mime: 'image/jpeg',
              size: 1200,
              url: 'https://example.com/api/chat/attachments/4',
              thumbnailUrl:
                  'https://example.com/api/chat/attachments/4?variant=thumb',
            ),
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.byKey(const ValueKey('chat-photo-4')), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('chat-photo-4')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('chat-photo-viewer')), findsOneWidget);
  });

  testWidgets('a PDF chip shows the file name and size', (tester) async {
    await tester.pumpWidget(
      _app(
        ChatMessageBubble(
          mine: true,
          message: _message(
            id: 9,
            attachment: const ChatAttachment(
              name: 'handover.pdf',
              mime: 'application/pdf',
              size: 2048,
              url: 'https://example.com/api/chat/attachments/9',
            ),
          ),
        ),
      ),
    );

    expect(find.byKey(const ValueKey('chat-pdf-9')), findsOneWidget);
    expect(find.text('handover.pdf'), findsOneWidget);
    expect(find.text('2 KB'), findsOneWidget);
  });

  testWidgets('send is enabled with only an attachment', (tester) async {
    final file = _tempFile('anihow-note.pdf', 4);
    addTearDown(file.deleteSync);

    await tester.pumpWidget(
      _app(
        ChatComposer(
          onSend: (_, _) async {},
          pick: (_) async => PendingChatAttachment(
            path: file.path,
            name: 'handover.pdf',
            isPdf: true,
          ),
        ),
      ),
    );

    FilledButton send() =>
        tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'Send'));
    expect(send().onPressed, isNull);

    await tester.tap(find.byKey(const Key('chat-attach')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('chat-attach-pdf')));
    await tester.pumpAndSettle();

    expect(find.text('handover.pdf'), findsOneWidget);
    expect(send().onPressed, isNotNull);
  });

  testWidgets('a file over 5 MB is refused', (tester) async {
    final file = _tempFile('anihow-oversize.bin', chatAttachmentMaxBytes + 1);
    addTearDown(file.deleteSync);

    await tester.pumpWidget(
      _app(
        ChatComposer(
          onSend: (_, _) async {},
          pick: (_) async => PendingChatAttachment(
            path: file.path,
            name: 'too-big.pdf',
            isPdf: true,
          ),
        ),
      ),
    );

    await tester.tap(find.byKey(const Key('chat-attach')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('chat-attach-pdf')));
    await tester.pumpAndSettle();

    expect(find.text('This file is over 5 MB.'), findsOneWidget);
    final send = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Send'),
    );
    expect(send.onPressed, isNull);
  });
}

File _tempFile(String name, int bytes) {
  final file = File(
    '${Directory.systemTemp.path}${Platform.pathSeparator}$name',
  );
  final handle = file.openSync(mode: FileMode.write);
  handle.truncateSync(bytes);
  handle.closeSync();
  return file;
}
