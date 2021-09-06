import sublime
import sublime_plugin
import re
import webbrowser
import urllib.request
import sys
import xml.etree.ElementTree as ET

# http://www.sublimetext.com/docs/api_reference.html

# regex patterns
pattern_func = re.compile("^\s*(procedure|function)+\s+(\w+)")
pattern_meth_decl = re.compile("^\s*(class)*\s*(procedure|function|constructor|destructor)+\s+(\w+)")
pattern_method = re.compile("^\s*(class)*\s*(procedure|function|constructor|destructor)+\s+(\w+)\.(\w+)")
pattern_class = re.compile("^\s*(generic)*\s*(\w+)\s*(<\w+>)*\s*=\s*(class|object|objcclass|objccategory|objcprotocol|interface|record)\s*(external|abstract|sealed)*\s*(name)*\s*('\w+')*\s*(\((.*)\))*\s*(\['(.*)'\])*$")
pattern_helper = re.compile("^\s*(\w+)\s*=\s*((class|type|record)+\s+helper)\s+for\s+(\w+)")

# utilties
def line_at(view, point):
		return view.rowcol(point)[0]

def line_str(view, num):
		pt = view.text_point(num, 0)
		line = view.full_line(pt)
		return view.substr(line)

def line_begin(view, num):
		pt = view.text_point(num, 0)
		return view.rowcol(pt)[0]

def line_at_sel(view):
	sel = view.sel()[0]
	return line_at(view, sel.begin())

def sel_line(view, num):
	pt = view.text_point(num, 0)
	view.sel().clear()
	view.sel().add(sublime.Region(pt))
	view.show(pt)
	pass

def last_line (view):
	return line_at(view, view.size())

def match_selector (view, line, scope):
	return view.match_selector(view.text_point(line, 0), scope)

def goto_line(view, text):
		sel_line(view, int(text))
		view.hide_popup()

