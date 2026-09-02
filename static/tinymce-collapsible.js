// Classic Editor support for collapsible sections. TinyMCE keeps each item open
// while editing and stores its front-end state in data-mce-open.
(function () {
    var GROUP = 'div.collapsible';
    var TITLE = 'h3.collapsible-title';
    var ITEM = 'details.collapsible-item';
    var OPEN_FLAG = 'data-mce-open';
    var EMPTY_FLAG = 'data-zw-empty';
    var ACTIVE_FLAG = 'data-zw-active';
    var BOGUS_BR = '<br data-mce-bogus="1">';
    var EMPTY_PARAGRAPH = '<p>' + BOGUS_BR + '</p>';
    var VK = tinymce.util.VK;

    tinymce.PluginManager.add('zw_collapsible', function (editor) {
        function closest(element, selector) {
            return editor.dom.getParent(element, selector);
        }

        function groupOf(element) {
            return closest(element, GROUP);
        }

        function itemOf(element) {
            return closest(element, ITEM);
        }

        function emptyParagraph() {
            return editor.dom.create('p', {}, BOGUS_BR);
        }

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

        function caretAtStartOf(element) {
            editor.selection.setCursorLocation(element, 0);
        }

        function caretAtEndOf(element) {
            editor.selection.select(element, true);
            editor.selection.collapse(false);
        }

        function createItem() {
            return editor.dom.create('details', {'class': 'collapsible-item', open: 'open'}, '<summary>Kop</summary>' + EMPTY_PARAGRAPH);
        }

        // TinyMCE's bogus line breaks make empty headings ambiguous to CSS.
        function flagEmptyHeadings() {
            tinymce.each(editor.dom.select(TITLE + ', ' + ITEM + ' > summary'), function (heading) {
                if (editor.dom.isEmpty(heading)) {
                    heading.setAttribute(EMPTY_FLAG, '');
                } else {
                    heading.removeAttribute(EMPTY_FLAG);
                }
            });
        }

        // Mark the item targeted by the floating toolbar.
        function markActiveItem(element) {
            var active = element && itemOf(element);
            tinymce.each(editor.dom.select(ITEM + '[' + ACTIVE_FLAG + ']'), function (item) {
                item.removeAttribute(ACTIVE_FLAG);
            });
            if (active) {
                active.setAttribute(ACTIVE_FLAG, '');
            }
        }

        editor.on('NodeChange', function (e) {
            markActiveItem(e.element);
        });

        editor.on('blur', function () {
            markActiveItem(null);
        });

        editor.on('PreInit', function () {
            editor.serializer.addAttributeFilter(EMPTY_FLAG + ',' + ACTIVE_FLAG, function (nodes, name) {
                tinymce.each(nodes, function (node) {
                    node.attr(name, null);
                });
            });
        });

        // Preserve the section structure after formatting or pasting.
        function repairStructure() {
            var dom = editor.dom;
            tinymce.each(dom.select(GROUP), function (group) {
                var title = group.querySelector('.collapsible-title');
                if (title && title.nodeName !== 'H3') {
                    dom.rename(title, 'h3');
                }
                tinymce.each(dom.select(ITEM, group), function (item) {
                    var first = item.firstElementChild;
                    if (first && /^(H[1-6]|P|DIV)$/.test(first.nodeName)) {
                        dom.rename(first, 'summary');
                    }
                });
            });
        }

        editor.on('SetContent NodeChange input', function () {
            repairStructure();
            flagEmptyHeadings();
        });

        // Restrict editing commands to markup the normalizer accepts.
        var HEADING_COMMANDS = /^(FormatBlock|mceToggleFormat|mceBlockQuote|InsertUnorderedList|InsertOrderedList|Bold|Italic|Underline|Strikethrough|WP_Link|mceInsertLink|mceLink|mceInsertRawHTML)$/;
        var BODY_COMMANDS = /^(mceBlockQuote|mceInsertRawHTML)$/;
        var BLOCK_FORMATS = /^(h[1-6]|pre|blockquote|div|address|aside)$/i;

        function blocked(e) {
            var selection = editor.selection;
            var start = selection.getStart();
            var end = selection.getEnd();
            var heading = closest(start, TITLE + ', summary') || closest(end, TITLE + ', summary');
            if (heading && groupOf(heading)) {
                return HEADING_COMMANDS.test(e.command);
            }
            if (itemOf(start) || itemOf(end)) {
                return BODY_COMMANDS.test(e.command)
                    || ((e.command === 'FormatBlock' || e.command === 'mceToggleFormat') && BLOCK_FORMATS.test(e.value));
            }
            return false;
        }

        editor.on('BeforeExecCommand', function (e) {
            if (!blocked(e)) {
                return;
            }
            e.preventDefault();
            // Reset the format dropdown after blocking its command.
            window.setTimeout(function () {
                editor.nodeChanged();
            }, 0);
        });

        // Detect clicks on the generated state control.
        function clickedStateLabel(summary, e) {
            var style = editor.getWin().getComputedStyle(summary, '::after');
            var rect = summary.getBoundingClientRect();
            var left = rect.left + (parseFloat(style.left) || 0);
            var top = rect.top + (parseFloat(style.top) || 0);
            var width = parseFloat(style.width) || 0;
            var height = parseFloat(style.height) || 0;
            return width > 0
                && e.clientX >= left && e.clientX <= left + width
                && e.clientY >= top && e.clientY <= top + height;
        }

        editor.on('click', function (e) {
            var summary = closest(e.target, 'summary');
            var item = summary && itemOf(summary);
            if (item && clickedStateLabel(summary, e)) {
                toggleOpenByDefault(item);
            }
        });

        editor.on('init', function () {
            // Keep items open while editing.
            editor.getBody().addEventListener('toggle', function (e) {
                if (editor.dom.is(e.target, ITEM) && !e.target.open) {
                    e.target.open = true;
                }
            }, true);

            // Run before TinyMCE's delete handlers.
            editor.on('keydown', onKeyDown, true);
        });

        function handled(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }

        function onKeyDown(e) {
            if (e.isDefaultPrevented() || (e.keyCode !== VK.ENTER && e.keyCode !== VK.BACKSPACE && e.keyCode !== VK.DELETE)) {
                return;
            }

            var dom = editor.dom;
            var start = editor.selection.getStart();
            var range = editor.selection.getRng();
            var block = dom.getParent(start, dom.isBlock);
            var group = groupOf(start);

            if (!group) {
                if (e.keyCode === VK.DELETE && range.collapsed && block && block.nextSibling && dom.is(block.nextSibling, GROUP) && caretAtEdge(block, range, 'end')) {
                    handled(e);
                }
                return;
            }

            var title = closest(start, TITLE);
            var item = itemOf(start);
            var summary = closest(start, 'summary');
            var heading = title || summary;

            if (e.keyCode === VK.ENTER && !e.shiftKey && heading) {
                // Keep headings intact when Enter is pressed.
                handled(e);
                if (!range.collapsed) {
                    editor.selection.setContent('');
                }
                if (title) {
                    var firstItem = group.querySelector(ITEM);
                    if (!firstItem) {
                        firstItem = createItem();
                        group.appendChild(firstItem);
                    }
                    caretAtStartOf(firstItem.querySelector('summary'));
                } else {
                    var next = summary.nextSibling;
                    if (!next || !dom.isBlock(next)) {
                        next = emptyParagraph();
                        dom.insertAfter(next, summary);
                    }
                    caretAtStartOf(next);
                }
                return;
            }

            if (e.keyCode === VK.ENTER && !e.shiftKey && block && block.parentNode === group && !title && dom.isEmpty(block)) {
                // Leave the section without emptying its final item.
                handled(e);
                var lastItem = block.previousElementSibling;
                if (lastItem && dom.is(lastItem, ITEM) && !lastItem.querySelector('summary').nextSibling) {
                    lastItem.appendChild(emptyParagraph());
                }
                dom.insertAfter(block, group);
                caretAtStartOf(block);
                return;
            }

            if (!range.collapsed) {
                return;
            }

            if (e.keyCode === VK.BACKSPACE) {
                if (heading && caretAtEdge(heading, range, 'start')) {
                    // Keep headings separate and remove empty items.
                    handled(e);
                    if (item && dom.isEmpty(item)) {
                        editor.undoManager.transact(function () {
                            removeItem(item);
                        });
                    }
                    return;
                }
                if (item && block && block.parentNode === item && block.previousSibling === item.querySelector('summary') && caretAtEdge(block, range, 'start')) {
                    handled(e);
                }
                return;
            }

            if (e.keyCode === VK.DELETE) {
                if (heading && caretAtEdge(heading, range, 'end')) {
                    handled(e);
                    return;
                }
                if (item && block && block.parentNode === item && !block.nextSibling && caretAtEdge(block, range, 'end')) {
                    handled(e);
                }
            }
        }

        function removeItem(item) {
            var previous = item.previousElementSibling;
            editor.dom.remove(item);
            caretAtEndOf(editor.dom.is(previous, ITEM) ? previous.lastElementChild : previous);
        }

        function insertSection() {
            var dom = editor.dom;
            if (groupOf(editor.selection.getStart())) {
                addItem();
                return;
            }

            var selection = editor.selection.getContent({format: 'html'});
            var body = /<(p|h[1-6]|ul|ol|blockquote|figure|div|table|pre)\b/i.test(selection) ? selection : '<p>' + selection + '</p>';

            editor.undoManager.transact(function () {
                editor.insertContent(
                    '<div class="collapsible" id="zw-collapsible-new"><h3 class="collapsible-title">Titel</h3>'
                    + '<details class="collapsible-item"><summary>Kop</summary>' + body + '</details></div>'
                );
                var group = dom.get('zw-collapsible-new');
                if (!group) {
                    return;
                }
                dom.setAttrib(group, 'id', null);
                if (!group.previousElementSibling) {
                    group.parentNode.insertBefore(emptyParagraph(), group);
                }
                editor.selection.select(group.querySelector(TITLE), true);
            });
        }

        function addItem() {
            var start = editor.selection.getStart();
            var group = groupOf(start);
            if (!group) {
                return;
            }
            var current = itemOf(start);
            editor.undoManager.transact(function () {
                var item = createItem();
                if (current) {
                    editor.dom.insertAfter(item, current);
                } else {
                    group.insertBefore(item, group.querySelector(ITEM));
                }
                editor.selection.select(item.querySelector('summary'), true);
            });
            caretMoved();
        }

        function toggleOpenByDefault(item) {
            editor.undoManager.transact(function () {
                editor.dom.setAttrib(item, OPEN_FLAG, item.hasAttribute(OPEN_FLAG) ? null : 'open');
            });
            editor.nodeChanged();
        }

        function caretMoved() {
            editor.nodeChanged();
        }

        function deleteGroup(group) {
            var paragraph = emptyParagraph();
            editor.dom.replace(paragraph, group);
            caretAtStartOf(paragraph);
        }

        function deleteSection() {
            var group = groupOf(editor.selection.getStart());
            if (!group) {
                return;
            }
            editor.undoManager.transact(function () {
                deleteGroup(group);
            });
            caretMoved();
        }

        function deleteItem() {
            var item = itemOf(editor.selection.getStart());
            var group = item && groupOf(item);
            if (!group) {
                return;
            }
            editor.undoManager.transact(function () {
                if (editor.dom.select(ITEM, group).length === 1) {
                    deleteGroup(group);
                } else {
                    removeItem(item);
                }
            });
            caretMoved();
        }

        editor.addButton('zw_collapsible', {
            icon: 'dashicon dashicons-plus-alt',
            tooltip: 'Uitklapbaar blok',
            onclick: insertSection,
        });

        editor.addButton('zw_collapsible_add', {
            text: 'Onderdeel toevoegen',
            tooltip: 'Voeg een uitklapbaar onderdeel toe',
            onclick: addItem,
        });

        editor.addButton('zw_collapsible_remove_item', {
            icon: 'dashicon dashicons-trash',
            tooltip: 'Verwijder dit onderdeel met kop en tekst',
            onclick: deleteItem,
        });

        editor.addButton('zw_collapsible_remove', {
            text: 'Hele blok verwijderen',
            tooltip: 'Verwijder het hele blok met titel, koppen en tekst',
            onclick: deleteSection,
        });

        // Anchor WordPress's toolbar to the active title or item.
        editor.on('init', function () {
            var itemToolbar = editor.wp._createToolbar(['zw_collapsible_add', 'zw_collapsible_remove_item', 'zw_collapsible_remove'], true);
            var titleToolbar = editor.wp._createToolbar(['zw_collapsible_add', 'zw_collapsible_remove'], true);

            editor.on('wptoolbar', function (e) {
                if (e.toolbar) {
                    return;
                }
                var item = itemOf(e.element);
                var title = !item && closest(e.element, TITLE);
                if (item || title) {
                    e.selection = item || title;
                    e.toolbar = item ? itemToolbar : titleToolbar;
                }
            });
        });
    });
})();
