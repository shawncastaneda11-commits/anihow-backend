import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'screens/admin_gate_screen.dart';
import 'screens/buyer/buyer_shell.dart';
import 'screens/farmer/farmer_shell.dart';
import 'screens/login_screen.dart';
import 'navigation/route_observer.dart';
import 'state/auth_controller.dart';
import 'state/preferences_controller.dart';
import 'state/theme_controller.dart';
import 'theme/anihow_theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final theme = ThemeController();
  final preferences = PreferencesController();
  await theme.load();
  await preferences.load();
  runApp(AniHowApp(theme: theme, preferences: preferences));
}

class AniHowApp extends StatefulWidget {
  const AniHowApp({super.key, required this.theme, required this.preferences});

  final ThemeController theme;
  final PreferencesController preferences;

  @override
  State<AniHowApp> createState() => _AniHowAppState();
}

class _AniHowAppState extends State<AniHowApp> {
  final AuthController _auth = AuthController();

  @override
  void initState() {
    super.initState();
    _auth.restoreSession();
  }

  @override
  void dispose() {
    _auth.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: _auth),
        ChangeNotifierProvider.value(value: widget.theme),
        ChangeNotifierProvider.value(value: widget.preferences),
      ],
      child: Consumer<ThemeController>(
        builder: (context, theme, _) {
          return MaterialApp(
            title: 'AniHow',
            theme: AniHowTheme.light(),
            darkTheme: AniHowTheme.dark(),
            themeMode: theme.mode,
            navigatorObservers: [anihowRouteObserver],
            home: const _RoleGate(),
          );
        },
      ),
    );
  }
}

class _RoleGate extends StatelessWidget {
  const _RoleGate();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    if (auth.restoring) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    final user = auth.user;
    if (user == null) {
      return const LoginScreen();
    }
    if (user.isFarmerSeller) {
      return const FarmerShell();
    }
    if (user.isBuyer) {
      return const BuyerShell();
    }
    return const AdminGateScreen();
  }
}
