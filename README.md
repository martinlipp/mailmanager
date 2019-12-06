# TYPO3 Extension ``mailmanager``

## 1. Features

- Manage all outgoing mails in one central place by configuring them in one single yaml configuration file.
- Disable or reroute all mails for debugging with one switch. Therefore you can already define the correct live email addresses during development and have not to worry about forgetting to switch them back when going live.
- Output the mail contents to accessible html files for quick debugging without having to spam your inbox.

## 2. Usage

### 1) Installation

Install the extension using Composer: `composer require martinlipp/mailmanager`.

### 2) Minimal setup

1) Create a yaml file for your mail configuration (for example inside your site package extension in Configuration/Yaml/Mails.yaml)
2) Define the location of your yaml file via TypoScript: `plugin.tx_mailmanager.settings.configurationFilePath = EXT:your_site_package/Configuration/Yaml/Mails.yaml`
3) Set up your first mail configuration in your yaml file (`EXT:your_site_package/Configuration/Yaml/Mails.yaml`):
```
configurations:
  -
    identifier: 'MyIdentifier'
    isActive: true
    senderEmailAddress: 'sender@company.com'
    senderName: 'My sender'
    recipients:
      -
        emailAddress: 'recipient@company.com'
        name: 'My recipient'
    subject: 'My subject'
    templatePathAndFileName: 'EXT:your_site_package/Resources/Private/Templates/Mails/YourIdentifier.html'
    partialRootPaths: []
    layoutRootPaths: []
debug:
  disableDelivery: false
  enableRerouting: false
  reroutingEmailAddress: 'debug@company.com'
  reroutingName: 'Debug'
  enableDebugOutputToFiles: false
```
4) Define your template (`EXT:your_site_package/Resources/Private/Templates/Mails/YourIdentifier.html`):
```
<p>Dear customer,</p>
<p>this is a hello {variable} example mail</p>
<p>Cheers</p>
```
5) Use the provided MailService to send your mails:
```
/**
 * @Inject
 * @var Codeminds\MailManager\Service\MailService
 */
protected $mailService = null;

.
.
.

$variables = array('variable' => 'world');
$this->mailService->sendMail('MyIdentifier', $variables);
```

### 3) Additional setup (optional)

##### a) Use variables in configuration
You can use variables in your yaml configuration to e.g. set the recipient dynamically. Refer to the variable by using curly brackets: `{variableName}`. It is also possible to refer to nested variables: `{variable.nested.variableName}`.
Example:
```
configurations:
  -
    identifier: 'MyIdentifier'
    isActive: true
    senderEmailAddress: 'sender@company.com'
    senderName: 'My sender'
    recipients:
      -
        emailAddress: '{variableName}'
.
.
.
```
##### b) Use with TYPO3 form
1) Include the static TypoScript of the extension.
2) You can now add the two new finishers in the form module: Email to submitter (via MailManager) and Email to you (via MailManager)
3) In the finisher you have to define the identifier of your corresponding mail configuration
4) Optionaly you can set the receiver or sender to a dynamically set value coming from the form (the mail configuration has to be defined accordingly by setting `{emailAddress}` or `{name}`)
5) Define the templates and partials of the form extension (or use or own). In your yaml mail configuration:
```
    templatePathAndFileName: 'EXT:form/Resources/Private/Frontend/Templates/Finishers/Email/Html.html'
    partialRootPaths: ['EXT:form/Resources/Private/Frontend/Partials']
```

##### c) Debugging
Just use the self explanatory options under `debug` in your yaml file. If `enableDebugOutputToFiles` is on, the files will be put to /typo3temp/mails/<Identifier>.html for quick access via your browser.
