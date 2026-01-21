// const epic_gateways = [['EpayPaymentPayment', 'epay_epic_dk'], ['EpayPaymentMobilePay', 'epay_epic_mobilepay'], ['EpayPaymentApplePay', 'epay_epic_applepay'], ['EpayPaymentViaBill', 'epay_epic_viabill'], ['EpayPaymentPayPal', 'epay_epic_paypal']];
const epic_gateways = [['EpayPayment', 'epay_payment_solutions']];

epic_gateways.forEach(gateway => {

    const gateway_class = gateway[0];
    const gateway_id = gateway[1];

    const settings = window.wc.wcSettings.getSetting( gateway_class + '_data', {} );

    const ariaLabel = window.wp.htmlEntities.decodeEntities( settings.title );
    
    // console.log('Class: ' + gateway[0] + ' Aria: ' + settings.title + 'Settings: ' + settings);

    const { createElement, useEffect } = window.wp.element;
    
    const Label = () => {
        return window.wp.element.createElement('span', {className: 'blocks-woocommerce-epay-epic-inner'},
            window.wp.element.createElement('span', {
                className: 'blocks-woocommerce-epay-epic-inner__title'
            }, ariaLabel),
            window.wp.element.createElement('span', {
                dangerouslySetInnerHTML: {__html: settings.icon},
                className: 'blocks-woocommerce-epay-epic-inner__icons'
            }),
        );
    };

    // Payment window
    const Content = () => {
        return window.wp.htmlEntities.decodeEntities( settings.description || '' );
    };

    /* Hosted fields


    function loadExternalModule(scriptUrl, onLoadCallback) {
        const scriptEl = document.createElement("script");
        scriptEl.type = "module";
        scriptEl.src = scriptUrl;
        scriptEl.onload = onLoadCallback;
        document.body.appendChild(scriptEl);
        console.log("Dynamically loading external script:", scriptUrl);
    }

    function transactionAcceptedCallback(event) {
        onTransactionComplete(true);
    }

    function transactionDeclinedCallback(event) {
        onTransactionComplete(false);
    }
    
    function clientRedirectCallback(event) {
        return false; // Skipping redirect
    }

    function onTransactionComplete(success) {
        // Find the hosted fields container
        const fieldsContainer = document.getElementById("fields");

        // Hide fields and payment button
        fieldsContainer.style.display = "none";

        // Create a new container for the transaction message
        const messageContainer = document.createElement("div");
        messageContainer.id = "transaction-message";
        messageContainer.style.marginTop = "20px";

        // Insert the appropriate message based on the transaction result
        if (success) {
            messageContainer.innerHTML = '<h2>✅ Payment Success</h2><p>Your transaction has been completed successfully.</p>';
        } else {
            messageContainer.innerHTML = '<h2>❌ Payment Failed</h2><p>We\'re sorry, but your transaction could not be completed.</p>';
        }

        // Append the message container to the DOM
        if (fieldsContainer && fieldsContainer.parentNode) {
            fieldsContainer.parentNode.appendChild(messageContainer);
        } else {
            document.body.appendChild(messageContainer);
        }
    }


    // const Content = () => {
    const Content = ( props ) => {

    const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;


        console.log('Load Content');

        useEffect(() => {

            const createPaymentSession = async () => {

                try {
                    const response = await fetch('/wp-json/epay/v1/create-session', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ test: true }),
                        credentials: 'same-origin',
                    });

                    console.log('Session start:');
                    const data = await response.json();
                    console.log('Session data:', data);

                    const sessionId = data.payment_session.session.id;
                    const sessionKey = data.payment_session.key;
                    const amount = data.payment_session.session.amount;
                    const currency = data.payment_session.session.currency;
                    
                    // Dynamically load the external payment module using the URL from the response
                    loadExternalModule(data.payment_session_js_url, () => {
                        // Initialize hosted fields with session data and callbacks
                        epay
                        .setSessionId(sessionId)
                        .setSessionKey(sessionKey)
                        .setCallbacks({
                            // clientReady: clientReadyCallback,
                            // invalidSession: invalidSessionCallback,
                            // challengeIssued: challengeIssuedCallback,
                            transactionAccepted: transactionAcceptedCallback,
                            transactionDeclined: transactionDeclinedCallback,
                            clientRedirect: clientRedirectCallback,
                            // invalidInput: invalidInputCallback,
                            // inputValidity: inputValidityCallback,
                            // inputSubmit: inputSubmitCallback,
                            // sessionExpired: sessionExpiredCallback,
                            // error: errorCallback
                        })
                        .init();

                        // Mount the hosted fields into the container with id "fields"
                        epay.mountFields("fields", {
                        });

                        console.log('Mount fields');
                    });

                    // Fx initialiser iframe eller redirect
                    // initEpayIframe(data.session_url);
                } catch (error) {
                    console.error('Fejl ved oprettelse af session:', error);
                }
            };

            createPaymentSession();

            const unsubscribe = onPaymentSetup( async () => {
                
                epay.createCardTransaction();



                console.log('✅ placeOrder blev kaldt');
		        // throw new Error('Betalingssession kunne ikke oprettes');
		        return true;
            });

	    }, [
            emitResponse.responseTypes.ERROR,
		    emitResponse.responseTypes.SUCCESS,
	        onPaymentSetup
	    ] );

        return createElement('div', {},
            createElement('div', {
                dangerouslySetInnerHTML: {
                    __html: window.wp.htmlEntities.decodeEntities(settings.description || '')
                }
            }),
            createElement('div', {
                id: 'fields',
                style: { display: 'block', marginTop: '1em' }
            }), 
            createElement('div', { 
                id: 'error-block',
                style: { display: 'block', marginTop: '1em' }
            }),
        );
    };
    */

    const Block_Gateway = {
        name: gateway_id,
        label: Object( window.wp.element.createElement )( Label, null ),
        content: Object( window.wp.element.createElement )( Content, null ),
        edit: Object( window.wp.element.createElement )( Content, null ),
        canMakePayment: () => true,
        /* Hosted fields
        async placeOrder() {
            console.log('Before Place ordre');
            alert('test');
            throw new Error('Noget gik galt');
        },
         */
        ariaLabel: ariaLabel,
        supports: {
            features: settings.supports,
        },
    };

    console.log('Registrerer ePay Blocks gateway...');
    window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );
});
