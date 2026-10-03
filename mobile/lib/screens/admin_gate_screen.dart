import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import '../widgets/app_header.dart';
import '../widgets/primary_button.dart';

class AdminGateScreen extends StatelessWidget {
  const AdminGateScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    final s = AppStrings.of(context);

    return Scaffold(
      body: Column(
        children: [
          AppHeader(
            title: s.superAdmin,
          ),
          Padding(
            padding: AniHowSpace.screenPadding,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  s.t(
                    'Open http://127.0.0.1:8000/admin on this computer.',
                    'Buksan ang http://127.0.0.1:8000/admin sa computer na ito.',
                  ),
                  style: Theme.of(context).textTheme.bodyLarge,
                ),
                const SizedBox(height: AniHowSpace.section),
                PrimaryButton(label: s.logOut, onPressed: auth.logout),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
