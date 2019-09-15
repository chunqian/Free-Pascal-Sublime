import sublime
import sublime_plugin
import re
import importlib
defaultExec = importlib.import_module("Default.exec")


class FpcExecCommand(defaultExec.ExecCommand):
	done_building_message = "Building Finished"
	notes_issued = ""
	show_panel = 0

	# ugle hack to hide panel before running
	# if we override run() we could avoid this
	def hide_phantoms(self):
		super(FpcExecCommand, self).hide_phantoms()
		# self.window.run_command("hide_panel", {"cancel": True})

	def on_finished(self, proc):
		super(FpcExecCommand, self).on_finished(proc)

		# why does this keep showing???
		#if self.show_panel:
		# self.window.run_command("show_panel", {"panel": "output.exec"})

		errs = self.output_view.find_all_results()
		if len(errs) > 0:
			#print(errs)
			#self.window.run_command("show_panel", {"panel": "output.exec"})
			self.window.run_command("next_result")

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

		# parse status message
		pattern_compiling = re.compile("^Compiling (.*)/(\w+\.\w+)");
		pattern_linking = re.compile("^Linking (.*)");
		pattern_finished = re.compile("^(\d+) lines compiled, (.*)$");				
		pattern_notes = re.compile("^(\d+) note.*issued$");				
		pattern_error = re.compile("^(error): (.*)$");				

		# error: Compilation aborted
		res = pattern_error.match(characters);
		if res:
			self.show_panel = True
			return

		# warnings also?
		#2 warning(s) issued

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
