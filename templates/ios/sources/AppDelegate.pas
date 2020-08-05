{$mode objfpc}
{$modeswitch objectivec2}

unit AppDelegate;
interface
uses
  iPhoneAll;

type
  TAppDelegate = objcclass(UIResponder, UIApplicationDelegateProtocol)
    private
      window: UIWindow;
    public
      function application_didFinishLaunchingWithOptions(application: UIApplication; launchOptions: NSDictionary): boolean; message 'application:didFinishLaunchingWithOptions:';
  end;
  
implementation

function TAppDelegate.application_didFinishLaunchingWithOptions (application: UIApplication; launchOptions: NSDictionary): boolean;
var
  viewController: UIViewController;
begin
  window := UIWindow.alloc.initWithFrame(UIScreen.mainScreen.bounds);
  
  viewController := UIViewController.alloc.init;
  viewController.view.setBackgroundColor(UIColor.blueColor);
  
  window.setRootViewController(viewController);
  window.makeKeyAndVisible;
  
  result := true;
end;

end.