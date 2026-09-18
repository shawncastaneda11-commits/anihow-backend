import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import '../widgets/app_header.dart';
import '../widgets/primary_button.dart';

class AdminGateScreen extends StatelessWidget {
  const AdminGateScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();

    return Scaffold(
      body: Column(
        children: [
          const AppHeader(
            title: 'Super admin',
          ),
          Padding(
            padding: AniHowSpace.screenPadding,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Open http://127.0.0.1:8000/admin on this computer.',
                  style: Theme.of(context).textTheme.bodyLarge,
                ),
                const SizedBox(height: AniHowSpace.section),
                PrimaryButton(label: 'Log out', onPressed: auth.logout),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
