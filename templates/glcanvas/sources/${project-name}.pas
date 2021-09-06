{$mode objfpc}

program ${program-name};
uses
  CThreads, GeometryTypes, VectorMath,
  GLPT, GLCanvas;

const
  window_size_width = 512;
  window_size_height = 512;


procedure EventCallback(event: pGLPT_MessageRec);
begin
  case event^.mcode of
    GLPT_MESSAGE_KEYPRESS:
      case event^.params.keyboard.keycode of
        GLPT_KEY_SPACE:
          begin
          	writeln('space key down')
          end;
      end;
  end;
end;

begin
  SetupCanvas(window_size_width, window_size_height, @EventCallback);

  while IsRunning do
    begin
      ClearBackground;
      { ... draw stuff ... }
      SwapBuffers;
    end;

  QuitApp;
end.