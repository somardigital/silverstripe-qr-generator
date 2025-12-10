<?php

namespace Netwerkstatt\QrGenerator\Extensions;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
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
    public function generateQRCode()
    {
        $filename = ASSETS_PATH . $this->getQrCodeName();

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
        $result->saveToFile(ASSETS_PATH . $this->getQrCodeName());

        return $result->getString();
    }

    /**
     * Helper method to generate the filename for the current QR-Code
     *
     * @todo: use classname etc...
     *
     * @return string
     */
    private function getQrCodeName()
    {
        $qrPath = '/qr/';

        // check if $path exists in assets
        if (!is_dir(ASSETS_PATH . $qrPath)) {
            mkdir(ASSETS_PATH . $qrPath);
        }

        $base = URLSegmentFilter::create()->filter(implode('-', [
            'qr',
            $this->owner->ClassName,
            $this->owner->Title,
            $this->owner->ID,
            substr(md5($this->getQrCodeContent()), 0, 5 )
        ]));

        return $qrPath . $base . '.png';
    }

    /**
     * Use absolute link as default
     *
     * @todo: check if owner has a method to provide content. This might be useful for other types of codes,
     * e.g. for contact data, calendar data etc...
     */
    private function getQrCodeContent(): string
    {
        return $this->getOwner()->AbsoluteLink();
    }

    /**
     * URL for using in <img alt="Scan me" src="$QRCodeURL" />
     */
    public function getQRCodeURL(): string
    {
        $this->generateQRCode();
        return Controller::join_links(Director::baseURL(), ASSETS_DIR, $this->getQrCodeName());
    }

}