class GotoFunctionImplementationCommand(sublime_plugin.TextCommand):
		
		def run(self, edit):
				# region = self.view.sel()[0].b
				line = line_at_sel(self.view)

				# TODO: this is all broken since the new syntax definition
				sel = self.view.sel()[0]
				rowcol = self.view.rowcol(sel.begin())
				text_point = self.view.text_point(rowcol[0], rowcol[1])
				print(rowcol)
				print(text_point)
				score = self.view.score_selector(text_point, "meta.scope.struct entity.name.method")
				print("  score: "+str(score))

				if self.view.score_selector(text_point, "meta.scope.struct entity.name.method") > 0:
					print("GOTO METH IMPL")
					return

					# if self.goto_meth_impl(line, "meta.scope.method.pascal"):
					# 		return
	
				# if match_selector(self.view, line, "meta.scope.method.pascal"):
				# 		if self.goto_meth_impl(line, "meta.scope.method.pascal"):
				# 				return
				# elif match_selector(self.view, line, "meta.scope.helper.pascal"):
				# 		if self.goto_meth_impl(line, "meta.scope.helper.pascal"):
				# 				return
				# elif match_selector(self.view, line, "meta.method.implemented.pascal"):
				# 		if self.goto_meth_decl(line):
				# 				return
				# elif match_selector(self.view, line, "meta.function.declared.pascal"):
				# 		if self.goto_func_impl(line):
				# 				return
				# elif match_selector(self.view, line, "meta.function.implemented.pascal"):
				# 		if self.goto_func_decl(line):
				# 				return

				# if we fall through to here use default command
				print("no selector found, use 'goto_definition' instead")
				self.view.window().run_command("goto_definition")
				return

		# command
		def goto_meth_decl(self, start_line):
			last_line = line_at(self.view, self.view.size())
			cur_line = start_line
			content = line_str(self.view, cur_line)

			res = pattern_method.match(content)
			match = match_selector(self.view, cur_line, "meta.scope.method.pascal")
			if res and match:
					name_class = res.groups()[2]
					name_method = res.groups()[3]
			else:
					return False
			
			# find class parent
			cur_line -= 1
			for cur_line in range(cur_line,0,-1):
					content = line_str(self.view, cur_line)
					res = pattern_class.match(content)
					if res and res.groups()[1] == name_class:
							break

			# find method declaration
			cur_line += 1
			for cur_line in range(cur_line,last_line):
					content = line_str(self.view, cur_line)
					res = pattern_meth_decl.match(content)
					if res and res.groups()[2] == name_method:
							sel_line(self.view, cur_line)
							return True

			return False

		def goto_meth_impl(self, start_line, class_scope):
			last_line = line_at(self.view, self.view.size())
			cur_line = start_line
			content = line_str(self.view, cur_line)

			res = pattern_meth_decl.match(content)
			match = match_selector(self.view, cur_line, "meta.method.declared.pascal")
			if res and match:
					name_method = res.groups()[2] 
			else:
					return False
			
			# find class parent
			cur_line -= 1
			for cur_line in range(cur_line,0,-1):
					content = line_str(self.view, cur_line)
					if class_scope == "meta.scope.helper.pascal":
						res = pattern_helper.match(content)
						capture = 0
					else:
						res = pattern_class.match(content)
						capture = 1;
					match = match_selector(self.view, cur_line, class_scope)
					# outside of class scope,bail
					if not match:
						return False
					if res and match:
							name_class = res.groups()[capture] 
							break

			# find implementation
			cur_line += 1
			for cur_line in range(cur_line,last_line):
					content = line_str(self.view, cur_line)
					res = pattern_method.match(content)
					match = match_selector(self.view, cur_line, "meta.method.implemented.pascal")
					if res and match and res.groups()[2] == name_class and res.groups()[3] == name_method:
							sel_line(self.view, cur_line)
							return True
			
			return False

		def goto_func_decl(self, start_line):
			last_line = line_at(self.view, self.view.size())
			cur_line = start_line
			content = line_str(self.view, cur_line)

			res = pattern_func.match(content)
			match = match_selector(self.view, cur_line, "meta.function.implemented.pascal")
			if res and match:
					name = res.groups()[1]
			else:
					return False
			
			cur_line -= 1
			for cur_line in range(cur_line,0,-1):
					content = line_str(self.view, cur_line)
					res = pattern_func.match(content)
					if res and res.groups()[1] == name:
							sel_line(self.view, cur_line)
							return True

			return False

		def goto_func_impl(self, start_line):
			last_line = line_at(self.view, self.view.size())
			cur_line = start_line
			content = line_str(self.view, cur_line)

			# TODO: verify interface/implementation scopes
			res = pattern_func.match(content)
			match = match_selector(self.view, cur_line, "meta.function.declared.pascal")
			if res and match:
					name = res.groups()[1]
			else:
					return False
			
			cur_line += 1
			for cur_line in range(cur_line,last_line):
					content = line_str(self.view, cur_line)
					res = pattern_func.match(content)
					if res and res.groups()[1] == name:
							sel_line(self.view, cur_line)
							return True
			
			return False


class ImplementMethodCommand(GotoFunctionImplementationCommand):
		def run(self, edit):

				# get search
				meth = pattern_meth_decl.match(self.line_str(self.line_at_sel()))
				res = self.find_class(self.line_at_sel(), "meta.scope.class.pascal")
				print(res)
				print(meth.groups())

				# find implementation
				cur_line = res[0]
				found = False
				pattern = re.compile("^\s*implementation\s*$")
				for cur_line in range(cur_line, self.last_line()):
					res = pattern.match(self.line_str(cur_line))
					if res and match_selector(self.view, cur_line, "meta.scope.implementation.pascal"):
							print("found implementation at "+str(cur_line))
							found = True
							break
				if not found:
						return

				# find end
				pattern = re.compile("^\s*end\.\s*$")
				for cur_line in range(cur_line, self.last_line()):
					res = pattern.match(self.line_str(cur_line))
					if res and match_selector(self.view, cur_line, "meta.scope.implementation.pascal"):
							print("found end at "+str(cur_line))
							break

				# insert at last line found
				print('last line '+str(cur_line))
				pattern = re.compile("^(\s*)")
				res = pattern.match(self.line_str(cur_line))

				point = self.view.text_point(cur_line, 0)
				#meth.groups()[0]+" "+meth.groups()[1]
				self.view.insert(edit, point, res.groups()[0]+"new method")
				self.sel_line(cur_line)

				pass

		def find_class (self, start_line, scope):
			# find class parent
			cur_line = start_line
			for cur_line in range(cur_line,0,-1):
					content = self.line_str(cur_line)
					res = pattern_class.match(content)
					if res and match_selector(self.view, cur_line, scope):
							return [cur_line, res.groups()]
			return False


