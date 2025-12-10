<?php

namespace Netwerkstatt\QrGenerator\Tasks;

use Netwerkstatt\QrGenerator\Extensions\QrGeneratorExtension;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * An administrative task to delete all queued jobs records from the database.
 * Use with caution!
 */
class DeleteAllCachedQRCodesTask extends BuildTask
{
    /**
     * @inheritdoc
     * @return string
     */
    public function getTitle(): string
    {
        return "Delete all cached QR codes.";
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getDescription(): string
    {
        return "Remove all cached QR codes. QR codes are generated on request.";
    }

    /**
     * Run the task
     * @param HTTPRequest $request
     */
    public function run($request): void
    {
        $classesWithExtension = ClassInfo::classesWithExtension(QrGeneratorExtension::class);

        $confirm = $request->getVar('confirm');
        if (!$confirm) {
            $this->logMessage("Really delete all cached QR code images? Please add ?confirm=1 to the URL to confirm.");
            $this->logMessage("List of files that would be deleted:");
        }

        $folders = [];
        foreach ($classesWithExtension as $class) {
            $object = Injector::inst()->create($class);
            $folder = $object->getQrCodeAssetsPath();
            if (!in_array($folder, $folders)) {
                $folders[] = rtrim($folder, DIRECTORY_SEPARATOR);
            }
        }
        foreach ($folders as $folder) {
            foreach (glob($folder . '/*') as $file) {
                if (is_file($file)) {
                    if ($confirm) {
                        $this->logMessage("Deleting $file");
                        unlink($file);
                    } else {
                        $this->logMessage($file);
                    }
                }
            }
        }
    }

    private function logMessage($message): void
    {
        echo $message . (Director::is_cli() ? PHP_EOL : '<br>');
    }
}
