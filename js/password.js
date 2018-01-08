if (typeof OnCoreClient === 'undefined') {
    var OnCoreClient = {};
}

OnCoreClient.passwordFieldHandler = function($element) {
    $element.prop('type', 'password');
};
