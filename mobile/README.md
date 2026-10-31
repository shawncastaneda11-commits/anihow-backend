# AniHow mobile app

The Flutter (Android) app for buyers and farmer-sellers.

Setup, running on the emulator, building the APK and the test accounts are all in the main [README](../README.md):

- [Run the app on the emulator](../README.md#5-run-the-app-on-the-emulator)
- [Build the Android app](../README.md#build-the-android-app)
- [Test copy (staging)](../README.md#test-copy-staging)

Server addresses for release builds live in `dart_defines/` (`vps.json` for the live server, `staging.json` for the test copy).

Checks that run on every pull request:

```bash
flutter analyze
flutter test
```
