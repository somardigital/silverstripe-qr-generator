<?php

namespace Netwerkstatt\QrGenerator\Tasks;

use Netwerkstatt\QrGenerator\Extensions\QrGeneratorExtension;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Administrative task to delete all cached QR code images from disk.
 * QR codes are regenerated on next request.
 */
class DeleteAllCachedQRCodesTask extends BuildTask
{
    protected string $title = 'Delete all cached QR codes.';

    protected static string $description = 'Remove all cached QR codes. QR codes are generated on request.';

    protected static string $commandName = 'delete-all-cached-qr-codes';

    public function getOptions(): array
    {
        return [
            new InputOption(
                'confirm',
                null,
                InputOption::VALUE_NONE,
                'Actually delete files (otherwise lists what would be deleted).'
            ),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $classesWithExtension = ClassInfo::classesWithExtension(QrGeneratorExtension::class);
        $confirm = (bool) $input->getOption('confirm');

        if (!$confirm) {
            $output->writeln('Really delete all cached QR code images? Re-run with --confirm to apply.');
            $output->writeln('List of files that would be deleted:');
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
                if (!is_file($file)) {
                    continue;
                }
                if ($confirm) {
                    $output->writeln("Deleting $file");
                    unlink($file);
                } else {
                    $output->writeln($file);
                }
            }
        }

        return Command::SUCCESS;
    }
}
