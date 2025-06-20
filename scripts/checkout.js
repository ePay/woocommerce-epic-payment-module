const epic_gateways = [['Epay_EPIC_Payment', 'epay_epic_dk'], ['Epay_EPIC_MobilePay', 'epay_epic_mobilepay'], ['Epay_EPIC_ApplePay', 'epay_epic_applepay'], ['Epay_EPIC_ViaBill', 'epay_epic_viabill'], ['Epay_EPIC_PayPal', 'epay_epic_paypal']];

epic_gateways.forEach(gateway => {

    const gateway_class = gateway[0];
    const gateway_id = gateway[1];

    const settings = window.wc.wcSettings.getSetting( gateway_class + '_data', {} );
    
    const ariaLabel = window.wp.htmlEntities.decodeEntities( settings.title );
    
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

    const Content = () => {
        return window.wp.htmlEntities.decodeEntities( settings.description || '' );
    };

    const Block_Gateway = {
        // name: 'epay_dk',
        name: gateway_id,
        label: Object( window.wp.element.createElement )( Label, null ),
        content: Object( window.wp.element.createElement )( Content, null ),
        edit: Object( window.wp.element.createElement )( Content, null ),
        canMakePayment: () => true,
        ariaLabel: ariaLabel,
        supports: {
            features: settings.supports,
        },
    };
    window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );

});

/*
const settings2 = window.wc.wcSettings.getSetting( 'Epay_MobilePay_data', {} );
const label2 = window.wp.htmlEntities.decodeEntities( settings2.title );

const Content2 = () => {
    return window.wp.htmlEntities.decodeEntities( settings2.description || '' );
};

const Block_Gateway2 = {
    name: 'epay_mobilepay',
    label: label2,
    content: Object( window.wp.element.createElement )( Content2, null ),
    edit: Object( window.wp.element.createElement )( Content2, null ),
    canMakePayment: () => true,
    ariaLabel: label2,
    supports: {
        features: settings2.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway2 );
*/
