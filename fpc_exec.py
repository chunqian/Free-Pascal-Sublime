import sublime
import sublime_plugin
import re
import importlib
import os
defaultExec = importlib.import_module("Default.exec")

# regex patterns
pattern_compiling = re.compile("^Compiling (.*)/(\w+\.\w+)");
pattern_linking = re.compile("^Linking (.*)");
pattern_finished = re.compile("^(\d+) lines compiled, (.*)$");        
pattern_notes = re.compile("^(\d+) note.*issued$");       
pattern_error = re.compile("^(error): (.*)$");        
pattern_warning = re.compile("^(.*):([0-9]+): (warning|hint)+: (?:[0-9]+): (.*)$");       

# global compiler messages
# currently only warnings are produced
build_messages = dict()

def get_warning(file_name, index):
  if file_name in build_messages:
    if str(index) in build_messages[file_name]:
      return build_messages[file_name][str(index)]
  else:
    return None

def add_warning(view, file_name, line_num, message):
  line_pos = view.text_point(int(line_num) - 1, 0)
  line_region = view.line(line_pos)
  regions = view.get_regions("warning");
  view.add_regions("warning", regions + [line_region], "comment.warning", icon="dot", flags=sublime.DRAW_STIPPLED_UNDERLINE | sublime.DRAW_NO_FILL | sublime.DRAW_NO_OUTLINE)
  if not (file_name in build_messages):
    build_messages[file_name] = dict()
  key = str(len(regions));
  build_messages[file_name][key] = message

class FpcExecEventListener(sublime_plugin.EventListener):

    def show_warning_popup(self, view, message, point):
      body = """
          <body id=show-definitions>
              <style>
                  body {
                      font-family: system;
                  }
                  p {
                      font-size: 1.05rem;
                      margin: 0;
                  }
              </style>
              <p>%s</p>
          </body>
      """ % (message)

      view.show_popup(
          body,
          flags=sublime.HIDE_ON_MOUSE_MOVE_AWAY,
          location=point,
          max_width=1024)
      pass

    def on_hover(self, view, point, hover_zone):
      file_name = os.path.basename(view.file_name())
      print(file_name+" at "+str(view.rowcol(point)[0]))
      marks = view.get_regions("warning")
      for idx, region in enumerate(marks):
        print(marks)
        if region.contains(point):
          message = get_warning(file_name, idx)
          if message != None:
            self.show_warning_popup(view, message, point)


class FpcExecCommand(defaultExec.ExecCommand):
  done_building_message = "Building Finished"
  notes_issued = ""
  show_panel = 0

  def hide_phantoms(self):
    super(FpcExecCommand, self).hide_phantoms()
    # self.window.run_command("hide_panel", {"cancel": True})

    views = self.window.views()
    for view in views:
      view.erase_regions("warning")

  def on_finished(self, proc):
    super(FpcExecCommand, self).on_finished(proc)

    # show next erro
    errs = self.output_view.find_all_results()
    if len(errs) > 0:
      self.window.run_command("next_result")

    # show total note count
    if self.notes_issued != "":
      sublime.status_message(self.done_building_message+" ("+self.notes_issued+" notes issued)")
    else:
      sublime.status_message(self.done_building_message)

  def on_data(self, proc, data):
    try:
      characters = data.decode(self.encoding)
    except:
      characters = "[Decode error - output not " + self.encoding + "]\n"
      proc = None

    # log full line
    characters = characters.replace('\r\n', '\n').replace('\r', '\n')
    self.append_string(proc, characters)

    # https://forum.sublimetext.com/t/best-way-to-deal-with-highlighting-multi-line-function-definitions/47770/15
    # https://www.sublimetext.com/docs/3/api_reference.html#sublime.View
    
    show_warnings = sublime.load_settings("FPC.sublime-settings").get("show_warnings", False)
    if show_warnings:
      lines = characters.strip().split('\n')
      for line in lines:
        res = pattern_warning.match(line);
        if res:
          file_name = os.path.basename(res.groups()[0])
          line_num = res.groups()[1]
          message = res.groups()[3]

          view = self.window.find_open_file(file_name)
          add_warning(view, file_name, line_num, message)

    # error: Compilation aborted
    res = pattern_error.match(characters);
    if res:
      self.show_panel = True
      return

    res = pattern_compiling.match(characters);
    if res:
      sublime.status_message("Compiling "+res.groups()[1])
      return

    res = pattern_linking.match(characters);
    if res:
      sublime.status_message("Linking...")
      return

    res = pattern_finished.match(characters);
    if res:
      self.done_building_message = "Building finished -- "+characters
      return

    res = pattern_notes.match(characters);
    if res:
      self.notes_issued = res.groups()[0]
      return
