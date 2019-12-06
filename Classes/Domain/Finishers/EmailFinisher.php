<?php
declare(strict_types = 1);
namespace Codeminds\MailManager\Domain\Finishers;

/**
 * Copyright 2019 Martin Lipp
 * 
 * This file is part of "mailmanager", an extension for TYPO3 CMS.
 * 
 * mailmanager is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * mailmanager is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use Swift_Attachment;
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

                $attachments[] = Swift_Attachment::newInstance($file->getContents(), $file->getName(), $file->getMimeType());
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