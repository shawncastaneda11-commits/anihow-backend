/// Hand-built mailto. [Uri.encodeComponent] keeps spaces as %20.
/// [Uri] query parameters would turn those spaces into +.
String sellerMailto({
  required String email,
  required String subject,
  required String body,
}) {
  return 'mailto:$email?subject=${Uri.encodeComponent(subject)}&body=${Uri.encodeComponent(body)}';
}

const sellerAccountSubject = 'AniHow seller account request';
const farmAccountSubject = 'AniHow farm account request';
const helpEmailSubject = 'AniHow help';

const sellerAccountBody = 'Full name:\nFarm:\nMobile number:\nEmail:\n';
const farmAccountBody = 'Farm name:\nLocation:\nContact person:\nMobile number:\n';
