// Classic Editor plugin for collapsible sections: a <div class="collapsible"> with a
// heading and one or more editable <details class="collapsible-item"> items. TinyMCE 4.9 keeps
// every <details> expanded while editing and parks the stored open attribute in
// data-mce-open, which it writes back on save; that attribute therefore doubles as
// the "expanded by default" flag.
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

        function caretAtStartOf(element) {
            editor.selection.setCursorLocation(element, 0);
        }

        function caretAtEndOf(element) {
            editor.selection.select(element, true);
            editor.selection.collapse(false);
        }

        function createItem(body) {
            return editor.dom.create('details', {'class': 'collapsible-item', open: 'open'}, '<summary>Kop</summary>' + body);
        }

        // The editor stylesheet shows a placeholder in flagged headings; CSS alone
        // cannot tell an emptied heading (bogus <br>) from one with text and a <br>.
        function flagEmptyHeadings() {
            tinymce.each(editor.dom.select(TITLE + ', ' + ITEM + ' > summary'), function (heading) {
                if (editor.dom.isEmpty(heading)) {
                    heading.setAttribute(EMPTY_FLAG, '');
                } else {
                    heading.removeAttribute(EMPTY_FLAG);
                }
            });
        }

        // The editor stylesheet outlines the item at the caret so it is clear which
        // item the floating toolbar acts on; the title gets no mark of its own.
        function markActiveItem(element) {
            var active = element && itemOf(element);
            tinymce.each(editor.dom.select(ITEM + '[' + ACTIVE_FLAG + ']'), function (item) {
                if (item !== active) {
                    item.removeAttribute(ACTIVE_FLAG);
                }
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

        // The title must stay an h3 and every item must start with a summary; the
        // format dropdown or a stray paste can break that, so put it back.
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

        // Typing fires input; TinyMCE's own delete handling fires NodeChange instead.
        editor.on('SetContent NodeChange input', function () {
            repairStructure();
            flagEmptyHeadings();
        });

        // Title and item headings are plain text; item bodies allow paragraph-level
        // formatting only. Saving enforces the same rules (CollapsibleNormalizer).
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
            // The format dropdown already shows the chosen level; a node change resets it.
            window.setTimeout(function () {
                editor.nodeChanged();
            }, 0);
        });

        // The state label is the heading's ::after box, absolutely positioned within the bar.
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

        // TinyMCE already keeps a click on a heading from collapsing the item;
        // clicking its state label flips how the item opens on the site.
        editor.on('click', function (e) {
            var summary = closest(e.target, 'summary');
            var item = summary && itemOf(summary);
            if (item && clickedStateLabel(summary, e)) {
                toggleOpenByDefault(item);
            }
        });

        editor.on('init', function () {
            // A collapsed item could not be reopened by clicking, so undo any other way of closing it.
            editor.getBody().addEventListener('toggle', function (e) {
                if (editor.dom.is(e.target, ITEM) && !e.target.open) {
                    e.target.open = true;
                }
            }, true);

            // TinyMCE prepends its own delete handlers during init, so register after
            // init and prepend again to get in front of them.
            editor.on('keydown', onKeyDown, true);
        });

        // Later handlers (TinyMCE's own delete logic) would still act on the new caret position.
        function handled(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }

        function onKeyDown(e) {
            var dom = editor.dom;
            var start = editor.selection.getStart();
            var range = editor.selection.getRng();
            var block = dom.getParent(start, dom.isBlock);
            var group = groupOf(start);

            if (e.isDefaultPrevented()) {
                return;
            }

            // Delete just before a section would pull its title into the paragraph.
            if (!group) {
                if (e.keyCode === VK.DELETE && range.collapsed && block && block.nextSibling && dom.is(block.nextSibling, GROUP) && caretAtEdge(block, range, 'end')) {
                    handled(e);
                }
                return;
            }

            var title = closest(start, TITLE);
            var item = itemOf(start);
            var summary = closest(start, 'summary');

            if (e.keyCode === VK.ENTER && !e.shiftKey && (title || summary)) {
                // Enter in a heading moves on instead of breaking the heading.
                handled(e);
                if (!range.collapsed) {
                    editor.selection.setContent('');
                }
                if (title) {
                    var firstItem = group.querySelector(ITEM);
                    if (!firstItem) {
                        firstItem = createItem(EMPTY_PARAGRAPH);
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
                // Enter on an empty line at the end of the section leaves it. The split
                // may have taken the last item's only paragraph along; give it a new one.
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
                if (summary && dom.isEmpty(summary)) {
                    // An empty heading stays; an entirely empty item goes away.
                    handled(e);
                    if (item && dom.isEmpty(item)) {
                        removeItem(item);
                    }
                    return;
                }
                if (title && (dom.isEmpty(title) || caretAtEdge(title, range, 'start'))) {
                    handled(e);
                    return;
                }
                if (summary && caretAtEdge(summary, range, 'start')) {
                    // Would merge the heading into the title or the previous item.
                    handled(e);
                    return;
                }
                if (item && block && block.parentNode === item && block.previousSibling === item.querySelector('summary') && caretAtEdge(block, range, 'start')) {
                    // Would merge the text into the heading.
                    handled(e);
                }
                return;
            }

            if (e.keyCode === VK.DELETE) {
                var heading = title || summary;
                if (heading && caretAtEdge(heading, range, 'end')) {
                    // Would pull the following text into the heading.
                    handled(e);
                    return;
                }
                if (item && block && block.parentNode === item && !block.nextSibling && caretAtEdge(block, range, 'end')) {
                    // Would pull the next heading into this item's text.
                    handled(e);
                }
            }
        }

        function removeItem(item) {
            editor.undoManager.transact(function () {
                var previous = item.previousElementSibling;
                editor.dom.remove(item);
                caretAtEndOf(editor.dom.is(previous, ITEM) ? previous.lastElementChild : previous);
            });
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
                // Leave the placeholder title selected so typing replaces it.
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
                var item = createItem(EMPTY_PARAGRAPH);
                if (current) {
                    editor.dom.insertAfter(item, current);
                } else {
                    // From the title, the new item goes right below it.
                    group.insertBefore(item, group.querySelector(ITEM));
                }
                editor.selection.select(item.querySelector('summary'), true);
            });
            caretMoved();
        }

        function toggleOpenByDefault(item) {
            editor.undoManager.transact(function () {
                // Native setAttribute: TinyMCE's setAttrib drops empty values.
                if (item.hasAttribute(OPEN_FLAG)) {
                    item.removeAttribute(OPEN_FLAG);
                } else {
                    item.setAttribute(OPEN_FLAG, '');
                }
            });
            editor.nodeChanged();
        }

        // The floating toolbar follows the caret on click and keyup only, so a toolbar
        // action that moves the caret has to announce it.
        function caretMoved() {
            editor.nodeChanged();
            editor.fire('keyup', {keyCode: 0});
        }

        // Replaces the section with an empty paragraph so the caret has somewhere to go.
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

        // Drops the item with its heading and text; undo brings it back. Without
        // items left the section itself goes.
        function deleteItem() {
            var item = itemOf(editor.selection.getStart());
            var group = item && groupOf(item);
            if (!group) {
                return;
            }
            editor.undoManager.transact(function () {
                var previous = item.previousElementSibling;
                editor.dom.remove(item);
                if (!group.querySelector(ITEM)) {
                    deleteGroup(group);
                    return;
                }
                caretAtEndOf(editor.dom.is(previous, ITEM) ? previous.lastElementChild : previous);
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
            text: 'Onderdeel verwijderen',
            tooltip: 'Verwijder dit onderdeel met kop en tekst',
            onclick: deleteItem,
        });

        editor.addButton('zw_collapsible_remove', {
            text: 'Blok verwijderen',
            tooltip: 'Verwijder het hele blok met titel, koppen en tekst',
            onclick: deleteSection,
        });

        // TinyMCE places the toolbar right below the matched element, so match the
        // item or title at the caret rather than the section, which can be tall.
        editor.addContextToolbar(ITEM, 'zw_collapsible_add zw_collapsible_remove_item zw_collapsible_remove');
        editor.addContextToolbar(TITLE, 'zw_collapsible_add zw_collapsible_remove');

        // TinyMCE measures from the edit area, but WordPress pads that area to make
        // room for its pinned toolbar, which puts every floating toolbar that far too high.
        editor.settings.inline_toolbar_position_handler = function (rects) {
            var area = editor.getContentAreaContainer().getBoundingClientRect();
            var frame = editor.iframeElement.getBoundingClientRect();
            return {
                left: rects.panelRect.left + frame.left - area.left,
                top: rects.panelRect.top + frame.top - area.top,
            };
        };
    });
})();
