import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

/// Shared loading / empty / error chrome for a [Future] or [AsyncSnapshot].
class AsyncView<T> extends StatelessWidget {
  const AsyncView({
    super.key,
    required Future<T> this.future,
    required this.onRetry,
    required this.builder,
    this.isEmpty,
    this.emptyBuilder,
    this.emptyMessage,
  }) : snapshot = null;

  const AsyncView.snapshot({
    super.key,
    required AsyncSnapshot<T> this.snapshot,
    required this.onRetry,
    required this.builder,
    this.isEmpty,
    this.emptyBuilder,
    this.emptyMessage,
  }) : future = null;

  final Future<T>? future;
  final AsyncSnapshot<T>? snapshot;
  final VoidCallback onRetry;
  final Widget Function(BuildContext context, T data) builder;
  final bool Function(T data)? isEmpty;
  final WidgetBuilder? emptyBuilder;
  final String? emptyMessage;

  @override
  Widget build(BuildContext context) {
    final snapshot = this.snapshot;
    if (snapshot != null) {
      return _buildFromSnapshot(context, snapshot);
    }
    return FutureBuilder<T>(
      future: future,
      builder: _buildFromSnapshot,
    );
  }

  Widget _buildFromSnapshot(BuildContext context, AsyncSnapshot<T> snapshot) {
    if (snapshot.connectionState != ConnectionState.done) {
      return const Center(child: CircularProgressIndicator());
    }
    if (snapshot.hasError) {
      return AsyncViewError(onRetry: onRetry);
    }
    final data = snapshot.data;
    if (data == null || _isEmpty(data)) {
      return _buildEmpty(context);
    }
    return builder(context, data);
  }

  bool _isEmpty(T data) {
    if (isEmpty != null) {
      return isEmpty!(data);
    }
    return data is Iterable && data.isEmpty;
  }

  Widget _buildEmpty(BuildContext context) {
    if (emptyBuilder != null) {
      return emptyBuilder!(context);
    }
    return Center(
      child: Padding(
        padding: AniHowSpace.screenPadding,
        child: Text(
          emptyMessage ?? 'Nothing here yet.',
          style: Theme.of(context).textTheme.bodyLarge,
          textAlign: TextAlign.center,
        ),
      ),
    );
  }
}

class AsyncViewError extends StatelessWidget {
  const AsyncViewError({super.key, required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: AniHowSpace.screenPadding,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 48, color: AniHowColors.sage),
            const SizedBox(height: AniHowSpace.cardGap),
            Text(
              'Something went wrong.',
              style: theme.textTheme.titleMedium?.copyWith(color: AniHowColors.deepGreen),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AniHowSpace.section),
            FilledButton(
              onPressed: onRetry,
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}
