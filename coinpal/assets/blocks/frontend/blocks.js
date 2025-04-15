const settingsCoinpal = window.wc.wcSettings.getSetting( 'coinpal_data', {} );

const iconCoinpal = () => {
    return window.wp.element.createElement("img", {
        src: settingsCoinpal.plugin_url,
        alt: "Coinpal",
        style: { marginLeft: "40px", verticalAlign: "middle" }
    });
};

const labelCoinpal = window.wp.element.createElement(
    "span",
    { style: {  } },
    window.wp.htmlEntities.decodeEntities(settingsCoinpal.title) || window.wp.i18n.__('Coinpal', 'wc-coinpal'),
    window.wp.element.createElement(iconCoinpal)
);


const contentCoinpal = () => {
    return window.wp.element.createElement(
        "div",
        null,
        window.wp.htmlEntities.decodeEntities(settingsCoinpal.description || '')
    );
};

const Block_Coinpal_Gateway = {
    name: 'coinpal',
    label: labelCoinpal,
    content: Object( window.wp.element.createElement )( contentCoinpal, null ),
    edit: Object( window.wp.element.createElement )( contentCoinpal, null ),
    canMakePayment: () => true,
    ariaLabel: 'Coinpal Payment Method',
    supports: {
        features: settingsCoinpal.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Coinpal_Gateway );