on run argv
	set my_project to item 1 of argv
	tell application "Xcode"
		activate
		open my_project
		tell application "System Events"
			perform (keystroke "r" using {command down})
		end tell
	end tell
end run
