/// Digits a dialer can open. Keeps a leading + and drops spaces and dashes.
String? dialablePhone(String? number) {
  if (number == null) {
    return null;
  }
  final digits = number.replaceAll(RegExp(r'[^\d+]'), '');
  if (digits.isEmpty) {
    return null;
  }
  return digits;
}
