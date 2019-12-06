<?php
declare(strict_types = 1);
namespace Codeminds\MailManager\Domain\Finishers;

use TYPO3\CMS\Extbase\Annotation\Inject;
use TYPO3\CMS\Form\Domain\Finishers as Finishers;
use TYPO3\CMS\Form\Service\TranslationService;

class EmailFinisher extends Finishers\EmailFinisher
{

    /**
     * @Inject
     * @var Codeminds\MailManager\Service\MailService
     */
    protected $mailService = null;
    
    /**
     * Executes this finisher
     * @see AbstractFinisher::execute()
     *
     * @throws FinisherException
     */
    protected function executeInternal()
    {
        $formRuntime = $this->finisherContext->getFormRuntime();

        $translationService = TranslationService::getInstance();
        if (isset($this->options['translation']['language']) && !empty($this->options['translation']['language'])) {
            $languageBackup = $translationService->getLanguage();
            $translationService->setLanguage($this->options['translation']['language']);
        }
        if (!empty($languageBackup)) {
            $translationService->setLanguage($languageBackup);
        }

        $emailAddress = $this->parseOption('emailAddress');
        $name = $this->parseOption('name');
        $configurationName = $this->parseOption('configurationName');

        if (empty($configurationName)) {
            throw new FinisherException('The option "configurationName" must be set for the MailManager EmailFinisher.', 1575428509);
        }

        $elements = $formRuntime->getFormDefinition()->getRenderablesRecursively();
        $attachments = array();
        foreach ($elements as $element) {
            if (!$element instanceof FileUpload) {
                continue;
            }
            $file = $formRuntime[$element->getIdentifier()];
            if ($file) {
                if ($file instanceof FileReference) {
                    $file = $file->getOriginalResource();
                }

                $attachments[] = \Swift_Attachment::newInstance($file->getContents(), $file->getName(), $file->getMimeType());
            }
        }

        $variables = array(
            'form' => $formRuntime,
            'finisherVariableProvider' => $this->finisherContext->getFinisherVariableProvider(),
            'emailAddress' => $emailAddress,
            'name' => $name
        );

        $this->mailService->sendMail($configurationName, $variables, array(), $attachments);
    }

}