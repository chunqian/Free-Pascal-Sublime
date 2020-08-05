import sublime
import sublime_plugin
import re
import importlib
import os
from functools import cmp_to_key

defaultExec = importlib.import_module("Default.exec")

# regex patterns
pattern_compiling = re.compile("^Compiling (.*)/(\w+\.\w+)");
pattern_linking = re.compile("^Linking (.*)");
pattern_finished = re.compile("^(\d+) lines compiled, (.*)$");
pattern_notes = re.compile("^(\d+) note.*issued$");  
pattern_error = re.compile("^(error): (.*)$");
# the error format has changed before, these are the 2 variants
# pattern_warning = re.compile("^(.*):([0-9]+): (warning|hint)+: (?:[0-9]+): (.*)$"); 
pattern_warning = re.compile("^(.*):([0-9]+):(?:[0-9]+): (warning|hint)+: (.*)$"); 

# global compiler messages
# currently only warnings are produced
build_messages = dict()

class Warning():
  message = ""
  line = 0
  def __str__(self):
    return self.message+":"+str(self.line)
  def __init__(self, message, line):
    self.message = message
    self.line = line

def get_warning(file_name, idx):
  global build_messages
  if file_name in build_messages:
    return build_messages[file_name][idx]
  else:
    return None

def clear_warnings(window):
  global build_messages
  for view in window.views():
    view.erase_regions("warning")
  build_messages = dict()

def add_warning(view, file_name, line_num, message):
  global build_messages
  line_pos = view.text_point(line_num - 1, 0)
  line_region = view.line(line_pos)
  regions = view.get_regions("warning");
  view.add_regions("warning", regions + [line_region], "comment.warning", icon="dot", flags=sublime.DRAW_STIPPLED_UNDERLINE | sublime.DRAW_NO_FILL | sublime.DRAW_NO_OUTLINE)
  if not (file_name in build_messages):
    build_messages[file_name] = list()
  items = build_messages[file_name]
  warning = Warning(message, line_num)

  for i in range(0, len(items)):
    if items[i].line == warning.line:
      # TODO: there are multiple messages at this line. how do we display them all?
      # items[i].message += "\n"+warning.message
      return
  items.append(warning)

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

    def on_load(self, view):
      # TODO: save warnings in prefs and restore when files open
      # file_name = os.path.basename(view.file_name())
      # print("loaded "+file_name)
      # self.view.settings().set("_sel_text", text)
      # text = view.settings().get("_sel_text")
      pass

    def on_hover(self, view, point, hover_zone):
      # TOOD: view can be None?
      if view is None:
        return
      if view.file_name() is None:
        return
      file_name = os.path.basename(view.file_name())

      marks = view.get_regions("warning")
      for idx, region in enumerate(marks):
        if region.contains(point):
          warning = get_warning(file_name, idx)
          # print("region #"+str(idx)+" at "+file_name+":"+str(view.rowcol(point)[0] + 1))
          if warning != None:
            self.show_warning_popup(view, warning.message, point)

class FpcExecCommand(defaultExec.ExecCommand):
  done_building_message = "Building Finished"
  notes_issued = ""
  show_panel = 0

  def hide_annotations(self):
    super(FpcExecCommand, self).hide_annotations()
    clear_warnings(self.window)

  # NOTE: hide_phantoms is deprecated by hide_annotations in ST4
  def hide_phantoms(self):
    super(FpcExecCommand, self).hide_phantoms()
    clear_warnings(self.window)

  def on_finished(self, proc):
    global build_messages

    super(FpcExecCommand, self).on_finished(proc)

    # sort warnings by line number
    for file_name in build_messages:
      if build_messages[file_name] != None:
        items = build_messages[file_name]
        items.sort(key=lambda warning: warning.line, reverse=False)
        build_messages[file_name] = items

    # DEBUGGING
    for file_name in build_messages:
      if build_messages[file_name] != None:
        items = build_messages[file_name]
        for i in range(0, len(items)):
          print(file_name+" "+str(i)+" -> "+str(items[i]))
          pass
          
    # show next error
    errs = self.output_view.find_all_results()
    if len(errs) > 0:
      self.window.run_command("next_result")

    # show total note count
    if self.notes_issued != "":
      sublime.status_message(self.done_building_message+" ("+self.notes_issued+" notes issued)")
    else:
      sublime.status_message(self.done_building_message)

  def on_data(self, proc, data):
    
    # NOTE: this was required for an old version of ST3
    # try:
    #   characters = data.decode(self.encoding)
    # except:
    #   characters = "[Decode error - output not " + self.encoding + "]\n"
    #   proc = None
    characters = data

    # log full line
    characters = characters.replace('\r\n', '\n').replace('\r', '\n')
    if int(sublime.version()) <= 3211:
      self.append_string(proc, characters)
    else:
      self.write(characters)
    
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
          if view != None:
            add_warning(view, file_name, int(line_num), message)

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
