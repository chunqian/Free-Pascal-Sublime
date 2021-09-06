{$mode objfpc}
{$modeswitch objectivec1}

unit AppDelegate;
interface
uses
	CocoaAll;

type
	TAppDelegate = objcclass(NSObject, NSApplicationDelegateProtocol)
    private
      window: NSWindow;
  	public
  		procedure applicationDidFinishLaunching(notification: NSNotification); message 'applicationDidFinishLaunching:';
 	end;

implementation

procedure TAppDelegate.applicationDidFinishLaunching(notification : NSNotification);
begin
	// Insert code here to initialize your application 
end;

end.