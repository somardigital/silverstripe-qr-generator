# Silverstripe QR-Generator

## Installation
composer require wernerkrauss/silverstripe-qr-generator

## Requirements
* Silverstripe CMS 5.0 or newer

## How it works

The code iterates over method candidates to get the content of the QR code (`QrCodeContent()`, `AbsoluteLink()`, `Link()`). 
The codes are cached as png files in `/assets/qr/` by default. The path can be customised via an extension point (see below).

You can either include the code inline or as a source. Both will work out of the box:

### Inline QR Code
`
<img alt="Scan me" src="data:image/png;base64,$QRCodeBase64" />
`

### Linked Image

`
<img alt="Scan me" src="$QRCodeURL" />
`

## Extension points

### Path inside the assets folder where QR codes are stored

In your extension, create a method `updateQrAssetsPath` that received the path where QR codes are stored.
Update the path via reference. Starting and trailing directory separators are added automatically. 

### Filename of the QR code image stored on the filesystem

In your extension, create a method `updateQrCodeFilename` that receives the file name that should be used when storing the file locally. Update the filename via reference.
File extension `.png` is added automatically if left out.

### Content of the QR code

In your extension, create a method `updateQrCodeContent` that receives the content that should be encoded in the QR code. Update the content via reference.

## Configuration

Optionally, the extension can add a QR tab to the CMS tabs for each page.
This is turned off by default and can be enabled globally or by page type.

### Enable QR tab in the CMS for all page types
```
Netwerkstatt\QrGenerator\Extensions\QrGeneratorExtension:
  showQrTabInCms: true
```

### Enable QR tab in the CMS for a specific page type only
```
App\PageTypes\NewsPage:
  showQrTabInCms: true
```

## Todo
*  More pre-defined formats with wrapper, e.g. calendar item, address...
*  create a Subclass of ViewableData that wraps the generated QR code and can be modified in templates
