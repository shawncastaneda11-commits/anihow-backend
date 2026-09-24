import 'package:anihow/config/api_config.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('configurationProblem is null in debug with no defines', () {
    expect(ApiConfig.configurationProblem(), isNull);
    expect(ApiConfig.configurationProblem(isRelease: false), isNull);
    expect(ApiConfig.reverbPort, 8080);
    expect(ApiConfig.reverbScheme, 'ws');
  });

  test('configurationProblem flags each missing release define', () {
    expect(
      ApiConfig.configurationProblem(
        isRelease: true,
        apiBaseUrl: '',
        reverbHost: 'ws.example.com',
        reverbScheme: 'wss',
      ),
      'API_BASE_URL is empty',
    );
    expect(
      ApiConfig.configurationProblem(
        isRelease: true,
        apiBaseUrl: 'http://api.example.com',
        reverbHost: 'ws.example.com',
        reverbScheme: 'wss',
      ),
      'API_BASE_URL must start with https://',
    );
    expect(
      ApiConfig.configurationProblem(
        isRelease: true,
        apiBaseUrl: 'https://api.example.com',
        reverbHost: '',
        reverbScheme: 'wss',
      ),
      'REVERB_HOST is empty',
    );
    expect(
      ApiConfig.configurationProblem(
        isRelease: true,
        apiBaseUrl: 'https://api.example.com',
        reverbHost: 'ws.example.com',
        reverbScheme: 'ws',
      ),
      'REVERB_SCHEME must be wss',
    );
    expect(
      ApiConfig.configurationProblem(
        isRelease: true,
        apiBaseUrl: 'https://api.example.com',
        reverbHost: 'ws.example.com',
        reverbScheme: 'wss',
      ),
      isNull,
    );
  });

  test('reverb port defaults to 443 for wss and 8080 for ws', () {
    expect(ApiConfig.resolveReverbPort(portRaw: '', scheme: 'wss'), 443);
    expect(ApiConfig.resolveReverbPort(portRaw: '', scheme: 'ws'), 8080);
    expect(ApiConfig.resolveReverbPort(portRaw: '6001', scheme: 'wss'), 6001);
    expect(ApiConfig.resolveReverbPort(portRaw: ' 443 ', scheme: 'ws'), 443);
  });
}
