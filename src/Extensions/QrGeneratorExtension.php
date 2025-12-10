<?php

namespace Netwerkstatt\QrGenerator\Extensions;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ValidationException;
use SilverStripe\View\Parsers\URLSegmentFilter;

/**
 * Class QrGeneratorExtension
 */
class QrGeneratorExtension extends Extension
{
    private static $showQrTabInCms = false;

    public function updateCMSFields(FieldList $fields)
    {
        if (!$this->getOwner()->isInDB()) {
            return;
        }

        $config = Config::inst();
        if ($config->get(self::class, 'showQrTabInCms') ||
            $config->get(get_class($this->getOwner()), 'showQrTabInCms')
        ) {
            $fields->addFieldsToTab('Root.QR', [
                HeaderField::create('QRHeading', _t('QrGeneratorExtension.Title', 'QR Code'), 2),
                LiteralField::create('QRContent',
                    '<p><img src="data:image/png;base64,' . $this->getQRCodeBase64() . '" /></p>'),
                LiteralField::create('QRDownload', '<p><a href="' . $this->getQRCodeURL() . '" target="_blank">'
                    . _t('QrGeneratorExtension.Download', 'Download')
                    . '</a></p>')
            ]);
        }

    }

    /**
     * For inline images
     *
     * <img alt="Scan me" src="data:image/png;base64,$QRCodeBase64" />
     */
    public function getQRCodeBase64(): string
    {
        return base64_encode($this->generateQRCode());
    }

    /**
     * Very simple proof of concept for now.
     *
     * uses AbsoluteLink() to get the URL...
     *
     * @todo: make output format configurable
     * @todo: make size configurable (by DataObject)
     *
     * @return string
     */
    public function generateQRCode(): string
    {
        $filename = $this->getQrCodeAssetsPath() . $this->getQrCodeFilename();

        if (file_exists($filename)) {
            return file_get_contents($filename);
        }

        $qrCode = new QrCode(
            data: $this->getQrCodeContent(),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 320,
            margin: 5,
            roundBlockSizeMode: RoundBlockSizeMode::Enlarge,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );

        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        $result->saveToFile($filename);

        return $result->getString();
    }

    private function getQrCodeAssetsPath(): string
    {
        $qrPath = 'qr';

        $this->getOwner()->extend('updateQrAssetsPath', $qrPath);

        $qrPath =
            DIRECTORY_SEPARATOR .
            ltrim(rtrim($qrPath, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR;

        // check if $path exists in assets
        if (!is_dir(ASSETS_PATH . $qrPath)) {
            mkdir(ASSETS_PATH . $qrPath);
        }

        return ASSETS_PATH . $qrPath;
    }

    /**
     * Helper method to generate the filename for the current QR-Code
     *
     * Extendable via `updateQrCodeFilename`.
     */
    private function getQrCodeFilename(): string
    {
        $owner = $this->getOwner();
        $filename = URLSegmentFilter::create()->filter(implode('-', [
            'qr',
            $owner->ClassName,
            $owner->Title,
            $owner->ID,
            substr(md5($this->getQrCodeContent()), 0, 5)
        ]));

        $this->getOwner()->extend('updateQrCodeFilename', $filename);
        if (!str_ends_with($filename, '.png')) {
            $filename .= '.png';
        }

        return $filename;
    }

    /**
     * Get content for the QR code
     *
     * Cascades through QrCodeContent, AbsoluteLink, Link methods.
     *
     * Extendable via `updateQrCodeContent`.
     */
    private function getQrCodeContent(): string
    {
        if ($this->getOwner()->hasMethod('QrCodeContent')) {
            $content = $this->getOwner()->QrCodeContent();
        } elseif ($this->getOwner()->hasMethod('AbsoluteLink')) {
            $content = $this->getOwner()->AbsoluteLink();
        } elseif ($this->getOwner()->hasMethod('Link')) {
            $content = $this->getOwner()->Link();
        } else {
            Injector::inst()->get(LoggerInterface::class)->warning(
                sprintf('Class %s does not have QrCodeContent, AbsoluteLink or Link method', get_class($this->getOwner()))
            );
            $content = $this->getOwner()->ID; // safe default to have at least some content in the QR
        }

        $this->getOwner()->extend('updateQrCodeContent', $content);

        return $content;
    }

    /**
     * URL for using in <img alt="Scan me" src="$QRCodeURL" />
     */
    public function getQRCodeURL(): string
    {
        $this->generateQRCode();
        return Controller::join_links(Director::baseURL(), $this->getQrCodeAssetsPath(), $this->getQrCodeFilename());
    }

    /**
     * Delete the QR code if the file exists
     */
    private function deleteQRCode(): ?bool
    {
        $filename = $this->getQrCodeAssetsPath() . $this->getQrCodeFilename();

        if (file_exists($filename)) {
            return unlink($filename);
        }

        return null;
    }

    /**
     * Hook into afterWrite to delete the QR code after object is saved in case its data changed
     */
    public function onAfterWrite(): void
    {
        $this->deleteQRCode();
    }
}
