{$mode objfpc}
{$modeswitch objectivec2}
{$modeswitch advancedrecords}
{$modeswitch multihelpers}

unit IOSUtils;
interface
uses
  //{$if defined(iphonesim) or defined(cpuarm)}
  //iPhoneAll
  //{$else }
  //CocoaAll
  //{$endif}
  // TODO: cpuarm doesn't work for ppcrossa64??
  SysUtils, iPhoneAll;

type
  CGPointHelper = record helper for CGPoint
    function ToStr: string;
  end;
  CGSizeHelper = record helper for CGSize
    function ToStr: string;
  end;
  CGRectHelper = record helper for CGRect
    function ToStr: string;
  end;

type
  UIImageHelpers = objccategory (UIImage)
    function scaledToSize(newSize: CGSize): UIImage; message 'scaledToSize:';
  end;

implementation
uses
  CTypes;

function UIImageHelpers.scaledToSize(newSize: CGSize): UIImage;
var
  newImage: UIImage;
begin
  UIGraphicsBeginImageContextWithOptions(newSize, false, 0.0);
  drawInRect(CGRectMake(0, 0, newSize.width, newSize.height));
  newImage := UIGraphicsGetImageFromCurrentImageContext();    
  UIGraphicsEndImageContext();
  result := newImage;
end;

function CGPointHelper.ToStr: string;
begin
  result := '{'+FloatToStr(x)+','+FloatToStr(y)+'}';
end;

function CGSizeHelper.ToStr: string;
begin
  result := '{'+FloatToStr(width)+','+FloatToStr(height)+'}';
end;

function CGRectHelper.ToStr: string;
begin
  result := '{'+origin.ToStr+','+size.ToStr+'}';
end;

end.