class FindAllOccurencesCommand(sublime_plugin.TextCommand):
	def on_done(self, index):
		print(index)
		return

	def run(self, edit):
		# select the word under the cursor
		# self.view.window().run_command("find_under_expand")
		self.view.window().run_command("expand_selection", {"to": "word"})
		sel = self.view.sel()[0]
		word = self.view.substr(sel)
		regions = self.view.find_all("("+word+")")
		lines = ""
		last_line = -1
		cur_line = line_at_sel(self.view)
		for region in regions:
			line = self.view.rowcol(region.a)
			num = str(line[0])
			index = int(line[0])
			if index == last_line or index == cur_line:
				continue
			source = line_str(self.view, index)
			source.strip()
			source = source.replace(word, "<b>"+word+"</b>")
			lines += "<span><a href=\""+num+"\">Line "+num+":</a>  "+source+"</span>|"
			last_line = index
    
    # show popup
		html = """
		<body id=show-scope>
		  <style>
				p {
					margin-top: 0;
				}
				a {
					font-family: system;
					font-size: 1.05rem;
				}
				b {
					color: green;
					text-decoration: underline;
				}
		  </style>
		  %
		</body>
		"""
		lines = lines.replace('|', '<br>')
		html = html.replace('%', lines)

		#items = ['hello', 'world', 'foobar']
		#self.view.window().show_quick_panel(items, self.on_done)
		#self.view.window().show_input_panel("caption", "foo", self.on_done, self.on_done, self.on_done)
		#self.view.show_popup_menu(items, self.on_done)
		self.view.show_popup(html, max_width=800, on_navigate=lambda x: goto_line(self.view, x))


class LazDocCommand(sublime_plugin.TextCommand):

	def show_url(self, url):
		url = "https://docs.getlazarus.org"+url
		with urllib.request.urlopen(url) as url:
			html = url.read().decode("utf-8")
			self.view.show_popup(html, max_width=800, on_navigate=lambda x: self.show_url(x))

	def open_url(self, url):
		url = "https://docs.getlazarus.org"+url
		print(url)
		webbrowser.open(url, new=2)

	def run(self, edit):
		# select the word under the cursor
		self.view.window().run_command("expand_selection", {"to": "word"})
		sel = self.view.sel()[0]
		word = self.view.substr(sel)

		# https://docs.python.org/2/library/xml.etree.elementtree.html
		# https://docs.getlazarus.org/?method=codesearch&format=xml&phrase=TStringList
		url = "https://docs.getlazarus.org/?method=codesearch&format=xml&phrase="+word
		print(url)

		with urllib.request.urlopen(url) as url:
			xml = url.read().decode("utf-8")
			html = ""
			# print("html:"+html)
			if xml == "":
				html = "<p>No results found</p>"
			else:
				# TODO: getlazarus.org is bugged and returns HTML tags in the title tag
				# we need to parse these out or include them
				# <title>
				# 	<b>TJSONObject</b>
				# 	Class
				# </title>
				xml = re.sub(r"<b>(\w+)</b>", r"\1", xml, flags=re.MULTILINE)
				xml = xml.replace("\n", "")
				xml = xml.replace("<no description>", "no description")

				root = ET.fromstring(xml)
				for result in root.findall('result'): 
					link = result.attrib['link']

					title = result.find('title').text

					# attempt to parse children and retain some data
					if title == None:
						title = ""
						for child in result.find('title'):
							if title != "":
								title += " "
							title += child.text

					kind = result.find('kind').text
					path = result.find('path').text
					description = result.find('description').text
					# TODO: we have to replace again here but why???
					description = description.replace("<no description>", "no description")

					html += "<h2><a href=\""+link+"\">"+title+"</a></h2>"
					html += "<p style=\"margin-left: 10%\">"+description+"</p>"
					html += "<hr>"

			self.view.show_popup(html, max_width=800, on_navigate=lambda x: self.open_url(x))

