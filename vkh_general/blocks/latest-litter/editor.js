(function (wp) {
    var createElement = wp.element.createElement;
    var Preview = typeof wp.serverSideRender === 'function' ? wp.serverSideRender : (wp.serverSideRender.ServerSideRender || wp.serverSideRender.default);
    wp.blocks.registerBlockType('vkh/latest-litter', {
        apiVersion: 3,
        title: 'VKH: Aktueller Wurf',
        category: 'widgets',
        icon: 'heart',
        description: 'Welpen des neuesten Wurfs mit Ampelstatus.',
        supports: {html: false},
        edit: function () {
            return createElement('div', wp.blockEditor.useBlockProps(), createElement(Preview, {block: 'vkh/latest-litter'}));
        },
        save: function () { return null; }
    });
})(window.wp);