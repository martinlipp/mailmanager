<?php
declare(strict_types=1);
namespace Codeminds\MailManager\Service;

use Codeminds\MailManager\Service\MailService;
use Exception;
use Swift_Attachment;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\Inject;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Form\ViewHelpers\RenderRenderableViewHelper;

class MailService {

    /**
     * @Inject
     * @var TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * @var array
     */
    protected $variables;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $identifier;

    /**
     *
     * @param string $identifier
     * @param array $variables
     * @param array $attachments
     * @param array $swiftAttachments
     * @return int number of successful recipients
     */
    public function sendMail(string $identifier, array $variables = array(), array $attachments = array(), array $swiftAttachments = array()): int
    {
        $this->identifier = $identifier;
        $this->variables = $variables;
        $configuration = $this->getConfiguration();
        if ($configuration['isActive'] === false) {
            return 0;
        }

        $sender = $this->getSender();
        $recipients = $this->getRecipients();
        $cc = $this->getRecipientsByType();
        $bcc = $this->getRecipientsByType('bcc');
        $replyTo = $this->getReplyTo();
        $subject = $this->getSubject();
        $body = $this->renderTemplate();
        $attachments = $this->addConfigurationAttachments($attachments);

        if ($configuration['enableDebugOutputToFiles'] === true) {
            GeneralUtility::writeFileToTypo3tempDir(Environment::getPublicPath() . '/typo3temp/mails/' . $identifier . '.html', $body);
        }
        if ($configuration['disableDelivery'] === true) {
            return 0;
        } else {
            $mail = $this->createMailMessage($subject, $sender, $recipients, $body, null, $attachments, $cc, $bcc, $replyTo, '', array(), array(), $swiftAttachments);
            $numberOfSuccessfulRecipients = $mail->send();
            $failedRecipients = $mail->getFailedRecipients();
            $numberOfFailedRecipients = count($failedRecipients);
            return $numberOfSuccessfulRecipients;
        }
    }

    /**
     * 
     * @return array
     */
    protected function getSender(): array
    {
        $configuration = $this->configuration;
        return array(
            $this->resolveValueFromVariables($configuration['senderEmailAddress']) => $this->resolveValueFromVariables($configuration['senderName'])
        );
    }

    /**
     * 
     * @return array
     */
    protected function getRecipients(): array
    {
        $configuration = $this->configuration;
        $recipients = array();
        if ($configuration['enableRerouting'] === true && !empty($configuration['reroutingEmailAddress'])) {
            $emailAddress = $configuration['reroutingEmailAddress'];
            $name = $configuration['reroutingName'];
            $recipients[$emailAddress] = $name;
        } else {
            foreach ($configuration['recipients'] as $recipient) {
                $emailAddress = $this->resolveValueFromVariables($recipient['emailAddress']);
                $name = $this->resolveValueFromVariables($recipient['name']);
                $recipients[$emailAddress] = $name; 
            }
        }
        return $recipients;
    }

    /**
     * 
     * @param string $type
     * @return array
     */
    protected function getRecipientsByType($type = 'cc'): array
    {
        $configuration = $this->configuration;
        if (empty($configuration[$type])) {
            return array();
        }
        $recipients = array();
        if ($configuration['enableRerouting'] === true && !empty($configuration['reroutingEmailAddress'])) {
            return array();
        } else {
            foreach ($configuration[$type] as $recipient) {
                $emailAddress = $this->resolveValueFromVariables($recipient['emailAddress']);
                $name = $this->resolveValueFromVariables($recipient['name']);
                $recipients[$emailAddress] = $name; 
            }
        }
        return $recipients;
    }

    /**
     * 
     * @return array
     */
    protected function getReplyTo(): array
    {
        $configuration = $this->configuration;
        if (empty($configuration['replyTo'])) {
            return array();
        }
        $recipients = array();
        foreach ($configuration['replyTo'] as $recipient) {
            $emailAddress = $this->resolveValueFromVariables($recipient['emailAddress']);
            $name = $this->resolveValueFromVariables($recipient['name']);
            $recipients[$emailAddress] = $name; 
        }
        return $recipients;
    }

    /**
     * 
     * @return string
     */
    protected function getSubject(): string
    {
        $configuration = $this->configuration;
        return $this->resolveValueFromVariables($configuration['subject']);
    }

    /**
     * 
     * @param array $attachments
     * @return array
     */
    protected function addConfigurationAttachments(array $attachments = array()): array
    {
        $configuration = $this->configuration;
        $attachmentsFromConfiguration = $configuration['attachments'];
        $attachments = array();
        if (is_array($attachmentsFromConfiguration)) {
            foreach ($attachmentsFromConfiguration as $attachment) {
                $attachments[] = GeneralUtility::getFileAbsFileName($attachment);
            }
        }
        return $attachments;
    }

    /**
     * 
     * @param string $value
     * @return string
     */
    protected function resolveValueFromVariables(string $value): ?string
    {
        $variables = $this->variables;
        if (substr($value, 0, 1) === '{' && substr($value, -1) === '}') {
            $path = trim($value, '{}');
            $subject = $variables;
            foreach (explode('.', $path) as $index => $pathSegment) {
                $subject = $subject[$pathSegment];
                if ($subject === null) {
                    break;
                }
            }
            return $subject;
        }
        return $value;
    }

