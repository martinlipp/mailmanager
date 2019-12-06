/**
 * Module: TYPO3/CMS/MailManager/Backend/FormEditor/ViewModel
 */
define(['jquery',
        'TYPO3/CMS/Form/Backend/FormEditor/Helper'
        ], function($, Helper) {
        'use strict';

    return (function($, Helper) {

        /**
         * @private
         *
         * @var object
         */
        var _formEditorApp = null;

        /**
         * @private
         *
         * @return object
         */
        function getFormEditorApp() {
            return _formEditorApp;
        };

        /**
         * @private
         *
         * @return object
         */
        function getPublisherSubscriber() {
            return getFormEditorApp().getPublisherSubscriber();
        };

        /**
         * @private
         *
         * @return object
         */
        function getUtility() {
            return getFormEditorApp().getUtility();
        };

        /**
         * @private
         *
         * @param object
         * @return object
         */
        function getHelper() {
            return Helper;
        };

        /**
         * @private
         *
         * @return object
         */
        function getCurrentlySelectedFormElement() {
            return getFormEditorApp().getCurrentlySelectedFormElement();
        };

        /**
         * @private
         *
         * @param mixed test
         * @param string message
         * @param int messageCode
         * @return void
         */
        function assert(test, message, messageCode) {
            return getFormEditorApp().assert(test, message, messageCode);
        };

        /**
         * @private
         *
         * @return void
         * @throws 1491643380
         */
        function _helperSetup() {
            assert('function' === $.type(Helper.bootstrap),
                'The view model helper does not implement the method "bootstrap"',
                1491643380
            );
            Helper.bootstrap(getFormEditorApp());
        };

        /**
         * @private
         *
         * @return void
         */
        function _subscribeEvents() {
            /**
             * @private
             *
             * @param string
             * @param array
             *              args[0] = editorConfiguration
             *              args[1] = editorHtml
             *              args[2] = collectionElementIdentifier
             *              args[3] = collectionName
             * @return void
             */
            getPublisherSubscriber().subscribe('view/inspector/editor/insert/perform', function(topic, args) {
                if (args[0]['templateName'] === 'Inspector-SelectFormelementsEditor') {
                    renderSelectFormelementsEditor(
                        args[0],
                        args[1],
                        args[2],
                        args[3]
                    );
                }
            });
        };

        /**
         * @private
         *
         * @param object editorConfiguration
         * @param object editorHtml
         * @param string collectionElementIdentifier
         * @param string collectionName
         * @return void
         */
        function renderSelectFormelementsEditor(editorConfiguration, editorHtml, collectionElementIdentifier, collectionName) {
            var nonCompositeNonToplevelFormElements, propertyData, propertyPath, selectElement;
            assert(
                'object' === $.type(editorConfiguration),
                'Invalid parameter "editorConfiguration"',
                1475421048
            );
            assert(
                'object' === $.type(editorHtml),
                'Invalid parameter "editorHtml"',
                1475421049
            );
            assert(
                getUtility().isNonEmptyString(editorConfiguration['label']),
                'Invalid configuration "label"',
                1475421050
            );
            assert(
                getUtility().isNonEmptyString(editorConfiguration['propertyPath']),
                'Invalid configuration "propertyPath"',
                1475421051
            );

            propertyPath = getFormEditorApp().buildPropertyPath(
                editorConfiguration['propertyPath'],
                collectionElementIdentifier,
                collectionName
            );

            getHelper()
                .getTemplatePropertyDomElement('label', editorHtml)
                .append(editorConfiguration['label']);

            selectElement = getHelper()
                .getTemplatePropertyDomElement('selectOptions', editorHtml);

            propertyData = getCurrentlySelectedFormElement().get(propertyPath);

            nonCompositeNonToplevelFormElements = getFormEditorApp().getNonCompositeNonToplevelFormElements();

            $.each(nonCompositeNonToplevelFormElements, function(i, nonCompositeNonToplevelFormElement) {
                var option;
                if ('{' + nonCompositeNonToplevelFormElement.get('identifier') + '}' === propertyData) {
                    option = new Option(nonCompositeNonToplevelFormElement.get('label'), '{' + nonCompositeNonToplevelFormElement.get('identifier') + '}', false, true);
                } else {
                    option = new Option(nonCompositeNonToplevelFormElement.get('label'), '{' + nonCompositeNonToplevelFormElement.get('identifier') + '}');
                }
                $(option).data({value: '{' + nonCompositeNonToplevelFormElement.get('identifier') + '}'});
                selectElement.append(option);
                if (!propertyData && i == 0) {
                    getCurrentlySelectedFormElement().set(propertyPath, '{' + nonCompositeNonToplevelFormElement.get('identifier') + '}');
                }
            });

            selectElement.on('change', function() {
                getCurrentlySelectedFormElement().set(propertyPath, $('option:selected', $(this)).data('value'));
            });
        };

        /**
         * @public
         *
         * @param object formEditorApp
         * @return void
         */
        function bootstrap(formEditorApp) {
            _formEditorApp = formEditorApp;
            _helperSetup();
            _subscribeEvents();
        };

        /**
         * Publish the public methods.
         * Implements the "Revealing Module Pattern".
         */
        return {
            bootstrap: bootstrap
        };
    })($, Helper);
});