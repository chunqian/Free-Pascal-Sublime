{$mode objfpc}
{$modeswitch objectivec1}

unit AppDelegate;
interface
uses
	CocoaAll;

type
	TAppController = objcclass(NSObject)
	public
		procedure applicationDidFinishLaunching(notification : NSNotification); message 'applicationDidFinishLaunching:';
	private
   		window: NSWindow;
 	end;

implementation

procedure TAppController.applicationDidFinishLaunching(notification : NSNotification);
begin
	// Insert code here to initialize your application 
end;

end.