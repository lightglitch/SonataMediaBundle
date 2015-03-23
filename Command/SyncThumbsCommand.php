<?php

/*
 * This file is part of the Sonata package.
*
* (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Sonata\MediaBundle\Command;

use Symfony\Component\Console\Input\InputArgument;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * This command can be used to re-generate the thumbnails for all uploaded medias.
 *
 * Useful if you have existing media content and added new formats.
 *
 */
class SyncThumbsCommand extends BaseCommand
{
    protected $quiet = false;
    protected $output;

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->setName('sonata:media:sync-thumbnails')
            ->setDescription('Sync uploaded image thumbs with new media formats')
            ->setDefinition(array(
                new InputArgument('providerName', InputArgument::OPTIONAL, 'The provider'),
                new InputArgument('context', InputArgument::OPTIONAL, 'The context'),
                new InputOption('batchSize', null, InputOption::VALUE_REQUIRED, 'Media batch size (100 by default)', 100)
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $providerName = $input->getArgument('providerName');
        if (null === $providerName) {
            $providers = array_keys($this->getMediaPool()->getProviders());
            $providerKey = $this->getHelperSet()->get('dialog')->select($output, 'Please select the provider', $providers);
            $providerName = $providers[$providerKey];
        }

        $context  = $input->getArgument('context');
        if (null === $context) {
            $contexts = array_keys($this->getMediaPool()->getContexts());
            $contextKey = $this->getHelperSet()->get('dialog')->select($output, 'Please select the context', $contexts);
            $context = $contexts[$contextKey];
        }

        $this->quiet = $input->getOption('quiet');
        $this->output = $output;

        $provider = $this->getMediaPool()->getProvider($providerName);

        $batchCounter = 0;
        $batchSize = intval($input->getOption('batchSize'));
        $totalMediasCount = 0;
        do {
            $batchCounter++;
            try {
                $medias = $this->getMediaManager()->findBy(
                    array(
                        'providerName' => $providerName,
                        'context' => $context
                    ),
                    array(
                        'id' => 'ASC'
                    ),
                    $batchSize,
                    ($batchCounter - 1) * $batchSize
                );
            } catch (\Exception $e) {
                $this->log('Error: ' . $e->getMessage());
                break;
            }

            $batchMediasCount = count($medias);
            if ($batchMediasCount === 0) {
                break;
            }

            $totalMediasCount += $batchMediasCount;
            $this->log(
                sprintf(
                    "Loaded %s medias (batch #%d) for generating thumbs (provider: %s, context: %s)",
                    $batchMediasCount,
                    $batchCounter,
                    $providerName,
                    $context
                )
            );

            foreach ($medias as $media) {
                $this->log("Generating thumbs for " . $media->getName() . ' - ' . $media->getId());

                try {
                    $provider->removeThumbnails($media);
                } catch (\Exception $e) {
                    $this->log(sprintf("<error>Unable to remove old thumbnails, media: %s - %s </error>",
                        $media->getId(), $e->getMessage()));
                    continue;
                }

                try {
                    $provider->generateThumbnails($media);
                } catch (\Exception $e) {
                    $this->log(sprintf("<error>Unable to generated new thumbnails, media: %s - %s </error>",
                        $media->getId(), $e->getMessage()));
                    continue;
                }
            }
        } while (true);

        $this->log("Done (total medias processed: {$totalMediasCount}).");
    }

    /**
     * Write a message to the output
     *
     * @param string $message
     *
     * @return void
     */
    protected function log($message)
    {
        if (false === $this->quiet) {
            $this->output->writeln($message);
        }
    }
}