    /**
     *
     * @return array
     */
    protected function getConfiguration(): array
    {
        $identifier = $this->identifier;
        $fullConfiguration = $this->getFullConfiguration();
        foreach ($fullConfiguration['configurations'] as $configuration) {
            if ($configuration['identifier'] === $identifier) {
                $configuration = array_merge($fullConfiguration['debug'], $configuration);
                $this->configuration = $configuration;
                return $configuration;
            }
        }
        $this->configuration = $fullConfiguration['debug'];
        return $fullConfiguration['debug'];
    }

    /**
     *
     * @return array
     */
    protected function getFullConfiguration(): array
    {
        $settings = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS, 'mailmanager', 'tx_mailmanager');
        $path = $settings['configurationFilePath'];
        if (empty($path)) {
            throw new Exception('No configurationFilePath defined for mailmanager. Try to set "plugin.tx_mailmanager.settings.configurationFilePath".');
        }
        $yamlLoader = GeneralUtility::makeInstance(YamlFileLoader::class);
        return $yamlLoader->load($settings['configurationFilePath']);
    }

    /**
     *
     * @return string
     */
    protected function renderTemplate(): string
    {
        $configuration = $this->configuration;
        $templatePathAndFileName = $configuration['templatePathAndFileName'];
        $partialRootPaths = $configuration['partialRootPaths'];
        $layoutRootPaths = $configuration['layoutRootPaths'];
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename($templatePathAndFileName);
        if (count($partialRootPaths) > 0) {
            $view->setPartialRootPaths($partialRootPaths);
        }
        if (count($layoutRootPaths) > 0) {
            $view->setPartialRootPaths($layoutRootPaths);
        }
        $view->assignMultiple($this->variables);
        if (!empty($this->variables['form'])) {
            $view->getRenderingContext()
                ->getViewHelperVariableContainer()
                ->addOrUpdate(RenderRenderableViewHelper::class, 'formRuntime', $this->variables['form']);
        }
        return $view->render();
    }

    /**
     *
     * @param string $subject
     * @param array $from (is also returnPath if no sender and no returnPath is set)
     * @param array $to example: array('your@address.tld' => 'Your Name', 'no-name@example.org')
     * @param string $htmlBody is required if plainBody is not set; if plainBody is set too it will be send as additional part
     * @param string $plainBody is required if htmlBody is not set; will also be send as additional part if htmlBody is set
     * @param array $attachments example: array('other-filename.pdf' => '/path/to/file', '/path/to/another/file')
     * @param array $cc example: array('your@address.tld' => 'Your Name', 'no-name@example.org')
     * @param array $bcc example: array('your@address.tld' => 'Your Name', 'no-name@example.org')
     * @param array $replyTo example: array('your@address.tld' => 'Your Name', 'no-name@example.org')
     * @param string $returnPath must be email address only
     * @param array $readReceiptTo example: array('your@address.tld' => 'Your Name', 'no-name@example.org')
     * @param array $sender must be one address; use when multiple from addresses are being used or when the writer of the message is not the sender (is also returnPath if not set)
     * @param array $swiftAttachments array of SwiftAttachment objects (for more options like dynamic content or other dispositions; use $attachment if you only attach simple files from paths)
     * @return MailMessage
     */
    protected function createMailMessage(string $subject, array $from, array $to, string $htmlBody = null, string $plainBody = null, array $attachments = array(), array $cc = array(), array $bcc = array(), array $replyTo = array(), string $returnPath = '', array $readReceiptTo = array(), array $sender = array(), array $swiftAttachments = array()): MailMessage
    {
        $mail = GeneralUtility::makeInstance(MailMessage::class);
        $mail->setSubject($subject);
        $mail->setFrom($from);
        $mail->setTo($to);
        if ($htmlBody !== null) {
            $mail->setBody($htmlBody, 'text/html');
            if ($plainBody !== null) {
                $mail->addPart($plainBody, 'text/plain');
            }
        } elseif ($plainBody !== null) {
            $mail->setBody($plainBody);
        }
        foreach ($attachments as $filename => $path) {
            $attachment = Swift_Attachment::fromPath($path);
            if (!is_int($filename)) {
                // set filename only if explicitly set as string key in array
                $attachment->setFilename($filename);
            }
            $mail->attach($attachment);
        }
        foreach ($swiftAttachments as $sattachment) {
            $mail->attach($sattachment);
        }
        if (count($cc) > 0) {
            $mail->setCc($cc);
        }
        if (count($bcc) > 0) {
            $mail->setBcc($bcc);
        }
        if (count($replyTo) > 0) {
            $mail->setReplyTo($replyTo);
        }
        if ($returnPath !== '') {
            $mail->setReturnPath($returnPath);
        }
        if (count($readReceiptTo) > 0) {
            $mail->setReadReceiptTo($readReceiptTo);
        }
        if (count($sender) === 1) {
            $mail->setSender($sender);
        }
        return $mail;
    }
}