#include <flutter/dart_project.h>
#include <flutter/flutter_view_controller.h>
#include <windows.h>

#include "flutter_window.h"
#include "utils.h"

namespace {

// Pixel logical size so the desktop window matches the Android phone layout.
constexpr unsigned int kAndroidWidth = 412;
constexpr unsigned int kAndroidHeight = 915;
constexpr int kVerticalChrome = 96;
constexpr int kHorizontalMargin = 48;

void FrameAsAndroidPhone(HWND window) {
  LONG_PTR style = ::GetWindowLongPtr(window, GWL_STYLE);
  style &= ~(static_cast<LONG_PTR>(WS_THICKFRAME) |
             static_cast<LONG_PTR>(WS_MAXIMIZEBOX));
  ::SetWindowLongPtr(window, GWL_STYLE, style);

  UINT dpi = ::GetDpiForWindow(window);
  if (dpi == 0) {
    dpi = 96;
  }
  const double scale = static_cast<double>(dpi) / 96.0;

  // Width stays at the Pixel 7 width. Shrinking both sides made listing
  // cards narrower than the emulator, so titles wrapped onto extra lines.
  int client_width = static_cast<int>(kAndroidWidth * scale);
  int client_height = static_cast<int>(kAndroidHeight * scale);

  RECT work_area{};
  ::SystemParametersInfoW(SPI_GETWORKAREA, 0, &work_area, 0);
  const int available_width =
      (work_area.right - work_area.left) - kHorizontalMargin;
  const int available_height =
      (work_area.bottom - work_area.top) - kVerticalChrome;
  if (available_height > 0 && client_height > available_height) {
    client_height = available_height;
  }
  if (available_width > 0 && client_width > available_width) {
    client_width = available_width;
  }

  RECT frame{0, 0, client_width, client_height};
  const DWORD window_style =
      static_cast<DWORD>(::GetWindowLongPtr(window, GWL_STYLE));
  const DWORD extended_style =
      static_cast<DWORD>(::GetWindowLongPtr(window, GWL_EXSTYLE));
  ::AdjustWindowRectExForDpi(&frame, window_style, FALSE, extended_style, dpi);

  const int outer_width = frame.right - frame.left;
  const int outer_height = frame.bottom - frame.top;
  int origin_x =
      work_area.left + ((work_area.right - work_area.left) - outer_width) / 2;
  int origin_y =
      work_area.top + ((work_area.bottom - work_area.top) - outer_height) / 2;
  if (origin_x < work_area.left) {
    origin_x = work_area.left;
  }
  if (origin_y < work_area.top) {
    origin_y = work_area.top;
  }

  ::SetWindowPos(window, nullptr, origin_x, origin_y, outer_width, outer_height,
                 SWP_NOZORDER | SWP_NOACTIVATE | SWP_FRAMECHANGED);
}

}  // namespace

int APIENTRY wWinMain(_In_ HINSTANCE instance, _In_opt_ HINSTANCE prev,
                      _In_ wchar_t *command_line, _In_ int show_command) {
  // Attach to console when present (e.g., 'flutter run') or create a
  // new console when running with a debugger.
  if (!::AttachConsole(ATTACH_PARENT_PROCESS) && ::IsDebuggerPresent()) {
    CreateAndAttachConsole();
  }

  // Initialize COM, so that it is available for use in the library and/or
  // plugins.
  ::CoInitializeEx(nullptr, COINIT_APARTMENTTHREADED);

  flutter::DartProject project(L"data");

  std::vector<std::string> command_line_arguments =
      GetCommandLineArguments();

  project.set_dart_entrypoint_arguments(std::move(command_line_arguments));

  FlutterWindow window(project);
  Win32Window::Point origin(10, 10);
  Win32Window::Size size(kAndroidWidth, kAndroidHeight);
  if (!window.Create(L"anihow", origin, size)) {
    return EXIT_FAILURE;
  }
  window.SetQuitOnClose(true);
  FrameAsAndroidPhone(window.GetHandle());

  ::MSG msg;
  while (::GetMessage(&msg, nullptr, 0, 0)) {
    ::TranslateMessage(&msg);
    ::DispatchMessage(&msg);
  }

  ::CoUninitialize();
  return EXIT_SUCCESS;
}
