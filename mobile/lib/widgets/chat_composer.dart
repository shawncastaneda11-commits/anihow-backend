import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../l10n/app_strings.dart';
import 'primary_button.dart';

const chatAttachmentMaxBytes = 5 * 1024 * 1024;

enum ChatAttachChoice { photo, camera, pdf }

class PendingChatAttachment {
  const PendingChatAttachment({
    required this.path,
    required this.name,
    required this.isPdf,
  });

  final String path;
  final String name;
  final bool isPdf;
}

typedef ChatAttachmentPicker = Future<PendingChatAttachment?> Function(
  ChatAttachChoice choice,
);

class ChatComposer extends StatefulWidget {
  const ChatComposer({
    super.key,
    required this.onSend,
    this.busy = false,
    this.pick,
  });

  final Future<void> Function(String body, String? attachmentPath) onSend;
  final bool busy;
  final ChatAttachmentPicker? pick;

  @override
  State<ChatComposer> createState() => _ChatComposerState();
}

class _ChatComposerState extends State<ChatComposer> {
  final _input = TextEditingController();
  PendingChatAttachment? _pending;
  bool _submitting = false;

  @override
  void dispose() {
    _input.dispose();
    super.dispose();
  }

  bool get _canSend {
    if (widget.busy || _submitting) {
      return false;
    }
    return _input.text.trim().isNotEmpty || _pending != null;
  }

  Future<void> _openSheet() async {
    final choice = await showModalBottomSheet<ChatAttachChoice>(
      context: context,
      builder: (context) {
        final s = AppStrings.of(context);
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              _sheetChoice(
                sheetContext: context,
                key: const Key('chat-attach-photo'),
                icon: Icons.photo_library_outlined,
                label: s.attachPhoto,
                choice: ChatAttachChoice.photo,
              ),
              _sheetChoice(
                sheetContext: context,
                key: const Key('chat-attach-camera'),
                icon: Icons.photo_camera_outlined,
                label: s.attachCamera,
                choice: ChatAttachChoice.camera,
              ),
              _sheetChoice(
                sheetContext: context,
                key: const Key('chat-attach-pdf'),
                icon: Icons.picture_as_pdf_outlined,
                label: s.attachPdf,
                choice: ChatAttachChoice.pdf,
              ),
            ],
          ),
        );
      },
    );
    if (choice == null || !mounted) {
      return;
    }
    final picked = await (widget.pick ?? _defaultPick)(choice);
    if (picked == null || !mounted) {
      return;
    }
    final length = File(picked.path).lengthSync();
    if (!mounted) {
      return;
    }
    if (length > chatAttachmentMaxBytes) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppStrings.of(context).attachmentTooLarge)),
      );
      return;
    }
    setState(() => _pending = picked);
  }

  Widget _sheetChoice({
    required BuildContext sheetContext,
    required Key key,
    required IconData icon,
    required String label,
    required ChatAttachChoice choice,
  }) {
    return SizedBox(
      height: 48,
      child: ListTile(
        key: key,
        leading: Icon(icon),
        title: Text(label),
        onTap: () => Navigator.pop(sheetContext, choice),
      ),
    );
  }

  Future<PendingChatAttachment?> _defaultPick(ChatAttachChoice choice) async {
    if (choice == ChatAttachChoice.pdf) {
      final file = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: const ['pdf'],
      );
      final path = file?.path;
      if (file == null || path == null) {
        return null;
      }
      return PendingChatAttachment(path: path, name: file.name, isPdf: true);
    }

    final picked = await ImagePicker().pickImage(
      source: choice == ChatAttachChoice.camera
          ? ImageSource.camera
          : ImageSource.gallery,
      maxWidth: 1600,
      maxHeight: 1600,
      imageQuality: 80,
    );
    if (picked == null) {
      return null;
    }
    return PendingChatAttachment(
      path: picked.path,
      name: picked.name,
      isPdf: false,
    );
  }

  Future<void> _submit() async {
    if (!_canSend) {
      return;
    }
    final body = _input.text.trim();
    final path = _pending?.path;
    setState(() => _submitting = true);
    try {
      await widget.onSend(body, path);
    } catch (_) {
      return;
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
    if (!mounted) {
      return;
    }
    _input.clear();
    setState(() => _pending = null);
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final pending = _pending;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (pending != null)
          Row(
            children: [
              const Icon(Icons.attach_file),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  pending.name,
                  key: const Key('chat-pending-attachment'),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              SizedBox(
                width: 48,
                height: 48,
                child: IconButton(
                  key: const Key('chat-remove-attachment'),
                  tooltip: s.remove,
                  onPressed: widget.busy || _submitting
                      ? null
                      : () => setState(() => _pending = null),
                  icon: const Icon(Icons.close),
                ),
              ),
            ],
          ),
        Row(
          children: [
            SizedBox(
              width: 48,
              height: 48,
              child: IconButton(
                key: const Key('chat-attach'),
                tooltip: s.attachFile,
                onPressed: widget.busy || _submitting ? null : _openSheet,
                icon: const Icon(Icons.attach_file),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: TextField(
                controller: _input,
                minLines: 1,
                maxLines: 4,
                maxLength: 1000,
                decoration: InputDecoration(
                  hintText: s.sendMessageHint,
                  counterText: '',
                ),
                textInputAction: TextInputAction.send,
                onSubmitted: (_) => _submit(),
              ),
            ),
            const SizedBox(width: 8),
            ListenableBuilder(
              listenable: _input,
              builder: (context, _) {
                return PrimaryButton(
                  label: s.send,
                  busy: widget.busy || _submitting,
                  expand: false,
                  onPressed: _canSend ? _submit : null,
                );
              },
            ),
          ],
        ),
      ],
    );
  }
}
