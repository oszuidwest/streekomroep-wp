// Classic Editor toolbar button that wraps the selection in the [uitklap] shortcode.
(function () {
    var BLOCK_PATTERN = /<(p|h[1-6]|ul|ol|blockquote|figure|div|table|pre)\b/i;

    tinymce.PluginManager.add('zw_uitklap', function (editor) {
        function paragraph(text) {
            return '<p>' + tinymce.DOM.encode(text) + '</p>';
        }

        function insert(kop, open) {
            // Square brackets would end the shortcode early and double quotes its attribute.
            var attribute = kop.replace(/\[/g, '(').replace(/\]/g, ')').replace(/"/g, "'");
            var opening = paragraph('[uitklap kop="' + attribute + '"' + (open ? ' open' : '') + ']');
            var closing = paragraph('[/uitklap]');
            var selection = editor.selection.getContent({format: 'html'});
            var body = BLOCK_PATTERN.test(selection) ? selection : '<p>' + (selection || 'Tekst') + '</p>';

            editor.insertContent(opening + body + closing);
        }

        editor.addButton('zw_uitklap', {
            icon: 'dashicon dashicons-plus-alt',
            tooltip: 'Uitklapbaar blok',
            onclick: function () {
                editor.windowManager.open({
                    title: 'Uitklapbaar blok',
                    body: [
                        {type: 'textbox', name: 'kop', label: 'Kop', minWidth: 320},
                        {type: 'checkbox', name: 'open', label: 'Weergave', text: 'Standaard uitgeklapt'},
                    ],
                    onsubmit: function (e) {
                        var kop = (e.data.kop || '').trim();
                        if (!kop) {
                            e.preventDefault();
                            editor.windowManager.alert('Vul een kop in.');
                            return;
                        }
                        insert(kop, !!e.data.open);
                    },
                });
            },
        });
    });
})();
