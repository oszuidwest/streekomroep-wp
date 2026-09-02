// Classic Editor plugin for collapsible sections: an editable <details class="uitklap">
// block with its heading in <summary>. TinyMCE 4.9 keeps every <details> expanded
// while editing and parks the stored open attribute in data-mce-open, which it writes
// back on save; that attribute therefore doubles as the "expanded by default" flag.
(function () {
    var SELECTOR = 'details.uitklap';
    var OPEN_FLAG = 'data-mce-open';
    var BLOCK_PATTERN = /<(p|h[1-6]|ul|ol|blockquote|figure|div|table|pre)\b/i;
    var ENTER = 13;
    var BACKSPACE = 8;
    var DELETE = 46;

    tinymce.PluginManager.add('zw_uitklap', function (editor) {
        function sectionOf(element) {
            return editor.dom.getParent(element, SELECTOR);
        }

        function summaryOf(details) {
            return details.querySelector('summary');
        }

        // Nothing is selected between the range start and the edge of the block.
        function caretAtEdge(block, range, edge) {
            var probe = range.cloneRange();
            if (edge === 'start') {
                probe.setStart(block, 0);
                probe.setEnd(range.startContainer, range.startOffset);
            } else {
                probe.setStart(range.endContainer, range.endOffset);
                probe.setEnd(block, block.childNodes.length);
            }
            return probe.toString() === '';
        }

        // Clicking a heading must place the caret, not collapse the section.
        editor.on('click', function (e) {
            if (editor.dom.getParent(e.target, 'summary') && sectionOf(e.target)) {
                e.preventDefault();
            }
        });

        editor.on('init', function () {
            editor.getBody().addEventListener('toggle', function (e) {
                if (editor.dom.is(e.target, SELECTOR) && !e.target.open) {
                    e.target.open = true;
                }
            }, true);
        });

        // Later handlers (TinyMCE's own delete logic) would still act on the new caret position.
        function handled(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }

        // TinyMCE prepends its own delete handlers during init, so register after init
        // and prepend again to get in front of them.
        editor.on('init', function () {
            editor.on('keydown', onKeyDown, true);
        });

        function onKeyDown(e) {
            var dom = editor.dom;
            var start = editor.selection.getStart();
            var details = sectionOf(start);
            if (!details || e.isDefaultPrevented()) {
                return;
            }

            var range = editor.selection.getRng();
            var summary = dom.getParent(start, 'summary');

            if (e.keyCode === ENTER && summary && !e.shiftKey) {
                // Enter in the heading moves on to the text instead of breaking the heading.
                handled(e);
                var next = summary.nextSibling;
                if (!next || !dom.isBlock(next)) {
                    next = dom.create('p', {}, '<br data-mce-bogus="1">');
                    dom.insertAfter(next, summary);
                }
                editor.selection.setCursorLocation(next, 0);
                return;
            }

            if (!range.collapsed) {
                return;
            }

            if (e.keyCode === BACKSPACE && summary && dom.isEmpty(summary)) {
                // An empty heading stays; an entirely empty section goes away.
                handled(e);
                if (dom.isEmpty(details)) {
                    editor.undoManager.transact(function () {
                        var paragraph = dom.create('p', {}, '<br data-mce-bogus="1">');
                        dom.replace(paragraph, details);
                        editor.selection.setCursorLocation(paragraph, 0);
                    });
                }
                return;
            }

            if (e.keyCode === DELETE && summary && caretAtEdge(summary, range, 'end')) {
                // Delete at the end of the heading would pull the first paragraph into it.
                handled(e);
                return;
            }

            var block = dom.getParent(start, dom.isBlock);
            var firstBlock = block && block.parentNode === details && block.previousSibling === summaryOf(details);
            if (e.keyCode === BACKSPACE && firstBlock && caretAtEdge(block, range, 'start')) {
                // Backspace at the start of the text would merge it into the heading.
                handled(e);
            }
        }

        function insertSection() {
            var dom = editor.dom;
            var selection = editor.selection.getContent({format: 'html'});
            var body = BLOCK_PATTERN.test(selection) ? selection : '<p>' + selection + '</p>';

            editor.undoManager.transact(function () {
                editor.insertContent('<details class="uitklap" id="zw-uitklap-new"><summary>Kop</summary>' + body + '</details>');
                var details = dom.get('zw-uitklap-new');
                if (!details) {
                    return;
                }
                dom.setAttrib(details, 'id', null);
                // Leave the placeholder heading selected so typing replaces it.
                editor.selection.select(summaryOf(details), true);
            });
        }

        function toggleOpenByDefault() {
            var details = sectionOf(editor.selection.getStart());
            if (!details) {
                return;
            }
            editor.undoManager.transact(function () {
                // Native setAttribute: TinyMCE's setAttrib drops empty values.
                if (details.hasAttribute(OPEN_FLAG)) {
                    details.removeAttribute(OPEN_FLAG);
                } else {
                    details.setAttribute(OPEN_FLAG, '');
                }
            });
            editor.nodeChanged();
        }

        // Turns the section back into ordinary paragraphs, keeping the heading as bold text.
        function unwrapSection() {
            var dom = editor.dom;
            var details = sectionOf(editor.selection.getStart());
            if (!details) {
                return;
            }
            editor.undoManager.transact(function () {
                var summary = summaryOf(details);
                if (summary && dom.isEmpty(summary)) {
                    dom.remove(summary);
                } else if (summary) {
                    var strong = dom.create('strong');
                    while (summary.firstChild) {
                        strong.appendChild(summary.firstChild);
                    }
                    var paragraph = dom.create('p');
                    paragraph.appendChild(strong);
                    dom.replace(paragraph, summary);
                }
                dom.remove(details, true);
            });
            editor.nodeChanged();
        }

        editor.addButton('zw_uitklap', {
            icon: 'dashicon dashicons-plus-alt',
            tooltip: 'Uitklapbaar blok',
            onclick: insertSection,
        });

        editor.addButton('zw_uitklap_open', {
            text: 'Standaard uitgeklapt',
            tooltip: 'Toon dit blok op de site meteen uitgeklapt',
            onclick: toggleOpenByDefault,
            onPostRender: function () {
                var button = this;
                editor.on('NodeChange', function (e) {
                    var details = sectionOf(e.element);
                    button.active(!!details && details.hasAttribute(OPEN_FLAG));
                });
            },
        });

        editor.addButton('zw_uitklap_remove', {
            text: 'Blok opheffen',
            tooltip: 'Haal het blok weg; kop en tekst blijven staan',
            onclick: unwrapSection,
        });

        editor.addContextToolbar(SELECTOR, 'zw_uitklap_open zw_uitklap_remove');
    });
})();
